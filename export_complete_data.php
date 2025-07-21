<?php
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
session_start();
if (!isset($_SESSION['user_id'])) {
    die('Vous devez être connecté pour exporter les données.');
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = getConnection();
    
    // Requête pour extraire toutes les données avec les informations de catégories
    $stmt = $pdo->prepare("
        SELECT 
            d.id,
            d.owner,
            d.name,
            d.manufacturer,
            d.package,
            d.pins,
            d.smd,
            d.quantity,
            d.location_id,
            d.order_quantity,
            d.price,
            d.datasheet,
            d.comment,
            d.category,
            d.public,
            d.url,
            d.image_path,
            d.created_at,
            d.supplier_id,
            d.supplier_reference,
            -- Informations de catégorie
            cs.name as subcategory_name,
            ch.id as category_head_id,
            ch.name as category_head_name,
            -- Informations d'emplacement
            l.casier,
            l.tiroir,
            l.compartiment,
            l.description as location_description,
            -- Informations de fournisseur
            s.name as supplier_name
        FROM data d
        LEFT JOIN category_sub cs ON d.category = cs.id
        LEFT JOIN category_head ch ON cs.category_head_id = ch.id
        LEFT JOIN location l ON d.location_id = l.id
        LEFT JOIN suppliers s ON d.supplier_id = s.id
        WHERE d.owner = ?
        ORDER BY d.name
    ");
    
    $stmt->execute([$user_id]);
    $components = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Afficher les résultats
    echo "=== EXPORT COMPLET DES DONNÉES ===\n\n";
    echo "Nombre de composants trouvés : " . count($components) . "\n\n";
    
    if (!empty($components)) {
        echo "Structure des données exportées :\n";
        echo str_repeat('=', 80) . "\n";
        
        // Afficher les en-têtes
        $headers = array_keys($components[0]);
        echo "Colonnes disponibles : " . implode(', ', $headers) . "\n\n";
        
        // Afficher quelques exemples
        echo "Exemples de données (3 premiers composants) :\n";
        echo str_repeat('-', 80) . "\n";
        
        for ($i = 0; $i < min(3, count($components)); $i++) {
            $component = $components[$i];
            echo "Composant #" . ($i + 1) . " :\n";
            
            foreach ($component as $key => $value) {
                echo sprintf("  %-25s : %s\n", $key, $value ?? 'NULL');
            }
            echo "\n";
        }
        
        echo "\n=== INFORMATIONS POUR L'IMPORT ===\n\n";
        echo "Pour réimporter ces données, vous devez connaître :\n";
        echo "1. Les IDs des catégories principales (category_head_id) :\n";
        
        // Lister les catégories principales utilisées
        $stmt = $pdo->query("
            SELECT DISTINCT ch.id, ch.name 
            FROM category_head ch 
            INNER JOIN category_sub cs ON ch.id = cs.category_head_id 
            INNER JOIN data d ON cs.id = d.category 
            WHERE d.owner = $user_id
            ORDER BY ch.id
        ");
        
        $used_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($used_categories as $cat) {
            echo "   - ID {$cat['id']} : {$cat['name']}\n";
        }
        
        echo "\n2. Les IDs des sous-catégories (category) :\n";
        
        // Lister les sous-catégories utilisées
        $stmt = $pdo->query("
            SELECT DISTINCT cs.id, cs.name, ch.name as parent_name 
            FROM category_sub cs 
            INNER JOIN category_head ch ON cs.category_head_id = ch.id 
            INNER JOIN data d ON cs.id = d.category 
            WHERE d.owner = $user_id
            ORDER BY cs.id
        ");
        
        $used_subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($used_subcategories as $subcat) {
            echo "   - ID {$subcat['id']} : {$subcat['name']} (parent: {$subcat['parent_name']})\n";
        }
        
        echo "\n3. Mapping complet catégorie -> sous-catégorie :\n";
        foreach ($used_subcategories as $subcat) {
            $parent_id = null;
            foreach ($used_categories as $cat) {
                if ($cat['name'] === $subcat['parent_name']) {
                    $parent_id = $cat['id'];
                    break;
                }
            }
            echo "   - Catégorie {$parent_id} -> Sous-catégorie {$subcat['id']}\n";
        }
        
    } else {
        echo "Aucun composant trouvé pour cet utilisateur.\n";
    }
    
} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage() . "\n";
}
?>