<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=not_logged_in');
    exit();
}

// Récupérer les messages de la session
$success = '';
$error = '';
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

try {
    $pdo = getConnection();
    
    // Créer la table packages si elle n'existe pas
    $sql_packages = "
        CREATE TABLE IF NOT EXISTS packages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL UNIQUE,
            description TEXT,
            pin_count INT,
            package_type ENUM('DIP', 'SOIC', 'QFP', 'BGA', 'TO', 'SOT', 'TSSOP', 'MSOP', 'QFN', 'DFN', 'PLCC', 'PGA', 'LGA', 'CSP', 'Other') DEFAULT 'Other',
            pitch DECIMAL(5,2),
            dimensions VARCHAR(255),
            mounting_type ENUM('Through-hole', 'Surface-mount', 'Both') DEFAULT 'Through-hole',
            notes TEXT,
            owner INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_owner (owner),
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql_packages);
    
    // Vérifier si l'utilisateur a déjà des packages (pour éviter de réinsérer les packages par défaut)
    $user_packages_count = $pdo->prepare("SELECT COUNT(*) FROM packages WHERE owner = ?");
    $user_packages_count->execute([$_SESSION['user_id']]);
    
    // Ajouter les packages les plus connus seulement si l'utilisateur n'en a aucun
    if ($user_packages_count->fetchColumn() == 0) {
        $common_packages = [
            // DIP Packages
            ['name' => 'DIP-8', 'description' => 'Package DIP 8 broches standard', 'pin_count' => 8, 'package_type' => 'DIP', 'pitch' => 2.54, 'dimensions' => '9.9x6.4mm', 'mounting_type' => 'Through-hole', 'notes' => 'Package très commun pour les circuits intégrés'],
        ['name' => 'DIP-14', 'description' => 'Package DIP 14 broches', 'pin_count' => 14, 'package_type' => 'DIP', 'pitch' => 2.54, 'dimensions' => '17.8x6.4mm', 'mounting_type' => 'Through-hole', 'notes' => 'Package standard pour circuits logiques'],
        ['name' => 'DIP-16', 'description' => 'Package DIP 16 broches standard', 'pin_count' => 16, 'package_type' => 'DIP', 'pitch' => 2.54, 'dimensions' => '19.3x6.4mm', 'mounting_type' => 'Through-hole', 'notes' => 'Package standard pour microcontrôleurs et circuits logiques'],
        ['name' => 'DIP-18', 'description' => 'Package DIP 18 broches', 'pin_count' => 18, 'package_type' => 'DIP', 'pitch' => 2.54, 'dimensions' => '22.9x6.4mm', 'mounting_type' => 'Through-hole', 'notes' => 'Package pour microcontrôleurs moyens'],
        ['name' => 'DIP-20', 'description' => 'Package DIP 20 broches', 'pin_count' => 20, 'package_type' => 'DIP', 'pitch' => 2.54, 'dimensions' => '25.4x6.4mm', 'mounting_type' => 'Through-hole', 'notes' => 'Package pour microcontrôleurs et circuits complexes'],
        ['name' => 'DIP-24', 'description' => 'Package DIP 24 broches', 'pin_count' => 24, 'package_type' => 'DIP', 'pitch' => 2.54, 'dimensions' => '30.5x15.2mm', 'mounting_type' => 'Through-hole', 'notes' => 'Package large pour circuits complexes'],
        ['name' => 'DIP-28', 'description' => 'Package DIP 28 broches', 'pin_count' => 28, 'package_type' => 'DIP', 'pitch' => 2.54, 'dimensions' => '35.6x15.2mm', 'mounting_type' => 'Through-hole', 'notes' => 'Package pour microcontrôleurs avancés'],
        ['name' => 'DIP-40', 'description' => 'Package DIP 40 broches', 'pin_count' => 40, 'package_type' => 'DIP', 'pitch' => 2.54, 'dimensions' => '50.8x15.2mm', 'mounting_type' => 'Through-hole', 'notes' => 'Package pour microprocesseurs et circuits très complexes'],
        
        // SOT Packages
        ['name' => 'SOT-23', 'description' => 'Small Outline Transistor 3 broches', 'pin_count' => 3, 'package_type' => 'SOT', 'pitch' => 0.95, 'dimensions' => '2.9x1.3x1.1mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package très populaire pour transistors et régulateurs'],
        ['name' => 'SOT-23-5', 'description' => 'SOT-23 5 broches', 'pin_count' => 5, 'package_type' => 'SOT', 'pitch' => 0.95, 'dimensions' => '2.9x1.6x1.1mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Version 5 broches du SOT-23'],
        ['name' => 'SOT-23-6', 'description' => 'SOT-23 6 broches', 'pin_count' => 6, 'package_type' => 'SOT', 'pitch' => 0.95, 'dimensions' => '2.9x1.6x1.1mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Version 6 broches du SOT-23'],
        ['name' => 'SOT-89', 'description' => 'SOT-89 package de puissance', 'pin_count' => 3, 'package_type' => 'SOT', 'pitch' => 1.5, 'dimensions' => '4.5x2.5x1.5mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package de puissance moyenne'],
        ['name' => 'SOT-223', 'description' => 'SOT-223 package de puissance', 'pin_count' => 4, 'package_type' => 'SOT', 'pitch' => 2.3, 'dimensions' => '6.5x3.5x1.6mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package de puissance avec dissipateur thermique'],
        
        // TO Packages
        ['name' => 'TO-92', 'description' => 'Transistor Outline 92', 'pin_count' => 3, 'package_type' => 'TO', 'pitch' => 2.54, 'dimensions' => '5.2x4.2x4.6mm', 'mounting_type' => 'Through-hole', 'notes' => 'Package standard pour transistors de signal'],
        ['name' => 'TO-220', 'description' => 'Transistor Outline package de puissance', 'pin_count' => 3, 'package_type' => 'TO', 'pitch' => 2.54, 'dimensions' => '10.4x4.6x9.9mm', 'mounting_type' => 'Through-hole', 'notes' => 'Package standard pour composants de puissance avec dissipateur'],
        ['name' => 'TO-220AB', 'description' => 'TO-220 avec isolation', 'pin_count' => 3, 'package_type' => 'TO', 'pitch' => 2.54, 'dimensions' => '10.4x4.6x9.9mm', 'mounting_type' => 'Through-hole', 'notes' => 'Version isolée du TO-220'],
        ['name' => 'TO-247', 'description' => 'TO-247 haute puissance', 'pin_count' => 3, 'package_type' => 'TO', 'pitch' => 5.45, 'dimensions' => '20.8x6.6x15.9mm', 'mounting_type' => 'Through-hole', 'notes' => 'Package haute puissance pour MOSFET et IGBT'],
        ['name' => 'TO-263', 'description' => 'TO-263 surface mount', 'pin_count' => 3, 'package_type' => 'TO', 'pitch' => 2.54, 'dimensions' => '10.2x8.4x4.5mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Version CMS du TO-220'],
        
        // SOIC Packages
        ['name' => 'SOIC-8', 'description' => 'Small Outline IC 8 broches', 'pin_count' => 8, 'package_type' => 'SOIC', 'pitch' => 1.27, 'dimensions' => '4.9x3.9mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package CMS standard'],
        ['name' => 'SOIC-14', 'description' => 'Small Outline IC 14 broches', 'pin_count' => 14, 'package_type' => 'SOIC', 'pitch' => 1.27, 'dimensions' => '8.7x3.9mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package CMS pour circuits logiques'],
        ['name' => 'SOIC-16', 'description' => 'Small Outline IC 16 broches', 'pin_count' => 16, 'package_type' => 'SOIC', 'pitch' => 1.27, 'dimensions' => '9.9x3.9mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package CMS populaire'],
        ['name' => 'SOIC-20', 'description' => 'Small Outline IC 20 broches', 'pin_count' => 20, 'package_type' => 'SOIC', 'pitch' => 1.27, 'dimensions' => '12.8x7.5mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package CMS large'],
        ['name' => 'SOIC-28', 'description' => 'Small Outline IC 28 broches', 'pin_count' => 28, 'package_type' => 'SOIC', 'pitch' => 1.27, 'dimensions' => '17.9x7.5mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package CMS pour circuits complexes'],
        
        // TSSOP Packages
        ['name' => 'TSSOP-8', 'description' => 'Thin Shrink Small Outline Package 8 broches', 'pin_count' => 8, 'package_type' => 'TSSOP', 'pitch' => 0.65, 'dimensions' => '3.0x3.0mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package très compact'],
        ['name' => 'TSSOP-14', 'description' => 'TSSOP 14 broches', 'pin_count' => 14, 'package_type' => 'TSSOP', 'pitch' => 0.65, 'dimensions' => '5.0x4.4mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package compact pour circuits intégrés'],
        ['name' => 'TSSOP-16', 'description' => 'TSSOP 16 broches', 'pin_count' => 16, 'package_type' => 'TSSOP', 'pitch' => 0.65, 'dimensions' => '5.0x4.4mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package compact populaire'],
        ['name' => 'TSSOP-20', 'description' => 'TSSOP 20 broches', 'pin_count' => 20, 'package_type' => 'TSSOP', 'pitch' => 0.65, 'dimensions' => '6.5x4.4mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package compact pour microcontrôleurs'],
        ['name' => 'TSSOP-28', 'description' => 'TSSOP 28 broches', 'pin_count' => 28, 'package_type' => 'TSSOP', 'pitch' => 0.65, 'dimensions' => '9.7x4.4mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package compact pour circuits avancés'],
        
        // QFP Packages
        ['name' => 'LQFP-32', 'description' => 'Low Profile Quad Flat Package 32 broches', 'pin_count' => 32, 'package_type' => 'QFP', 'pitch' => 0.8, 'dimensions' => '7x7mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package carré pour microcontrôleurs'],
        ['name' => 'LQFP-48', 'description' => 'LQFP 48 broches', 'pin_count' => 48, 'package_type' => 'QFP', 'pitch' => 0.5, 'dimensions' => '7x7mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package compact haute densité'],
        ['name' => 'LQFP-64', 'description' => 'LQFP 64 broches', 'pin_count' => 64, 'package_type' => 'QFP', 'pitch' => 0.5, 'dimensions' => '10x10mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package pour microcontrôleurs avancés'],
        ['name' => 'LQFP-100', 'description' => 'LQFP 100 broches', 'pin_count' => 100, 'package_type' => 'QFP', 'pitch' => 0.5, 'dimensions' => '14x14mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package haute densité pour processeurs'],
        ['name' => 'TQFP-44', 'description' => 'Thin Quad Flat Package 44 broches', 'pin_count' => 44, 'package_type' => 'QFP', 'pitch' => 0.8, 'dimensions' => '10x10mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package fin pour microcontrôleurs'],
        
        // QFN Packages
        ['name' => 'QFN-16', 'description' => 'Quad Flat No-leads 16 broches', 'pin_count' => 16, 'package_type' => 'QFN', 'pitch' => 0.5, 'dimensions' => '3x3mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package très compact sans pattes'],
        ['name' => 'QFN-20', 'description' => 'QFN 20 broches', 'pin_count' => 20, 'package_type' => 'QFN', 'pitch' => 0.5, 'dimensions' => '4x4mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package compact pour circuits intégrés'],
        ['name' => 'QFN-24', 'description' => 'QFN 24 broches', 'pin_count' => 24, 'package_type' => 'QFN', 'pitch' => 0.5, 'dimensions' => '4x4mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package haute densité'],
        ['name' => 'QFN-32', 'description' => 'QFN 32 broches', 'pin_count' => 32, 'package_type' => 'QFN', 'pitch' => 0.5, 'dimensions' => '5x5mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package pour microcontrôleurs compacts'],
        ['name' => 'QFN-48', 'description' => 'QFN 48 broches', 'pin_count' => 48, 'package_type' => 'QFN', 'pitch' => 0.4, 'dimensions' => '6x6mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package très haute densité'],
        
        // BGA Packages
        ['name' => 'BGA-64', 'description' => 'Ball Grid Array 64 broches', 'pin_count' => 64, 'package_type' => 'BGA', 'pitch' => 0.8, 'dimensions' => '8x8mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package à billes pour haute densité'],
        ['name' => 'BGA-100', 'description' => 'BGA 100 broches', 'pin_count' => 100, 'package_type' => 'BGA', 'pitch' => 0.8, 'dimensions' => '10x10mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package BGA pour processeurs'],
        ['name' => 'BGA-144', 'description' => 'BGA 144 broches', 'pin_count' => 144, 'package_type' => 'BGA', 'pitch' => 0.8, 'dimensions' => '12x12mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package BGA haute performance'],
        ['name' => 'BGA-256', 'description' => 'BGA 256 broches', 'pin_count' => 256, 'package_type' => 'BGA', 'pitch' => 0.8, 'dimensions' => '17x17mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package BGA pour processeurs complexes'],
        
        // MSOP Packages
        ['name' => 'MSOP-8', 'description' => 'Mini Small Outline Package 8 broches', 'pin_count' => 8, 'package_type' => 'MSOP', 'pitch' => 0.65, 'dimensions' => '3x3mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package miniature'],
        ['name' => 'MSOP-10', 'description' => 'MSOP 10 broches', 'pin_count' => 10, 'package_type' => 'MSOP', 'pitch' => 0.5, 'dimensions' => '3x3mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package miniature haute densité'],
        
        // DFN Packages
        ['name' => 'DFN-6', 'description' => 'Dual Flat No-leads 6 broches', 'pin_count' => 6, 'package_type' => 'DFN', 'pitch' => 0.65, 'dimensions' => '2x2mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package ultra-compact'],
        ['name' => 'DFN-8', 'description' => 'DFN 8 broches', 'pin_count' => 8, 'package_type' => 'DFN', 'pitch' => 0.5, 'dimensions' => '2x3mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package très compact sans pattes'],
        
        // PLCC Packages
        ['name' => 'PLCC-20', 'description' => 'Plastic Leaded Chip Carrier 20 broches', 'pin_count' => 20, 'package_type' => 'PLCC', 'pitch' => 1.27, 'dimensions' => '9x9mm', 'mounting_type' => 'Both', 'notes' => 'Package carré avec pattes en J'],
        ['name' => 'PLCC-28', 'description' => 'PLCC 28 broches', 'pin_count' => 28, 'package_type' => 'PLCC', 'pitch' => 1.27, 'dimensions' => '11.5x11.5mm', 'mounting_type' => 'Both', 'notes' => 'Package pour microcontrôleurs'],
        ['name' => 'PLCC-44', 'description' => 'PLCC 44 broches', 'pin_count' => 44, 'package_type' => 'PLCC', 'pitch' => 1.27, 'dimensions' => '16.6x16.6mm', 'mounting_type' => 'Both', 'notes' => 'Package pour circuits complexes'],
        
        // CSP Packages
        ['name' => 'CSP-16', 'description' => 'Chip Scale Package 16 broches', 'pin_count' => 16, 'package_type' => 'CSP', 'pitch' => 0.4, 'dimensions' => '2.5x2.5mm', 'mounting_type' => 'Surface-mount', 'notes' => 'Package ultra-miniature']
    ];
    
    foreach ($common_packages as $package) {
        try {
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM packages WHERE name = ? AND owner = ?");
            $check_stmt->execute([$package['name'], $_SESSION['user_id']]);
            
            if ($check_stmt->fetchColumn() == 0) {
                $insert_stmt = $pdo->prepare("INSERT INTO packages (name, description, pin_count, package_type, pitch, dimensions, mounting_type, notes, owner) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $insert_stmt->execute([
                    $package['name'],
                    $package['description'],
                    $package['pin_count'],
                    $package['package_type'],
                    $package['pitch'],
                    $package['dimensions'],
                    $package['mounting_type'],
                    $package['notes'],
                    $_SESSION['user_id']
                ]);
            }
        } catch (PDOException $e) {
            // Ignorer les erreurs d'insertion des packages par défaut
        }
    }
    } // Fin de la condition if ($user_packages_count->fetchColumn() == 0)
    
    // Traitement des actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add') {
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $pin_count = !empty($_POST['pin_count']) ? (int)$_POST['pin_count'] : null;
            $package_type = $_POST['package_type'];
            $pitch = !empty($_POST['pitch']) ? (float)$_POST['pitch'] : null;
            $dimensions = trim($_POST['dimensions']);
            $mounting_type = $_POST['mounting_type'];
            $notes = trim($_POST['notes']);
            $image_path = trim($_POST['image_path']);
            
            if (!empty($name)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO packages (name, description, pin_count, package_type, pitch, dimensions, mounting_type, notes, image_path, owner) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $description ?: null, $pin_count, $package_type, $pitch, $dimensions ?: null, $mounting_type, $notes ?: null, $image_path ?: null, $_SESSION['user_id']]);
                    $success = "Package ajouté avec succès !";
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $error = "Ce nom de package existe déjà.";
                    } else {
                        $error = "Erreur lors de l'ajout : " . $e->getMessage();
                    }
                }
            } else {
                $error = "Le nom du package est obligatoire.";
            }
        }
        
        if ($action === 'edit') {
            $id = (int)$_POST['id'];
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $pin_count = !empty($_POST['pin_count']) ? (int)$_POST['pin_count'] : null;
            $package_type = $_POST['package_type'];
            $pitch = !empty($_POST['pitch']) ? (float)$_POST['pitch'] : null;
            $dimensions = trim($_POST['dimensions']);
            $mounting_type = $_POST['mounting_type'];
            $notes = trim($_POST['notes']);
            $image_path = trim($_POST['image_path']);
            
            if (!empty($name)) {
                try {
                    $stmt = $pdo->prepare("UPDATE packages SET name = ?, description = ?, pin_count = ?, package_type = ?, pitch = ?, dimensions = ?, mounting_type = ?, notes = ?, image_path = ? WHERE id = ? AND owner = ?");
                    $stmt->execute([$name, $description ?: null, $pin_count, $package_type, $pitch, $dimensions ?: null, $mounting_type, $notes ?: null, $image_path ?: null, $id, $_SESSION['user_id']]);
                    $success = "Package modifié avec succès !";
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $error = "Ce nom de package existe déjà.";
                    } else {
                        $error = "Erreur lors de la modification : " . $e->getMessage();
                    }
                }
            } else {
                $error = "Le nom du package est obligatoire.";
            }
        }
        
        if ($action === 'delete') {
            $id = (int)$_POST['id'];
            try {
                // Vérifier d'abord si le package existe
                $check_stmt = $pdo->prepare("SELECT owner FROM packages WHERE id = ?");
                $check_stmt->execute([$id]);
                $package = $check_stmt->fetch();
                
                if (!$package) {
                    $_SESSION['error_message'] = "Package non trouvé (ID: $id).";
                } elseif ($package['owner'] != $_SESSION['user_id']) {
                    $_SESSION['error_message'] = "Vous n'avez pas les droits pour supprimer ce package (Owner: {$package['owner']}, User: {$_SESSION['user_id']}).";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM packages WHERE id = ? AND owner = ?");
                    $stmt->execute([$id, $_SESSION['user_id']]);
                    
                    if ($stmt->rowCount() > 0) {
                        $_SESSION['success_message'] = "Package supprimé avec succès !";
                    } else {
                        $_SESSION['error_message'] = "Erreur inattendue lors de la suppression.";
                    }
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Erreur lors de la suppression : " . $e->getMessage();
            }
            header('Location: packages_management.php');
            exit;
        }
    }
    
    // Récupérer les packages avec filtres
    $filter_type = $_GET['filter_type'] ?? '';
    $filter_mounting = $_GET['filter_mounting'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $sql = "SELECT * FROM packages WHERE owner = ?";
    $params = [$_SESSION['user_id']];
    
    if (!empty($filter_type)) {
        $sql .= " AND package_type = ?";
        $params[] = $filter_type;
    }
    
    if (!empty($filter_mounting)) {
        $sql .= " AND mounting_type = ?";
        $params[] = $filter_mounting;
    }
    
    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $sql .= " ORDER BY name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $packages = $stmt->fetchAll();
    
    // Récupérer les statistiques
    $stmt = $pdo->prepare("SELECT package_type, COUNT(*) as count FROM packages WHERE owner = ? GROUP BY package_type ORDER BY count DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = "Erreur de base de données : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Packages</title>
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
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            text-decoration: none;
            font-size: 0.8em;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .logout-btn:hover {
            background: #c82333;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
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

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 2px solid #dee2e6;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }

        .form-container {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            border: 2px solid #dee2e6;
            margin-bottom: 30px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .filters {
            background: #e9ecef;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            display: inline-block;
            margin-right: 10px;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
        }

        .btn-secondary {
            background: #2196F3;
            color: white;
        }

        .btn-danger {
            background: #f44336;
            color: white;
        }

        .btn-warning {
            background: #ff9800;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .packages-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .packages-table th,
        .packages-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .packages-table th {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
        }

        .packages-table tr:hover {
            background: #f5f5f5;
        }

        .package-type {
            background: #667eea;
            color: white;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: bold;
        }

        .mounting-type {
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.8em;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 2% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            position: absolute;
            top: 15px;
            right: 20px;
        }

        .close:hover {
            color: black;
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .package-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

        .no-image {
            font-size: 24px;
            color: #999;
            display: inline-block;
            width: 50px;
            height: 50px;
            line-height: 50px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                <span>👤 <?php echo htmlspecialchars($_SESSION['user_email']); ?></span>
                <a href="logout.php" class="logout-btn">🚪 Déconnexion</a>
            </div>
            <h1>📋 Gestion des Packages</h1>
            
            <div class="nav-section">
                <div class="nav-buttons">
                    <a href="components.php">📦 Composants</a>
                    <a href="create_component.php">➕ Créer</a>
                    <a href="projects.php">🚀 Projets</a>
                    <a href="packages_management.php" class="active">📦 Packages</a>
                    <a href="settings.php">⚙️ Paramètres</a>
                </div>
            </div>
        </div>

        <div class="content">
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    ✅ <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Statistiques -->
            <h3>📊 Statistiques</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($packages); ?></div>
                    <div>Total Packages</div>
                </div>
                <?php foreach ($stats as $stat): ?>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stat['count']; ?></div>
                        <div><?php echo htmlspecialchars($stat['package_type']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Formulaire d'ajout -->
            <div class="form-container">
                <h3>➕ Ajouter un nouveau package</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Nom du package *</label>
                            <input type="text" id="name" name="name" required placeholder="Ex: DIP-8, SOIC-16">
                        </div>
                        <div class="form-group">
                            <label for="package_type">Type de package</label>
                            <select id="package_type" name="package_type">
                                <option value="DIP">DIP (Dual In-line Package)</option>
                                <option value="SOIC">SOIC (Small Outline IC)</option>
                                <option value="QFP">QFP (Quad Flat Package)</option>
                                <option value="BGA">BGA (Ball Grid Array)</option>
                                <option value="TO">TO (Transistor Outline)</option>
                                <option value="SOT">SOT (Small Outline Transistor)</option>
                                <option value="DO">DO (Diode Outline)</option>
                                <option value="TSSOP">TSSOP (Thin Shrink Small Outline Package)</option>
                                <option value="MSOP">MSOP (Mini Small Outline Package)</option>
                                <option value="QFN">QFN (Quad Flat No-leads)</option>
                                <option value="DFN">DFN (Dual Flat No-leads)</option>
                                <option value="PLCC">PLCC (Plastic Leaded Chip Carrier)</option>
                                <option value="PGA">PGA (Pin Grid Array)</option>
                                <option value="LGA">LGA (Land Grid Array)</option>
                                <option value="CSP">CSP (Chip Scale Package)</option>
                                <option value="Other">Autre</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="pin_count">Nombre de pins</label>
                            <input type="number" id="pin_count" name="pin_count" min="1" placeholder="Ex: 8, 16, 32">
                        </div>
                        <div class="form-group">
                            <label for="mounting_type">Type de montage</label>
                            <select id="mounting_type" name="mounting_type">
                                <option value="Through-hole">Traversant (Through-hole)</option>
                                <option value="Surface-mount">CMS (Surface-mount)</option>
                                <option value="Both">Les deux</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="pitch">Pas (mm)</label>
                            <input type="number" id="pitch" name="pitch" step="0.01" min="0" placeholder="Ex: 2.54, 1.27, 0.5">
                        </div>
                        <div class="form-group">
                            <label for="dimensions">Dimensions</label>
                            <input type="text" id="dimensions" name="dimensions" placeholder="Ex: 10x8mm, 0.6x0.3x0.15mm">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" placeholder="Description détaillée du package"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" placeholder="Notes et commentaires"></textarea>
                    </div>
                    <div class="form-group">
                    <label>Image du package</label>
                    <small>Placez une image nommée <strong>[nom-du-package].png</strong> ou <strong>[nom-du-package].jpg</strong> dans le dossier <code>img/</code> pour qu'elle s'affiche automatiquement.</small>
                </div>
                    <button type="submit" class="btn btn-primary">✅ Ajouter le package</button>
                </form>
            </div>

            <!-- Filtres -->
            <div class="filters">
                <h3>🔍 Filtres et recherche</h3>
                <form method="GET">
                    <div class="filters-grid">
                        <div class="form-group">
                            <label for="search">Recherche</label>
                            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nom ou description">
                        </div>
                        <div class="form-group">
                            <label for="filter_type">Type de package</label>
                            <select id="filter_type" name="filter_type">
                                <option value="">Tous les types</option>
                                <option value="DIP" <?php echo $filter_type === 'DIP' ? 'selected' : ''; ?>>DIP</option>
                                <option value="SOIC" <?php echo $filter_type === 'SOIC' ? 'selected' : ''; ?>>SOIC</option>
                                <option value="QFP" <?php echo $filter_type === 'QFP' ? 'selected' : ''; ?>>QFP</option>
                                <option value="BGA" <?php echo $filter_type === 'BGA' ? 'selected' : ''; ?>>BGA</option>
                                <option value="TO" <?php echo $filter_type === 'TO' ? 'selected' : ''; ?>>TO</option>
                                <option value="SOT" <?php echo $filter_type === 'SOT' ? 'selected' : ''; ?>>SOT</option>
                                <option value="DO" <?php echo $filter_type === 'DO' ? 'selected' : ''; ?>>DO</option>
                                <option value="Other" <?php echo $filter_type === 'Other' ? 'selected' : ''; ?>>Autre</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="filter_mounting">Type de montage</label>
                            <select id="filter_mounting" name="filter_mounting">
                                <option value="">Tous les montages</option>
                                <option value="Through-hole" <?php echo $filter_mounting === 'Through-hole' ? 'selected' : ''; ?>>Traversant</option>
                                <option value="Surface-mount" <?php echo $filter_mounting === 'Surface-mount' ? 'selected' : ''; ?>>CMS</option>
                                <option value="Both" <?php echo $filter_mounting === 'Both' ? 'selected' : ''; ?>>Les deux</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-secondary">🔍 Filtrer</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Liste des packages -->
            <h3>📋 Liste des packages (<?php echo count($packages); ?>)</h3>
            
            <?php if (empty($packages)): ?>
                <p>Aucun package trouvé. Ajoutez-en un ci-dessus !</p>
            <?php else: ?>
                <table class="packages-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>Pins</th>
                            <th>Montage</th>
                            <th>Pas</th>
                            <th>Dimensions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($packages as $package): ?>
                            <tr>
                                <td>
                                    <?php 
                                    // Chercher l'image dans le dossier img/ avec le nom du package
                                    $package_name = $package['name'];
                                    $image_found = false;
                                    $image_src = '';
                                    
                                    // Vérifier si une image existe avec .png ou .jpg
                                    if (file_exists("img/{$package_name}.png")) {
                                        $image_src = "img/{$package_name}.png";
                                        $image_found = true;
                                    } elseif (file_exists("img/{$package_name}.jpg")) {
                                        $image_src = "img/{$package_name}.jpg";
                                        $image_found = true;
                                    } elseif (!empty($package['image_path'])) {
                                        // Fallback vers l'URL personnalisée si définie
                                        $image_src = $package['image_path'];
                                        $image_found = true;
                                    }
                                    ?>
                                    <?php if ($image_found): ?>
                                        <img src="<?php echo htmlspecialchars($image_src); ?>" 
                                             alt="<?php echo htmlspecialchars($package['name']); ?>" 
                                             class="package-image" 
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                        <span class="no-image" style="display:none;">📦</span>
                                    <?php else: ?>
                                        <span class="no-image">📦</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($package['name']); ?></strong></td>
                                <td><span class="package-type"><?php echo htmlspecialchars($package['package_type']); ?></span></td>
                                <td><?php echo $package['pin_count'] ? htmlspecialchars($package['pin_count']) : '-'; ?></td>
                                <td><span class="mounting-type"><?php echo htmlspecialchars($package['mounting_type']); ?></span></td>
                                <td><?php echo $package['pitch'] ? htmlspecialchars($package['pitch']) . ' mm' : '-'; ?></td>
                                <td><?php echo htmlspecialchars($package['dimensions'] ?: '-'); ?></td>
                                <td>
                                    <div class="actions">
                                        <button class="btn btn-warning" onclick="editPackage(<?php echo $package['id']; ?>)">✏️ Modifier</button>
                                        <button class="btn btn-danger" onclick="deletePackage(<?php echo $package['id']; ?>)">🗑️ Supprimer</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de modification -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h3>✏️ Modifier le package</h3>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_name">Nom du package *</label>
                        <input type="text" id="edit_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_package_type">Type de package</label>
                        <select id="edit_package_type" name="package_type">
                            <option value="DIP">DIP (Dual In-line Package)</option>
                            <option value="SOIC">SOIC (Small Outline IC)</option>
                            <option value="QFP">QFP (Quad Flat Package)</option>
                            <option value="BGA">BGA (Ball Grid Array)</option>
                            <option value="TO">TO (Transistor Outline)</option>
                            <option value="SOT">SOT (Small Outline Transistor)</option>
                            <option value="DO">DO (Diode Outline)</option>
                            <option value="TSSOP">TSSOP (Thin Shrink Small Outline Package)</option>
                            <option value="MSOP">MSOP (Mini Small Outline Package)</option>
                            <option value="QFN">QFN (Quad Flat No-leads)</option>
                            <option value="DFN">DFN (Dual Flat No-leads)</option>
                            <option value="PLCC">PLCC (Plastic Leaded Chip Carrier)</option>
                            <option value="PGA">PGA (Pin Grid Array)</option>
                            <option value="LGA">LGA (Land Grid Array)</option>
                            <option value="CSP">CSP (Chip Scale Package)</option>
                            <option value="Other">Autre</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_pin_count">Nombre de pins</label>
                        <input type="number" id="edit_pin_count" name="pin_count" min="1">
                    </div>
                    <div class="form-group">
                        <label for="edit_mounting_type">Type de montage</label>
                        <select id="edit_mounting_type" name="mounting_type">
                            <option value="Through-hole">Traversant (Through-hole)</option>
                            <option value="Surface-mount">CMS (Surface-mount)</option>
                            <option value="Both">Les deux</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_pitch">Pas (mm)</label>
                        <input type="number" id="edit_pitch" name="pitch" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label for="edit_dimensions">Dimensions</label>
                        <input type="text" id="edit_dimensions" name="dimensions">
                    </div>
                </div>
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description"></textarea>
                </div>
                <div class="form-group">
                    <label for="edit_notes">Notes</label>
                    <textarea id="edit_notes" name="notes"></textarea>
                </div>
                <div class="form-group">
                    <label>Image du package</label>
                    <small>Placez une image nommée <strong>[nom-du-package].png</strong> ou <strong>[nom-du-package].jpg</strong> dans le dossier <code>img/</code> pour qu'elle s'affiche automatiquement.</small>
                </div>
                <button type="submit" class="btn btn-primary">✅ Sauvegarder</button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">❌ Annuler</button>
            </form>
        </div>
    </div>

    <script>
        const packages = <?php echo json_encode($packages); ?>;

        function editPackage(id) {
            const package = packages.find(p => p.id == id);
            if (package) {
                document.getElementById('edit_id').value = package.id;
                document.getElementById('edit_name').value = package.name;
                document.getElementById('edit_package_type').value = package.package_type;
                document.getElementById('edit_pin_count').value = package.pin_count || '';
                document.getElementById('edit_mounting_type').value = package.mounting_type;
                document.getElementById('edit_pitch').value = package.pitch || '';
                document.getElementById('edit_dimensions').value = package.dimensions || '';
                document.getElementById('edit_description').value = package.description || '';
                document.getElementById('edit_notes').value = package.notes || '';
                document.getElementById('editModal').style.display = 'block';
            }
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function deletePackage(id) {
            const package = packages.find(p => p.id == id);
            if (package && confirm(`Êtes-vous sûr de vouloir supprimer le package "${package.name}" ?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Fermer le modal en cliquant à l'extérieur
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>