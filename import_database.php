<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=not_logged_in');
    exit();
}

$message = '';
$error = '';

// Traitement de l'upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sql_file'])) {
    try {
        $pdo = getConnection();
        $user_id = $_SESSION['user_id'];
        
        // Vérifier le fichier uploadé
        if ($_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Erreur lors de l\'upload du fichier.');
        }
        
        // Vérifier l'extension
        $file_info = pathinfo($_FILES['sql_file']['name']);
        if (strtolower($file_info['extension']) !== 'sql') {
            throw new Exception('Seuls les fichiers .sql sont acceptés.');
        }
        
        // Lire le contenu du fichier
        $sql_content = file_get_contents($_FILES['sql_file']['tmp_name']);
        if ($sql_content === false) {
            throw new Exception('Impossible de lire le fichier.');
        }
        
        // Commencer une transaction
        $pdo->beginTransaction();
        
        // Diviser le contenu en requêtes individuelles
        $queries = explode(';', $sql_content);
        $executed_queries = 0;
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query) && !preg_match('/^\s*--/', $query)) {
                // Remplacer l'owner par l'utilisateur actuel pour les INSERT
                if (preg_match('/^INSERT INTO/', $query)) {
                    // Cette regex simple remplace les valeurs d'owner, mais une approche plus robuste serait nécessaire en production
                    $query = preg_replace('/\bowner\s*=\s*\d+/', "owner = $user_id", $query);
                }
                
                $stmt = $pdo->prepare($query);
                $stmt->execute();
                $executed_queries++;
            }
        }
        
        // Valider la transaction
        $pdo->commit();
        
        $message = "Import réussi ! $executed_queries requêtes exécutées.";
        
    } catch(Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        $error = "Erreur lors de l'import : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Base de Données - Gestion des Composants</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .content {
            padding: 30px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: bold;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .warning-box h3 {
            margin-bottom: 10px;
            color: #856404;
        }

        .file-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📥 Import Base de Données</h1>
            <p>Restaurez vos données à partir d'un fichier SQL</p>
        </div>

        <div class="content">
            <?php if ($message): ?>
                <div class="alert alert-success">
                    ✅ <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="warning-box">
                <h3>⚠️ Attention</h3>
                <p>L'import d'un fichier SQL peut écraser vos données existantes. Assurez-vous d'avoir fait une sauvegarde avant de procéder.</p>
                <p>Seuls les fichiers .sql générés par la fonction d'export de ce système sont recommandés.</p>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="sql_file">Fichier SQL à importer :</label>
                    <input type="file" id="sql_file" name="sql_file" class="form-control" accept=".sql" required>
                </div>

                <div class="file-info">
                    <h4>📋 Informations sur le fichier :</h4>
                    <ul>
                        <li>Format accepté : .sql uniquement</li>
                        <li>Taille maximale : 50 MB</li>
                        <li>Le fichier doit contenir des requêtes SQL valides</li>
                        <li>Les données seront associées à votre compte utilisateur</li>
                    </ul>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary">📥 Importer</button>
                    <a href="settings.php" class="btn btn-secondary">🔙 Retour aux paramètres</a>
                </div>
            </form>
        </div>
    </div>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>