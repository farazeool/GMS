-- ============================================================
-- BrightBlaze – Milestone 7: Offline Synchronization Infrastructure
-- Adds UUID columns, soft-delete tracking, sync state columns
-- Creates sync_queue, sync_state, sync_conflicts tables
--
-- UUIDs are nullable initially; PHP backfills, then adds constraints.
-- Triggers auto-generate UUIDs for future inserts.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. Add UUID + sync tracking columns to all synchronizable tables
--    All uuid columns are nullable initially to avoid conflicts
--    with existing empty-string defaults.
-- ============================================================

ALTER TABLE `roles`
  ADD COLUMN `uuid` CHAR(36) NULL AFTER `id`,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `created_at`,
  ADD COLUMN `sync_status` ENUM('synced','pending','conflict') NOT NULL DEFAULT 'synced' AFTER `deleted_at`,
  ADD COLUMN `sync_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `sync_status`,
  ADD COLUMN `last_synced_at` TIMESTAMP NULL DEFAULT NULL AFTER `sync_version`;

ALTER TABLE `users`
  ADD COLUMN `uuid` CHAR(36) NULL AFTER `id`,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `updated_at`,
  ADD COLUMN `sync_status` ENUM('synced','pending','conflict') NOT NULL DEFAULT 'synced' AFTER `deleted_at`,
  ADD COLUMN `sync_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `sync_status`,
  ADD COLUMN `last_synced_at` TIMESTAMP NULL DEFAULT NULL AFTER `sync_version`;

ALTER TABLE `customers`
  ADD COLUMN `uuid` CHAR(36) NULL AFTER `id`,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `updated_at`,
  ADD COLUMN `sync_status` ENUM('synced','pending','conflict') NOT NULL DEFAULT 'synced' AFTER `deleted_at`,
  ADD COLUMN `sync_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `sync_status`,
  ADD COLUMN `last_synced_at` TIMESTAMP NULL DEFAULT NULL AFTER `sync_version`;

ALTER TABLE `vehicles`
  ADD COLUMN `uuid` CHAR(36) NULL AFTER `id`,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `updated_at`,
  ADD COLUMN `sync_status` ENUM('synced','pending','conflict') NOT NULL DEFAULT 'synced' AFTER `deleted_at`,
  ADD COLUMN `sync_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `sync_status`,
  ADD COLUMN `last_synced_at` TIMESTAMP NULL DEFAULT NULL AFTER `sync_version`;

ALTER TABLE `job_cards`
  ADD COLUMN `uuid` CHAR(36) NULL AFTER `id`,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `updated_at`,
  ADD COLUMN `sync_status` ENUM('synced','pending','conflict') NOT NULL DEFAULT 'synced' AFTER `deleted_at`,
  ADD COLUMN `sync_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `sync_status`,
  ADD COLUMN `last_synced_at` TIMESTAMP NULL DEFAULT NULL AFTER `sync_version`;

ALTER TABLE `service_notes`
  ADD COLUMN `uuid` CHAR(36) NULL AFTER `id`,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `created_at`,
  ADD COLUMN `sync_status` ENUM('synced','pending','conflict') NOT NULL DEFAULT 'synced' AFTER `deleted_at`,
  ADD COLUMN `sync_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `sync_status`,
  ADD COLUMN `last_synced_at` TIMESTAMP NULL DEFAULT NULL AFTER `sync_version`;

ALTER TABLE `maintenance_records`
  ADD COLUMN `uuid` CHAR(36) NULL AFTER `id`,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `updated_at`,
  ADD COLUMN `sync_status` ENUM('synced','pending','conflict') NOT NULL DEFAULT 'synced' AFTER `deleted_at`,
  ADD COLUMN `sync_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `sync_status`,
  ADD COLUMN `last_synced_at` TIMESTAMP NULL DEFAULT NULL AFTER `sync_version`;

ALTER TABLE `report_logs`
  ADD COLUMN `uuid` CHAR(36) NULL AFTER `id`,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `created_at`,
  ADD COLUMN `sync_status` ENUM('synced','pending','conflict') NOT NULL DEFAULT 'synced' AFTER `deleted_at`,
  ADD COLUMN `sync_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `sync_status`,
  ADD COLUMN `last_synced_at` TIMESTAMP NULL DEFAULT NULL AFTER `sync_version`;

ALTER TABLE `settings`
  ADD COLUMN `uuid` CHAR(36) NULL AFTER `setting_key`,
  ADD COLUMN `deleted_at` TIMESTAMP NULL DEFAULT NULL AFTER `updated_at`,
  ADD COLUMN `sync_status` ENUM('synced','pending','conflict') NOT NULL DEFAULT 'synced' AFTER `deleted_at`,
  ADD COLUMN `sync_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `sync_status`,
  ADD COLUMN `last_synced_at` TIMESTAMP NULL DEFAULT NULL AFTER `sync_version`;

-- ============================================================
-- 2. Create sync_queue table
-- ============================================================

CREATE TABLE `sync_queue` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` CHAR(36) NOT NULL,
    `entity_type` VARCHAR(50) NOT NULL COMMENT 'Table name: customers, vehicles, job_cards, etc.',
    `entity_uuid` CHAR(36) NOT NULL COMMENT 'UUID of the record being synced',
    `operation` ENUM('create','update','delete') NOT NULL,
    `payload` JSON NOT NULL,
    `status` ENUM('pending','syncing','completed','failed','retry_scheduled') NOT NULL DEFAULT 'pending',
    `attempt_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `last_error` TEXT DEFAULT NULL,
    `scheduled_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `started_at` TIMESTAMP NULL DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sync_queue_uuid` (`uuid`),
    KEY `idx_sync_queue_entity` (`entity_type`, `entity_uuid`),
    KEY `idx_sync_queue_status` (`status`),
    KEY `idx_sync_queue_scheduled` (`scheduled_at`),
    KEY `idx_sync_queue_status_scheduled` (`status`, `scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. Create sync_state table
-- ============================================================

CREATE TABLE `sync_state` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key_name` VARCHAR(100) NOT NULL,
    `key_value` TEXT NOT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sync_state_key` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. Create sync_conflicts table
-- ============================================================

CREATE TABLE `sync_conflicts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` CHAR(36) NOT NULL,
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_uuid` CHAR(36) NOT NULL,
    `local_data` JSON NOT NULL,
    `remote_data` JSON NOT NULL,
    `resolution_strategy` ENUM('local_wins','remote_wins','merge','manual') NOT NULL DEFAULT 'manual',
    `resolved_data` JSON DEFAULT NULL,
    `status` ENUM('detected','resolving','resolved','ignored') NOT NULL DEFAULT 'detected',
    `detected_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolved_at` TIMESTAMP NULL DEFAULT NULL,
    `resolved_by` INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sync_conflicts_uuid` (`uuid`),
    KEY `idx_sync_conflicts_entity` (`entity_type`, `entity_uuid`),
    KEY `idx_sync_conflicts_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. Seed initial sync_state values
-- ============================================================

INSERT INTO `sync_state` (`key_name`, `key_value`) VALUES
('last_sync_at', ''),
('last_push_at', ''),
('last_pull_at', ''),
('sync_mode', 'local_only'),
('sync_version', '1')
ON DUPLICATE KEY UPDATE `key_value` = VALUES(`key_value`);

SET FOREIGN_KEY_CHECKS = 1;