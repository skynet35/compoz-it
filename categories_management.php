<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=not_logged_in');
    exit();
}

try {
    $pdo = getConnection();
    
    // Traitement des actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add_category':
                $name = trim($_POST['category_name'] ?? '');
                if (!empty($name)) {
                    $stmt = $pdo->prepare("INSERT INTO category_head (name) VALUES (?)");
                    $stmt->execute([$name]);
                    header('Location: categories_management.php?success=category_added');
                    exit();
                } else {
                    $error = "Le nom de la catégorie est obligatoire.";
                }
                break;
                
            case 'add_subcategory':
                $name = trim($_POST['subcategory_name'] ?? '');
                $parent_id = (int)($_POST['parent_category'] ?? 0);
                if (!empty($name) && $parent_id > 0) {
                    $stmt = $pdo->prepare("INSERT INTO category_sub (name, category_head_id) VALUES (?, ?)");
                    $stmt->execute([$name, $parent_id]);
                    header('Location: categories_management.php?success=subcategory_added');
                    exit();
                } else {
                    $error = "Le nom de la sous-catégorie et la catégorie parent sont obligatoires.";
                }
                break;
                
            case 'delete_category':
                $id = (int)($_POST['category_id'] ?? 0);
                if ($id > 0) {
                    // Vérifier s'il y a des sous-catégories
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM category_sub WHERE category_head_id = ?");
                    $stmt->execute([$id]);
                    $subcategory_count = $stmt->fetchColumn();
                    
                    if ($subcategory_count > 0) {
                        $error = "Impossible de supprimer cette catégorie car elle contient des sous-catégories.";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM category_head WHERE id = ?");
                        $stmt->execute([$id]);
                        header('Location: categories_management.php?success=category_deleted');
                        exit();
                    }
                }
                break;
                
            case 'rename_category':
                $id = (int)($_POST['category_id'] ?? 0);
                $new_name = trim($_POST['new_name'] ?? '');
                if ($id > 0 && !empty($new_name)) {
                    $stmt = $pdo->prepare("UPDATE category_head SET name = ? WHERE id = ?");
                    $stmt->execute([$new_name, $id]);
                    header('Location: categories_management.php?success=category_renamed');
                    exit();
                } else {
                    $error = "Le nom de la catégorie est obligatoire.";
                }
                break;
                
            case 'rename_subcategory':
                $id = (int)($_POST['subcategory_id'] ?? 0);
                $new_name = trim($_POST['new_name'] ?? '');
                if ($id > 0 && !empty($new_name)) {
                    $stmt = $pdo->prepare("UPDATE category_sub SET name = ? WHERE id = ?");
                    $stmt->execute([$new_name, $id]);
                    header('Location: categories_management.php?success=subcategory_renamed');
                    exit();
                } else {
                    $error = "Le nom de la sous-catégorie est obligatoire.";
                }
                break;
                
            case 'delete_subcategory':
                $id = (int)($_POST['subcategory_id'] ?? 0);
                if ($id > 0) {
                    // Vérifier s'il y a des composants utilisant cette sous-catégorie
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM data WHERE category = ?");
                    $stmt->execute([$id]);
                    $component_count = $stmt->fetchColumn();
                    
                    if ($component_count > 0) {
                        $error = "Impossible de supprimer cette sous-catégorie car elle est utilisée par $component_count composant(s). Vous pouvez seulement la renommer.";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM category_sub WHERE id = ?");
                        $stmt->execute([$id]);
                        header('Location: categories_management.php?success=subcategory_deleted');
                        exit();
                    }
                }
                break;
        }
    }
    
    // Récupérer les catégories principales
    $stmt = $pdo->query("SELECT * FROM category_head ORDER BY name");
    $categories = $stmt->fetchAll();
    
    // Récupérer les sous-catégories avec le nombre de composants
    $stmt = $pdo->query("
        SELECT cs.*, ch.name as parent_name, 
               COUNT(d.id) as component_count
        FROM category_sub cs 
        LEFT JOIN category_head ch ON cs.category_head_id = ch.id 
        LEFT JOIN data d ON cs.id = d.category
        GROUP BY cs.id, cs.name, cs.category_head_id, ch.name
        ORDER BY ch.name, cs.name
    ");
    $subcategories = $stmt->fetchAll();
    
    // Grouper les sous-catégories par catégorie parent
    $subcategories_by_category = [];
    foreach ($subcategories as $subcat) {
        $subcategories_by_category[$subcat['category_head_id']][] = $subcat;
    }
    
} catch(PDOException $e) {
    $error = "Erreur de base de données : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Catégories - Composants</title>
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
            max-width: 1400px;
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
            position: relative;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-align: center;
        }

        .user-info {
            position: absolute;
            top: 20px;
            right: 30px;
            background: rgba(255,255,255,0.15);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .nav-section {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            text-align: center;
        }

        .nav-buttons {
            display: flex;
            gap: 15px;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
        }

        .nav-buttons a {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 12px 24px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-buttons a:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .nav-buttons a.active {
            background: rgba(255, 255, 255, 0.4);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
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

        .section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 2px solid #dee2e6;
        }

        .section h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5em;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 600;
            color: #333;
        }

        .form-group input, .form-group select {
            padding: 12px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus, .form-group select:focus {
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
            font-size: 0.9em;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
        }

        .category-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border: 2px solid #e9ecef;
        }

        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f3f4;
        }

        .category-title {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
        }

        .subcategory-list {
            list-style: none;
            padding: 0;
        }

        .subcategory-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .subcategory-item:last-child {
            border-bottom: none;
        }

        .subcategory-name {
            font-weight: 500;
        }

        .component-count {
            background: #667eea;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8em;
        }

        /* Styles pour la vue compacte */
        .compact-view .categories-grid {
            display: block;
        }

        .compact-view .category-card {
            margin-bottom: 15px;
            border: 1px solid #e1f5fe;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .compact-view .category-card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .compact-view .category-header {
            cursor: pointer;
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #1565c0;
            padding: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            border-radius: 15px 15px 0 0;
        }

        .compact-view .category-header:hover {
            background: linear-gradient(135deg, #bbdefb 0%, #90caf9 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(21, 101, 192, 0.15);
        }

        .compact-view .category-arrow {
            font-size: 1.2em;
            transition: transform 0.3s ease;
        }

        .compact-view .category-card.expanded .category-arrow {
            transform: rotate(90deg);
        }

        .compact-view .subcategory-content {
            display: none;
            background: linear-gradient(135deg, #fafafa 0%, #f5f5f5 100%);
            padding: 20px;
            border-radius: 0 0 20px 20px;
        }

        .compact-view .category-card.expanded .subcategory-content {
            display: block;
        }

        .compact-view .subcategory-list {
            margin: 0;
            padding: 0;
        }

        .compact-view .subcategory-item {
            background: white;
            margin-bottom: 12px;
            padding: 16px;
            border-radius: 15px;
            border-left: 4px solid #42a5f5;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            transition: all 0.2s ease;
        }

        .compact-view .subcategory-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(66, 165, 245, 0.15);
        }

        .compact-view .section:first-of-type,
        .compact-view .section:nth-of-type(2) {
            display: none !important;
        }

        .compact-view .section:last-of-type h2 {
            margin-bottom: 20px;
            text-align: center;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                👤 <?php echo htmlspecialchars($_SESSION['user_email']); ?>
                <a href="logout.php" style="margin-left: 15px; color: white; text-decoration: none; background: rgba(255,255,255,0.2); padding: 5px 10px; border-radius: 15px;">🚪 Déconnexion</a>
            </div>
            <h1>🏷️ Gestion des Catégories</h1>
            
            <div class="nav-section">
                <div class="nav-buttons">
                    <a href="components.php">📦 Composants</a>
                    <a href="create_component.php">➕ Créer</a>
                    <a href="projects.php">🚀 Projets</a>
                    <a href="settings.php">⚙️ Paramètres</a>
                </div>

            </div>
        </div>

        <div class="content">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    ✅ 
                    <?php
                    switch($_GET['success']) {
                        case 'category_added': echo 'Catégorie ajoutée avec succès !'; break;
                        case 'subcategory_added': echo 'Sous-catégorie ajoutée avec succès !'; break;
                        case 'category_deleted': echo 'Catégorie supprimée avec succès !'; break;
                        case 'subcategory_deleted': echo 'Sous-catégorie supprimée avec succès !'; break;
                        case 'category_renamed': echo 'Catégorie renommée avec succès !'; break;
                        case 'subcategory_renamed': echo 'Sous-catégorie renommée avec succès !'; break;
                        default: echo 'Opération réussie !';
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Ajouter une catégorie principale -->
            <div class="section">
                <h2>➕ Ajouter une catégorie principale</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add_category">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="category_name">Nom de la catégorie</label>
                            <input type="text" id="category_name" name="category_name" required placeholder="Ex: Microcontrôleurs">
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">➕ Ajouter la catégorie</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Ajouter une sous-catégorie -->
            <div class="section">
                <h2>🏷️ Ajouter une sous-catégorie</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add_subcategory">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="parent_category">Catégorie parent</label>
                            <select id="parent_category" name="parent_category" required>
                                <option value="">Sélectionner une catégorie</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="subcategory_name">Nom de la sous-catégorie</label>
                            <input type="text" id="subcategory_name" name="subcategory_name" required placeholder="Ex: Arduino">
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">🏷️ Ajouter la sous-catégorie</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Bouton de basculement de vue -->
            <div style="text-align: center; margin: 20px 0;">
                <button id="toggleViewBtn" class="btn btn-primary" onclick="toggleView()" style="background: #28a745; border: none; padding: 12px 24px; border-radius: 25px; font-weight: 500; box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3); transition: all 0.3s ease;">📋 Vue compacte</button>
            </div>

            <!-- Liste des catégories -->
            <div class="section">
                <h2>📋 Catégories existantes</h2>
                <div class="categories-grid" id="categoriesContainer">
                    <?php foreach ($categories as $category): ?>
                        <div class="category-card" data-category-id="<?php echo $category['id']; ?>">
                            <div class="category-header" onclick="toggleCategory(<?php echo $category['id']; ?>)">
                                <div>
                                    <div class="category-title"><?php echo htmlspecialchars($category['name']); ?></div>
                                    <div style="font-size: 0.8em; color: #666; margin-top: 2px;">ID: <?php echo $category['id']; ?></div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span class="category-arrow">▶</span>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="showRenameCategoryForm(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name'], ENT_QUOTES); ?>')" style="margin-right: 5px;">✏️</button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette catégorie ?')" onclick="event.stopPropagation();">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">🗑️ Supprimer</button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="subcategory-content">
                                <?php if (isset($subcategories_by_category[$category['id']])): ?>
                                    <ul class="subcategory-list">
                                        <?php foreach ($subcategories_by_category[$category['id']] as $subcat): ?>
                                            <li class="subcategory-item">
                                                <div>
                                                    <span class="subcategory-name"><?php echo htmlspecialchars($subcat['name']); ?></span>
                                                    <div style="font-size: 0.7em; color: #888; margin-top: 2px;">ID: <?php echo $subcat['id']; ?></div>
                                                </div>
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <span class="component-count"><?php echo $subcat['component_count']; ?> composants</span>
                                                    <?php if ($subcat['component_count'] > 0): ?>
                                                        <button type="button" class="btn btn-primary btn-sm" onclick="showRenameForm(<?php echo $subcat['id']; ?>, '<?php echo htmlspecialchars($subcat['name'], ENT_QUOTES); ?>')">✏️ Renommer</button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-primary btn-sm" onclick="showRenameForm(<?php echo $subcat['id']; ?>, '<?php echo htmlspecialchars($subcat['name'], ENT_QUOTES); ?>')">✏️</button>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette sous-catégorie ?')">
                                                            <input type="hidden" name="action" value="delete_subcategory">
                                                            <input type="hidden" name="subcategory_id" value="<?php echo $subcat['id']; ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p style="color: #666; font-style: italic;">Aucune sous-catégorie</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal de renommage des sous-catégories -->
    <div id="renameModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%;">
            <h3 style="margin-bottom: 20px; color: #333;">✏️ Renommer la sous-catégorie</h3>
            <form method="POST" id="renameForm">
                <input type="hidden" name="action" value="rename_subcategory">
                <input type="hidden" name="subcategory_id" id="renameSubcategoryId">
                <div class="form-group">
                    <label for="new_name">Nouveau nom :</label>
                    <input type="text" id="new_name" name="new_name" required style="width: 100%; padding: 12px; border: 2px solid #dee2e6; border-radius: 8px; font-size: 1em;">
                </div>
                <div style="margin-top: 20px; text-align: right; gap: 10px; display: flex; justify-content: flex-end;">
                    <button type="button" onclick="hideRenameForm()" class="btn" style="background: #6c757d; color: white;">Annuler</button>
                    <button type="submit" class="btn btn-primary">✏️ Renommer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de renommage des catégories principales -->
    <div id="renameCategoryModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%;">
            <h3 style="margin-bottom: 20px; color: #333;">✏️ Renommer la catégorie</h3>
            <form method="POST" id="renameCategoryForm">
                <input type="hidden" name="action" value="rename_category">
                <input type="hidden" name="category_id" id="renameCategoryId">
                <div class="form-group">
                    <label for="new_category_name">Nouveau nom :</label>
                    <input type="text" id="new_category_name" name="new_name" required style="width: 100%; padding: 12px; border: 2px solid #dee2e6; border-radius: 8px; font-size: 1em;">
                </div>
                <div style="margin-top: 20px; text-align: right; gap: 10px; display: flex; justify-content: flex-end;">
                    <button type="button" onclick="hideRenameCategoryForm()" class="btn" style="background: #6c757d; color: white;">Annuler</button>
                    <button type="submit" class="btn btn-primary">✏️ Renommer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let isCompactView = false;

        function showRenameForm(subcategoryId, currentName) {
            document.getElementById('renameSubcategoryId').value = subcategoryId;
            document.getElementById('new_name').value = currentName;
            document.getElementById('renameModal').style.display = 'flex';
            document.getElementById('new_name').focus();
            document.getElementById('new_name').select();
        }

        function hideRenameForm() {
            document.getElementById('renameModal').style.display = 'none';
        }

        function showRenameCategoryForm(categoryId, currentName) {
            document.getElementById('renameCategoryId').value = categoryId;
            document.getElementById('new_category_name').value = currentName;
            document.getElementById('renameCategoryModal').style.display = 'flex';
            document.getElementById('new_category_name').focus();
            document.getElementById('new_category_name').select();
        }

        function hideRenameCategoryForm() {
            document.getElementById('renameCategoryModal').style.display = 'none';
        }

        function toggleView() {
            const container = document.querySelector('.container');
            const toggleBtn = document.getElementById('toggleViewBtn');
            const addSections = document.querySelectorAll('.section:first-of-type, .section:nth-of-type(2)');
            
            isCompactView = !isCompactView;
            
            if (isCompactView) {
                container.classList.add('compact-view');
                toggleBtn.textContent = '📋 Vue normale';
                toggleBtn.style.background = '#dc3545';
                
                // Masquer les sections d'ajout
                addSections.forEach(section => {
                    section.style.display = 'none';
                });
            } else {
                container.classList.remove('compact-view');
                toggleBtn.textContent = '📋 Vue compacte';
                toggleBtn.style.background = '#28a745';
                
                // Réafficher les sections d'ajout
                addSections.forEach(section => {
                    section.style.display = 'block';
                });
                
                // Fermer tous les accordéons
                document.querySelectorAll('.category-card').forEach(card => {
                    card.classList.remove('expanded');
                });
            }
        }

        function toggleCategory(categoryId) {
            if (!isCompactView) return;
            
            const categoryCard = document.querySelector(`[data-category-id="${categoryId}"]`);
            categoryCard.classList.toggle('expanded');
        }

        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('renameModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideRenameForm();
            }
        });

        document.getElementById('renameCategoryModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideRenameCategoryForm();
            }
        });

        // Fermer le modal avec la touche Échap
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideRenameForm();
                hideRenameCategoryForm();
            }
        });
    </script>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>