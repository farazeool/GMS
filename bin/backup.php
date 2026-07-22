#!/usr/bin/env php
<?php
/**
 * BrightBlaze – Database Backup Tool
 *
 * Usage:
 *   php bin/backup.php [output.sql]
 *
 * Creates a timestamped SQL dump of the configured database.
 * Writes to the given path, or to database/backups/ by default.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/security.php';

function backup_database(string $outputPath): bool
{
    $host = env('DB_HOST', 'localhost');
    $port = env('DB_PORT', '3306');
    $name = env('DB_NAME', 'brightblaze_garage');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');

    $dir = dirname($outputPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $mysqli = new mysqli($host, $user, $pass, $name, (int) $port);
    if ($mysqli->connect_errno) {
        fwrite(STDERR, "ERROR: Database connection failed: " . $mysqli->connect_error . PHP_EOL);
        return false;
    }

    $mysqli->set_charset('utf8mb4');

    $tables = $mysqli->query('SHOW TABLES');
    if (!$tables) {
        fwrite(STDERR, "ERROR: Could not list tables: " . $mysqli->error . PHP_EOL);
        return false;
    }

    $fp = fopen($outputPath, 'w');
    if (!$fp) {
        fwrite(STDERR, "ERROR: Cannot open output file: {$outputPath}" . PHP_EOL);
        return false;
    }

    fwrite($fp, "-- BrightBlaze Garage Backup" . PHP_EOL);
    fwrite($fp, "-- Generated: " . date('c') . PHP_EOL);
    fwrite($fp, "-- Database: {$name}" . PHP_EOL);
    fwrite($fp, "SET FOREIGN_KEY_CHECKS = 0;" . PHP_EOL . PHP_EOL);

    while ($row = $tables->fetch_row()) {
        $table = $row[0];
        $create = $mysqli->query("SHOW CREATE TABLE `{$table}`");
        if (!$create) {
            continue;
        }
        $row2 = $create->fetch_assoc();
        fwrite($fp, "-- Table: {$table}" . PHP_EOL);
        fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;" . PHP_EOL);
        fwrite($fp, $row2['Create Table'] . ";" . PHP_EOL . PHP_EOL);

        $result = $mysqli->query("SELECT * FROM `{$table}`");
        if (!$result) {
            continue;
        }
        while ($data = $result->fetch_assoc()) {
            $cols = array_map(function ($c) use ($mysqli) {
                return '`' . $mysqli->real_escape_string($c) . '`';
            }, array_keys($data));
            $vals = array_map(function ($v) use ($mysqli) {
                return $v === null
                    ? 'NULL'
                    : "'" . $mysqli->real_escape_string($v) . "'";
            }, array_values($data));
            fwrite($fp, "INSERT INTO `{$table}` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");" . PHP_EOL);
        }
        fwrite($fp, PHP_EOL);
    }

    fwrite($fp, "SET FOREIGN_KEY_CHECKS = 1;" . PHP_EOL);
    fclose($fp);
    $mysqli->close();

    return true;
}

$output = $argv[1] ?? dirname(__DIR__) . '/database/backups/brightblaze_' . date('Ymd_His') . '.sql';

// Guard: skip CLI execution when loaded for inclusion
if (!defined('BACKUP_INCLUDE_ONLY') || !BACKUP_INCLUDE_ONLY) {
    if (backup_database($output)) {
        echo "Backup created: {$output}" . PHP_EOL;
        exit(0);
    } else {
        echo "Backup failed." . PHP_EOL;
        exit(1);
    }
}
