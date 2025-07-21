<?php
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Normalisation des catégories</h2>";
    
    // Mapping des corrections
    $corrections = [
        'Schema' => 'schema',
        'schéma' => 'schema',
        'Schéma' => 'schema',
        'Programme' => 'programme',
        'programs' => 'programme',
        'code' => 'programme',
        'Document' => 'document',
        'documents' => 'document',
        'doc' => 'document',
        'autres' => 'autre',
        'Autre' => 'autre',
        'Photo' => 'photo',
        'Datasheet' => 'datasheet',
        'Documentation' => 'documentation'
    ];
    
    $total_updated = 0;
    
    foreach ($corrections as $old_cat => $new_cat) {
        $stmt = $pdo->prepare("UPDATE project_files SET file_category = ? WHERE file_category = ?");
        $stmt->execute([$new_cat, $old_cat]);
        $updated = $stmt->rowCount();
        if ($updated > 0) {
            echo "<p>Corrigé '$old_cat' → '$new_cat' : $updated fichier(s)</p>";
            $total_updated += $updated;
        }
    }
    
    // Nettoyer les espaces en début/fin
    $stmt = $pdo->prepare("UPDATE project_files SET file_category = TRIM(file_category) WHERE file_category != TRIM(file_category)");
    $stmt->execute();
    $trimmed = $stmt->rowCount();
    if ($trimmed > 0) {
        echo "<p>Supprimé les espaces : $trimmed fichier(s)</p>";
        $total_updated += $trimmed;
    }
    
    echo "<p><strong>Total de fichiers mis à jour : $total_updated</strong></p>";
    
    // Afficher les catégories après correction
    $stmt = $pdo->prepare("SELECT DISTINCT file_category, COUNT(*) as count FROM project_files GROUP BY file_category ORDER BY file_category");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Catégories après correction :</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Catégorie</th><th>Nombre de fichiers</th></tr>";
    
    foreach ($categories as $cat) {
        echo "<tr>";
        echo "<td style='font-family: monospace; background: #f0f0f0;'>'{$cat['file_category']}'</td>";
        echo "<td>{$cat['count']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><a href='project_detail.php?id=1'>Retourner à la page du projet</a></p>";
    
} catch(PDOException $e) {
    echo "Erreur de connexion : " . $e->getMessage();
}
?>