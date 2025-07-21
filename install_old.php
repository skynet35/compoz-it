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
                
                header('Location: install.php?step=2');
                exit;
                
            } catch (PDOException $e) {
                $error = 'Erreur de connexion à la base de données: ' . $e->getMessage();
            }
        }
    } elseif ($step === '2') {
        // Étape 2: Import du fichier SQL
        if (!isset($_SESSION['db_config'])) {
            header('Location: install.php?step=1');
            exit;
        }
        
        $config = $_SESSION['db_config'];
        
        try {
            $pdo = new PDO("mysql:host={$config['host']};dbname={$config['name']};charset=utf8", 
                          $config['user'], $config['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Traitement du fichier SQL uploadé
            if (isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
                $sql_content = file_get_contents($_FILES['sql_file']['tmp_name']);
                
                // Nettoyer le contenu SQL
                $sql_content = str_replace(["\r\n", "\r"], "\n", $sql_content);
                
                // Diviser le contenu en requêtes individuelles de manière plus robuste
                $queries = [];
                $current_query = '';
                $in_string = false;
                $string_char = '';
                
                for ($i = 0; $i < strlen($sql_content); $i++) {
                    $char = $sql_content[$i];
                    
                    if (!$in_string && ($char === '"' || $char === "'")) {
                        $in_string = true;
                        $string_char = $char;
                    } elseif ($in_string && $char === $string_char && ($i === 0 || $sql_content[$i-1] !== '\\')) {
                        $in_string = false;
                    }
                    
                    $current_query .= $char;
                    
                    if (!$in_string && $char === ';') {
                        $query = trim($current_query);
                        if (!empty($query) && !preg_match('/^\s*--/', $query)) {
                            $queries[] = $query;
                        }
                        $current_query = '';
                    }
                }
                
                // Ajouter la dernière requête si elle n'est pas vide
                $query = trim($current_query);
                if (!empty($query) && !preg_match('/^\s*--/', $query)) {
                    $queries[] = $query;
                }
                
                foreach ($queries as $query) {
                    if (!empty(trim($query))) {
                        try {
                            $pdo->exec($query);
                        } catch (PDOException $e) {
                            throw new PDOException("Erreur dans la requête SQL: " . substr($query, 0, 100) . "... - " . $e->getMessage());
                        }
                    }
                }
                
                $message = 'Base de données importée avec succès!';
            } else {
                // Créer les tables de base si aucun fichier n'est fourni
                createBaseTables($pdo);
                $message = 'Tables de base créées avec succès!';
            }
            
            // Créer le fichier config.php
            createConfigFile($config);
            
            header('Location: install.php?step=3');
            exit;
            
        } catch (PDOException $e) {
            $error = 'Erreur lors de l\'import: ' . $e->getMessage();
        }
    } elseif ($step === '3') {
        // Étape 3: Création du compte administrateur ou connexion
        $action = $_POST['action'] ?? 'create';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = 'Email et mot de passe sont obligatoires.';
        } elseif ($action === 'create' && $password !== $confirm_password) {
            $error = 'Les mots de passe ne correspondent pas.';
        } else {
            try {
                require_once 'config.php';
                $pdo = getConnection();
                
                if ($action === 'create') {
                    // Créer un nouveau utilisateur
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
                    $stmt->execute([$email, $hashedPassword]);
                    
                    $message = 'Compte administrateur créé avec succès!';
                    
                    // Nettoyer la session
                    unset($_SESSION['db_config']);
                    
                    header('Location: install.php?step=complete');
                    exit;
                } else {
                    // Connexion avec un compte existant
                    $stmt = $pdo->prepare("SELECT id, password FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user && password_verify($password, $user['password'])) {
                        $message = 'Connexion réussie!';
                        
                        // Nettoyer la session
                        unset($_SESSION['db_config']);
                        
                        header('Location: install.php?step=complete');
                        exit;
                    } else {
                        $error = 'Email ou mot de passe incorrect.';
                    }
                }
                
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = 'Un compte avec cet email existe déjà.';
                } else {
                    $error = 'Erreur: ' . $e->getMessage();
                }
            }
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
            FOREIGN KEY (owner) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_location (casier, tiroir, compartiment, owner)
        )",
        
        "CREATE TABLE IF NOT EXISTS category_head (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            owner INT NOT NULL,
            FOREIGN KEY (owner) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS category_sub (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            category_head_id INT NOT NULL,
            description TEXT,
            owner INT NOT NULL,
            FOREIGN KEY (category_head_id) REFERENCES category_head(id) ON DELETE CASCADE,
            FOREIGN KEY (owner) REFERENCES users(id) ON DELETE CASCADE,
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
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (location_id) REFERENCES location(id) ON DELETE SET NULL,
            FOREIGN KEY (owner) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            status ENUM('planning', 'in_progress', 'completed', 'on_hold') DEFAULT 'planning',
            start_date DATE,
            end_date DATE,
            owner INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (owner) REFERENCES users(id) ON DELETE CASCADE
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
    <title>Installation - CompoZ'IT</title>
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
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 2rem;
            width: 100%;
            max-width: 600px;
        }
        
        .title {
            text-align: center;
            color: #333;
            margin-bottom: 2rem;
            font-size: 2rem;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
            color: #6c757d;
        }
        
        .step.active {
            background: #007bff;
            color: white;
        }
        
        .step.completed {
            background: #28a745;
            color: white;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        
        input[type="text"], input[type="password"], input[type="email"], input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        input:focus {
            outline: none;
            border-color: #007bff;
        }
        
        .btn {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s ease;
            width: 100%;
            margin-bottom: 1rem;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        }
        
        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .success {
            background: #efe;
            color: #363;
            border: 1px solid #cfc;
        }
        
        .info-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .complete-box {
            text-align: center;
            padding: 2rem;
        }
        
        .complete-box h2 {
            color: #28a745;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="title">🚀 Installation CompoZ'IT</h1>
        
        <div class="step-indicator">
            <div class="step <?php echo $step === '1' ? 'active' : ($step > '1' ? 'completed' : ''); ?>">1</div>
            <div class="step <?php echo $step === '2' ? 'active' : ($step > '2' ? 'completed' : ''); ?>">2</div>
            <div class="step <?php echo $step === '3' ? 'active' : ($step > '3' ? 'completed' : ''); ?>">3</div>
        </div>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($step === '1'): ?>
            <div class="info-box">
                <h4>📋 Étape 1: Configuration de la base de données</h4>
                <p>Configurez les paramètres de connexion à votre base de données MySQL.</p>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="db_host">🖥️ Serveur (Host)</label>
                    <input type="text" id="db_host" name="db_host" value="localhost" required>
                </div>
                
                <div class="form-group">
                    <label for="db_name">🗄️ Nom de la base de données</label>
                    <input type="text" id="db_name" name="db_name" placeholder="CompoZ_IT" required>
                </div>
                
                <div class="form-group">
                    <label for="db_user">👤 Utilisateur</label>
                    <input type="text" id="db_user" name="db_user" placeholder="root" required>
                </div>
                
                <div class="form-group">
                    <label for="db_pass">🔒 Mot de passe</label>
                    <input type="password" id="db_pass" name="db_pass">
                </div>
                
                <button type="submit" class="btn">➡️ Continuer</button>
            </form>
            
        <?php elseif ($step === '2'): ?>
            <div class="info-box">
                <h4>📋 Étape 2: Import de la base de données</h4>
                <p>Vous pouvez importer un fichier SQL existant ou laisser vide pour créer les tables de base.</p>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="sql_file">📁 Fichier SQL (optionnel)</label>
                    <input type="file" id="sql_file" name="sql_file" accept=".sql">
                    <small style="color: #6c757d;">Laissez vide pour créer les tables de base automatiquement.</small>
                </div>
                
                <button type="submit" class="btn">➡️ Continuer</button>
            </form>
            
        <?php elseif ($step === '3'): ?>
            <div class="info-box">
                <h4>📋 Étape 3: Compte administrateur</h4>
                <p>Créez un nouveau compte administrateur ou connectez-vous avec un compte existant.</p>
            </div>
            
            <div style="margin-bottom: 1.5rem; text-align: center;">
                <button type="button" class="btn" onclick="showCreateForm()" id="createBtn" style="margin-right: 10px;">➕ Créer un compte</button>
                <button type="button" class="btn btn-secondary" onclick="showLoginForm()" id="loginBtn">🔑 Se connecter</button>
            </div>
            
            <form method="POST" id="createForm">
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label for="email">📧 Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">🔒 Mot de passe</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">🔒 Confirmer le mot de passe</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn">✅ Créer le compte</button>
            </form>
            
            <form method="POST" id="loginForm" style="display: none;">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="login_email">📧 Email</label>
                    <input type="email" id="login_email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="login_password">🔒 Mot de passe</label>
                    <input type="password" id="login_password" name="password" required>
                </div>
                
                <button type="submit" class="btn">🔑 Se connecter</button>
            </form>
            
            <script>
                function showCreateForm() {
                    document.getElementById('createForm').style.display = 'block';
                    document.getElementById('loginForm').style.display = 'none';
                    document.getElementById('createBtn').className = 'btn';
                    document.getElementById('loginBtn').className = 'btn btn-secondary';
                }
                
                function showLoginForm() {
                    document.getElementById('createForm').style.display = 'none';
                    document.getElementById('loginForm').style.display = 'block';
                    document.getElementById('createBtn').className = 'btn btn-secondary';
                    document.getElementById('loginBtn').className = 'btn';
                }
            </script>
            
        <?php elseif ($step === 'complete'): ?>
            <div class="complete-box">
                <h2>🎉 Installation terminée!</h2>
                <p>Votre application CompoZ'IT est maintenant prête à être utilisée.</p>
                <a href="index.php" class="btn" style="text-decoration: none; display: block; margin-top: 1rem;">🚀 Accéder à l'application</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>