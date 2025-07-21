<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=not_logged_in');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file_id = (int)($_POST['file_id'] ?? 0);
    $project_id = (int)($_POST['project_id'] ?? 0);
    
    if ($file_id <= 0 || $project_id <= 0) {
        header('Location: projects.php?error=invalid_parameters');
        exit();
    }
    
    try {
        $pdo = getConnection();
        
        // Vérifier que le fichier appartient à un projet de l'utilisateur
        $stmt = $pdo->prepare("
            SELECT pf.file_path, p.name as project_name 
            FROM project_files pf 
            JOIN projects p ON pf.project_id = p.id 
            WHERE pf.id = ? AND p.owner = ?
        ");
        $stmt->execute([$file_id, $_SESSION['user_id']]);
        $file = $stmt->fetch();
        
        if (!$file) {
            header('Location: project_detail.php?id=' . $project_id . '&error=file_not_found#files');
            exit();
        }
        
        // Supprimer le fichier physique
        $file_path = __DIR__ . '/' . $file['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Supprimer l'enregistrement de la base de données
        $stmt = $pdo->prepare("DELETE FROM project_files WHERE id = ?");
        $stmt->execute([$file_id]);
        
        header('Location: project_detail.php?id=' . $project_id . '&tab=files&success=file_deleted#files');
        
    } catch(PDOException $e) {
        header('Location: project_detail.php?id=' . $project_id . '&error=delete_failed#files');
    }
} else {
    header('Location: projects.php');
}
exit();
?>