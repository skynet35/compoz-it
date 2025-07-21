-- Script pour réinitialiser la base de données et importer les données
-- À utiliser quand vous avez l'erreur "Table 'xxx.users' doesn't exist"

-- Désactiver les vérifications de clés étrangères
SET FOREIGN_KEY_CHECKS = 0;

-- Supprimer toutes les tables si elles existent
DROP TABLE IF EXISTS `project_items`;
DROP TABLE IF EXISTS `project_files`;
DROP TABLE IF EXISTS `project_components`;
DROP TABLE IF EXISTS `projects`;
DROP TABLE IF EXISTS `supplier_contacts`;
DROP TABLE IF EXISTS `suppliers`;
DROP TABLE IF EXISTS `packages`;
DROP TABLE IF EXISTS `manufacturers`;
DROP TABLE IF EXISTS `data`;
DROP TABLE IF EXISTS `location`;
DROP TABLE IF EXISTS `category_sub`;
DROP TABLE IF EXISTS `category_head`;
DROP TABLE IF EXISTS `users`;

-- Réactiver les vérifications de clés étrangères
SET FOREIGN_KEY_CHECKS = 1;

-- IMPORTANT: Créer d'abord la table users car d'autres tables peuvent y faire référence
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insérer un utilisateur par défaut
INSERT INTO `users` (`id`, `email`, `password`, `created_at`) VALUES
(1, 'admin@example.com', '$2y$10$example', '2025-07-05 23:14:24');

-- Maintenant créer les autres tables
-- Structure de la table `category_head`
CREATE TABLE `category_head` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Déchargement des données de la table `category_head`
INSERT INTO `category_head` (`id`, `name`) VALUES
(1, 'Résistances'),
(2, 'Condensateurs'),
(3, 'Diodes'),
(4, 'Transistors'),
(5, 'Circuits intégrés'),
(6, 'Connecteurs'),
(7, 'Inductances'),
(8, 'Cristaux et oscillateurs'),
(9, 'Fusibles et protection'),
(10, 'Relais et commutateurs'),
(11, 'Capteurs'),
(12, 'Afficheurs'),
(13, 'Alimentations'),
(14, 'Mécanique'),
(15, 'Thermistances'),
(16, 'Varistances'),
(17, 'Optoélectronique'),
(18, 'Modules et cartes');

-- Structure de la table `category_sub`
CREATE TABLE `category_sub` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `category_head_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Structure de la table `location`
CREATE TABLE `location` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `casier` varchar(50) NOT NULL,
  `tiroir` varchar(50) NOT NULL,
  `compartiment` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `owner` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Structure de la table `data`
CREATE TABLE `data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `subcategory` varchar(100) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `location_id` int(11) DEFAULT NULL,
  `owner` int(11) NOT NULL DEFAULT 1,
  `image_path` varchar(500) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;