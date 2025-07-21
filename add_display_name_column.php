<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    echo "=== AJOUT DU CHAMP NOM PERSONNALISÉ POUR LES FICHIERS ===\n\n";
    
    // Vérifier si la colonne display_name existe déjà
    $stmt = $pdo->query("SHOW COLUMNS FROM project_files LIKE 'display_name'");
    if ($stmt->rowCount() == 0) {
        // Ajouter la colonne display_name après original_name
        $pdo->exec("ALTER TABLE project_files ADD COLUMN display_name VARCHAR(255) NULL AFTER original_name");
        echo "✅ Colonne 'display_name' ajoutée à la table project_files.\n";
        
        // Mettre à jour les enregistrements existants pour utiliser original_name comme display_name par défaut
        $pdo->exec("UPDATE project_files SET display_name = original_name WHERE display_name IS NULL");
        echo "✅ Noms d'affichage initialisés pour les fichiers existants.\n";
    } else {
        echo "ℹ️ Colonne 'display_name' existe déjà dans la table project_files.\n";
    }
    
    echo "\n🎉 Champ nom personnalisé configuré avec succès !\n";
    echo "<br><a href='project_detail.php'>Retour aux détails du projet</a>\n";
    
} catch(PDOException $e) {
    echo "❌ Erreur lors de la configuration : " . $e->getMessage() . "\n";
}
?>