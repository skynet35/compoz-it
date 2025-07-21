<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    // Ajouter la colonne logo_path à la table suppliers
    $sql = "ALTER TABLE suppliers ADD COLUMN logo_path VARCHAR(255) AFTER address";
    $pdo->exec($sql);
    
    echo "Colonne logo_path ajoutée avec succès à la table suppliers.\n";
    
    // Vérifier que la colonne a été ajoutée
    $stmt = $pdo->query("DESCRIBE suppliers");
    $columns = $stmt->fetchAll();
    
    echo "\nStructure mise à jour de la table suppliers:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch(PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>