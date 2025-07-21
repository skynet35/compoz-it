<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

// Vérifier que c'est une requête GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit();
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    $pdo = getConnection();
    
    if (empty($search)) {
        // Retourner tous les packages disponibles, triés par nom
        $stmt = $pdo->prepare("
            SELECT name, package_type, pin_count, mounting_type 
            FROM packages 
            ORDER BY name ASC 
            LIMIT 20
        ");
        $stmt->execute();
    } else {
        // Rechercher les packages qui contiennent la chaîne de recherche
        $stmt = $pdo->prepare("
            SELECT name, package_type, pin_count, mounting_type 
            FROM packages 
            WHERE name LIKE ? OR package_type LIKE ? 
            ORDER BY name ASC 
            LIMIT 20
        ");
        $searchTerm = '%' . $search . '%';
        $stmt->execute([$searchTerm, $searchTerm]);
    }
    
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Extraire seulement les noms des packages
    $packageNames = array_column($packages, 'name');
    
    header('Content-Type: application/json');
    echo json_encode($packageNames);
    
} catch(PDOException $e) {
    error_log("Erreur lors de la récupération des packages : " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données']);
}
?>