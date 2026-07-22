-- ============================================================
-- BrightBlaze – Milestone 10: Commercial Garage Suite
-- Inventory, Invoices, Payments, Quotations, Portal, Notifications
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- Inventory Categories
-- ============================================================
CREATE TABLE `inventory_categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `name` VARCHAR(80) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_cat_uuid` (`uuid`),
  UNIQUE KEY `uq_inv_cat_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Inventory Suppliers
-- ============================================================
CREATE TABLE `inventory_suppliers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `contact_person` VARCHAR(80) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(120) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_sup_uuid` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Inventory Items
-- ============================================================
CREATE TABLE `inventory_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `sku` VARCHAR(60) NOT NULL,
  `barcode` VARCHAR(60) DEFAULT NULL,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `supplier_id` INT UNSIGNED DEFAULT NULL,
  `purchase_price` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `selling_price` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `minimum_stock` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `reorder_level` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `warehouse_location` VARCHAR(60) DEFAULT NULL,
  `unit` VARCHAR(30) NOT NULL DEFAULT 'piece' COMMENT 'piece, liter, kg, box, etc.',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `sync_status` ENUM('synced','pending','conflict') NOT NULL DEFAULT 'synced',
  `sync_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `last_synced_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_item_uuid` (`uuid`),
  UNIQUE KEY `uq_inv_item_sku` (`sku`),
  KEY `idx_inv_item_category` (`category_id`),
  KEY `idx_inv_item_supplier` (`supplier_id`),
  KEY `idx_inv_item_low_stock` (`quantity`, `minimum_stock`),
  CONSTRAINT `fk_inv_item_category` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_item_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `inventory_suppliers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Inventory Movements (stock history + adjustments)
-- ============================================================
CREATE TABLE `inventory_movements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `item_id` INT UNSIGNED NOT NULL,
  `type` ENUM('purchase','sale','adjustment','return','transfer_in','transfer_out','job_usage') NOT NULL,
  `quantity_change` DECIMAL(10,2) NOT NULL COMMENT 'Positive = increase, Negative = decrease',
  `quantity_before` DECIMAL(10,2) NOT NULL,
  `quantity_after` DECIMAL(10,2) NOT NULL,
  `reference_type` VARCHAR(40) DEFAULT NULL COMMENT 'job_card, invoice, purchase_order',
  `reference_id` INT UNSIGNED DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_mov_uuid` (`uuid`),
  KEY `idx_inv_mov_item` (`item_id`),
  KEY `idx_inv_mov_type` (`type`),
  KEY `idx_inv_mov_reference` (`reference_type`, `reference_id`),
  CONSTRAINT `fk_inv_mov_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Job Card Parts (parts used on a job)
-- ============================================================
CREATE TABLE `job_card_parts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `job_card_id` INT UNSIGNED NOT NULL,
  `item_id` INT UNSIGNED NOT NULL,
  `quantity_used` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `unit_cost` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `sale_price` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `subtotal` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_job_part_uuid` (`uuid`),
  KEY `idx_job_part_job` (`job_card_id`),
  KEY `idx_job_part_item` (`item_id`),
  CONSTRAINT `fk_job_part_job` FOREIGN KEY (`job_card_id`) REFERENCES `job_cards`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_job_part_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Quotations (estimates)
-- ============================================================
CREATE TABLE `quotations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `quotation_number` VARCHAR(30) NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `vehicle_id` INT UNSIGNED NOT NULL,
  `status` ENUM('draft','approved','rejected','converted') NOT NULL DEFAULT 'draft',
  `subtotal` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `discount` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `total` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `job_card_id` INT UNSIGNED DEFAULT NULL COMMENT 'Set when converted to job card',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_quot_uuid` (`uuid`),
  UNIQUE KEY `uq_quot_number` (`quotation_number`),
  KEY `idx_quot_customer` (`customer_id`),
  KEY `idx_quot_vehicle` (`vehicle_id`),
  KEY `idx_quot_status` (`status`),
  CONSTRAINT `fk_quot_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`),
  CONSTRAINT `fk_quot_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Quotation Lines
-- ============================================================
CREATE TABLE `quotation_lines` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `quotation_id` INT UNSIGNED NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `line_total` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `item_id` INT UNSIGNED DEFAULT NULL COMMENT 'Optional link to inventory item',
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_quot_line_uuid` (`uuid`),
  KEY `idx_quot_line_quotation` (`quotation_id`),
  CONSTRAINT `fk_quot_line_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Invoices
-- ============================================================
CREATE TABLE `invoices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `invoice_number` VARCHAR(30) NOT NULL,
  `job_card_id` INT UNSIGNED DEFAULT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `vehicle_id` INT UNSIGNED DEFAULT NULL,
  `subtotal` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `discount` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `total` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `paid_amount` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `balance` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `status` ENUM('draft','sent','partial','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
  `due_date` DATE DEFAULT NULL,
  `paid_date` DATETIME DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_uuid` (`uuid`),
  UNIQUE KEY `uq_inv_number` (`invoice_number`),
  KEY `idx_inv_job` (`job_card_id`),
  KEY `idx_inv_customer` (`customer_id`),
  KEY `idx_inv_status` (`status`),
  CONSTRAINT `fk_inv_job` FOREIGN KEY (`job_card_id`) REFERENCES `job_cards`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inv_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Invoice Lines
-- ============================================================
CREATE TABLE `invoice_lines` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `invoice_id` INT UNSIGNED NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `line_total` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `item_id` INT UNSIGNED DEFAULT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_line_uuid` (`uuid`),
  KEY `idx_inv_line_invoice` (`invoice_id`),
  CONSTRAINT `fk_inv_line_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Payments
-- ============================================================
CREATE TABLE `payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `invoice_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(10,3) NOT NULL,
  `payment_method` ENUM('cash','card','bank_transfer','other') NOT NULL DEFAULT 'cash',
  `reference_number` VARCHAR(60) DEFAULT NULL COMMENT 'Transaction ID, cheque number, etc.',
  `payment_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pay_uuid` (`uuid`),
  KEY `idx_pay_invoice` (`invoice_id`),
  CONSTRAINT `fk_pay_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Audit Log
-- ============================================================
CREATE TABLE `audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(60) NOT NULL COMMENT 'created, updated, deleted, payment, adjustment, etc.',
  `entity_type` VARCHAR(50) NOT NULL COMMENT 'customers, vehicles, inventory, invoices, etc.',
  `entity_id` INT UNSIGNED DEFAULT NULL,
  `entity_uuid` CHAR(36) DEFAULT NULL,
  `old_values` JSON DEFAULT NULL,
  `new_values` JSON DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_audit_uuid` (`uuid`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_entity` (`entity_type`, `entity_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Notifications
-- ============================================================
CREATE TABLE `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `type` VARCHAR(40) NOT NULL COMMENT 'job_assigned, job_completed, quotation_ready, invoice_created, payment_received, service_reminder',
  `channel` ENUM('email','sms','whatsapp','in_app') NOT NULL DEFAULT 'email',
  `recipient_type` ENUM('customer','technician','admin','all_admins') NOT NULL,
  `recipient_id` INT UNSIGNED DEFAULT NULL COMMENT 'User ID or Customer ID',
  `recipient_address` VARCHAR(255) DEFAULT NULL COMMENT 'Email or phone number',
  `subject` VARCHAR(255) NOT NULL,
  `body` TEXT NOT NULL,
  `status` ENUM('pending','sent','failed','read') NOT NULL DEFAULT 'pending',
  `sent_at` TIMESTAMP NULL DEFAULT NULL,
  `read_at` TIMESTAMP NULL DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notif_uuid` (`uuid`),
  KEY `idx_notif_status` (`status`),
  KEY `idx_notif_type` (`type`),
  KEY `idx_notif_recipient` (`recipient_type`, `recipient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Service Reminders
-- ============================================================
CREATE TABLE `service_reminders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `vehicle_id` INT UNSIGNED NOT NULL,
  `reminder_type` ENUM('mileage','months','custom') NOT NULL DEFAULT 'mileage',
  `interval_value` INT UNSIGNED NOT NULL COMMENT 'Every N km or N months',
  `last_odometer` INT UNSIGNED DEFAULT NULL COMMENT 'Odometer at last service',
  `last_service_date` DATE DEFAULT NULL,
  `next_due_odometer` INT UNSIGNED DEFAULT NULL,
  `next_due_date` DATE DEFAULT NULL,
  `description` VARCHAR(255) NOT NULL,
  `status` ENUM('active','triggered','completed','cancelled') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rem_uuid` (`uuid`),
  KEY `idx_rem_vehicle` (`vehicle_id`),
  KEY `idx_rem_status` (`status`),
  KEY `idx_rem_next_date` (`next_due_date`),
  CONSTRAINT `fk_rem_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Customer Portal Users
-- ============================================================
CREATE TABLE `portal_users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `email` VARCHAR(120) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_portal_uuid` (`uuid`),
  UNIQUE KEY `uq_portal_email` (`email`),
  KEY `idx_portal_customer` (`customer_id`),
  CONSTRAINT `fk_portal_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Triggers for UUID auto-generation
-- ============================================================
DROP TRIGGER IF EXISTS `trg_inventory_categories_uuid`;
CREATE TRIGGER `trg_inventory_categories_uuid` BEFORE INSERT ON `inventory_categories`
FOR EACH ROW BEGIN IF NEW.uuid IS NULL OR NEW.uuid = '' THEN SET NEW.uuid = UUID(); END IF; END;

DROP TRIGGER IF EXISTS `trg_inventory_suppliers_uuid`;
CREATE TRIGGER `trg_inventory_suppliers_uuid` BEFORE INSERT ON `inventory_suppliers`
FOR EACH ROW BEGIN IF NEW.uuid IS NULL OR NEW.uuid = '' THEN SET NEW.uuid = UUID(); END IF; END;

DROP TRIGGER IF EXISTS `trg_inventory_items_uuid`;
CREATE TRIGGER `trg_inventory_items_uuid` BEFORE INSERT ON `inventory_items`
FOR EACH ROW BEGIN IF NEW.uuid IS NULL OR NEW.uuid = '' THEN SET NEW.uuid = UUID(); END IF; END;

DROP TRIGGER IF EXISTS `trg_inventory_movements_uuid`;
CREATE TRIGGER `trg_inventory_movements_uuid` BEFORE INSERT ON `inventory_movements`
FOR EACH ROW BEGIN IF NEW.uuid IS NULL OR NEW.uuid = '' THEN SET NEW.uuid = UUID(); END IF; END;

DROP TRIGGER IF EXISTS `trg_job_card_parts_uuid`;
CREATE TRIGGER `trg_job_card_parts_uuid` BEFORE INSERT ON `job_card_parts`
FOR EACH ROW BEGIN IF NEW.uuid IS NULL OR NEW.uuid = '' THEN SET NEW.uuid = UUID(); END IF; END;

DROP TRIGGER IF EXISTS `trg_quotations_uuid`;
CREATE TRIGGER `trg_quotations_uuid` BEFORE INSERT ON `quotations`
FOR EACH ROW BEGIN IF NEW.uuid IS NULL OR NEW.uuid = '' THEN SET NEW.uuid = UUID(); END IF; END;

DROP TRIGGER IF EXISTS `trg_quotation_lines_uuid`;
CREATE TRIGGER `trg_quotation_lines_uuid` BEFORE INSERT ON `quotation_lines`
FOR EACH ROW BEGIN IF NEW.uuid IS NULL OR NEW.uuid = '' THEN SET NEW.uuid = UUID(); END IF; END;

DROP TRIGGER IF EXISTS `trg_invoices_uuid`;
CREATE TRIGGER `trg_invoices_uuid` BEFORE INSERT ON `invoices`
FOR EACH ROW BEGIN IF NEW.uuid IS NULL OR NEW.uuid = '' THEN SET NEW.uuid = UUID(); END IF; END;

DROP TRIGGER IF EXISTS `trg_invoice_lines_uuid`;
CREATE TRIGGER `trg_invoice_lines_uuid` BEFORE INSERT ON `invoice_lines`
FOR EACH ROW BEGIN IF NEW.uuid IS NULL OR NEW.uuid = '' THEN SET NEW.uuid = UUID(); END IF; END;

DROP TRIGGER IF EXISTS `trg_payments_uuid`;
CREATE TRIGGER `trg_payments_uuid` BEFORE INSERT ON `payments`
FOR EACH ROW BEGIN IF NEW.uuid IS NULL OR NEW.uuid = '' THEN SET NEW.uuid = UUID(); END IF; END;

DROP TRIGGER IF EXISTS `trg_audit_log_uuid`;
CREATE TRIGGER `trg_audit_log_uuid` BEFORE INSERT ON `audit_log`
FOR EACH ROW BEGIN IF NEW.uuid IS NULL OR NEW.uuid = '' THEN SET NEW.uuid = UUID(); END IF; END;

DROP TRIGGER IF EXISTS `trg_notifications_uuid`;
CREATE TRIGGER `trg_notifications_uuid` BEFORE INSERT ON `notifications`
FOR EACH ROW BEGIN IF NEW.uuid IS NULL OR NEW.uuid = '' THEN SET NEW.uuid = UUID(); END IF; END;

DROP TRIGGER IF EXISTS `trg_service_reminders_uuid`;
CREATE TRIGGER `trg_service_reminders_uuid` BEFORE INSERT ON `service_reminders`
FOR EACH ROW BEGIN IF NEW.uuid IS NULL OR NEW.uuid = '' THEN SET NEW.uuid = UUID(); END IF; END;

DROP TRIGGER IF EXISTS `trg_portal_users_uuid`;
CREATE TRIGGER `trg_portal_users_uuid` BEFORE INSERT ON `portal_users`
FOR EACH ROW BEGIN IF NEW.uuid IS NULL OR NEW.uuid = '' THEN SET NEW.uuid = UUID(); END IF; END;

SET FOREIGN_KEY_CHECKS = 1;