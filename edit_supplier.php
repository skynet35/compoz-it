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
    
    // Récupérer le fournisseur
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ? AND owner = ?");
    $stmt->execute([$supplier_id, $_SESSION['user_id']]);
    $supplier = $stmt->fetch();
    
    if (!$supplier) {
        header('Location: suppliers.php?error=supplier_not_found');
        exit();
    }
    
    // Récupérer les contacts existants
    $stmt = $pdo->prepare("SELECT * FROM supplier_contacts WHERE supplier_id = ? ORDER BY name");
    $stmt->execute([$supplier_id]);
    $existing_contacts = $stmt->fetchAll();
    
} catch(PDOException $e) {
    header('Location: suppliers.php?error=database_error');
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Gestion du logo
    $logo_path = $supplier['logo_path']; // Garder le logo existant par défaut
    
    // Vérifier si une image existante a été sélectionnée
    if (!empty($_POST['existing_logo'])) {
        $selected_logo = $_POST['existing_logo'];
        // Vérifier que le fichier existe dans le dossier img/
        if (file_exists('img/' . $selected_logo)) {
            $logo_path = 'img/' . $selected_logo;
        }
    }
    // Sinon, vérifier si un nouveau fichier a été uploadé
    elseif (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'img/';
        
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $filename = 'supplier_logo_' . $supplier_id . '_' . time() . '.' . $file_extension;
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_path)) {
                // Supprimer l'ancien logo s'il existe et s'il n'est pas dans le dossier img/ partagé
                if ($supplier['logo_path'] && file_exists($supplier['logo_path']) && strpos($supplier['logo_path'], 'supplier_logo_') !== false) {
                    unlink($supplier['logo_path']);
                }
                $logo_path = $target_path;
            }
        }
    }
    
    // Validation
    if (empty($name)) {
        $error = "Le nom du fournisseur est obligatoire.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Mettre à jour le fournisseur
            $stmt = $pdo->prepare("
                UPDATE suppliers 
                SET name = ?, website = ?, email = ?, phone = ?, address = ?, logo_path = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND owner = ?
            ");
            
            $result = $stmt->execute([
                $name,
                $website ?: null,
                $email ?: null,
                $phone ?: null,
                $address ?: null,
                $logo_path,
                $notes ?: null,
                $supplier_id,
                $_SESSION['user_id']
            ]);
            
            if ($result) {
                // Supprimer tous les contacts existants
                $stmt = $pdo->prepare("DELETE FROM supplier_contacts WHERE supplier_id = ?");
                $stmt->execute([$supplier_id]);
                
                // Ajouter les nouveaux contacts
                $contacts = $_POST['contacts'] ?? [];
                foreach ($contacts as $contact) {
                    $contact_name = trim($contact['name'] ?? '');
                    $contact_email = trim($contact['email'] ?? '');
                    $contact_phone = trim($contact['phone'] ?? '');
                    $contact_position = trim($contact['position'] ?? '');
                    $contact_notes = trim($contact['notes'] ?? '');
                    
                    if (!empty($contact_name)) {
                        $stmt = $pdo->prepare("
                            INSERT INTO supplier_contacts (supplier_id, name, email, phone, position, notes) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([
                            $supplier_id,
                            $contact_name,
                            $contact_email ?: null,
                            $contact_phone ?: null,
                            $contact_position ?: null,
                            $contact_notes ?: null
                        ]);
                    }
                }
                
                $pdo->commit();
                header('Location: suppliers.php?success=supplier_updated');
                exit();
            } else {
                $pdo->rollBack();
                $error = "Erreur lors de la modification du fournisseur.";
            }
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $error = "Erreur de base de données : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier le Fournisseur - ECDB</title>
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
            text-align: center;
        }

        .content {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
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

        .btn-primary {
            background: #4CAF50;
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

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        .contacts-section {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            background: #f8f9fa;
        }

        .contact-item {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            background: white;
            position: relative;
        }

        .contact-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .contact-title {
            font-weight: bold;
            color: #495057;
        }

        .remove-contact {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 3px;
            padding: 5px 10px;
            cursor: pointer;
            font-size: 12px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .current-logo {
            max-width: 100px;
            max-height: 100px;
            border-radius: 10px;
            margin-bottom: 10px;
            border: 2px solid #ddd;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✏️ Modifier le Fournisseur</h1>
            <p>Modification de: <?php echo htmlspecialchars($supplier['name']); ?></p>
        </div>

        <div class="content">
            <div style="margin-bottom: 20px;">
                <a href="suppliers.php" class="btn btn-secondary">← Retour aux fournisseurs</a>
            </div>

            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="name">Nom du fournisseur *</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($supplier['name']); ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="website">Site web</label>
                        <input type="url" id="website" name="website" value="<?php echo htmlspecialchars($supplier['website'] ?? ''); ?>" placeholder="https://exemple.com">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($supplier['email'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="phone">Téléphone</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($supplier['phone'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="address">Adresse</label>
                    <textarea id="address" name="address" placeholder="Adresse complète du fournisseur"><?php echo htmlspecialchars($supplier['address'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="logo">Logo de l'entreprise</label>
                    <?php if ($supplier['logo_path']): ?>
                        <div style="margin-bottom: 10px;">
                            <p><strong>Logo actuel:</strong></p>
                            <img src="<?php echo htmlspecialchars($supplier['logo_path']); ?>" alt="Logo actuel" class="current-logo">
                        </div>
                    <?php endif; ?>
                    
                    <!-- Option 1: Sélectionner une image existante -->
                    <div style="margin-bottom: 15px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: #f8f9fa;">
                        <h4 style="margin: 0 0 10px 0; color: #495057;">📁 Choisir une image existante</h4>
                        <select name="existing_logo" id="existing_logo" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;">
                            <option value="">-- Sélectionner une image du dossier img/ --</option>
                            <?php
                            $img_dir = 'img/';
                            if (is_dir($img_dir)) {
                                $images = glob($img_dir . '*.{jpg,jpeg,png,gif,webp,svg}', GLOB_BRACE);
                                foreach ($images as $image) {
                                    $filename = basename($image);
                                    $selected = ($supplier['logo_path'] === $image) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($filename) . '" ' . $selected . '>' . htmlspecialchars($filename) . '</option>';
                                }
                            }
                            ?>
                        </select>
                        <div id="preview-container" style="margin-top: 10px;"></div>
                    </div>
                    
                    <!-- Option 2: Uploader un nouveau fichier -->
                    <div style="margin-bottom: 10px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: #f8f9fa;">
                        <h4 style="margin: 0 0 10px 0; color: #495057;">📤 Ou uploader un nouveau fichier</h4>
                        <input type="file" id="logo" name="logo" accept="image/*" style="width: 100%;">
                        <small style="color: #666;">Formats acceptés: JPG, PNG, GIF, WebP, SVG.</small>
                    </div>
                    
                    <small style="color: #666;"><strong>Note:</strong> Si vous sélectionnez une image existante ET uploadez un nouveau fichier, le nouveau fichier sera prioritaire.</small>
                </div>

                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" placeholder="Notes et commentaires sur le fournisseur"><?php echo htmlspecialchars($supplier['notes'] ?? ''); ?></textarea>
                </div>

                <div class="contacts-section">
                    <h3>👥 Contacts</h3>
                    <p style="margin-bottom: 15px; color: #666;">Gérez les contacts de ce fournisseur</p>
                    
                    <div id="contacts-container">
                        <!-- Les contacts existants et nouveaux seront ajoutés ici -->
                    </div>
                    
                    <button type="button" onclick="addContact()" class="btn btn-secondary">➕ Ajouter un contact</button>
                </div>

                <div style="margin-top: 30px; text-align: center;">
                    <button type="submit" class="btn btn-primary">💾 Enregistrer les modifications</button>
                    <a href="suppliers.php" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        let contactIndex = 0;
        const existingContacts = <?php echo json_encode($existing_contacts); ?>;

        function addContact(contactData = null) {
            const container = document.getElementById('contacts-container');
            const contactDiv = document.createElement('div');
            contactDiv.className = 'contact-item';
            
            const name = contactData ? contactData.name : '';
            const position = contactData ? contactData.position || '' : '';
            const email = contactData ? contactData.email || '' : '';
            const phone = contactData ? contactData.phone || '' : '';
            const notes = contactData ? contactData.notes || '' : '';
            
            contactDiv.innerHTML = `
                <div class="contact-header">
                    <span class="contact-title">Contact ${contactIndex + 1}</span>
                    <button type="button" class="remove-contact" onclick="removeContact(this)">Supprimer</button>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="contacts[${contactIndex}][name]" value="${name}" required>
                    </div>
                    <div class="form-group">
                        <label>Poste</label>
                        <input type="text" name="contacts[${contactIndex}][position]" value="${position}" placeholder="Ex: Responsable commercial">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="contacts[${contactIndex}][email]" value="${email}">
                    </div>
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="tel" name="contacts[${contactIndex}][phone]" value="${phone}">
                    </div>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="contacts[${contactIndex}][notes]" placeholder="Notes sur ce contact">${notes}</textarea>
                </div>
            `;
            container.appendChild(contactDiv);
            contactIndex++;
        }

        function removeContact(button) {
            button.closest('.contact-item').remove();
        }

        // Prévisualisation de l'image sélectionnée
        function previewExistingImage() {
            const select = document.getElementById('existing_logo');
            const previewContainer = document.getElementById('preview-container');
            
            if (select.value) {
                const imagePath = 'img/' + select.value;
                previewContainer.innerHTML = `
                    <p style="margin: 5px 0; font-weight: bold; color: #495057;">Aperçu:</p>
                    <img src="${imagePath}" alt="Aperçu" style="max-width: 100px; max-height: 100px; border-radius: 5px; border: 2px solid #ddd;">
                `;
            } else {
                previewContainer.innerHTML = '';
            }
        }
        
        // Charger les contacts existants
        document.addEventListener('DOMContentLoaded', function() {
            // Ajouter les contacts existants
            existingContacts.forEach(contact => {
                addContact(contact);
            });
            
            // Si aucun contact existant, ajouter un contact vide
            if (existingContacts.length === 0) {
                addContact();
            }
            
            // Ajouter l'événement de prévisualisation
            document.getElementById('existing_logo').addEventListener('change', previewExistingImage);
            
            // Prévisualiser l'image actuelle si elle est dans la liste
            previewExistingImage();
        });
    </script>

    <footer style="margin-top: 2rem; padding: 1rem; text-align: center; border-top: 1px solid #ddd; background-color: #f8f9fa; color: #666; font-size: 0.9em;">
        Créé par Jérémy Leroy - Version 1.0 - Copyright © 2025 - Tous droits réservés selon les termes de la licence Creative Commons CC BY-NC-SA 3.0
    </footer>
</body>
</html>