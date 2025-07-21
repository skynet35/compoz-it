<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    // Créer la table suppliers si elle n'existe pas
    $sql_suppliers = "
        CREATE TABLE IF NOT EXISTS suppliers (
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
            INDEX idx_owner (owner)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql_suppliers);
    echo "Table suppliers créée ou existe déjà.\n";
    
    // Créer la table supplier_contacts
    $sql_contacts = "
        CREATE TABLE IF NOT EXISTS supplier_contacts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            supplier_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            phone VARCHAR(50),
            position VARCHAR(255),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
            INDEX idx_supplier_id (supplier_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql_contacts);
    echo "Table supplier_contacts créée ou existe déjà.\n";
    
    echo "Tables créées avec succès !\n";
    
} catch(PDOException $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
?>