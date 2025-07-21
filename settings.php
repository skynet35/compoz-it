<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=not_logged_in');
    exit();
}

try {
    $pdo = getConnection();
    
    // Récupérer les statistiques
    $stats = [];
    
    // Statistiques des emplacements
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM location WHERE owner = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['locations'] = $stmt->fetch()['count'];
    
    // Statistiques des fournisseurs
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM suppliers WHERE owner = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['suppliers'] = $stmt->fetch()['count'];
    
    // Statistiques des packages
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM packages WHERE owner = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['packages'] = $stmt->fetch()['count'];
    
    // Statistiques des fabricants (depuis la table data)
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT manufacturer) as count FROM data WHERE owner = ? AND manufacturer IS NOT NULL AND manufacturer != ''");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['manufacturers'] = $stmt->fetch()['count'];
    
    // Statistiques des composants
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM data WHERE owner = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['components'] = $stmt->fetch()['count'];
    
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            position: relative;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-align: center;
        }

        .user-info {
            position: absolute;
            top: 20px;
            right: 30px;
            background: rgba(255,255,255,0.15);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .nav-section {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            text-align: center;
        }

        .nav-buttons {
            display: flex;
            gap: 15px;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
        }

        .nav-buttons a {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 12px 24px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-buttons a:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .nav-buttons a.active {
            background: rgba(255, 255, 255, 0.4);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .content {
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

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9em;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
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
        <div class="header">
            <div class="user-info">
                👤 <?php echo htmlspecialchars($_SESSION['user_email']); ?>
                <a href="logout.php" style="margin-left: 15px; color: white; text-decoration: none; background: rgba(255,255,255,0.2); padding: 5px 10px; border-radius: 15px; transition: all 0.3s ease;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">🚪 Déconnexion</a>
            </div>
            <h1>⚙️ Paramètres</h1>
            
            <div class="nav-section">
                <div class="nav-buttons">
                    <a href="components.php">📦 Composants</a>
                    <a href="create_component.php">➕ Créer</a>
                    <a href="projects.php">🚀 Projets</a>
                    <a href="settings.php" class="active">⚙️ Paramètres</a>
                </div>
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
                        Configurez l'accès à la base de données.
                    </div>
                    <div class="setting-actions">
                        <a href="profile.php" class="btn btn-primary">⚙️ Gérer le profil</a>
                        <a href="db_config.php" class="btn btn-info">🗄️ Config. BDD</a>
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
    </div>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>