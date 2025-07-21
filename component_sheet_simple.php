<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

// Vérifier l'authentification
if (!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$component_id = $_GET['id'] ?? null;

if (!$component_id) {
    echo "ID du composant manquant";
    exit();
}

try {
    $pdo = getConnection();
    
    // Récupérer le composant
    $stmt = $pdo->prepare("SELECT * FROM data WHERE id = ? AND owner = ?");
    $stmt->execute([$component_id, $_SESSION['user_id']]);
    $component = $stmt->fetch();
    
    if (!$component) {
        echo "Composant non trouvé ou accès refusé";
        exit();
    }
    
} catch(PDOException $e) {
    echo "Erreur de base de données : " . $e->getMessage();
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche Produit Simple - <?php echo htmlspecialchars($component['name']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 800px;
            margin: 0 auto;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px 5px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .info-row {
            margin: 10px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="success">
            ✅ <strong>Succès !</strong> La fiche produit fonctionne maintenant !
        </div>
        
        <h1>📋 Fiche Produit Simple</h1>
        
        <div class="info-row">
            <strong>ID :</strong> <?php echo $component['id']; ?>
        </div>
        
        <div class="info-row">
            <strong>Nom :</strong> <?php echo htmlspecialchars($component['name']); ?>
        </div>
        
        <div class="info-row">
            <strong>Fabricant :</strong> <?php echo htmlspecialchars($component['manufacturer'] ?? 'Non défini'); ?>
        </div>
        
        <div class="info-row">
            <strong>Package :</strong> <?php echo htmlspecialchars($component['package'] ?? 'Non défini'); ?>
        </div>
        
        <div class="info-row">
            <strong>Pins :</strong> <?php echo $component['pins'] ?? 'Non défini'; ?>
        </div>
        
        <div class="info-row">
            <strong>SMD :</strong> <?php echo $component['smd']; ?>
        </div>
        
        <div class="info-row">
            <strong>Quantité :</strong> <?php echo $component['quantity']; ?>
        </div>
        
        <div class="info-row">
            <strong>Prix :</strong> <?php echo $component['price'] ? number_format($component['price'], 2) . ' €' : 'Non défini'; ?>
        </div>
        
        <div class="info-row">
            <strong>Commentaire :</strong> <?php echo htmlspecialchars($component['comment'] ?? 'Aucun'); ?>
        </div>
        
        <hr style="margin: 30px 0;">
        
        <h3>Actions :</h3>
        <a href="edit_component.php?id=<?php echo $component['id']; ?>" class="btn btn-primary">✏️ Modifier</a>
        <a href="components.php" class="btn btn-secondary">← Retour à la liste</a>
        <button class="btn btn-danger" onclick="openDeleteModal(<?php echo $component['id']; ?>, '<?php echo htmlspecialchars($component['name'], ENT_QUOTES); ?>')">🗑️ Supprimer</button>
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
            const deleteModal = document.getElementById('deleteModal');
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }
    </script>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>