<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    // Vérifier la structure de la table suppliers
    $stmt = $pdo->query("DESCRIBE suppliers");
    $columns = $stmt->fetchAll();
    
    echo "Structure de la table suppliers:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    // Vérifier si logo_path existe
    $logo_path_exists = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'logo_path') {
            $logo_path_exists = true;
            break;
        }
    }
    
    echo "\nColonne logo_path existe: " . ($logo_path_exists ? "OUI" : "NON") . "\n";
    
} catch(PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
}
?>