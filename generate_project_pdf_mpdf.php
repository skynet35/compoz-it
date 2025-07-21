<?php
// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'auth.php';
require_once 'vendor/autoload.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Récupérer l'ID du projet
$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($project_id <= 0) {
    die('ID de projet invalide');
}

// Récupérer les informations du projet
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
$stmt->execute([$project_id, $_SESSION['user_id']]);
$project = $stmt->fetch();

if (!$project) {
    die('Projet non trouvé');
}

// Récupérer les composants du projet
$stmt = $pdo->prepare("
    SELECT pc.quantity_needed, pc.quantity_used, c.name, c.manufacturer, c.package, c.description, c.quantity as stock, c.price
    FROM project_components pc 
    JOIN components c ON pc.component_id = c.id 
    WHERE pc.project_id = ?
    ORDER BY c.name
");
$stmt->execute([$project_id]);
$components = $stmt->fetchAll();

// Récupérer les travaux et matériaux
$stmt = $pdo->prepare("
    SELECT item_type, item_name, description, quantity, unit, unit_price, total_price 
    FROM project_items 
    WHERE project_id = ?
    ORDER BY item_type, item_name
");
$stmt->execute([$project_id]);
$items = $stmt->fetchAll();

// Récupérer les fichiers du projet
$stmt = $pdo->prepare("
    SELECT display_name, file_size, uploaded_at 
    FROM project_files 
    WHERE project_id = ?
    ORDER BY uploaded_at DESC
");
$stmt->execute([$project_id]);
$files = $stmt->fetchAll();

// Fonction pour formater la taille des fichiers
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

// Calculer les totaux
$total_components_cost = 0;
foreach ($components as $component) {
    $total_components_cost += ($component['price'] ?? 0) * $component['quantity_needed'];
}

$total_items_cost = 0;
foreach ($items as $item) {
    $total_items_cost += $item['total_price'] ?? 0;
}

$total_project_cost = $total_components_cost + $total_items_cost;

// Générer le HTML pour le PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Fiche Projet - ' . htmlspecialchars($project['name']) . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
            font-size: 12px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .project-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        .project-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        .section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 5px;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 11px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #3498db;
            color: white;
            font-weight: bold;
        }
        .total-row {
            background-color: #e8f5e8;
            font-weight: bold;
        }
        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-en-cours {
            background: #d1ecf1;
            color: #0c5460;
        }
        .status-termine {
            background: #d4edda;
            color: #155724;
        }
        .progress-bar {
            width: 60px;
            height: 10px;
            background: #e9ecef;
            border-radius: 5px;
            overflow: hidden;
            display: inline-block;
        }
        .progress-fill {
            height: 100%;
            background: #28a745;
        }
        .cost-summary {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .cost-summary h3 {
            margin-top: 0;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="project-title">Fiche de Projet</div>
        <div style="font-size: 20px; color: #666;">' . htmlspecialchars($project['name']) . '</div>
    </div>
    
    <div class="project-info">
        <strong>Statut:</strong> <span class="status status-' . strtolower(str_replace(' ', '-', $project['status'])) . '">' . htmlspecialchars($project['status']) . '</span><br>
        <strong>Créé le:</strong> ' . date('d/m/Y à H:i', strtotime($project['created_at'])) . '<br>';
        
if ($project['description']) {
    $html .= '<strong>Description:</strong> ' . htmlspecialchars($project['description']) . '<br>';
}

$html .= '
    </div>';

// Section Composants
if (!empty($components)) {
    $html .= '
    <div class="section">
        <div class="section-title">📋 Composants du Projet (' . count($components) . ')</div>
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Fabricant</th>
                    <th>Boîtier</th>
                    <th>Nécessaire</th>
                    <th>Utilisé</th>
                    <th>Stock</th>
                    <th>Prix unitaire</th>
                    <th>Coût total</th>
                    <th>Progression</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($components as $component) {
        $progress = $component['quantity_needed'] > 0 ? ($component['quantity_used'] / $component['quantity_needed']) * 100 : 0;
        $progress = min(100, $progress);
        $cost = ($component['price'] ?? 0) * $component['quantity_needed'];
        
        $html .= '
                <tr>
                    <td><strong>' . htmlspecialchars($component['name']) . '</strong></td>
                    <td>' . htmlspecialchars($component['manufacturer'] ?: 'N/A') . '</td>
                    <td>' . htmlspecialchars($component['package'] ?: 'N/A') . '</td>
                    <td>' . $component['quantity_needed'] . '</td>
                    <td>' . $component['quantity_used'] . '</td>
                    <td>' . $component['stock'] . '</td>
                    <td>' . number_format($component['price'] ?? 0, 2, ',', ' ') . ' €</td>
                    <td>' . number_format($cost, 2, ',', ' ') . ' €</td>
                    <td>' . round($progress) . '%</td>
                </tr>';
    }
    
    $html .= '
                <tr class="total-row">
                    <td colspan="7"><strong>Total Composants</strong></td>
                    <td><strong>' . number_format($total_components_cost, 2, ',', ' ') . ' €</strong></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>';
}

// Section Travaux et Matériaux
if (!empty($items)) {
    $html .= '
    <div class="section">
        <div class="section-title">🔧 Travaux et Matériaux (' . count($items) . ')</div>
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Nom</th>
                    <th>Description</th>
                    <th>Quantité</th>
                    <th>Unité</th>
                    <th>Prix unitaire</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($items as $item) {
        $type_icon = $item['item_type'] === 'work' ? '🔧' : '📦';
        $html .= '
                <tr>
                    <td>' . $type_icon . ' ' . ucfirst($item['item_type']) . '</td>
                    <td><strong>' . htmlspecialchars($item['item_name']) . '</strong></td>
                    <td>' . htmlspecialchars($item['description'] ?: '-') . '</td>
                    <td>' . $item['quantity'] . '</td>
                    <td>' . htmlspecialchars($item['unit']) . '</td>
                    <td>' . number_format($item['unit_price'], 2, ',', ' ') . ' €</td>
                    <td>' . number_format($item['total_price'], 2, ',', ' ') . ' €</td>
                </tr>';
    }
    
    $html .= '
                <tr class="total-row">
                    <td colspan="6"><strong>Total Travaux et Matériaux</strong></td>
                    <td><strong>' . number_format($total_items_cost, 2, ',', ' ') . ' €</strong></td>
                </tr>
            </tbody>
        </table>
    </div>';
}

// Section Fichiers
if (!empty($files)) {
    $html .= '
    <div class="section">
        <div class="section-title">📁 Fichiers du Projet (' . count($files) . ')</div>
        <table>
            <thead>
                <tr>
                    <th>Nom du fichier</th>
                    <th>Taille</th>
                    <th>Date d\'ajout</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($files as $file) {
        $html .= '
                <tr>
                    <td>' . htmlspecialchars($file['display_name']) . '</td>
                    <td>' . formatFileSize($file['file_size']) . '</td>
                    <td>' . date('d/m/Y à H:i', strtotime($file['uploaded_at'])) . '</td>
                </tr>';
    }
    
    $html .= '
            </tbody>
        </table>
    </div>';
}

// Résumé des coûts
$html .= '
    <div class="cost-summary">
        <h3>💰 Résumé des Coûts</h3>
        <table style="background: white;">
            <tr>
                <td><strong>Coût des composants :</strong></td>
                <td style="text-align: right;"><strong>' . number_format($total_components_cost, 2, ',', ' ') . ' €</strong></td>
            </tr>
            <tr>
                <td><strong>Coût travaux et matériaux :</strong></td>
                <td style="text-align: right;"><strong>' . number_format($total_items_cost, 2, ',', ' ') . ' €</strong></td>
            </tr>
            <tr style="border-top: 2px solid #856404;">
                <td><strong>COÛT TOTAL DU PROJET :</strong></td>
                <td style="text-align: right; font-size: 14px;"><strong>' . number_format($total_project_cost, 2, ',', ' ') . ' €</strong></td>
            </tr>
        </table>
    </div>

    <div style="margin-top: 30px; text-align: center; font-size: 10px; color: #666;">
        Fiche générée le ' . date('d/m/Y à H:i') . '
    </div>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>';

try {
    // Créer une instance mPDF
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'P',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 16,
        'margin_bottom' => 16,
        'margin_header' => 9,
        'margin_footer' => 9,
        'tempDir' => sys_get_temp_dir()
    ]);
    
    // Charger le HTML
    $mpdf->WriteHTML($html);
    
    // Générer le nom du fichier
    $filename = 'Fiche_Projet_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['name']) . '_' . date('Y-m-d') . '.pdf';
    
    // Envoyer le PDF au navigateur
    $mpdf->Output($filename, 'D'); // 'D' pour téléchargement direct
    
} catch (Exception $e) {
    // En cas d'erreur, afficher le HTML et proposer l'impression
    echo '<div style="background: #f8d7da; color: #721c24; padding: 15px; margin: 20px; border-radius: 5px;">';
    echo '<strong>Erreur lors de la génération du PDF :</strong> ' . htmlspecialchars($e->getMessage());
    echo '<br><br>Le contenu sera affiché ci-dessous. Vous pouvez utiliser Ctrl+P pour imprimer.';
    echo '</div>';
    
    echo $html;
    
    echo '<script>';
    echo 'setTimeout(function() { window.print(); }, 1000);';
    echo '</script>';
}
?>