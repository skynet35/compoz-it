<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

// Vérifier que c'est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit();
}

// Récupérer les données POST
if (!isset($_POST['project_id']) || !isset($_POST['image_path'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Données manquantes']);
    exit();
}

$project_id = (int)$_POST['project_id'];
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
    
    // Vérifier que le projet appartient à l'utilisateur
    $stmt = $pdo->prepare("SELECT id FROM projects WHERE id = ? AND owner = ?");
    $stmt->execute([$project_id, $_SESSION['user_id']]);
    $project = $stmt->fetch();
    
    if (!$project) {
        http_response_code(404);
        echo json_encode(['error' => 'Projet non trouvé']);
        exit();
    }
    
    // Mettre à jour l'image du projet
    $stmt = $pdo->prepare("UPDATE projects SET image_path = ? WHERE id = ? AND owner = ?");
    $result = $stmt->execute([$image_path ?: null, $project_id, $_SESSION['user_id']]);
    
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
    error_log("Erreur lors de la mise à jour de l'image du projet : " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données']);
}
?>