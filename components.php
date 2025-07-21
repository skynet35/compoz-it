<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=not_logged_in');
    exit();
}

// Connexion à la base de données
try {
    $pdo = getConnection();
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer les filtres
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : null;
$subcategory_filter = isset($_GET['subcategory']) ? (int)$_GET['subcategory'] : null;

// Construire la requête avec filtres
$sql = "
    SELECT d.*, cs.name as subcategory_name, ch.name as category_name,
           l.casier, l.tiroir, l.compartiment, s.name as supplier_name
    FROM data d 
    LEFT JOIN category_sub cs ON d.category = cs.id 
    LEFT JOIN category_head ch ON cs.category_head_id = ch.id 
    LEFT JOIN location l ON d.location_id = l.id
    LEFT JOIN suppliers s ON d.supplier_id = s.id
    WHERE d.owner = ?";

$params = [$_SESSION['user_id']];

if ($category_filter) {
    $sql .= " AND ch.id = ?";
    $params[] = $category_filter;
}

if ($subcategory_filter) {
    $sql .= " AND cs.id = ?";
    $params[] = $subcategory_filter;
}

$sql .= " ORDER BY d.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$components = $stmt->fetchAll();

// Récupérer les catégories pour le menu de filtrage
$stmt = $pdo->query("SELECT * FROM category_head ORDER BY name");
$categories = $stmt->fetchAll();

// Récupérer les sous-catégories
$stmt = $pdo->query("SELECT * FROM category_sub ORDER BY category_head_id, name");
$subcategories = $stmt->fetchAll();

// Grouper les sous-catégories par catégorie principale
$subcategories_by_category = [];
foreach ($subcategories as $subcat) {
    $subcategories_by_category[$subcat['category_head_id']][] = $subcat;
}


?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CompoZ'IT - Gestion de composants électroniques</title>
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

        .header-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
        }

        .logo {
            width: 60px;
            height: 60px;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
        }

        .header h1 {
            font-size: 2.5em;
            margin: 0;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .header .subtitle {
            font-size: 1.1em;
            opacity: 0.9;
            margin-top: 5px;
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

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
        }

        .btn-secondary {
            background: #2196F3;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-danger {
            background: #f44336;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .content {
            padding: 30px;
        }



        .components-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .components-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 10px;
            text-align: left;
            font-weight: bold;
        }

        .components-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
        }

        .components-table tr:hover {
            background: #f8f9fa;
        }

        .quantity-badge {
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .category-badge {
            background: #6c757d;
            color: white;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 11px;
        }

        .no-components {
            text-align: center;
            padding: 50px;
            color: #666;
            font-size: 18px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: bold;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .filter-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 200px;
        }

        .filter-group label {
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }

        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .quantity-btn {
            width: 25px;
            height: 25px;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .quantity-btn.increase {
            background: #28a745;
            color: white;
        }

        .quantity-btn.decrease {
            background: #dc3545;
            color: white;
        }

        .quantity-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .quantity-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .quantity-display {
            min-width: 40px;
            text-align: center;
            font-weight: bold;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .btn-filter {
            background: #6c757d;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-filter:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }

        .btn-clear {
            background: #17a2b8;
        }

        .btn-clear:hover {
            background: #138496;
        }
        
        /* Styles pour les chips de catégories */
        .categories-container, .subcategories-container {
            margin-bottom: 20px;
        }
        
        .category-chips, .subcategory-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .category-chip, .subcategory-chip {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px solid #dee2e6;
            color: #495057;
            padding: 10px 16px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            user-select: none;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .category-chip:hover, .subcategory-chip:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .category-chip.active, .subcategory-chip.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .category-chip.active:hover, .subcategory-chip.active:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
            transform: translateY(-1px);
        }
        
        .subcategories-container {
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .components-table {
                font-size: 12px;
            }
            
            .components-table th,
            .components-table td {
                padding: 8px 5px;
            }

            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                min-width: auto;
            }
        }

        /* Styles pour la modal de sélection d'images */
        .component-image {
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .component-image:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .no-image-placeholder {
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .no-image-placeholder:hover {
            background: #e0e0e0 !important;
            transform: scale(1.05);
        }

        .image-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .image-modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 80%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            position: relative;
        }

        .close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            position: absolute;
            right: 15px;
            top: 15px;
        }

        .close-modal:hover {
            color: #000;
        }

        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .image-option {
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            background: white;
        }

        .image-option:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .image-option img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 5px;
        }

        .image-option-name {
            margin-top: 8px;
            font-size: 12px;
            color: #666;
            word-break: break-word;
        }

        .remove-image-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .remove-image-btn:hover {
            background: #c82333;
            transform: translateY(-1px);
        }
        
        .upload-image-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            margin-right: 10px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .upload-image-btn:hover {
            background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .upload-image-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 10px rgba(102, 126, 234, 0.3);
        }
        
        .upload-section {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .upload-progress {
            display: none;
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            width: 0%;
            transition: width 0.3s ease;
        }
        
        /* Styles pour la modal de suppression */
        .delete-modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: translate(-50%, -60%) scale(0.8);
            }
            to { 
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }
        
        .delete-modal-content {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            overflow: hidden;
            min-width: 400px;
            max-width: 500px;
            animation: slideIn 0.4s ease;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .delete-modal-header {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
            padding: 25px 30px;
            text-align: center;
            position: relative;
        }
        
        .delete-modal-header h3 {
            margin: 0;
            font-size: 1.4em;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .delete-modal-body {
            padding: 30px;
            text-align: center;
        }
        
        .delete-modal-body p {
            margin: 15px 0;
            font-size: 1.1em;
            color: #333;
        }
        
        .component-name {
            font-weight: bold;
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
            padding: 10px 15px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
            font-size: 1.2em !important;
        }
        
        .warning-text {
            color: #ff6b6b;
            font-weight: 600;
            font-size: 1em !important;
            margin-top: 20px !important;
        }
        
        .delete-modal-footer {
            padding: 20px 30px 30px;
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        
        .btn-cancel, .btn-delete {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-cancel {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }
        
        .btn-cancel:hover {
            background: linear-gradient(135deg, #5a6268 0%, #495057 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }
        
        .btn-delete:hover {
            background: linear-gradient(135deg, #ff5252 0%, #e53e3e 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4);
        }
        
        .btn-cancel:active, .btn-delete:active {
            transform: translateY(0);
        }
        
        @media (max-width: 480px) {
            .delete-modal-content {
                min-width: 90%;
                margin: 0 5%;
            }
            
            .delete-modal-footer {
                flex-direction: column;
            }
            
            .btn-cancel, .btn-delete {
                width: 100%;
            }
        }
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                <strong>👤</strong> <?php echo htmlspecialchars($_SESSION['user_email']); ?>
                <a href="logout.php" style="margin-left: 15px; color: #dc3545; text-decoration: none; font-weight: bold;">🚪 Déconnexion</a>
            </div>
            <div class="header-content">
                <img src="img/compozit.svg" alt="CompoZ'IT Logo" class="logo">
                <div>
                    <h1>CompoZ'IT</h1>
                    <div class="subtitle">Gestion de composants électroniques</div>
                </div>
            </div>
            <div class="nav-section">
                <div class="nav-buttons">
                    <a href="components.php" class="active">📦 Composants</a>
                    <a href="create_component.php">➕ Créer</a>
                    <a href="projects.php">🚀 Projets</a>
                    <a href="settings.php">⚙️ Paramètres</a>
                </div>
            </div>
        </div>

        <div class="content">
            <!-- Messages de succès et d'erreur -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <?php
                    switch($_GET['success']) {
                        case 'component_added':
                            echo "✅ Composant ajouté avec succès !";
                            break;
                        case 'component_updated':
                            echo "✅ Composant modifié avec succès !";
                            break;
                        case 'component_deleted':
                            $name = isset($_GET['name']) ? htmlspecialchars($_GET['name']) : 'le composant';
                            echo "✅ " . $name . " a été supprimé avec succès !";
                            break;
                        default:
                            echo "✅ Opération réussie !";
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error">
                    <?php
                    switch($_GET['error']) {
                        case 'name_required':
                            echo "❌ Le nom du composant est obligatoire.";
                            break;
                        case 'invalid_quantity':
                            echo "❌ La quantité doit être un nombre positif.";
                            break;
                        case 'add_failed':
                            echo "❌ Erreur lors de l'ajout du composant.";
                            break;
                        case 'component_not_found':
                            echo "❌ Composant introuvable.";
                            break;
                        case 'delete_failed':
                            echo "❌ Erreur lors de la suppression.";
                            break;
                        case 'database_error':
                            echo "❌ Erreur de base de données.";
                            break;
                        case 'quantity_not_zero':
                            $name = isset($_GET['name']) ? htmlspecialchars($_GET['name']) : 'le composant';
                            $quantity = isset($_GET['quantity']) ? (int)$_GET['quantity'] : 0;
                            echo "❌ Impossible de supprimer " . $name . " : la quantité doit être à 0 (actuellement " . $quantity . " unité(s)).";
                            break;
                        default:
                            echo "❌ Une erreur s'est produite.";
                            break;
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistiques -->
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($components); ?></div>
                    <div class="stat-label">Composants Total</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo array_sum(array_column($components, 'quantity')); ?></div>
                    <div class="stat-label">Quantité Total</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count(array_unique(array_column($components, 'category'))); ?></div>
                    <div class="stat-label">Catégories Utilisées</div>
                </div>
            </div>

            <!-- Section de filtrage -->
            <div class="filter-section">
                <h3 style="margin-bottom: 15px; color: #333;">🔍 Filtrer par catégorie</h3>
                
                <!-- Catégories principales -->
                <div class="categories-container">
                    <h4 style="margin-bottom: 10px; color: #555;">Catégories principales :</h4>
                    <div class="category-chips">
                        <div class="category-chip <?php echo !$category_filter ? 'active' : ''; ?>" onclick="selectCategory(null)">
                            📦 Toutes
                        </div>
                        <?php foreach ($categories as $cat): ?>
                            <div class="category-chip <?php echo ($category_filter == $cat['id']) ? 'active' : ''; ?>" 
                                 onclick="selectCategory(<?php echo $cat['id']; ?>)" 
                                 data-category-id="<?php echo $cat['id']; ?>">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Sous-catégories -->
                <div class="subcategories-container" id="subcategoriesContainer" <?php echo !$category_filter ? 'style="display: none;"' : ''; ?>>
                    <h4 style="margin-bottom: 10px; color: #555;">Sous-catégories :</h4>
                    <div class="subcategory-chips" id="subcategoryChips">
                        <div class="subcategory-chip <?php echo !$subcategory_filter ? 'active' : ''; ?>" onclick="selectSubcategory(null)">
                            📋 Toutes
                        </div>
                        <?php if ($category_filter && isset($subcategories_by_category[$category_filter])): ?>
                            <?php foreach ($subcategories_by_category[$category_filter] as $subcat): ?>
                                <div class="subcategory-chip <?php echo ($subcategory_filter == $subcat['id']) ? 'active' : ''; ?>" 
                                     onclick="selectSubcategory(<?php echo $subcat['id']; ?>)" 
                                     data-subcategory-id="<?php echo $subcat['id']; ?>">
                                    <?php echo htmlspecialchars($subcat['name']); ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Formulaire caché pour la soumission -->
                <form method="GET" action="components.php" id="filterForm" style="display: none;">
                    <input type="hidden" name="category" id="categoryInput" value="<?php echo $category_filter; ?>">
                    <input type="hidden" name="subcategory" id="subcategoryInput" value="<?php echo $subcategory_filter; ?>">
                </form>
            </div>

            <!-- Bouton d'ajout -->
            <div style="text-align: center; margin-bottom: 30px;">
                <a href="create_component.php" class="btn btn-primary" style="font-size: 16px; padding: 15px 30px;">➕ Créer un nouveau composant</a>
            </div>

            <!-- Liste des composants -->
            <?php if (empty($components)): ?>
                <div class="no-components">
                    <h3>Aucun composant trouvé</h3>
                    <p>Commencez par ajouter votre premier composant électronique !</p>
                </div>
            <?php else: ?>
                <table class="components-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Nom</th>
                            <th style="display: none;">Fabricant</th>
                            <th>Package</th>
                            <th>Pins</th>
                            <th>SMD</th>
                            <th>Quantité</th>
                            <th style="display: none;">Fournisseur</th>
                            <th style="display: none;">Réf. Fournisseur</th>
                            <th>Emplacement</th>
                            <th>Datasheet</th>
                            <th>Commentaire</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($components as $component): ?>
                            <tr>
                                <td>
                                    <?php if ($component['image_path']): ?>
                                        <img src="<?php echo htmlspecialchars($component['image_path']); ?>" alt="Image du composant" class="component-image" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;" onclick="openImageModal(<?php echo $component['id']; ?>)" title="Cliquer pour changer l'image">
                                    <?php else: ?>
                                        <div class="no-image-placeholder" style="width: 50px; height: 50px; background: #f0f0f0; border-radius: 5px; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #666;" onclick="openImageModal(<?php echo $component['id']; ?>)" title="Cliquer pour ajouter une image">📷</div>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo htmlspecialchars($component['name']); ?></strong></td>
                                <td style="display: none;"><?php echo htmlspecialchars($component['manufacturer'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($component['package'] ?? '-'); ?></td>
                                <td><?php echo $component['pins'] ?? '-'; ?></td>
                                <td><?php echo $component['smd']; ?></td>
                                <td>
                                    <div class="quantity-controls">
                                        <button class="quantity-btn decrease" onclick="updateQuantity(<?php echo $component['id']; ?>, -1)" <?php echo ($component['quantity'] <= 0) ? 'disabled' : ''; ?>>-</button>
                                        <span class="quantity-display" id="quantity-<?php echo $component['id']; ?>"><?php echo $component['quantity']; ?></span>
                                        <button class="quantity-btn increase" onclick="updateQuantity(<?php echo $component['id']; ?>, 1)">+</button>
                                    </div>
                                </td>
                                <td style="display: none;"><?php echo htmlspecialchars($component['supplier_name'] ?? 'Non défini'); ?></td>
                                <td style="display: none;"><?php echo htmlspecialchars($component['supplier_reference'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($component['casier']): ?>
                                        <?php echo htmlspecialchars($component['casier'] . '-' . $component['tiroir'] . '-' . $component['compartiment']); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($component['datasheet']): ?>
                                        <a href="<?php echo htmlspecialchars($component['datasheet']); ?>" target="_blank" class="btn btn-info" style="font-size: 11px; padding: 3px 8px;">📄 Voir</a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars(substr($component['comment'] ?? '', 0, 50)) . (strlen($component['comment'] ?? '') > 50 ? '...' : ''); ?></td>
                                <td style="white-space: nowrap;">
                                    <a href="component_sheet.php?id=<?php echo $component['id']; ?>" class="btn btn-info" style="font-size: 12px; padding: 6px 10px; margin-right: 5px; display: inline-block;" title="Fiche produit">📋 Fiche</a>
                                    <button onclick="openProjectModal(<?php echo $component['id']; ?>, '<?php echo htmlspecialchars($component['name'], ENT_QUOTES); ?>')" class="btn btn-success" style="font-size: 12px; padding: 6px 10px; margin-right: 5px; display: inline-block;" title="Ajouter au projet">🚀 Projet</button>
                                    <a href="edit_component.php?id=<?php echo $component['id']; ?>" class="btn btn-secondary" style="font-size: 12px; padding: 6px 10px; margin-right: 5px; display: inline-block;" title="Modifier le composant">✏️</a>
                                    <?php if ($component['quantity'] > 0): ?>
                                        <span class="btn btn-danger" style="font-size: 12px; padding: 6px 10px; display: inline-block; opacity: 0.5; cursor: not-allowed;" title="Impossible de supprimer : quantité non nulle (<?php echo $component['quantity']; ?> unité(s))">🗑️</span>
                                    <?php else: ?>
                                        <button class="btn btn-danger" style="font-size: 12px; padding: 6px 10px; display: inline-block;" onclick="openDeleteModal(<?php echo $component['id']; ?>, '<?php echo htmlspecialchars($component['name'], ENT_QUOTES); ?>')" title="Supprimer le composant">🗑️</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal de sélection d'images -->
    <div id="imageModal" class="image-modal">
        <div class="image-modal-content">
            <span class="close-modal" onclick="closeImageModal()">&times;</span>
            <h3>Choisir une image</h3>
            <p>Sélectionnez une image du dossier /img ou importez une nouvelle image :</p>
            
            <div class="upload-section">
                <input type="file" id="imageUpload" accept="image/*" style="display: none;" onchange="uploadImage()">
                <button class="upload-image-btn" onclick="document.getElementById('imageUpload').click()">📁 Importer une image du PC</button>
                <button class="remove-image-btn" onclick="removeImage()">🗑️ Supprimer l'image actuelle</button>
            </div>
            
            <div class="upload-progress" id="uploadProgress">
                 <p>Upload en cours...</p>
                 <div class="progress-bar">
                     <div class="progress-fill" id="progressFill"></div>
                 </div>
             </div>
             
             <div id="imageGrid" class="image-grid">
                 <!-- Les images seront chargées ici via JavaScript -->
             </div>
        </div>
    </div>
    
    <!-- Modal d'ajout au projet -->
    <div id="projectModal" class="image-modal">
        <div class="image-modal-content">
            <span class="close-modal" onclick="closeProjectModal()">&times;</span>
            <h3>Ajouter au projet</h3>
            <p id="componentName">Composant: </p>
            
            <div style="margin-bottom: 20px;">
                 <label for="projectSelect" style="display: block; margin-bottom: 5px; font-weight: bold;">Sélectionner un projet:</label>
                 <select id="projectSelect" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" onchange="toggleNewProjectForm()">
                     <option value="">Chargement des projets...</option>
                 </select>
             </div>
             
             <!-- Formulaire de création de nouveau projet -->
             <div id="newProjectForm" style="display: none; margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 4px; background-color: #f8f9fa;">
                 <h4 style="margin-top: 0; margin-bottom: 15px;">Créer un nouveau projet</h4>
                 <div style="margin-bottom: 10px;">
                     <label for="newProjectName" style="display: block; margin-bottom: 5px; font-weight: bold;">Nom du projet:</label>
                     <input type="text" id="newProjectName" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="Nom du nouveau projet">
                 </div>
                 <div style="margin-bottom: 10px;">
                     <label for="newProjectDescription" style="display: block; margin-bottom: 5px; font-weight: bold;">Description:</label>
                     <textarea id="newProjectDescription" rows="2" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;" placeholder="Description du projet (optionnel)"></textarea>
                 </div>
             </div>
            
            <div style="margin-bottom: 20px;">
                <label for="componentQuantity" style="display: block; margin-bottom: 5px; font-weight: bold;">Quantité nécessaire:</label>
                <input type="number" id="componentQuantity" min="1" value="1" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label for="componentNotes" style="display: block; margin-bottom: 5px; font-weight: bold;">Notes (optionnel):</label>
                <textarea id="componentNotes" rows="3" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; resize: vertical;" placeholder="Notes sur l'utilisation de ce composant dans le projet..."></textarea>
            </div>
            
            <div style="text-align: right;">
                <button onclick="closeProjectModal()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 4px; margin-right: 10px; cursor: pointer;">Annuler</button>
                <button onclick="addToProject()" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">Ajouter au projet</button>
            </div>
        </div>
    </div>
    
    <!-- Modal de confirmation de suppression -->
    <div id="deleteModal" class="delete-modal">
        <div class="delete-modal-content">
            <div class="delete-modal-header">
                <h3>⚠️ Confirmation de suppression</h3>
            </div>
            <div class="delete-modal-body">
                <p>Êtes-vous sûr de vouloir supprimer ce composant ?</p>
                <p id="componentToDelete" class="component-name"></p>
                <p class="warning-text">⚠️ Cette action est irréversible !</p>
            </div>
            <div class="delete-modal-footer">
                <button onclick="closeDeleteModal()" class="btn-cancel">❌ Annuler</button>
                <button onclick="confirmDelete()" class="btn-delete">🗑️ Supprimer</button>
            </div>
        </div>
    </div>
    
    <script>
        // Fonction pour mettre à jour la quantité
        function updateQuantity(componentId, change) {
            const quantityElement = document.getElementById('quantity-' + componentId);
            const currentQuantity = parseInt(quantityElement.textContent);
            const newQuantity = currentQuantity + change;
            
            if (newQuantity < 0) {
                return;
            }
            
            // Envoyer la requête AJAX
            fetch('update_quantity.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'component_id=' + componentId + '&change=' + change
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    quantityElement.textContent = data.new_quantity;
                    
                    // Mettre à jour l'état du bouton diminuer
                    const decreaseBtn = quantityElement.parentElement.querySelector('.decrease');
                    if (data.new_quantity <= 0) {
                        decreaseBtn.disabled = true;
                    } else {
                        decreaseBtn.disabled = false;
                    }
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de la mise à jour de la quantité');
            });
        }
        
        // Données des sous-catégories pour le filtrage dynamique
        const subcategoriesData = <?php echo json_encode($subcategories_by_category); ?>;
        
        // Fonction pour mettre à jour les sous-catégories
        function updateSubcategories() {
            const categorySelect = document.getElementById('category');
            const subcategorySelect = document.getElementById('subcategory');
            const selectedCategory = categorySelect.value;
            
            // Vider les options actuelles
            subcategorySelect.innerHTML = '<option value="">Toutes les sous-catégories</option>';
            
            // Ajouter les nouvelles options si une catégorie est sélectionnée
            if (selectedCategory && subcategoriesData[selectedCategory]) {
                subcategoriesData[selectedCategory].forEach(function(subcat) {
                    const option = document.createElement('option');
                    option.value = subcat.id;
                    option.textContent = subcat.name;
                    subcategorySelect.appendChild(option);
                });
            }
        }
        
        // Variable globale pour stocker l'ID du composant en cours de modification
        let currentComponentId = null;
        
        // Fonctions pour la modal d'images
        function openImageModal(componentId) {
            currentComponentId = componentId;
            document.getElementById('imageModal').style.display = 'block';
            loadImages();
        }
        
        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
            currentComponentId = null;
        }
        
        function loadImages() {
            fetch('get_images.php')
            .then(response => response.json())
            .then(data => {
                if (data.images) {
                    const imageGrid = document.getElementById('imageGrid');
                    imageGrid.innerHTML = '';
                    
                    data.images.forEach(image => {
                        const imageOption = document.createElement('div');
                        imageOption.className = 'image-option';
                        imageOption.onclick = () => selectImage(image.path);
                        
                        imageOption.innerHTML = `
                            <img src="${image.path}" alt="${image.name}">
                            <div class="image-option-name">${image.name}</div>
                        `;
                        
                        imageGrid.appendChild(imageOption);
                    });
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement des images:', error);
                alert('Erreur lors du chargement des images');
            });
        }
        
        function selectImage(imagePath) {
            if (!currentComponentId) {
                alert('Erreur: Aucun composant sélectionné');
                return;
            }
            
            fetch('update_image.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'component_id=' + currentComponentId + '&image_path=' + encodeURIComponent(imagePath)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Recharger la page pour afficher la nouvelle image
                    location.reload();
                } else {
                    alert('Erreur lors de la mise à jour : ' + (data.error || 'Erreur inconnue'));
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur de communication avec le serveur');
            });
        }
        
        function removeImage() {
            if (!currentComponentId) {
                alert('Erreur: Aucun composant sélectionné');
                return;
            }
            
            if (confirm('Êtes-vous sûr de vouloir supprimer l\'image de ce composant ?')) {
                fetch('update_image.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'component_id=' + currentComponentId + '&image_path='
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Recharger la page pour afficher le changement
                        location.reload();
                    } else {
                        alert('Erreur lors de la suppression : ' + (data.error || 'Erreur inconnue'));
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur de communication avec le serveur');
                });
            }
        }
        
        // Fonction pour uploader une image
         function uploadImage() {
             const fileInput = document.getElementById('imageUpload');
             const file = fileInput.files[0];
             
             if (!file) {
                 return;
             }
             
             // Vérifier le type de fichier
             const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];
             if (!allowedTypes.includes(file.type)) {
                 alert('Type de fichier non autorisé. Formats acceptés: JPG, PNG, GIF, SVG, WebP');
                 return;
             }
             
             // Vérifier la taille (max 5MB)
             if (file.size > 5 * 1024 * 1024) {
                 alert('Le fichier est trop volumineux (max 5MB)');
                 return;
             }
             
             const formData = new FormData();
             formData.append('image', file);
             
             const progressDiv = document.getElementById('uploadProgress');
             const progressFill = document.getElementById('progressFill');
             
             // Afficher la barre de progression
             progressDiv.style.display = 'block';
             progressFill.style.width = '0%';
             
             // Créer une requête XMLHttpRequest pour suivre le progrès
             const xhr = new XMLHttpRequest();
             
             xhr.upload.addEventListener('progress', function(e) {
                 if (e.lengthComputable) {
                     const percentComplete = (e.loaded / e.total) * 100;
                     progressFill.style.width = percentComplete + '%';
                 }
             });
             
             xhr.onload = function() {
                 progressDiv.style.display = 'none';
                 
                 if (xhr.status === 200) {
                     try {
                         const response = JSON.parse(xhr.responseText);
                         if (response.success) {
                             // Utiliser l'image uploadée pour le composant
                             selectImage(response.image_path);
                         } else {
                             alert('Erreur lors de l\'upload : ' + (response.error || 'Erreur inconnue'));
                         }
                     } catch (e) {
                         alert('Erreur lors du traitement de la réponse');
                     }
                 } else {
                     alert('Erreur lors de l\'upload du fichier');
                 }
                 
                 // Réinitialiser l'input file
                 fileInput.value = '';
             };
             
             xhr.onerror = function() {
                 progressDiv.style.display = 'none';
                 alert('Erreur de communication avec le serveur');
                 fileInput.value = '';
             };
             
             xhr.open('POST', 'upload_image.php');
             xhr.send(formData);
         }
         
         // Variables globales pour la modal de projet
         let currentProjectComponentId = null;
         
         // Fonctions pour la modal de projet
         function openProjectModal(componentId, componentName) {
             currentProjectComponentId = componentId;
             document.getElementById('componentName').textContent = 'Composant: ' + componentName;
             document.getElementById('projectModal').style.display = 'block';
             loadProjects();
         }
         
         function closeProjectModal() {
             document.getElementById('projectModal').style.display = 'none';
             currentProjectComponentId = null;
             document.getElementById('componentQuantity').value = '1';
             document.getElementById('componentNotes').value = '';
             document.getElementById('projectSelect').value = '';
             document.getElementById('newProjectForm').style.display = 'none';
             document.getElementById('newProjectName').value = '';
             document.getElementById('newProjectDescription').value = '';
         }
         
         function loadProjects() {
             fetch('get_projects.php')
             .then(response => response.json())
             .then(data => {
                 const projectSelect = document.getElementById('projectSelect');
                 projectSelect.innerHTML = '<option value="">Sélectionner un projet...</option>';
                 
                 // Ajouter l'option pour créer un nouveau projet
                 const newProjectOption = document.createElement('option');
                 newProjectOption.value = 'new';
                 newProjectOption.textContent = '+ Créer un nouveau projet';
                 newProjectOption.style.fontWeight = 'bold';
                 newProjectOption.style.color = '#28a745';
                 projectSelect.appendChild(newProjectOption);
                 
                 if (data.projects && data.projects.length > 0) {
                     data.projects.forEach(project => {
                         const option = document.createElement('option');
                         option.value = project.id;
                         option.textContent = project.name;
                         projectSelect.appendChild(option);
                     });
                 }
             })
             .catch(error => {
                 console.error('Erreur lors du chargement des projets:', error);
                 const projectSelect = document.getElementById('projectSelect');
                 projectSelect.innerHTML = '<option value="">Erreur de chargement</option>';
                 const newProjectOption = document.createElement('option');
                 newProjectOption.value = 'new';
                 newProjectOption.textContent = '+ Créer un nouveau projet';
                 newProjectOption.style.fontWeight = 'bold';
                 newProjectOption.style.color = '#28a745';
                 projectSelect.appendChild(newProjectOption);
             });
         }
         
         function toggleNewProjectForm() {
             const projectSelect = document.getElementById('projectSelect');
             const newProjectForm = document.getElementById('newProjectForm');
             
             if (projectSelect.value === 'new') {
                 newProjectForm.style.display = 'block';
             } else {
                 newProjectForm.style.display = 'none';
                 // Réinitialiser les champs du formulaire
                 document.getElementById('newProjectName').value = '';
                 document.getElementById('newProjectDescription').value = '';
             }
         }
         
         function addToProject() {
             const projectId = document.getElementById('projectSelect').value;
             const quantity = document.getElementById('componentQuantity').value;
             const notes = document.getElementById('componentNotes').value;
             
             if (!projectId) {
                 alert('Veuillez sélectionner un projet');
                 return;
             }
             
             if (!quantity || quantity < 1) {
                 alert('Veuillez entrer une quantité valide');
                 return;
             }
             
             // Si l'utilisateur veut créer un nouveau projet
             if (projectId === 'new') {
                 const newProjectName = document.getElementById('newProjectName').value.trim();
                 const newProjectDescription = document.getElementById('newProjectDescription').value.trim();
                 
                 if (!newProjectName) {
                     alert('Veuillez entrer un nom pour le nouveau projet');
                     return;
                 }
                 
                 // Créer le nouveau projet d'abord
                 fetch('create_project_ajax.php', {
                     method: 'POST',
                     headers: {
                         'Content-Type': 'application/x-www-form-urlencoded',
                     },
                     body: 'name=' + encodeURIComponent(newProjectName) + '&description=' + encodeURIComponent(newProjectDescription)
                 })
                 .then(response => response.json())
                 .then(data => {
                     if (data.success) {
                         // Ajouter le composant au nouveau projet
                         addComponentToExistingProject(data.project_id, quantity, notes);
                     } else {
                         alert('Erreur lors de la création du projet: ' + (data.message || 'Erreur inconnue'));
                     }
                 })
                 .catch(error => {
                     console.error('Erreur:', error);
                     alert('Erreur lors de la création du projet');
                 });
             } else {
                 // Ajouter à un projet existant
                 addComponentToExistingProject(projectId, quantity, notes);
             }
         }
         
         function addComponentToExistingProject(projectId, quantity, notes) {
             fetch('add_component_to_project.php', {
                 method: 'POST',
                 headers: {
                     'Content-Type': 'application/x-www-form-urlencoded',
                 },
                 body: 'project_id=' + projectId + '&component_id=' + currentProjectComponentId + '&quantity=' + quantity + '&notes=' + encodeURIComponent(notes)
             })
             .then(response => response.json())
             .then(data => {
                 if (data.success) {
                     alert('Composant ajouté au projet avec succès!');
                     closeProjectModal();
                 } else {
                     alert('Erreur: ' + (data.message || 'Erreur inconnue'));
                 }
             })
             .catch(error => {
                 console.error('Erreur:', error);
                 alert('Erreur lors de l\'ajout au projet');
             });
         }
         
         // Variables globales pour la modal de suppression
         let deleteComponentId = null;
         
         // Fonctions pour la modal de suppression
         function openDeleteModal(componentId, componentName) {
             deleteComponentId = componentId;
             document.getElementById('componentToDelete').textContent = componentName;
             document.getElementById('deleteModal').style.display = 'block';
         }
         
         function closeDeleteModal() {
             document.getElementById('deleteModal').style.display = 'none';
             deleteComponentId = null;
         }
         
         function confirmDelete() {
             if (deleteComponentId) {
                 window.location.href = 'delete_component.php?id=' + deleteComponentId;
             }
         }
         
         // Fermer la modal en cliquant à l'extérieur
         window.onclick = function(event) {
             const imageModal = document.getElementById('imageModal');
             const projectModal = document.getElementById('projectModal');
             const deleteModal = document.getElementById('deleteModal');
             
             if (event.target === imageModal) {
                 closeImageModal();
             } else if (event.target === projectModal) {
                 closeProjectModal();
             } else if (event.target === deleteModal) {
                 closeDeleteModal();
             }
         }
         
         // Fonctions pour la gestion des catégories
         function selectCategory(categoryId) {
             // Sauvegarder la position de défilement actuelle
             sessionStorage.setItem('scrollPosition', window.pageYOffset);
             
             // Mettre à jour l'input caché
             document.getElementById('categoryInput').value = categoryId || '';
             document.getElementById('subcategoryInput').value = ''; // Reset subcategory
             
             // Mettre à jour les classes actives des catégories
             var categoryChips = document.querySelectorAll('.category-chip');
             categoryChips.forEach(function(chip) {
                 chip.classList.remove('active');
             });
             
             if (categoryId) {
                 var selectedChip = document.querySelector('.category-chip[data-category-id="' + categoryId + '"]');
                 if (selectedChip) {
                     selectedChip.classList.add('active');
                 }
                 // Afficher les sous-catégories
                 document.getElementById('subcategoriesContainer').style.display = 'block';
             } else {
                 // Sélection "Toutes" - activer le premier chip
                 categoryChips[0].classList.add('active');
                 // Masquer les sous-catégories
                 document.getElementById('subcategoriesContainer').style.display = 'none';
             }
             
             // Soumettre le formulaire
             document.getElementById('filterForm').submit();
         }
         
         function selectSubcategory(subcategoryId) {
             // Sauvegarder la position de défilement actuelle
             sessionStorage.setItem('scrollPosition', window.pageYOffset);
             
             // Mettre à jour l'input caché
             document.getElementById('subcategoryInput').value = subcategoryId || '';
             
             // Mettre à jour les classes actives des sous-catégories
             var subcategoryChips = document.querySelectorAll('.subcategory-chip');
             subcategoryChips.forEach(function(chip) {
                 chip.classList.remove('active');
             });
             
             if (subcategoryId) {
                 var selectedChip = document.querySelector('.subcategory-chip[data-subcategory-id="' + subcategoryId + '"]');
                 if (selectedChip) {
                     selectedChip.classList.add('active');
                 }
             } else {
                 // Sélection "Toutes" - activer le premier chip
                 subcategoryChips[0].classList.add('active');
             }
             
             // Soumettre le formulaire
             document.getElementById('filterForm').submit();
         }
         
         // Restaurer la position de défilement après le chargement de la page
         window.addEventListener('load', function() {
             var scrollPosition = sessionStorage.getItem('scrollPosition');
             if (scrollPosition) {
                 window.scrollTo(0, parseInt(scrollPosition));
                 sessionStorage.removeItem('scrollPosition');
             }
         });
    </script>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>