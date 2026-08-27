<?php
/* =========================================================================
   Configuration de la base de données
   ---------------------------------------------------------------------------
   NE PAS MODIFIER DIRECTEMENT CE FICHIER SUR VOTRE SERVEUR DE PRODUCTION !
   ---------------------------------------------------------------------------
   Pour personnaliser DB_HOST / DB_NAME / DB_USER / DB_PASSWORD :
   1. Copiez ce fichier     :    config.local.template.php  → config.local.php
   2. Editez config.local.php avec vos credentials
   3. Par défaut : config.local.php est DANS .gitignore (jamais commitésur GitHub)
   ========================================================================= */

/* --- Surcharge FACULTATIVE via fichier local (toujours chargé EN PREMIER, avant define() -- pour eviter les "Cannot redeclare") --- */
$_compozitLocalConfig = __DIR__ . DIRECTORY_SEPARATOR . 'config.local.php';
if (file_exists($_compozitLocalConfig)) {
    require_once $_compozitLocalConfig;
}
unset($_compozitLocalConfig);

/* --- Valeurs par défaut (fallback si config.local.php absent ou incomplet) --- */
if (!defined('DB_HOST'))     define('DB_HOST', 'localhost');
if (!defined('DB_NAME'))     define('DB_NAME', 'compozit');
if (!defined('DB_USER'))     define('DB_USER', 'root');
if (!defined('DB_PASSWORD')) define('DB_PASSWORD', '');

/* --- Vérification (empêche accès direct navigateur à config.php) --- */
if (!defined('COMPOZIT_BOOTSTRAP') && php_sapi_name() !== 'cli') {
    if (count(get_included_files()) === 1) {
        http_response_code(403);
        die('Forbidden');
    }
}

function ensureLocationStorageTypeColumn($pdo) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM location LIKE 'storage_type'");
        $column = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

        if (!$column) {
            $pdo->exec("ALTER TABLE location ADD COLUMN storage_type VARCHAR(30) NOT NULL DEFAULT 'casier' AFTER description");
        }

        $pdo->exec("UPDATE location SET storage_type = 'casier' WHERE storage_type IS NULL OR storage_type = ''");
    } catch (PDOException $e) {
    }
}

function ensureLocationLogoPathColumn($pdo) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM location LIKE 'logo_path'");
        $column = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

        if (!$column) {
            $pdo->exec("ALTER TABLE location ADD COLUMN logo_path VARCHAR(500) DEFAULT NULL AFTER storage_type");
        }
    } catch (PDOException $e) {
    }
}

function getLocationStorageTypes() {
    return [
        'casier' => ['label' => 'Casier', 'icon' => '🗄️'],
        'boite' => ['label' => 'Boite', 'icon' => '📦'],
        'classeur' => ['label' => 'Classeur', 'icon' => '🗂️'],
        'etagere' => ['label' => 'Etagere', 'icon' => '🪜'],
    ];
}

function initDatabase() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8", DB_USER, DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $safeDbName = str_replace('`', '', DB_NAME);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeDbName}`");
        $pdo->exec("USE `{$safeDbName}`");

    // Créer la table users
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Créer la table location
    $pdo->exec("CREATE TABLE IF NOT EXISTS location (
        id INT AUTO_INCREMENT PRIMARY KEY,
        casier VARCHAR(50) NOT NULL,
        tiroir VARCHAR(50) NOT NULL,
        compartiment VARCHAR(50) NOT NULL,
        description TEXT,
        owner INT NOT NULL,
        FOREIGN KEY (owner) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_location (casier, tiroir, compartiment, owner)
    )");

    // Créer la table data
    $pdo->exec("CREATE TABLE IF NOT EXISTS data (
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
        supplier_id INT,
        package_id INT,
        manufacturer_id INT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (location_id) REFERENCES location(id) ON DELETE SET NULL,
        FOREIGN KEY (owner) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Créer la table category_head
    $pdo->exec("CREATE TABLE IF NOT EXISTS category_head (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        owner INT NOT NULL,
        FOREIGN KEY (owner) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Créer la table category_sub
    $pdo->exec("CREATE TABLE IF NOT EXISTS category_sub (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        category_head_id INT NOT NULL,
        description TEXT,
        owner INT NOT NULL,
        FOREIGN KEY (category_head_id) REFERENCES category_head(id) ON DELETE CASCADE,
        FOREIGN KEY (owner) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_sub_category (name, category_head_id, owner)
    )");

    // Créer la table suppliers
    $pdo->exec("CREATE TABLE IF NOT EXISTS suppliers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        website VARCHAR(500),
        notes TEXT,
        logo_path VARCHAR(500),
        owner INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (owner) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Créer la table supplier_contacts
    $pdo->exec("CREATE TABLE IF NOT EXISTS supplier_contacts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT NOT NULL,
        contact_type ENUM('email', 'phone', 'address', 'other') NOT NULL,
        contact_value TEXT NOT NULL,
        contact_label VARCHAR(100),
        is_primary BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
    )");

    // Créer la table projects
    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
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
    )");

    // Créer la table project_components
    $pdo->exec("CREATE TABLE IF NOT EXISTS project_components (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        component_id INT NOT NULL,
        quantity_needed INT NOT NULL DEFAULT 1,
        quantity_used INT DEFAULT 0,
        notes TEXT,
        added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
        FOREIGN KEY (component_id) REFERENCES data(id) ON DELETE CASCADE,
        UNIQUE KEY unique_project_component (project_id, component_id)
    )");

    // Créer la table packages
    $pdo->exec("CREATE TABLE IF NOT EXISTS packages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        image_path VARCHAR(500),
        owner INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (owner) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Créer la table manufacturers
    $pdo->exec("CREATE TABLE IF NOT EXISTS manufacturers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE,
        website VARCHAR(500),
        logo_path VARCHAR(500),
        owner INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (owner) REFERENCES users(id) ON DELETE CASCADE
    )");
        
        return true;
    } catch(PDOException $e) {
        die("Erreur d'initialisation de la base de données : " . $e->getMessage());
    }
}

// Fonction pour obtenir une connexion à la base de données
function getConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        ensureLocationStorageTypeColumn($pdo);
        ensureLocationLogoPathColumn($pdo);
        return $pdo;
    } catch(PDOException $e) {
        die("Erreur de connexion à la base de données : " . $e->getMessage());
    }
}

// Initialiser la base de données
initDatabase();
?>