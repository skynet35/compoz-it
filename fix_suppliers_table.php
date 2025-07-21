<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    echo "=== CORRECTION DE LA TABLE SUPPLIERS ===\n\n";
    
    // Vérifier si la colonne owner existe déjà
    $stmt = $pdo->query("DESCRIBE suppliers");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('owner', $columns)) {
        echo "Ajout de la colonne 'owner' à la table suppliers...\n";
        
        // Ajouter la colonne owner
        $sql = "ALTER TABLE suppliers ADD COLUMN owner INT NOT NULL DEFAULT 1";
        $pdo->exec($sql);
        
        // Ajouter la clé étrangère
        $sql = "ALTER TABLE suppliers ADD FOREIGN KEY (owner) REFERENCES users(id) ON DELETE CASCADE";
        $pdo->exec($sql);
        
        // Ajouter un index
        $sql = "ALTER TABLE suppliers ADD INDEX idx_owner (owner)";
        $pdo->exec($sql);
        
        echo "✓ Colonne 'owner' ajoutée avec succès !\n";
        echo "✓ Clé étrangère ajoutée\n";
        echo "✓ Index ajouté\n";
    } else {
        echo "✓ La colonne 'owner' existe déjà\n";
    }
    
    echo "\n=== VÉRIFICATION FINALE ===\n";
    $stmt = $pdo->query("DESCRIBE suppliers");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'owner') {
            echo "✓ Colonne 'owner' : {$column['Type']} {$column['Null']} {$column['Key']}\n";
            break;
        }
    }
    
} catch(PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}
?>