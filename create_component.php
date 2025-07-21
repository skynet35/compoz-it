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

// Récupérer les catégories pour le formulaire d'ajout
$stmt = $pdo->query("SELECT * FROM category_head ORDER BY name");
$categories = $stmt->fetchAll();

// Récupérer les sous-catégories
$stmt = $pdo->query("SELECT cs.*, ch.name as parent_name FROM category_sub cs LEFT JOIN category_head ch ON cs.category_head_id = ch.id ORDER BY cs.category_head_id, cs.name");
$subcategories = $stmt->fetchAll();

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

// Récupérer les fabricants de l'utilisateur
$stmt = $pdo->prepare("SELECT name FROM manufacturers WHERE owner = ? ORDER BY name");
$stmt->execute([$_SESSION['user_id']]);
$manufacturers = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Récupérer les fournisseurs
$stmt = $pdo->query("SELECT * FROM suppliers ORDER BY name");
$suppliers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un Composant</title>
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

        .form-container {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            border: 2px solid #dee2e6;
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

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 5px rgba(102, 126, 234, 0.3);
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

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
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
            border-radius: 0 0 5px 5px;
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
            border-color: #667eea;
        }
        
        .existing-image-item.selected {
            border-color: #667eea;
            background-color: #f0f4ff;
        }
        
        .existing-image-item img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 3px;
        }
        
        .existing-image-item .image-name {
            font-size: 10px;
            margin-top: 5px;
            color: #666;
            word-break: break-word;
        }
        
        .form-group {
            position: relative;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .user-info {
                position: static;
                text-align: center;
                margin-bottom: 15px;
            }
            
            .nav-buttons {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                👤 <?php echo htmlspecialchars($_SESSION['user_email']); ?>
                <a href="logout.php" style="margin-left: 15px; color: #dc3545; text-decoration: none; font-weight: bold;">🚪 Déconnexion</a>
            </div>
            <h1>➕ Créer un Composant</h1>
            
            <div class="nav-section">
                <div class="nav-buttons">
                    <a href="components.php">📦 Composants</a>
                    <a href="create_component.php" class="active">➕ Créer</a>
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
                        case 'database_error':
                            echo "❌ Erreur de base de données.";
                            break;
                        default:
                            echo "❌ Une erreur s'est produite.";
                    }
                    ?>
                </div>
            <?php endif; ?>

            <!-- Formulaire de création -->
            <div class="form-container">
                <h3>📝 Informations du composant</h3>
                <form action="add_component.php" method="POST" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Nom du composant *</label>
                            <input type="text" id="name" name="name" required placeholder="Ex: Arduino Uno R3">
                        </div>
                        <div class="form-group">
                            <label for="manufacturer">Fabricant</label>
                            <select id="manufacturer" name="manufacturer" onchange="toggleNewManufacturer()">
                                <option value="">Sélectionner un fabricant</option>
                                <?php foreach ($manufacturers as $manufacturer): ?>
                                    <option value="<?php echo htmlspecialchars($manufacturer); ?>">
                                        <?php echo htmlspecialchars($manufacturer); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="__new__">➕ Nouveau fabricant</option>
                            </select>
                        </div>
                        <div class="form-group" id="new_manufacturer_group" style="display: none;">
                            <label for="new_manufacturer">Nom du nouveau fabricant</label>
                            <input type="text" id="new_manufacturer" name="new_manufacturer" placeholder="Ex: Arduino">
                        </div>
                        <div class="form-group">
                            <label for="package">Package</label>
                            <input type="text" id="package" name="package" placeholder="Ex: DIP-28, SOIC-8" autocomplete="off">
                            <div id="package-suggestions" class="autocomplete-suggestions"></div>
                        </div>
                        <div class="form-group">
                            <label for="pins">Nombre de pins</label>
                            <input type="number" id="pins" name="pins" placeholder="Ex: 14">
                        </div>
                        <div class="form-group">
                            <label for="smd">SMD</label>
                            <select id="smd" name="smd">
                                <option value="No">Non</option>
                                <option value="Yes">Oui</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="quantity">Quantité *</label>
                            <input type="number" id="quantity" name="quantity" value="1" required min="1">
                        </div>
                        <div class="form-group">
                            <label for="order_quantity">Quantité à commander</label>
                            <input type="number" id="order_quantity" name="order_quantity" value="0" min="0">
                        </div>
                        <div class="form-group">
                            <label for="price">Prix (€)</label>
                            <input type="number" id="price" name="price" step="0.01" min="0" placeholder="Ex: 2.50">
                        </div>
                        <div class="form-group">
                            <label for="supplier_id">Fournisseur</label>
                            <select id="supplier_id" name="supplier_id">
                                <option value="">Sélectionner un fournisseur</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>">
                                        <?php echo htmlspecialchars($supplier['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
                                    <option value="<?php echo $location['id']; ?>" class="<?php echo $statusClass; ?>">
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
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="category">Sous-catégorie</label>
                            <select id="category" name="category" onchange="loadSubcategoryImages()">
                                <option value="">Sélectionner d'abord une catégorie principale</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="supplier_reference">Référence fournisseur</label>
                            <input type="text" id="supplier_reference" name="supplier_reference" placeholder="Ex: REF-12345">
                        </div>
                    </div>
                    <div class="form-group">
                            <label for="datasheet">Datasheet (URL)</label>
                            <input type="url" id="datasheet" name="datasheet" placeholder="https://exemple.com/datasheet.pdf">
                        </div>

                        <div class="form-group">
                            <label for="public">
                                <input type="checkbox" id="public" name="public" value="1">
                                Composant public
                            </label>
                        </div>
                    <div class="form-group">
                        <label for="image_type">Type d'image</label>
                        <select id="image_type" name="image_type" onchange="toggleImageInput()">
                            <option value="">Aucune image</option>
                            <option value="existing">Utiliser une image existante</option>
                            <option value="upload">Importer une image</option>
                            <option value="url">Lien vers une image</option>
                        </select>
                    </div>
                    <div class="form-group" id="existing_images_group" style="display: none;">
                        <label>Images disponibles</label>
                        <div id="existing-images-container" class="existing-images-grid"></div>
                        <input type="hidden" id="selected_existing_image" name="selected_existing_image">
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
                        <textarea id="comment" name="comment" rows="3" placeholder="Informations supplémentaires sur le composant..."></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">✅ Créer le composant</button>
                        <a href="components.php" class="btn btn-secondary">↩️ Retour à la liste</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Variables globales
    let packageTimeout;
    let currentPackages = [];
    
    function toggleNewManufacturer() {
        const manufacturerSelect = document.getElementById('manufacturer');
        const newManufacturerGroup = document.getElementById('new_manufacturer_group');
        
        if (manufacturerSelect.value === '__new__') {
            newManufacturerGroup.style.display = 'block';
        } else {
            newManufacturerGroup.style.display = 'none';
            document.getElementById('new_manufacturer').value = '';
        }
    }
    
    function updateSubcategories() {
        const categoryHead = document.getElementById('category_head').value;
        const subcategorySelect = document.getElementById('category');
        
        // Vider les sous-catégories
        subcategorySelect.innerHTML = '<option value="">Chargement...</option>';
        
        if (categoryHead === '') {
            subcategorySelect.innerHTML = '<option value="">Sélectionner d\'abord une catégorie principale</option>';
            return;
        }
        
        // Récupérer les sous-catégories via AJAX
        fetch('get_subcategories.php?parent_id=' + categoryHead)
            .then(response => response.json())
            .then(data => {
                subcategorySelect.innerHTML = '<option value="">Sélectionner une sous-catégorie</option>';
                data.forEach(subcat => {
                    const option = document.createElement('option');
                    option.value = subcat.id;
                    option.textContent = subcat.name;
                    subcategorySelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Erreur:', error);
                subcategorySelect.innerHTML = '<option value="">Erreur de chargement</option>';
            });
    }
    
    function loadSubcategoryImages() {
        const imageType = document.getElementById('image_type').value;
        
        if (imageType === 'existing') {
            fetch('get_subcategory_images.php')
                .then(response => response.json())
                .then(data => {
                    displayExistingImages(data);
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des images:', error);
                    const container = document.getElementById('existing-images-container');
                    container.innerHTML = '<p style="color: #dc3545;">Erreur lors du chargement des images</p>';
                });
        }
    }
    
    function displayExistingImages(images) {
        const container = document.getElementById('existing-images-container');
        
        if (images.length === 0) {
            container.innerHTML = '<p style="color: #666; font-style: italic;">Aucune image disponible</p>';
            return;
        }
        
        let html = '';
        images.forEach(image => {
            const imageName = image.image_path.split('/').pop();
            html += `
                <div class="existing-image-item" onclick="selectExistingImage('${image.image_path}', this)">
                    <img src="${image.image_path}" alt="${imageName}" onerror="this.src='img/placeholder.png'">
                    <div class="image-name">${imageName}</div>
                </div>
            `;
        });
        
        container.innerHTML = html;
    }
    
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
    
    function toggleImageInput() {
        const imageType = document.getElementById('image_type').value;
        const existingGroup = document.getElementById('existing_images_group');
        const uploadGroup = document.getElementById('image_upload_group');
        const urlGroup = document.getElementById('image_url_group');
        const imageUrlInput = document.getElementById('image_url');
        
        existingGroup.style.display = 'none';
        uploadGroup.style.display = 'none';
        urlGroup.style.display = 'none';
        
        // Désactiver la validation pour le champ URL quand il n'est pas utilisé
         if (imageType === 'url') {
             urlGroup.style.display = 'block';
             imageUrlInput.removeAttribute('disabled');
             imageUrlInput.type = 'url';
         } else {
             imageUrlInput.setAttribute('disabled', 'disabled');
             imageUrlInput.type = 'text'; // Changer le type pour éviter la validation URL
             if (imageType === 'existing') {
                 existingGroup.style.display = 'block';
                 loadSubcategoryImages();
             } else if (imageType === 'upload') {
                 uploadGroup.style.display = 'block';
             }
         }
    }
    
    // Autocomplétion pour les packages
    function setupPackageAutocomplete() {
        const packageInput = document.getElementById('package');
        const suggestionsDiv = document.getElementById('package-suggestions');
        
        packageInput.addEventListener('input', function() {
            clearTimeout(packageTimeout);
            const query = this.value.trim();
            
            if (query.length === 0) {
                suggestionsDiv.innerHTML = '';
                suggestionsDiv.style.display = 'none';
                return;
            }
            
            packageTimeout = setTimeout(() => {
                fetch('get_packages.php?search=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(packages => {
                        displayPackageSuggestions(packages, suggestionsDiv, packageInput);
                    })
                    .catch(error => {
                        console.error('Erreur lors de la récupération des packages:', error);
                    });
            }, 300);
        });
        
        // Cacher les suggestions quand on clique ailleurs
        document.addEventListener('click', function(e) {
            if (!packageInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
                suggestionsDiv.style.display = 'none';
            }
        });
    }
    
    function displayPackageSuggestions(packages, suggestionsDiv, inputElement) {
        suggestionsDiv.innerHTML = '';
        
        if (packages.length === 0) {
            suggestionsDiv.style.display = 'none';
            return;
        }
        
        packages.forEach(packageName => {
            const suggestionItem = document.createElement('div');
            suggestionItem.className = 'suggestion-item';
            suggestionItem.textContent = packageName;
            suggestionItem.onclick = () => {
                inputElement.value = packageName;
                suggestionsDiv.style.display = 'none';
                checkAndSetPackageImage(packageName);
            };
            suggestionsDiv.appendChild(suggestionItem);
        });
        
        suggestionsDiv.style.display = 'block';
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
    
    // Fonction pour mettre à jour l'aperçu de l'image
    function updateImagePreview() {
        const imageUrl = document.getElementById('image_url').value;
        const previewDiv = document.getElementById('image_preview');
        const previewImg = document.getElementById('preview_img');
        
        if (imageUrl.trim() !== '') {
            previewImg.src = imageUrl;
            previewDiv.style.display = 'block';
            
            // Gérer les erreurs de chargement d'image
            previewImg.onerror = function() {
                previewDiv.style.display = 'none';
            };
        } else {
            previewDiv.style.display = 'none';
        }
    }
    
    // Initialiser l'autocomplétion au chargement de la page
    document.addEventListener('DOMContentLoaded', function() {
        setupPackageAutocomplete();
    });
    </script>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>