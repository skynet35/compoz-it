<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    // Créer la table packages si elle n'existe pas
    $sql_packages = "
        CREATE TABLE IF NOT EXISTS packages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL UNIQUE,
            description TEXT,
            pin_count INT,
            package_type ENUM('DIP', 'SOIC', 'QFP', 'BGA', 'TO', 'SOT', 'TSSOP', 'MSOP', 'QFN', 'DFN', 'PLCC', 'PGA', 'LGA', 'CSP', 'Other') DEFAULT 'Other',
            pitch DECIMAL(5,2),
            dimensions VARCHAR(255),
            mounting_type ENUM('Through-hole', 'Surface-mount', 'Both') DEFAULT 'Through-hole',
            notes TEXT,
            owner INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_owner (owner),
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql_packages);
    
    echo "Table 'packages' créée avec succès !\n";
    
} catch(PDOException $e) {
    echo "Erreur lors de la création de la table packages : " . $e->getMessage() . "\n";
}
?>