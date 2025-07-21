// Créer la base de données et les tables si elles n'existent pas
function initDatabase() {
    try {
        // Connexion sans spécifier la base de données
        $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8", DB_USER, DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Créer la base de données si elle n'existe pas
        $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
        $pdo->exec("USE " . DB_NAME);
        
        // Créer la table users
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $pdo->exec($sql);
        
        // Créer la table category_head
        $sql = "CREATE TABLE IF NOT EXISTS category_head (
            id INT PRIMARY KEY,
            name VARCHAR(100) NOT NULL
        )";
        $pdo->exec($sql);
        
        // Créer la table category_sub
        $sql = "CREATE TABLE IF NOT EXISTS category_sub (
            id INT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            category_head_id INT,
            FOREIGN KEY (category_head_id) REFERENCES category_head(id)
        )";
        $pdo->exec($sql);
        
        // Créer la table location
        $sql = "CREATE TABLE IF NOT EXISTS location (
            id INT AUTO_INCREMENT PRIMARY KEY,
            owner INT NOT NULL,
            casier VARCHAR(100) NOT NULL,
            tiroir VARCHAR(100) NOT NULL,
            compartiment VARCHAR(100) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (owner) REFERENCES users(id),
            UNIQUE KEY unique_location (owner, casier, tiroir, compartiment)
        )";
        $pdo->exec($sql);
        
        // Créer la table suppliers
        $sql = "CREATE TABLE IF NOT EXISTS suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            website VARCHAR(255),
            contact_email VARCHAR(255),
            phone VARCHAR(50),
            address TEXT,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $pdo->exec($sql);
        
        // Créer la table data (composants)
        $sql = "CREATE TABLE IF NOT EXISTS data (
            id INT AUTO_INCREMENT PRIMARY KEY,
            owner INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            manufacturer VARCHAR(255),
            package VARCHAR(100),
            pins INT,
            smd ENUM('Yes', 'No') DEFAULT 'No',
            quantity INT DEFAULT 0,
            order_quantity INT DEFAULT 0,
            price DECIMAL(10,2) DEFAULT NULL,
            location_id INT,
            datasheet TEXT,
            comment TEXT,
            category INT,
            public ENUM('Yes', 'No') DEFAULT 'No',
            url TEXT,
            image_path VARCHAR(255),
            supplier_id INT,
            supplier_reference VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (owner) REFERENCES users(id),
            FOREIGN KEY (category) REFERENCES category_sub(id),
            FOREIGN KEY (location_id) REFERENCES location(id),
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
        )";
        $pdo->exec($sql);
        
        // Insérer les catégories principales si elles n'existent pas
        insertCategoryHead($pdo);
        insertCategorySub($pdo);
        
        return true;
    } catch(PDOException $e) {
        die("Erreur d'initialisation de la base de données : " . $e->getMessage());
    }
}

// Insérer les catégories principales
function insertCategoryHead($pdo) {
    $categories = [
        [1, 'Cables'],
        [2, 'Condensateurs'],
        [3, 'Connecteurs'],
        [4, 'Diode'],
        [5, 'CI'],
        [6, 'Inductances'],
        [7, 'Mechanique'],
        [8, 'Opto'],
        [9, 'Protections'],
        [10, 'Interrupteur/poussoirs'],
        [11, 'Régulateurs/Transfo'],
        [12, 'Transistors'],
        [13, 'Resistances'],
        [14, 'Ecrans'],
        [15, 'Capteurs'],
        [16, 'Modules'],
        [17, 'Autres'],
        [18, 'Oscillateurs']
    ];
    
    foreach ($categories as $cat) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO category_head (id, name) VALUES (?, ?)");
        $stmt->execute($cat);
    }
}

// Insérer les sous-catégories
function insertCategorySub($pdo) {
    $subcategories = [
        // Cables (1xx)
        [101, 'Ruban', 1], [102, 'Coaxial', 1], [103, 'Rouleau', 1], [104, 'Cable PC', 1],
        [105, 'Signal/Data', 1], [106, 'Fibre optic', 1], [107, 'Dupont', 1], [199, 'Divers', 1],
        
        // Condensateurs (2xx)
        [201, 'Ceramique', 2], [202, 'Electrolytique', 2], [203, 'Polyester', 2], [204, 'Tantal', 2],
        [205, 'Variable', 2], [299, 'Divers', 2],
        
        // Connecteurs (3xx)
        [301, 'Audio', 3], [302, 'Coaxial', 3], [303, 'DC-alim', 3], [304, 'D-Sub', 3],
        [305, 'HF', 3], [306, 'PCB', 3], [307, 'Cable PC', 3], [308, 'Data', 3], [399, 'Divers', 3],
        
        // Diodes (4xx)
        [401, 'Redresseur', 4], [402, 'Schottky', 4], [403, 'Petits signaux', 4], [404, 'Zener', 4],
        [406, 'Bridge', 4], [499, 'Divers', 4],
        
        // CI (5xx)
        [501, '4xxx', 5], [502, '74xx', 5], [503, 'Microcontroller', 5], [504, 'Comparateur', 5],
        [505, 'AOP', 5], [506, 'Temperature', 5], [507, 'Timer & Osc.', 5], [508, 'Référence de tension', 5],
        [509, 'Régulateur de tension', 5], [510, 'Convertisseur Data', 5], [511, 'A/D Multiplexeur', 5],
        [512, 'Driver', 5], [513, 'Opto Driver', 5], [514, 'Convertisseur DC/DC', 5], [515, 'Audio/Video', 5],
        [516, 'Memoires', 5], [517, 'Logic', 5], [599, 'Divers', 5],
        
        // Inductances (6xx)
        [601, 'Ferrite', 6], [602, 'Filtre', 6], [603, 'Inducteur', 6], [699, 'Divers', 6],
        
        // Mechanique (7xx)
        [701, 'Box', 7], [702, 'Distance', 7], [703, 'Supports Fusibles', 7], [704, 'Moteurs', 7],
        [705, 'Relais', 7], [708, 'IC Socket', 7], [709, 'Radiateur', 7], [710, 'Bouton potar', 7],
        [711, 'Metre', 7], [799, 'Divers', 7],
        
        // Opto (8xx)
        [801, 'Barrières photo-electrique', 8], [802, 'Laser', 8], [803, 'LED', 8], [804, 'LED 3mm', 8],
        [805, 'LED 5mm', 8], [806, 'Optocoupleur', 8], [807, 'IR LED', 8], [808, 'Ampoules', 8], [899, 'Divers', 8],
        
        // Protections (9xx)
        [901, 'Fusibles', 9], [902, 'Varistances', 9], [903, 'Thermistances CTN', 9], [904, 'Thermistances CTP', 9],
        [905, 'Support Fusibles', 9], [999, 'Divers', 9],
        
        // Interrupteur/poussoirs (10xx)
        [1001, 'Clavier', 10], [1002, 'Momentanné', 10], [1003, 'Monté sur PCB', 10], [1004, 'Encodeur rotatif', 10],
        [1005, 'Maintient', 10], [1007, 'DIP', 10], [1099, 'Divers', 10],
        
        // Régulateurs/Transfo (11xx)
        [1101, 'Alimentation', 11], [1102, 'Transformateur', 11], [1103, 'Convertisseur DC/DC', 11], [1199, 'Divers', 11],
        
        // Transistors (12xx)
        [1201, 'JBT', 12], [1202, 'JFET', 12], [1204, 'NPN', 12], [1205, 'PNP', 12],
        [1206, 'Triac', 12], [1207, 'Thyristor', 12], [1208, 'MOSFET-N', 12], [1209, 'MOSFET-P', 12], [1299, 'Divers', 12],
        
        // Resistances (13xx)
        [1301, '1/4W Carbon', 13], [1302, '1/4W Metal', 13], [1303, '1/6W Carbon', 13], [1304, '1/6W Metal', 13],
        [1305, 'CMS-0603', 13], [1306, 'CMS-0805', 13], [1307, 'CMS-1206', 13], [1308, 'Effect', 13],
        [1309, 'Photo', 13], [1310, 'Réseaux', 13], [1311, 'Temperature', 13], [1312, 'Potentiometre', 13],
        [1313, '1/3W Carbon', 13], [1314, '1/3W Metal', 13], [1315, 'Precision', 13], [1399, 'Divers', 13],
        
        // Ecrans (14xx)
        [1401, 'LCD', 14], [1402, 'VFD', 14], [1403, 'TFT', 14], [1404, 'LED', 14], [1499, 'Divers', 14],
        
        // Capteurs (15xx)
        [1501, 'Humidité', 15], [1502, 'Temperature', 15], [1503, 'Pression', 15], [1504, 'Magnetique', 15],
        [1505, 'Mouvement', 15], [1506, 'Lumière', 15], [1507, 'Son', 15], [1508, 'Gaz', 15], [1599, 'Divers', 15],
        
        // Modules (16xx)
        [1601, 'Arduino', 16], [1602, 'Raspberry Pi', 16], [1603, 'ESP32/ESP8266', 16], [1604, 'Bluetooth', 16],
        [1605, 'WiFi', 16], [1606, 'GPS', 16], [1607, 'GSM/GPRS', 16], [1608, 'LoRa', 16], [1699, 'Divers', 16],
        
        // Autres (17xx)
        [1701, 'Outils', 17], [1702, 'Accessoires', 17], [1703, 'Kits', 17], [1799, 'Divers', 17],
        
        // Oscillateurs (18xx)
        [1801, 'Quartz', 18], [1802, 'Céramique', 18], [1803, 'RC', 18], [1899, 'Divers', 18]
    ];
    
    foreach ($subcategories as $subcat) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO category_sub (id, name, category_head_id) VALUES (?, ?, ?)");
        $stmt->execute($subcat);
    }
}
?>