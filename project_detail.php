<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=not_logged_in');
    exit();
}

// Récupérer l'ID du projet
$project_id = (int)($_GET['id'] ?? 0);
if ($project_id <= 0) {
    header('Location: projects.php?error=invalid_project');
    exit();
}

// Connexion à la base de données
try {
    $pdo = getConnection();
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_component':
                $component_id = (int)($_POST['component_id'] ?? 0);
                $quantity_needed = (int)($_POST['quantity_needed'] ?? 1);
                $notes = trim($_POST['notes'] ?? '');
                
                if ($component_id > 0 && $quantity_needed > 0) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO project_components (project_id, component_id, quantity_needed, notes) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE quantity_needed = quantity_needed + VALUES(quantity_needed), notes = VALUES(notes)");
                        $stmt->execute([$project_id, $component_id, $quantity_needed, $notes]);
                        header("Location: project_detail.php?id=$project_id&tab=components&success=component_added");
                        exit();
                    } catch (PDOException $e) {
                        $error = "Erreur lors de l'ajout du composant : " . $e->getMessage();
                    }
                } else {
                    $error = "Veuillez sélectionner un composant et une quantité valide.";
                }
                break;
                
            case 'remove_component':
                $pc_id = (int)($_POST['pc_id'] ?? 0);
                if ($pc_id > 0) {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM project_components WHERE id = ? AND project_id = ?");
                        $stmt->execute([$pc_id, $project_id]);
                        header("Location: project_detail.php?id=$project_id&tab=components&success=component_removed#components");
                        exit();
                    } catch (PDOException $e) {
                        $error = "Erreur lors de la suppression : " . $e->getMessage();
                    }
                }
                break;
                
            case 'update_quantity_used':
                $pc_id = (int)($_POST['pc_id'] ?? 0);
                $quantity_used = (int)($_POST['quantity_used'] ?? 0);
                if ($pc_id > 0 && $quantity_used >= 0) {
                    try {
                        $stmt = $pdo->prepare("UPDATE project_components SET quantity_used = ? WHERE id = ? AND project_id = ?");
                        $stmt->execute([$quantity_used, $pc_id, $project_id]);
                        // Détecter l'onglet source via le referer ou un paramètre
                        $redirect_tab = 'overview'; // Par défaut
                        if (isset($_POST['source_tab'])) {
                            $redirect_tab = $_POST['source_tab'];
                        } elseif (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'tab=components') !== false) {
                            $redirect_tab = 'components';
                        }
                        header("Location: project_detail.php?id=$project_id&tab=$redirect_tab&success=quantity_updated");
                        exit();
                    } catch (PDOException $e) {
                        $error = "Erreur lors de la mise à jour : " . $e->getMessage();
                    }
                }
                break;
                
            case 'add_project_item':
                $type = $_POST['item_type'];
                $name = trim($_POST['item_name']);
                $description = trim($_POST['item_description']);
                $quantity = floatval($_POST['item_quantity']);
                $unit = trim($_POST['item_unit']);
                $unit_price = floatval($_POST['item_unit_price']);
                
                if (empty($name) || $quantity <= 0 || $unit_price < 0) {
                    $error = "Veuillez remplir tous les champs obligatoires avec des valeurs valides.";
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO project_items (project_id, type, name, description, quantity, unit, unit_price)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$project_id, $type, $name, $description, $quantity, $unit, $unit_price]);
                        
                        header("Location: project_detail.php?id=$project_id&tab=materials&success=item_added#materials");
                        exit();
                    } catch (PDOException $e) {
                        $error = "Erreur lors de l'ajout : " . $e->getMessage();
                    }
                }
                break;
                
            case 'remove_project_item':
                $item_id = intval($_POST['item_id']);
                
                try {
                    $stmt = $pdo->prepare("DELETE FROM project_items WHERE id = ? AND project_id = ?");
                    $stmt->execute([$item_id, $project_id]);
                    
                    header("Location: project_detail.php?id=$project_id&tab=materials&success=item_removed#materials");
                    exit();
                } catch (PDOException $e) {
                    $error = "Erreur lors de la suppression : " . $e->getMessage();
                }
                break;
                
            case 'update_item_status':
                $item_id = intval($_POST['item_id']);
                $status = $_POST['item_status'];
                
                try {
                    $stmt = $pdo->prepare("UPDATE project_items SET status = ? WHERE id = ? AND project_id = ?");
                    $stmt->execute([$status, $item_id, $project_id]);
                    
                    header("Location: project_detail.php?id=$project_id&tab=materials&success=status_updated");
                    exit();
                } catch (PDOException $e) {
                    $error = "Erreur lors de la mise à jour : " . $e->getMessage();
                }
                break;
                
            case 'update_progress':
                $item_id = intval($_POST['item_id']);
                $progress_change = intval($_POST['progress_change']);
                
                try {
                    // Récupérer les informations de l'élément
                    $stmt = $pdo->prepare("SELECT quantity, quantity_completed, unit FROM project_items WHERE id = ? AND project_id = ?");
                    $stmt->execute([$item_id, $project_id]);
                    $item = $stmt->fetch();
                    
                    if ($item) {
                        $total_quantity = floatval($item['quantity']);
                        $current_completed = floatval($item['quantity_completed'] ?? 0);
                        $unit = strtolower(trim($item['unit']));
                        
                        // Déterminer l'incrément selon l'unité
                        $increment = 1; // Par défaut pour les pièces
                        if (strpos($unit, 'heure') !== false || strpos($unit, 'h') !== false) {
                            $increment = 0.5; // Demi-heure pour les heures
                        }
                        
                        // Calculer la nouvelle quantité complétée
                        if ($progress_change > 0) {
                            $new_completed = min($total_quantity, $current_completed + $increment);
                        } else {
                            $new_completed = max(0, $current_completed - $increment);
                        }
                        
                        // Calculer le nouveau pourcentage
                        $new_progress = $total_quantity > 0 ? ($new_completed / $total_quantity) * 100 : 0;
                        
                        // Déterminer le nouveau statut basé sur la progression
                        $new_status = 'En attente';
                        if ($new_progress >= 100) {
                            $new_status = 'Terminé';
                        } elseif ($new_progress > 0) {
                            $new_status = 'En cours';
                        }
                        
                        // Mettre à jour la quantité complétée et le statut
                        $stmt = $pdo->prepare("UPDATE project_items SET quantity_completed = ?, status = ? WHERE id = ? AND project_id = ?");
                        $stmt->execute([$new_completed, $new_status, $item_id, $project_id]);
                    }
                    
                    header("Location: project_detail.php?id=$project_id&tab=materials&success=progress_updated");
                    exit();
                } catch (PDOException $e) {
                    $error = "Erreur lors de la mise à jour de la progression : " . $e->getMessage();
                }
                break;
                
            case 'edit_project_item':
                $item_id = intval($_POST['item_id']);
                $type = $_POST['item_type'];
                $name = trim($_POST['item_name']);
                $description = trim($_POST['item_description']);
                $quantity = floatval($_POST['item_quantity']);
                $unit = trim($_POST['item_unit']);
                $unit_price = floatval($_POST['item_unit_price']);
                
                if ($item_id > 0 && !empty($name) && $quantity > 0 && $unit_price >= 0) {
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE project_items 
                            SET type = ?, name = ?, description = ?, quantity = ?, unit = ?, unit_price = ?
                            WHERE id = ? AND project_id = ?
                        ");
                        $stmt->execute([$type, $name, $description, $quantity, $unit, $unit_price, $item_id, $project_id]);
                        
                        header("Location: project_detail.php?id=$project_id&tab=materials&success=item_updated#materials");
                        exit();
                    } catch (PDOException $e) {
                        $error = "Erreur lors de la modification : " . $e->getMessage();
                    }
                } else {
                    $error = "Veuillez remplir tous les champs obligatoires avec des valeurs valides.";
                }
                break;
                
            case 'rename_file':
                $file_id = intval($_POST['file_id']);
                $new_name = trim($_POST['new_name']);
                
                if ($file_id > 0 && !empty($new_name)) {
                    try {
                        // Vérifier que le fichier appartient au projet
                        $stmt = $pdo->prepare("SELECT id FROM project_files WHERE id = ? AND project_id = ?");
                        $stmt->execute([$file_id, $project_id]);
                        
                        if ($stmt->fetch()) {
                            // Mettre à jour le nom d'affichage
                            $stmt = $pdo->prepare("UPDATE project_files SET display_name = ? WHERE id = ? AND project_id = ?");
                            $stmt->execute([$new_name, $file_id, $project_id]);
                            
                            header("Location: project_detail.php?id=$project_id&tab=files&success=file_renamed#files");
                            exit();
                        } else {
                            $error = "Fichier non trouvé.";
                        }
                    } catch (PDOException $e) {
                        $error = "Erreur lors du renommage : " . $e->getMessage();
                    }
                } else {
                    $error = "Nom de fichier invalide.";
                }
                break;
                
            case 'edit_file':
                $file_id = intval($_POST['file_id']);
                $display_name = trim($_POST['display_name']);
                $description = trim($_POST['description']);
                
                if ($file_id > 0 && !empty($display_name)) {
                    try {
                        // Vérifier que le fichier appartient au projet
                        $stmt = $pdo->prepare("SELECT * FROM project_files WHERE id = ? AND project_id = ?");
                        $stmt->execute([$file_id, $project_id]);
                        $existing_file = $stmt->fetch();
                        
                        if ($existing_file) {
                            $file_path = $existing_file['file_path'];
                            
                            // Gérer le remplacement du fichier si un nouveau fichier est fourni
                            if (isset($_FILES['new_file']) && $_FILES['new_file']['error'] === UPLOAD_ERR_OK) {
                                $upload_file = $_FILES['new_file'];
                                $original_name = $upload_file['name'];
                                $file_size = $upload_file['size'];
                                $file_type = $upload_file['type'];
                                
                                // Limiter la taille des fichiers (50MB max)
                                if ($file_size <= 50 * 1024 * 1024) {
                                    $file_extension = pathinfo($original_name, PATHINFO_EXTENSION);
                                    $safe_filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', pathinfo($original_name, PATHINFO_FILENAME));
                                    $new_filename = $safe_filename . '_' . time() . '.' . $file_extension;
                                    
                                    // Créer le dossier du projet s'il n'existe pas
                                    $project_folder = 'Projets/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['name']);
                                    if (!is_dir($project_folder)) {
                                        mkdir($project_folder, 0755, true);
                                    }
                                    
                                    $new_file_path = $project_folder . '/' . $new_filename;
                                    
                                    if (move_uploaded_file($upload_file['tmp_name'], $new_file_path)) {
                                        // Supprimer l'ancien fichier
                                        if (file_exists($existing_file['file_path'])) {
                                            unlink($existing_file['file_path']);
                                        }
                                        
                                        $file_path = $new_file_path;
                                        
                                        // Utiliser la catégorie fournie par l'utilisateur
                                        $file_category = $_POST['file_category'] ?? 'autre';
                                        
                                        // Mettre à jour avec le nouveau fichier
                                        $stmt = $pdo->prepare("UPDATE project_files SET display_name = ?, description = ?, file_path = ?, file_type = ?, file_size = ?, file_category = ?, original_name = ? WHERE id = ? AND project_id = ?");
                                        $stmt->execute([$display_name, $description, $file_path, $file_type, $file_size, $file_category, $original_name, $file_id, $project_id]);
                                    } else {
                                        $error = "Erreur lors du téléchargement du nouveau fichier.";
                                    }
                                } else {
                                    $error = "Le fichier est trop volumineux (50MB maximum).";
                                }
                            } else {
                                // Mettre à jour seulement le nom et la description
                                $stmt = $pdo->prepare("UPDATE project_files SET display_name = ?, description = ? WHERE id = ? AND project_id = ?");
                                $stmt->execute([$display_name, $description, $file_id, $project_id]);
                            }
                            
                            if (!isset($error)) {
                                header("Location: project_detail.php?id=$project_id&tab=files&success=file_updated#files");
                                exit();
                            }
                        } else {
                            $error = "Fichier non trouvé.";
                        }
                    } catch (PDOException $e) {
                        $error = "Erreur lors de la modification : " . $e->getMessage();
                    }
                } else {
                    $error = "Nom de fichier invalide.";
                }
                break;
        }
    }
}

// Récupérer les informations du projet
try {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND owner = ?");
    $stmt->execute([$project_id, $_SESSION['user_id']]);
    $project = $stmt->fetch();
    
    if (!$project) {
        header('Location: projects.php?error=project_not_found');
        exit();
    }
} catch (PDOException $e) {
    die("Erreur lors de la récupération du projet : " . $e->getMessage());
}

// Récupérer les composants du projet avec les prix
try {
    $stmt = $pdo->prepare("
        SELECT pc.*, d.name as component_name, d.manufacturer, d.package, d.quantity as stock_quantity,
               d.price, l.casier, l.tiroir, l.compartiment
        FROM project_components pc
        JOIN data d ON pc.component_id = d.id
        LEFT JOIN location l ON d.location_id = l.id
        WHERE pc.project_id = ?
        ORDER BY pc.added_at DESC
    ");
    $stmt->execute([$project_id]);
    $project_components = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Erreur lors de la récupération des composants : " . $e->getMessage());
}

// Calculer la progression globale et le coût total
$total_needed = 0;
$total_used = 0;
$total_cost = 0;
$total_cost_used = 0;

foreach ($project_components as $pc) {
    $total_needed += $pc['quantity_needed'];
    $total_used += $pc['quantity_used'];
    
    $component_price = $pc['price'] ?? 0;
    $total_cost += $component_price * $pc['quantity_needed'];
    $total_cost_used += $component_price * $pc['quantity_used'];
}

$components_progress = $total_needed > 0 ? ($total_used / $total_needed) * 100 : 0;
$components_progress = min(100, $components_progress);

// Récupérer les éléments de projet (travaux et matériaux)
try {
    $stmt = $pdo->prepare("
        SELECT * FROM project_items 
        WHERE project_id = ?
        ORDER BY type, added_at DESC
    ");
    $stmt->execute([$project_id]);
    $project_items = $stmt->fetchAll();
    

} catch (PDOException $e) {
    die("Erreur lors de la récupération des éléments : " . $e->getMessage());
}

// Calculer le coût total des éléments et leur progression
$items_total_cost = 0;
$items_total_quantity = 0;
$items_completed_quantity = 0;

foreach ($project_items as $item) {
    $items_total_cost += $item['total_price'];
    $items_total_quantity += floatval($item['quantity']);
    $items_completed_quantity += floatval($item['quantity_completed'] ?? 0);
}

// Calculer la progression des travaux et matériaux
$items_progress = $items_total_quantity > 0 ? ($items_completed_quantity / $items_total_quantity) * 100 : 0;
$items_progress = min(100, $items_progress);

// Calculer la progression globale (moyenne pondérée des composants et des travaux/matériaux)
$total_elements = count($project_components) + count($project_items);
if ($total_elements > 0) {
    $components_weight = count($project_components) / $total_elements;
    $items_weight = count($project_items) / $total_elements;
    $global_progress = ($components_progress * $components_weight) + ($items_progress * $items_weight);
} else {
    $global_progress = 0;
}
$global_progress = min(100, $global_progress);

// Récupérer les fichiers du projet
try {
    $stmt = $pdo->prepare("
        SELECT * FROM project_files 
        WHERE project_id = ?
        ORDER BY file_category, uploaded_at DESC
    ");
    $stmt->execute([$project_id]);
    $project_files = $stmt->fetchAll();
} catch (PDOException $e) {
    $project_files = [];
}

// Récupérer tous les composants disponibles pour l'ajout
try {
    $stmt = $pdo->prepare("
        SELECT d.*, l.casier, l.tiroir, l.compartiment
        FROM data d
        LEFT JOIN location l ON d.location_id = l.id
        WHERE d.owner = ?
        ORDER BY d.name
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $available_components = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Erreur lors de la récupération des composants disponibles : " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projet: <?php echo htmlspecialchars($project['name']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --accent-color: #f093fb;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-color: #1f2937;
            --light-color: #f8fafc;
            --border-color: #e2e8f0;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            color: var(--dark-color);
        }

        .main-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header moderne */
        .modern-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }

        .modern-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="%23ffffff" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>') repeat;
            pointer-events: none;
        }

        .header-content {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 0 auto;
        }

        .project-header {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .project-avatar {
            width: 100px;
            height: 100px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .project-info h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .project-meta {
            display: flex;
            gap: 2rem;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-info {
            position: absolute;
            top: 1rem;
            right: 2rem;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-size: 0.9rem;
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

        /* Container principal */
        .content-container {
            flex: 1;
            background: white;
            margin: -2rem 2rem 2rem;
            border-radius: 20px 20px 0 0;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        /* Système d'onglets moderne */
        .tabs-header {
            background: var(--light-color);
            border-bottom: 1px solid var(--border-color);
            padding: 0 2rem;
            margin-top: 10mm;
        }

        .tabs-nav {
            display: flex;
            gap: 0;
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .tabs-nav::-webkit-scrollbar {
            display: none;
        }

        .tab-button {
            background: rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(102, 126, 234, 0.2);
            padding: 1.25rem 2rem;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--primary-color);
            border-bottom: 3px solid transparent;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            border-radius: 8px 8px 0 0;
            margin-right: 2px;
        }

        .tab-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 8px 8px 0 0;
        }

        .tab-button:hover {
            color: white;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .tab-button.active {
            color: white;
            border-bottom-color: var(--primary-color);
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            transform: translateY(-1px);
        }

        .tab-icon {
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
        }

        .tab-content {
            display: none;
            padding: 2rem;
            animation: fadeInUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Cards modernes */
        .modern-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .modern-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        /* Grille de statistiques */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1.5rem;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(102, 126, 234, 0.3);
        }

        .stat-icon {
            font-size: 2rem;
            opacity: 0.9;
        }

        .stat-content h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-content p {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        /* Formulaires modernes */
        .modern-form {
            background: var(--light-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            font-size: 0.9rem;
        }

        .form-input, .form-select, .form-textarea {
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        /* Tables modernes */
        .modern-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .modern-table th {
            background: var(--light-color);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--dark-color);
            border-bottom: 1px solid var(--border-color);
        }

        .modern-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .modern-table tr:hover {
            background: var(--light-color);
        }

        /* Progress bar moderne */
        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--border-color);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color), var(--primary-color));
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        /* Messages */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .content-container {
                margin: -1rem 1rem 1rem;
            }
            
            .tab-content {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header moderne -->
        <header class="modern-header">
            <div class="user-info">
                <i class="fas fa-user"></i>
                Connecté en tant que: <strong><?php echo htmlspecialchars($_SESSION['user_email']); ?></strong>
            </div>
            
            <div class="nav-section">
                <div class="nav-buttons">
                    <a href="components.php">📦 Composants</a>
                    <a href="projects.php">🚀 Projets</a>
                    <a href="settings.php">⚙️ Paramètres</a>
                </div>
            </div>
            
            <div class="header-content">
                <div class="project-header">
                    <div class="project-avatar">
                        <?php if (!empty($project['image_path']) && file_exists($project['image_path'])): ?>
                            <img src="<?php echo htmlspecialchars($project['image_path']); ?>" alt="Image du projet" style="width: 100%; height: 100%; object-fit: cover; border-radius: 18px;">
                        <?php else: ?>
                            <i class="fas fa-microchip"></i>
                        <?php endif; ?>
                    </div>
                    
                    <div class="project-info">
                        <h1><?php echo htmlspecialchars($project['name']); ?></h1>
                        <div class="project-meta">
                            <div class="meta-item">
                                <i class="fas fa-calendar"></i>
                                Créé le <?php echo date('d/m/Y', strtotime($project['created_at'])); ?>
                            </div>
                            <div class="meta-item">
                                <i class="fas fa-clock"></i>
                                Modifié le <?php echo date('d/m/Y', strtotime($project['updated_at'])); ?>
                            </div>
                            <?php if (!empty($project['description'])): ?>
                            <div class="meta-item">
                                <i class="fas fa-info-circle"></i>
                                <?php echo htmlspecialchars($project['description']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Container principal -->
        <div class="content-container">
            <!-- Navigation par onglets -->
            <div class="tabs-header">
                <nav class="tabs-nav">
                    <button class="tab-button active" onclick="showTab('overview')">
                        <i class="fas fa-chart-pie tab-icon"></i>
                        Vue d'ensemble
                    </button>
                    <button class="tab-button" onclick="showTab('components')">
                        <i class="fas fa-microchip tab-icon"></i>
                        Composants
                    </button>
                    <button class="tab-button" onclick="showTab('materials')">
                        <i class="fas fa-tools tab-icon"></i>
                        Travaux & Matériaux
                    </button>
                    <button class="tab-button" onclick="showTab('files')">
                        <i class="fas fa-folder tab-icon"></i>
                        Documents & Photos
                    </button>
                </nav>
            </div>

            <!-- Messages -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php
                    switch ($_GET['success']) {
                        case 'component_added': echo 'Composant ajouté avec succès !'; break;
                        case 'component_removed': echo 'Composant supprimé avec succès !'; break;
                        case 'quantity_updated': echo 'Quantité mise à jour avec succès !'; break;
                        case 'item_added': echo 'Élément ajouté avec succès !'; break;
                        case 'item_removed': echo 'Élément supprimé avec succès !'; break;
                        case 'status_updated': echo 'Statut mis à jour avec succès !'; break;
                        default: echo 'Opération réussie !';
                    }
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Contenu des onglets -->
            
            <!-- Onglet Vue d'ensemble -->
            <div id="overview" class="tab-content active">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-microchip"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($project_components); ?></h3>
                            <p>Composants</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-tools"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($project_items); ?></h3>
                            <p>Travaux & Matériaux</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-folder"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo count($project_files); ?></h3>
                            <p>Documents</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-euro-sign"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo number_format($total_cost + $items_total_cost, 2); ?>€</h3>
                            <p>Coût total</p>
                        </div>
                    </div>
                </div>



                <div class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-chart-line"></i>
                            Progression du projet
                        </h3>
                        <span style="font-weight: 600; color: var(--primary-color);"><?php echo number_format($global_progress, 1); ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $global_progress; ?>%"></div>
                    </div>
                    <p style="margin-top: 1rem; color: #64748b; font-size: 0.9rem;">
                        <?php echo $total_used; ?> composants utilisés sur <?php echo $total_needed; ?> nécessaires
                    </p>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                    <div class="modern-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-microchip"></i>
                                Résumé Composants
                            </h3>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                            <div style="text-align: center; padding: 1rem; background: var(--light-color); border-radius: 8px;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-color); margin-bottom: 0.5rem;"><?php echo $total_needed; ?></div>
                                <div style="font-size: 0.9rem; color: #64748b;">Nécessaires</div>
                            </div>
                            <div style="text-align: center; padding: 1rem; background: var(--light-color); border-radius: 8px;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: var(--success-color); margin-bottom: 0.5rem;"><?php echo $total_used; ?></div>
                                <div style="font-size: 0.9rem; color: #64748b;">Utilisés</div>
                            </div>
                        </div>
                    </div>

                    <div class="modern-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-euro-sign"></i>
                                Résumé Financier
                            </h3>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                            <div style="text-align: center; padding: 1rem; background: var(--light-color); border-radius: 8px;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-color); margin-bottom: 0.5rem;"><?php echo number_format($total_cost, 2); ?>€</div>
                                <div style="font-size: 0.9rem; color: #64748b;">Composants</div>
                            </div>
                            <div style="text-align: center; padding: 1rem; background: var(--light-color); border-radius: 8px;">
                                <div style="font-size: 1.5rem; font-weight: 700; color: var(--warning-color); margin-bottom: 0.5rem;"><?php echo number_format($items_total_cost, 2); ?>€</div>
                                <div style="font-size: 0.9rem; color: #64748b;">Travaux & Matériaux</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Liste des Composants -->
                <?php if (!empty($project_components)): ?>
                <div class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-microchip"></i>
                            Composants du projet (<?php echo count($project_components); ?>)
                        </h3>
                    </div>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Composant</th>
                                    <th>Quantité</th>
                                    <th>Progression</th>
                                    <th>Prix</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($project_components, 0, 10) as $pc): ?>
                                    <?php
                                    $progress = $pc['quantity_needed'] > 0 ? ($pc['quantity_used'] / $pc['quantity_needed']) * 100 : 0;
                                    $progress = min(100, $progress);
                                    $component_price = $pc['price'] ?? 0;
                                    $total_component_cost = $component_price * $pc['quantity_needed'];
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($pc['component_name']); ?></strong>
                                            <?php if (isset($pc['manufacturer']) && $pc['manufacturer']): ?>
                                                <br><small style="color: #64748b;"><?php echo htmlspecialchars($pc['manufacturer']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="update_quantity_used">
                                                    <input type="hidden" name="pc_id" value="<?php echo $pc['id']; ?>">
                                                    <input type="hidden" name="quantity_used" value="<?php echo max(0, $pc['quantity_used'] - 1); ?>">
                                                    <input type="hidden" name="source_tab" value="overview">
                                                    <button type="submit" class="btn btn-sm" style="background: #ef4444; color: white; padding: 0.25rem 0.5rem; border: none; border-radius: 4px; font-size: 0.8rem;" <?php echo $pc['quantity_used'] <= 0 ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-minus"></i>
                                                    </button>
                                                </form>
                                                <span style="font-weight: 600; min-width: 60px; text-align: center;"><?php echo $pc['quantity_used']; ?> / <?php echo $pc['quantity_needed']; ?></span>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="update_quantity_used">
                                                    <input type="hidden" name="pc_id" value="<?php echo $pc['id']; ?>">
                                                    <input type="hidden" name="quantity_used" value="<?php echo min($pc['quantity_needed'], $pc['quantity_used'] + 1); ?>">
                                                    <input type="hidden" name="source_tab" value="overview">
                                                    <button type="submit" class="btn btn-sm" style="background: #10b981; color: white; padding: 0.25rem 0.5rem; border: none; border-radius: 4px; font-size: 0.8rem;" <?php echo $pc['quantity_used'] >= $pc['quantity_needed'] ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="progress-bar" style="width: 80px;">
                                                <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                            </div>
                                            <small style="color: #64748b;"><?php echo number_format($progress, 0); ?>%</small>
                                        </td>
                                        <td><?php echo number_format($total_component_cost, 2); ?>€</td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($project_components) > 10): ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: #64748b; font-style: italic;">
                                            ... et <?php echo count($project_components) - 10; ?> autres composants
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Liste des Travaux & Matériaux -->
                <?php if (!empty($project_items)): ?>
                <div class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-tools"></i>
                            Travaux & Matériaux (<?php echo count($project_items); ?>)
                        </h3>
                    </div>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Type</th>
                                    <th>Quantité</th>
                                    <th>Progression</th>
                                    <th>Prix unitaire</th>
                                    <th>Coût total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($project_items, 0, 10) as $item): ?>
                                    <?php
                                    // Calculer la progression basée sur les quantités réelles
                                    $total_quantity = floatval($item['quantity']);
                                    $completed_quantity = floatval($item['quantity_completed'] ?? 0);
                                    $progress = $total_quantity > 0 ? ($completed_quantity / $total_quantity) * 100 : 0;
                                    $progress = min(100, max(0, $progress));
                                    $item_total_cost = $item['quantity'] * $item['unit_price'];
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['name'] ?? $item['description']); ?></strong>
                                            <?php if (isset($item['description']) && $item['description'] && $item['description'] !== ($item['name'] ?? '')): ?>
                                                <br><small style="color: #64748b;"><?php echo htmlspecialchars($item['description']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span style="padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; 
                                                         background: <?php 
                                                             if ($item['type'] === 'travail') echo 'rgba(59, 130, 246, 0.1)';
                                                             elseif ($item['type'] === 'service') echo 'rgba(168, 85, 247, 0.1)';
                                                             else echo 'rgba(16, 185, 129, 0.1)';
                                                         ?>;
                                                         color: <?php 
                                                             if ($item['type'] === 'travail') echo '#3b82f6';
                                                             elseif ($item['type'] === 'service') echo '#a855f7';
                                                             else echo '#10b981';
                                                         ?>;">
                                                <?php echo ucfirst($item['type'] ?? 'matériel'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['unit'] ?? ''); ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <button onclick="updateProgress(<?php echo $item['id']; ?>, -25)" 
                                                        style="background: #ef4444; color: white; border: none; border-radius: 4px; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.8rem;"
                                                        title="Diminuer la progression">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <div style="display: flex; flex-direction: column; align-items: center; gap: 0.25rem;">
                                                    <div class="progress-bar" style="width: 80px;">
                                                        <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                                    </div>
                                                    <small style="color: #64748b; font-size: 0.75rem;"><?php echo number_format($completed_quantity, 1); ?> / <?php echo number_format($total_quantity, 1); ?> <?php echo htmlspecialchars($item['unit']); ?></small>
                                                </div>
                                                <button onclick="updateProgress(<?php echo $item['id']; ?>, 25)" 
                                                        style="background: #10b981; color: white; border: none; border-radius: 4px; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.8rem;"
                                                        title="Augmenter la progression">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td><?php echo number_format($item['unit_price'], 2); ?>€</td>
                                        <td><strong><?php echo number_format($item_total_cost, 2); ?>€</strong></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($project_items) > 10): ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: #64748b; font-style: italic;">
                                            ... et <?php echo count($project_items) - 10; ?> autres éléments
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Liste des Documents -->
                <?php if (!empty($project_files)): ?>
                <div class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-folder"></i>
                            Documents & Photos (<?php echo count($project_files); ?>)
                        </h3>
                    </div>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <div style="display: flex; flex-direction: column; gap: 1rem; padding: 0.5rem;">
                            <?php foreach (array_slice($project_files, 0, 12) as $file): ?>
                                <?php
                                // Déterminer la couleur de bordure selon la catégorie
                                $borderColor = '#e5e7eb'; // Couleur par défaut
                                $categoryIcon = 'fas fa-file';
                                switch($file['file_category'] ?? 'other') {
                                    case 'photo':
                                        $borderColor = '#10b981'; // Vert
                                        $categoryIcon = 'fas fa-image';
                                        break;
                                    case 'schéma':
                                    case 'schema':
                                        $borderColor = '#8b5cf6'; // Violet pour les schémas
                                        $categoryIcon = 'fas fa-drafting-compass';
                                        break;
                                    case 'datasheet':
                                        $borderColor = '#ef4444'; // Rouge
                                        $categoryIcon = 'fas fa-file-code';
                                        break;
                                    case 'programme':
                                        $borderColor = '#3b82f6'; // Bleu pour les programmes
                                        $categoryIcon = 'fas fa-code';
                                        break;
                                    case 'document':
                                        $borderColor = '#06b6d4'; // Cyan pour les documents
                                        $categoryIcon = 'fas fa-file-text';
                                        break;
                                    case 'autre':
                                        $borderColor = '#84cc16'; // Lime pour autres
                                        $categoryIcon = 'fas fa-file-alt';
                                        break;
                                    default:
                                        $borderColor = '#6b7280'; // Gris
                                        $categoryIcon = 'fas fa-file';
                                        break;
                                }
                                
                                $filename = isset($file['filename']) ? $file['filename'] : (isset($file['file_name']) ? $file['file_name'] : (isset($file['original_name']) ? $file['original_name'] : 'Fichier sans nom'));
                                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                $is_image = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                ?>
                                
                                <!-- Ligne horizontale pour chaque fichier -->
                                <div style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: #ffffff; border: 2px solid <?php echo $borderColor; ?>; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: all 0.2s ease;" onmouseover="this.style.boxShadow='0 4px 8px rgba(0,0,0,0.15)'" onmouseout="this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)'">
                                    
                                    <!-- 1. Logo/Image (60px) -->
                                    <div style="width: 60px; height: 60px; flex-shrink: 0;">
                                        <?php if ($is_image && ($file['file_category'] ?? '') === 'photo'): ?>
                                            <div style="width: 100%; height: 100%; border-radius: 8px; overflow: hidden; border: 2px solid <?php echo $borderColor; ?>; background: #f8fafc; display: flex; align-items: center; justify-content: center;">
                                                <img src="<?php echo htmlspecialchars($file['file_path'] ?? ''); ?>" 
                                                     alt="<?php echo htmlspecialchars($file['display_name'] ?? $file['original_name'] ?? $filename); ?>"
                                                     style="width: 100%; height: 100%; object-fit: cover;"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                                                <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; color: <?php echo $borderColor; ?>;">
                                                    <i class="<?php echo $categoryIcon; ?>" style="font-size: 1.5rem;"></i>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div style="width: 100%; height: 100%; background: <?php echo $borderColor; ?>20; border-radius: 8px; display: flex; align-items: center; justify-content: center; border: 2px solid <?php echo $borderColor; ?>;">
                                                <i class="<?php echo $categoryIcon; ?>" style="color: <?php echo $borderColor; ?>; font-size: 1.8rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- 2. Nom (éditable) -->
                                    <div style="flex: 1; min-width: 200px;">
                                        <div id="filename-display-overview-<?php echo $file['id']; ?>" style="font-weight: 600; color: var(--dark-color); font-size: 1rem; line-height: 1.4; word-break: break-word; cursor: pointer;" onclick="editFileNameOverview(<?php echo $file['id']; ?>)">
                                            <?php echo htmlspecialchars($file['display_name'] ?? $file['original_name'] ?? $filename); ?>
                                            <i class="fas fa-edit" style="margin-left: 0.5rem; font-size: 0.8rem; color: #9ca3af;"></i>
                                        </div>
                                        <div id="filename-edit-overview-<?php echo $file['id']; ?>" style="display: none;">
                                            <input type="text" id="filename-input-overview-<?php echo $file['id']; ?>" 
                                                   value="<?php echo htmlspecialchars($file['display_name'] ?? $file['original_name'] ?? $filename); ?>"
                                                   style="width: 100%; padding: 0.5rem; border: 2px solid <?php echo $borderColor; ?>; border-radius: 4px; font-size: 0.9rem;">
                                            <div style="margin-top: 0.5rem; display: flex; gap: 0.5rem;">
                                                <button onclick="saveFileNameOverview(<?php echo $file['id']; ?>)" 
                                                        style="background: <?php echo $borderColor; ?>; color: white; border: none; padding: 0.25rem 0.75rem; border-radius: 4px; font-size: 0.8rem; cursor: pointer;">
                                                    <i class="fas fa-check"></i> Sauver
                                                </button>
                                                <button onclick="cancelEditOverview(<?php echo $file['id']; ?>)" 
                                                        style="background: #6b7280; color: white; border: none; padding: 0.25rem 0.75rem; border-radius: 4px; font-size: 0.8rem; cursor: pointer;">
                                                    <i class="fas fa-times"></i> Annuler
                                                </button>
                                            </div>
                                        </div>
                                        <div style="font-size: 0.75rem; color: #9ca3af; margin-top: 0.25rem;">
                                            <span style="background: <?php echo $borderColor; ?>; color: white; padding: 0.125rem 0.5rem; border-radius: 12px; font-size: 0.7rem;">
                                                <?php echo ucfirst($file['file_category'] ?? 'autre'); ?>
                                            </span>
                                            • <?php echo strtoupper($extension); ?> • <?php echo isset($file['file_size']) ? number_format($file['file_size'] / 1024, 1) . ' KB' : 'Taille inconnue'; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- 3. Description -->
                                     <div style="flex: 1; min-width: 150px;">
                                         <?php if (isset($file['description']) && $file['description']) : ?>
                                             <div style="font-size: 0.875rem; color: #4b5563; line-height: 1.4;">
                                                 <?php echo htmlspecialchars($file['description']); ?>
                                             </div>
                                         <?php else: ?>
                                             <div style="font-size: 0.875rem; color: #9ca3af; font-style: italic;">
                                                 Aucune description
                                             </div>
                                         <?php endif; ?>
                                        <div style="font-size: 0.75rem; color: #9ca3af; margin-top: 0.25rem;">
                                            <i class="fas fa-clock" style="margin-right: 0.25rem;"></i>
                                            <?php echo date('d/m/Y H:i', strtotime($file['uploaded_at'])); ?>
                                        </div>
                                    </div>
                                    
                                    <!-- 4. Actions -->
                                    <div style="display: flex; gap: 0.5rem; flex-shrink: 0;">
                                        <?php if (isset($file['id'])): ?>
                                            <a href="download_project_file.php?id=<?php echo $file['id']; ?>" 
                                               style="background: #10b981; color: white; padding: 0.5rem; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; transition: all 0.2s;" 
                                               title="Télécharger"
                                               onmouseover="this.style.background='#059669'" onmouseout="this.style.background='#10b981'">
                                                <i class="fas fa-download" style="font-size: 0.875rem;"></i>
                                            </a>
                                            <button onclick="openEditFileModal(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars($file['display_name'] ?? $file['original_name'] ?? $filename, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($file['description'] ?? '', ENT_QUOTES); ?>')" 
                                                    style="background: #3b82f6; color: white; border: none; padding: 0.5rem; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; transition: all 0.2s;" 
                                                    title="Modifier"
                                                    onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#3b82f6'">
                                                <i class="fas fa-edit" style="font-size: 0.875rem;"></i>
                                            </button>
                                            <form method="POST" action="delete_project_file.php" style="display: inline;">
                                                <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                                <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                                                <button type="submit" 
                                                        style="background: #ef4444; color: white; border: none; padding: 0.5rem; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; transition: all 0.2s;"
                                                        title="Supprimer"
                                                        onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#ef4444'"
                                                        onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce fichier ?')">
                                                    <i class="fas fa-trash" style="font-size: 0.875rem;"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($project_files) > 12): ?>
                                <div style="border: 2px dashed #d1d5db; border-radius: 12px; padding: 2rem; background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%); display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; color: #6b7280; min-height: 150px;">
                                    <i class="fas fa-folder-plus" style="font-size: 2rem; margin-bottom: 0.75rem; opacity: 0.5;"></i>
                                    <div style="font-weight: 600; margin-bottom: 0.25rem;">+ <?php echo count($project_files) - 12; ?> autres fichiers</div>
                                    <div style="font-size: 0.875rem; opacity: 0.7;">Consultez l'onglet Documents pour voir tous les fichiers</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Onglet Composants -->
            <div id="components" class="tab-content">
                <div class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-plus"></i>
                            Ajouter un composant
                        </h3>
                    </div>
                    
                    <form method="POST" class="modern-form">
                        <input type="hidden" name="action" value="add_component">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Composant</label>
                                <div style="position: relative;">
                                    <input type="text" id="component_search" class="form-input" placeholder="Rechercher un composant..." autocomplete="off" required>
                                    <input type="hidden" name="component_id" id="selected_component_id" required>
                                    <div id="component_suggestions" style="position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #d1d5db; border-top: none; border-radius: 0 0 8px 8px; max-height: 200px; overflow-y: auto; z-index: 1000; display: none;"></div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Quantité nécessaire</label>
                                <input type="number" name="quantity_needed" class="form-input" min="1" value="1" required>
                            </div>
                            
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label class="form-label">Notes (optionnel)</label>
                                <textarea name="notes" class="form-textarea" rows="2" placeholder="Notes sur l'utilisation de ce composant..."></textarea>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Ajouter le composant
                        </button>
                    </form>
                </div>

                <?php if (!empty($project_components)): ?>
                <div class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-list"></i>
                            Liste des composants (<?php echo count($project_components); ?>)
                        </h3>
                    </div>
                    
                    <div style="overflow-x: auto;">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Composant</th>
                                    <th>Fabricant</th>
                                    <th>Package</th>
                                    <th>Quantité</th>
                                    <th>Progression</th>
                                    <th>Prix unitaire</th>
                                    <th>Coût total</th>
                                    <th>Localisation</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($project_components as $pc): ?>
                                    <?php
                                    $progress = $pc['quantity_needed'] > 0 ? ($pc['quantity_used'] / $pc['quantity_needed']) * 100 : 0;
                                    $progress = min(100, $progress);
                                    $component_price = $pc['price'] ?? 0;
                                    $total_component_cost = $component_price * $pc['quantity_needed'];
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($pc['component_name']); ?></strong>
                                            <?php if ($pc['notes']): ?>
                                                <br><small style="color: #64748b;"><?php echo htmlspecialchars($pc['notes']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($pc['manufacturer'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($pc['package'] ?? 'N/A'); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                                                <input type="hidden" name="action" value="update_quantity_used">
                                                <input type="hidden" name="pc_id" value="<?php echo $pc['id']; ?>">
                                                <input type="hidden" name="source_tab" value="components">
                                                <input type="number" name="quantity_used" value="<?php echo $pc['quantity_used']; ?>" 
                                                       min="0" max="<?php echo $pc['quantity_needed']; ?>" 
                                                       style="width: 60px; padding: 0.25rem; border: 1px solid var(--border-color); border-radius: 4px;">
                                                <span style="color: #64748b;">/ <?php echo $pc['quantity_needed']; ?></span>
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                        </td>
                                        <td>
                                            <div class="progress-bar" style="width: 100px;">
                                                <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                            </div>
                                            <small style="color: #64748b;"><?php echo number_format($progress, 1); ?>%</small>
                                        </td>
                                        <td><?php echo number_format($component_price, 2); ?>€</td>
                                        <td><strong><?php echo number_format($total_component_cost, 2); ?>€</strong></td>
                                        <td>
                                            <?php if ($pc['casier'] || $pc['tiroir'] || $pc['compartiment']): ?>
                                                <small style="color: #64748b;">
                                                    <?php echo implode(' - ', array_filter([$pc['casier'], $pc['tiroir'], $pc['compartiment']])); ?>
                                                </small>
                                            <?php else: ?>
                                                <small style="color: #64748b;">Non localisé</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="remove_component">
                                                <input type="hidden" name="pc_id" value="<?php echo $pc['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce composant ?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div class="modern-card" style="text-align: center; padding: 3rem;">
                    <i class="fas fa-microchip" style="font-size: 3rem; color: #e2e8f0; margin-bottom: 1rem;"></i>
                    <h3 style="color: #64748b; margin-bottom: 0.5rem;">Aucun composant ajouté</h3>
                    <p style="color: #94a3b8;">Commencez par ajouter des composants à votre projet.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Onglet Travaux & Matériaux -->
            <div id="materials" class="tab-content">
                <div class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-plus"></i>
                            Ajouter un élément
                        </h3>
                    </div>
                    
                    <form method="POST" class="modern-form">
                        <input type="hidden" name="action" value="add_project_item">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Type</label>
                                <select name="item_type" class="form-select" required>
                                    <option value="">Sélectionner un type...</option>
                                    <option value="travail">Travail</option>
                                    <option value="materiel">Matériel</option>
                                    <option value="service">Service</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Nom</label>
                                <input type="text" name="item_name" class="form-input" required placeholder="Nom de l'élément">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Quantité</label>
                                <input type="number" name="item_quantity" class="form-input" step="0.01" min="0.01" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Unité</label>
                                <input type="text" name="item_unit" class="form-input" required placeholder="ex: pièces, heures, m²">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Prix unitaire (€)</label>
                                <input type="number" name="item_unit_price" class="form-input" step="0.01" min="0" required>
                            </div>
                            
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label class="form-label">Description (optionnel)</label>
                                <textarea name="item_description" class="form-textarea" rows="2" placeholder="Description de l'élément..."></textarea>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Ajouter l'élément
                        </button>
                    </form>
                </div>

                <?php if (!empty($project_items)): ?>
                <div class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-list"></i>
                            Liste des éléments (<?php echo count($project_items); ?>)
                        </h3>
                    </div>
                    
                    <div style="overflow-x: auto;">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Type</th>
                                    <th>Quantité</th>
                                    <th>Progression</th>
                                    <th>Prix unitaire</th>
                                    <th>Coût total</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($project_items as $item): ?>
                                    <?php
                                    // Calculer la progression basée sur les quantités réelles
                                    $total_quantity = floatval($item['quantity']);
                                    $completed_quantity = floatval($item['quantity_completed'] ?? 0);
                                    $progress = $total_quantity > 0 ? ($completed_quantity / $total_quantity) * 100 : 0;
                                    $progress = min(100, max(0, $progress));
                                    $item_total_cost = $item['quantity'] * $item['unit_price'];
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                            <?php if ($item['description']): ?>
                                                <br><small style="color: #64748b;"><?php echo htmlspecialchars($item['description']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>

                                            <span style="padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 500; 
                                                         background: <?php 
                                                             if ($item['type'] === 'travail') echo 'rgba(59, 130, 246, 0.1)';
                                                             elseif ($item['type'] === 'service') echo 'rgba(168, 85, 247, 0.1)';
                                                             else echo 'rgba(16, 185, 129, 0.1)';
                                                         ?>;
                                                         color: <?php 
                                                             if ($item['type'] === 'travail') echo '#3b82f6';
                                                             elseif ($item['type'] === 'service') echo '#a855f7';
                                                             else echo '#10b981';
                                                         ?>;">                                                <?php echo ucfirst($item['type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <button onclick="updateProgress(<?php echo $item['id']; ?>, -25)" 
                                                        style="background: #ef4444; color: white; border: none; border-radius: 4px; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.8rem;"
                                                        title="Diminuer la progression">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <div style="display: flex; flex-direction: column; align-items: center; gap: 0.25rem;">
                                                    <div class="progress-bar" style="width: 80px;">
                                                        <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                                                    </div>
                                                    <small style="color: #64748b; font-size: 0.75rem;"><?php echo number_format($completed_quantity, 2); ?> / <?php echo number_format($total_quantity, 2); ?> <?php echo htmlspecialchars($item['unit']); ?></small>
                                                </div>
                                                <button onclick="updateProgress(<?php echo $item['id']; ?>, 25)" 
                                                        style="background: #10b981; color: white; border: none; border-radius: 4px; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.8rem;"
                                                        title="Augmenter la progression">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td><?php echo number_format($item['unit_price'], 2); ?>€</td>
                                        <td><strong><?php echo number_format($item_total_cost, 2); ?>€</strong></td>
                                        <td>
                                            <button onclick="editItem(<?php echo $item['id']; ?>, '<?php echo addslashes($item['name']); ?>', '<?php echo $item['type']; ?>', <?php echo $item['quantity']; ?>, '<?php echo addslashes($item['unit']); ?>', <?php echo $item['unit_price']; ?>, '<?php echo addslashes($item['description'] ?? ''); ?>')" 
                                                    class="btn btn-sm btn-warning" style="margin-right: 0.5rem;" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="remove_project_item">
                                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet élément ?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div class="modern-card" style="text-align: center; padding: 3rem;">
                    <i class="fas fa-tools" style="font-size: 3rem; color: #e2e8f0; margin-bottom: 1rem;"></i>
                    <h3 style="color: #64748b; margin-bottom: 0.5rem;">Aucun élément ajouté</h3>
                    <p style="color: #94a3b8;">Ajoutez des travaux et matériaux à votre projet.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Onglet Documents & Photos -->
            <div id="files" class="tab-content">
                <div class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-upload"></i>
                            Ajouter un fichier
                        </h3>
                    </div>
                    
                    <form action="upload_project_file.php" method="POST" enctype="multipart/form-data" class="modern-form">
                        <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Fichier</label>
                                <input type="file" name="project_file" class="form-input" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Catégorie</label>
                                <select name="file_category" class="form-select" required>
                                    <option value="">Sélectionner une catégorie...</option>
                                    <option value="schema">Schéma</option>
                                    <option value="photo">Photo</option>
                                    <option value="datasheet">Datasheet</option>
                                    <option value="programme">Programme</option>
                                    <option value="document">Document</option>
                                    <option value="autre">Autre</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Nom du fichier (optionnel)</label>
                                <input type="text" name="display_name" class="form-input" placeholder="Nom personnalisé pour le fichier...">
                            </div>
                            
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label class="form-label">Description (optionnel)</label>
                                <textarea name="file_description" class="form-textarea" rows="2" placeholder="Description du fichier..."></textarea>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i>
                            Télécharger le fichier
                        </button>
                    </form>
                </div>

                <?php if (!empty($project_files)): ?>
                <div class="modern-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-folder-open"></i>
                            Fichiers du projet (<?php echo count($project_files); ?>)
                        </h3>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem;">
                        <?php foreach ($project_files as $file): 

                            
                            // Définir les couleurs de bordure selon le type
                            $border_color = '#d1d5db'; // Couleur par défaut
                            $category_color = '#6b7280';
                            $icon = 'fa-file';
                            
                            switch($file['file_category']) {
                                case 'photo':
                                    $border_color = '#10b981'; // Vert pour les photos
                                    $category_color = '#10b981';
                                    $icon = 'fa-image';
                                    break;
                                case 'schema':
                                    $border_color = '#8b5cf6'; // Violet pour les schémas
                                    $category_color = '#8b5cf6';
                                    $icon = 'fa-project-diagram';
                                    break;
                                case 'documentation':
                                    $border_color = '#f59e0b'; // Orange pour la documentation
                                    $category_color = '#f59e0b';
                                    $icon = 'fa-file-alt';
                                    break;
                                case 'datasheet':
                                    $border_color = '#ef4444'; // Rouge pour les datasheets
                                    $category_color = '#ef4444';
                                    $icon = 'fa-file-code';
                                    break;
                                case 'programme':
                                    $border_color = '#3b82f6'; // Bleu pour les programmes
                                    $category_color = '#3b82f6';
                                    $icon = 'fa-code';
                                    break;
                                case 'document':
                                    $border_color = '#06b6d4'; // Cyan pour les documents
                                    $category_color = '#06b6d4';
                                    $icon = 'fa-file-text';
                                    break;
                                case 'autre':
                                case 'autres':
                                    $border_color = '#84cc16'; // Lime pour autres
                                    $category_color = '#84cc16';
                                    $icon = 'fa-file-alt';
                                    break;
                                case 'Schema':
                                case 'schéma':
                                case 'Schéma':
                                    $border_color = '#8b5cf6'; // Violet pour les schémas
                                    $category_color = '#8b5cf6';
                                    $icon = 'fa-project-diagram';
                                    break;
                                case 'Programme':
                                case 'programs':
                                case 'code':
                                    $border_color = '#3b82f6'; // Bleu pour les programmes
                                    $category_color = '#3b82f6';
                                    $icon = 'fa-code';
                                    break;
                                case 'Document':
                                case 'documents':
                                case 'doc':
                                    $border_color = '#06b6d4'; // Cyan pour les documents
                                    $category_color = '#06b6d4';
                                    $icon = 'fa-file-text';
                                    break;
                                default:
                                    $border_color = '#6b7280'; // Gris pour non définis
                                    $category_color = '#6b7280';
                                    $icon = 'fa-file';
                            }
                            
                            // Vérifier si c'est une image pour afficher une miniature
                            $is_image = in_array(strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                        ?>
                            <div style="border: 3px solid <?php echo $border_color; ?>; border-radius: 12px; padding: 1.25rem; background: var(--light-color); box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: transform 0.2s ease, box-shadow 0.2s ease;" 
                                 onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 16px rgba(0,0,0,0.15)'" 
                                 onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.1)'">
                                
                                <!-- Miniature ou icône -->
                                <div style="text-align: center; margin-bottom: 1rem;">
                                    <?php if ($is_image && $file['file_category'] === 'photo'): ?>
                                        <div style="width: 120px; height: 120px; margin: 0 auto; border-radius: 8px; overflow: hidden; border: 2px solid <?php echo $border_color; ?>; background: #f8fafc; display: flex; align-items: center; justify-content: center;">
                                            <img src="<?php echo htmlspecialchars($file['file_path']); ?>" 
                                                 alt="<?php echo htmlspecialchars($file['original_name']); ?>"
                                                 style="width: 100%; height: 100%; object-fit: cover;"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                                            <div style="display: none; width: 100%; height: 100%; align-items: center; justify-content: center; color: <?php echo $category_color; ?>;">
                                                <i class="fas <?php echo $icon; ?>" style="font-size: 2.5rem;"></i>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div style="width: 120px; height: 120px; margin: 0 auto; border-radius: 8px; background: linear-gradient(135deg, <?php echo $border_color; ?>20, <?php echo $border_color; ?>10); display: flex; align-items: center; justify-content: center; border: 2px solid <?php echo $border_color; ?>;">
                                            <i class="fas <?php echo $icon; ?>" style="font-size: 3rem; color: <?php echo $category_color; ?>;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Informations du fichier -->
                                <div style="text-align: center;">
                                    <div id="filename-display-<?php echo $file['id']; ?>" style="font-weight: 600; color: var(--dark-color); word-break: break-word; margin-bottom: 0.5rem; font-size: 0.95rem;">
                                        <?php echo htmlspecialchars($file['display_name'] ?? $file['original_name']); ?>
                                    </div>
                                    <div id="filename-edit-<?php echo $file['id']; ?>" style="display: none; margin-bottom: 0.5rem;">
                                        <input type="text" id="filename-input-<?php echo $file['id']; ?>" 
                                               value="<?php echo htmlspecialchars($file['display_name'] ?? $file['original_name']); ?>"
                                               style="width: 100%; padding: 0.25rem; border: 1px solid <?php echo $border_color; ?>; border-radius: 4px; font-size: 0.9rem; text-align: center;">
                                        <div style="margin-top: 0.5rem;">
                                            <button onclick="saveFileName(<?php echo $file['id']; ?>)" 
                                                    style="background: <?php echo $border_color; ?>; color: white; border: none; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; margin-right: 0.25rem; cursor: pointer;">
                                                <i class="fas fa-check"></i> Sauver
                                            </button>
                                            <button onclick="cancelEdit(<?php echo $file['id']; ?>)" 
                                                    style="background: #6b7280; color: white; border: none; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem; cursor: pointer;">
                                                <i class="fas fa-times"></i> Annuler
                                            </button>
                                        </div>
                                    </div>
                                    <div style="display: inline-block; background: <?php echo $category_color; ?>; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.75rem;">
                                        <?php echo ucfirst($file['file_category']); ?>
                                    </div>
                                </div>
                                
                                <?php if (isset($file['file_description']) && $file['file_description']): ?>
                                    <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 0.75rem; text-align: center; line-height: 1.4;">
                                        <?php echo htmlspecialchars($file['file_description']); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div style="font-size: 0.75rem; color: #94a3b8; margin-bottom: 1rem; text-align: center; line-height: 1.3;">
                                    <div><strong>Taille:</strong> <?php echo number_format($file['file_size'] / 1024, 1); ?> KB</div>
                                    <div><strong>Ajouté:</strong> <?php echo date('d/m/Y H:i', strtotime($file['uploaded_at'])); ?></div>
                                </div>
                                
                                <div style="display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap;">
                                    <button onclick="editFileName(<?php echo $file['id']; ?>)" 
                                            style="background: #6b7280; color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 0.25rem;">
                                        <i class="fas fa-edit"></i>
                                        <span>Renommer</span>
                                    </button>
                                    <a href="download_project_file.php?id=<?php echo $file['id']; ?>" 
                                       class="btn btn-sm" 
                                       style="background: <?php echo $border_color; ?>; color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none; display: flex; align-items: center; gap: 0.25rem;">
                                        <i class="fas fa-download"></i>
                                        <span>Télécharger</span>
                                    </a>
                                    <form method="POST" action="delete_project_file.php" style="display: inline;">
                                        <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                        <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                                        <button type="submit" 
                                                style="background: #ef4444; color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 0.25rem;"
                                                onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce fichier ?')">
                                            <i class="fas fa-trash"></i>
                                            <span>Supprimer</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="modern-card" style="text-align: center; padding: 3rem;">
                    <i class="fas fa-folder-open" style="font-size: 3rem; color: #e2e8f0; margin-bottom: 1rem;"></i>
                    <h3 style="color: #64748b; margin-bottom: 0.5rem;">Aucun fichier ajouté</h3>
                    <p style="color: #94a3b8;">Téléchargez des documents, photos ou schémas pour votre projet.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal de modification de fichier -->
    <div id="editFileModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div style="background-color: #fefefe; margin: 5% auto; padding: 0; border-radius: 12px; width: 90%; max-width: 600px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 600;">
                    <i class="fas fa-edit" style="margin-right: 0.5rem;"></i>
                    Modifier le fichier
                </h3>
                <button onclick="closeEditFileModal()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 50%; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                    &times;
                </button>
            </div>
            <form id="editFileForm" method="POST" enctype="multipart/form-data" style="padding: 2rem;">
                <input type="hidden" name="action" value="edit_file">
                <input type="hidden" name="file_id" id="edit_file_id">
                <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151;">Nom du fichier</label>
                    <input type="text" name="display_name" id="edit_display_name" required 
                           style="width: 100%; padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 1rem; transition: border-color 0.2s;"
                           onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151;">Description</label>
                    <textarea name="description" id="edit_description" rows="3" 
                              style="width: 100%; padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 1rem; resize: vertical; transition: border-color 0.2s;"
                              onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'"
                              placeholder="Description du fichier (optionnel)"></textarea>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #374151;">Remplacer le fichier (optionnel)</label>
                    <input type="file" name="new_file" id="edit_new_file" 
                           style="width: 100%; padding: 0.75rem; border: 2px dashed #e5e7eb; border-radius: 8px; background: #f9fafb; transition: border-color 0.2s;"
                           onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e5e7eb'">
                    <small style="color: #6b7280; font-size: 0.875rem; margin-top: 0.5rem; display: block;">
                        Laissez vide pour conserver le fichier actuel
                    </small>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" onclick="closeEditFileModal()" 
                            style="background: #6b7280; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 500; transition: background-color 0.2s;"
                            onmouseover="this.style.backgroundColor='#4b5563'" onmouseout="this.style.backgroundColor='#6b7280'">
                        <i class="fas fa-times" style="margin-right: 0.5rem;"></i>
                        Annuler
                    </button>
                    <button type="button" onclick="submitEditFile()" 
                            style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 8px; cursor: pointer; font-weight: 500; transition: transform 0.2s;"
                            onmouseover="this.style.transform='translateY(-1px)'" onmouseout="this.style.transform='translateY(0)'">
                        <i class="fas fa-save" style="margin-right: 0.5rem;"></i>
                        Sauvegarder
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Gestion des onglets
        function showTab(tabName) {
            // Masquer tous les contenus
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => {
                content.classList.remove('active');
            });
            
            // Désactiver tous les boutons
            const buttons = document.querySelectorAll('.tab-button');
            buttons.forEach(button => {
                button.classList.remove('active');
            });
            
            // Afficher le contenu sélectionné
            const selectedContent = document.getElementById(tabName);
            if (selectedContent) {
                selectedContent.classList.add('active');
            }
            
            // Activer le bouton sélectionné
            const selectedButton = document.querySelector(`[onclick="showTab('${tabName}')"]`);
            if (selectedButton) {
                selectedButton.classList.add('active');
            }
        }

        // Afficher l'onglet approprié au chargement
        document.addEventListener('DOMContentLoaded', function() {
            // Vérifier d'abord s'il y a un paramètre tab dans l'URL (priorité)
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            
            if (tabParam) {
                showTab(tabParam);
                return;
            }
            
            // Sinon, vérifier s'il y a une ancre dans l'URL
            if (window.location.hash === '#files') {
                showTab('files');
                return;
            }
            if (window.location.hash === '#components') {
                showTab('components');
                return;
            }
            if (window.location.hash === '#materials') {
                showTab('materials');
                return;
            }
            
            // Par défaut, afficher l'onglet overview
            showTab('overview');
        });

        // Autocomplétion pour les composants
        function setupAutocomplete() {
            const input = document.getElementById('component_name');
            if (!input) return;
            
            const availableComponents = <?php echo json_encode(array_column($available_components, 'name')); ?>;
            
            input.addEventListener('input', function() {
                const value = this.value.toLowerCase();
                const suggestions = availableComponents.filter(comp => 
                    comp.toLowerCase().includes(value)
                ).slice(0, 5);
                
                // Afficher les suggestions (implémentation basique)
                console.log('Suggestions:', suggestions);
            });
        }

        // Fonction pour gérer la barre de recherche des composants
        function setupComponentSearch() {
            const searchInput = document.getElementById('component_search');
            const hiddenInput = document.getElementById('selected_component_id');
            const suggestionsDiv = document.getElementById('component_suggestions');
            
            if (!searchInput) return;
            
            // Données des composants disponibles
            const components = <?php echo json_encode($available_components); ?>;
            
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                
                if (query.length < 1) {
                    suggestionsDiv.style.display = 'none';
                    hiddenInput.value = '';
                    return;
                }
                
                // Filtrer les composants
                const filtered = components.filter(comp => {
                    const name = comp.name.toLowerCase();
                    const manufacturer = (comp.manufacturer || '').toLowerCase();
                    return name.includes(query) || manufacturer.includes(query);
                });
                
                // Afficher les suggestions
                if (filtered.length > 0) {
                    suggestionsDiv.innerHTML = '';
                    filtered.slice(0, 10).forEach(comp => {
                        const div = document.createElement('div');
                        div.style.cssText = 'padding: 0.75rem; cursor: pointer; border-bottom: 1px solid #f3f4f6; transition: background-color 0.2s;';
                        div.innerHTML = `
                            <div style="font-weight: 500; color: #1f2937;">${comp.name}</div>
                            <div style="font-size: 0.875rem; color: #6b7280;">
                                ${comp.manufacturer ? comp.manufacturer + ' - ' : ''}Stock: ${comp.quantity}
                            </div>
                        `;
                        
                        div.addEventListener('mouseenter', function() {
                            this.style.backgroundColor = '#f9fafb';
                        });
                        
                        div.addEventListener('mouseleave', function() {
                            this.style.backgroundColor = 'white';
                        });
                        
                        div.addEventListener('click', function() {
                            searchInput.value = comp.name + (comp.manufacturer ? ' - ' + comp.manufacturer : '');
                            hiddenInput.value = comp.id;
                            suggestionsDiv.style.display = 'none';
                        });
                        
                        suggestionsDiv.appendChild(div);
                    });
                    suggestionsDiv.style.display = 'block';
                } else {
                    suggestionsDiv.innerHTML = '<div style="padding: 0.75rem; color: #6b7280; text-align: center;">Aucun composant trouvé</div>';
                    suggestionsDiv.style.display = 'block';
                    hiddenInput.value = '';
                }
            });
            
            // Fermer les suggestions en cliquant ailleurs
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
                    suggestionsDiv.style.display = 'none';
                }
            });
            
            // Gérer les touches du clavier
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    suggestionsDiv.style.display = 'none';
                }
            });
        }
        
        // Initialiser l'autocomplétion et la recherche de composants
        document.addEventListener('DOMContentLoaded', function() {
            setupAutocomplete();
            setupComponentSearch();
        });

        // Fonction pour mettre à jour la progression
        function updateProgress(itemId, change) {
            // Créer un formulaire pour envoyer la requête
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            // Ajouter les champs cachés
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'update_progress';
            form.appendChild(actionInput);
            
            const itemIdInput = document.createElement('input');
            itemIdInput.type = 'hidden';
            itemIdInput.name = 'item_id';
            itemIdInput.value = itemId;
            form.appendChild(itemIdInput);
            
            const changeInput = document.createElement('input');
            changeInput.type = 'hidden';
            changeInput.name = 'progress_change';
            changeInput.value = change;
            form.appendChild(changeInput);
            
            // Ajouter le formulaire au document et le soumettre
            document.body.appendChild(form);
            form.submit();
        }

        // Fonctions pour le renommage des fichiers
        function editFileName(fileId) {
            document.getElementById('filename-display-' + fileId).style.display = 'none';
            document.getElementById('filename-edit-' + fileId).style.display = 'block';
            document.getElementById('filename-input-' + fileId).focus();
        }

        function cancelEdit(fileId) {
            document.getElementById('filename-display-' + fileId).style.display = 'block';
            document.getElementById('filename-edit-' + fileId).style.display = 'none';
        }

        function saveFileName(fileId) {
            const newName = document.getElementById('filename-input-' + fileId).value.trim();
            if (!newName) {
                alert('Le nom du fichier ne peut pas être vide.');
                return;
            }

            // Créer un formulaire pour envoyer la requête
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            // Ajouter les champs cachés
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'rename_file';
            form.appendChild(actionInput);
            
            const fileIdInput = document.createElement('input');
            fileIdInput.type = 'hidden';
            fileIdInput.name = 'file_id';
            fileIdInput.value = fileId;
            form.appendChild(fileIdInput);
            
            const newNameInput = document.createElement('input');
            newNameInput.type = 'hidden';
            newNameInput.name = 'new_name';
            newNameInput.value = newName;
            form.appendChild(newNameInput);
            
            // Ajouter le formulaire au document et le soumettre
            document.body.appendChild(form);
            form.submit();
        }
        
        // Fonctions pour l'édition dans la vue d'ensemble
        function editFileNameOverview(fileId) {
            document.getElementById('filename-display-overview-' + fileId).style.display = 'none';
            document.getElementById('filename-edit-overview-' + fileId).style.display = 'block';
            document.getElementById('filename-input-overview-' + fileId).focus();
        }
        
        function saveFileNameOverview(fileId) {
            const newName = document.getElementById('filename-input-overview-' + fileId).value.trim();
            if (!newName) {
                alert('Le nom du fichier ne peut pas être vide.');
                return;
            }

            // Créer un formulaire pour envoyer la requête
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            // Ajouter les champs cachés
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'rename_file';
            form.appendChild(actionInput);
            
            const fileIdInput = document.createElement('input');
            fileIdInput.type = 'hidden';
            fileIdInput.name = 'file_id';
            fileIdInput.value = fileId;
            form.appendChild(fileIdInput);
            
            const newNameInput = document.createElement('input');
            newNameInput.type = 'hidden';
            newNameInput.name = 'new_name';
            newNameInput.value = newName;
            form.appendChild(newNameInput);
            
            // Ajouter le formulaire au document et le soumettre
            document.body.appendChild(form);
            form.submit();
        }
        
        function cancelEditOverview(fileId) {
            document.getElementById('filename-display-overview-' + fileId).style.display = 'block';
            document.getElementById('filename-edit-overview-' + fileId).style.display = 'none';
        }
        
        // Fonctions pour la modal de modification de fichier
        function openEditFileModal(fileId, displayName, description) {
            document.getElementById('edit_file_id').value = fileId;
            document.getElementById('edit_display_name').value = displayName;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_new_file').value = '';
            document.getElementById('editFileModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeEditFileModal() {
            document.getElementById('editFileModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Fermer la modale en cliquant à l'extérieur
        window.onclick = function(event) {
            const modal = document.getElementById('editFileModal');
            if (event.target === modal) {
                closeEditFileModal();
            }
        }
        
        // Gérer la soumission du formulaire de modification de fichier
        function submitEditFile() {
            const form = document.getElementById('editFileForm');
            const displayName = document.getElementById('edit_display_name').value.trim();
            
            // Validation
            if (!displayName) {
                alert('Le nom du fichier ne peut pas être vide.');
                return;
            }
            
            const formData = new FormData(form);
            
            // Ajouter l'action
            formData.append('action', 'edit_file');
            
            // Afficher un indicateur de chargement
            const submitBtn = document.querySelector('#editFileModal button[onclick="submitEditFile()"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Modification en cours...';
            submitBtn.disabled = true;
            
            fetch('project_detail.php?id=<?php echo $project_id; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    // Recharger la page pour voir les modifications
                    window.location.reload();
                } else {
                    throw new Error('Erreur lors de la modification');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de la modification du fichier');
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        }
        
        // Fonctions pour la modification d'éléments
        function editItem(itemId, name, type, quantity, unit, unitPrice, description) {
            document.getElementById('edit_item_id').value = itemId;
            document.getElementById('edit_item_name').value = name;
            document.getElementById('edit_item_type').value = type;
            document.getElementById('edit_item_quantity').value = quantity;
            document.getElementById('edit_item_unit').value = unit;
            document.getElementById('edit_item_unit_price').value = unitPrice;
            document.getElementById('edit_item_description').value = description;
            document.getElementById('editItemModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeEditItemModal() {
            document.getElementById('editItemModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        function submitEditItem() {
            const form = document.getElementById('editItemForm');
            const itemName = document.getElementById('edit_item_name').value.trim();
            
            // Validation
            if (!itemName) {
                alert('Le nom de l\'élément ne peut pas être vide.');
                return;
            }
            
            const formData = new FormData(form);
            formData.append('action', 'edit_project_item');
            
            // Afficher un indicateur de chargement
            const submitBtn = document.querySelector('#editItemModal button[onclick="submitEditItem()"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Modification en cours...';
            submitBtn.disabled = true;
            
            fetch('project_detail.php?id=<?php echo $project_id; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    window.location.href = 'project_detail.php?id=<?php echo $project_id; ?>&tab=materials&success=item_updated#materials';
                } else {
                    throw new Error('Erreur lors de la modification');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de la modification de l\'élément');
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        }
    </script>
    
    <!-- Modale de modification d'élément -->
    <div id="editItemModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
        <div style="background-color: var(--light-color); margin: 5% auto; padding: 0; border-radius: 12px; width: 90%; max-width: 600px; box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
            <div style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white; padding: 1.5rem; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; font-size: 1.5rem; font-weight: 600;">
                    <i class="fas fa-edit"></i>
                    Modifier l'élément
                </h2>
                <button onclick="closeEditItemModal()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer; padding: 0.5rem; border-radius: 50%; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='rgba(255,255,255,0.2)'" onmouseout="this.style.backgroundColor='transparent'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="editItemForm" style="padding: 2rem;">
                <input type="hidden" id="edit_item_id" name="item_id">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-color);">Type</label>
                        <select id="edit_item_type" name="item_type" class="form-select" required>
                            <option value="">Sélectionner un type...</option>
                            <option value="travail">Travail</option>
                            <option value="materiel">Matériel</option>
                            <option value="service">Service</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-color);">Nom</label>
                        <input type="text" id="edit_item_name" name="item_name" class="form-input" required placeholder="Nom de l'élément">
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-color);">Quantité</label>
                        <input type="number" id="edit_item_quantity" name="item_quantity" class="form-input" step="0.01" min="0.01" required>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-color);">Unité</label>
                        <input type="text" id="edit_item_unit" name="item_unit" class="form-input" required placeholder="ex: pièces, heures, m²">
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-color);">Prix unitaire (€)</label>
                        <input type="number" id="edit_item_unit_price" name="item_unit_price" class="form-input" step="0.01" min="0" required>
                    </div>
                </div>
                
                <div style="margin-bottom: 2rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-color);">Description (optionnel)</label>
                    <textarea id="edit_item_description" name="item_description" class="form-textarea" rows="3" placeholder="Description de l'élément..."></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" onclick="closeEditItemModal()" style="padding: 0.75rem 1.5rem; border: 2px solid var(--border-color); background: transparent; color: var(--text-color); border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s;" onmouseover="this.style.backgroundColor='var(--light-color)'" onmouseout="this.style.backgroundColor='transparent'">
                        Annuler
                    </button>
                    <button type="button" onclick="submitEditItem()" style="padding: 0.75rem 1.5rem; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
                        <i class="fas fa-save"></i>
                        Modifier l'élément
                    </button>
                </div>
            </form>
        </div>
    </div>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>