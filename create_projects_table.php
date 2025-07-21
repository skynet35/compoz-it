<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    // Créer la table projects si elle n'existe pas
    $sql_projects = "
        CREATE TABLE IF NOT EXISTS projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            owner INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            status ENUM('En cours', 'Terminé', 'En attente', 'Annulé') DEFAULT 'En cours',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (owner) REFERENCES users(id) ON DELETE CASCADE
        )
    ";
    
    $pdo->exec($sql_projects);
    echo "Table 'projects' créée avec succès.<br>";
    
    // Créer la table project_components pour la liaison many-to-many
    $sql_project_components = "
        CREATE TABLE IF NOT EXISTS project_components (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            component_id INT NOT NULL,
            quantity_needed INT DEFAULT 1,
            quantity_used INT DEFAULT 0,
            notes TEXT,
            added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (component_id) REFERENCES data(id) ON DELETE CASCADE,
            UNIQUE KEY unique_project_component (project_id, component_id)
        )
    ";
    
    $pdo->exec($sql_project_components);
    echo "Table 'project_components' créée avec succès.<br>";
    
    echo "<br>✅ Tables de projets créées avec succès !";
    echo "<br><a href='projects.php'>Aller à la gestion des projets</a>";
    
} catch(PDOException $e) {
    echo "Erreur lors de la création des tables : " . $e->getMessage();
}
?>