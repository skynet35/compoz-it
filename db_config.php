<?php
session_start();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = $_POST['db_host'] ?? 'localhost';
    $db_name = $_POST['db_name'] ?? '';
    $db_user = $_POST['db_user'] ?? '';
    $db_pass = $_POST['db_pass'] ?? '';
    
    if (empty($db_name) || empty($db_user)) {
        $error = 'Le nom de la base de données et l\'utilisateur sont obligatoires.';
    } else {
        // Créer le contenu du fichier config.php
        $config_content = "<?php\n";
        $config_content .= "// Configuration de la base de données\n";
        $config_content .= "define('DB_HOST', '" . addslashes($db_host) . "');\n";
        $config_content .= "define('DB_NAME', '" . addslashes($db_name) . "');\n";
        $config_content .= "define('DB_USER', '" . addslashes($db_user) . "');\n";
        $config_content .= "define('DB_PASSWORD', '" . addslashes($db_pass) . "');\n\n";
        
        // Ajouter le reste du contenu de config.php
        $config_content .= "function initDatabase() {\n";
        $config_content .= "    try {\n";
        $config_content .= "        // Connexion sans spécifier la base de données\n";
        $config_content .= "        \$pdo = new PDO(\"mysql:host=\" . DB_HOST . \";charset=utf8\", DB_USER, DB_PASSWORD);\n";
        $config_content .= "        \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n";
        $config_content .= "        \n";
        $config_content .= "        // Créer la base de données si elle n'existe pas\n";
        $config_content .= "        \$pdo->exec(\"CREATE DATABASE IF NOT EXISTS \" . DB_NAME);\n";
        $config_content .= "        \$pdo->exec(\"USE \" . DB_NAME);\n\n";
        
        $config_content .= "    // Créer la table users\n";
        $config_content .= "    \$pdo->exec(\"CREATE TABLE IF NOT EXISTS users (\n";
        $config_content .= "        id INT AUTO_INCREMENT PRIMARY KEY,\n";
        $config_content .= "        email VARCHAR(255) UNIQUE NOT NULL,\n";
        $config_content .= "        password VARCHAR(255) NOT NULL,\n";
        $config_content .= "        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP\n";
        $config_content .= "    )\");\n\n";
        
        $config_content .= "    // Créer la table location\n";
        $config_content .= "    \$pdo->exec(\"CREATE TABLE IF NOT EXISTS location (\n";
        $config_content .= "        id INT AUTO_INCREMENT PRIMARY KEY,\n";
        $config_content .= "        casier VARCHAR(50) NOT NULL,\n";
        $config_content .= "        tiroir VARCHAR(50) NOT NULL,\n";
        $config_content .= "        compartiment VARCHAR(50) NOT NULL,\n";
        $config_content .= "        description TEXT,\n";
        $config_content .= "        owner INT NOT NULL,\n";
        $config_content .= "        FOREIGN KEY (owner) REFERENCES users(id) ON DELETE CASCADE,\n";
        $config_content .= "        UNIQUE KEY unique_location (casier, tiroir, compartiment, owner)\n";
        $config_content .= "    )\");\n\n";
        
        $config_content .= "    // Créer la table data\n";
        $config_content .= "    \$pdo->exec(\"CREATE TABLE IF NOT EXISTS data (\n";
        $config_content .= "        id INT AUTO_INCREMENT PRIMARY KEY,\n";
        $config_content .= "        name VARCHAR(255) NOT NULL,\n";
        $config_content .= "        description TEXT,\n";
        $config_content .= "        category VARCHAR(100),\n";
        $config_content .= "        subcategory VARCHAR(100),\n";
        $config_content .= "        quantity INT DEFAULT 0,\n";
        $config_content .= "        location_id INT,\n";
        $config_content .= "        owner INT NOT NULL,\n";
        $config_content .= "        image_path VARCHAR(500),\n";
        $config_content .= "        price DECIMAL(10,2),\n";
        $config_content .= "        supplier_id INT,\n";
        $config_content .= "        package_id INT,\n";
        $config_content .= "        manufacturer_id INT,\n";
        $config_content .= "        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n";
        $config_content .= "        FOREIGN KEY (location_id) REFERENCES location(id) ON DELETE SET NULL,\n";
        $config_content .= "        FOREIGN KEY (owner) REFERENCES users(id) ON DELETE CASCADE\n";
        $config_content .= "    )\");\n\n";
        
        // Ajouter toutes les autres tables
        $config_content .= "    // Créer la table category_head\n";
        $config_content .= "    \$pdo->exec(\"CREATE TABLE IF NOT EXISTS category_head (\n";
        $config_content .= "        id INT AUTO_INCREMENT PRIMARY KEY,\n";
        $config_content .= "        name VARCHAR(100) NOT NULL UNIQUE,\n";
        $config_content .= "        description TEXT,\n";
        $config_content .= "        owner INT NOT NULL,\n";
        $config_content .= "        FOREIGN KEY (owner) REFERENCES users(id) ON DELETE CASCADE\n";
        $config_content .= "    )\");\n\n";
        
        $config_content .= "    // Créer la table category_sub\n";
        $config_content .= "    \$pdo->exec(\"CREATE TABLE IF NOT EXISTS category_sub (\n";
        $config_content .= "        id INT AUTO_INCREMENT PRIMARY KEY,\n";
        $config_content .= "        name VARCHAR(100) NOT NULL,\n";
        $config_content .= "        category_head_id INT NOT NULL,\n";
        $config_content .= "        description TEXT,\n";
        $config_content .= "        owner INT NOT NULL,\n";
        $config_content .= "        FOREIGN KEY (category_head_id) REFERENCES category_head(id) ON DELETE CASCADE,\n";
        $config_content .= "        FOREIGN KEY (owner) REFERENCES users(id) ON DELETE CASCADE,\n";
        $config_content .= "        UNIQUE KEY unique_sub_category (name, category_head_id, owner)\n";
        $config_content .= "    )\");\n\n";
        
        $config_content .= "    // Créer la table suppliers\n";
        $config_content .= "    \$pdo->exec(\"CREATE TABLE IF NOT EXISTS suppliers (\n";
        $config_content .= "        id INT AUTO_INCREMENT PRIMARY KEY,\n";
        $config_content .= "        name VARCHAR(255) NOT NULL,\n";
        $config_content .= "        website VARCHAR(500),\n";
        $config_content .= "        notes TEXT,\n";
        $config_content .= "        logo_path VARCHAR(500),\n";
        $config_content .= "        owner INT NOT NULL,\n";
        $config_content .= "        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
        $config_content .= "        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n";
        $config_content .= "        FOREIGN KEY (owner) REFERENCES users(id) ON DELETE CASCADE\n";
        $config_content .= "    )\");\n\n";
        
        $config_content .= "    // Créer la table supplier_contacts\n";
        $config_content .= "    \$pdo->exec(\"CREATE TABLE IF NOT EXISTS supplier_contacts (\n";
        $config_content .= "        id INT AUTO_INCREMENT PRIMARY KEY,\n";
        $config_content .= "        supplier_id INT NOT NULL,\n";
        $config_content .= "        contact_type ENUM('email', 'phone', 'address', 'other') NOT NULL,\n";
        $config_content .= "        contact_value TEXT NOT NULL,\n";
        $config_content .= "        contact_label VARCHAR(100),\n";
        $config_content .= "        is_primary BOOLEAN DEFAULT FALSE,\n";
        $config_content .= "        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
        $config_content .= "        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE\n";
        $config_content .= "    )\");\n\n";
        
        $config_content .= "    // Créer la table projects\n";
        $config_content .= "    \$pdo->exec(\"CREATE TABLE IF NOT EXISTS projects (\n";
        $config_content .= "        id INT AUTO_INCREMENT PRIMARY KEY,\n";
        $config_content .= "        name VARCHAR(255) NOT NULL,\n";
        $config_content .= "        description TEXT,\n";
        $config_content .= "        status ENUM('planning', 'in_progress', 'completed', 'on_hold') DEFAULT 'planning',\n";
        $config_content .= "        start_date DATE,\n";
        $config_content .= "        end_date DATE,\n";
        $config_content .= "        owner INT NOT NULL,\n";
        $config_content .= "        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
        $config_content .= "        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n";
        $config_content .= "        FOREIGN KEY (owner) REFERENCES users(id) ON DELETE CASCADE\n";
        $config_content .= "    )\");\n\n";
        
        $config_content .= "    // Créer la table project_components\n";
        $config_content .= "    \$pdo->exec(\"CREATE TABLE IF NOT EXISTS project_components (\n";
        $config_content .= "        id INT AUTO_INCREMENT PRIMARY KEY,\n";
        $config_content .= "        project_id INT NOT NULL,\n";
        $config_content .= "        component_id INT NOT NULL,\n";
        $config_content .= "        quantity_needed INT NOT NULL DEFAULT 1,\n";
        $config_content .= "        quantity_used INT DEFAULT 0,\n";
        $config_content .= "        notes TEXT,\n";
        $config_content .= "        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
        $config_content .= "        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,\n";
        $config_content .= "        FOREIGN KEY (component_id) REFERENCES data(id) ON DELETE CASCADE,\n";
        $config_content .= "        UNIQUE KEY unique_project_component (project_id, component_id)\n";
        $config_content .= "    )\");\n\n";
        
        $config_content .= "    // Créer la table packages\n";
        $config_content .= "    \$pdo->exec(\"CREATE TABLE IF NOT EXISTS packages (\n";
        $config_content .= "        id INT AUTO_INCREMENT PRIMARY KEY,\n";
        $config_content .= "        name VARCHAR(100) NOT NULL UNIQUE,\n";
        $config_content .= "        description TEXT,\n";
        $config_content .= "        image_path VARCHAR(500),\n";
        $config_content .= "        owner INT NOT NULL,\n";
        $config_content .= "        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
        $config_content .= "        FOREIGN KEY (owner) REFERENCES users(id) ON DELETE CASCADE\n";
        $config_content .= "    )\");\n\n";
        
        $config_content .= "    // Créer la table manufacturers\n";
        $config_content .= "    \$pdo->exec(\"CREATE TABLE IF NOT EXISTS manufacturers (\n";
        $config_content .= "        id INT AUTO_INCREMENT PRIMARY KEY,\n";
        $config_content .= "        name VARCHAR(255) NOT NULL UNIQUE,\n";
        $config_content .= "        website VARCHAR(500),\n";
        $config_content .= "        logo_path VARCHAR(500),\n";
        $config_content .= "        owner INT NOT NULL,\n";
        $config_content .= "        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
        $config_content .= "        FOREIGN KEY (owner) REFERENCES users(id) ON DELETE CASCADE\n";
        $config_content .= "    )\");\n";
        $config_content .= "        \n";
        $config_content .= "        return true;\n";
        $config_content .= "    } catch(PDOException \$e) {\n";
        $config_content .= "        die(\"Erreur d'initialisation de la base de données : \" . \$e->getMessage());\n";
        $config_content .= "    }\n";
        $config_content .= "}\n\n";
        
        $config_content .= "// Fonction pour obtenir une connexion à la base de données\n";
        $config_content .= "function getConnection() {\n";
        $config_content .= "    try {\n";
        $config_content .= "        \$pdo = new PDO(\"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=utf8\", DB_USER, DB_PASSWORD);\n";
        $config_content .= "        \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\n";
        $config_content .= "        return \$pdo;\n";
        $config_content .= "    } catch(PDOException \$e) {\n";
        $config_content .= "        die(\"Erreur de connexion à la base de données : \" . \$e->getMessage());\n";
        $config_content .= "    }\n";
        $config_content .= "}\n\n";
        
        $config_content .= "// Initialiser la base de données\n";
        $config_content .= "initDatabase();\n";
        $config_content .= "?>";
        
        // Sauvegarder le fichier config.php
        if (file_put_contents('config.php', $config_content)) {
            $message = 'Configuration de la base de données sauvegardée avec succès!';
        } else {
            $error = 'Erreur lors de la sauvegarde de la configuration.';
        }
    }
}

// Lire la configuration actuelle si elle existe
$current_config = [
    'db_host' => 'localhost',
    'db_name' => '',
    'db_user' => '',
    'db_pass' => ''
];

if (file_exists('config.php')) {
    $config_content = file_get_contents('config.php');
    if (preg_match("/define\('DB_HOST', '([^']*)'\)/", $config_content, $matches)) {
        $current_config['db_host'] = $matches[1];
    }
    if (preg_match("/define\('DB_NAME', '([^']*)'\)/", $config_content, $matches)) {
        $current_config['db_name'] = $matches[1];
    }
    if (preg_match("/define\('DB_USER', '([^']*)'\)/", $config_content, $matches)) {
        $current_config['db_user'] = $matches[1];
    }
    if (preg_match("/define\('DB_PASSWORD', '([^']*)'/", $config_content, $matches)) {
        $current_config['db_pass'] = $matches[1];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration Base de Données</title>
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
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
        }
        
        .title {
            text-align: center;
            margin-bottom: 2rem;
            color: #333;
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s ease;
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
        
        .info-box h4 {
            color: #495057;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="title">⚙️ Configuration Base de Données</h1>
        
        <?php if (defined('DB_NAME')): ?>
            <div class="info-box">
                <h4>🗄️ Base de données actuelle</h4>
                <p><strong><?php echo htmlspecialchars(DB_NAME); ?></strong></p>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div class="info-box">
            <h4>📋 Instructions</h4>
            <p>Configurez ici les paramètres de connexion à votre base de données MySQL. Assurez-vous que la base de données existe et que l'utilisateur a les droits nécessaires.</p>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label for="db_host">🖥️ Serveur (Host)</label>
                <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($current_config['db_host']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_name">🗄️ Nom de la base de données</label>
                <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($current_config['db_name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_user">👤 Utilisateur</label>
                <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($current_config['db_user']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="db_pass">🔒 Mot de passe</label>
                <input type="password" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($current_config['db_pass']); ?>">
            </div>
            
            <button type="submit" class="btn">💾 Sauvegarder la configuration</button>
            <a href="index.php" class="btn btn-secondary" style="text-decoration: none; display: block; text-align: center;">↩️ Retour à la connexion</a>
        </form>
    </div>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>