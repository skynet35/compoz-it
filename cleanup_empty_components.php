<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=not_logged_in');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

try {
    $pdo = getConnection();
    
    // Traitement de la suppression
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_empty'])) {
        $stmt = $pdo->prepare("DELETE FROM data WHERE (name IS NULL OR name = '' OR TRIM(name) = '') AND owner = ?");
        $stmt->execute([$user_id]);
        $deleted_count = $stmt->rowCount();
        
        $message = "✅ $deleted_count composant(s) vide(s) supprimé(s) avec succès.";
    }
    
    // Rechercher les composants vides
    $stmt = $pdo->prepare("SELECT id, name, manufacturer, package, quantity, created_at FROM data WHERE (name IS NULL OR name = '' OR TRIM(name) = '') AND owner = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $empty_components = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Erreur: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nettoyage des Composants Vides</title>
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
            max-width: 1000px;
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

        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: bold;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
            transition: transform 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .empty-name {
            color: #999;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧹 Nettoyage des Composants Vides</h1>
            <p>Suppression des composants sans nom</p>
        </div>
        
        <div class="content">
            <?php if ($message): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <a href="settings.php" class="btn">← Retour aux paramètres</a>
            
            <?php if (count($empty_components) > 0): ?>
                <div class="message warning">
                    ⚠️ <strong><?php echo count($empty_components); ?> composant(s) vide(s) trouvé(s)</strong><br>
                    Ces composants n'ont pas de nom et peuvent être supprimés en toute sécurité.
                </div>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nom</th>
                            <th>Fabricant</th>
                            <th>Package</th>
                            <th>Quantité</th>
                            <th>Créé le</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empty_components as $component): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($component['id']); ?></td>
                                <td class="empty-name"><?php echo empty($component['name']) ? '(vide)' : htmlspecialchars($component['name']); ?></td>
                                <td><?php echo htmlspecialchars($component['manufacturer'] ?: '(vide)'); ?></td>
                                <td><?php echo htmlspecialchars($component['package'] ?: '(vide)'); ?></td>
                                <td><?php echo htmlspecialchars($component['quantity']); ?></td>
                                <td><?php echo htmlspecialchars($component['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <form method="POST" style="margin-top: 20px;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer tous les composants vides ? Cette action est irréversible.')">
                    <button type="submit" name="delete_empty" class="btn btn-danger">
                        🗑️ Supprimer tous les composants vides (<?php echo count($empty_components); ?>)
                    </button>
                </form>
                
            <?php else: ?>
                <div class="message success">
                    ✅ <strong>Aucun composant vide trouvé</strong><br>
                    Votre base de données est propre !
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>