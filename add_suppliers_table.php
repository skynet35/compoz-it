<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    // Créer la table suppliers
    $sql = "CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        contact_person VARCHAR(255),
        email VARCHAR(255),
        phone VARCHAR(50),
        website VARCHAR(255),
        address TEXT,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "Table suppliers créée avec succès.\n";
    
    // Ajouter les colonnes supplier_id et supplier_reference à la table data
    try {
        $pdo->exec("ALTER TABLE data ADD COLUMN supplier_id INT NULL");
        echo "Colonne supplier_id ajoutée à la table data.\n";
    } catch(PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') === false) {
            throw $e;
        }
        echo "Colonne supplier_id existe déjà.\n";
    }
    
    try {
        $pdo->exec("ALTER TABLE data ADD COLUMN supplier_reference VARCHAR(255) NULL");
        echo "Colonne supplier_reference ajoutée à la table data.\n";
    } catch(PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') === false) {
            throw $e;
        }
        echo "Colonne supplier_reference existe déjà.\n";
    }
    
    // Ajouter la clé étrangère
    try {
        $pdo->exec("ALTER TABLE data ADD FOREIGN KEY (supplier_id) REFERENCES suppliers(id)");
        echo "Clé étrangère ajoutée.\n";
    } catch(PDOException $e) {
        echo "Clé étrangère existe déjà ou erreur: " . $e->getMessage() . "\n";
    }
    
    // Insérer quelques fournisseurs par défaut
    $defaultSuppliers = [
        ['Mouser Electronics', 'Support', 'support@mouser.com', '+1-817-804-3800', 'https://www.mouser.com', 'Texas, USA'],
        ['Digi-Key Electronics', 'Support', 'support@digikey.com', '+1-800-344-4539', 'https://www.digikey.com', 'Minnesota, USA'],
        ['Farnell', 'Support', 'support@farnell.com', '+44-113-263-6311', 'https://www.farnell.com', 'Leeds, UK'],
        ['RS Components', 'Support', 'support@rs-online.com', '+44-1536-444105', 'https://www.rs-online.com', 'Corby, UK'],
        ['Conrad Electronic', 'Support', 'support@conrad.fr', '+33-1-56-69-50-00', 'https://www.conrad.fr', 'France']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO suppliers (name, contact_person, email, phone, website, address) VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($defaultSuppliers as $supplier) {
        $stmt->execute($supplier);
    }
    
    echo "Fournisseurs par défaut ajoutés.\n";
    echo "Migration terminée avec succès !\n";
    
} catch(PDOException $e) {
    die("Erreur lors de la migration : " . $e->getMessage());
}
?>