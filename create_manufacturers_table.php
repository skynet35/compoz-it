<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    // Créer la table manufacturers si elle n'existe pas
    $sql_manufacturers = "
        CREATE TABLE IF NOT EXISTS manufacturers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            website VARCHAR(255),
            email VARCHAR(255),
            phone VARCHAR(50),
            address TEXT,
            logo_path VARCHAR(255),
            notes TEXT,
            owner INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_manufacturer_owner (name, owner),
            INDEX idx_owner (owner),
            INDEX idx_name (name),
            FOREIGN KEY (owner) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql_manufacturers);
    
    echo "Table 'manufacturers' créée avec succès !\n";
    
} catch(PDOException $e) {
    echo "Erreur lors de la création de la table manufacturers : " . $e->getMessage() . "\n";
}
?>