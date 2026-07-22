#!/usr/bin/env php
<?php
/**
 * BrightBlaze – Database Restore Tool
 *
 * Usage:
 *   php bin/restore.php [backup.sql]
 *
 * WARNING: This drops and recreates all tables from the backup file.
 * Do not run against a database you need to preserve.
 *
 * Safety checks:
 *  - Requires the file to exist
 *  - Requires a confirmation prompt
 *  - Refuses to restore into the production database unless APP_ENV=local
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php bin/restore.php <backup.sql>" . PHP_EOL);
    exit(1);
}

$file = $argv[1];
if (!file_exists($file)) {
    fwrite(STDERR, "ERROR: Backup file not found: {$file}" . PHP_EOL);
    exit(1);
}

$dbName = env('DB_NAME', 'brightblaze_garage');
if ($dbName === 'brightblaze_garage' && env('APP_ENV', 'local') !== 'local') {
    fwrite(STDERR, "ERROR: Refusing to restore into production database while APP_ENV is not 'local'." . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "WARNING: This will drop all tables in '{$dbName}' and restore from backup." . PHP_EOL);
fwrite(STDOUT, "Type 'yes' to continue: ");
$answer = trim(fgets(STDIN));
if ($answer !== 'yes') {
    fwrite(STDOUT, "Restore cancelled." . PHP_EOL);
    exit(0);
}

$host = env('DB_HOST', 'localhost');
$port = env('DB_PORT', '3306');
$user = env('DB_USER', 'root');
$pass = env('DB_PASS', '');

$mysqli = new mysqli($host, $user, $pass, $dbName, (int) $port);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "ERROR: Database connection failed: " . $mysqli->connect_error . PHP_EOL);
    exit(1);
}

$sql = file_get_contents($file);
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, "ERROR: Cannot read backup file or file is empty." . PHP_EOL);
    exit(1);
}

$statements = array_filter(array_map('trim', explode(';', $sql)), 'strlen');

$mysqli->query('SET FOREIGN_KEY_CHECKS = 0');
foreach ($statements as $statement) {
    if (stripos($statement, 'CREATE DATABASE') === 0 || stripos($statement, 'USE ') === 0) {
        continue;
    }
    if (!$mysqli->query($statement)) {
        fwrite(STDERR, "WARNING: " . $mysqli->error . PHP_EOL);
    }
}
$mysqli->query('SET FOREIGN_KEY_CHECKS = 1');
$mysqli->close();

fwrite(STDOUT, "Restore completed from: {$file}" . PHP_EOL);
exit(0);
