<?php
/**
 * Script d'assignation automatique des images de packages
 * 
 * Ce script vérifie tous les composants sans image et leur assigne
 * automatiquement l'image de leur package si elle existe dans le dossier img/
 * 
 * Utilisation :
 * - Exécution manuelle : php auto_assign_package_images.php
 * - Tâche cron quotidienne : 0 2 * * * /usr/bin/php /path/to/auto_assign_package_images.php
 */

require_once 'config.php';

// Configuration
$IMAGE_DIRECTORY = 'C:\\xampp\\htdocs\\Simple\\img\\';
$LOG_FILE = __DIR__ . '/logs/auto_assign_images.log';

// Créer le dossier de logs s'il n'existe pas
if (!file_exists(dirname($LOG_FILE))) {
    mkdir(dirname($LOG_FILE), 0755, true);
}

/**
 * Fonction principale pour scanner et assigner les images
 * Peut être appelée depuis d'autres scripts
 */
function scanAndAssignImages($pdo = null, $user_id = null) {
    global $IMAGE_DIRECTORY, $LOG_FILE;
    
    try {
        if (!$pdo) {
            $pdo = getConnection();
        }
        
        $assigned_count = 0;
        $total_checked = 0;
        
        // Requête pour récupérer les composants sans image mais avec un package
        $query = "SELECT id, name, package FROM data WHERE (image_path IS NULL OR image_path = '') AND package IS NOT NULL AND package != ''";
        if ($user_id) {
            $query .= " AND owner = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$user_id]);
        } else {
            $stmt = $pdo->query($query);
        }
        
        $components = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_checked = count($components);
        
        foreach ($components as $component) {
            $imagePath = findPackageImage($component['package'], $IMAGE_DIRECTORY);
            
            if ($imagePath) {
                // Convertir le chemin absolu en chemin relatif
                $relativeImagePath = 'img/' . basename($imagePath);
                
                // Mettre à jour le composant
                $updateStmt = $pdo->prepare("UPDATE data SET image_path = ? WHERE id = ?");
                $updateStmt->execute([$relativeImagePath, $component['id']]);
                
                $assigned_count++;
                logMessage("Image assignée : {$component['name']} -> {$relativeImagePath}");
            }
        }
        
        return [
            'success' => true,
            'assigned_count' => $assigned_count,
            'total_checked' => $total_checked
        ];
        
    } catch (Exception $e) {
        logMessage("Erreur : " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'assigned_count' => 0,
            'total_checked' => 0
        ];
    }
}

/**
 * Fonction de logging
 */
function logMessage($message) {
    global $LOG_FILE;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($LOG_FILE, "[$timestamp] $message" . PHP_EOL, FILE_APPEND | LOCK_EX);
    echo "[$timestamp] $message" . PHP_EOL;
}

/**
 * Vérifie si une image existe pour un package donné
 */
function findPackageImage($packageName, $imageDirectory) {
    if (empty($packageName)) {
        return null;
    }
    
    // Extensions d'images supportées
    $extensions = ['svg', 'png', 'jpg', 'jpeg', 'gif', 'webp'];
    
    // Stratégies de recherche multiples
    $searchStrategies = [
        $packageName,  // Nom exact
        preg_replace('/[^a-zA-Z0-9\-_]/', '', $packageName), // Nom nettoyé
        strtoupper($packageName), // Nom en majuscules
        strtolower($packageName)  // Nom en minuscules
    ];
    
    foreach ($searchStrategies as $searchName) {
        foreach ($extensions as $ext) {
            $imagePath = $imageDirectory . $searchName . '.' . $ext;
            if (file_exists($imagePath)) {
                return 'img/' . $searchName . '.' . $ext;
            }
        }
    }
    
    // Recherche par correspondance partielle (pour les cas comme "TO-263 (D2PAK).jpg")
    $files = glob($imageDirectory . '*');
    foreach ($files as $file) {
        $filename = pathinfo($file, PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        
        if (in_array($extension, $extensions)) {
            // Vérifier si le nom du package est contenu dans le nom du fichier
            if (stripos($filename, $packageName) !== false) {
                return 'img/' . basename($file);
            }
        }
    }
    
    return null;
}

/**
 * Script principal
 */
try {
    logMessage("Début de l'assignation automatique des images de packages");
    
    // Connexion à la base de données
    $pdo = new PDO('mysql:host=localhost;dbname=toto;charset=utf8', 'root', '1cchkp78');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Récupérer tous les composants sans image mais avec un package défini
    $stmt = $pdo->prepare("
        SELECT id, name, package, image_path 
        FROM data 
        WHERE (image_path IS NULL OR image_path = '' OR image_path = 'default.png') 
        AND package IS NOT NULL 
        AND package != ''
    ");
    $stmt->execute();
    $componentsWithoutImage = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logMessage("Trouvé " . count($componentsWithoutImage) . " composants sans image avec un package défini");
    
    $updatedCount = 0;
    $processedPackages = [];
    
    foreach ($componentsWithoutImage as $component) {
        $packageId = $component['package'];
        
        // Éviter de rechercher plusieurs fois la même image de package
        if (!isset($processedPackages[$packageId])) {
            $imageFound = findPackageImage($packageId, $IMAGE_DIRECTORY);
            $processedPackages[$packageId] = $imageFound;
            
            if ($imageFound) {
                logMessage("Image trouvée pour le package '$packageId': $imageFound");
            }
        }
        
        $packageImagePath = $processedPackages[$packageId];
        
        if ($packageImagePath) {
            // Mettre à jour le composant avec l'image du package
            $updateStmt = $pdo->prepare("UPDATE data SET image_path = ? WHERE id = ?");
            $updateStmt->execute([$packageImagePath, $component['id']]);
            
            $updatedCount++;
            logMessage("Composant '{$component['name']}' (ID: {$component['id']}) mis à jour avec l'image: $packageImagePath");
        }
    }
    
    logMessage("Assignation terminée. $updatedCount composants mis à jour.");
    
    // Statistiques finales
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM data");
    $stmt->execute();
    $totalComponents = $stmt->fetch()['total'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as without_image FROM data WHERE (image_path IS NULL OR image_path = '' OR image_path = 'default.png')");
    $stmt->execute();
    $componentsWithoutImage = $stmt->fetch()['without_image'];
    
    logMessage("Statistiques: $totalComponents composants au total, $componentsWithoutImage sans image");
    
} catch (PDOException $e) {
    logMessage("Erreur de base de données: " . $e->getMessage());
    exit(1);
} catch (Exception $e) {
    logMessage("Erreur: " . $e->getMessage());
    exit(1);
}

logMessage("Script terminé avec succès");
?>