-- Milestone 7 migration for EXISTING installs only.
-- Adds sync metadata fields and sync logs table.
-- Run in phpMyAdmin > SQL (after earlier migrations).

USE `brightblaze_garage`;

ALTER TABLE `customers`
  ADD COLUMN `sync_status` ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending' AFTER `updated_at`,
  ADD COLUMN `synced_at` DATETIME DEFAULT NULL AFTER `sync_status`,
  ADD COLUMN `local_updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `synced_at`;

ALTER TABLE `vehicles`
  ADD COLUMN `sync_status` ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending' AFTER `updated_at`,
  ADD COLUMN `synced_at` DATETIME DEFAULT NULL AFTER `sync_status`,
  ADD COLUMN `local_updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `synced_at`;

ALTER TABLE `job_cards`
  ADD COLUMN `sync_status` ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending' AFTER `updated_at`,
  ADD COLUMN `synced_at` DATETIME DEFAULT NULL AFTER `sync_status`,
  ADD COLUMN `local_updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `synced_at`;

ALTER TABLE `service_notes`
  ADD COLUMN `sync_status` ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending' AFTER `created_at`,
  ADD COLUMN `synced_at` DATETIME DEFAULT NULL AFTER `sync_status`,
  ADD COLUMN `local_updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `synced_at`;

ALTER TABLE `maintenance_records`
  ADD COLUMN `sync_status` ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending' AFTER `updated_at`,
  ADD COLUMN `synced_at` DATETIME DEFAULT NULL AFTER `sync_status`,
  ADD COLUMN `local_updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `synced_at`;

CREATE TABLE IF NOT EXISTS `sync_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `level` VARCHAR(20) NOT NULL,
  `message` VARCHAR(500) NOT NULL,
  `context_json` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sync_logs_created_at` (`created_at`),
  KEY `idx_sync_logs_created_by` (`created_by`),
  CONSTRAINT `fk_sync_logs_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
