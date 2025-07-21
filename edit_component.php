<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=not_logged_in');
    exit();
}

// Vérifier si l'ID du composant est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: components.php?error=invalid_id');
    exit();
}

$component_id = (int)$_GET['id'];

try {
    $pdo = getConnection();
    
    // Récupérer le composant (vérifier qu'il appartient à l'utilisateur)
    $stmt = $pdo->prepare("SELECT * FROM data WHERE id = ? AND owner = ?");
    $stmt->execute([$component_id, $_SESSION['user_id']]);
    $component = $stmt->fetch();
    
    if (!$component) {
        header('Location: components.php?error=component_not_found');
        exit();
    }
    
    // Traitement du formulaire de modification
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
        // $weight supprimé car la colonne n'existe plus dans la table
        $datasheet = trim($_POST['datasheet'] ?? '');
        $comment = trim($_POST['comment'] ?? '');
        $category = !empty($_POST['category']) ? (int)$_POST['category'] : null;
        $public = $_POST['public'] ?? 'No';
        $url = trim($_POST['url'] ?? '');
        $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
        $supplier_reference = trim($_POST['supplier_reference'] ?? '');
        
        // Gestion de l'image
        $image_path = $component['image_path']; // Garder l'image existante par défaut
        $image_type = $_POST['image_type'] ?? '';
        
        if ($image_type === 'existing' && !empty($_POST['existing_image'])) {
            // Utiliser une image existante
            $image_path = trim($_POST['existing_image']);
        } elseif ($image_type === 'upload' && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // Upload d'une nouvelle image
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
                    // Supprimer l'ancienne image si elle existe
                    if ($image_path && file_exists($image_path)) {
                        unlink($image_path);
                    }
                    $image_path = $upload_path;
                }
            }
        } elseif ($image_type === 'url' && !empty($_POST['image_url'])) {
            // URL d'image
            $image_path = trim($_POST['image_url']);
        } elseif ($image_type === 'none') {
            // Aucune image - supprimer l'image existante
            if ($image_path && file_exists($image_path) && strpos($image_path, 'uploads/') === 0) {
                unlink($image_path);
            }
            $image_path = null;
        }
        // Si $image_type est vide ou non défini, on garde l'image existante (pas de changement)
        
        // Validation
        if (empty($name)) {
            $error = "Le nom du composant est obligatoire.";
        } elseif ($quantity < 0) {
            $error = "La quantité ne peut pas être négative.";
        } else {
            // Mise à jour
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
    
    // Récupérer les catégories principales
    $stmt = $pdo->query("SELECT * FROM category_head ORDER BY name");
    $categories = $stmt->fetchAll();
    
    // Récupérer les sous-catégories pour le formulaire
    $stmt = $pdo->query("SELECT cs.*, ch.name as parent_name, cs.category_head_id as parent_id FROM category_sub cs LEFT JOIN category_head ch ON cs.category_head_id = ch.id ORDER BY cs.category_head_id, cs.name");
    $subcategories = $stmt->fetchAll();
    
    // Déterminer la catégorie principale du composant actuel
    $component_category_head = null;
    if ($component['category']) {
        foreach ($subcategories as $subcat) {
            if ($subcat['id'] == $component['category']) {
                $component_category_head = $subcat['parent_id'];
                break;
            }
        }
    }
    
    // Récupérer les emplacements de l'utilisateur avec le nombre de composants
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
    
    // Récupérer les fournisseurs
    $stmt = $pdo->query("SELECT * FROM suppliers ORDER BY name");
    $suppliers = $stmt->fetchAll();
    
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
    <title>Modifier le Composant</title>
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
            text-align: center;
        }

        .content {
            padding: 30px;
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
            font-weight: bold;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            display: inline-block;
            margin-right: 10px;
        }

        .btn-primary {
            background: #4CAF50;
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

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        .back-link {
            margin-bottom: 20px;
        }
        
        /* Styles pour les emplacements */
        .location-available {
            background-color: #d4edda !important;
            color: #155724 !important;
        }
        
        .location-occupied {
            background-color: #f8d7da !important;
            color: #721c24 !important;
        }
        
        /* Styles pour l'autocomplétion */
        .autocomplete-suggestions {
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            width: 100%;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .suggestion-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        
        .suggestion-item:hover {
            background-color: #f5f5f5;
        }
        
        .suggestion-item:last-child {
            border-bottom: none;
        }
        
        /* Styles pour les images existantes */
        .existing-images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .existing-image-item {
            border: 2px solid #ddd;
            border-radius: 5px;
            padding: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .existing-image-item:hover {
            border-color: #007bff;
        }
        
        .existing-image-item.selected {
            border-color: #28a745;
            background-color: #f8f9fa;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✏️ Modifier le Composant</h1>
            <p>Modification de: <?php echo htmlspecialchars($component['name']); ?></p>
        </div>

        <div class="content">
            <div class="back-link">
                <a href="components.php" class="btn btn-secondary">← Retour à la liste</a>
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
                    <div class="form-group">
                        <label for="manufacturer">Fabricant</label>
                        <input type="text" id="manufacturer" name="manufacturer" value="<?php echo htmlspecialchars($component['manufacturer'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
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
                        <img id="preview_img" src="" alt="Aperçu" style="max-width: 150px; max-height: 150px; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                </div>
                <div class="form-group">
                    <label for="comment">Commentaire</label>
                    <textarea id="comment" name="comment" rows="3"><?php echo htmlspecialchars($component['comment'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">💾 Sauvegarder les modifications</button>
                <a href="components.php" class="btn btn-secondary">Annuler</a>
            </form>
        </div>
    </div>

    <script>
        // Stocker toutes les sous-catégories au chargement
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
            
            // Réinitialiser la sous-catégorie
            category.innerHTML = '<option value="">Sélectionner une sous-catégorie</option>';
            
            if (selectedParentId) {
                // Afficher seulement les sous-catégories de la catégorie sélectionnée
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
        
        // Initialiser les sous-catégories au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            // Sélectionner automatiquement la catégorie principale si une sous-catégorie est déjà sélectionnée
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
             
             // Désactiver la validation pour le champ URL quand il n'est pas utilisé
             if (imageType === 'url') {
                 urlGroup.style.display = 'block';
                 imageUrlInput.removeAttribute('disabled');
                 imageUrlInput.type = 'url';
             } else {
                 imageUrlInput.setAttribute('disabled', 'disabled');
                 imageUrlInput.type = 'text'; // Changer le type pour éviter la validation URL
                 if (imageType === 'upload') {
                     uploadGroup.style.display = 'block';
                 } else if (imageType === 'existing') {
                     existingGroup.style.display = 'block';
                     loadSubcategoryImages();
                 }
             }
         }
         
         // Fonction pour charger les images de la sous-catégorie
         function loadSubcategoryImages() {
             const categorySelect = document.getElementById('category');
             const subcategoryId = categorySelect.value;
             const container = document.getElementById('existing-images-container');
             
             if (!subcategoryId) {
                 container.innerHTML = '<p>Veuillez d\'abord sélectionner une sous-catégorie.</p>';
                 return;
             }
             
             fetch('get_subcategory_images.php?subcategory_id=' + subcategoryId)
                 .then(response => response.json())
                 .then(data => {
                     displayExistingImages(data);
                 })
                 .catch(error => {
                     console.error('Erreur:', error);
                     container.innerHTML = '<p>Erreur lors du chargement des images.</p>';
                 });
         }
         
         // Fonction pour afficher les images existantes
         function displayExistingImages(images) {
             const container = document.getElementById('existing-images-container');
             
             if (images.length === 0) {
                 container.innerHTML = '<p>Aucune image trouvée pour cette sous-catégorie.</p>';
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
         
         // Fonction pour sélectionner une image existante
         function selectExistingImage(imagePath, element) {
             // Retirer la sélection précédente
             document.querySelectorAll('.existing-image-item').forEach(item => {
                 item.classList.remove('selected');
             });
             
             // Ajouter la sélection à l'élément cliqué
             element.classList.add('selected');
             
             // Mettre à jour le champ caché
             document.getElementById('selected_existing_image').value = imagePath;
         }
         
         // Configuration de l'autocomplétion pour les packages
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
                         if (data.length > 0) {
                             let html = '';
                             data.forEach(packageName => {
                                 html += `<div class="suggestion-item" onclick="selectPackage('${packageName}')">${packageName}</div>`;
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
             
             // Cacher les suggestions quand on clique ailleurs
             document.addEventListener('click', function(e) {
                 if (!packageInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
                     suggestionsDiv.style.display = 'none';
                 }
             });
         }
         
         // Fonction pour sélectionner un package
         function selectPackage(packageName) {
             document.getElementById('package').value = packageName;
             document.getElementById('package-suggestions').style.display = 'none';
             checkAndSetPackageImage(packageName);
         }
         
         // Fonction pour vérifier et définir automatiquement l'image du package
         function checkAndSetPackageImage(packageName) {
             // Vérifier si une image existe pour ce package
             const imageExtensions = ['png', 'jpg', 'jpeg', 'svg'];
             let imageFound = false;
             
             // Fonction pour tester chaque extension
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
                         // Définir automatiquement l'image trouvée
                         document.getElementById('image_type').value = 'url';
                         toggleImageInput();
                         document.getElementById('image_url').value = imagePath;
                         
                         // S'assurer que le champ URL est activé mais garder le type text pour éviter la validation
                         const imageUrlInput = document.getElementById('image_url');
                         imageUrlInput.removeAttribute('disabled');
                         imageUrlInput.type = 'text'; // Utiliser 'text' car c'est un chemin relatif, pas une URL complète
                         
                         // Afficher l'aperçu de l'image
                         updateImagePreview();
                         
                         // Afficher un message informatif
                         const imageUrlGroup = document.getElementById('image_url_group');
                         if (!imageUrlGroup.querySelector('.auto-image-notice')) {
                             const notice = document.createElement('div');
                             notice.className = 'auto-image-notice';
                             notice.style.cssText = 'color: #28a745; font-size: 12px; margin-top: 5px;';
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
             
             // Commencer le test avec la première extension
             testImageExtension(0);
         }
         
         // Initialiser l'autocomplétion au chargement de la page
         document.addEventListener('DOMContentLoaded', function() {
             setupPackageAutocomplete();
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

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>