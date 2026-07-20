#!/usr/bin/env php
<?php
/**
 * BrightBlaze – Plain-PHP Database Migration Runner
 *
 * Usage:
 *   php bin/migrate.php          # Same as "up"
 *   php bin/migrate.php up       # Apply pending migrations
 *   php bin/migrate.php status   # Show applied and pending migrations
 *
 * XAMPP:
 *   /Applications/XAMPP/xamppfiles/bin/php bin/migrate.php status
 *   /Applications/XAMPP/xamppfiles/bin/php bin/migrate.php up
 *
 * Requirements:
 *   - Creates and maintains a `schema_migrations` tracking table.
 *   - Discovers ordered migration files from database/migrations/.
 *   - Applies only pending migrations in order.
 *   - Tracks each successfully applied migration exactly once.
 *   - Never records a failed migration as applied.
 *   - Uses transactions where MySQL supports them.
 *   - Never resets, erases, or re-imports the database.
 *   - Makes repeated execution safe (idempotent).
 *   - Preserves compatibility with Milestone 5 schema.
 */

// Bootstrap the application
$root = dirname(__DIR__);
require_once $root . '/config/config.php';

// --- CLI helpers ---

function migrate_writeln(string $message = ''): void
{
    if (php_sapi_name() === 'cli') {
        echo $message . PHP_EOL;
    }
}

function migrate_error(string $message): void
{
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, 'ERROR: ' . $message . PHP_EOL);
    }
}

// --- Migration system ---

define('MIGRATIONS_DIR', dirname(__DIR__) . '/database/migrations');
define('MIGRATION_TABLE', 'schema_migrations');

/**
 * Ensure the schema_migrations tracking table exists.
 */
function ensure_migration_table(): void
{
    $pdo = db();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `" . MIGRATION_TABLE . "` (
            `version` VARCHAR(255) NOT NULL,
            `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `checksum` VARCHAR(64) NOT NULL DEFAULT '',
            PRIMARY KEY (`version`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Get the checksum of a migration file for integrity tracking.
 */
function migration_checksum(string $filepath): string
{
    return hash_file('sha256', $filepath) ?: '';
}

/**
 * Discover migration files in order.
 * Files are sorted alphabetically by filename (e.g., m1_, m2_, m3_).
 *
 * @return array<int, array{version: string, path: string, checksum: string}>
 */
function discover_migrations(): array
{
    $dir = MIGRATIONS_DIR;
    if (!is_dir($dir)) {
        return [];
    }

    $files = glob($dir . '/*.sql');
    if ($files === false || $files === []) {
        return [];
    }

    sort($files, SORT_STRING);

    $migrations = [];
    foreach ($files as $path) {
        $version = basename($path);
        $migrations[] = [
            'version'  => $version,
            'path'     => $path,
            'checksum' => migration_checksum($path),
        ];
    }

    return $migrations;
}

/**
 * Get the set of already-applied migrations from the database.
 *
 * @return array<string, array{version: string, applied_at: string, checksum: string}>
 */
function get_applied_migrations(): array
{
    $pdo = db();
    $stmt = $pdo->query("SELECT `version`, `applied_at`, `checksum` FROM `" . MIGRATION_TABLE . "` ORDER BY `version` ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $applied = [];
    foreach ($rows as $row) {
        $applied[$row['version']] = $row;
    }
    return $applied;
}

/**
 * Determine pending migrations (discovered but not yet applied).
 *
 * @return array<int, array{version: string, path: string, checksum: string}>
 */
function get_pending_migrations(): array
{
    $all     = discover_migrations();
    $applied = get_applied_migrations();

    return array_values(array_filter($all, function ($m) use ($applied) {
        return !isset($applied[$m['version']]);
    }));
}

/**
 * Apply a single migration file.
 *
 * @param array{version: string, path: string, checksum: string} $migration
 * @return bool True on success, false on failure.
 */
function apply_migration(array $migration): bool
{
    $pdo = db();
    $sql = file_get_contents($migration['path']);
    if ($sql === false || trim($sql) === '') {
        migrate_error("Cannot read or empty migration file: {$migration['version']}");
        return false;
    }

    try {
        // MySQL auto-commits on DDL (CREATE TABLE, ALTER TABLE, etc.),
        // so we cannot wrap DDL statements in a single transaction.
        // We execute the SQL statements directly, then wrap only the
        // tracking INSERT in a transaction.

        // Execute the migration statements.
        // Split by semicolons to handle multi-statement SQL files safely.
        $statements = explode(';', $sql);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement !== '' && !str_starts_with($statement, '--')) {
                // Skip USE statements as the PDO connection already selects the database
                if (!preg_match('/^\s*USE\s+/i', $statement)) {
                    $pdo->exec($statement);
                }
            }
        }

        // Record the migration as applied (wrap in transaction for safety)
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            INSERT INTO `" . MIGRATION_TABLE . "` (`version`, `checksum`)
            VALUES (:version, :checksum)
        ");
        $stmt->execute([
            ':version'  => $migration['version'],
            ':checksum' => $migration['checksum'],
        ]);
        $pdo->commit();

        return true;
    } catch (PDOException $e) {
        // Attempt rollback only if there's an active transaction
        try {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (PDOException $rollbackError) {
            // Ignore rollback failures
        }
        migrate_error("Migration failed: {$migration['version']} - " . $e->getMessage());
        return false;
    }
}

// --- Adapt existing Milestone 4 and 5 migrations ---

/**
 * Adapt existing Milestone 4 migration for the runner.
 * The original uses USE `brightblaze_garage`; we skip that.
 * We only apply if the column doesn't already exist (idempotent).
 */
function adapt_m4_migration(): void
{
    $version = 'm4_maintenance_updated_at.sql';
    $applied = get_applied_migrations();
    if (isset($applied[$version])) {
        return;
    }

    $pdo = db();
    try {
        // Check if column already exists
        $stmt = $pdo->query("SHOW COLUMNS FROM `maintenance_records` LIKE 'updated_at'");
        if ($stmt->fetch()) {
            // Column exists, just record it as applied
            $checksum = migration_checksum(MIGRATIONS_DIR . '/' . $version);
            $stmt = $pdo->prepare("
                INSERT INTO `" . MIGRATION_TABLE . "` (`version`, `checksum`)
                VALUES (:version, :checksum)
            ");
            $stmt->execute([':version' => $version, ':checksum' => $checksum]);
            migrate_writeln("  [SKIP]    $version (already applied)");
            return;
        }

        // Apply the ALTER TABLE
        $pdo->exec("
            ALTER TABLE `maintenance_records`
            ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`
        ");

        $checksum = migration_checksum(MIGRATIONS_DIR . '/' . $version);
        $stmt = $pdo->prepare("
            INSERT INTO `" . MIGRATION_TABLE . "` (`version`, `checksum`)
            VALUES (:version, :checksum)
        ");
        $stmt->execute([':version' => $version, ':checksum' => $checksum]);
        migrate_writeln("  [OK]      $version");
    } catch (PDOException $e) {
        migrate_error("Failed to adapt m4 migration: " . $e->getMessage());
    }
}

/**
 * Adapt existing Milestone 5 migration for the runner.
 * Uses CREATE TABLE IF NOT EXISTS so it's already idempotent.
 */
function adapt_m5_migration(): void
{
    $version = 'm5_settings.sql';
    $applied = get_applied_migrations();
    if (isset($applied[$version])) {
        return;
    }

    $pdo = db();
    try {
        // Execute the CREATE TABLE IF NOT EXISTS and INSERT IGNORE statements
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `settings` (
              `setting_key` VARCHAR(60) NOT NULL,
              `setting_value` TEXT DEFAULT NULL,
              `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`) VALUES
            ('garage_name',       'BrightBlaze Garage'),
            ('business_phone',    '+965 2222 0000'),
            ('business_email',    'info@brightblaze.com.kw'),
            ('business_address',  'Block 1, Canada Dry Street, Shuwaikh Industrial, Kuwait'),
            ('currency',          'KWD'),
            ('installation_mode', 'local'),
            ('sync_mode',         'local_only'),
            ('cloud_api_url',     ''),
            ('sync_api_key',      ''),
            ('last_sync_at',      ''),
            ('sync_status',       'local_only')
        ");

        $checksum = migration_checksum(MIGRATIONS_DIR . '/' . $version);
        $stmt = $pdo->prepare("
            INSERT INTO `" . MIGRATION_TABLE . "` (`version`, `checksum`)
            VALUES (:version, :checksum)
        ");
        $stmt->execute([':version' => $version, ':checksum' => $checksum]);
        migrate_writeln("  [OK]      $version");
    } catch (PDOException $e) {
        migrate_error("Failed to adapt m5 migration: " . $e->getMessage());
    }
}

// --- Commands ---

/**
 * Show migration status.
 */
function cmd_status(): void
{
    ensure_migration_table();

    $all     = discover_migrations();
    $applied = get_applied_migrations();

    migrate_writeln("Migration Status");
    migrate_writeln(str_repeat('-', 60));

    if (empty($all)) {
        migrate_writeln("No migration files found in " . MIGRATIONS_DIR);
        return;
    }

    foreach ($all as $m) {
        $version = $m['version'];
        if (isset($applied[$version])) {
            $appliedAt = $applied[$version]['applied_at'];
            migrate_writeln("  [OK]      $version  ($appliedAt)");
        } else {
            migrate_writeln("  [PENDING] $version");
        }
    }

    // Count
    $pendingCount = count($all) - count($applied);
    migrate_writeln(str_repeat('-', 60));
    migrate_writeln(count($applied) . " applied, " . $pendingCount . " pending");
}

/**
 * Apply pending migrations.
 */
function cmd_up(): void
{
    ensure_migration_table();

    // First, adapt existing Milestone 4 and 5 migrations
    migrate_writeln("Adapting existing migrations...");
    adapt_m4_migration();
    adapt_m5_migration();

    // Then apply any new migrations
    $pending = get_pending_migrations();

    if (empty($pending)) {
        migrate_writeln("Nothing to apply. All migrations are up to date.");
        return;
    }

    migrate_writeln("Applying " . count($pending) . " pending migration(s)...");
    migrate_writeln(str_repeat('-', 60));

    $applied = 0;
    $failed  = 0;

    foreach ($pending as $migration) {
        migrate_writeln("  Applying {$migration['version']}...");
        if (apply_migration($migration)) {
            $applied++;
            migrate_writeln("  [OK]      {$migration['version']}");
        } else {
            $failed++;
            migrate_error("Migration failed: {$migration['version']}. Stopping.");
            break;
        }
    }

    migrate_writeln(str_repeat('-', 60));
    migrate_writeln("$applied applied, $failed failed");
}

// --- Main ---
// Guard: skip command execution when loaded for testing
if (!defined('MIGRATE_INCLUDE_ONLY') || !MIGRATE_INCLUDE_ONLY) {
    $command = $argv[1] ?? 'up';

    switch ($command) {
        case 'up':
            cmd_up();
            break;
        case 'status':
            cmd_status();
            break;
        default:
            migrate_writeln("Usage: php bin/migrate.php {up|status}");
            exit(1);
    }
}
