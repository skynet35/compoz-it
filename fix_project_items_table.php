<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    echo "Modification de la table project_items...<br>";
    
    // Modifier la colonne type pour utiliser les bonnes valeurs
    $sql_alter = "ALTER TABLE project_items MODIFY COLUMN type ENUM('travail', 'matériel', 'service') NOT NULL";
    $pdo->exec($sql_alter);
    echo "✅ Structure de la table modifiée avec succès.<br>";
    
    // Corriger les données existantes si nécessaire
    $sql_update_work = "UPDATE project_items SET type = 'travail' WHERE type = 'work'";
    $pdo->exec($sql_update_work);
    echo "✅ Données 'work' converties en 'travail'.<br>";
    
    $sql_update_material = "UPDATE project_items SET type = 'matériel' WHERE type = 'material'";
    $pdo->exec($sql_update_material);
    echo "✅ Données 'material' converties en 'matériel'.<br>";
    
    // Vérifier les données après modification
    $stmt = $pdo->query("SELECT id, name, type FROM project_items");
    $items = $stmt->fetchAll();
    
    echo "<br><strong>Éléments dans la base de données :</strong><br>";
    foreach ($items as $item) {
        echo "ID: {$item['id']}, Nom: {$item['name']}, Type: '{$item['type']}'<br>";
    }
    
    echo "<br>✅ Table project_items corrigée avec succès !";
    echo "<br><a href='project_detail.php?id=1'>Retour au projet</a>";
    
} catch(PDOException $e) {
    echo "Erreur lors de la modification de la table : " . $e->getMessage();
}
?>