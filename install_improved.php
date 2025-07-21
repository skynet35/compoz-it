<?php
session_start();

$message = '';
$error = '';
$step = $_GET['step'] ?? '1';

// Vérifier si l'installation est déjà faite
if (file_exists('config.php') && $step === '1') {
    // Vérifier si la configuration est complète
    include_once 'config.php';
    if (defined('DB_PASSWORD') && defined('DB_NAME') && !empty(DB_PASSWORD) && !empty(DB_NAME)) {
        $step = 'complete';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === '1') {
        // Étape 1: Configuration de la base de données
        $db_host = $_POST['db_host'] ?? 'localhost';
        $db_name = $_POST['db_name'] ?? '';
        $db_user = $_POST['db_user'] ?? '';
        $db_pass = $_POST['db_pass'] ?? '';
        
        if (empty($db_name) || empty($db_user)) {
            $error = 'Le nom de la base de données et l\'utilisateur sont obligatoires.';
        } else {
            try {
                // Tester la connexion
                $pdo = new PDO("mysql:host=$db_host;charset=utf8", $db_user, $db_pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Créer la base de données si elle n'existe pas
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name`");
                $pdo->exec("USE `$db_name`");
                
                // Sauvegarder la configuration
                $_SESSION['db_config'] = [
                    'host' => $db_host,
                    'name' => $db_name,
                    'user' => $db_user,
                    'pass' => $db_pass
                ];
                
                header('Location: install_improved.php?step=2');
                exit;
                
            } catch (PDOException $e) {
                $error = 'Erreur de connexion à la base de données: ' . $e->getMessage();
            }
        }
    } elseif ($step === '2') {
        // Étape 2: Import du fichier SQL amélioré
        if (!isset($_SESSION['db_config'])) {
            header('Location: install_improved.php?step=1');
            exit;
        }
        
        $config = $_SESSION['db_config'];
        
        try {
            $pdo = new PDO("mysql:host={$config['host']};dbname={$config['name']};charset=utf8", 
                          $config['user'], $config['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Traitement du fichier SQL uploadé avec gestion améliorée
            if (isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
                $sql_content = file_get_contents($_FILES['sql_file']['tmp_name']);
                
                // Nettoyer le contenu SQL de manière plus complète
                $sql_content = str_replace(["\r\n", "\r"], "\n", $sql_content);
                
                // Supprimer les directives phpMyAdmin spécifiques
                $sql_content = preg_replace('/\/\*![0-9]+.*?\*\//s', '', $sql_content);
                $sql_content = preg_replace('/^SET .*?;$/m', '', $sql_content);
                $sql_content = preg_replace('/^START TRANSACTION;$/m', '', $sql_content);
                $sql_content = preg_replace('/^COMMIT;$/m', '', $sql_content);
                
                // Supprimer les commentaires SQL
                $sql_content = preg_replace('/^--.*$/m', '', $sql_content);
                $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
                
                // Nettoyer les lignes vides multiples
                $sql_content = preg_replace('/\n\s*\n/', "\n", $sql_content);
                
                // Diviser en requêtes de manière plus robuste
                $queries = [];
                $current_query = '';
                $in_string = false;
                $string_char = '';
                $in_comment = false;
                
                $lines = explode("\n", $sql_content);
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    
                    // Ignorer les lignes vides et les commentaires
                    if (empty($line) || substr($line, 0, 2) === '--' || substr($line, 0, 2) === '/*') {
                        continue;
                    }
                    
                    $current_query .= $line . ' ';
                    
                    // Vérifier si la ligne se termine par un point-virgule (fin de requête)
                    if (substr(rtrim($line), -1) === ';') {
                        $query = trim($current_query);
                        if (!empty($query)) {
                            $queries[] = $query;
                        }
                        $current_query = '';
                    }
                }
                
                // Ajouter la dernière requête si elle n'est pas vide
                if (!empty(trim($current_query))) {
                    $queries[] = trim($current_query);
                }
                
                // Exécuter les requêtes avec gestion d'erreurs détaillée
                $executed_queries = 0;
                $failed_queries = 0;
                $error_details = [];
                
                foreach ($queries as $index => $query) {
                    if (!empty(trim($query))) {
                        try {
                            $pdo->exec($query);
                            $executed_queries++;
                        } catch (PDOException $e) {
                            $failed_queries++;
                            $error_details[] = "Requête " . ($index + 1) . ": " . substr($query, 0, 50) . "... - " . $e->getMessage();
                            
                            // Continuer avec les autres requêtes au lieu de s'arrêter
                            continue;
                        }
                    }
                }
                
                if ($failed_queries > 0) {
                    $error = "Import partiellement réussi. $executed_queries requêtes exécutées, $failed_queries échouées.\n\nErreurs:\n" . implode("\n", array_slice($error_details, 0, 5));
                    if (count($error_details) > 5) {
                        $error .= "\n... et " . (count($error_details) - 5) . " autres erreurs.";
                    }
                } else {
                    $message = "Base de données importée avec succès! $executed_queries requêtes exécutées.";
                }
                
            } else {
                // Créer les tables de base si aucun fichier n'est fourni
                createBaseTables($pdo);
                $message = 'Tables de base créées avec succès!';
            }
            
            // Créer le fichier config.php seulement si pas d'erreur critique
            if (empty($error) || strpos($error, 'partiellement réussi') !== false) {
                createConfigFile($config);
                header('Location: install_improved.php?step=3');
                exit;
            }
            
        } catch (PDOException $e) {
            $error = 'Erreur lors de l\'import: ' . $e->getMessage();
        }
    }
}

function createBaseTables($pdo) {
    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS location (
            id INT AUTO_INCREMENT PRIMARY KEY,
            casier VARCHAR(50) NOT NULL,
            tiroir VARCHAR(50) NOT NULL,
            compartiment VARCHAR(50) NOT NULL,
            description TEXT,
            owner INT NOT NULL,
            UNIQUE KEY unique_location (casier, tiroir, compartiment, owner)
        )",
        
        "CREATE TABLE IF NOT EXISTS category_head (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            owner INT NOT NULL
        )",
        
        "CREATE TABLE IF NOT EXISTS category_sub (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            category_head_id INT NOT NULL,
            description TEXT,
            owner INT NOT NULL,
            UNIQUE KEY unique_sub_category (name, category_head_id, owner)
        )",
        
        "CREATE TABLE IF NOT EXISTS data (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            category VARCHAR(100),
            subcategory VARCHAR(100),
            quantity INT DEFAULT 0,
            location_id INT,
            owner INT NOT NULL,
            image_path VARCHAR(500),
            price DECIMAL(10,2),
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    ];
    
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
}

function createConfigFile($config) {
    $configContent = "<?php\n";
    $configContent .= "// Configuration de la base de données\n";
    $configContent .= "define('DB_HOST', '" . addslashes($config['host']) . "');\n";
    $configContent .= "define('DB_NAME', '" . addslashes($config['name']) . "');\n";
    $configContent .= "define('DB_USER', '" . addslashes($config['user']) . "');\n";
    $configContent .= "define('DB_PASSWORD', '" . addslashes($config['pass']) . "');\n\n";
    
    $configContent .= "function getConnection() {\n";
    $configContent .= "    try {\n";
    $configContent .= "        \$pdo = new PDO(\"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=utf8\", DB_USER, DB_PASSWORD);\n";
    $configContent .= "        \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n";
    $configContent .= "        return \$pdo;\n";
    $configContent .= "    } catch (PDOException \$e) {\n";
    $configContent .= "        die(\"Erreur de connexion à la base de données: \" . \$e->getMessage());\n";
    $configContent .= "    }\n";
    $configContent .= "}\n";
    
    file_put_contents('config.php', $configContent);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Améliorée - CompoZ'IT</title>
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
        
        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #333;
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .logo p {
            color: #666;
            font-size: 1.1em;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
            color: #666;
        }
        
        .step.active {
            background: #667eea;
            color: white;
        }
        
        .step.completed {
            background: #4CAF50;
            color: white;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        input[type="text"], input[type="password"], input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
            width: 100%;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
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
            white-space: pre-line;
        }
        
        .info-box {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .info-box h4 {
            color: #1976d2;
            margin-bottom: 10px;
        }
        
        .info-box p {
            color: #424242;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>CompoZ'IT</h1>
            <p>Installation Améliorée</p>
        </div>
        
        <?php if ($step === '1'): ?>
            <div class="step-indicator">
                <div class="step active">1</div>
                <div class="step">2</div>
                <div class="step">3</div>
            </div>
            
            <div class="info-box">
                <h4>📋 Étape 1: Configuration de la base de données</h4>
                <p>Configurez les paramètres de connexion à votre base de données MySQL.</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="db_host">Hôte de la base de données:</label>
                    <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="db_name">Nom de la base de données:</label>
                    <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="db_user">Utilisateur:</label>
                    <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? 'root') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="db_pass">Mot de passe:</label>
                    <input type="password" id="db_pass" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>">
                </div>
                
                <button type="submit" class="btn">Continuer</button>
            </form>
            
        <?php elseif ($step === '2'): ?>
            <div class="step-indicator">
                <div class="step completed">1</div>
                <div class="step active">2</div>
                <div class="step">3</div>
            </div>
            
            <div class="info-box">
                <h4>📋 Étape 2: Import de la base de données (Version Améliorée)</h4>
                <p>Importez votre fichier SQL. Cette version gère mieux les fichiers phpMyAdmin avec leurs directives spécifiques.</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="sql_file">Fichier SQL (optionnel):</label>
                    <input type="file" id="sql_file" name="sql_file" accept=".sql">
                    <small style="color: #666; display: block; margin-top: 5px;">
                        Laissez vide pour créer les tables de base uniquement.
                    </small>
                </div>
                
                <button type="submit" class="btn">Importer</button>
            </form>
            
        <?php else: ?>
            <div class="step-indicator">
                <div class="step completed">1</div>
                <div class="step completed">2</div>
                <div class="step completed">3</div>
            </div>
            
            <div class="info-box">
                <h4>✅ Installation terminée!</h4>
                <p>Votre application CompoZ'IT est maintenant prête à être utilisée.</p>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="index.php" class="btn" style="display: inline-block; text-decoration: none;">Accéder à l'application</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>