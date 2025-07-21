<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    echo "Suppression des colonnes inutiles de la table data...\n";
    
    // Supprimer les colonnes location et weight
    $columnsToRemove = ['location', 'weight'];
    
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
    
    echo "\nNettoyage terminé avec succès!\n";
    
} catch (PDOException $e) {
    echo "Erreur de connexion: " . $e->getMessage() . "\n";
}
?>