<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    
    echo "<h2>Correction de la table project_files</h2>";
    
    // Vérifier si la colonne file_category existe
    $stmt = $pdo->query("SHOW COLUMNS FROM project_files LIKE 'file_category'");
    $columnExists = $stmt->rowCount() > 0;
    
    if (!$columnExists) {
        echo "<p style='color: orange;'>⚠️ La colonne 'file_category' n'existe pas. Ajout en cours...</p>";
        
        // Ajouter la colonne file_category
        $sql = "ALTER TABLE project_files ADD COLUMN file_category ENUM('document', 'photo', 'datasheet', 'program', 'schema', 'autre', 'other') DEFAULT 'autre' AFTER file_size";
        $pdo->exec($sql);
        
        echo "<p style='color: green;'>✅ Colonne 'file_category' ajoutée avec succès!</p>";
    } else {
        echo "<p style='color: green;'>✅ La colonne 'file_category' existe déjà.</p>";
    }
    
    // Vérifier la structure finale
    echo "<h3>Structure finale de la table project_files :</h3>";
    $stmt = $pdo->query("DESCRIBE project_files");
    echo "<table border='1'>";
    echo "<tr><th>Colonne</th><th>Type</th><th>Null</th><th>Défaut</th></tr>";
    while ($row = $stmt->fetch()) {
        $highlight = ($row['Field'] == 'file_category') ? ' style="background-color: #90EE90;"' : '';
        echo "<tr{$highlight}>";
        echo "<td><strong>" . $row['Field'] . "</strong></td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test d'insertion
    echo "<h3>Test d'insertion :</h3>";
    $testSql = "INSERT INTO project_files (project_id, file_name, original_name, file_path, file_type, file_size, file_category, description) 
                VALUES (1, 'test_fix.pdf', 'test_fix.pdf', '/test/fix', 'application/pdf', 2048, 'schema', 'Test après correction')";
    
    try {
        $pdo->exec($testSql);
        echo "<p style='color: green;'>✅ Test d'insertion réussi avec catégorie 'schema'</p>";
        
        // Vérifier l'insertion
        $stmt = $pdo->query("SELECT id, file_category, original_name FROM project_files WHERE original_name = 'test_fix.pdf'");
        $result = $stmt->fetch();
        if ($result) {
            echo "<p><strong>Résultat :</strong> ID: " . $result['id'] . ", Catégorie: '<span style='color: blue;'>{$result['file_category']}</span>'</p>";
            
            // Nettoyer
            $pdo->exec("DELETE FROM project_files WHERE original_name = 'test_fix.pdf'");
            echo "<p style='color: orange;'>🧹 Données de test supprimées</p>";
        }
    } catch(PDOException $e) {
        echo "<p style='color: red;'>❌ Erreur lors du test : " . $e->getMessage() . "</p>";
    }
    
    echo "<p style='color: green; font-weight: bold;'>🎉 Table project_files corrigée! Vous pouvez maintenant tester l'upload de fichiers.</p>";
    echo "<p><a href='project_detail.php?id=1'>Tester l'upload de fichiers</a></p>";
    
} catch(PDOException $e) {
    echo "<p style='color: red;'>❌ Erreur : " . $e->getMessage() . "</p>";
}
?>