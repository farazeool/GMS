-- ============================================================
-- BrightBlaze – Milestone 9: BEFORE INSERT Triggers for UUIDs
-- Auto-generate UUIDs for all synchronizable tables and sync tables.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. Triggers for business entity tables
-- ============================================================

DELIMITER $$

CREATE TRIGGER `trg_roles_uuid` BEFORE INSERT ON `roles`
FOR EACH ROW
BEGIN
  IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
    SET NEW.uuid = UUID();
  END IF;
END$$

CREATE TRIGGER `trg_users_uuid` BEFORE INSERT ON `users`
FOR EACH ROW
BEGIN
  IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
    SET NEW.uuid = UUID();
  END IF;
END$$

CREATE TRIGGER `trg_customers_uuid` BEFORE INSERT ON `customers`
FOR EACH ROW
BEGIN
  IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
    SET NEW.uuid = UUID();
  END IF;
END$$

CREATE TRIGGER `trg_vehicles_uuid` BEFORE INSERT ON `vehicles`
FOR EACH ROW
BEGIN
  IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
    SET NEW.uuid = UUID();
  END IF;
END$$

CREATE TRIGGER `trg_job_cards_uuid` BEFORE INSERT ON `job_cards`
FOR EACH ROW
BEGIN
  IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
    SET NEW.uuid = UUID();
  END IF;
END$$

CREATE TRIGGER `trg_service_notes_uuid` BEFORE INSERT ON `service_notes`
FOR EACH ROW
BEGIN
  IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
    SET NEW.uuid = UUID();
  END IF;
END$$

CREATE TRIGGER `trg_maintenance_records_uuid` BEFORE INSERT ON `maintenance_records`
FOR EACH ROW
BEGIN
  IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
    SET NEW.uuid = UUID();
  END IF;
END$$

CREATE TRIGGER `trg_report_logs_uuid` BEFORE INSERT ON `report_logs`
FOR EACH ROW
BEGIN
  IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
    SET NEW.uuid = UUID();
  END IF;
END$$

CREATE TRIGGER `trg_settings_uuid` BEFORE INSERT ON `settings`
FOR EACH ROW
BEGIN
  IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
    SET NEW.uuid = UUID();
  END IF;
END$$

DELIMITER ;

-- ============================================================
-- 2. Triggers for sync tables
-- ============================================================

DELIMITER $$

CREATE TRIGGER `trg_sync_queue_uuid` BEFORE INSERT ON `sync_queue`
FOR EACH ROW
BEGIN
  IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
    SET NEW.uuid = UUID();
  END IF;
END$$

CREATE TRIGGER `trg_sync_conflicts_uuid` BEFORE INSERT ON `sync_conflicts`
FOR EACH ROW
BEGIN
  IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
    SET NEW.uuid = UUID();
  END IF;
END$$

DELIMITER ;

SET FOREIGN_KEY_CHECKS = 1;