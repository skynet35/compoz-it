<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    echo "=== STRUCTURE DE LA TABLE DATA ===\n\n";
    
    // Obtenir la structure de la table data
    $stmt = $pdo->query('DESCRIBE data');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Colonnes actuelles dans la table data :\n";
    echo str_repeat('-', 60) . "\n";
    printf("%-20s %-20s %-10s %-10s\n", "Colonne", "Type", "Null", "Défaut");
    echo str_repeat('-', 60) . "\n";
    
    $current_columns = [];
    foreach ($columns as $column) {
        $current_columns[] = $column['Field'];
        printf("%-20s %-20s %-10s %-10s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Null'], 
            $column['Default'] ?? 'NULL'
        );
    }
    
    echo "\n\n=== VÉRIFICATION DES COLONNES ATTENDUES ===\n\n";
    
    // Colonnes attendues selon votre spécification
    $expected_columns = [
        'id', 'owner', 'name', 'manufacturer', 'package', 'pins', 
        'smd', 'quantity', 'location_id', 'order_quantity', 'price', 
        'datasheet', 'comment', 'category', 'public', 'url', 
        'image_path', 'created_at', 'supplier_id', 'supplier_reference'
    ];
    
    echo "Colonnes attendues : " . implode(', ', $expected_columns) . "\n\n";
    
    // Vérifier les colonnes manquantes
    $missing_columns = array_diff($expected_columns, $current_columns);
    if (!empty($missing_columns)) {
        echo "❌ COLONNES MANQUANTES :\n";
        foreach ($missing_columns as $missing) {
            echo "   - $missing\n";
        }
    } else {
        echo "✅ Toutes les colonnes attendues sont présentes !\n";
    }
    
    // Vérifier les colonnes supplémentaires
    $extra_columns = array_diff($current_columns, $expected_columns);
    if (!empty($extra_columns)) {
        echo "\n⚠️  COLONNES SUPPLÉMENTAIRES :\n";
        foreach ($extra_columns as $extra) {
            echo "   - $extra\n";
        }
    }
    
    echo "\n\n=== INFORMATIONS SUR LES CATÉGORIES ===\n\n";
    
    // Compter les catégories principales
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM category_head');
    $head_count = $stmt->fetchColumn();
    echo "Catégories principales : $head_count\n";
    
    // Compter les sous-catégories
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM category_sub');
    $sub_count = $stmt->fetchColumn();
    echo "Sous-catégories : $sub_count\n";
    
    // Exemple de mapping catégorie/sous-catégorie
    echo "\nExemples de mapping catégorie -> sous-catégorie :\n";
    $stmt = $pdo->query('
        SELECT ch.id as cat_id, ch.name as cat_name, cs.id as sub_id, cs.name as sub_name 
        FROM category_head ch 
        LEFT JOIN category_sub cs ON ch.id = cs.category_head_id 
        ORDER BY ch.id, cs.id 
        LIMIT 10
    ');
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['sub_id']) {
            echo "   Catégorie {$row['cat_id']} ({$row['cat_name']}) -> Sous-cat {$row['sub_id']} ({$row['sub_name']})\n";
        }
    }
    
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
?>