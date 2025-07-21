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
            // Vérifier si l'emplacement existe déjà
            $stmt = $pdo->prepare("SELECT id FROM location WHERE owner = ? AND casier = ? AND tiroir = ? AND compartiment = ?");
            $stmt->execute([$user_id, $casier, $tiroir, $compartiment]);
            
            if ($stmt->fetch()) {
                header('Location: locations.php?error=exists');
                exit();
            }
            
            // Insérer le nouvel emplacement
            $stmt = $pdo->prepare("INSERT INTO location (owner, casier, tiroir, compartiment, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $casier, $tiroir, $compartiment, $description]);
            
            header('Location: locations.php?success=added');
            exit();
        } catch (PDOException $e) {
            $error_message = 'Erreur lors de l\'ajout de l\'emplacement.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Emplacement</title>
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
        }

        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
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
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .info-box h3 {
            color: #1976d2;
            margin-bottom: 10px;
        }

        .info-box p {
            color: #424242;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>➕ Ajouter un Emplacement</h1>
            <div class="nav-buttons">
                <a href="components.php">📦 Composants</a>
                <a href="locations.php">🗂️ Emplacements</a>
                <a href="logout.php">🚪 Déconnexion</a>
            </div>
        </div>

        <div class="content">
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="info-box">
                <h3>ℹ️ Information sur les emplacements</h3>
                <p><strong>Casier:</strong> Identifiant du casier principal (ex: A, B, C1, etc.)</p>
                <p><strong>Tiroir:</strong> Numéro ou nom du tiroir dans le casier (ex: 1, 2, T1, etc.)</p>
                <p><strong>Compartiment:</strong> Numéro du compartiment dans le tiroir (ex: 1, 2, 3, 4)</p>
                <p><strong>Code final:</strong> Casier-Tiroir-Compartiment (ex: A-1-1, B-2-3)</p>
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
                    <button type="submit" class="btn btn-primary">💾 Ajouter l'Emplacement</button>
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