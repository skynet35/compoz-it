<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    // Vérifier si la colonne image_path existe déjà
    $stmt = $pdo->query("SHOW COLUMNS FROM packages LIKE 'image_path'");
    if ($stmt->rowCount() == 0) {
        // Ajouter la colonne image_path
        $sql = "ALTER TABLE packages ADD COLUMN image_path VARCHAR(500) NULL AFTER notes";
        $pdo->exec($sql);
        echo "Colonne image_path ajoutée avec succès à la table packages.\n";
    } else {
        echo "La colonne image_path existe déjà dans la table packages.\n";
    }
    
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
?>