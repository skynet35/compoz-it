<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=not_logged_in');
    exit();
}

try {
    $pdo = getConnection();
    $user_id = $_SESSION['user_id'];
    
    // Définir le nom du fichier d'export
    $filename = 'export_composants_' . date('Y-m-d_H-i-s') . '.sql';
    
    // Headers pour le téléchargement
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Commencer l'export SQL
    echo "-- Export de la base de données des composants\n";
    echo "-- Généré le : " . date('Y-m-d H:i:s') . "\n";
    echo "-- Utilisateur : " . (isset($_SESSION['user_email']) ? $_SESSION['user_email'] : 'Utilisateur ID: ' . $user_id) . "\n\n";
    
    // Tables à exporter avec leurs données (seulement les données, pas la structure)
    $tables = [
        'categories' => 'Catégories',
        'subcategories' => 'Sous-catégories', 
        'location' => 'Emplacements',
        'suppliers' => 'Fournisseurs',
        'packages' => 'Packages',
        'data' => 'Composants'
    ];
    
    foreach ($tables as $table => $description) {
        echo "-- \n-- Export des données : $description ($table)\n-- \n\n";
        
        // Vérifier si la table existe
        try {
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE owner = ?");
            $check_stmt->execute([$user_id]);
        } catch (PDOException $e) {
            echo "-- Erreur : Table $table non trouvée ou inaccessible\n\n";
            continue;
        }
        
        // Récupérer les données de l'utilisateur
        $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE owner = ?");
        $stmt->execute([$user_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            // Générer les requêtes INSERT
            $columns = array_keys($rows[0]);
            echo "-- Suppression des données existantes pour cette table\n";
            echo "DELETE FROM `$table` WHERE owner = $user_id;\n\n";
            
            echo "-- Insertion des nouvelles données\n";
            echo "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n";
            
            $values = [];
            foreach ($rows as $row) {
                $escaped_values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $escaped_values[] = 'NULL';
                    } elseif (is_numeric($value)) {
                        $escaped_values[] = $value;
                    } else {
                        $escaped_values[] = "'" . str_replace("'", "''", $value) . "'";
                    }
                }
                $values[] = '(' . implode(', ', $escaped_values) . ')';
            }
            
            echo implode(",\n", $values) . ";\n\n";
        } else {
            echo "-- Aucune donnée trouvée pour cette table\n\n";
        }
    }
    
    echo "-- Fin de l'export\n";
    echo "-- Total des tables exportées : " . count($tables) . "\n";
    
} catch(PDOException $e) {
    // En cas d'erreur, rediriger vers settings avec un message d'erreur
    header('Location: settings.php?error=export_failed&message=' . urlencode($e->getMessage()));
    exit();
}
?>