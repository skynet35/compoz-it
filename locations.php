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

// Traitement des messages
$success_message = '';
$error_message = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':
            $success_message = 'Emplacement ajouté avec succès!';
            break;
        case 'batch_added':
            $success_message = 'Emplacements créés par grappe avec succès!';
            break;
        case 'updated':
            $success_message = 'Emplacement modifié avec succès!';
            break;
        case 'deleted':
            $success_message = 'Emplacement supprimé avec succès!';
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'exists':
            $error_message = 'Cet emplacement existe déjà!';
            break;
        case 'invalid':
            $error_message = 'Données invalides!';
            break;
        case 'not_found':
            $error_message = 'Emplacement non trouvé!';
            break;
        case 'in_use':
            $error_message = 'Impossible de supprimer: cet emplacement est utilisé par des composants!';
            break;
    }
}

// Récupérer tous les emplacements de l'utilisateur
$stmt = $pdo->prepare("SELECT l.*, 
                             COUNT(d.id) as component_count,
                             COALESCE(SUM(d.quantity), 0) as total_quantity
                      FROM location l 
                      LEFT JOIN data d ON l.id = d.location_id 
                      WHERE l.owner = ? 
                      GROUP BY l.id 
                      ORDER BY l.casier, l.tiroir, l.compartiment");
$stmt->execute([$user_id]);
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les IDs des tiroirs pour chaque casier/tiroir
$tiroir_ids = [];
foreach ($locations as $location) {
    $key = $location['casier'] . '-' . $location['tiroir'];
    if (!isset($tiroir_ids[$key])) {
        $tiroir_ids[$key] = $location['id'];
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Emplacements</title>
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
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            text-decoration: none;
            font-size: 0.8em;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .logout-btn:hover {
            background: #c82333;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
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
            border-radius: 8px;
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

        .action-buttons {
            margin-bottom: 30px;
            text-align: center;
        }

        .btn {
            display: inline-block;
            padding: 12px 25px;
            margin: 0 10px;
            text-decoration: none;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .locations-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .locations-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: bold;
        }

        .locations-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .locations-table tr:hover {
            background-color: #f8f9fa;
        }

        .location-code {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
        }

        .component-count {
            background: #e3f2fd;
            color: #1976d2;
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: bold;
        }

        .no-locations {
            text-align: center;
            padding: 50px;
            color: #666;
            font-size: 1.2em;
        }

        .btn-small {
            padding: 5px 10px;
            font-size: 0.9em;
            margin: 0 2px;
        }

        .view-toggle {
            margin-bottom: 20px;
            text-align: center;
        }

        .toggle-btn {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            padding: 10px 20px;
            margin: 0 5px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: bold;
        }

        .toggle-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }

        .casiers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .casier-card {
            background: white;
            border: 3px solid #dee2e6;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            position: relative;
        }

        .casier-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #667eea;
        }

        .casier-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, #f8f9ff 0%, #e8f0ff 100%);
        }

        .casier-letter {
            font-size: 3em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }

        .casier-info {
            font-size: 0.9em;
            color: #666;
        }

        .casier-stats {
            margin-top: 10px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .casier-details {
            display: none;
            margin-top: 30px;
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            border: 2px solid #dee2e6;
        }

        .casier-details.active {
            display: block;
        }

        .tiroirs-container {
            margin-top: 20px;
        }

        .tiroir-section {
            background: white;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .tiroir-section:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .tiroir-header {
            font-size: 1.3em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 15px;
            text-align: center;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
        }

        .compartiments-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-start;
            align-items: stretch;
        }

        .compartiment-card {
            background: #e9ecef;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 12px;
            min-width: 120px;
            text-align: center;
            transition: all 0.3s ease;
            flex: 0 0 auto;
        }

        .compartiment-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .compartiment-number {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .compartiment-quantity {
            color: #667eea;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .compartiment-actions {
            display: flex;
            gap: 5px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .compartiment-actions a {
            font-size: 0.7em;
            padding: 4px 8px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
        }

        .edit-link {
            background: #ff9800;
            color: white;
        }

        .delete-link {
            background: #f44336;
            color: white;
        }

        .edit-link:hover, .delete-link:hover {
            opacity: 0.8;
        }

        .back-btn {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .back-btn:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="user-info">
                <span>👤 <?php echo htmlspecialchars($_SESSION['user_email']); ?></span>
                <a href="logout.php" class="logout-btn">🚪 Déconnexion</a>
            </div>
            <h1>🗂️ Gestion des Emplacements</h1>
            <div class="nav-section">
                <div class="nav-buttons">
                    <a href="components.php">📦 Composants</a>
                    <a href="create_component.php">➕ Créer</a>
                    <a href="projects.php">🚀 Projets</a>
                    <a href="settings.php">⚙️ Paramètres</a>
                </div>
            </div>
        </div>

        <div class="content">
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="action-buttons">
                <a href="add_location.php" class="btn btn-primary">➕ Ajouter un Emplacement</a>
                <a href="add_location_batch.php" class="btn btn-success">📦 Créer par Grappe</a>
            </div>

            <div class="view-toggle">
                <button class="toggle-btn active" onclick="showCasiersView()">🗂️ Vue Casiers</button>
                <button class="toggle-btn" onclick="showTableView()">📋 Vue Tableau</button>
            </div>

            <?php if (empty($locations)): ?>
                <div class="no-locations">
                    <p>Aucun emplacement trouvé.</p>
                    <p>Commencez par créer vos premiers emplacements!</p>
                </div>
            <?php else: ?>
                <!-- Vue Casiers -->
                <div id="casiers-view">
                    <?php
                    // Organiser les emplacements par casier
                    $casiers = [];
                    foreach ($locations as $location) {
                        $casier = $location['casier'];
                        if (!isset($casiers[$casier])) {
                            $casiers[$casier] = [
                                'tiroirs' => [],
                                'total_components' => 0,
                                'total_quantity' => 0
                            ];
                        }
                        
                        $tiroir = $location['tiroir'];
                        if (!isset($casiers[$casier]['tiroirs'][$tiroir])) {
                            $casiers[$casier]['tiroirs'][$tiroir] = [];
                        }
                        
                        $casiers[$casier]['tiroirs'][$tiroir][] = $location;
                        $casiers[$casier]['total_components'] += $location['component_count'];
                        $casiers[$casier]['total_quantity'] += $location['total_quantity'];
                    }
                    
                    ksort($casiers);
                    ?>
                    
                    <div class="casiers-grid">
                        <?php foreach ($casiers as $casier_letter => $casier_data): ?>
                            <div class="casier-card" onclick="selectCasier('<?php echo $casier_letter; ?>')">
                                <div class="casier-letter"><?php echo htmlspecialchars($casier_letter); ?></div>
                                <div class="casier-info">
                                    <strong><?php echo count($casier_data['tiroirs']); ?></strong> tiroir(s)
                                </div>
                                <div class="casier-stats">
                                    <div><strong><?php echo $casier_data['total_quantity']; ?></strong> composants</div>
                                    <div><strong><?php echo $casier_data['total_components']; ?></strong> emplacements utilisés</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Détails du casier sélectionné -->
                    <?php foreach ($casiers as $casier_letter => $casier_data): ?>
                        <div id="casier-<?php echo $casier_letter; ?>" class="casier-details">
                            <button class="back-btn" onclick="deselectCasier()">← Retour aux casiers</button>
                            <h2>🗂️ Casier <?php echo htmlspecialchars($casier_letter); ?></h2>
                            
                            <div class="tiroirs-container">
                                 <?php 
                                 ksort($casier_data['tiroirs']);
                                 foreach ($casier_data['tiroirs'] as $tiroir_number => $tiroir_locations): 
                                     // Trier les compartiments par numéro
                                     usort($tiroir_locations, function($a, $b) {
                                         return (int)$a['compartiment'] - (int)$b['compartiment'];
                                     });
                                 ?>
                                     <div class="tiroir-section">
                                         <div class="tiroir-header">
                                             Ligne <?php echo htmlspecialchars($tiroir_number); ?> 
                                             <span style="font-size: 0.9em; color: #667eea; font-weight: bold; background: #f0f4ff; padding: 2px 8px; border-radius: 12px; margin-left: 10px;">
                                                 ID: <?php echo $tiroir_ids[$casier_letter . '-' . $tiroir_number] ?? 'N/A'; ?>
                                             </span>
                                             <br>
                                             <small style="font-size: 0.85em; color: #666; margin-top: 5px; display: block;">
                                             <?php 
                                             $codes = [];
                                             foreach ($tiroir_locations as $location) {
                                                 $codes[] = htmlspecialchars($casier_letter . $tiroir_number . '-' . $location['compartiment']);
                                             }
                                             echo implode(', ', $codes);
                                             ?>
                                             </small>
                                         </div>
                                         <div class="compartiments-row">
                                             <?php foreach ($tiroir_locations as $location): ?>
                                                 <div class="compartiment-card">
                                                     <div class="compartiment-number">
                                                         <?php echo htmlspecialchars($casier_letter . $tiroir_number . '-' . $location['compartiment']); ?>
                                                         <br><small style="font-size: 0.7em; color: #999;">ID: <?php echo $location['id']; ?></small>
                                                     </div>
                                                     <div class="compartiment-quantity">
                                                         <?php echo $location['total_quantity']; ?> pièces
                                                     </div>
                                                     <div class="compartiment-actions">
                                                         <a href="edit_location.php?id=<?php echo $location['id']; ?>" class="edit-link">✏️ Modifier</a>
                                                         <?php if ($location['component_count'] == 0): ?>
                                                             <a href="delete_location.php?id=<?php echo $location['id']; ?>" 
                                                                class="delete-link"
                                                                onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet emplacement?')">🗑️ Supprimer</a>
                                                         <?php endif; ?>
                                                     </div>
                                                 </div>
                                             <?php endforeach; ?>
                                         </div>
                                     </div>
                                 <?php endforeach; ?>
                             </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Vue Tableau -->
                <div id="table-view" style="display: none;">
                    <table class="locations-table">
                        <thead>
                            <tr>
                                <th>Code Emplacement</th>
                                <th>Casier</th>
                                <th>Tiroir</th>
                                <th>Compartiment</th>
                                <th>Description</th>
                                <th>Composants</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($locations as $location): ?>
                                <tr>
                                    <td>
                                        <span class="location-code">
                                            <?php echo htmlspecialchars($location['casier'] . '-' . $location['tiroir'] . '-' . $location['compartiment']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($location['casier']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($location['tiroir']); ?>
                                        <br><span style="font-size: 0.8em; color: #667eea; font-weight: bold; background: #f0f4ff; padding: 1px 6px; border-radius: 8px;">ID: <?php echo $location['id']; ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($location['compartiment']); ?></td>
                                    <td><?php echo htmlspecialchars($location['description'] ?? ''); ?></td>
                                    <td>
                                        <span class="component-count">
                                            <?php echo $location['total_quantity']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="edit_location.php?id=<?php echo $location['id']; ?>" class="btn btn-warning btn-small">✏️ Modifier</a>
                                        <?php if ($location['component_count'] == 0): ?>
                                            <a href="delete_location.php?id=<?php echo $location['id']; ?>" 
                                               class="btn btn-danger btn-small" 
                                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet emplacement?')">🗑️ Supprimer</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function showCasiersView() {
            document.getElementById('casiers-view').style.display = 'block';
            document.getElementById('table-view').style.display = 'none';
            document.querySelectorAll('.toggle-btn')[0].classList.add('active');
            document.querySelectorAll('.toggle-btn')[1].classList.remove('active');
        }
        
        function showTableView() {
            document.getElementById('casiers-view').style.display = 'none';
            document.getElementById('table-view').style.display = 'block';
            document.querySelectorAll('.toggle-btn')[0].classList.remove('active');
            document.querySelectorAll('.toggle-btn')[1].classList.add('active');
        }
        
        function selectCasier(casierLetter) {
            // Masquer la grille des casiers
            document.querySelector('.casiers-grid').style.display = 'none';
            
            // Masquer tous les détails de casiers
            document.querySelectorAll('.casier-details').forEach(detail => {
                detail.classList.remove('active');
            });
            
            // Afficher les détails du casier sélectionné
            document.getElementById('casier-' + casierLetter).classList.add('active');
        }
        
        function deselectCasier() {
            // Afficher la grille des casiers
            document.querySelector('.casiers-grid').style.display = 'grid';
            
            // Masquer tous les détails de casiers
            document.querySelectorAll('.casier-details').forEach(detail => {
                detail.classList.remove('active');
            });
        }
    </script>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>