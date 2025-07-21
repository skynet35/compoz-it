<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    // Vérifier si la colonne image_path existe
    $stmt = $pdo->query("SHOW COLUMNS FROM data LIKE 'image_path'");
    $column_exists = $stmt->fetch();
    
    if (!$column_exists) {
        echo "La colonne image_path n'existe pas. Ajout de la colonne...\n";
        
        // Ajouter la colonne image_path
        $pdo->exec("ALTER TABLE data ADD COLUMN image_path VARCHAR(255) AFTER url");
        
        echo "Colonne image_path ajoutée avec succès!\n";
    } else {
        echo "La colonne image_path existe déjà.\n";
    }
    
    // Afficher la structure mise à jour
    echo "\nStructure actuelle de la table 'data':\n";
    $stmt = $pdo->query('DESCRIBE data');
    while($row = $stmt->fetch()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
    
} catch(PDOException $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
?>