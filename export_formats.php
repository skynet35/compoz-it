<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=not_logged_in');
    exit();
}

$user_id = $_SESSION['user_id'];
$format = isset($_GET['format']) ? $_GET['format'] : '';

if ($format) {
    try {
        $pdo = getConnection();
        
        // Récupérer toutes les données de l'utilisateur
        $data = [];
        
        // Export direct de toutes les colonnes de la table data
        $stmt = $pdo->prepare("
            SELECT * FROM data WHERE owner = ? ORDER BY name
        ");
        $stmt->execute([$user_id]);
        $components = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $filename = 'export_composants_' . date('Y-m-d_H-i-s');
        
        switch ($format) {
            case 'csv':
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
                
                // BOM pour UTF-8
                echo "\xEF\xBB\xBF";
                
                if (!empty($components)) {
                    // En-têtes
                    $headers = array_keys($components[0]);
                    echo implode(';', $headers) . "\n";
                    
                    // Données
                    foreach ($components as $row) {
                        $escaped_row = [];
                        foreach ($row as $value) {
                            $escaped_row[] = '"' . str_replace('"', '""', $value ?? '') . '"';
                        }
                        echo implode(';', $escaped_row) . "\n";
                    }
                }
                break;
                
            case 'json':
                header('Content-Type: application/json; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '.json"');
                
                $export_data = [
                    'export_info' => [
                        'date' => date('Y-m-d H:i:s'),
                        'user_id' => $user_id,
                        'total_components' => count($components)
                    ],
                    'components' => $components
                ];
                
                echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;
                
            case 'html':
                header('Content-Type: text/html; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '.html"');
                
                echo '<!DOCTYPE html>';
                echo '<html lang="fr">';
                echo '<head>';
                echo '<meta charset="UTF-8">';
                echo '<title>Export Composants - ' . date('Y-m-d H:i:s') . '</title>';
                echo '<style>';
                echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
                echo 'table { border-collapse: collapse; width: 100%; }';
                echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
                echo 'th { background-color: #f2f2f2; font-weight: bold; }';
                echo 'tr:nth-child(even) { background-color: #f9f9f9; }';
                echo '.header { margin-bottom: 20px; }';
                echo '</style>';
                echo '</head>';
                echo '<body>';
                echo '<div class="header">';
                echo '<h1>Export des Composants</h1>';
                echo '<p>Généré le : ' . date('Y-m-d H:i:s') . '</p>';
                echo '<p>Nombre total de composants : ' . count($components) . '</p>';
                echo '</div>';
                
                if (!empty($components)) {
                    echo '<table>';
                    echo '<thead><tr>';
                    foreach (array_keys($components[0]) as $header) {
                        echo '<th>' . htmlspecialchars($header) . '</th>';
                    }
                    echo '</tr></thead>';
                    echo '<tbody>';
                    foreach ($components as $row) {
                        echo '<tr>';
                        foreach ($row as $value) {
                            echo '<td>' . htmlspecialchars($value ?? '') . '</td>';
                        }
                        echo '</tr>';
                    }
                    echo '</tbody>';
                    echo '</table>';
                }
                
                echo '
    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body></html>';
                break;
                
            case 'xlsx':
                // Pour Excel, on va créer un CSV avec des tabulations
                header('Content-Type: application/vnd.ms-excel; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
                
                echo "\xEF\xBB\xBF"; // BOM UTF-8
                
                if (!empty($components)) {
                    // En-têtes
                    echo implode("\t", array_keys($components[0])) . "\n";
                    
                    // Données
                    foreach ($components as $row) {
                        $escaped_row = [];
                        foreach ($row as $value) {
                            $escaped_row[] = str_replace(["\t", "\n", "\r"], [' ', ' ', ' '], $value ?? '');
                        }
                        echo implode("\t", $escaped_row) . "\n";
                    }
                }
                break;
                
            case 'txt':
                header('Content-Type: text/plain; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $filename . '.txt"');
                
                echo "Export des Composants\n";
                echo "======================\n\n";
                echo "Généré le : " . date('Y-m-d H:i:s') . "\n";
                echo "Nombre total de composants : " . count($components) . "\n\n";
                
                foreach ($components as $i => $component) {
                    echo "Composant #" . ($i + 1) . "\n";
                    echo str_repeat('-', 20) . "\n";
                    foreach ($component as $key => $value) {
                        echo ucfirst($key) . " : " . ($value ?? 'N/A') . "\n";
                    }
                    echo "\n";
                }
                break;
                
            case 'sql':
                // Rediriger vers l'ancien export SQL
                header('Location: export_database.php');
                exit();
                
            default:
                header('Location: export_formats.php?error=format_invalid');
                exit();
        }
        
        exit();
        
    } catch(Exception $e) {
        header('Location: export_formats.php?error=export_failed&message=' . urlencode($e->getMessage()));
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formats d'Export - Gestion des Composants</title>
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
            max-width: 1000px;
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

        .formats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .format-card {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .format-card:hover {
            border-color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .format-icon {
            font-size: 3em;
            margin-bottom: 15px;
        }

        .format-title {
            font-size: 1.5em;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }

        .format-description {
            color: #666;
            line-height: 1.5;
        }

        .format-features {
            margin-top: 15px;
            font-size: 0.9em;
            color: #888;
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
            font-size: 16px;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .actions {
            text-align: center;
            margin-top: 30px;
        }

        .info-box {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            color: #004085;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📤 Formats d'Export</h1>
            <p>Choisissez le format d'export qui vous convient</p>
        </div>

        <div class="content">
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error">
                    ❌ 
                    <?php 
                    switch($_GET['error']) {
                        case 'format_invalid':
                            echo 'Format d\'export invalide.';
                            break;
                        case 'export_failed':
                            echo 'Erreur lors de l\'export : ' . htmlspecialchars($_GET['message'] ?? 'Erreur inconnue');
                            break;
                        default:
                            echo 'Une erreur est survenue.';
                    }
                    ?>
                </div>
            <?php endif; ?>

            <div class="info-box">
                <h3>📋 Informations sur l'export</h3>
                <p>Tous les formats incluent vos composants avec leurs informations complètes : nom, description, catégorie, quantité, emplacement, etc.</p>
            </div>

            <div class="formats-grid">
                <a href="?format=csv" class="format-card">
                    <div class="format-icon">📊</div>
                    <div class="format-title">CSV</div>
                    <div class="format-description">
                        Format tableur universel, compatible avec Excel, LibreOffice, Google Sheets
                    </div>
                    <div class="format-features">
                        ✓ Séparateur point-virgule<br>
                        ✓ Encodage UTF-8 avec BOM
                    </div>
                </a>

                <a href="?format=json" class="format-card">
                    <div class="format-icon">🔧</div>
                    <div class="format-title">JSON</div>
                    <div class="format-description">
                        Format structuré pour développeurs et intégrations API
                    </div>
                    <div class="format-features">
                        ✓ Structure hiérarchique<br>
                        ✓ Métadonnées incluses
                    </div>
                </a>

                <a href="?format=html" class="format-card">
                    <div class="format-icon">🌐</div>
                    <div class="format-title">HTML</div>
                    <div class="format-description">
                        Page web avec tableau formaté, prête à imprimer
                    </div>
                    <div class="format-features">
                        ✓ Mise en forme automatique<br>
                        ✓ Prêt pour impression
                    </div>
                </a>

                <a href="?format=xlsx" class="format-card">
                    <div class="format-icon">📈</div>
                    <div class="format-title">Excel</div>
                    <div class="format-description">
                        Format Excel natif avec colonnes séparées
                    </div>
                    <div class="format-features">
                        ✓ Compatible Microsoft Excel<br>
                        ✓ Séparateur tabulation
                    </div>
                </a>

                <a href="?format=txt" class="format-card">
                    <div class="format-icon">📝</div>
                    <div class="format-title">Texte</div>
                    <div class="format-description">
                        Format texte simple, lisible par tous les éditeurs
                    </div>
                    <div class="format-features">
                        ✓ Format universel<br>
                        ✓ Présentation structurée
                    </div>
                </a>

                <a href="?format=sql" class="format-card">
                    <div class="format-icon">🗄️</div>
                    <div class="format-title">SQL</div>
                    <div class="format-description">
                        Sauvegarde complète de la base de données
                    </div>
                    <div class="format-features">
                        ✓ Restauration complète<br>
                        ✓ Toutes les tables incluses
                    </div>
                </a>
            </div>

            <div class="actions">
                <a href="settings.php" class="btn btn-secondary">🔙 Retour aux paramètres</a>
            </div>
        </div>
    </div>
</body>
</html>