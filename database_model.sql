-- CompoZ'IT Database Model
-- Structure complète de la base de données pour l'application de gestion de composants électroniques

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Table des utilisateurs
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des emplacements
CREATE TABLE `location` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner` int(11) NOT NULL,
  `casier` varchar(255) DEFAULT NULL,
  `tiroir` varchar(255) DEFAULT NULL,
  `compartiment` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des catégories principales
CREATE TABLE `category_head` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des sous-catégories
CREATE TABLE `category_sub` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `category_head_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des fournisseurs
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `owner` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table des contacts fournisseurs
CREATE TABLE `supplier_contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des fabricants
CREATE TABLE `manufacturers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `owner` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des packages/boîtiers
CREATE TABLE `packages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `pin_count` int(11) DEFAULT NULL,
  `package_type` enum('DIP','SOIC','QFP','BGA','TO','SOT','TSSOP','MSOP','QFN','DFN','PLCC','PGA','LGA','CSP','Other','DO') DEFAULT 'Other',
  `pitch` decimal(5,2) DEFAULT NULL,
  `dimensions` varchar(255) DEFAULT NULL,
  `mounting_type` enum('Through-hole','Surface-mount','Both') DEFAULT 'Through-hole',
  `notes` text DEFAULT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `owner` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des composants (data)
CREATE TABLE `data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner` int(11) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `manufacturer` varchar(255) DEFAULT NULL,
  `package` varchar(255) DEFAULT NULL,
  `pins` int(11) DEFAULT NULL,
  `smd` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `location_id` int(11) DEFAULT NULL,
  `order_quantity` int(11) DEFAULT 0,
  `price` decimal(10,2) DEFAULT NULL,
  `datasheet` varchar(255) DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `category` int(11) DEFAULT NULL,
  `public` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_reference` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `location_id` (`location_id`),
  KEY `owner` (`owner`),
  KEY `supplier_id` (`supplier_id`),
  CONSTRAINT `data_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `location` (`id`) ON DELETE SET NULL,
  CONSTRAINT `data_ibfk_2` FOREIGN KEY (`owner`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `data_ibfk_3` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des projets
CREATE TABLE `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `owner` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `status` enum('En cours','Terminé','En attente','Annulé') DEFAULT 'En cours',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table des composants de projets
CREATE TABLE `project_components` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `component_id` int(11) NOT NULL,
  `quantity_needed` int(11) DEFAULT 1,
  `quantity_used` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table des fichiers de projets
CREATE TABLE `project_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_category` enum('document','photo','datasheet','programme','autre','schema') DEFAULT 'autre',
  `description` text DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table des éléments de projet (travaux et matériaux)
CREATE TABLE `project_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `type` enum('travail','matériel','service') NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `quantity_completed` decimal(10,2) DEFAULT 0.00,
  `unit` varchar(50) DEFAULT 'unité',
  `unit_price` decimal(10,2) DEFAULT 0.00,
  `total_price` decimal(10,2) GENERATED ALWAYS AS (`quantity` * `unit_price`) STORED,
  `status` enum('En attente','En cours','Terminé') DEFAULT 'En attente',
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insertion de données d'exemple pour les catégories
INSERT INTO `category_head` (`id`, `name`, `description`, `owner`) VALUES
(1, 'Résistances', 'Composants passifs pour limiter le courant', 1),
(2, 'Condensateurs', 'Composants de stockage d\'énergie électrique', 1),
(3, 'Semiconducteurs', 'Diodes, transistors et circuits intégrés', 1),
(4, 'Connecteurs', 'Éléments de connexion et interfaces', 1),
(5, 'Inductances', 'Bobines et transformateurs', 1);

INSERT INTO `category_sub` (`id`, `name`, `category_head_id`, `description`, `owner`) VALUES
(1, 'Résistances fixes', 1, 'Résistances à valeur fixe', 1),
(2, 'Résistances variables', 1, 'Potentiomètres et résistances ajustables', 1),
(3, 'Condensateurs céramique', 2, 'Condensateurs en céramique', 1),
(4, 'Condensateurs électrolytiques', 2, 'Condensateurs polarisés', 1),
(5, 'Diodes', 3, 'Diodes de redressement et signal', 1),
(6, 'Transistors', 3, 'Transistors bipolaires et FET', 1),
(7, 'Circuits intégrés', 3, 'Microcontrôleurs et amplificateurs', 1),
(8, 'Connecteurs PCB', 4, 'Connecteurs pour circuits imprimés', 1),
(9, 'Connecteurs externes', 4, 'Prises et fiches', 1);

-- Insertion de packages d'exemple
INSERT INTO `packages` (`id`, `name`, `description`, `image_path`, `owner`) VALUES
(1, 'DIP-8', 'Dual In-line Package 8 broches', 'img/DIP-8.svg', 1),
(2, 'SOT-23', 'Small Outline Transistor 3 broches', 'img/SOT-23.svg', 1),
(3, 'TO-220', 'Transistor Outline package', 'img/TO-220.svg', 1),
(4, '0805', 'Composant CMS 0805', NULL, 1),
(5, '1206', 'Composant CMS 1206', NULL, 1);

COMMIT;