<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    // Ajouter la colonne updated_at à la table suppliers
    $sql = "ALTER TABLE suppliers ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at";
    $pdo->exec($sql);
    
    echo "Colonne updated_at ajoutée avec succès à la table suppliers.\n";
    
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