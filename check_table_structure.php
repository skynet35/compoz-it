<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    echo "<h2>Structure de la table project_files :</h2>";
    $stmt = $pdo->query("DESCRIBE project_files");
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>Derniers fichiers ajoutés :</h2>";
    $stmt = $pdo->query("SELECT id, original_name, file_category, uploaded_at FROM project_files ORDER BY uploaded_at DESC LIMIT 5");
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Nom</th><th>Catégorie</th><th>Date</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['original_name'] . "</td>";
        echo "<td>'" . $row['file_category'] . "' (" . strlen($row['file_category']) . " chars)</td>";
        echo "<td>" . $row['uploaded_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch(PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
?>