<?php
require_once 'session_init.php';
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=not_logged_in');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: components.php?error=invalid_id');
    exit();
}

$component_id = (int)$_GET['id'];

try {
    $pdo = getConnection();
    
    $stmt = $pdo->prepare("SELECT * FROM data WHERE id = ? AND owner = ?");
    $stmt->execute([$component_id, $_SESSION['user_id']]);
    $component = $stmt->fetch();
    
    if (!$component) {
        header('Location: components.php?error=component_not_found');
        exit();
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $manufacturer = trim($_POST['manufacturer'] ?? '');
        $package = trim($_POST['package'] ?? '');
        $pins = !empty($_POST['pins']) ? (int)$_POST['pins'] : null;
        $smd = $_POST['smd'] ?? 'No';
        $quantity = (int)($_POST['quantity'] ?? 1);
        $order_quantity = (int)($_POST['order_quantity'] ?? 0);
        $price = !empty($_POST['price']) ? (float)$_POST['price'] : null;
        $location_id = !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null;
        $datasheet = trim($_POST['datasheet'] ?? '');
        $comment = trim($_POST['comment'] ?? '');
        $category = !empty($_POST['category']) ? (int)$_POST['category'] : null;
        $public = $_POST['public'] ?? 'No';
        $url = trim($_POST['url'] ?? '');
        $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        $supplier_reference = trim($_POST['supplier_reference'] ?? '');
        
        $image_path = $component['image_path'];
        $image_type = $_POST['image_type'] ?? '';
        
        if ($image_type === 'existing' && !empty($_POST['existing_image'])) {
            $image_path = trim($_POST['existing_image']);
        } elseif ($image_type === 'upload' && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'img/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = 'component_' . $component_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    if ($image_path && file_exists($image_path)) {
                        unlink($image_path);
                    }
                    $image_path = $upload_path;
                }
            }
        } elseif ($image_type === 'url' && !empty($_POST['image_url'])) {
            $image_path = trim($_POST['image_url']);
        } elseif ($image_type === 'none') {
            if ($image_path && file_exists($image_path) && strpos($image_path, 'uploads/') === 0) {
                unlink($image_path);
            }
            $image_path = null;
        }
        
        if (empty($name)) {
            $error = "Le nom du composant est obligatoire.";
        } elseif ($quantity < 0) {
            $error = "La quantité ne peut pas être négative.";
        } else {
            $sql = "UPDATE data SET 
                name = ?, manufacturer = ?, package = ?, pins = ?, smd = ?, 
                quantity = ?, order_quantity = ?, price = ?, location_id = ?, datasheet = ?, 
                comment = ?, category = ?, public = ?, url = ?, image_path = ?, 
                supplier_id = ?, supplier_reference = ?
                WHERE id = ? AND owner = ?";
            
            try {
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    $name,
                    $manufacturer ?: null,
                    $package ?: null,
                    $pins,
                    $smd,
                    $quantity,
                    $order_quantity,
                    $price,
                    $location_id,
                    $datasheet ?: null,
                    $comment ?: null,
                    $category,
                    $public,
                    $url ?: null,
                    $image_path,
                    $supplier_id,
                    $supplier_reference ?: null,
                    $component_id,
                    $_SESSION['user_id']
                ]);
                
                if ($result) {
                    header('Location: components.php?success=component_updated');
                    exit();
                } else {
                    $error = "Erreur lors de la mise à jour du composant.";
                }
            } catch(PDOException $e) {
                error_log("Erreur PDO lors de la mise à jour : " . $e->getMessage());
                $error = "❌ Erreur de base de données : " . $e->getMessage();
            }
        }
    }
    
    $stmt = $pdo->query("SELECT * FROM category_head ORDER BY name");
    $categories = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT cs.*, ch.name as parent_name, cs.category_head_id as parent_id FROM category_sub cs LEFT JOIN category_head ch ON cs.category_head_id = ch.id ORDER BY cs.category_head_id, cs.name");
    $subcategories = $stmt->fetchAll();
    
    $component_category_head = null;
    if ($component['category']) {
        foreach ($subcategories as $subcat) {
            if ($subcat['id'] == $component['category']) {
                $component_category_head = $subcat['parent_id'];
                break;
            }
        }
    }
    
$stmt = $pdo->prepare("SELECT l.*, COUNT(d.id) as component_count 
                      FROM location l 
                      LEFT JOIN data d ON l.id = d.location_id AND d.owner = ?
                      WHERE l.owner = ? 
                      GROUP BY l.id 
                      ORDER BY 
                        CASE WHEN COUNT(d.id) = 0 THEN 0 ELSE 1 END,
                        l.casier, l.tiroir, l.compartiment");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$locations = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT * FROM suppliers ORDER BY name");
    $suppliers = $stmt->fetchAll();

    // Récupérer les fabricants de l'utilisateur
    $stmt = $pdo->prepare("SELECT name FROM manufacturers WHERE owner = ? ORDER BY name");
    $stmt->execute([$_SESSION['user_id']]);
    $manufacturers = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Récupérer les packages existants
    $stmt = $pdo->query("SELECT name FROM packages ORDER BY name");
    $packages_list = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch(PDOException $e) {
    error_log("Erreur lors de la modification du composant : " . $e->getMessage());
    header('Location: components.php?error=database_error');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un Composant</title>
    <style>
        :root {
            --bg-primary: #f8fafc;
            --bg-card: #ffffff;
            --bg-muted: #f1f5f9;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --border-color: #e2e8f0;
            --border-light: #f1f5f9;
            --accent-indigo: #6366f1;
            --accent-indigo-light: #e0e7ff;
            --accent-purple: #8b5cf6;
            --accent-pink: #ec4899;
            --accent-blue: #3b82f6;
            --accent-green: #10b981;
            --accent-amber: #f59e0b;
            --accent-red: #ef4444;
            --accent-teal: #14b8a6;
            --accent-orange: #f97316;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.08), 0 2px 4px -2px rgba(0,0,0,0.05);
            --shadow-lg: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.05);
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --radius-xl: 20px;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            min-height: 100vh;
            color: var(--text-primary);
        }
        .container {
            max-width: 1480px;
            margin: 0 auto;
            padding: 20px;
        }
        .app-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: var(--radius-xl);
            padding: 28px 32px 32px;
            margin-bottom: 24px;
            box-shadow: 0 20px 40px rgba(102,126,234,0.2);
            position: relative;
            overflow: hidden;
        }
        .app-header::before {
            content: '';
            position: absolute;
            top: -80px;
            right: -80px;
            width: 260px;
            height: 260px;
            background: radial-gradient(circle, rgba(255,255,255,0.15), transparent 70%);
            border-radius: 50%;
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
            z-index: 2;
            margin-bottom: 20px;
        }
        .header-title {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .header-icon {
            width: 52px;
            height: 52px;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            border: 1px solid rgba(255,255,255,0.25);
        }
        .header-title h1 {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .header-title p {
            font-size: 13px;
            opacity: 0.85;
            margin-top: 3px;
        }
        .user-chip {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            padding: 8px 16px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.2);
            font-size: 13px;
        }
        .logout-link {
            color: white;
            text-decoration: none;
            background: rgba(255,255,255,0.2);
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 500;
            transition: background 0.2s;
        }
        .logout-link:hover { background: rgba(255,255,255,0.3); }
        .nav-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 6px;
            position: relative;
            z-index: 2;
        }
        .nav-buttons a {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            color: white;
            padding: 10px 18px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
            border: 1px solid rgba(255,255,255,0.2);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .nav-buttons a:hover {
            background: rgba(255,255,255,0.28);
            transform: translateY(-1px);
        }
        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.18s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            white-space: nowrap;
        }
        .btn:hover { transform: translateY(-1px); box-shadow: var(--shadow-md); }
        .btn:active { transform: translateY(0); }
        .btn-indigo  { background: var(--accent-indigo); color: white; }
        .btn-purple  { background: var(--accent-purple); color: white; }
        .btn-danger  { background: var(--accent-red); color: white; }
        .btn-ghost   { background: var(--bg-muted); color: var(--text-secondary); }
        .btn-ghost:hover { background: #e2e8f0; }
        .btn-sm      { padding: 6px 11px; font-size: 12px; border-radius: 6px; gap: 4px; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; box-shadow: none !important; }
        .btn-primary { background: var(--accent-green); color: white; }
        .btn-secondary { background: var(--accent-blue); color: white; }
        .btn-success { background: var(--accent-green); color: white; }

        .content-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 30px;
            box-shadow: var(--shadow-md);
        }
        .back-link {
            margin-bottom: 20px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-group label {
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 13px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.15s, box-shadow 0.15s;
            background: var(--bg-card);
            color: var(--text-primary);
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--accent-indigo);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        .error {
            background: #fef2f2;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            border: 1px solid #fecaca;
            font-size: 14px;
        }
        .location-available {
            background-color: #dcfce7 !important;
            color: #166534 !important;
        }
        .location-occupied {
            background-color: #fef2f2 !important;
            color: #991b1b !important;
        }
        .autocomplete-wrapper {
            position: relative;
        }
        .autocomplete-suggestions {
            position: absolute;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            width: 100%;
            box-shadow: var(--shadow-md);
            border-radius: 0 0 var(--radius-sm) var(--radius-sm);
        }
        .suggestion-item {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid var(--border-light);
            font-size: 14px;
        }
        .suggestion-item:hover {
            background-color: var(--bg-muted);
        }
        .suggestion-item:last-child {
            border-bottom: none;
        }
        .existing-images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .existing-image-item {
            border: 2px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 5px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        .existing-image-item:hover {
            border-color: var(--accent-indigo);
        }
        .existing-image-item.selected {
            border-color: var(--accent-green);
            background-color: var(--bg-muted);
        }
        .existing-image-item img {
            width: 100%;
            height: 80px;
            object-fit: cover;
            border-radius: 3px;
        }
        .existing-image-item .image-name {
            font-size: 12px;
            margin-top: 5px;
            word-break: break-word;
            color: var(--text-secondary);
        }
        .form-actions {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        footer {
            margin-top: 2rem;
            padding: 1rem;
            text-align: center;
            border-top: 1px solid var(--border-color);
            background-color: var(--bg-card);
            color: var(--text-secondary);
            font-size: 0.9em;
            border-radius: var(--radius-lg);
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="app-header">
            <div class="header-top">
                <div class="header-title">
                    <div class="header-icon">✏️</div>
                    <div>
                        <h1>Modifier un Composant</h1>
                        <p>Mettez à jour les informations du composant</p>
                    </div>
                </div>
                <div class="user-chip">
                    <span>👤 <?php echo htmlspecialchars($_SESSION['user_email'] ?? 'Utilisateur'); ?></span>
                    <a href="logout.php" class="logout-link">🚪 Déconnexion</a>
                </div>
            </div>
            <nav class="nav-buttons">
                <a href="components.php">← Retour</a>
                <a href="components.php">📦 Composants</a>
                <a href="add_component.php">➕ Créer</a>
                <a href="projects.php">🚀 Projets</a>
                <a href="settings.php">⚙️ Paramètres</a>
            </nav>
        </header>

        <div class="content-card">
            <div class="back-link">
                <a href="components.php" class="btn btn-ghost">← Retour à la liste</a>
            </div>

            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">Nom du composant *</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($component['name']); ?>" required>
                    </div>
                    <div class="form-group autocomplete-wrapper">
                        <label for="manufacturer">Fabricant</label>
                        <input type="text" id="manufacturer" name="manufacturer" value="<?php echo htmlspecialchars($component['manufacturer'] ?? ''); ?>" autocomplete="off">
                        <div id="manufacturer-suggestions" class="autocomplete-suggestions"></div>
                    </div>
                    <div class="form-group autocomplete-wrapper">
                        <label for="package">Package</label>
                        <input type="text" id="package" name="package" value="<?php echo htmlspecialchars($component['package'] ?? ''); ?>" autocomplete="off">
                        <div id="package-suggestions" class="autocomplete-suggestions"></div>
                    </div>
                    <div class="form-group">
                        <label for="pins">Nombre de pins</label>
                        <input type="number" id="pins" name="pins" value="<?php echo $component['pins'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="smd">SMD</label>
                        <select id="smd" name="smd">
                            <option value="No" <?php echo $component['smd'] === 'No' ? 'selected' : ''; ?>>Non</option>
                            <option value="Yes" <?php echo $component['smd'] === 'Yes' ? 'selected' : ''; ?>>Oui</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="quantity">Quantité *</label>
                        <input type="number" id="quantity" name="quantity" value="<?php echo $component['quantity']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="order_quantity">Quantité à commander</label>
                        <input type="number" id="order_quantity" name="order_quantity" value="<?php echo $component['order_quantity'] ?? 0; ?>">
                    </div>
                    <div class="form-group">
                        <label for="price">Prix (€)</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo $component['price'] ?? ''; ?>" placeholder="Ex: 2.50">
                    </div>
                    <div class="form-group">
                        <label for="location_id">Emplacement</label>
                        <select id="location_id" name="location_id">
                            <option value="">Sélectionner un emplacement</option>
                            <?php foreach ($locations as $location): ?>
                                <?php 
                                $isOccupied = $location['component_count'] > 0;
                                $statusClass = $isOccupied ? 'location-occupied' : 'location-available';
                                $statusText = $isOccupied ? ' (Occupé)' : ' (Disponible)';
                                ?>
                                <option value="<?php echo $location['id']; ?>" class="<?php echo $statusClass; ?>" <?php echo $component['location_id'] == $location['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars("{$location['casier']}{$location['tiroir']}-{$location['compartiment']}"); ?><?php echo $statusText; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="category_head">Catégorie principale</label>
                        <select id="category_head" name="category_head" onchange="updateSubcategories()">
                            <option value="">Sélectionner une catégorie</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $component_category_head == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="category">Sous-catégorie</label>
                        <select id="category" name="category" onchange="loadSubcategoryImages()">
                            <option value="">Sélectionner d'abord une catégorie principale</option>
                            <?php foreach ($subcategories as $subcat): ?>
                                <option value="<?php echo $subcat['id']; ?>" data-parent="<?php echo $subcat['parent_id']; ?>" <?php echo $component['category'] == $subcat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subcat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="supplier_id">Fournisseur</label>
                        <select id="supplier_id" name="supplier_id">
                            <option value="">Sélectionner un fournisseur</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['id']; ?>" <?php echo $component['supplier_id'] == $supplier['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($supplier['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="supplier_reference">Référence fournisseur</label>
                        <input type="text" id="supplier_reference" name="supplier_reference" value="<?php echo htmlspecialchars($component['supplier_reference'] ?? ''); ?>" placeholder="Ex: REF-12345">
                    </div>

                    <div class="form-group">
                        <label for="datasheet">Datasheet (URL)</label>
                        <input type="url" id="datasheet" name="datasheet" value="<?php echo htmlspecialchars($component['datasheet'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="public">
                            <input type="checkbox" id="public" name="public" value="1" <?php echo $component['public'] === 'Yes' ? 'checked' : ''; ?>>
                            Composant public
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="image_type">Type d'image</label>
                    <select id="image_type" name="image_type" onchange="toggleImageInput()">
                        <option value="">Conserver l'image actuelle</option>
                        <option value="existing">Image existante</option>
                        <option value="upload">Importer une image</option>
                        <option value="url">Lien vers une image</option>
                        <option value="none">Supprimer l'image</option>
                    </select>
                </div>
                <div class="form-group" id="existing_images_group" style="display: none;">
                    <label>Images existantes de la même sous-catégorie</label>
                    <div id="existing-images-container"></div>
                    <input type="hidden" id="selected_existing_image" name="existing_image">
                </div>
                <div class="form-group" id="image_upload_group" style="display: none;">
                    <label for="image">Image du composant (fichier)</label>
                    <input type="file" id="image" name="image" accept="image/*">
                </div>
                <div class="form-group" id="image_url_group" style="display: none;">
                    <label for="image_url">URL de l'image</label>
                    <input type="url" id="image_url" name="image_url" placeholder="https://exemple.com/image.jpg" onchange="updateImagePreview()">
                    <div id="image_preview" style="margin-top: 10px; display: none;">
                        <img id="preview_img" src="" alt="Aperçu" style="max-width: 150px; max-height: 150px; border: 1px solid var(--border-color); border-radius: var(--radius-sm);">
                    </div>
                </div>
                <div class="form-group">
                    <label for="comment">Commentaire</label>
                    <textarea id="comment" name="comment" rows="3"><?php echo htmlspecialchars($component['comment'] ?? ''); ?></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">💾 Sauvegarder les modifications</button>
                    <a href="components.php" class="btn btn-ghost">Annuler</a>
                </div>
            </form>
        </div>

        <footer>
            Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
        </footer>
    </div>

    <script>
        const allSubcategories = [];
        <?php foreach ($subcategories as $subcat): ?>
        allSubcategories.push({
            id: '<?php echo $subcat['id']; ?>',
            name: '<?php echo addslashes($subcat['name']); ?>',
            parent_id: '<?php echo $subcat['parent_id']; ?>',
            selected: <?php echo $component['category'] == $subcat['id'] ? 'true' : 'false'; ?>
        });
        <?php endforeach; ?>
        
        function updateSubcategories() {
            const categoryHead = document.getElementById('category_head');
            const category = document.getElementById('category');
            const selectedParentId = categoryHead.value;
            const currentSelection = category.value;
            
            category.innerHTML = '<option value="">Sélectionner une sous-catégorie</option>';
            
            if (selectedParentId) {
                allSubcategories.forEach(subcat => {
                    if (subcat.parent_id === selectedParentId) {
                        const option = document.createElement('option');
                        option.value = subcat.id;
                        option.textContent = subcat.name;
                        if (subcat.selected || subcat.id === currentSelection) {
                            option.selected = true;
                        }
                        category.appendChild(option);
                    }
                });
            } else {
                category.innerHTML = '<option value="">Sélectionner d\'abord une catégorie principale</option>';
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($component_category_head): ?>
            document.getElementById('category_head').value = '<?php echo $component_category_head; ?>';
            <?php endif; ?>
            updateSubcategories();
        });
         
         function toggleImageInput() {
             const imageType = document.getElementById('image_type').value;
             const uploadGroup = document.getElementById('image_upload_group');
             const urlGroup = document.getElementById('image_url_group');
             const existingGroup = document.getElementById('existing_images_group');
             const imageUrlInput = document.getElementById('image_url');
             
             uploadGroup.style.display = 'none';
             urlGroup.style.display = 'none';
             existingGroup.style.display = 'none';
             
             if (imageType === 'url') {
                 urlGroup.style.display = 'block';
                 imageUrlInput.removeAttribute('disabled');
                 imageUrlInput.type = 'url';
             } else {
                 imageUrlInput.setAttribute('disabled', 'disabled');
                 imageUrlInput.type = 'text';
                 if (imageType === 'upload') {
                     uploadGroup.style.display = 'block';
                 } else if (imageType === 'existing') {
                     existingGroup.style.display = 'block';
                     loadSubcategoryImages();
                 }
             }
         }
         
         function loadSubcategoryImages() {
             const categorySelect = document.getElementById('category');
             const subcategoryId = categorySelect.value;
             const container = document.getElementById('existing-images-container');
             
             if (!subcategoryId) {
                 container.innerHTML = '<p style="color: var(--text-secondary); padding: 10px;">Veuillez d\'abord sélectionner une sous-catégorie.</p>';
                 return;
             }
             
             fetch('get_subcategory_images.php?subcategory_id=' + subcategoryId)
                 .then(response => response.json())
                 .then(data => {
                     displayExistingImages(data);
                 })
                 .catch(error => {
                     console.error('Erreur:', error);
                     container.innerHTML = '<p style="color: var(--accent-red);">Erreur lors du chargement des images.</p>';
                 });
         }
         
         function displayExistingImages(images) {
             const container = document.getElementById('existing-images-container');
             
             if (images.length === 0) {
                 container.innerHTML = '<p style="color: var(--text-secondary); padding: 10px;">Aucune image trouvée pour cette sous-catégorie.</p>';
                 return;
             }
             
             let html = '<div class="existing-images-grid">';
             images.forEach(image => {
                 const imageName = image.image_path.split('/').pop();
                 html += `
                     <div class="existing-image-item" onclick="selectExistingImage('${image.image_path}', this)">
                         <img src="${image.image_path}" alt="${imageName}" onerror="this.src='img/placeholder.png'">
                         <div class="image-name">${imageName}</div>
                     </div>
                 `;
             });
             html += '</div>';
             
             container.innerHTML = html;
         }
         
         function selectExistingImage(imagePath, element) {
             document.querySelectorAll('.existing-image-item').forEach(item => {
                 item.classList.remove('selected');
             });
             
             element.classList.add('selected');
             document.getElementById('selected_existing_image').value = imagePath;
         }
         
         function setupPackageAutocomplete() {
             const packageInput = document.getElementById('package');
             const suggestionsDiv = document.getElementById('package-suggestions');
             
             packageInput.addEventListener('input', function() {
                 const query = this.value.trim();
                 
                 if (query.length === 0) {
                     suggestionsDiv.innerHTML = '';
                     suggestionsDiv.style.display = 'none';
                     return;
                 }
                 
                 fetch('get_packages.php?search=' + encodeURIComponent(query))
                     .then(response => response.json())
                     .then(data => {
                         if (Array.isArray(data) && data.length > 0) {
                             let html = '';
                             data.forEach(pkg => {
                                 const pkgName = (typeof pkg === 'string') ? pkg : (pkg.name || '');
                                 if (!pkgName) return;
                                 const extra = [];
                                 if (typeof pkg === 'object' && pkg.package_type) extra.push(pkg.package_type);
                                 if (typeof pkg === 'object' && pkg.pin_count) extra.push(`${pkg.pin_count} pins`);
                                 if (typeof pkg === 'object' && pkg.mounting_type) extra.push(pkg.mounting_type);
                                 const extraHtml = extra.length ? ` <small style="color:var(--text-muted); margin-left:8px;">${extra.join(' · ')}</small>` : '';
                                 html += `<div class="suggestion-item" onclick="selectPackage('${pkgName.replace(/'/g, "\\'")}')">${pkgName}${extraHtml}</div>`;
                             });
                             suggestionsDiv.innerHTML = html;
                             suggestionsDiv.style.display = 'block';
                         } else {
                             suggestionsDiv.innerHTML = '';
                             suggestionsDiv.style.display = 'none';
                         }
                     })
                     .catch(error => {
                         console.error('Erreur:', error);
                         suggestionsDiv.innerHTML = '';
                         suggestionsDiv.style.display = 'none';
                     });
             });
             
             document.addEventListener('click', function(e) {
                 if (!packageInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
                     suggestionsDiv.style.display = 'none';
                 }
             });
         }
         
         function setupManufacturerAutocomplete() {
             const manuInput = document.getElementById('manufacturer');
             const suggestionsDiv = document.getElementById('manufacturer-suggestions');
             if (!manuInput || !suggestionsDiv) return;
             const manuList = Array.isArray(MANUFACTURERS_LIST) ? MANUFACTURERS_LIST : [];
             
             manuInput.addEventListener('input', function() {
                 const query = this.value.trim().toLowerCase();
                 if (query.length === 0) {
                     suggestionsDiv.innerHTML = '';
                     suggestionsDiv.style.display = 'none';
                     return;
                 }
                 
                 const matches = manuList.filter(m => m.toLowerCase().includes(query)).slice(0, 20);
                 
                 if (matches.length > 0) {
                     suggestionsDiv.innerHTML = matches.map(m => 
                         `<div class="suggestion-item" onclick="selectManufacturer('${m.replace(/'/g, "\\'")}')">${m}</div>`
                     ).join('');
                     suggestionsDiv.style.display = 'block';
                 } else {
                     suggestionsDiv.innerHTML = '';
                     suggestionsDiv.style.display = 'none';
                 }
             });
             
             document.addEventListener('click', function(e) {
                 if (!manuInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
                     suggestionsDiv.style.display = 'none';
                 }
             });
         }
         
         function selectManufacturer(manufacturerName) {
             const el = document.getElementById('manufacturer');
             if (el) el.value = manufacturerName;
             const div = document.getElementById('manufacturer-suggestions');
             if (div) div.style.display = 'none';
         }
         
         function selectPackage(packageName) {
             document.getElementById('package').value = packageName;
             document.getElementById('package-suggestions').style.display = 'none';
             checkAndSetPackageImage(packageName);
         }
         
         function checkAndSetPackageImage(packageName) {
             const imageExtensions = ['png', 'jpg', 'jpeg', 'svg'];
             let imageFound = false;
             
             function testImageExtension(index) {
                 if (index >= imageExtensions.length || imageFound) {
                     return;
                 }
                 
                 const ext = imageExtensions[index];
                 const imagePath = `img/${packageName}.${ext}`;
                 const img = new Image();
                 
                 img.onload = function() {
                     if (!imageFound) {
                         imageFound = true;
                         document.getElementById('image_type').value = 'url';
                         toggleImageInput();
                         document.getElementById('image_url').value = imagePath;
                         
                         const imageUrlInput = document.getElementById('image_url');
                         imageUrlInput.removeAttribute('disabled');
                         imageUrlInput.type = 'text';
                         
                         updateImagePreview();
                         
                         const imageUrlGroup = document.getElementById('image_url_group');
                         if (!imageUrlGroup.querySelector('.auto-image-notice')) {
                             const notice = document.createElement('div');
                             notice.className = 'auto-image-notice';
                             notice.style.cssText = 'color: var(--accent-green); font-size: 12px; margin-top: 5px;';
                             notice.textContent = '✓ Image du package détectée automatiquement';
                             imageUrlGroup.appendChild(notice);
                         }
                     }
                 };
                 
                 img.onerror = function() {
                     testImageExtension(index + 1);
                 };
                 
                 img.src = imagePath;
             }
             
             testImageExtension(0);
         }
         
         const MANUFACTURERS_LIST = <?php echo json_encode($manufacturers ?? [], JSON_UNESCAPED_UNICODE); ?>;
         
         document.addEventListener('DOMContentLoaded', function() {
             setupPackageAutocomplete();
             setupManufacturerAutocomplete();
         });
        
        function updateImagePreview() {
            const imageUrl = document.getElementById('image_url').value;
            const previewDiv = document.getElementById('image_preview');
            const previewImg = document.getElementById('preview_img');
            
            if (imageUrl) {
                previewImg.src = imageUrl;
                previewImg.onload = function() {
                    previewDiv.style.display = 'block';
                };
                previewImg.onerror = function() {
                    previewDiv.style.display = 'none';
                };
            } else {
                previewDiv.style.display = 'none';
            }
        }
    </script>
</body>
</html>
