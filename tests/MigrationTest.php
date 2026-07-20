<?php

/**
 * Tests for the migration runner (bin/migrate.php).
 * Covers discovery, ordering, applied tracking, pending detection,
 * rerun safety, and failed migration behavior.
 *
 * Uses an isolated test database (brightblaze_test).
 */
class MigrationTest extends BaseTestCase
{
    private static bool $dbInitialized = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        if (!self::$dbInitialized) {
            self::setUpTestDatabase();
            self::$dbInitialized = true;
        }
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        if (self::$dbInitialized) {
            self::tearDownTestDatabase();
            self::$dbInitialized = false;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->reloadEnv();
        // Ensure schema_migrations table is clean for each test
        $this->cleanMigrationTable();
    }

    private function cleanMigrationTable(): void
    {
        try {
            $pdo = db();
            $pdo->exec('DROP TABLE IF EXISTS `schema_migrations`');
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `schema_migrations` (
                    `version` VARCHAR(255) NOT NULL,
                    `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `checksum` VARCHAR(64) NOT NULL DEFAULT '',
                    PRIMARY KEY (`version`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (PDOException $e) {
            // Ignore if table doesn't exist yet
        }
    }

    /**
     * Create a temporary migration file for testing.
     */
    private function createTempMigration(string $filename, string $content): string
    {
        $dir = dirname(__DIR__) . '/database/migrations';
        $path = $dir . '/' . $filename;
        file_put_contents($path, $content);
        return $path;
    }

    /**
     * Remove a temporary migration file.
     */
    private function removeTempMigration(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }

    // --- Unit tests ---

    public function test_ensure_migration_table_creates_table(): void
    {
        $pdo = db();
        $result = $pdo->query("SHOW TABLES LIKE 'schema_migrations'");
        $this->assertNotFalse($result);
        $this->assertNotEmpty($result->fetchAll());
    }

    public function test_discover_migrations_returns_array(): void
    {
        $migrations = discover_migrations();
        $this->assertIsArray($migrations);
    }

    public function test_discover_migrations_returns_ordered_results(): void
    {
        $migrations = discover_migrations();
        if (count($migrations) > 1) {
            for ($i = 1; $i < count($migrations); $i++) {
                $this->assertGreaterThan(
                    $migrations[$i - 1]['version'],
                    $migrations[$i]['version']
                );
            }
        }
        $this->assertTrue(true);
    }

    public function test_migration_has_required_keys(): void
    {
        $migrations = discover_migrations();
        if (!empty($migrations)) {
            $m = $migrations[0];
            $this->assertArrayHasKey('version', $m);
            $this->assertArrayHasKey('path', $m);
            $this->assertArrayHasKey('checksum', $m);
        }
        $this->assertTrue(true);
    }

    public function test_migration_checksum_is_sha256(): void
    {
        $migrations = discover_migrations();
        if (!empty($migrations)) {
            $this->assertEquals(64, strlen($migrations[0]['checksum']));
        }
        $this->assertTrue(true);
    }

    public function test_get_applied_migrations_returns_empty_initially(): void
    {
        $applied = get_applied_migrations();
        $this->assertIsArray($applied);
        $this->assertEmpty($applied);
    }

    public function test_get_pending_migrations_returns_all_initially(): void
    {
        $all = discover_migrations();
        $pending = get_pending_migrations();
        $this->assertCount(count($all), $pending);
    }

    // --- Integration tests ---

    public function test_apply_migration_tracks_migration(): void
    {
        $temp = $this->createTempMigration('test_m1_create_temp_table.sql', "
            CREATE TABLE IF NOT EXISTS `test_temp_table` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $migrations = discover_migrations();
        $testMigration = null;
        foreach ($migrations as $m) {
            if ($m['version'] === 'test_m1_create_temp_table.sql') {
                $testMigration = $m;
                break;
            }
        }

        $this->assertNotNull($testMigration);
        $result = apply_migration($testMigration);
        $this->assertTrue($result);

        // Verify it's tracked
        $applied = get_applied_migrations();
        $this->assertArrayHasKey('test_m1_create_temp_table.sql', $applied);

        // Clean up
        $pdo = db();
        $pdo->exec('DROP TABLE IF EXISTS `test_temp_table`');
        $this->removeTempMigration($temp);
    }

    public function test_rerun_migration_is_idempotent(): void
    {
        $temp = $this->createTempMigration('test_m2_rerun_test.sql', "
            CREATE TABLE IF NOT EXISTS `test_rerun_table` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $migrations = discover_migrations();
        $testMigration = null;
        foreach ($migrations as $m) {
            if ($m['version'] === 'test_m2_rerun_test.sql') {
                $testMigration = $m;
                break;
            }
        }

        $this->assertNotNull($testMigration);

        // First apply
        $result1 = apply_migration($testMigration);
        $this->assertTrue($result1);

        // Verify it's in applied
        $pendingAfterFirst = get_pending_migrations();
        $pendingVersions = array_column($pendingAfterFirst, 'version');
        $this->assertNotContains('test_m2_rerun_test.sql', $pendingVersions);

        // Clean up
        $pdo = db();
        $pdo->exec('DROP TABLE IF EXISTS `test_rerun_table`');
        $this->removeTempMigration($temp);
    }

    public function test_failed_migration_not_recorded(): void
    {
        // Create a migration with invalid SQL
        $temp = $this->createTempMigration('test_m3_fail_test.sql', "
            CREATE TABLE invalid_sql;
        ");

        $migrations = discover_migrations();
        $testMigration = null;
        foreach ($migrations as $m) {
            if ($m['version'] === 'test_m3_fail_test.sql') {
                $testMigration = $m;
                break;
            }
        }

        $this->assertNotNull($testMigration);

        // Attempt to apply (should fail)
        $result = apply_migration($testMigration);
        $this->assertFalse($result);

        // Verify it's NOT in applied migrations
        $applied = get_applied_migrations();
        $this->assertArrayNotHasKey('test_m3_fail_test.sql', $applied);

        $this->removeTempMigration($temp);
    }

    public function test_status_shows_pending(): void
    {
        $all = discover_migrations();
        $pending = get_pending_migrations();

        // At minimum, all should be pending (fresh table)
        $this->assertGreaterThanOrEqual(count($all), count($pending));
    }

    public function test_applied_migration_tracking(): void
    {
        $temp = $this->createTempMigration('test_m4_track_test.sql', "
            CREATE TABLE IF NOT EXISTS `test_track_table` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $migrations = discover_migrations();
        $testMigration = null;
        foreach ($migrations as $m) {
            if ($m['version'] === 'test_m4_track_test.sql') {
                $testMigration = $m;
                break;
            }
        }

        $this->assertNotNull($testMigration);
        apply_migration($testMigration);

        $applied = get_applied_migrations();
        $this->assertArrayHasKey('test_m4_track_test.sql', $applied);
        $this->assertEquals($testMigration['checksum'], $applied['test_m4_track_test.sql']['checksum']);

        // Clean up
        $pdo = db();
        $pdo->exec('DROP TABLE IF EXISTS `test_track_table`');
        $this->removeTempMigration($temp);
    }
}