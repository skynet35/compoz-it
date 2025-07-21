<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    // Vérifier si la colonne quantity_completed existe déjà
    $stmt = $pdo->prepare("SHOW COLUMNS FROM project_items LIKE 'quantity_completed'");
    $stmt->execute();
    $column_exists = $stmt->fetch();
    
    if (!$column_exists) {
        // Ajouter la colonne quantity_completed
        $sql = "ALTER TABLE project_items ADD COLUMN quantity_completed DECIMAL(10,2) DEFAULT 0 AFTER quantity";
        $pdo->exec($sql);
        echo "Colonne 'quantity_completed' ajoutée avec succès à la table 'project_items'.<br>";
        
        // Mettre à jour les valeurs existantes basées sur le statut
        $update_sql = "
            UPDATE project_items 
            SET quantity_completed = CASE 
                WHEN status = 'Terminé' THEN quantity
                WHEN status = 'En cours' THEN quantity * 0.5
                ELSE 0
            END
        ";
        $pdo->exec($update_sql);
        echo "Valeurs de quantity_completed mises à jour basées sur le statut existant.<br>";
    } else {
        echo "La colonne 'quantity_completed' existe déjà.<br>";
    }
    
    echo "<br>✅ Colonne quantity_completed configurée avec succès !";
    echo "<br><a href='project_detail.php'>Retour aux projets</a>";
    
} catch(PDOException $e) {
    echo "Erreur lors de l'ajout de la colonne : " . $e->getMessage();
}
?>