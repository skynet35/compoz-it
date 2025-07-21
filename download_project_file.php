<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=not_logged_in');
    exit();
}

$file_id = (int)($_GET['id'] ?? 0);

if ($file_id <= 0) {
    header('Location: projects.php?error=invalid_file');
    exit();
}

try {
    $pdo = getConnection();
    
    // Vérifier que le fichier appartient à un projet de l'utilisateur
    $stmt = $pdo->prepare("
        SELECT pf.file_path, pf.original_name, pf.file_type, p.name as project_name 
        FROM project_files pf 
        JOIN projects p ON pf.project_id = p.id 
        WHERE pf.id = ? AND p.owner = ?
    ");
    $stmt->execute([$file_id, $_SESSION['user_id']]);
    $file = $stmt->fetch();
    
    if (!$file) {
        header('Location: projects.php?error=file_not_found');
        exit();
    }
    
    $file_path = __DIR__ . '/' . $file['file_path'];
    
    if (!file_exists($file_path)) {
        header('Location: projects.php?error=file_not_exists');
        exit();
    }
    
    // Définir les en-têtes pour le téléchargement
    header('Content-Type: ' . $file['file_type']);
    header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    // Lire et envoyer le fichier
    readfile($file_path);
    exit();
    
} catch(PDOException $e) {
    header('Location: projects.php?error=database_error');
    exit();
}
?>