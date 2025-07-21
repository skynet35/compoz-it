<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$pdo = getConnection();

$error_message = '';
$location_id = intval($_GET['id'] ?? 0);

if ($location_id <= 0) {
    header('Location: locations.php?error=invalid');
    exit();
}

// Récupérer l'emplacement
$stmt = $pdo->prepare("SELECT * FROM location WHERE id = ? AND owner = ?");
$stmt->execute([$location_id, $user_id]);
$location = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$location) {
    header('Location: locations.php?error=not_found');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $casier = trim($_POST['casier'] ?? '');
    $tiroir = trim($_POST['tiroir'] ?? '');
    $compartiment = trim($_POST['compartiment'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    // Validation
    if (empty($casier) || empty($tiroir) || empty($compartiment)) {
        $error_message = 'Tous les champs obligatoires doivent être remplis.';
    } else {
        try {
            // Vérifier si le nouvel emplacement existe déjà (sauf si c'est le même)
            $stmt = $pdo->prepare("SELECT id FROM location WHERE owner = ? AND casier = ? AND tiroir = ? AND compartiment = ? AND id != ?");
            $stmt->execute([$user_id, $casier, $tiroir, $compartiment, $location_id]);
            
            if ($stmt->fetch()) {
                $error_message = 'Un emplacement avec ces coordonnées existe déjà.';
            } else {
                // Mettre à jour l'emplacement
                $stmt = $pdo->prepare("UPDATE location SET casier = ?, tiroir = ?, compartiment = ?, description = ? WHERE id = ? AND owner = ?");
                $stmt->execute([$casier, $tiroir, $compartiment, $description, $location_id, $user_id]);
                
                header('Location: locations.php?success=updated');
                exit();
            }
        } catch (PDOException $e) {
            $error_message = 'Erreur lors de la modification de l\'emplacement.';
        }
    }
} else {
    // Pré-remplir le formulaire avec les données existantes
    $_POST['casier'] = $location['casier'];
    $_POST['tiroir'] = $location['tiroir'];
    $_POST['compartiment'] = $location['compartiment'];
    $_POST['description'] = $location['description'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier l'Emplacement</title>
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
            max-width: 600px;
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
            position: relative;
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
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

        .nav-buttons {
            margin: 20px 0;
        }

        .nav-buttons a {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 25px;
            margin: 0 10px;
            transition: all 0.3s ease;
        }

        .nav-buttons a:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        .content {
            padding: 30px;
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
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .required {
            color: #e74c3c;
        }

        .btn {
            display: inline-block;
            padding: 12px 25px;
            margin: 10px 5px;
            text-decoration: none;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: bold;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-actions {
            text-align: center;
            margin-top: 30px;
        }

        .info-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .info-box h3 {
            color: #856404;
            margin-bottom: 10px;
        }

        .info-box p {
            color: #856404;
            margin-bottom: 5px;
        }

        .current-location {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .current-location h3 {
            color: #1976d2;
            margin-bottom: 10px;
        }

        .location-code {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: bold;
            font-size: 1.2em;
            display: inline-block;
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
            <h1>✏️ Modifier l'Emplacement</h1>
            <div class="nav-buttons">
                <a href="components.php">📦 Composants</a>
                <a href="locations.php">🗂️ Emplacements</a>
            </div>
        </div>

        <div class="content">
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="current-location">
                <h3>📍 Emplacement actuel</h3>
                <div class="location-code">
                    <?php echo htmlspecialchars($location['casier'] . '-' . $location['tiroir'] . '-' . $location['compartiment']); ?>
                </div>
            </div>

            <div class="info-box">
                <h3>⚠️ Attention</h3>
                <p>La modification de cet emplacement affectera tous les composants qui y sont stockés.</p>
                <p>Assurez-vous que les nouvelles coordonnées sont correctes.</p>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="casier">Casier <span class="required">*</span></label>
                    <input type="text" id="casier" name="casier" required 
                           placeholder="Ex: A, B, C1, etc." 
                           value="<?php echo htmlspecialchars($_POST['casier'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="tiroir">Tiroir <span class="required">*</span></label>
                    <input type="text" id="tiroir" name="tiroir" required 
                           placeholder="Ex: 1, 2, T1, etc." 
                           value="<?php echo htmlspecialchars($_POST['tiroir'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="compartiment">Compartiment <span class="required">*</span></label>
                    <input type="text" id="compartiment" name="compartiment" required 
                           placeholder="Ex: 1, 2, 3, 4" 
                           value="<?php echo htmlspecialchars($_POST['compartiment'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="description">Description (optionnel)</label>
                    <textarea id="description" name="description" 
                              placeholder="Description de l'emplacement..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">💾 Modifier l'Emplacement</button>
                    <a href="locations.php" class="btn btn-secondary">❌ Annuler</a>
                </div>
            </form>
        </div>
    </div>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>