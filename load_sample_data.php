<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=not_logged_in');
    exit();
}

$user_id = $_SESSION['user_id'];
$format = isset($_GET['export']) ? $_GET['export'] : '';

if ($format) {
    $filename = 'donnees_exemple_' . date('Y-m-d_H-i-s');
    
    // Données d'exemple au format de la table data avec l'ordre exact des colonnes d'export
    $sample_components = [
        [
            'id' => 1,
            'owner' => $user_id,
            'name' => 'Résistance 1kΩ',
            'manufacturer' => 'Vishay',
            'package' => 'THT',
            'pins' => 2,
            'smd' => 'No',
            'quantity' => 100,
            'location_id' => 1,
            'order_quantity' => 0,
            'price' => 0.05,
            'datasheet' => '',
            'comment' => 'Résistance carbone 1/4W 5%',
            'category' => 1301,
            'public' => 'No',
            'url' => '',
            'image_path' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'supplier_id' => 1,
            'supplier_reference' => 'CFR-25JB-52-1K0'
        ],
        [
            'id' => 2,
            'owner' => $user_id,
            'name' => 'Résistance 10kΩ',
            'manufacturer' => 'Vishay',
            'package' => 'THT',
            'pins' => 2,
            'smd' => 'No',
            'quantity' => 50,
            'location_id' => 1,
            'order_quantity' => 0,
            'price' => 0.05,
            'datasheet' => '',
            'comment' => 'Résistance carbone 1/4W 5%',
            'category' => 1301,
            'public' => 'No',
            'url' => '',
            'image_path' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'supplier_id' => 2,
            'supplier_reference' => 'CFR-25JB-52-10K'
        ],
        [
            'id' => 3,
            'owner' => $user_id,
            'name' => 'Condensateur 100nF',
            'manufacturer' => 'Murata',
            'package' => '0805',
            'pins' => 2,
            'smd' => 'Yes',
            'quantity' => 200,
            'location_id' => 5,
            'order_quantity' => 0,
            'price' => 0.10,
            'datasheet' => '',
            'comment' => 'Condensateur céramique 50V',
            'category' => 201,
            'public' => 'No',
            'url' => '',
            'image_path' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'supplier_id' => 3,
            'supplier_reference' => 'GRM21BR71H104KA01L'
        ],
        [
            'id' => 4,
            'owner' => $user_id,
            'name' => 'Condensateur 10µF',
            'manufacturer' => 'Panasonic',
            'package' => 'THT',
            'pins' => 2,
            'smd' => 'No',
            'quantity' => 30,
            'location_id' => 3,
            'order_quantity' => 0,
            'price' => 0.15,
            'datasheet' => '',
            'comment' => 'Condensateur électrolytique 25V',
            'category' => 202,
            'public' => 'No',
            'url' => '',
            'image_path' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'supplier_id' => 4,
            'supplier_reference' => 'ECA-1EM100'
        ],
        [
            'id' => 5,
            'owner' => $user_id,
            'name' => 'Diode 1N4148',
            'manufacturer' => 'ON Semiconductor',
            'package' => 'SOT-23',
            'pins' => 2,
            'smd' => 'Yes',
            'quantity' => 25,
            'location_id' => 5,
            'order_quantity' => 0,
            'price' => 0.08,
            'datasheet' => '',
            'comment' => 'Diode de commutation rapide',
            'category' => 403,
            'public' => 'No',
            'url' => '',
            'image_path' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'supplier_id' => 1,
            'supplier_reference' => 'MMBD4148T1G'
        ]
    ];

    switch ($format) {
        case 'csv':
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            
            // BOM pour UTF-8
            echo "\xEF\xBB\xBF";
            
            if (!empty($sample_components)) {
                // En-têtes (toutes les colonnes de la table data)
                $headers = array_keys($sample_components[0]);
                echo implode(';', $headers) . "\n";
                
                // Données
                foreach ($sample_components as $row) {
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
                    'total_components' => count($sample_components)
                ],
                'components' => $sample_components
            ];
            
            echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'sql':
            header('Content-Type: application/sql; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.sql"');
            
            echo "-- Export de données d'exemple pour l'import de composants\n";
            echo "-- Généré le : " . date('Y-m-d H:i:s') . "\n";
            echo "-- Utilisateur : " . (isset($_SESSION['user_email']) ? $_SESSION['user_email'] : 'Utilisateur ID: ' . $user_id) . "\n\n";
            
            echo "-- \n-- Export des données : Composants d'exemple (data)\n-- \n\n";
            
            if (!empty($sample_components)) {
                // Générer les requêtes INSERT au format export_database.php
                $columns = array_keys($sample_components[0]);
                echo "-- Suppression des données existantes pour cette table\n";
                echo "DELETE FROM `data` WHERE owner = $user_id;\n\n";
                
                echo "-- Insertion des nouvelles données\n";
                echo "INSERT INTO `data` (`" . implode('`, `', $columns) . "`) VALUES\n";
                
                $values = [];
                foreach ($sample_components as $row) {
                    $escaped_values = [];
                    foreach ($row as $value) {
                        if ($value === null || $value === '') {
                            $escaped_values[] = 'NULL';
                        } elseif (is_numeric($value)) {
                            $escaped_values[] = $value;
                        } else {
                            $escaped_values[] = "'" . str_replace("'", "''", $value) . "'";
                        }
                    }
                    $values[] = '(' . implode(', ', $escaped_values) . ')';
                }
                
                echo implode(",\n", $values) . ";\n\n";
            } else {
                echo "-- Aucune donnée trouvée pour cette table\n\n";
            }
            
            echo "-- Fin de l'export\n";
            echo "-- Total des composants exportés : " . count($sample_components) . "\n";
            break;
            
        case 'html':
            header('Content-Type: text/html; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.html"');
            
            echo "<!DOCTYPE html>\n<html lang='fr'>\n<head>\n";
            echo "<meta charset='UTF-8'>\n";
            echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
            echo "<title>Données d'exemple - Composants</title>\n";
            echo "<style>\n";
            echo "body { font-family: Arial, sans-serif; margin: 20px; }\n";
            echo "table { border-collapse: collapse; width: 100%; }\n";
            echo "th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }\n";
            echo "th { background-color: #f2f2f2; }\n";
            echo "tr:nth-child(even) { background-color: #f9f9f9; }\n";
            echo "</style>\n</head>\n<body>\n";
            echo "<h1>Données d'exemple - Composants électroniques</h1>\n";
            echo "<p>Généré le : " . date('Y-m-d H:i:s') . "</p>\n";
            echo "<table>\n<thead>\n<tr>\n";
            
            if (!empty($sample_components)) {
                foreach (array_keys($sample_components[0]) as $header) {
                    echo "<th>" . htmlspecialchars($header) . "</th>\n";
                }
                echo "</tr>\n</thead>\n<tbody>\n";
                
                foreach ($sample_components as $row) {
                    echo "<tr>\n";
                    foreach ($row as $value) {
                        echo "<td>" . htmlspecialchars($value ?? '') . "</td>\n";
                    }
                    echo "</tr>\n";
                }
                echo "</tbody>\n";
            }
            
            echo "</table>\n
    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>\n</html>";
            break;
            
        case 'xlsx':
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            echo "\xEF\xBB\xBF"; // BOM UTF-8
            
            if (!empty($sample_components)) {
                echo implode("\t", array_keys($sample_components[0])) . "\n";
                foreach ($sample_components as $row) {
                    $escaped_row = [];
                    foreach ($row as $value) {
                        $escaped_row[] = '"' . str_replace('"', '""', $value ?? '') . '"';
                    }
                    echo implode("\t", $escaped_row) . "\n";
                }
            }
            break;
            
        case 'txt':
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.txt"');
            
            echo "Données d'exemple - Composants électroniques\n";
            echo "Généré le : " . date('Y-m-d H:i:s') . "\n";
            echo str_repeat("=", 50) . "\n\n";
            
            if (!empty($sample_components)) {
                foreach ($sample_components as $index => $row) {
                    echo "Composant #" . ($index + 1) . "\n";
                    echo str_repeat("-", 20) . "\n";
                    foreach ($row as $key => $value) {
                        echo $key . ": " . ($value ?? 'N/A') . "\n";
                    }
                    echo "\n";
                }
            }
            break;
            
        default:
            header('Location: load_sample_data.php');
            exit;
    }
    exit;
}

// Gestion de l'affichage des ID des emplacements
if (isset($_GET['show_locations'])) {
    require_once 'config.php';
    
    // Vérifier si l'utilisateur est connecté
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php?error=not_logged_in');
        exit();
    }
    
    $user_id = $_SESSION['user_id'];
    $pdo = getConnection();
    
    header('Content-Type: text/html; charset=utf-8');
    
    echo "<!DOCTYPE html>\n<html lang='fr'>\n<head>\n";
    echo "<meta charset='UTF-8'>\n";
    echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
    echo "<title>ID des Emplacements</title>\n";
    echo "<style>\n";
    echo "body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }\n";
    echo "table { border-collapse: collapse; width: 100%; background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }\n";
    echo "th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }\n";
    echo "th { background-color: #28a745; color: white; }\n";
    echo "tr:nth-child(even) { background-color: #f9f9f9; }\n";
    echo "tr:hover { background-color: #e8f5e8; }\n";
    echo ".back-btn { display: inline-block; background: #6c757d; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; margin-bottom: 20px; }\n";
    echo ".back-btn:hover { background: #5a6268; }\n";
    echo "h1 { color: #333; }\n";
    echo "</style>\n</head>\n<body>\n";
    echo "<a href='load_sample_data.php' class='back-btn'>← Retour</a>\n";
    echo "<h1>📍 ID des Emplacements</h1>\n";
    
    try {
        $stmt = $pdo->prepare("SELECT id, casier, tiroir, compartiment, description FROM location WHERE owner = ? ORDER BY casier, tiroir, compartiment");
        $stmt->execute([$user_id]);
        $locations = $stmt->fetchAll();
        
        if ($locations) {
            echo "<table>\n<thead>\n<tr>\n";
            echo "<th>ID</th><th>Casier</th><th>Tiroir</th><th>Compartiment</th><th>Description</th>\n";
            echo "</tr>\n</thead>\n<tbody>\n";
            
            foreach ($locations as $location) {
                echo "<tr>\n";
                echo "<td><strong>" . htmlspecialchars($location['id']) . "</strong></td>\n";
                echo "<td>" . htmlspecialchars($location['casier'] ?? '') . "</td>\n";
                echo "<td>" . htmlspecialchars($location['tiroir'] ?? '') . "</td>\n";
                echo "<td>" . htmlspecialchars($location['compartiment'] ?? '') . "</td>\n";
                echo "<td>" . htmlspecialchars($location['description'] ?? '') . "</td>\n";
                echo "</tr>\n";
            }
            echo "</tbody>\n</table>\n";
        } else {
            echo "<p>Aucun emplacement trouvé. Créez d'abord des emplacements dans la section 'Emplacements'.</p>\n";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Erreur lors de la récupération des emplacements : " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
    
    echo "</body>\n</html>";
    exit;
}

// Gestion de l'affichage des ID des catégories
if (isset($_GET['show_categories'])) {
    require_once 'config.php';
    
    // Vérifier si l'utilisateur est connecté
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php?error=not_logged_in');
        exit();
    }
    
    $user_id = $_SESSION['user_id'];
    $pdo = getConnection();
    
    header('Content-Type: text/html; charset=utf-8');
    
    echo "<!DOCTYPE html>\n<html lang='fr'>\n<head>\n";
    echo "<meta charset='UTF-8'>\n";
    echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
    echo "<title>ID des Catégories</title>\n";
    echo "<style>\n";
    echo "body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }\n";
    echo "table { border-collapse: collapse; width: 100%; background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }\n";
    echo "th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }\n";
    echo "th { background-color: #17a2b8; color: white; }\n";
    echo "tr:nth-child(even) { background-color: #f9f9f9; }\n";
    echo "tr:hover { background-color: #e1f5fe; }\n";
    echo ".back-btn { display: inline-block; background: #6c757d; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; margin-bottom: 20px; }\n";
    echo ".back-btn:hover { background: #5a6268; }\n";
    echo "h1 { color: #333; }\n";
    echo "h2 { color: #17a2b8; margin-top: 30px; }\n";
    echo "</style>\n</head>\n<body>\n";
    echo "<a href='load_sample_data.php' class='back-btn'>← Retour</a>\n";
    echo "<h1>📂 ID des Catégories</h1>\n";
    
    try {
        // Récupération des catégories principales
        $stmt = $pdo->prepare("SELECT id, name FROM category_head ORDER BY id");
        $stmt->execute();
        $main_categories = $stmt->fetchAll();
        
        // Récupération des sous-catégories
        $stmt = $pdo->prepare("SELECT id, name, category_head_id FROM category_sub ORDER BY id");
        $stmt->execute();
        $sub_categories = $stmt->fetchAll();
        
        // Organiser les sous-catégories par catégorie principale
        $subcategories_by_head = [];
        foreach ($sub_categories as $subcat) {
            $subcategories_by_head[$subcat['category_head_id']][] = $subcat;
        }
        
        if ($main_categories) {
            echo "<h2>🌳 Arbre des catégories</h2>\n";
            echo "<div style='font-family: monospace; line-height: 1.6; background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff;'>\n";
            
            foreach ($main_categories as $category) {
                echo "<div style='margin-bottom: 10px;'>\n";
                echo "<strong style='color: #007bff;'>" . htmlspecialchars($category['id']) . ":</strong> " . htmlspecialchars($category['name']) . "\n";
                
                // Afficher les sous-catégories de cette catégorie principale
                if (isset($subcategories_by_head[$category['id']])) {
                    foreach ($subcategories_by_head[$category['id']] as $subcategory) {
                        echo "<br>&nbsp;&nbsp;&nbsp;&nbsp;<span style='color: #28a745;'>" . htmlspecialchars($subcategory['id']) . ":</span> " . htmlspecialchars($subcategory['name']) . "\n";
                    }
                }
                echo "</div>\n";
            }
            
            echo "</div>\n";
        }
        
        if (!$main_categories && !$sub_categories) {
            echo "<p>Aucune catégorie trouvée. Créez d'abord des catégories dans la section 'Gestion des catégories'.</p>\n";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Erreur lors de la récupération des catégories : " . htmlspecialchars($e->getMessage()) . "</p>\n";
    }
    
    echo "</body>\n</html>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Données d'Exemple - Gestion des Composants</title>
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

        .description {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 0 8px 8px 0;
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
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .format-card:hover {
            border-color: #667eea;
            background: #f8f9ff;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
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
            margin-bottom: 15px;
        }

        .format-details {
            font-size: 0.9em;
            color: #888;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
        }

        .back-button {
            display: inline-block;
            background: #6c757d;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: background 0.3s ease;
        }

        .back-button:hover {
            background: #5a6268;
        }

        .info-box {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .info-box h3 {
            color: #1976d2;
            margin-bottom: 10px;
        }

        .info-box ul {
            margin-left: 20px;
            color: #424242;
        }

        .info-box li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📦 Données d'Exemple</h1>
            <p>Téléchargez des fichiers d'exemple pour tester l'import</p>
        </div>

        <div class="content">
            <div class="description">
                <h3>💡 Comment utiliser ces fichiers ?</h3>
                <p>Téléchargez un fichier d'exemple dans le format de votre choix, puis utilisez la fonction d'import pour l'importer dans votre base de données. Ces fichiers contiennent des données d'exemple représentatives pour vous aider à comprendre le format attendu.</p>
            </div>

            <div class="info-box">
                <h3>📋 Contenu des fichiers d'exemple :</h3>
                <ul>
                    <li><strong>10 composants variés</strong> : résistances, condensateurs, diodes, LED, modules</li>
                    <li><strong>Catégories diversifiées</strong> : résistances, condensateurs, semiconducteurs, modules, etc.</li>
                    <li><strong>Emplacements d'exemple</strong> : tiroirs, boîtes SMD, étagères</li>
                    <li><strong>Fournisseurs réels</strong> : Mouser, Digi-Key, Farnell, RS Components</li>
                    <li><strong>Données complètes</strong> : nom, description, package, quantité, prix</li>
                </ul>
            </div>

            <div class="info-box">
                <h3>🔍 Outils de référence :</h3>
                <p>Consultez les ID actuels de votre base de données pour adapter vos fichiers d'import :</p>
                <div style="display: flex; gap: 15px; margin-top: 15px; flex-wrap: wrap;">
                    <a href="?show_locations=1" class="back-button" style="background: #28a745; text-decoration: none; color: white;">
                        📍 Voir les ID des emplacements
                    </a>
                    <a href="?show_categories=1" class="back-button" style="background: #17a2b8; text-decoration: none; color: white;">
                        📂 Voir les ID des catégories
                    </a>
                </div>
            </div>

            <div class="formats-grid">
                <a href="?export=csv" class="format-card">
                    <div class="format-icon">📊</div>
                    <div class="format-title">CSV</div>
                    <div class="format-description">
                        Format tableur universel avec séparateur point-virgule
                    </div>
                    <div class="format-details">
                        <strong>Idéal pour :</strong> Excel, LibreOffice, Google Sheets<br>
                        <strong>Encodage :</strong> UTF-8 avec BOM
                    </div>
                </a>

                <a href="?export=json" class="format-card">
                    <div class="format-icon">🔧</div>
                    <div class="format-title">JSON</div>
                    <div class="format-description">
                        Format structuré avec métadonnées complètes
                    </div>
                    <div class="format-details">
                        <strong>Idéal pour :</strong> Développeurs, APIs, scripts<br>
                        <strong>Structure :</strong> Hiérarchique avec informations d'export
                    </div>
                </a>

                <a href="?export=html" class="format-card">
                    <div class="format-icon">🌐</div>
                    <div class="format-title">HTML</div>
                    <div class="format-description">
                        Page web avec tableau formaté
                    </div>
                    <div class="format-details">
                        <strong>Idéal pour :</strong> Visualisation, impression<br>
                        <strong>Format :</strong> Tableau HTML stylé
                    </div>
                </a>

                <a href="?export=xlsx" class="format-card">
                    <div class="format-icon">📈</div>
                    <div class="format-title">XLSX</div>
                    <div class="format-description">
                        Fichier Excel compatible (format CSV-TSV)
                    </div>
                    <div class="format-details">
                        <strong>Idéal pour :</strong> Microsoft Excel<br>
                        <strong>Séparateur :</strong> Tabulation
                    </div>
                </a>

                <a href="?export=sql" class="format-card">
                    <div class="format-icon">🗄️</div>
                    <div class="format-title">SQL</div>
                    <div class="format-description">
                        Requêtes SQL prêtes à exécuter
                    </div>
                    <div class="format-details">
                        <strong>Idéal pour :</strong> Import direct en base<br>
                        <strong>Contenu :</strong> INSERT avec IDs de catégories réels
                    </div>
                </a>

                <a href="?export=txt" class="format-card">
                    <div class="format-icon">📄</div>
                    <div class="format-title">TXT</div>
                    <div class="format-description">
                        Fichier texte formaté lisible
                    </div>
                    <div class="format-details">
                        <strong>Idéal pour :</strong> Lecture simple, documentation<br>
                        <strong>Format :</strong> Texte structuré
                    </div>
                </a>
            </div>

            <div class="info-box">
                <h3>🚀 Étapes suivantes :</h3>
                <ul>
                    <li><strong>1.</strong> Téléchargez un fichier d'exemple dans le format souhaité</li>
                    <li><strong>2.</strong> Examinez la structure et adaptez vos propres données</li>
                    <li><strong>3.</strong> Utilisez la fonction "Import" dans les paramètres</li>
                    <li><strong>4.</strong> Sélectionnez votre fichier modifié pour l'importer</li>
                </ul>
            </div>

            <a href="settings.php" class="back-button">← Retour aux paramètres</a>
        </div>
    </div>
</body>
</html>