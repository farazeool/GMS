-- Milestone 4 migration for EXISTING installs only.
-- Fresh imports of database/brightblaze.sql already include this column.
-- Run in phpMyAdmin > SQL.

USE `brightblaze_garage`;

ALTER TABLE `maintenance_records`
  ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;
