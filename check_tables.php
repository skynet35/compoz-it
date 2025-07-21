<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    echo "=== VÉRIFICATION DES TABLES ===\n\n";
    
    // Lister toutes les tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Tables existantes :\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    
    echo "\n=== VÉRIFICATION DES COLONNES ===\n\n";
    
    // Tables à vérifier
    $tablesToCheck = ['location', 'suppliers', 'packages', 'manufacturers', 'data'];
    
    foreach ($tablesToCheck as $table) {
        if (in_array($table, $tables)) {
            echo "Table '$table' :\n";
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $hasOwner = false;
            foreach ($columns as $column) {
                if ($column['Field'] === 'owner') {
                    $hasOwner = true;
                    echo "  ✓ Colonne 'owner' trouvée : {$column['Type']}\n";
                    break;
                }
            }
            
            if (!$hasOwner) {
                echo "  ❌ Colonne 'owner' MANQUANTE\n";
                echo "  Colonnes disponibles : ";
                foreach ($columns as $column) {
                    echo $column['Field'] . " ";
                }
                echo "\n";
            }
            echo "\n";
        } else {
            echo "❌ Table '$table' n'existe PAS\n\n";
        }
    }
    
} catch(PDOException $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
?>