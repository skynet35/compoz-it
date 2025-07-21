<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=not_logged_in');
    exit();
}

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: components.php');
    exit();
}

// Validation des données
$name = trim($_POST['name'] ?? '');
$manufacturer = trim($_POST['manufacturer'] ?? '');
$new_manufacturer = trim($_POST['new_manufacturer'] ?? '');

// Gérer le nouveau fabricant
if ($manufacturer === '__new__' && !empty($new_manufacturer)) {
    $manufacturer = $new_manufacturer;
}
$package = trim($_POST['package'] ?? '');
$pins = !empty($_POST['pins']) ? (int)$_POST['pins'] : null;
$smd = $_POST['smd'] ?? 'No';
$quantity = (int)($_POST['quantity'] ?? 1);
$order_quantity = (int)($_POST['order_quantity'] ?? 0);
$price = !empty($_POST['price']) ? (float)$_POST['price'] : null;
$location_id = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null;

$datasheet = trim($_POST['datasheet'] ?? '');
$comment = trim($_POST['comment'] ?? '');
$category = !empty($_POST['category']) ? (int)$_POST['category'] : null;
$public = $_POST['public'] ?? 'No';
$url = trim($_POST['url'] ?? '');
$supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
$supplier_reference = trim($_POST['supplier_reference'] ?? '');

// Gestion de l'image (upload, URL ou existante)
$image_path = null;
$image_type = $_POST['image_type'] ?? '';

if ($image_type === 'existing' && !empty($_POST['selected_existing_image'])) {
    // Utiliser une image existante
    $image_path = trim($_POST['selected_existing_image']);
} elseif ($image_type === 'upload' && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'img/';
    
    // Créer le dossier img s'il n'existe pas
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (in_array($file_extension, $allowed_extensions)) {
        // Générer un nom de fichier unique
        $filename = uniqid('component_') . '.jpg'; // Toujours en JPG après compression
        $target_path = $upload_dir . $filename;
        
        // Vérifier si l'extension GD est disponible
        if (extension_loaded('gd') && function_exists('imagecreatefromjpeg')) {
            // Compression et redimensionnement de l'image
            if (compressAndResizeImage($_FILES['image']['tmp_name'], $target_path, 800, 600, 85)) {
                $image_path = $target_path;
            }
        } else {
            // Si GD n'est pas disponible, copier le fichier directement
            $filename = uniqid('component_') . '.' . $file_extension;
            $target_path = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                $image_path = $target_path;
            }
        }
    }
} elseif ($image_type === 'url' && !empty($_POST['image_url'])) {
    // Gérer l'URL de l'image (peut être une URL complète ou un chemin relatif)
    $image_url = trim($_POST['image_url']);
    // Vérifier si c'est une URL complète ou un chemin relatif
    if (filter_var($image_url, FILTER_VALIDATE_URL) || (strpos($image_url, 'img/') === 0 && file_exists($image_url))) {
        $image_path = $image_url;
    }
}

// Fonction de compression et redimensionnement d'image (nécessite l'extension GD)
function compressAndResizeImage($source, $destination, $max_width, $max_height, $quality) {
    // Vérifier si l'extension GD et les fonctions nécessaires sont disponibles
    if (!extension_loaded('gd') || !function_exists('imagecreatefromjpeg') || !function_exists('imagecreatefrompng')) {
        return false;
    }
    $info = getimagesize($source);
    if ($info === false) return false;
    
    $width = $info[0];
    $height = $info[1];
    $mime = $info['mime'];
    
    // Calculer les nouvelles dimensions en gardant le ratio
    $ratio = min($max_width / $width, $max_height / $height);
    $new_width = intval($width * $ratio);
    $new_height = intval($height * $ratio);
    
    // Créer l'image source selon le type
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($source);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    if (!$image) return false;
    
    // Créer la nouvelle image redimensionnée
    $new_image = imagecreatetruecolor($new_width, $new_height);
    
    // Préserver la transparence pour PNG
    if ($mime === 'image/png') {
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
        imagefill($new_image, 0, 0, $transparent);
    }
    
    // Redimensionner l'image
    imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    // Sauvegarder en JPEG avec compression
    $result = imagejpeg($new_image, $destination, $quality);
    
    // Libérer la mémoire
    imagedestroy($image);
    imagedestroy($new_image);
    
    return $result;
}

// Validation des champs obligatoires
if (empty($name)) {
    header('Location: components.php?error=name_required');
    exit();
}

if ($quantity < 0) {
    header('Location: components.php?error=invalid_quantity');
    exit();
}

try {
    $pdo = getConnection();
    
    // Si un nouveau fabricant est spécifié, l'ajouter à la table manufacturers
    if (!empty($manufacturer) && $manufacturer === $new_manufacturer) {
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO manufacturers (name, owner) VALUES (?, ?)");
            $stmt->execute([$manufacturer, $_SESSION['user_id']]);
        } catch (PDOException $e) {
            // Ignorer les erreurs de doublons
        }
    }
    
    // Préparer la requête d'insertion
    $sql = "INSERT INTO data (
        owner, name, manufacturer, package, pins, smd, quantity, order_quantity, price,
        location_id, datasheet, comment,
        category, public, url, image_path, supplier_id, supplier_reference
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $_SESSION['user_id'],
        $name,
        $manufacturer ?: null,
        $package ?: null,
        $pins,
        $smd,
        $quantity,
        $order_quantity,
        $price,
        $location_id,
        $datasheet ?: null,
        $comment ?: null,
        $category,
        $public,
        $url ?: null,
        $image_path,
        $supplier_id,
        $supplier_reference ?: null
    ]);
    
    if ($result) {
        header('Location: components.php?success=component_added');
    } else {
        header('Location: components.php?error=add_failed');
    }
    
} catch(PDOException $e) {
    error_log("Erreur lors de l'ajout du composant : " . $e->getMessage());
    header('Location: components.php?error=database_error');
}

exit();
?>