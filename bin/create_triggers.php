#!/usr/bin/env php
<?php
/**
 * Create UUID BEFORE INSERT triggers for all synchronizable tables.
 * Run once after migrations are applied.
 */

require_once __DIR__ . '/../config/config.php';

$pdo = db();
$tables = [
    'roles',
    'users',
    'customers',
    'vehicles',
    'job_cards',
    'service_notes',
    'maintenance_records',
    'report_logs',
    'settings',
    'sync_queue',
    'sync_conflicts',
];

echo "Creating UUID BEFORE INSERT triggers...\n";

foreach ($tables as $table) {
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
        echo "  ✓ Created $triggerName\n";
    } catch (PDOException $e) {
        echo "  ✗ Failed $triggerName: " . $e->getMessage() . "\n";
    }
}

echo "\nDone.\n";
