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
$success_count = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $casier = trim($_POST['casier'] ?? '');
    $premier_tiroir = intval($_POST['premier_tiroir'] ?? 10);
    $tiroirs_horizontal = intval($_POST['tiroirs_horizontal'] ?? 6);
    $tiroirs_vertical = intval($_POST['tiroirs_vertical'] ?? 3);
    $compartiments_par_tiroir = intval($_POST['compartiments_par_tiroir'] ?? 4);
    $description = trim($_POST['description'] ?? '');
    
    // Validation
    if (empty($casier)) {
        $error_message = 'Le nom du casier est obligatoire.';
    } elseif ($tiroirs_horizontal < 1 || $tiroirs_horizontal > 20) {
        $error_message = 'Le nombre de tiroirs horizontaux doit être entre 1 et 20.';
    } elseif ($tiroirs_vertical < 1 || $tiroirs_vertical > 10) {
        $error_message = 'Le nombre de tiroirs verticaux doit être entre 1 et 10.';
    } elseif ($compartiments_par_tiroir < 1 || $compartiments_par_tiroir > 10) {
        $error_message = 'Le nombre de compartiments par tiroir doit être entre 1 et 10.';
    } else {
        try {
            $pdo->beginTransaction();
            
            $created_locations = [];
            $skipped_locations = [];
            
            // Créer les emplacements en grille
            for ($ligne = 0; $ligne < $tiroirs_vertical; $ligne++) {
                $tiroir_ligne = $premier_tiroir + ($ligne * 10); // A10, A20, A30, etc.
                
                for ($col = 0; $col < $tiroirs_horizontal; $col++) {
                    $tiroir = $tiroir_ligne + $col; // A10, A11, A12, A13, A14, A15
                    
                    // Créer les compartiments pour chaque tiroir
                    for ($compartiment = 1; $compartiment <= $compartiments_par_tiroir; $compartiment++) {
                        // Vérifier si l'emplacement existe déjà
                        $stmt = $pdo->prepare("SELECT id FROM location WHERE owner = ? AND casier = ? AND tiroir = ? AND compartiment = ?");
                        $stmt->execute([$user_id, $casier, $tiroir, $compartiment]);
                        
                        if ($stmt->fetch()) {
                            $skipped_locations[] = "$casier-$tiroir-$compartiment";
                            continue;
                        }
                        
                        // Créer l'emplacement
                        $location_description = $description ? "$description (Ligne " . ($ligne + 1) . ", Tiroir $tiroir, Compartiment $compartiment)" : "";
                        $stmt = $pdo->prepare("INSERT INTO location (owner, casier, tiroir, compartiment, description) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$user_id, $casier, $tiroir, $compartiment, $location_description]);
                        
                        $created_locations[] = "$casier-$tiroir-$compartiment";
                        $success_count++;
                    }
                }
            }
            
            $pdo->commit();
            
            if ($success_count > 0) {
                header('Location: locations.php?success=batch_added&count=' . $success_count);
                exit();
            } else {
                $error_message = 'Aucun emplacement créé. Tous les emplacements existent déjà.';
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = 'Erreur lors de la création des emplacements: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer des Emplacements par Grille</title>
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
            max-width: 700px;
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

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
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

        .preview-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }

        .preview-box h4 {
            color: #495057;
            margin-bottom: 10px;
        }

        .preview-item {
            display: inline-block;
            background: #e9ecef;
            padding: 5px 10px;
            margin: 2px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
    </style>
    <script>
        function updatePreview() {
            const casier = document.getElementById('casier').value || 'X';
            const premierTiroir = parseInt(document.getElementById('premier_tiroir').value) || 10;
            const tiroirsHorizontal = parseInt(document.getElementById('tiroirs_horizontal').value) || 6;
            const tiroirsVertical = parseInt(document.getElementById('tiroirs_vertical').value) || 3;
            const compartimentsParTiroir = parseInt(document.getElementById('compartiments_par_tiroir').value) || 4;
            
            const previewDiv = document.getElementById('preview');
            let html = '<h4>Aperçu de la grille qui sera créée:</h4>';
            
            let totalCount = 0;
            for (let ligne = 0; ligne < tiroirsVertical; ligne++) {
                const tiroirLigne = premierTiroir + (ligne * 10);
                html += `<div style="margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 5px;">`;
                html += `<strong>Ligne ${ligne + 1}:</strong><br>`;
                
                for (let col = 0; col < tiroirsHorizontal; col++) {
                    const tiroir = tiroirLigne + col;
                    html += `<div style="margin: 5px 0; padding: 5px; background: #e9ecef; border-radius: 3px; display: inline-block; margin-right: 10px;">`;
                    html += `<strong>${casier}${tiroir}:</strong> `;
                    
                    for (let compartiment = 1; compartiment <= compartimentsParTiroir; compartiment++) {
                        html += `<span class="preview-item">${casier}${tiroir}-${compartiment}</span>`;
                        totalCount++;
                    }
                    html += `</div>`;
                }
                html += `</div>`;
            }
            
            html += `<p style="margin-top: 15px; font-weight: bold; color: #495057; text-align: center;">Total: ${totalCount} emplacements</p>`;
            previewDiv.innerHTML = html;
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = ['casier', 'premier_tiroir', 'tiroirs_horizontal', 'tiroirs_vertical', 'compartiments_par_tiroir'];
            inputs.forEach(id => {
                document.getElementById(id).addEventListener('input', updatePreview);
            });
            updatePreview();
        });
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔲 Créer par Grille</h1>
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
                <h3>ℹ️ Création par grille</h3>
                <p><strong>Principe:</strong> Créer une grille de tiroirs avec compartiments organisée en lignes et colonnes</p>
                <p><strong>Exemple:</strong> Premier tiroir A10, 6 tiroirs horizontaux, 3 lignes verticales, 4 compartiments par tiroir</p>
                <p><strong>Résultat:</strong></p>
                <p>• Ligne 1: A10-1, A10-2, A10-3, A10-4, A11-1, A11-2, A11-3, A11-4, etc.</p>
                <p>• Ligne 2: A20-1, A20-2, A20-3, A20-4, A21-1, A21-2, A21-3, A21-4, etc.</p>
                <p>• Ligne 3: A30-1, A30-2, A30-3, A30-4, A31-1, A31-2, A31-3, A31-4, etc.</p>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="casier">Nom du Casier <span class="required">*</span></label>
                    <input type="text" id="casier" name="casier" required 
                           placeholder="Ex: A, B, C1, ELEC1, etc." 
                           value="<?php echo htmlspecialchars($_POST['casier'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="premier_tiroir">Premier Tiroir <span class="required">*</span></label>
                    <input type="number" id="premier_tiroir" name="premier_tiroir" 
                           min="1" max="99" required 
                           placeholder="Ex: 10 pour commencer par A10"
                           value="<?php echo htmlspecialchars($_POST['premier_tiroir'] ?? '10'); ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="tiroirs_horizontal">Tiroirs Horizontaux <span class="required">*</span></label>
                        <input type="number" id="tiroirs_horizontal" name="tiroirs_horizontal" 
                               min="1" max="20" required 
                               placeholder="Nombre par ligne"
                               value="<?php echo htmlspecialchars($_POST['tiroirs_horizontal'] ?? '6'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="tiroirs_vertical">Lignes Verticales <span class="required">*</span></label>
                        <input type="number" id="tiroirs_vertical" name="tiroirs_vertical" 
                               min="1" max="10" required 
                               placeholder="Nombre de lignes"
                               value="<?php echo htmlspecialchars($_POST['tiroirs_vertical'] ?? '3'); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="compartiments_par_tiroir">Compartiments par Tiroir <span class="required">*</span></label>
                    <input type="number" id="compartiments_par_tiroir" name="compartiments_par_tiroir" 
                           min="1" max="10" required 
                           placeholder="Nombre de compartiments par tiroir"
                           value="<?php echo htmlspecialchars($_POST['compartiments_par_tiroir'] ?? '4'); ?>">
                </div>

                <div class="form-group">
                    <label for="description">Description (optionnel)</label>
                    <textarea id="description" name="description" 
                              placeholder="Description générale pour tous les emplacements..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div class="preview-box" id="preview">
                    <!-- Le contenu sera généré par JavaScript -->
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">🚀 Créer tous les Emplacements</button>
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