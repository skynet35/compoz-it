<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    echo "Nettoyage de la table data...\n";
    
    // Supprimer les colonnes inutiles
    $columnsToRemove = ['scrap', 'width', 'depth', 'height'];
    
    foreach ($columnsToRemove as $column) {
        try {
            // Vérifier si la colonne existe avant de la supprimer
            $stmt = $pdo->query("SHOW COLUMNS FROM data LIKE '$column'");
            if ($stmt->rowCount() > 0) {
                $pdo->exec("ALTER TABLE data DROP COLUMN $column");
                echo "Colonne $column supprimée.\n";
            } else {
                echo "Colonne $column n'existe pas.\n";
            }
        } catch (PDOException $e) {
            echo "Erreur lors de la suppression de la colonne $column: " . $e->getMessage() . "\n";
        }
    }
    
    // Vérifier que les colonnes fournisseur existent
    $stmt = $pdo->query("SHOW COLUMNS FROM data LIKE 'supplier_id'");
    if ($stmt->rowCount() == 0) {
        echo "Ajout de la colonne supplier_id...\n";
        $pdo->exec("ALTER TABLE data ADD COLUMN supplier_id INT NULL");
        echo "Colonne supplier_id ajoutée.\n";
    } else {
        echo "Colonne supplier_id existe déjà.\n";
    }
    
    $stmt = $pdo->query("SHOW COLUMNS FROM data LIKE 'supplier_reference'");
    if ($stmt->rowCount() == 0) {
        echo "Ajout de la colonne supplier_reference...\n";
        $pdo->exec("ALTER TABLE data ADD COLUMN supplier_reference VARCHAR(100) NULL");
        echo "Colonne supplier_reference ajoutée.\n";
    } else {
        echo "Colonne supplier_reference existe déjà.\n";
    }
    
    // Vérifier la clé étrangère
    $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'data' AND COLUMN_NAME = 'supplier_id' AND REFERENCED_TABLE_NAME = 'suppliers'");
    if ($stmt->rowCount() == 0) {
        try {
            echo "Ajout de la clé étrangère...\n";
            $pdo->exec("ALTER TABLE data ADD CONSTRAINT fk_data_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL");
            echo "Clé étrangère ajoutée.\n";
        } catch (PDOException $e) {
            echo "Erreur lors de l'ajout de la clé étrangère: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Clé étrangère existe déjà.\n";
    }
    
    echo "\nNettoyage terminé avec succès !\n";
    
} catch(PDOException $e) {
    echo "Erreur de connexion : " . $e->getMessage() . "\n";
}
?>