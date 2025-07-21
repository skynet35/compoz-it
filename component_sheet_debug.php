<?php
session_start();

// Vérification simple de session
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Connexion directe à la base de données
try {
    $pdo = new PDO('mysql:host=localhost;dbname=composants_db;charset=utf8', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Erreur de connexion : ' . $e->getMessage());
}

// Récupération de l'ID
$id = $_GET['id'] ?? null;
if (!$id) {
    die('ID manquant');
}

// Récupération du composant
try {
    $stmt = $pdo->prepare('SELECT * FROM components WHERE id = ?');
    $stmt->execute([$id]);
    $component = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$component) {
        die('Composant non trouvé');
    }
} catch (PDOException $e) {
    die('Erreur de requête : ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Fiche - <?php echo htmlspecialchars($component['name']); ?></title>
    <meta charset="UTF-8">
</head>
<body>
    <h1>Debug Fiche Produit</h1>
    <p><strong>ID:</strong> <?php echo htmlspecialchars($component['id']); ?></p>
    <p><strong>Nom:</strong> <?php echo htmlspecialchars($component['name']); ?></p>
    <p><strong>Catégorie:</strong> <?php echo htmlspecialchars($component['category']); ?></p>
    <p><strong>Quantité:</strong> <?php echo htmlspecialchars($component['quantity']); ?></p>
    <p><strong>Prix:</strong> <?php echo htmlspecialchars($component['price']); ?> €</p>
    <p><a href="components.php">Retour</a></p>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>