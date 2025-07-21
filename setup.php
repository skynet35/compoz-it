<?php
// Page de configuration initiale de CompoZ'IT

// Vérifier si le fichier de configuration existe déjà
$config_exists = file_exists('config.php');
$config_content = '';

if ($config_exists) {
    $config_content = file_get_contents('config.php');
    // Extraire les valeurs actuelles
    preg_match("/define\('DB_HOST', '([^']*)'\);", $config_content, $host_match);
    preg_match("/define\('DB_USER', '([^']*)'\);", $config_content, $user_match);
    preg_match("/define\('DB_PASSWORD', '([^']*)'\);", $config_content, $pass_match);
    preg_match("/define\('DB_NAME', '([^']*)'\);", $config_content, $name_match);
    
    $current_host = isset($host_match[1]) ? $host_match[1] : 'localhost';
    $current_user = isset($user_match[1]) ? $user_match[1] : 'root';
    $current_password = isset($pass_match[1]) ? $pass_match[1] : '';
    $current_dbname = isset($name_match[1]) ? $name_match[1] : 'CompozIT';
} else {
    $current_host = 'localhost';
    $current_user = 'root';
    $current_password = '';
    $current_dbname = 'CompozIT';
}

$message = '';
$error = '';

// Traitement du formulaire
if ($_POST) {
    $host = trim($_POST['host']);
    $user = trim($_POST['user']);
    $password = $_POST['password'];
    $dbname = trim($_POST['dbname']);
    
    if (empty($host) || empty($user) || empty($dbname)) {
        $error = 'Tous les champs sauf le mot de passe sont obligatoires.';
    } else {
        // Tester la connexion
        try {
            $test_pdo = new PDO("mysql:host=$host;charset=utf8", $user, $password);
            $test_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Créer la base de données si elle n'existe pas
            $test_pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
            
            // Générer le nouveau fichier config.php
            $new_config = "<?php\n";
            $new_config .= "// Configuration de la base de données\n";
            $new_config .= "define('DB_HOST', '$host');\n";
            $new_config .= "define('DB_USER', '$user');\n";
            $new_config .= "define('DB_PASSWORD', '$password');\n";
            $new_config .= "define('DB_NAME', '$dbname');\n\n";
            
            // Ajouter les fonctions existantes
            $new_config .= "// Fonction de connexion à la base de données\n";
            $new_config .= "function getDBConnection() {\n";
            $new_config .= "    try {\n";
            $new_config .= "        \$pdo = new PDO(\"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=utf8\", DB_USER, DB_PASSWORD);\n";
            $new_config .= "        \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n";
            $new_config .= "        return \$pdo;\n";
            $new_config .= "    } catch(PDOException \$e) {\n";
            $new_config .= "        die(\"Erreur de connexion : \" . \$e->getMessage());\n";
            $new_config .= "    }\n";
            $new_config .= "}\n\n";
            
            // Ajouter le reste du contenu existant si le fichier existe
            if ($config_exists && $config_content) {
                $lines = explode("\n", $config_content);
                $in_function = false;
                $function_started = false;
                
                foreach ($lines as $line) {
                    if (strpos($line, 'function getDBConnection()') !== false) {
                        $in_function = true;
                        $function_started = true;
                        continue;
                    }
                    if ($in_function && trim($line) === '}' && $function_started) {
                        $in_function = false;
                        $function_started = false;
                        continue;
                    }
                    if (!$in_function && !preg_match('/^define\(/', $line) && trim($line) !== '<?php' && !preg_match('/^\/\/ Configuration/', $line)) {
                        if (trim($line) !== '') {
                            $new_config .= $line . "\n";
                        }
                    }
                }
            } else {
                // Ajouter les fonctions par défaut si le fichier n'existe pas
                $new_config .= file_get_contents('config_template.php');
            }
            
            // Sauvegarder le fichier
            if (file_put_contents('config.php', $new_config)) {
                $message = 'Configuration sauvegardée avec succès ! La base de données "' . $dbname . '" a été créée.';
                $current_host = $host;
                $current_user = $user;
                $current_password = $password;
                $current_dbname = $dbname;
            } else {
                $error = 'Erreur lors de la sauvegarde du fichier de configuration.';
            }
            
        } catch (PDOException $e) {
            $error = 'Erreur de connexion à la base de données : ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration CompoZ'IT</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .setup-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 600px;
            width: 100%;
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
            font-weight: 700;
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1em;
        }

        .content {
            padding: 40px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
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

        .info-box {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .info-box h3 {
            color: #1976d2;
            margin-bottom: 10px;
        }

        .info-box p {
            color: #424242;
            line-height: 1.6;
        }

        .actions {
            margin-top: 30px;
            text-align: center;
        }

        .actions a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .actions a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="header">
            <h1>🔧 CompoZ'IT</h1>
            <p>Configuration de la base de données</p>
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
            
            <div class="info-box">
                <h3>📋 Instructions</h3>
                <p>Configurez les paramètres de connexion à votre base de données MySQL. 
                   Si la base de données n'existe pas, elle sera créée automatiquement.</p>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="host">🌐 Hôte de la base de données</label>
                    <input type="text" id="host" name="host" value="<?php echo htmlspecialchars($current_host); ?>" placeholder="localhost" required>
                </div>
                
                <div class="form-group">
                    <label for="user">👤 Nom d'utilisateur</label>
                    <input type="text" id="user" name="user" value="<?php echo htmlspecialchars($current_user); ?>" placeholder="root" required>
                </div>
                
                <div class="form-group">
                    <label for="password">🔒 Mot de passe</label>
                    <input type="password" id="password" name="password" value="<?php echo htmlspecialchars($current_password); ?>" placeholder="Laissez vide si aucun mot de passe">
                </div>
                
                <div class="form-group">
                    <label for="dbname">🗄️ Nom de la base de données</label>
                    <input type="text" id="dbname" name="dbname" value="<?php echo htmlspecialchars($current_dbname); ?>" placeholder="CompozIT" required>
                </div>
                
                <button type="submit" class="btn">
                    💾 Sauvegarder la configuration
                </button>
            </form>
            
            <?php if ($config_exists && $message): ?>
                <div class="actions">
                    <p><a href="index.php">➡️ Accéder à l'application</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>