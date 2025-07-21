# CompoZ'IT

Un système de gestion de composants électroniques avec PHP et MySQL.

## 🚀 Fonctionnalités

- ✅ Inscription d'utilisateurs
- ✅ Connexion sécurisée
- ✅ Hachage des mots de passe
- ✅ Gestion des sessions
- ✅ Interface moderne et responsive
- ✅ Validation des données
- ✅ Messages d'erreur et de succès

## 📋 Prérequis

- XAMPP (Apache + MySQL + PHP)
- PHP 7.4 ou supérieur
- Extension PDO MySQL activée

## 🛠️ Installation

1. **Démarrer XAMPP**
   - Lancer Apache et MySQL depuis le panneau de contrôle XAMPP

2. **Placer les fichiers**
   - Copier le dossier `compozit` dans `C:\xampp\htdocs\`

3. **Configuration automatique**
   - La base de données et la table seront créées automatiquement au premier accès
   - Nom de la base : `Compozit`
   - Table : `users` (id, email, password, created_at) préenregistré: admin@compozit.fr / pass:compozit

4. **Accéder à l'application**
   - Ouvrir votre navigateur
   - Aller à : `http://localhost/compozit`

## 🔧 Configuration

Les paramètres de base de données sont dans `config.php` :

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', '');
```

## 📁 Structure des fichiers

```
Simple/
├── index.php          # Page de connexion/inscription
├── auth.php           # Traitement de l'authentification

├── logout.php         # Script de déconnexion
├── config.php         # Configuration de la base de données
└── README.md          # Ce fichier
```

## 🎯 Utilisation

1. **Première utilisation**
   - Cliquer sur l'onglet "Inscription"
   - Saisir un email et un mot de passe (min. 6 caractères)
   - Confirmer le mot de passe
   - Cliquer sur "S'inscrire"

2. **Connexion**
   - Utiliser l'email et le mot de passe créés
   - Cliquer sur "Se connecter"
   - Vous serez redirigé vers le dashboard

3. **Déconnexion**
   - Cliquer sur "Se déconnecter" dans le dashboard

## 🔒 Sécurité

- Mots de passe hachés avec `password_hash()`
- Protection contre l'injection SQL avec PDO
- Validation des emails
- Gestion sécurisée des sessions
- Échappement des données affichées

## 🐛 Dépannage

**Erreur de connexion à la base de données :**
- Vérifier que MySQL est démarré dans XAMPP
- Vérifier le mot de passe dans `config.php`

**Page blanche :**
- Vérifier les logs d'erreur PHP
- S'assurer que l'extension PDO est activée

**Problème de session :**
- Vérifier que les cookies sont activés
- Effacer le cache du navigateur

## 📊 Base de données

La table `users` est créée automatiquement avec cette structure :

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## ✨ Fonctionnalités avancées possibles

- Récupération de mot de passe par email
- Profil utilisateur
- Rôles et permissions
- Connexion avec réseaux sociaux
- Authentification à deux facteurs

---

**Développé avec ❤️ pour un test simple et fonctionnel**
