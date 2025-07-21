<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    echo "Correction des collations de la base de données...\n";
    
    // Modifier la collation de la base de données
    $pdo->exec("ALTER DATABASE `ecdb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Base de données mise à jour.\n";
    
    // Liste des tables à corriger
    $tables = [
        'users' => ['username', 'password', 'email'],
        'data' => ['name', 'manufacturer', 'package', 'smd', 'scrap', 'datasheet', 'comment', 'public', 'url', 'image_path'],
        'manufacturers' => ['name'],
        'location' => ['casier', 'tiroir', 'compartiment'],
        'category_head' => ['name'],
        'category_sub' => ['name']
    ];
    
    foreach ($tables as $table => $columns) {
        echo "Correction de la table $table...\n";
        
        // Modifier la table
        $pdo->exec("ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Modifier chaque colonne texte spécifiquement
        foreach ($columns as $column) {
            try {
                $pdo->exec("ALTER TABLE `$table` MODIFY `$column` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                echo "  - Colonne $column mise à jour\n";
            } catch (PDOException $e) {
                // Ignorer les erreurs pour les colonnes qui n'existent pas ou ont un type différent
                echo "  - Colonne $column ignorée: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\nCorrection terminée avec succès!\n";
    echo "Toutes les tables utilisent maintenant utf8mb4_unicode_ci.\n";
    
} catch(PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>