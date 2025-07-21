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
    
    // Vérifier si la colonne supplier_id existe déjà
    $stmt = $pdo->prepare("SHOW COLUMNS FROM data LIKE 'supplier_id'");
    $stmt->execute();
    $column_exists = $stmt->fetch();
    
    if (!$column_exists) {
        // Ajouter la colonne supplier_id à la table data
        $sql = "ALTER TABLE data ADD COLUMN supplier_id INT NULL AFTER manufacturer";
        $pdo->exec($sql);
        
        // Ajouter l'index pour optimiser les requêtes
        $sql_index = "ALTER TABLE data ADD INDEX idx_supplier_id (supplier_id)";
        $pdo->exec($sql_index);
        
        echo "Colonne supplier_id ajoutée avec succès à la table data !";
    } else {
        echo "La colonne supplier_id existe déjà dans la table data.";
    }
    
} catch(PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mise à jour de la base de données</title>
</head>
<body>
    <h1>Mise à jour de la base de données</h1>
    <p><a href="components.php">Retour aux composants</a></p>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>