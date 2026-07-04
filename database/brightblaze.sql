-- ============================================================
-- BrightBlaze – Garage Management & Job Card System
-- MySQL schema + seed data (phpMyAdmin / XAMPP compatible)
-- Import via phpMyAdmin > Import
-- ============================================================

CREATE DATABASE IF NOT EXISTS `brightblaze_garage`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `brightblaze_garage`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `report_logs`, `service_notes`, `maintenance_records`, `job_cards`, `vehicles`, `customers`, `users`, `roles`;
SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Tables
-- ------------------------------------------------------------

CREATE TABLE `roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(80) NOT NULL,
  `full_name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(120) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role_id` INT UNSIGNED NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_role` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(120) DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customers_name` (`name`),
  KEY `idx_customers_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vehicles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` INT UNSIGNED NOT NULL,
  `plate_number` VARCHAR(20) NOT NULL,
  `make` VARCHAR(60) NOT NULL,
  `model` VARCHAR(60) NOT NULL,
  `year` SMALLINT UNSIGNED DEFAULT NULL,
  `color` VARCHAR(40) DEFAULT NULL,
  `vin` VARCHAR(30) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vehicles_plate` (`plate_number`),
  UNIQUE KEY `uq_vehicles_vin` (`vin`),
  KEY `idx_vehicles_customer` (`customer_id`),
  CONSTRAINT `fk_vehicles_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `job_cards` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_number` VARCHAR(20) NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `vehicle_id` INT UNSIGNED NOT NULL,
  `service_category` VARCHAR(60) NOT NULL,
  `technician_id` INT UNSIGNED DEFAULT NULL,
  `priority` ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium',
  `status` ENUM('Pending','Assigned','In Progress','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `problem_description` TEXT NOT NULL,
  `estimated_completion` DATE DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_job_cards_number` (`job_number`),
  KEY `idx_job_cards_status` (`status`),
  KEY `idx_job_cards_priority` (`priority`),
  KEY `idx_job_cards_customer` (`customer_id`),
  KEY `idx_job_cards_vehicle` (`vehicle_id`),
  KEY `idx_job_cards_technician` (`technician_id`),
  CONSTRAINT `fk_job_cards_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_job_cards_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_job_cards_technician` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `service_notes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_card_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `note` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_service_notes_job` (`job_card_id`),
  KEY `idx_service_notes_user` (`user_id`),
  CONSTRAINT `fk_service_notes_job` FOREIGN KEY (`job_card_id`) REFERENCES `job_cards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_service_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `maintenance_records` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vehicle_id` INT UNSIGNED NOT NULL,
  `job_card_id` INT UNSIGNED DEFAULT NULL,
  `description` VARCHAR(255) NOT NULL,
  `service_date` DATE NOT NULL,
  `cost` DECIMAL(10,3) DEFAULT NULL COMMENT 'Cost in KWD',
  `odometer_km` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_maintenance_vehicle` (`vehicle_id`),
  KEY `idx_maintenance_job` (`job_card_id`),
  CONSTRAINT `fk_maintenance_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_maintenance_job` FOREIGN KEY (`job_card_id`) REFERENCES `job_cards` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `report_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `report_type` VARCHAR(50) NOT NULL,
  `filters` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report_logs_user` (`user_id`),
  CONSTRAINT `fk_report_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Seed data
-- All user accounts use the password: password
-- (bcrypt hash below). Change after first login.
-- ------------------------------------------------------------

INSERT INTO `roles` (`id`, `name`) VALUES
(1, 'admin'),
(2, 'technician');

INSERT INTO `users` (`id`, `username`, `full_name`, `email`, `phone`, `password_hash`, `role_id`, `is_active`) VALUES
(1, 'admin',  'Yousef Al-Mutairi', 'admin@brightblaze.com.kw',  '+965 9900 0001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1),
(2, 'hamad',  'Hamad Al-Enezi',    'hamad@brightblaze.com.kw',  '+965 9900 0002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 1),
(3, 'rajesh', 'Rajesh Kumar',      'rajesh@brightblaze.com.kw', '+965 9900 0003', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 1),
(4, 'joseph', 'Joseph Mathew',     'joseph@brightblaze.com.kw', '+965 9900 0004', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 1);

INSERT INTO `customers` (`id`, `name`, `phone`, `email`, `address`) VALUES
(1, 'Fahad Al-Sabah',      '+965 9911 2233', 'fahad.alsabah@gmail.com',    'Block 10, Street 5, Salmiya, Hawalli'),
(2, 'Noura Al-Rashid',     '+965 6600 4455', 'noura.alrashid@hotmail.com', 'Block 4, Tunis Street, Hawally'),
(3, 'Abdullah Al-Kandari', '+965 5522 7788', 'a.alkandari@outlook.com',    'Block 1, Habeeb Munawer St, Farwaniya'),
(4, 'Mariam Al-Ajmi',      '+965 9788 1122', 'mariam.alajmi@gmail.com',    'Block 3, Al-Jahra City, Jahra'),
(5, 'Salem Al-Otaibi',     '+965 6655 9900', 'salem.otaibi@gmail.com',     'Block 8, Mecca Street, Fahaheel, Ahmadi'),
(6, 'Dana Al-Failakawi',   '+965 9977 3311', 'dana.failakawi@gmail.com',   'Block 2, Ahmad Al-Jaber St, Kuwait City');

INSERT INTO `vehicles` (`id`, `customer_id`, `plate_number`, `make`, `model`, `year`, `color`, `vin`) VALUES
(1, 1, '8/24173',  'Toyota',     'Land Cruiser', 2022, 'White',      'JTMHY7AJ5N4098765'),
(2, 1, '8/55210',  'Lexus',      'LX600',        2023, 'Black',      'JTJHY7AX8P4712345'),
(3, 2, '7/61842',  'Nissan',     'Patrol',       2021, 'Silver',     'JN8AY2NY5M9034567'),
(4, 3, '60/33871', 'Chevrolet',  'Tahoe',        2019, 'Dark Gray',  '1GNSKBKC0KR223344'),
(5, 4, '17/90234', 'Toyota',     'Camry',        2020, 'Pearl White','4T1B11HK5LU556677'),
(6, 5, '9/44567',  'Mitsubishi', 'Pajero',       2018, 'Red',        'JMYLYV98WJJ889900'),
(7, 6, '5/78120',  'GMC',        'Yukon',        2022, 'Black',      '1GKS2BKC8NR112233'),
(8, 6, '5/12908',  'Honda',      'Accord',       2017, 'Blue',       '1HGCR2F3XHA445566');

INSERT INTO `job_cards`
(`id`, `job_number`, `customer_id`, `vehicle_id`, `service_category`, `technician_id`, `priority`, `status`, `problem_description`, `estimated_completion`, `created_at`, `completed_at`) VALUES
(1,  'JC-2026-0001', 1, 1, 'AC Repair',            2, 'High',   'In Progress', 'AC blowing warm air. Suspected compressor failure. Customer reports issue is worse in afternoon heat.', DATE_ADD(CURDATE(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), NULL),
(2,  'JC-2026-0002', 2, 3, 'General Service',      3, 'Medium', 'Completed',   'Full 40,000 km service: oil change, filters, brake inspection, fluid top-up.', CURDATE(), DATE_SUB(NOW(), INTERVAL 1 DAY), NOW()),
(3,  'JC-2026-0003', 3, 4, 'Brakes & Suspension',  4, 'High',   'Assigned',    'Grinding noise when braking at low speed. Front pads and discs need inspection.', DATE_ADD(CURDATE(), INTERVAL 2 DAY), NOW(), NULL),
(4,  'JC-2026-0004', 4, 5, 'Diagnostics',          NULL, 'Medium', 'Pending',  'Check engine light on. Intermittent rough idle. Needs OBD scan.', DATE_ADD(CURDATE(), INTERVAL 3 DAY), NOW(), NULL),
(5,  'JC-2026-0005', 5, 6, 'Engine Repair',        2, 'High',   'In Progress', 'Overheating on highway. Coolant loss, possible radiator or head gasket issue.', DATE_ADD(CURDATE(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY), NULL),
(6,  'JC-2026-0006', 6, 7, 'Tyres & Alignment',    3, 'Low',    'Completed',   'Replace 4 tyres (285/45R22) and perform wheel alignment.', DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
(7,  'JC-2026-0007', 6, 8, 'Electrical',           4, 'Medium', 'Assigned',    'Left headlight and dashboard cluster flickering. Possible wiring or alternator issue.', DATE_ADD(CURDATE(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), NULL),
(8,  'JC-2026-0008', 1, 2, 'Bodywork & Paint',     NULL, 'Low',  'Pending',    'Scratches and small dent on rear right door. Customer wants repaint of panel.', DATE_ADD(CURDATE(), INTERVAL 7 DAY), NOW(), NULL),
(9,  'JC-2026-0009', 4, 5, 'AC Repair',            3, 'Medium', 'Cancelled',   'AC gas refill requested. Customer cancelled after price estimate.', NULL, DATE_SUB(NOW(), INTERVAL 10 DAY), NULL),
(10, 'JC-2026-0010', 1, 1, 'General Service',      2, 'Low',    'Completed',   '30,000 km scheduled maintenance: oil, filters, tyre rotation.', DATE_SUB(CURDATE(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 21 DAY), DATE_SUB(NOW(), INTERVAL 20 DAY));

INSERT INTO `service_notes` (`job_card_id`, `user_id`, `note`, `created_at`) VALUES
(1, 2, 'Confirmed compressor clutch not engaging. Ordered replacement compressor, expected tomorrow morning.', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 3, 'Service completed. Brake pads at 60%, recommended recheck at next service.', NOW()),
(5, 2, 'Pressure test done. Radiator leak confirmed at top tank. Awaiting customer approval for replacement.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(6, 3, 'Tyres replaced and alignment completed. Test drive OK.', DATE_SUB(NOW(), INTERVAL 3 DAY));

INSERT INTO `maintenance_records` (`vehicle_id`, `job_card_id`, `description`, `service_date`, `cost`, `odometer_km`) VALUES
(3, 2,  'General Service: 40,000 km full service', CURDATE(), 45.500, 40120),
(7, 6,  'Tyres & Alignment: 4 new tyres + alignment', DATE_SUB(CURDATE(), INTERVAL 3 DAY), 320.000, 38500),
(1, 10, 'General Service: 30,000 km scheduled maintenance', DATE_SUB(CURDATE(), INTERVAL 20 DAY), 38.750, 30210);
