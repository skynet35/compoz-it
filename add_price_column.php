<?php
require_once 'config.php';

try {
    // Obtenir la connexion à la base de données
    $pdo = getConnection();
    
    // Vérifier si la colonne price existe déjà
    $stmt = $pdo->query("SHOW COLUMNS FROM data LIKE 'price'");
    $column_exists = $stmt->fetch();
    
    if (!$column_exists) {
        // Ajouter la colonne price
        $pdo->exec("ALTER TABLE data ADD COLUMN price DECIMAL(10,2) DEFAULT NULL AFTER order_quantity");
        echo "Colonne 'price' ajoutée avec succès à la table data.\n";
    } else {
        echo "La colonne 'price' existe déjà dans la table data.\n";
    }
    
} catch (PDOException $e) {
    echo "Erreur lors de l'ajout de la colonne price: " . $e->getMessage() . "\n";
}
?>