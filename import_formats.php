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
$user_id = $_SESSION['user_id'];

// Traitement des décisions sur les doublons
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['duplicate_action'])) {
    try {
        $pdo = getConnection();
        $pdo->beginTransaction();
        
        // Réinitialiser le compteur d'ID pour cet import
        global $last_generated_id;
        $last_generated_id = null;
        
        $pending_import = $_SESSION['pending_import'] ?? null;
        if (!$pending_import) {
            throw new Exception('Aucun import en attente.');
        }
        
        $duplicate_action = $_POST['duplicate_action']; // 'skip' ou 'replace'
        $file_content = $pending_import['file_content'];
        $extension = $pending_import['extension'];
        $imported_count = 0;
        
        // Retraiter le fichier avec l'action choisie
        switch ($extension) {
            case 'csv':
                // Créer un fichier temporaire pour utiliser fgetcsv
                $temp_file = tempnam(sys_get_temp_dir(), 'csv_import_duplicate');
                file_put_contents($temp_file, $file_content);
                $handle = fopen($temp_file, 'r');
                
                if (!$handle) {
                    throw new Exception('Impossible d\'ouvrir le fichier CSV temporaire');
                }
                
                // Lire les en-têtes
                $headers = fgetcsv($handle, 0, ';', '"');
                if (!$headers) {
                    fclose($handle);
                    unlink($temp_file);
                    throw new Exception('Impossible de lire les en-têtes du fichier CSV');
                }
                
                $line_num = 0;
                
                // Lire les données ligne par ligne avec fgetcsv
                while (($data = fgetcsv($handle, 0, ';', '"')) !== FALSE) {
                    $line_num++;
                    
                    $debug_msg = "[DEBUG DOUBLONS] Traitement ligne " . ($line_num + 1) . " - Données parsées (" . count($data) . " colonnes)";
                    error_log($debug_msg);
                    file_put_contents(__DIR__ . '/import_debug.log', date('Y-m-d H:i:s') . ' ' . $debug_msg . "\n", FILE_APPEND);
                    
                    if (count($data) !== count($headers)) {
                        $debug_msg = "[DEBUG DOUBLONS] Ligne " . ($line_num + 1) . " ignorée - nombre de colonnes incorrect: " . count($data) . " vs " . count($headers);
                        error_log($debug_msg);
                        file_put_contents(__DIR__ . '/import_debug.log', date('Y-m-d H:i:s') . ' ' . $debug_msg . "\n", FILE_APPEND);
                        continue;
                    }
                    
                    $row = array_combine($headers, $data);
                    $debug_msg = "[DEBUG DOUBLONS] Traitement ligne " . ($line_num + 1) . " - Composant: " . ($row['name'] ?? 'SANS_NOM');
                    error_log($debug_msg);
                    file_put_contents(__DIR__ . '/import_debug.log', date('Y-m-d H:i:s') . ' ' . $debug_msg . "\n", FILE_APPEND);
                    
                    $result = importComponent($pdo, $row, $user_id, $duplicate_action);
                    if ($result['status'] === 'success') {
                        $imported_count++;
                        $debug_msg = "[DEBUG DOUBLONS] Composant importé avec succès: " . ($row['name'] ?? 'SANS_NOM');
                        error_log($debug_msg);
                        file_put_contents(__DIR__ . '/import_debug.log', date('Y-m-d H:i:s') . ' ' . $debug_msg . "\n", FILE_APPEND);
                    }
                }
                
                // Fermer et nettoyer
                fclose($handle);
                unlink($temp_file);
                break;
                
            case 'json':
                $json_data = json_decode($file_content, true);
                $components = $json_data['components'] ?? $json_data;
                
                foreach ($components as $component) {
                    $result = importComponent($pdo, $component, $user_id, $duplicate_action);
                    if ($result['status'] === 'success') {
                        $imported_count++;
                    }
                }
                break;
        }
        
        $pdo->commit();
        unset($_SESSION['pending_import']);
        
        $action_text = ($duplicate_action === 'skip') ? 'ignorés' : 'remplacés';
        $message = "Import terminé ! $imported_count composants importés. Doublons $action_text.";
        
    } catch (Exception $e) {
        if (isset($pdo)) $pdo->rollback();
        $error = 'Erreur lors du traitement des doublons : ' . $e->getMessage();
    }
}

// Traitement de l'upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    try {
        $pdo = getConnection();
        
        // Vérifier le fichier uploadé
        if ($_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Erreur lors de l\'upload du fichier.');
        }
        
        $file_info = pathinfo($_FILES['import_file']['name']);
        $extension = strtolower($file_info['extension']);
        $file_content = file_get_contents($_FILES['import_file']['tmp_name']);
        
        if ($file_content === false) {
            throw new Exception('Impossible de lire le fichier.');
        }
        
        // Commencer une transaction
        $pdo->beginTransaction();
        
        // Réinitialiser le compteur d'ID pour cet import
        global $last_generated_id;
        $last_generated_id = null;
        
        $imported_count = 0;
        
        switch ($extension) {
            case 'csv':
                // Créer un fichier temporaire pour utiliser fgetcsv
                $temp_file = tempnam(sys_get_temp_dir(), 'csv_import');
                file_put_contents($temp_file, $file_content);
                $handle = fopen($temp_file, 'r');
                
                if (!$handle) {
                    throw new Exception('Impossible d\'ouvrir le fichier CSV temporaire');
                }
                
                // Lire les en-têtes
                $headers = fgetcsv($handle, 0, ';', '"');
                if (!$headers) {
                    fclose($handle);
                    unlink($temp_file);
                    throw new Exception('Impossible de lire les en-têtes du fichier CSV');
                }
                
                $debug_msg = "[DEBUG] En-têtes CSV (" . count($headers) . "): " . implode(', ', $headers);
                error_log($debug_msg);
                file_put_contents(__DIR__ . '/import_debug.log', date('Y-m-d H:i:s') . ' ' . $debug_msg . "\n", FILE_APPEND);
                
                // Vérifier les colonnes obligatoires
                $required_columns = ['name', 'category', 'quantity'];
                foreach ($required_columns as $col) {
                    if (!in_array($col, $headers)) {
                        fclose($handle);
                        unlink($temp_file);
                        throw new Exception("Colonne obligatoire manquante : $col");
                    }
                }
                
                $line_num = 0;
                
                // Lire les données ligne par ligne avec fgetcsv
                while (($data = fgetcsv($handle, 0, ';', '"')) !== FALSE) {
                    $line_num++;
                    
                    $debug_msg = "[DEBUG] Ligne " . ($line_num + 1) . " - Données parsées (" . count($data) . " colonnes): " . implode(' | ', array_slice($data, 0, 5)) . (count($data) > 5 ? '...' : '');
                    error_log($debug_msg);
                    file_put_contents(__DIR__ . '/import_debug.log', date('Y-m-d H:i:s') . ' ' . $debug_msg . "\n", FILE_APPEND);
                    
                    if (count($data) !== count($headers)) {
                        $debug_msg = "[DEBUG] Ligne " . ($line_num + 1) . " ignorée - nombre de colonnes incorrect: " . count($data) . " vs " . count($headers);
                        error_log($debug_msg);
                        file_put_contents(__DIR__ . '/import_debug.log', date('Y-m-d H:i:s') . ' ' . $debug_msg . "\n", FILE_APPEND);
                        continue;
                    }
                    
                    $row = array_combine($headers, $data);
                    $debug_msg = "[DEBUG] Traitement ligne " . ($line_num + 1) . " - Composant: " . ($row['name'] ?? 'SANS_NOM');
                    error_log($debug_msg);
                    file_put_contents(__DIR__ . '/import_debug.log', date('Y-m-d H:i:s') . ' ' . $debug_msg . "\n", FILE_APPEND);
                    
                    // Insérer le composant
                    $result = importComponent($pdo, $row, $user_id);
                    if ($result['status'] === 'success') {
                        $imported_count++;
                        error_log("[DEBUG] Composant importé avec succès: " . ($row['name'] ?? 'SANS_NOM'));
                    } elseif ($result['status'] === 'duplicate') {
                        // Stocker les doublons pour demander à l'utilisateur
                        if (!isset($duplicates)) $duplicates = [];
                        $duplicates[] = $result;
                        error_log("[DEBUG] Doublon détecté: " . ($row['name'] ?? 'SANS_NOM'));
                    }
                }
                
                // Fermer et nettoyer
                fclose($handle);
                unlink($temp_file);
                break;
                
            case 'json':
                // Traitement JSON
                $json_data = json_decode($file_content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Fichier JSON invalide.');
                }
                
                $components = $json_data['components'] ?? $json_data;
                if (!is_array($components)) {
                    throw new Exception('Format JSON invalide.');
                }
                
                foreach ($components as $component) {
                    $result = importComponent($pdo, $component, $user_id);
                    if ($result['status'] === 'success') {
                        $imported_count++;
                    } elseif ($result['status'] === 'duplicate') {
                        // Stocker les doublons pour demander à l'utilisateur
                        if (!isset($duplicates)) $duplicates = [];
                        $duplicates[] = $result;
                    }
                }
                break;
                
            case 'sql':
                // Rediriger vers l'import SQL existant
                header('Location: import_database.php');
                exit();
                
            default:
                throw new Exception('Format de fichier non supporté : ' . $extension);
        }
        
        // Gérer les doublons détectés
        if (isset($duplicates) && !empty($duplicates)) {
            // Annuler la transaction pour permettre à l'utilisateur de décider
            $pdo->rollback();
            
            // Stocker les données en session pour les traiter après la décision de l'utilisateur
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['pending_import'] = [
                'file_content' => $file_content,
                'extension' => $extension,
                'duplicates' => $duplicates,
                'imported_count' => $imported_count
            ];
            
            $duplicate_names = array_column($duplicates, 'name');
            $duplicate_message = "Composants en doublon détectés : " . implode(', ', $duplicate_names);
        } else {
            // Valider la transaction
            $pdo->commit();
            
            $message = "Import réussi ! $imported_count composants importés.";
        }
        
        // Scanner automatiquement les images si demandé
        if (isset($_POST['auto_scan_images']) && $_POST['auto_scan_images'] == '1') {
            try {
                // Inclure le script de scan automatique
                include_once 'auto_assign_package_images.php';
                
                // Exécuter le scan
                $scan_result = scanAndAssignImages($pdo, $user_id);
                
                if ($scan_result['success']) {
                    $message .= " Scanner d'images exécuté : {$scan_result['assigned_count']} images assignées sur {$scan_result['total_checked']} composants vérifiés.";
                } else {
                    $message .= " Attention : erreur lors du scan d'images - {$scan_result['error']}";
                }
            } catch (Exception $scan_error) {
                $message .= " Attention : erreur lors du scan d'images - " . $scan_error->getMessage();
            }
        }
        
    } catch(Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        $error = "Erreur lors de l'import : " . $e->getMessage();
    }
}

// Fonction pour normaliser les noms de packages
function normalizePackageName($package) {
    if (empty($package)) {
        return $package;
    }
    
    // Ajouter un tiret entre les lettres et les chiffres
    // TO220 -> TO-220, SOT223 -> SOT-223, etc.
    $normalized = preg_replace('/([A-Za-z]+)(\d+)/', '$1-$2', $package);
    
    return $normalized;
}

// Variable globale pour suivre le dernier ID généré
static $last_generated_id = null;

// Fonction pour importer un composant
function importComponent($pdo, $data, $user_id, $duplicate_action = 'ask') {
    global $last_generated_id;
    
    // Debug: Log des données reçues
    $debug_msg = "[DEBUG] Importation du composant: " . ($data['name'] ?? 'SANS_NOM') . " - ID: " . ($data['id'] ?? 'AUCUN');
    error_log($debug_msg);
    file_put_contents(__DIR__ . '/import_debug.log', date('Y-m-d H:i:s') . ' ' . $debug_msg . "\n", FILE_APPEND);
    
    // Vérifier que le nom du composant n'est pas vide
    if (empty($data['name']) || trim($data['name']) === '') {
        return ['status' => 'skipped', 'message' => 'Composant ignoré - nom vide ou manquant'];
    }
    
    // Vérifier si un composant avec le même nom existe déjà
    if (!empty($data['name'])) {
        $stmt = $pdo->prepare("SELECT id, name FROM data WHERE name = ? AND owner = ?");
        $stmt->execute([$data['name'], $user_id]);
        $existing_component = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_component) {
            if ($duplicate_action === 'skip') {
                return ['status' => 'skipped', 'message' => 'Composant "' . $data['name'] . '" déjà existant - ignoré'];
            } elseif ($duplicate_action === 'replace') {
                // Supprimer l'ancien composant
                $stmt = $pdo->prepare("DELETE FROM data WHERE id = ?");
                $stmt->execute([$existing_component['id']]);
            } else {
                // Action 'ask' - retourner l'information pour que l'utilisateur décide
                return ['status' => 'duplicate', 'existing_id' => $existing_component['id'], 'name' => $data['name']];
            }
        }
    }
    
    // Gestion automatique de l'ID du composant
    if (isset($data['id']) && !empty($data['id'])) {
        // Vérifier si l'ID existe déjà
        $stmt = $pdo->prepare("SELECT id FROM data WHERE id = ?");
        $stmt->execute([$data['id']]);
        if ($stmt->fetchColumn()) {
            // ID déjà utilisé, générer un nouvel ID
            if ($last_generated_id === null) {
                $stmt = $pdo->prepare("SELECT MAX(id) FROM data");
                $stmt->execute();
                $last_generated_id = $stmt->fetchColumn() ?: 0;
            }
            $last_generated_id++;
            $data['id'] = $last_generated_id;
        }
    } else {
        // Pas d'ID fourni, générer un nouvel ID
        if ($last_generated_id === null) {
            $stmt = $pdo->prepare("SELECT MAX(id) FROM data");
            $stmt->execute();
            $last_generated_id = $stmt->fetchColumn() ?: 0;
        }
        $last_generated_id++;
        $data['id'] = $last_generated_id;
    }
    
    // Gestion de la catégorie (ID numérique ou nom)
    $category_id = null;
    if (!empty($data['category'])) {
        if (is_numeric($data['category'])) {
            // Si c'est un ID numérique, vérifier qu'il existe
            $stmt = $pdo->prepare("SELECT id FROM category_sub WHERE id = ?");
            $stmt->execute([$data['category']]);
            if ($stmt->fetchColumn()) {
                $category_id = $data['category'];
            }
        } else {
            // Si c'est un nom, chercher par nom de catégorie
            $stmt = $pdo->prepare("SELECT id FROM category_head WHERE name = ?");
            $stmt->execute([$data['category']]);
            $category_id = $stmt->fetchColumn();
            
            if (!$category_id) {
                // Créer une nouvelle catégorie avec un ID auto-incrémenté
                $stmt = $pdo->prepare("SELECT MAX(id) FROM category_head");
                $stmt->execute();
                $max_id = $stmt->fetchColumn() ?: 0;
                $category_id = $max_id + 1;
                
                $stmt = $pdo->prepare("INSERT INTO category_head (id, name) VALUES (?, ?)");
                $stmt->execute([$category_id, $data['category']]);
            }
        }
    }
    
    // Gestion de l'emplacement (ID direct ou parsing A-11-2)
    $location_id = null;
    if (!empty($data['location_id'])) {
        // Si location_id est fourni directement, vérifier qu'il existe
        $stmt = $pdo->prepare("SELECT id FROM location WHERE id = ? AND owner = ?");
        $stmt->execute([$data['location_id'], $user_id]);
        if ($stmt->fetchColumn()) {
            $location_id = $data['location_id'];
        } else {
            // Location_id n'existe pas, on le met à null
            $debug_msg = "[DEBUG] Location_id " . $data['location_id'] . " n'existe pas pour l'utilisateur " . $user_id . ", mis à null";
            error_log($debug_msg);
            file_put_contents(__DIR__ . '/import_debug.log', date('Y-m-d H:i:s') . ' ' . $debug_msg . "\n", FILE_APPEND);
            $location_id = null;
        }
    } elseif (!empty($data['location'])) {
        // Parser le format A-11-2
        if (preg_match('/^([A-Z]+)-([0-9]+)-([0-9]+)$/', $data['location'], $matches)) {
            $casier = $matches[1];
            $tiroir = $matches[2];
            $compartiment = $matches[3];
            
            // Chercher l'emplacement existant
            $stmt = $pdo->prepare("SELECT id FROM location WHERE casier = ? AND tiroir = ? AND compartiment = ? AND owner = ?");
            $stmt->execute([$casier, $tiroir, $compartiment, $user_id]);
            $location_id = $stmt->fetchColumn();
            
            if (!$location_id) {
                // Créer le nouvel emplacement
                $stmt = $pdo->prepare("INSERT INTO location (casier, tiroir, compartiment, owner) VALUES (?, ?, ?, ?)");
                $stmt->execute([$casier, $tiroir, $compartiment, $user_id]);
                $location_id = $pdo->lastInsertId();
            }
        } else {
            // Format ancien (juste casier)
            $stmt = $pdo->prepare("SELECT id FROM location WHERE casier = ? AND owner = ?");
            $stmt->execute([$data['location'], $user_id]);
            $location_id = $stmt->fetchColumn();
            
            if (!$location_id) {
                $stmt = $pdo->prepare("INSERT INTO location (casier, owner) VALUES (?, ?)");
                $stmt->execute([$data['location'], $user_id]);
                $location_id = $pdo->lastInsertId();
            }
        }
    }
    
    // Gestion du fournisseur (ID direct ou nom)
    $supplier_id = null;
    if (!empty($data['supplier_id'])) {
        // Si supplier_id est fourni directement, vérifier qu'il existe
        $stmt = $pdo->prepare("SELECT id FROM suppliers WHERE id = ?");
        $stmt->execute([$data['supplier_id']]);
        if ($stmt->fetchColumn()) {
            $supplier_id = $data['supplier_id'];
        } else {
            // Supplier_id n'existe pas, on le met à null
            $debug_msg = "[DEBUG] Supplier_id " . $data['supplier_id'] . " n'existe pas, mis à null";
            error_log($debug_msg);
            file_put_contents(__DIR__ . '/import_debug.log', date('Y-m-d H:i:s') . ' ' . $debug_msg . "\n", FILE_APPEND);
            $supplier_id = null;
        }
    } elseif (!empty($data['supplier'])) {
        $stmt = $pdo->prepare("SELECT id FROM suppliers WHERE name = ?");
        $stmt->execute([$data['supplier']]);
        $supplier_id = $stmt->fetchColumn();
        
        if (!$supplier_id) {
            $stmt = $pdo->prepare("INSERT INTO suppliers (name) VALUES (?)");
            $stmt->execute([$data['supplier']]);
            $supplier_id = $pdo->lastInsertId();
        }
    }
    
    // Insérer le composant avec tous les champs dans l'ordre de la table
    $stmt = $pdo->prepare("
        INSERT INTO data 
        (id, owner, name, manufacturer, package, pins, smd, quantity, order_quantity, price, location_id, datasheet, comment, category, public, url, image_path, supplier_id, supplier_reference, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $data['id'],
        $user_id,
        $data['name'] ?? '',
        $data['manufacturer'] ?? '',
        normalizePackageName($data['package'] ?? ''),
        $data['pins'] ?? 0,
        isset($data['smd']) ? $data['smd'] : 'No',
        $data['quantity'] ?? 0,
        $data['order_quantity'] ?? 0,
        $data['price'] ?? null,
        $location_id,
        $data['datasheet'] ?? $data['datasheet_url'] ?? null,
        $data['comment'] ?? $data['description'] ?? '',
        $category_id,
        isset($data['public']) ? $data['public'] : 'No',
        $data['url'] ?? '',
        $data['image_path'] ?? '',
        $supplier_id,
        $data['supplier_reference'] ?? ''
    ]);
    
    return ['status' => 'success', 'message' => 'Composant "' . ($data['name'] ?? '') . '" importé avec succès'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formats d'Import - Gestion des Composants</title>
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
        }

        .format-card.active {
            border-color: #667eea;
            background: #f8f9ff;
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

        .format-requirements {
            margin-top: 15px;
            font-size: 0.9em;
            color: #888;
            text-align: left;
        }

        .upload-section {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-weight: normal !important;
            margin-bottom: 5px !important;
        }

        .checkbox-container input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.2);
        }

        .form-text {
            color: #6c757d;
            font-size: 0.875em;
            margin-top: 5px;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
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

        .btn-primary {
            background: #667eea;
            color: white;
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
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
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
            <h1>📥 Formats d'Import</h1>
            <p>Importez vos composants depuis différents formats</p>
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

            <?php if (isset($duplicate_message)): ?>
                <div class="warning-box">
                    <h3>⚠️ Composants en doublon détectés</h3>
                    <p><?php echo htmlspecialchars($duplicate_message); ?></p>
                    <p>Que souhaitez-vous faire avec ces composants ?</p>
                    
                    <form method="POST" style="margin-top: 20px;">
                        <div class="actions">
                            <button type="submit" name="duplicate_action" value="skip" class="btn btn-secondary">
                                🚫 Ignorer les doublons
                            </button>
                            <button type="submit" name="duplicate_action" value="replace" class="btn btn-primary">
                                🔄 Remplacer les existants
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <div class="warning-box">
                <h3>⚠️ Attention</h3>
                <p>L'import de données peut créer de nouveaux composants dans votre base. Les catégories, emplacements et fournisseurs seront créés automatiquement s'ils n'existent pas.</p>
            </div>

            <div class="upload-section">
                <h3>📁 Importer un fichier</h3>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="import_file">Fichier à importer :</label>
                        <input type="file" id="import_file" name="import_file" class="form-control" accept=".csv,.json,.sql" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-container">
                            <input type="checkbox" name="auto_scan_images" value="1" checked>
                            <span class="checkmark"></span>
                            🖼️ Scanner automatiquement les images de packages après l'import
                        </label>
                        <small class="form-text">Cette option recherchera automatiquement des images correspondantes aux packages des composants importés.</small>
                    </div>
                    
                    <div class="actions">
                        <button type="submit" class="btn btn-primary">📥 Importer</button>
                    </div>
                </form>
            </div>

            <div class="info-box">
                <h3>📋 Formats supportés</h3>
                <p>Voici les formats d'import disponibles et leurs spécifications :</p>
            </div>

            <div class="formats-grid">
                <div class="format-card">
                    <div class="format-icon">📊</div>
                    <div class="format-title">CSV</div>
                    <div class="format-description">
                        Fichier CSV avec séparateur point-virgule
                    </div>
                    <div class="format-requirements">
                        <strong>Colonnes obligatoires :</strong><br>
                        • name (nom du composant)<br>
                        • category (catégorie)<br>
                        • quantity (quantité)<br><br>
                        <strong>Colonnes optionnelles :</strong><br>
                        • description, subcategory, package, pins, smd, location, supplier, price, datasheet_url
                    </div>
                </div>

                <div class="format-card">
                    <div class="format-icon">🔧</div>
                    <div class="format-title">JSON</div>
                    <div class="format-description">
                        Format JSON structuré
                    </div>
                    <div class="format-requirements">
                        <strong>Structure attendue :</strong><br>
                        • Tableau d'objets composants<br>
                        • Ou objet avec propriété "components"<br><br>
                        <strong>Propriétés obligatoires :</strong><br>
                        • name, category, quantity
                    </div>
                </div>

                <div class="format-card">
                    <div class="format-icon">🗄️</div>
                    <div class="format-title">SQL</div>
                    <div class="format-description">
                        Fichier SQL de sauvegarde complète
                    </div>
                    <div class="format-requirements">
                        <strong>Utilisation :</strong><br>
                        • Restauration complète<br>
                        • Fichiers générés par l'export SQL<br><br>
                        <strong>Note :</strong><br>
                        Redirige vers l'import SQL dédié
                    </div>
                </div>
            </div>

            <div class="actions">
                <a href="settings.php" class="btn btn-secondary">🔙 Retour aux paramètres</a>
            </div>
        </div>
    </div>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>