<?php
// Activer le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'session_init.php';
require_once 'config.php';

// Définir les en-têtes JSON
header('Content-Type: application/json; charset=utf-8');

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé', 'session_user_id' => ($_SESSION['user_id'] ?? 'NOT SET')]);
    exit();
}

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit();
}

// Récupérer les données POST
if (!isset($_POST['component_id']) || !isset($_POST['image_path'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Données manquantes']);
    exit();
}

$component_id = (int)$_POST['component_id'];
$image_path = trim($_POST['image_path']);

// Valider le chemin de l'image (doit être dans le dossier img/)
if (!empty($image_path) && !str_starts_with($image_path, 'img/')) {
    http_response_code(400);
    echo json_encode(['error' => 'Chemin d\'image invalide']);
    exit();
}

// Vérifier que le fichier existe si un chemin est fourni
if (!empty($image_path) && !file_exists($image_path)) {
    http_response_code(400);
    echo json_encode(['error' => 'Fichier image non trouvé']);
    exit();
}

try {
    $pdo = getConnection();
    
    // Vérifier que le composant appartient à l'utilisateur
    $stmt = $pdo->prepare("SELECT id FROM data WHERE id = ? AND owner = ?");
    $stmt->execute([$component_id, $_SESSION['user_id']]);
    $component = $stmt->fetch();
    
    if (!$component) {
        http_response_code(404);
        echo json_encode(['error' => 'Composant non trouvé']);
        exit();
    }
    
    // Mettre à jour l'image du composant
    $stmt = $pdo->prepare("UPDATE data SET image_path = ? WHERE id = ? AND owner = ?");
    $result = $stmt->execute([$image_path ?: null, $component_id, $_SESSION['user_id']]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'image_path' => $image_path
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la mise à jour']);
    }
    
} catch(PDOException $e) {
    error_log("Erreur lors de la mise à jour de l'image : " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données']);
}
?>