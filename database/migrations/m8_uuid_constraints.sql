-- ============================================================
-- BrightBlaze – Milestone 8: UUID Constraints & Indexes
-- Adds unique constraints and indexes on UUID columns
-- now that existing data has been backfilled.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `roles` MODIFY `uuid` CHAR(36) NOT NULL,
  ADD UNIQUE KEY `uq_roles_uuid` (`uuid`),
  ADD INDEX `idx_roles_sync_status` (`sync_status`);

ALTER TABLE `users` MODIFY `uuid` CHAR(36) NOT NULL,
  ADD UNIQUE KEY `uq_users_uuid` (`uuid`),
  ADD INDEX `idx_users_sync_status` (`sync_status`);

ALTER TABLE `customers` MODIFY `uuid` CHAR(36) NOT NULL,
  ADD UNIQUE KEY `uq_customers_uuid` (`uuid`),
  ADD INDEX `idx_customers_sync_status` (`sync_status`);

ALTER TABLE `vehicles` MODIFY `uuid` CHAR(36) NOT NULL,
  ADD UNIQUE KEY `uq_vehicles_uuid` (`uuid`),
  ADD INDEX `idx_vehicles_sync_status` (`sync_status`);

ALTER TABLE `job_cards` MODIFY `uuid` CHAR(36) NOT NULL,
  ADD UNIQUE KEY `uq_job_cards_uuid` (`uuid`),
  ADD INDEX `idx_job_cards_sync_status` (`sync_status`);

ALTER TABLE `service_notes` MODIFY `uuid` CHAR(36) NOT NULL,
  ADD UNIQUE KEY `uq_service_notes_uuid` (`uuid`),
  ADD INDEX `idx_service_notes_sync_status` (`sync_status`);

ALTER TABLE `maintenance_records` MODIFY `uuid` CHAR(36) NOT NULL,
  ADD UNIQUE KEY `uq_maintenance_records_uuid` (`uuid`),
  ADD INDEX `idx_maintenance_records_sync_status` (`sync_status`);

ALTER TABLE `report_logs` MODIFY `uuid` CHAR(36) NOT NULL,
  ADD UNIQUE KEY `uq_report_logs_uuid` (`uuid`),
  ADD INDEX `idx_report_logs_sync_status` (`sync_status`);

ALTER TABLE `settings` MODIFY `uuid` CHAR(36) NOT NULL,
  ADD UNIQUE KEY `uq_settings_uuid` (`uuid`),
  ADD INDEX `idx_settings_sync_status` (`sync_status`);

SET FOREIGN_KEY_CHECKS = 1;
