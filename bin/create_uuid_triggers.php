#!/usr/bin/env php
<?php
/**
 * Create BEFORE INSERT UUID triggers for all synchronizable tables.
 * Run via CLI after migrations are applied.
 */

require_once __DIR__ . '/../config/config.php';

$pdo = db();
$tables = [
    'roles' => 'id',
    'users' => 'id',
    'customers' => 'id',
    'vehicles' => 'id',
    'job_cards' => 'id',
    'service_notes' => 'id',
    'maintenance_records' => 'id',
    'report_logs' => 'id',
    'settings' => 'setting_key',
    'sync_queue' => 'id',
    'sync_conflicts' => 'id',
    'invoices' => 'id',
    'invoice_lines' => 'id',
    'quotations' => 'id',
    'quotation_lines' => 'id',
    'payments' => 'id',
    'audit_log' => 'id',
    'notifications' => 'id',
    'service_reminders' => 'id',
    'portal_users' => 'id',
    'inventory_categories' => 'id',
    'inventory_suppliers' => 'id',
    'inventory_items' => 'id',
    'inventory_movements' => 'id',
    'job_card_parts' => 'id',
    'quotations' => 'id',
    'quotation_lines' => 'id',
    'invoices' => 'id',
    'invoice_lines' => 'id',
];

echo "Creating BEFORE INSERT UUID triggers...\n\n";

$created = 0;
foreach ($tables as $table => $afterColumn) {
    $triggerName = "trg_{$table}_uuid";

    // Drop if exists
    try {
        $pdo->exec("DROP TRIGGER IF EXISTS `$triggerName`");
    } catch (PDOException $e) {
        // Ignore
    }

    // Create trigger
    $sql = "CREATE TRIGGER `$triggerName` BEFORE INSERT ON `$table`
FOR EACH ROW
BEGIN
  IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
    SET NEW.uuid = UUID();
  END IF;
END";

    try {
        $pdo->exec($sql);
        echo "  ✓ Created trg_{$table}_uuid\n";
        $created++;
    } catch (PDOException $e) {
        echo "  ✗ Failed {$table}: " . $e->getMessage() . "\n";
    }
}

echo "\nDone. $created triggers created.\n";
exit(0);