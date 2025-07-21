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

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_project':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $status = $_POST['status'] ?? 'En cours';
                
                if (!empty($name)) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO projects (owner, name, description, status) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$_SESSION['user_id'], $name, $description, $status]);
                        header('Location: projects.php?success=project_created');
                        exit();
                    } catch (PDOException $e) {
                        $error = "Erreur lors de la création du projet : " . $e->getMessage();
                    }
                } else {
                    $error = "Le nom du projet est obligatoire.";
                }
                break;
                
            case 'delete_project':
                $project_id = (int)($_POST['project_id'] ?? 0);
                if ($project_id > 0) {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ? AND owner = ?");
                        $stmt->execute([$project_id, $_SESSION['user_id']]);
                        header('Location: projects.php?success=project_deleted');
                        exit();
                    } catch (PDOException $e) {
                        $error = "Erreur lors de la suppression : " . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Récupérer les projets de l'utilisateur
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               COUNT(pc.id) as component_count,
               SUM(pc.quantity_needed) as total_components_needed,
               SUM(pc.quantity_used) as total_components_used,
               p.image_path
        FROM projects p 
        LEFT JOIN project_components pc ON p.id = pc.project_id 
        WHERE p.owner = ? 
        GROUP BY p.id 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $projects = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Erreur lors de la récupération des projets : " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Projets</title>
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

        .logout-btn {
            margin-left: 15px;
            color: #dc3545;
            text-decoration: none;
            font-weight: bold;
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

        .content {
            padding: 30px;
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

        .create-project-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
            transform: translateY(-2px);
        }

        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .project-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
            position: relative;
            overflow: hidden;
        }

        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .project-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 15px;
            border: 1px solid #e9ecef;
        }

        .project-image-placeholder {
            width: 100%;
            height: 150px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 3em;
            border: 1px solid #e9ecef;
        }

        .project-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .project-title {
            font-size: 1.4em;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .project-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-en-cours {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-termine {
            background: #d4edda;
            color: #155724;
        }

        .status-en-attente {
            background: #fff3cd;
            color: #856404;
        }

        .status-annule {
            background: #f8d7da;
            color: #721c24;
        }

        .project-description {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .project-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .stat-number {
            font-size: 1.8em;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .project-actions {
            display: flex;
            gap: 10px;
            justify-content: space-between;
        }

        .no-projects {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .no-projects h3 {
            font-size: 1.5em;
            margin-bottom: 15px;
        }

        /* Styles pour la modal d'images */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .image-modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 80%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close-modal:hover {
            color: black;
        }

        .upload-section {
            margin: 20px 0;
            padding: 20px;
            border: 2px dashed #ddd;
            border-radius: 10px;
            text-align: center;
        }

        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .image-option {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .image-option:hover {
            border-color: #667eea;
            transform: scale(1.05);
        }

        .image-option.selected {
            border-color: #667eea;
            background-color: #f0f4ff;
        }

        .image-option img {
            width: 100%;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
        }

        .image-option-name {
            font-size: 12px;
            margin-top: 5px;
            color: #666;
        }

        .project-image-clickable {
            cursor: pointer;
            transition: opacity 0.3s ease;
        }

        .project-image-clickable:hover {
            opacity: 0.8;
        }

        @media (max-width: 768px) {
            .projects-grid {
                grid-template-columns: 1fr;
            }
            
            .project-actions {
                flex-direction: column;
            }
            
            .image-modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                <strong>👤</strong> <?php echo htmlspecialchars($_SESSION['user_email']); ?>
                <a href="logout.php" class="logout-btn">🚪 Déconnexion</a>
            </div>
            <h1>🚀 Gestion des Projets</h1>
            <div class="nav-section">
                <div class="nav-buttons">
                    <a href="components.php">📦 Composants</a>
                    <a href="create_component.php">➕ Créer</a>
                    <a href="projects.php" class="active">🚀 Projets</a>
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
                        case 'project_created':
                            echo "✅ Projet créé avec succès !";
                            break;
                        case 'project_deleted':
                            echo "✅ Projet supprimé avec succès !";
                            break;
                        case 'component_added':
                            echo "✅ Composant ajouté au projet !";
                            break;
                        default:
                            echo "✅ Opération réussie !";
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Formulaire de création de projet -->
            <div class="create-project-section">
                <h2 style="margin-bottom: 20px; color: #333;">➕ Créer un nouveau projet</h2>
                <form method="POST" action="projects.php">
                    <input type="hidden" name="action" value="create_project">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 200px; gap: 20px; align-items: end;">
                        <div class="form-group">
                            <label for="name">Nom du projet *</label>
                            <input type="text" id="name" name="name" required placeholder="Ex: Robot autonome">
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <input type="text" id="description" name="description" placeholder="Description courte du projet">
                        </div>
                        <div class="form-group">
                            <label for="status">Statut</label>
                            <select id="status" name="status">
                                <option value="En cours">En cours</option>
                                <option value="En attente">En attente</option>
                                <option value="Terminé">Terminé</option>
                                <option value="Annulé">Annulé</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top: 15px;">🚀 Créer le projet</button>
                </form>
            </div>

            <!-- Liste des projets -->
            <?php if (empty($projects)): ?>
                <div class="no-projects">
                    <h3>Aucun projet trouvé</h3>
                    <p>Créez votre premier projet pour commencer à organiser vos composants !</p>
                </div>
            <?php else: ?>
                <div class="projects-grid">
                    <?php foreach ($projects as $project): ?>
                        <div class="project-card">
                            <!-- Image du projet -->
                            <?php if (!empty($project['image_path']) && file_exists($project['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($project['image_path']); ?>" alt="Image du projet" class="project-image project-image-clickable" onclick="openImageModal(<?php echo $project['id']; ?>)" title="Cliquer pour changer l'image">
                            <?php else: ?>
                                <div class="project-image-placeholder project-image-clickable" onclick="openImageModal(<?php echo $project['id']; ?>)" title="Cliquer pour ajouter une image">
                                    🚀
                                </div>
                            <?php endif; ?>
                            
                            <div class="project-header">
                                <div>
                                    <div class="project-title"><?php echo htmlspecialchars($project['name']); ?></div>
                                    <small style="color: #999;">Créé le <?php echo date('d/m/Y', strtotime($project['created_at'])); ?></small>
                                </div>
                                <span class="project-status status-<?php echo strtolower(str_replace(' ', '-', $project['status'])); ?>">
                                    <?php echo htmlspecialchars($project['status']); ?>
                                </span>
                            </div>
                            
                            <?php if ($project['description']): ?>
                                <div class="project-description">
                                    <?php echo htmlspecialchars($project['description']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="project-stats">
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $project['component_count']; ?></div>
                                    <div class="stat-label">Composants</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $project['total_components_needed'] ?? 0; ?></div>
                                    <div class="stat-label">Quantité totale</div>
                                </div>
                            </div>
                            
                            <div class="project-actions">
                                <a href="project_detail.php?id=<?php echo $project['id']; ?>" class="btn btn-info">👁️ Voir détails</a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce projet ?')">
                                    <input type="hidden" name="action" value="delete_project">
                                    <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                    <button type="submit" class="btn btn-danger">🗑️ Supprimer</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de sélection d'images -->
    <div id="imageModal" class="image-modal">
        <div class="image-modal-content">
            <span class="close-modal" onclick="closeImageModal()">&times;</span>
            <h3>Choisir une image pour le projet</h3>
            <p>Sélectionnez une image du dossier /img ou importez une nouvelle image :</p>
            
            <div class="upload-section">
                <h4>📤 Importer une nouvelle image</h4>
                <input type="file" id="imageUpload" accept="image/*" onchange="uploadImage()">
                <p style="margin-top: 10px; font-size: 14px; color: #666;">Formats acceptés: JPG, PNG, GIF, SVG, WebP (max 5MB)</p>
            </div>
            
            <div>
                <h4>🖼️ Images existantes</h4>
                <div id="imageGrid" class="image-grid">
                    <!-- Les images seront chargées ici par JavaScript -->
                </div>
            </div>
            
            <div style="margin-top: 20px; text-align: center;">
                <button onclick="removeProjectImage()" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-right: 10px;">🗑️ Supprimer l'image</button>
                <button onclick="closeImageModal()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">Annuler</button>
            </div>
        </div>
    </div>

    <script>
        let currentProjectId = null;
        let selectedImagePath = null;

        function openImageModal(projectId) {
            currentProjectId = projectId;
            document.getElementById('imageModal').style.display = 'block';
            loadImages();
        }

        function closeImageModal() {
            document.getElementById('imageModal').style.display = 'none';
            currentProjectId = null;
            selectedImagePath = null;
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
            });
        }

        function selectImage(imagePath) {
            // Désélectionner toutes les images
            document.querySelectorAll('.image-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Sélectionner l'image cliquée
            event.target.closest('.image-option').classList.add('selected');
            selectedImagePath = imagePath;
            
            // Mettre à jour l'image du projet
            updateProjectImage(imagePath);
        }

        function updateProjectImage(imagePath) {
            if (!currentProjectId) return;
            
            fetch('update_project_image.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'project_id=' + currentProjectId + '&image_path=' + encodeURIComponent(imagePath)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Recharger la page pour voir les changements
                    location.reload();
                } else {
                    alert('Erreur lors de la mise à jour de l\'image: ' + (data.error || 'Erreur inconnue'));
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de la mise à jour de l\'image');
            });
        }

        function uploadImage() {
            const fileInput = document.getElementById('imageUpload');
            const file = fileInput.files[0];
            
            if (!file) return;
            
            const formData = new FormData();
            formData.append('image', file);
            
            fetch('upload_image.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mettre à jour l'image du projet avec la nouvelle image
                    updateProjectImage(data.image_path);
                } else {
                    alert('Erreur lors de l\'upload: ' + (data.error || 'Erreur inconnue'));
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de l\'upload de l\'image');
            });
        }

        function removeProjectImage() {
            if (!currentProjectId) return;
            
            if (confirm('Êtes-vous sûr de vouloir supprimer l\'image de ce projet ?')) {
                fetch('update_project_image.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'project_id=' + currentProjectId + '&image_path='
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erreur lors de la suppression de l\'image: ' + (data.error || 'Erreur inconnue'));
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur lors de la suppression de l\'image');
                });
            }
        }

        // Fermer la modal en cliquant en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('imageModal');
            if (event.target === modal) {
                closeImageModal();
            }
        }
    </script>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>