<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=not_logged_in');
    exit();
}

// Vérifier si l'ID du fournisseur est fourni
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: suppliers.php?error=invalid_id');
    exit();
}

$supplier_id = (int)$_GET['id'];

try {
    $pdo = getConnection();
    
    // Vérifier que le fournisseur appartient à l'utilisateur
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ? AND owner = ?");
    $stmt->execute([$supplier_id, $_SESSION['user_id']]);
    $supplier = $stmt->fetch();
    
    if (!$supplier) {
        header('Location: suppliers.php?error=supplier_not_found');
        exit();
    }
    
    // Vérifier si le fournisseur est utilisé dans des composants
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM data WHERE supplier_id = ? AND owner = ?");
    $stmt->execute([$supplier_id, $_SESSION['user_id']]);
    $usage_count = $stmt->fetch()['count'];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
        $pdo->beginTransaction();
        
        try {
            // Supprimer les contacts du fournisseur
            $stmt = $pdo->prepare("DELETE FROM supplier_contacts WHERE supplier_id = ?");
            $stmt->execute([$supplier_id]);
            
            // Supprimer le logo s'il existe
            if ($supplier['logo_path'] && file_exists($supplier['logo_path'])) {
                unlink($supplier['logo_path']);
            }
            
            // Supprimer le fournisseur
            $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ? AND owner = ?");
            $stmt->execute([$supplier_id, $_SESSION['user_id']]);
            
            $pdo->commit();
            header('Location: suppliers.php?success=supplier_deleted');
            exit();
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $error = "Erreur lors de la suppression : " . $e->getMessage();
        }
    }
    
} catch(PDOException $e) {
    header('Location: suppliers.php?error=database_error');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer le Fournisseur - ECDB</title>
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
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .content {
            padding: 30px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            display: inline-block;
            margin-right: 10px;
        }

        .btn-danger {
            background: #dc3545;
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

        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #ffeaa7;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        .supplier-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }

        .supplier-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .supplier-logo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin-right: 15px;
            object-fit: cover;
            border: 2px solid #ddd;
        }

        .supplier-name {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }

        .usage-info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #bee5eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🗑️ Supprimer le Fournisseur</h1>
            <p>Confirmation de suppression</p>
        </div>

        <div class="content">
            <div style="margin-bottom: 20px;">
                <a href="suppliers.php" class="btn btn-secondary">← Retour aux fournisseurs</a>
            </div>

            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="supplier-info">
                <div class="supplier-header">
                    <?php if ($supplier['logo_path']): ?>
                        <img src="<?php echo htmlspecialchars($supplier['logo_path']); ?>" alt="Logo" class="supplier-logo">
                    <?php else: ?>
                        <div class="supplier-logo" style="background: #ddd; display: flex; align-items: center; justify-content: center; font-size: 24px;">🏢</div>
                    <?php endif; ?>
                    <div class="supplier-name"><?php echo htmlspecialchars($supplier['name']); ?></div>
                </div>
                
                <?php if ($supplier['website']): ?>
                    <p><strong>Site web:</strong> <?php echo htmlspecialchars($supplier['website']); ?></p>
                <?php endif; ?>
                
                <?php if ($supplier['email']): ?>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($supplier['email']); ?></p>
                <?php endif; ?>
                
                <?php if ($supplier['phone']): ?>
                    <p><strong>Téléphone:</strong> <?php echo htmlspecialchars($supplier['phone']); ?></p>
                <?php endif; ?>
            </div>

            <?php if ($usage_count > 0): ?>
                <div class="usage-info">
                    <strong>⚠️ Attention !</strong><br>
                    Ce fournisseur est utilisé par <strong><?php echo $usage_count; ?> composant(s)</strong>.
                    La suppression du fournisseur ne supprimera pas les composants, mais la référence au fournisseur sera perdue.
                </div>
            <?php endif; ?>

            <div class="warning">
                <strong>⚠️ Attention !</strong><br>
                Cette action est irréversible. Le fournisseur et tous ses contacts seront définitivement supprimés.
                <?php if ($supplier['logo_path']): ?>
                    <br>Le logo de l'entreprise sera également supprimé.
                <?php endif; ?>
            </div>

            <p style="margin-bottom: 30px; font-size: 16px; color: #333;">
                Êtes-vous sûr de vouloir supprimer le fournisseur <strong>"<?php echo htmlspecialchars($supplier['name']); ?>"</strong> ?
            </p>

            <form method="POST" style="text-align: center;">
                <button type="submit" name="confirm_delete" value="1" class="btn btn-danger" onclick="return confirm('Êtes-vous vraiment sûr ? Cette action ne peut pas être annulée.')">
                    🗑️ Oui, supprimer définitivement
                </button>
                <a href="suppliers.php" class="btn btn-secondary">Annuler</a>
            </form>
        </div>
    </div>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>