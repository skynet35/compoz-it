<?php
require_once 'session_init.php';
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=not_logged_in');
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'run_auto_assign':
                ob_start();
                include 'auto_assign_package_images.php';
                $output = ob_get_clean();
                $message = "Script d'assignation automatique exécuté avec succès.";
                break;
                
            case 'preview_changes':
                try {
                    $pdo = getConnection();
                    $stmt = $pdo->prepare("
                        SELECT d.id, d.name, d.package, d.image_path 
                        FROM data d
                        WHERE (d.image_path IS NULL OR d.image_path = '' OR d.image_path = 'default.png') 
                        AND d.package IS NOT NULL 
                        AND d.package != ''
                        ORDER BY d.package, d.name
                    ");
                    $stmt->execute();
                    $previewData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $previewWithImages = [];
                    $previewWithoutImages = [];
                    
                    $imageDir = __DIR__ . '/img/';
                    foreach ($previewData as $component) {
                        $packageImage = null;
                        $cleanPackageName = preg_replace('/[^a-zA-Z0-9\-_]/', '', $component['package']);
                        $extensions = ['svg', 'png', 'jpg', 'jpeg', 'gif', 'webp'];
                        
                        foreach ($extensions as $ext) {
                            if (file_exists($imageDir . $cleanPackageName . '.' . $ext)) {
                                $packageImage = 'img/' . $cleanPackageName . '.' . $ext;
                                break;
                            }
                            if (file_exists($imageDir . $component['package'] . '.' . $ext)) {
                                $packageImage = 'img/' . $component['package'] . '.' . $ext;
                                break;
                            }
                        }
                        
                        if ($packageImage) {
                            $component['new_image'] = $packageImage;
                            $previewWithImages[] = $component;
                        } else {
                            $previewWithoutImages[] = $component;
                        }
                    }
                } catch (PDOException $e) {
                    $error = "Erreur lors de la prévisualisation : " . $e->getMessage();
                }
                break;
        }
    }
}

try {
    $pdo = getConnection();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM data WHERE (image_path IS NULL OR image_path = '' OR image_path = 'default.png')");
    $stmt->execute();
    $componentsWithoutImage = $stmt->fetch()['count'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM data 
        WHERE (image_path IS NULL OR image_path = '' OR image_path = 'default.png') 
        AND package IS NOT NULL AND package != ''
    ");
    $stmt->execute();
    $componentsWithPackage = $stmt->fetch()['count'];
    
    $imageDir = __DIR__ . '/img/';
    $availableImages = [];
    if (is_dir($imageDir)) {
        $files = scandir($imageDir);
        foreach ($files as $file) {
            if (preg_match('/\.(svg|png|jpg|jpeg|gif|webp)$/i', $file)) {
                $availableImages[] = $file;
            }
        }
    }
    
} catch (PDOException $e) {
    $error = "Erreur de base de données : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionnaire d'Images de Packages - Gestion des Composants</title>
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

        .btn-primary {
            background: var(--accent-indigo);
            color: white;
        }

        .btn-secondary {
            background: var(--accent-blue);
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

        .btn-warning {
            background: var(--accent-amber);
            color: #212529;
        }

        .content {
            padding: 30px;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            border: 2px solid #e9ecef;
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

        .action-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 2px solid #e9ecef;
        }

        .action-section h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.3em;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: bold;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .preview-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .preview-table th,
        .preview-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .preview-table th {
            background: #f8f9fa;
            font-weight: bold;
        }

        .preview-table tr:hover {
            background: #f5f5f5;
        }

        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .image-item {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .image-item img {
            max-width: 60px;
            max-height: 60px;
            margin-bottom: 5px;
        }

        .image-name {
            font-size: 0.8em;
            color: #666;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="app-header">
        <div class="header-top">
            <div class="header-title">
                <div class="header-icon">🖼️</div>
                <div>
                    <h1>Gestion des Images de Boîtiers</h1>
                    <p>Associez les images aux packages (DIP, SOIC, SOT…)</p>
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
            <?php if ($message): ?>
                <div class="alert alert-success">
                    ✅ <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $componentsWithoutImage; ?></div>
                    <div class="stat-label">Composants sans image</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $componentsWithPackage; ?></div>
                    <div class="stat-label">Avec package défini</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($availableImages); ?></div>
                    <div class="stat-label">Images disponibles</div>
                </div>
            </div>

            <div class="action-section">
                <h3>🚀 Actions</h3>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="preview_changes">
                    <button type="submit" class="btn btn-info">
                        👁️ Prévisualiser les changements
                    </button>
                </form>
                
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="run_auto_assign">
                    <button type="submit" class="btn btn-success" onclick="return confirm('Êtes-vous sûr de vouloir exécuter l\'assignation automatique ?')">
                        ⚡ Exécuter l'assignation automatique
                    </button>
                </form>
                
                <a href="settings.php" class="btn btn-primary">
                    ⚙️ Retour aux paramètres
                </a>
            </div>

            <?php if (isset($previewWithImages) || isset($previewWithoutImages)): ?>
                <div class="action-section">
                    <h3>👁️ Prévisualisation des changements</h3>
                    
                    <?php if (!empty($previewWithImages)): ?>
                        <h4 style="color: #28a745; margin-top: 20px;">✅ Composants qui recevront une image (<?php echo count($previewWithImages); ?>)</h4>
                        <p>Ces composants recevront automatiquement une image de package :</p>
                        <table class="preview-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom du composant</th>
                                    <th>Package</th>
                                    <th>Image actuelle</th>
                                    <th>Nouvelle image</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($previewWithImages as $component): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($component['id']); ?></td>
                                        <td><?php echo htmlspecialchars($component['name']); ?></td>
                                        <td><?php echo htmlspecialchars($component['package']); ?></td>
                                        <td><?php echo htmlspecialchars($component['image_path'] ?: 'Aucune'); ?></td>
                                        <td>
                                            <img src="<?php echo htmlspecialchars($component['new_image']); ?>" alt="Package" style="max-width: 40px; max-height: 40px;">
                                            <?php echo htmlspecialchars($component['new_image']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    
                    <?php if (!empty($previewWithoutImages)): ?>
                        <h4 style="color: #dc3545; margin-top: 30px;">❌ Composants sans image trouvée (<?php echo count($previewWithoutImages); ?>)</h4>
                        <p>Ces composants ont un package défini mais aucune image correspondante n'a été trouvée :</p>
                        <table class="preview-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom du composant</th>
                                    <th>Package</th>
                                    <th>Image actuelle</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($previewWithoutImages as $component): ?>
                                    <tr style="background-color: #fff5f5;">
                                        <td><?php echo htmlspecialchars($component['id']); ?></td>
                                        <td><?php echo htmlspecialchars($component['name']); ?></td>
                                        <td><?php echo htmlspecialchars($component['package']); ?></td>
                                        <td><?php echo htmlspecialchars($component['image_path'] ?: 'Aucune'); ?></td>
                                        <td style="color: #dc3545;">❌ Aucune image trouvée</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="action-section">
                <h3>🖼️ Images de packages disponibles</h3>
                <div class="images-grid">
                    <?php foreach ($availableImages as $image): ?>
                        <div class="image-item">
                            <img src="img/<?php echo htmlspecialchars($image); ?>" alt="<?php echo htmlspecialchars($image); ?>" onerror="this.style.display='none'">
                            <div class="image-name"><?php echo htmlspecialchars($image); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
            Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
        </footer>
    </div>
</body>
</html>
