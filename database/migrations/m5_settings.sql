-- Milestone 5 migration for EXISTING installs only.
-- Fresh imports of database/brightblaze.sql already include this table.
-- Run in phpMyAdmin > SQL (after m4_maintenance_updated_at.sql).

USE `brightblaze_garage`;

CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` VARCHAR(60) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
('garage_name',       'BrightBlaze Garage'),
('business_phone',    '+965 2222 0000'),
('business_email',    'info@brightblaze.com.kw'),
('business_address',  'Block 1, Canada Dry Street, Shuwaikh Industrial, Kuwait'),
('currency',          'KWD'),
('installation_mode', 'local'),
('sync_mode',         'local_only'),
('cloud_api_url',     ''),
('sync_api_key',      ''),
('last_sync_at',      ''),
('sync_status',       'local_only');
