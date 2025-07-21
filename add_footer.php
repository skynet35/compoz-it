<?php
/**
 * Script pour ajouter le footer de copyright à toutes les pages du site
 * Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025
 */

// Texte du footer à ajouter
$footerText = '
    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
';

// Répertoire à traiter
$directory = __DIR__;

// Fichiers à exclure (ce script lui-même et les fichiers de sauvegarde)
$excludeFiles = ['add_footer.php', 'install_old.php', 'install_improved.php'];

// Fonction pour traiter un fichier
function addFooterToFile($filePath, $footerText) {
    $content = file_get_contents($filePath);
    
    // Vérifier si le footer n'est pas déjà présent
    if (strpos($content, 'Créé par Jérémy Leroy') !== false) {
        echo "Footer déjà présent dans: " . basename($filePath) . "\n";
        return false;
    }
    
    // Chercher la balise </body>
    if (preg_match('/(.*?)(<\/body>.*)/s', $content, $matches)) {
        $beforeBody = $matches[1];
        $afterBody = $matches[2];
        
        // Insérer le footer avant </body>
        $newContent = $beforeBody . $footerText . $afterBody;
        
        // Écrire le fichier modifié
        if (file_put_contents($filePath, $newContent)) {
            echo "Footer ajouté à: " . basename($filePath) . "\n";
            return true;
        } else {
            echo "Erreur lors de l'écriture de: " . basename($filePath) . "\n";
            return false;
        }
    } else {
        echo "Balise </body> non trouvée dans: " . basename($filePath) . "\n";
        return false;
    }
}

// Parcourir tous les fichiers PHP
$phpFiles = glob($directory . '/*.php');
$processedCount = 0;
$skippedCount = 0;

echo "Début du traitement des fichiers PHP...\n\n";

foreach ($phpFiles as $file) {
    $fileName = basename($file);
    
    // Exclure certains fichiers
    if (in_array($fileName, $excludeFiles)) {
        echo "Fichier exclu: $fileName\n";
        $skippedCount++;
        continue;
    }
    
    // Traiter le fichier
    if (addFooterToFile($file, $footerText)) {
        $processedCount++;
    } else {
        $skippedCount++;
    }
}

echo "\n=== RÉSUMÉ ===\n";
echo "Fichiers traités avec succès: $processedCount\n";
echo "Fichiers ignorés/échoués: $skippedCount\n";
echo "Total des fichiers PHP: " . count($phpFiles) . "\n";
echo "\nTraitement terminé !\n";

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajout du Footer - CompoZ'IT</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: #f8f9fa;
        }
        .container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success {
            color: #28a745;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
        }
        .info {
            color: #0c5460;
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
        }
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 0.5rem;
        }
        .btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎯 Ajout du Footer de Copyright</h1>
        
        <div class="success">
            <h3>✅ Traitement terminé avec succès !</h3>
            <p>Le footer de copyright a été ajouté à toutes les pages du site CompoZ'IT.</p>
        </div>
        
        <div class="info">
            <h3>📋 Détails du footer ajouté :</h3>
            <p><strong>Texte :</strong> Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0</p>
            <p><strong>Position :</strong> En bas de chaque page, avant la balise &lt;/body&gt;</p>
            <p><strong>Style :</strong> Footer centré avec bordure supérieure et arrière-plan gris clair</p>
        </div>
        
        <div class="info">
            <h3>🔧 Actions effectuées :</h3>
            <ul>
                <li>Parcours automatique de tous les fichiers PHP</li>
                <li>Vérification de la présence du footer (évite les doublons)</li>
                <li>Insertion du footer avant la balise &lt;/body&gt;</li>
                <li>Exclusion des fichiers de sauvegarde et utilitaires</li>
            </ul>
        </div>
        
        <div style="text-align: center; margin-top: 2rem;">
            <a href="index.php" class="btn">🏠 Retour à l'accueil</a>
            <a href="components.php" class="btn">📦 Voir les composants</a>
        </div>
    </div>
    
    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>