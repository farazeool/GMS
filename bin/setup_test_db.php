#!/usr/bin/env php
<?php
/**
 * Sets up the brightblaze_test database with base schema + commercial tables.
 * Run when test database needs recreation:
 *   php bin/setup_test_db.php
 */

$pdo = new PDO('mysql:host=db;port=3306;charset=utf8mb4', 'root', 'root', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$pdo->exec('DROP DATABASE IF EXISTS brightblaze_test');
$pdo->exec('CREATE DATABASE brightblaze_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE brightblaze_test');

// Import base schema
$schema = file_get_contents(__DIR__ . '/../database/brightblaze.sql');
$schema = preg_replace('/CREATE DATABASE[^;]+;/i', '', $schema);
$schema = preg_replace('/USE\s+`[^`]+`;/i', '', $schema);
$schema = preg_replace('/DROP TABLE IF EXISTS[^;]+;/i', '', $schema);

$statements = array_filter(array_map('trim', explode(';', $schema)));
foreach ($statements as $stmt) {
    try { $pdo->exec($stmt); } catch (PDOException $e) {}
}

// Create schema_migrations table
$pdo->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
    `version` VARCHAR(255) NOT NULL,
    `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `checksum` VARCHAR(64) NOT NULL DEFAULT '',
    PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Create commercial tables
require_once __DIR__ . '/../bin/setup_test_schema.php';
create_commercial_test_tables($pdo);

$tables = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='brightblaze_test'")->fetchColumn();
echo "Test database ready with $tables tables.\n";
exit(0);