#!/usr/bin/env php
<?php
/**
 * BrightBlaze – UUID Backfill Script
 * Generates UUIDs for all existing rows that don't have one yet.
 * Call after running m7_sync.sql migration.
 *
 * Usage:
 *   php bin/backfill_uuids.php
 */

require_once __DIR__ . '/../config/config.php';

$mysqli = new mysqli(
    env('DB_HOST', 'localhost'),
    env('DB_USER', 'root'),
    env('DB_PASS', ''),
    env('DB_NAME', 'brightblaze_garage'),
    (int) env('DB_PORT', '3306')
);

if ($mysqli->connect_error) {
    fwrite(STDERR, "ERROR: Database connection failed: " . $mysqli->connect_error . PHP_EOL);
    exit(1);
}

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
];

$totalUpdated = 0;

foreach ($tables as $table) {
    $result = $mysqli->query("SELECT COUNT(*) as c FROM `{$table}` WHERE `uuid` IS NULL OR `uuid` = ''");
    $row = $result->fetch_assoc();
    $count = (int) $row['c'];

    if ($count === 0) {
        echo "  [SKIP]    {$table} — no rows need UUIDs\n";
        continue;
    }

    $mysqli->query("UPDATE `{$table}` SET `uuid` = UUID() WHERE `uuid` IS NULL OR `uuid` = ''");
    $updated = $mysqli->affected_rows;
    $totalUpdated += $updated;
    echo "  [OK]      {$table} — {$updated} UUID(s) assigned\n";
}

$mysqli->close();
echo "\nDone. {$totalUpdated} UUID(s) assigned across " . count($tables) . " table(s).\n";
exit(0);
