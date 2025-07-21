<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    // Vérifier la structure de la table data
    echo "Structure de la table 'data':\n";
    $stmt = $pdo->query('DESCRIBE data');
    while($row = $stmt->fetch()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
    
    echo "\n\nStructure de la table 'location':\n";
    $stmt = $pdo->query('DESCRIBE location');
    while($row = $stmt->fetch()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
    
    // Vérifier si la colonne location_id existe
    $stmt = $pdo->query("SHOW COLUMNS FROM data LIKE 'location_id'");
    $column_exists = $stmt->fetch();
    
    if (!$column_exists) {
        echo "\n\nLa colonne location_id n'existe pas. Ajout de la colonne...\n";
        
        // Ajouter la colonne location_id
        $pdo->exec("ALTER TABLE data ADD COLUMN location_id INT AFTER quantity");
        
        // Ajouter la clé étrangère
        $pdo->exec("ALTER TABLE data ADD FOREIGN KEY (location_id) REFERENCES location(id)");
        
        echo "Colonne location_id ajoutée avec succès!\n";
    } else {
        echo "\n\nLa colonne location_id existe déjà.\n";
    }
    
} catch(PDOException $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
?>