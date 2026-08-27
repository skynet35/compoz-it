<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'session_init.php';
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=not_logged_in');
    exit();
}

$userId = $_SESSION['user_id'];
$stats = [
    'locations' => 0,
    'suppliers' => 0,
    'packages' => 0,
    'manufacturers' => 0,
    'components' => 0,
    'projects' => 0,
    'categories' => 0,
    'images' => 0
];
$error = null;

try {
    $pdo = getConnection();
    
    // Statistiques des emplacements
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM location WHERE owner = ?");
        $stmt->execute([$userId]);
        $stats['locations'] = (int)$stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['locations'] = 0;
    }
    
    // Statistiques des fournisseurs (certaines tables n'ont pas owner = user, voir structure)
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM suppliers");
        $stmt->execute();
        $stats['suppliers'] = (int)$stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['suppliers'] = 0;
    }
    
    // Statistiques des packages
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM packages");
        $stmt->execute();
        $stats['packages'] = (int)$stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['packages'] = 0;
    }
    
    // Statistiques des fabricants (depuis la table manufacturers si elle existe, sinon depuis data)
    try {
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT name) as count FROM manufacturers");
        $stmt->execute();
        $stats['manufacturers'] = (int)$stmt->fetch()['count'];
    } catch (Exception $e) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT manufacturer) as count FROM data WHERE owner = ? AND manufacturer IS NOT NULL AND manufacturer != ''");
            $stmt->execute([$userId]);
            $stats['manufacturers'] = (int)$stmt->fetch()['count'];
        } catch (Exception $e2) {
            $stats['manufacturers'] = 0;
        }
    }
    
    // Statistiques des composants
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM data WHERE owner = ?");
        $stmt->execute([$userId]);
        $stats['components'] = (int)$stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['components'] = 0;
    }
    
    // Statistiques des projets
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM projects WHERE owner = ?");
        $stmt->execute([$userId]);
        $stats['projects'] = (int)$stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['projects'] = 0;
    }
    
    // Statistiques des catégories
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM category_sub");
        $stats['categories'] = (int)$stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['categories'] = 0;
    }
    
    // Statistiques des images
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM images");
        $stats['images'] = (int)$stmt->fetch()['count'];
    } catch (Exception $e) {
        $stats['images'] = 0;
    }
    
} catch(PDOException $e) {
    $error = "Erreur de base de données : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Gestion des Composants</title>
    <style>
        :root {
            --bg-primary: #f8fafc;
            --bg-card: #ffffff;
            --bg-muted: #f1f5f9;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --border-color: #e2e8f0;
            --border-light: #f1f5f9;
            --accent-indigo: #6366f1;
            --accent-indigo-light: #e0e7ff;
            --accent-purple: #8b5cf6;
            --accent-pink: #ec4899;
            --accent-blue: #3b82f6;
            --accent-green: #10b981;
            --accent-amber: #f59e0b;
            --accent-red: #ef4444;
            --accent-teal: #14b8a6;
            --accent-orange: #f97316;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.08), 0 2px 4px -2px rgba(0,0,0,0.05);
            --shadow-lg: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.05);
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --radius-xl: 20px;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            min-height: 100vh;
            color: var(--text-primary);
        }
        .container {
            max-width: 1480px;
            margin: 0 auto;
            padding: 20px;
        }
        .app-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: var(--radius-xl);
            padding: 28px 32px 32px;
            margin-bottom: 24px;
            box-shadow: 0 20px 40px rgba(102,126,234,0.2);
            position: relative;
            overflow: hidden;
        }
        .app-header::before {
            content: '';
            position: absolute;
            top: -80px;
            right: -80px;
            width: 260px;
            height: 260px;
            background: radial-gradient(circle, rgba(255,255,255,0.15), transparent 70%);
            border-radius: 50%;
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
            z-index: 2;
            margin-bottom: 20px;
        }
        .header-title {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .header-icon {
            width: 52px;
            height: 52px;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            border: 1px solid rgba(255,255,255,0.25);
        }
        .header-title h1 {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .header-title p {
            font-size: 13px;
            opacity: 0.85;
            margin-top: 3px;
        }
        .user-chip {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            padding: 8px 16px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.2);
            font-size: 13px;
        }
        .logout-link {
            color: white;
            text-decoration: none;
            background: rgba(255,255,255,0.2);
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 500;
            transition: background 0.2s;
        }
        .logout-link:hover { background: rgba(255,255,255,0.3); }
        .nav-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 6px;
            position: relative;
            z-index: 2;
        }
        .nav-buttons a {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            color: white;
            padding: 10px 18px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
            border: 1px solid rgba(255,255,255,0.2);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .nav-buttons a:hover {
            background: rgba(255,255,255,0.28);
            transform: translateY(-1px);
        }
        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.18s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            white-space: nowrap;
        }
        .btn:hover { transform: translateY(-1px); box-shadow: var(--shadow-md); }
        .btn:active { transform: translateY(0); }
        .btn-indigo  { background: var(--accent-indigo); color: white; }
        .btn-purple  { background: var(--accent-purple); color: white; }
        .btn-danger  { background: var(--accent-red); color: white; }
        .btn-ghost   { background: var(--bg-muted); color: var(--text-secondary); }
        .btn-ghost:hover { background: #e2e8f0; }
        .btn-sm      { padding: 6px 11px; font-size: 12px; border-radius: 6px; gap: 4px; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; box-shadow: none !important; }

        .content {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 30px;
        }

        .stats-overview {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 2px solid #dee2e6;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 1.1em;
            color: #666;
            font-weight: 600;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .setting-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
            text-align: center;
        }

        .setting-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.15);
            border-color: #667eea;
        }

        .setting-icon {
            font-size: 3em;
            margin-bottom: 20px;
            display: block;
        }

        .setting-title {
            font-size: 1.5em;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
        }

        .setting-description {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .setting-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: var(--accent-indigo);
            color: white;
        }

        .btn-secondary {
            background: var(--text-secondary);
            color: white;
        }

        .btn-success {
            background: var(--accent-green);
            color: white;
        }

        .btn-info {
            background: var(--accent-teal);
            color: white;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: bold;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .quick-actions {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-top: 30px;
            text-align: center;
        }

        .quick-actions h3 {
            margin-bottom: 20px;
            font-size: 1.5em;
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .quick-action-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .quick-action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="app-header">
            <div class="header-top">
                <div class="header-title">
                    <div class="header-icon">⚙️</div>
                    <div>
                        <h1>Paramètres de l'Application</h1>
                        <p>Configurez l'application et vos préférences</p>
                    </div>
                </div>
                <div class="user-chip">
                    <span>👤 <?php echo htmlspecialchars($_SESSION['user_email'] ?? 'Utilisateur'); ?></span>
                    <a href="logout.php" class="logout-link">🚪 Déconnexion</a>
                </div>
            </div>
            <div class="nav-buttons">
                <a href="components.php">📦 Composants</a>
                <a href="create_component.php">➕ Créer</a>
                <a href="projects.php">🚀 Projets</a>
                <a href="settings.php">⚙️ Paramètres</a>
            </div>
        </div>
        <div class="content">
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Vue d'ensemble -->
            <div class="stats-overview">
                <h2>📊 Vue d'ensemble de votre inventaire</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['components']; ?></div>
                        <div class="stat-label">Composants</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['locations']; ?></div>
                        <div class="stat-label">Emplacements</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['suppliers']; ?></div>
                        <div class="stat-label">Fournisseurs</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['packages']; ?></div>
                        <div class="stat-label">Packages</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['manufacturers']; ?></div>
                        <div class="stat-label">Fabricants</div>
                    </div>
                </div>
            </div>

            <!-- Gestion des paramètres -->
            <h2>🔧 Gestion des paramètres</h2>
            <div class="settings-grid">
                <!-- Emplacements -->
                <div class="setting-card">
                    <div class="setting-icon">📍</div>
                    <div class="setting-title">Emplacements</div>
                    <div class="setting-description">
                        Gérez vos emplacements de stockage : tiroirs, étagères, boîtes, etc.
                        Organisez votre inventaire physique.
                    </div>
                    <div class="setting-actions">
                        <a href="locations.php" class="btn btn-primary">📋 Voir la liste</a>
                        <a href="locations.php#add" class="btn btn-success">➕ Ajouter</a>
                    </div>
                </div>

                <!-- Fournisseurs -->
                <div class="setting-card">
                    <div class="setting-icon">🏢</div>
                    <div class="setting-title">Fournisseurs</div>
                    <div class="setting-description">
                        Gérez vos fournisseurs : coordonnées, contacts, sites web.
                        Centralisez vos sources d'approvisionnement.
                    </div>
                    <div class="setting-actions">
                        <a href="suppliers.php" class="btn btn-primary">📋 Voir la liste</a>
                        <a href="suppliers_management.php" class="btn btn-info">⚙️ Gestion avancée</a>
                    </div>
                </div>

                <!-- Packages -->
                <div class="setting-card">
                    <div class="setting-icon">📦</div>
                    <div class="setting-title">Packages</div>
                    <div class="setting-description">
                        Gérez les types de boîtiers : DIP, SOIC, QFP, BGA, etc.
                        Définissez les caractéristiques techniques.
                    </div>
                    <div class="setting-actions">
                        <a href="packages_management.php" class="btn btn-primary">📋 Gestion complète</a>
                    </div>
                </div>

                <!-- Fabricants -->
                <div class="setting-card">
                    <div class="setting-icon">🏭</div>
                    <div class="setting-title">Fabricants</div>
                    <div class="setting-description">
                        Consultez la liste des fabricants de vos composants.
                        Analysez la répartition par marque.
                    </div>
                    <div class="setting-actions">
                        <a href="manufacturers.php" class="btn btn-primary">📋 Voir la liste</a>
                    </div>
                </div>

                <!-- Catégories -->
                <div class="setting-card">
                    <div class="setting-icon">🏷️</div>
                    <div class="setting-title">Catégories</div>
                    <div class="setting-description">
                        Gérez les catégories et sous-catégories de vos composants.
                        Organisez votre inventaire par type et fonction.
                    </div>
                    <div class="setting-actions">
                        <a href="categories_management.php" class="btn btn-primary">🏷️ Gestion complète</a>
                    </div>
                </div>

                <!-- Import/Export -->
                <div class="setting-card">
                    <div class="setting-icon">💾</div>
                    <div class="setting-title">Import/Export</div>
                    <div class="setting-description">
                        Sauvegardez et restaurez votre base de données.
                        Importez des exemples de données pour commencer.
                    </div>
                    <div class="setting-actions">
                        <a href="export_formats.php" class="btn btn-success">📤 Export</a>
                        <a href="import_formats.php" class="btn btn-info">📥 Import</a>
                        <a href="load_sample_data.php" class="btn btn-secondary">🎯 Données d'exemple</a>
                        <a href="package_images_manager.php" class="btn btn-primary">🖼️ Scanner les images</a>
                        <a href="cleanup_empty_components.php" class="btn btn-danger">🧹 Nettoyer composants vides</a>
                    </div>
                </div>

                <!-- Profile -->
                <div class="setting-card">
                    <div class="setting-icon">👤</div>
                    <div class="setting-title">Profile</div>
                    <div class="setting-description">
                        Gérez votre profil utilisateur : email, mot de passe.
                    </div>
                    <div class="setting-actions">
                        <a href="profile.php" class="btn btn-primary">⚙️ Gérer le profil</a>
                    </div>
                </div>
            </div>

            <!-- Actions rapides -->
            <div class="quick-actions">
                <h3>🚀 Actions rapides</h3>
                <div class="quick-actions-grid">
                    <a href="create_component.php" class="quick-action-btn">
                        ➕ Nouveau composant
                    </a>
                    <a href="components.php" class="quick-action-btn">
                        📦 Voir l'inventaire
                    </a>
                    <a href="locations.php" class="quick-action-btn">
                        📍 Gérer emplacements
                    </a>
                    <a href="suppliers_management.php" class="quick-action-btn">
                        🏢 Gérer fournisseurs
                    </a>
                    <a href="categories_management.php" class="quick-action-btn">
                        🏷️ Gérer catégories
                    </a>
                </div>
            </div>
        </div>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
    </div>
</body>
</html>