<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=not_logged_in');
    exit();
}

$message = '';
$error = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'run_auto_assign':
                // Exécuter le script d'assignation automatique
                ob_start();
                include 'auto_assign_package_images.php';
                $output = ob_get_clean();
                $message = "Script d'assignation automatique exécuté avec succès.";
                break;
                
            case 'preview_changes':
                // Prévisualiser les changements sans les appliquer
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
                    
                    // Séparer les composants avec et sans images trouvées
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

// Récupérer les statistiques
try {
    $pdo = getConnection();
    
    // Composants sans image
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM data WHERE (image_path IS NULL OR image_path = '' OR image_path = 'default.png')");
    $stmt->execute();
    $componentsWithoutImage = $stmt->fetch()['count'];
    
    // Composants sans image mais avec package
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM data 
        WHERE (image_path IS NULL OR image_path = '' OR image_path = 'default.png') 
        AND package IS NOT NULL AND package != ''
    ");
    $stmt->execute();
    $componentsWithPackage = $stmt->fetch()['count'];
    
    // Images disponibles dans le dossier img/
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
            max-width: 1200px;
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
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .content {
            padding: 30px;
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
            margin: 5px;
        }

        .btn-primary {
            background: #667eea;
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
        <div class="header">
            <h1>🖼️ Gestionnaire d'Images de Packages</h1>
            <p>Assignation automatique des images aux composants</p>
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

            <!-- Statistiques -->
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

            <!-- Actions -->
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

            <!-- Prévisualisation -->
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

            <!-- Images disponibles -->
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
    </div>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>