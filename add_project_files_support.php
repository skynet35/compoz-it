<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    echo "=== AJOUT DU SUPPORT DES FICHIERS POUR LES PROJETS ===\n\n";
    
    // Ajouter la colonne image_path à la table projects
    $stmt = $pdo->query("SHOW COLUMNS FROM projects LIKE 'image_path'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN image_path VARCHAR(500) NULL AFTER description");
        echo "✅ Colonne 'image_path' ajoutée à la table projects.\n";
    } else {
        echo "ℹ️ Colonne 'image_path' existe déjà dans la table projects.\n";
    }
    
    // Créer la table project_files pour les documents attachés
    $sql_project_files = "
        CREATE TABLE IF NOT EXISTS project_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_type VARCHAR(100) NOT NULL,
            file_size INT NOT NULL,
            file_category ENUM('document', 'photo', 'datasheet', 'program', 'other') DEFAULT 'other',
            description TEXT,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        )
    ";
    
    $pdo->exec($sql_project_files);
    echo "✅ Table 'project_files' créée avec succès.\n";
    
    // Créer le dossier Projets s'il n'existe pas
    $projects_dir = __DIR__ . '/Projets';
    if (!is_dir($projects_dir)) {
        mkdir($projects_dir, 0755, true);
        echo "✅ Dossier 'Projets' créé avec succès.\n";
    } else {
        echo "ℹ️ Dossier 'Projets' existe déjà.\n";
    }
    
    echo "\n🎉 Support des fichiers pour les projets configuré avec succès !\n";
    echo "<br><a href='projects.php'>Retour à la gestion des projets</a>\n";
    
} catch(PDOException $e) {
    echo "❌ Erreur lors de la configuration : " . $e->getMessage() . "\n";
}
?>