<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=not_logged_in');
    exit();
}

$message = '';
$error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $pdo = getConnection();
        
        if ($action === 'update_email') {
            $new_email = trim($_POST['new_email'] ?? '');
            
            if (empty($new_email)) {
                $error = 'Veuillez saisir un email';
            } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Email invalide';
            } else {
                // Vérifier si l'email n'est pas déjà utilisé
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$new_email, $_SESSION['user_id']]);
                
                if ($stmt->fetch()) {
                    $error = 'Cet email est déjà utilisé par un autre compte';
                } else {
                    // Mettre à jour l'email
                    $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
                    if ($stmt->execute([$new_email, $_SESSION['user_id']])) {
                        $_SESSION['user_email'] = $new_email;
                        $message = 'Email mis à jour avec succès';
                    } else {
                        $error = 'Erreur lors de la mise à jour de l\'email';
                    }
                }
            }
        } elseif ($action === 'update_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = 'Veuillez remplir tous les champs';
            } elseif (strlen($new_password) < 6) {
                $error = 'Le nouveau mot de passe doit contenir au moins 6 caractères';
            } elseif ($new_password !== $confirm_password) {
                $error = 'Les nouveaux mots de passe ne correspondent pas';
            } else {
                // Vérifier le mot de passe actuel
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                
                if (!$user || !password_verify($current_password, $user['password'])) {
                    $error = 'Mot de passe actuel incorrect';
                } else {
                    // Mettre à jour le mot de passe
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    if ($stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                        $message = 'Mot de passe mis à jour avec succès';
                    } else {
                        $error = 'Erreur lors de la mise à jour du mot de passe';
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $error = 'Erreur de base de données : ' . $e->getMessage();
    }
}

// Récupérer les informations de l'utilisateur
try {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    $error = 'Erreur lors de la récupération des données utilisateur';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - Gestion des Composants</title>
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
            max-width: 800px;
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
        }

        .nav-buttons a:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .content {
            padding: 30px;
        }

        .profile-section {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 2px solid #dee2e6;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
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
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 1em;
        }

        .btn-primary {
            background: #667eea;
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

        .section-title {
            font-size: 1.5em;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .current-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                👤 <?php echo htmlspecialchars($_SESSION['user_email']); ?>
                <a href="logout.php" style="margin-left: 15px; color: white; text-decoration: none; background: rgba(255,255,255,0.2); padding: 5px 10px; border-radius: 15px; transition: all 0.3s ease;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">🚪 Déconnexion</a>
            </div>
            <h1>👤 Profil Utilisateur</h1>
            
            <div class="nav-section">
                <div class="nav-buttons">
                    <a href="components.php">📦 Composants</a>
                    <a href="settings.php">⚙️ Paramètres</a>
                    <a href="db_config.php">🗄️ Config. BDD</a>
                </div>
            </div>
        </div>

        <div class="content">
            <?php if ($message): ?>
                <div class="alert alert-success">
                    ✅ <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Modification de l'email -->
            <div class="profile-section">
                <h2 class="section-title">📧 Modifier l'email</h2>
                
                <div class="current-info">
                    <strong>Email actuel :</strong> <?php echo htmlspecialchars($user['email'] ?? $_SESSION['user_email']); ?>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="update_email">
                    
                    <div class="form-group">
                        <label for="new_email">Nouvel email :</label>
                        <input type="email" id="new_email" name="new_email" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">💾 Mettre à jour l'email</button>
                    </div>
                </form>
            </div>

            <!-- Modification du mot de passe -->
            <div class="profile-section">
                <h2 class="section-title">🔒 Modifier le mot de passe</h2>

                <form method="POST">
                    <input type="hidden" name="action" value="update_password">
                    
                    <div class="form-group">
                        <label for="current_password">Mot de passe actuel :</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>

                    <div class="form-group">
                        <label for="new_password">Nouveau mot de passe :</label>
                        <input type="password" id="new_password" name="new_password" required minlength="6">
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirmer le nouveau mot de passe :</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">🔐 Mettre à jour le mot de passe</button>
                    </div>
                </form>
            </div>

            <!-- Accès rapide à la configuration BDD -->
            <div class="profile-section">
                <h2 class="section-title">🗄️ Configuration Base de Données</h2>
                
                <div class="current-info">
                    <strong>Base de données actuelle :</strong> <?php echo htmlspecialchars(DB_NAME); ?>
                </div>
                
                <p>Configurez les paramètres de connexion à votre base de données MySQL.</p>
                
                <div class="form-actions">
                    <a href="db_config.php" class="btn btn-secondary">⚙️ Configurer la base de données</a>
                </div>
            </div>
        </div>
    </div>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>