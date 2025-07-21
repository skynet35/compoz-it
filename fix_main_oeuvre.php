<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    echo "<h2>Correction de l'élément 'main d'oeuvre'</h2>";
    
    // Trouver l'élément "main d'oeuvre"
    $stmt = $pdo->prepare("SELECT * FROM project_items WHERE name LIKE '%main%oeuvre%' OR name LIKE '%main%œuvre%'");
    $stmt->execute();
    $items = $stmt->fetchAll();
    
    if (empty($items)) {
        echo "Aucun élément 'main d'oeuvre' trouvé.<br>";
        
        // Afficher tous les éléments pour debug
        $stmt = $pdo->query("SELECT id, name, type FROM project_items");
        $all_items = $stmt->fetchAll();
        
        echo "<h3>Tous les éléments :</h3>";
        foreach ($all_items as $item) {
            echo "ID: {$item['id']}, Nom: '{$item['name']}', Type: '{$item['type']}'<br>";
        }
    } else {
        echo "Éléments trouvés :<br>";
        foreach ($items as $item) {
            echo "ID: {$item['id']}, Nom: '{$item['name']}', Type actuel: '{$item['type']}'<br>";
            
            // Mettre à jour le type vers 'service'
            $update_stmt = $pdo->prepare("UPDATE project_items SET type = 'service' WHERE id = ?");
            $update_stmt->execute([$item['id']]);
            
            echo "✅ Type mis à jour vers 'service' pour l'élément ID {$item['id']}<br>";
        }
    }
    
    echo "<br><a href='project_detail.php?id=1'>Retour au projet</a>";
    
} catch(PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
?>