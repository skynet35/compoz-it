<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    // Créer la table project_items pour les travaux et matériaux non stockés
    $sql_project_items = "
        CREATE TABLE IF NOT EXISTS project_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            type ENUM('travail', 'matériel', 'service') NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            quantity DECIMAL(10,2) DEFAULT 1,
            unit VARCHAR(50) DEFAULT 'unité',
            unit_price DECIMAL(10,2) DEFAULT 0,
            total_price DECIMAL(10,2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
            status ENUM('En attente', 'En cours', 'Terminé') DEFAULT 'En attente',
            added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        )
    ";
    
    $pdo->exec($sql_project_items);
    echo "Table 'project_items' créée avec succès.<br>";
    
    echo "<br>✅ Table des éléments de projet créée avec succès !";
    echo "<br><a href='project_detail.php'>Retour aux projets</a>";
    
} catch(PDOException $e) {
    echo "Erreur lors de la création de la table : " . $e->getMessage();
}
?>