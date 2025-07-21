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
            case 'add':
                $name = trim($_POST['name']);
                if (!empty($name)) {
                    try {
                        // Insérer le nouveau fabricant dans la table manufacturers
                        $stmt = $pdo->prepare("INSERT INTO manufacturers (name, owner) VALUES (?, ?)");
                        $result = $stmt->execute([$name, $_SESSION['user_id']]);
                        if ($result) {
                            $success = "Fabricant ajouté avec succès.";
                        } else {
                            $error = "Erreur lors de l'ajout du fabricant.";
                        }
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) { // Violation de contrainte unique
                            $error = "Ce fabricant existe déjà.";
                        } else {
                            $error = "Erreur lors de l'ajout du fabricant.";
                        }
                    }
                } else {
                    $error = "Le nom du fabricant est obligatoire.";
                }
                break;
                
            case 'update':
                $old_name = trim($_POST['old_name']);
                $new_name = trim($_POST['new_name']);
                if (!empty($old_name) && !empty($new_name)) {
                    try {
                        $pdo->beginTransaction();
                        
                        // Mettre à jour le fabricant dans la table manufacturers
                        $stmt = $pdo->prepare("UPDATE manufacturers SET name = ? WHERE name = ? AND owner = ?");
                        $result1 = $stmt->execute([$new_name, $old_name, $_SESSION['user_id']]);
                        
                        // Mettre à jour les références dans la table data
                        $stmt = $pdo->prepare("UPDATE data SET manufacturer = ? WHERE manufacturer = ? AND owner = ?");
                        $result2 = $stmt->execute([$new_name, $old_name, $_SESSION['user_id']]);
                        
                        if ($result1) {
                            $pdo->commit();
                            $success = "Fabricant modifié avec succès.";
                        } else {
                            $pdo->rollback();
                            $error = "Erreur lors de la modification du fabricant.";
                        }
                    } catch (PDOException $e) {
                        $pdo->rollback();
                        if ($e->getCode() == 23000) {
                            $error = "Un fabricant avec ce nom existe déjà.";
                        } else {
                            $error = "Erreur lors de la modification du fabricant.";
                        }
                    }
                } else {
                    $error = "Les noms de fabricant sont obligatoires.";
                }
                break;
            
            case 'delete':
                $name = trim($_POST['name']);
                if (!empty($name)) {
                    try {
                        $pdo->beginTransaction();
                        
                        // Supprimer les références dans la table data
                        $stmt = $pdo->prepare("UPDATE data SET manufacturer = NULL WHERE manufacturer = ? AND owner = ?");
                        $stmt->execute([$name, $_SESSION['user_id']]);
                        
                        // Supprimer le fabricant de la table manufacturers
                        $stmt = $pdo->prepare("DELETE FROM manufacturers WHERE name = ? AND owner = ?");
                        $result = $stmt->execute([$name, $_SESSION['user_id']]);
                        
                        if ($result) {
                            $pdo->commit();
                            $success = "Fabricant supprimé avec succès.";
                        } else {
                            $pdo->rollback();
                            $error = "Erreur lors de la suppression du fabricant.";
                        }
                    } catch (PDOException $e) {
                        $pdo->rollback();
                        $error = "Erreur lors de la suppression du fabricant.";
                    }
                } else {
                    $error = "Le nom du fabricant est obligatoire.";
                }
                break;
        }
    }
}

// Récupérer tous les fabricants depuis la table manufacturers avec le nombre de composants
$stmt = $pdo->prepare("
    SELECT m.id, m.name, m.created_at, COUNT(d.id) as component_count 
    FROM manufacturers m 
    LEFT JOIN data d ON m.name = d.manufacturer AND d.owner = m.owner
    WHERE m.owner = ? 
    GROUP BY m.id, m.name, m.created_at
    ORDER BY m.name ASC
");
$stmt->execute([$_SESSION['user_id']]);
$manufacturers = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Fabricants</title>
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
            max-width: 1200px;
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
            border-radius: 5px;
            margin-bottom: 20px;
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

        .form-container {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            border: 1px solid #e9ecef;
        }

        .form-container h3 {
            color: #495057;
            margin-bottom: 20px;
            font-size: 1.3em;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .btn {
            padding: 10px 20px;
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

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .manufacturers-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .manufacturers-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: bold;
        }

        .manufacturers-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .manufacturers-table tr:hover {
            background: #f8f9fa;
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 10px;
            width: 80%;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
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
            <h1>🏭 Gestion des Fabricants</h1>
            
            <div class="nav-section">
                <div class="nav-buttons">
                    <a href="components.php">📦 Composants</a>
                    <a href="create_component.php">➕ Créer</a>
                    <a href="settings.php">⚙️ Paramètres</a>
                </div>
            </div>
        </div>

        <div class="content">
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Statistiques -->
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($manufacturers); ?></div>
                    <div class="stat-label">Fabricants uniques</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo array_sum(array_column($manufacturers, 'component_count')); ?></div>
                    <div class="stat-label">Composants avec fabricant</div>
                </div>
            </div>

            <!-- Formulaire d'ajout -->
            <div class="form-container">
                <h3>➕ Ajouter un nouveau fabricant</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label for="name">Nom du fabricant *</label>
                        <input type="text" id="name" name="name" required placeholder="Ex: Arduino, Texas Instruments, STMicroelectronics">
                    </div>
                    <button type="submit" class="btn btn-primary">💾 Ajouter le fabricant</button>
                </form>
            </div>

            <!-- Liste des fabricants -->
            <div class="manufacturers-list">
                <h3>📋 Liste des fabricants</h3>
                <?php if (empty($manufacturers)): ?>
                    <p>Aucun fabricant trouvé. Ajoutez des composants avec des fabricants pour les voir apparaître ici.</p>
                <?php else: ?>
                    <table class="manufacturers-table">
                        <thead>
                            <tr>
                                <th>Nom du fabricant</th>
                                <th>Nombre de composants</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($manufacturers as $manufacturer): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($manufacturer['name']); ?></td>
                                    <td><?php echo $manufacturer['component_count']; ?></td>
                                    <td>
                                        <button class="btn btn-warning" onclick="editManufacturer('<?php echo htmlspecialchars($manufacturer['name']); ?>')">✏️ Modifier</button>
                                        <button class="btn btn-danger" onclick="deleteManufacturer('<?php echo htmlspecialchars($manufacturer['name']); ?>')">🗑️ Supprimer</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal de modification -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3>✏️ Modifier le fabricant</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" id="old_name" name="old_name">
                <div class="form-group">
                    <label for="new_name">Nouveau nom *</label>
                    <input type="text" id="new_name" name="new_name" required>
                </div>
                <button type="submit" class="btn btn-primary">💾 Sauvegarder</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
            </form>
        </div>
    </div>

    <!-- Modal de suppression -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3>🗑️ Supprimer le fabricant</h3>
            <p>Êtes-vous sûr de vouloir supprimer ce fabricant ? Cette action supprimera le fabricant de tous les composants associés.</p>
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" id="delete_name" name="name">
                <button type="submit" class="btn btn-danger">🗑️ Confirmer la suppression</button>
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
            </form>
        </div>
    </div>

    <script>
        function editManufacturer(name) {
            document.getElementById('old_name').value = name;
            document.getElementById('new_name').value = name;
            document.getElementById('editModal').style.display = 'block';
        }

        function deleteManufacturer(name) {
            document.getElementById('delete_name').value = name;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Fermer les modals en cliquant à l'extérieur
        window.onclick = function(event) {
            const editModal = document.getElementById('editModal');
            const deleteModal = document.getElementById('deleteModal');
            if (event.target === editModal) {
                editModal.style.display = 'none';
            }
            if (event.target === deleteModal) {
                deleteModal.style.display = 'none';
            }
        }
    </script>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>