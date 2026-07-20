<?php
/**
 * BrightBlaze – Test Bootstrap
 *
 * Loads the environment and configuration for isolated test execution.
 * Uses a separate test database (brightblaze_test) to avoid altering
 * the production brightblaze_garage database.
 */

// Ensure we're in testing mode
define('PHPUNIT_RUNNING', true);

// Load the main application configuration
require_once __DIR__ . '/../config/config.php';

// Load the base test case (no Composer autoloader available)
require_once __DIR__ . '/BaseTestCase.php';

// Load migration runner functions for testing (skip command execution)
define('MIGRATE_INCLUDE_ONLY', true);
require_once __DIR__ . '/../bin/migrate.php';

/**
 * Create the test database if it doesn't exist and set up the schema.
 * This is called once before the test suite runs.
 */
function setup_test_database(): void
{
    $host = env('DB_HOST', 'localhost');
    $port = env('DB_PORT', '3306');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');
    $testDb = env('DB_NAME', 'brightblaze_test');

    try {
        // Connect without database to create it if needed
        $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$testDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = null;

        // Now connect to the test database and import the schema
        $pdo = db(); // This will connect to brightblaze_test via env vars

        // Import the main schema
        $schemaFile = __DIR__ . '/../database/brightblaze.sql';
        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            if ($sql !== false && $sql !== '') {
                // Remove CREATE DATABASE and USE statements
                $sql = preg_replace('/CREATE DATABASE[^;]+;/i', '', $sql);
                $sql = preg_replace('/USE\s+`[^`]+`;/i', '', $sql);
                $sql = preg_replace('/DROP TABLE IF EXISTS[^;]+;/i', '', $sql);

                $statements = explode(';', $sql);
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if ($statement !== '') {
                        try {
                            $pdo->exec($statement);
                        } catch (PDOException $e) {
                            // Skip errors from CREATE TABLE IF NOT EXISTS duplicates
                            // or statements that may already have been applied
                        }
                    }
                }
            }
        }

        // Also ensure schema_migrations table exists for migration tests
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `schema_migrations` (
                `version` VARCHAR(255) NOT NULL,
                `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `checksum` VARCHAR(64) NOT NULL DEFAULT '',
                PRIMARY KEY (`version`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        echo "Warning: Could not set up test database: " . $e->getMessage() . PHP_EOL;
    }
}

/**
 * Drop the test database tables after the test suite completes.
 * Only drops tables, not the database itself.
 */
function teardown_test_database(): void
{
    try {
        $pdo = db();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    } catch (PDOException $e) {
        // Silently ignore teardown failures
    }
}