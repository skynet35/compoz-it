<?php
session_start();

// Vérifier si l'installation est nécessaire
if (!file_exists('config.php')) {
    header('Location: install.php');
    exit();
}

require_once 'config.php';

// Vérifier si l'utilisateur est déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: components.php');
    exit();
}

$error = '';
$success = '';

if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}
if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Simple Login</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .title {
            text-align: center;
            margin-bottom: 2rem;
            color: #333;
            font-size: 1.8rem;
            font-weight: 600;
        }
        
        .form-tabs {
            display: flex;
            margin-bottom: 2rem;
            border-bottom: 1px solid #eee;
        }
        
        .tab {
            flex: 1;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
            color: #666;
        }
        
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .form-container {
            display: none;
        }
        
        .form-container.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .message {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .success {
            background: #efe;
            color: #363;
            border: 1px solid #cfc;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="title">🔐 Simple Login</h1>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="form-tabs">
            <div class="tab active" onclick="showForm('login')">Connexion</div>
            <div class="tab" onclick="showForm('register')">Inscription</div>
        </div>
        
        <!-- Formulaire de connexion -->
        <div id="login-form" class="form-container active">
            <form action="auth.php" method="POST">
                <input type="hidden" name="action" value="login">
                
                <div class="form-group">
                    <label for="login-email">📧 Email</label>
                    <input type="email" id="login-email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="login-password">🔒 Mot de passe</label>
                    <input type="password" id="login-password" name="password" required>
                </div>
                
                <button type="submit" class="btn">Se connecter</button>
            </form>
        </div>
        
        <!-- Formulaire d'inscription -->
        <div id="register-form" class="form-container">
            <form action="auth.php" method="POST">
                <input type="hidden" name="action" value="register">
                
                <div class="form-group">
                    <label for="register-email">📧 Email</label>
                    <input type="email" id="register-email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="register-password">🔒 Mot de passe</label>
                    <input type="password" id="register-password" name="password" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label for="confirm-password">🔒 Confirmer le mot de passe</label>
                    <input type="password" id="confirm-password" name="confirm_password" required minlength="6">
                </div>
                
                <button type="submit" class="btn">S'inscrire</button>
            </form>
            
            <div style="margin-top: 1rem; text-align: center;">
                <a href="db_config.php" class="btn" style="background: linear-gradient(135deg, #6c757d 0%, #495057 100%); text-decoration: none; display: block;">⚙️ Réglage base de données</a>
            </div>
        </div>
    </div>
    
    <script>
        function showForm(formType) {
            // Masquer tous les formulaires
            document.querySelectorAll('.form-container').forEach(form => {
                form.classList.remove('active');
            });
            
            // Désactiver tous les onglets
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Afficher le formulaire sélectionné
            document.getElementById(formType + '-form').classList.add('active');
            
            // Activer l'onglet sélectionné
            event.target.classList.add('active');
        }
    </script>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>