<?php
/**
 * BrightBlaze – Test Bootstrap
 *
 * Loads the environment and configuration for isolated test execution.
 * Uses a separate test database (brightblaze_test) to avoid altering
 * the production brightblaze_garage database.
 *
 * HARD GUARD: Every destructive test operation verifies the active
 * database name before proceeding. The only allowed test database
 * name is "brightblaze_test". The production database "brightblaze_garage"
 * is explicitly refused.
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
 * Validate that a candidate test database name is safe.
 * Only "brightblaze_test" is allowed. Rejects production name,
 * empty names, and names with suspicious characters.
 *
 * @param  string $testDb
 * @return string
 * @throws RuntimeException
 */
function validate_test_database_name(string $testDb): string
{
    $testDb = trim($testDb);

    if ($testDb === '') {
        throw new RuntimeException('REFUSED: Test database name is empty.');
    }

    if ($testDb === 'brightblaze_garage') {
        throw new RuntimeException(
            'REFUSED: Test database name must not be the production database "brightblaze_garage".'
        );
    }

    if ($testDb !== 'brightblaze_test') {
        throw new RuntimeException(
            'REFUSED: Test database name must be exactly "brightblaze_test", got "' . $testDb . '".'
        );
    }

    if (preg_match('/[\s`\'";\\\\]/', $testDb)) {
        throw new RuntimeException(
            'REFUSED: Test database name contains invalid characters: "' . $testDb . '".'
        );
    }

    return $testDb;
}

/**
 * Verify that the active database is a safe test database.
 * Throws RuntimeException if the database is brightblaze_garage or unknown.
 *
 * @param  PDO    $pdo  Active PDO connection
 * @return string       The verified database name
 * @throws RuntimeException
 */
function assert_test_database(PDO $pdo): string
{
    $stmt = $pdo->query('SELECT DATABASE()');
    $dbName = $stmt->fetchColumn();

    if ($dbName === false || $dbName === null || $dbName === '') {
        throw new RuntimeException('Cannot determine active database name');
    }

    if ($dbName === 'brightblaze_garage') {
        throw new RuntimeException(
            'REFUSED: Attempted to modify the production database "brightblaze_garage". '
            . 'Tests must use "brightblaze_test".'
        );
    }

    if ($dbName !== 'brightblaze_test') {
        throw new RuntimeException(
            'REFUSED: Unknown database "' . $dbName . '". '
            . 'Tests must use "brightblaze_test".'
        );
    }

    return $dbName;
}

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
    $rawDb = env('DB_NAME', 'brightblaze_test');

    // Validate the test database name BEFORE using it in SQL
    $testDb = validate_test_database_name($rawDb);

    try {
        // Connect without database to create it if needed
        $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$testDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo = null;

        // Now connect to the test database and verify it
        $pdo = db();
        assert_test_database($pdo);

        // Drop all existing tables to start clean
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        // Import the main schema
        $schemaFile = __DIR__ . '/../database/brightblaze.sql';
        if (!file_exists($schemaFile)) {
            throw new RuntimeException('Schema file not found: ' . $schemaFile);
        }

        $sql = file_get_contents($schemaFile);
        if ($sql === false || $sql === '') {
            throw new RuntimeException('Cannot read schema file: ' . $schemaFile);
        }

        // Remove CREATE DATABASE and USE statements
        $sql = preg_replace('/CREATE DATABASE[^;]+;/i', '', $sql);
        $sql = preg_replace('/USE\s+`[^`]+`;/i', '', $sql);
        $sql = preg_replace('/DROP TABLE IF EXISTS[^;]+;/i', '', $sql);

        $statements = explode(';', $sql);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }

        // Verify required tables were created
        $requiredTables = ['roles', 'users', 'customers', 'vehicles', 'job_cards', 'service_notes', 'maintenance_records', 'report_logs', 'settings'];
        $existingTables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($requiredTables as $table) {
            if (!in_array($table, $existingTables, true)) {
                throw new RuntimeException("Required table '{$table}' was not created during schema import");
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
        throw new RuntimeException('Test database setup failed: ' . $e->getMessage());
    } catch (RuntimeException $e) {
        throw $e;
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
        assert_test_database($pdo);
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    } catch (PDOException $e) {
        throw new RuntimeException('Test database teardown failed: ' . $e->getMessage());
    }
}

/**
 * Get seeded row counts for the test database.
 *
 * @return array<string, int>  table_name => row_count
 */
function get_seeded_row_counts(): array
{
    $pdo = db();
    assert_test_database($pdo);

    $tables = ['roles', 'users', 'customers', 'vehicles', 'job_cards', 'service_notes', 'maintenance_records', 'report_logs', 'settings'];
    $counts = [];

    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
            $counts[$table] = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            $counts[$table] = -1; // Table doesn't exist
        }
    }

    return $counts;
}