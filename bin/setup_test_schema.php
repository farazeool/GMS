#!/usr/bin/env php
<?php
/**
 * Create the test database tables needed for Stage 6 CommercialTest.
 * Called from bootstrap.php during PHPUnit setup.
 * Uses individual CREATE TABLE statements that are safe for PDO.
 */

function create_commercial_test_tables(PDO $pdo): void
{
    // Inventory Categories
    $pdo->exec("CREATE TABLE IF NOT EXISTS `inventory_categories` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `uuid` CHAR(36) NOT NULL,
        `name` VARCHAR(80) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_inv_cat_uuid` (`uuid`),
        UNIQUE KEY `uq_inv_cat_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `inventory_suppliers` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // The rest need sync_status, sync_version, last_synced_at, deleted_at to match inventory.php queries
    $pdo->exec("CREATE TABLE IF NOT EXISTS `inventory_items` (
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
        `unit` VARCHAR(30) NOT NULL DEFAULT 'piece',
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
        KEY `idx_inv_item_low_stock` (`quantity`, `minimum_stock`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `inventory_movements` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `uuid` CHAR(36) NOT NULL,
        `item_id` INT UNSIGNED NOT NULL,
        `type` VARCHAR(30) NOT NULL,
        `quantity_change` DECIMAL(10,2) NOT NULL,
        `quantity_before` DECIMAL(10,2) NOT NULL,
        `quantity_after` DECIMAL(10,2) NOT NULL,
        `reference_type` VARCHAR(40) DEFAULT NULL,
        `reference_id` INT UNSIGNED DEFAULT NULL,
        `notes` TEXT DEFAULT NULL,
        `created_by` INT UNSIGNED DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_inv_mov_uuid` (`uuid`),
        KEY `idx_inv_mov_item` (`item_id`),
        KEY `idx_inv_mov_type` (`type`),
        KEY `idx_inv_mov_reference` (`reference_type`, `reference_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `job_card_parts` (
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
        KEY `idx_job_part_item` (`item_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `quotations` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `uuid` CHAR(36) NOT NULL,
        `quotation_number` VARCHAR(30) NOT NULL,
        `customer_id` INT UNSIGNED NOT NULL,
        `vehicle_id` INT UNSIGNED NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
        `subtotal` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
        `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        `tax_amount` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
        `discount` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
        `total` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
        `notes` TEXT DEFAULT NULL,
        `created_by` INT UNSIGNED DEFAULT NULL,
        `job_card_id` INT UNSIGNED DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `deleted_at` TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_quot_uuid` (`uuid`),
        UNIQUE KEY `uq_quot_number` (`quotation_number`),
        KEY `idx_quot_customer` (`customer_id`),
        KEY `idx_quot_vehicle` (`vehicle_id`),
        KEY `idx_quot_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `quotation_lines` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `uuid` CHAR(36) NOT NULL,
        `quotation_id` INT UNSIGNED NOT NULL,
        `description` VARCHAR(255) NOT NULL,
        `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
        `unit_price` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
        `line_total` DECIMAL(10,3) NOT NULL DEFAULT 0.000,
        `item_id` INT UNSIGNED DEFAULT NULL,
        `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_quot_line_uuid` (`uuid`),
        KEY `idx_quot_line_quotation` (`quotation_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `invoices` (
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
        `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
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
        KEY `idx_inv_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `invoice_lines` (
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
        KEY `idx_inv_line_invoice` (`invoice_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `payments` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `uuid` CHAR(36) NOT NULL,
        `invoice_id` INT UNSIGNED NOT NULL,
        `amount` DECIMAL(10,3) NOT NULL,
        `payment_method` VARCHAR(20) NOT NULL DEFAULT 'cash',
        `reference_number` VARCHAR(60) DEFAULT NULL,
        `payment_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `notes` TEXT DEFAULT NULL,
        `created_by` INT UNSIGNED DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_pay_uuid` (`uuid`),
        KEY `idx_pay_invoice` (`invoice_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_log` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `uuid` CHAR(36) NOT NULL,
        `user_id` INT UNSIGNED DEFAULT NULL,
        `action` VARCHAR(60) NOT NULL,
        `entity_type` VARCHAR(50) NOT NULL,
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `notifications` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `uuid` CHAR(36) NOT NULL,
        `type` VARCHAR(40) NOT NULL,
        `channel` VARCHAR(20) NOT NULL DEFAULT 'email',
        `recipient_type` VARCHAR(20) NOT NULL DEFAULT 'customer',
        `recipient_id` INT UNSIGNED DEFAULT NULL,
        `recipient_address` VARCHAR(255) DEFAULT NULL,
        `subject` VARCHAR(255) NOT NULL,
        `body` TEXT NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
        `sent_at` TIMESTAMP NULL DEFAULT NULL,
        `read_at` TIMESTAMP NULL DEFAULT NULL,
        `error_message` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_notif_uuid` (`uuid`),
        KEY `idx_notif_status` (`status`),
        KEY `idx_notif_type` (`type`),
        KEY `idx_notif_recipient` (`recipient_type`, `recipient_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `service_reminders` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `uuid` CHAR(36) NOT NULL,
        `vehicle_id` INT UNSIGNED NOT NULL,
        `reminder_type` VARCHAR(20) NOT NULL DEFAULT 'mileage',
        `interval_value` INT UNSIGNED NOT NULL,
        `last_odometer` INT UNSIGNED DEFAULT NULL,
        `last_service_date` DATE DEFAULT NULL,
        `next_due_odometer` INT UNSIGNED DEFAULT NULL,
        `next_due_date` DATE DEFAULT NULL,
        `description` VARCHAR(255) NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'active',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_rem_uuid` (`uuid`),
        KEY `idx_rem_vehicle` (`vehicle_id`),
        KEY `idx_rem_status` (`status`),
        KEY `idx_rem_next_date` (`next_due_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `portal_users` (
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
        KEY `idx_portal_customer` (`customer_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

if (PHP_SAPI !== 'cli' && !defined('PHPUNIT_RUNNING')) {
    exit;
}
