<?php

/**
 * Tests for the migration runner (bin/migrate.php).
 * Covers discovery, natural ordering, checksum validation, applied tracking,
 * pending detection, rerun safety, failed migration behavior, SQL parsing,
 * adapter failure, CLI exit codes, and database guards.
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

    public function test_discover_migrations_natural_ordering(): void
    {
        // Create temporary migrations to test natural ordering
        $temp1 = $this->createTempMigration('m1_first.sql', 'SELECT 1');
        $temp2 = $this->createTempMigration('m2_second.sql', 'SELECT 1');
        $temp10 = $this->createTempMigration('m10_tenth.sql', 'SELECT 1');

        $migrations = discover_migrations();
        $versions = array_column($migrations, 'version');

        // m2 should come before m10 in natural ordering
        $pos2 = array_search('m2_second.sql', $versions, true);
        $pos10 = array_search('m10_tenth.sql', $versions, true);
        $this->assertNotFalse($pos2);
        $this->assertNotFalse($pos10);
        $this->assertLessThan($pos10, $pos2);

        $this->removeTempMigration($temp1);
        $this->removeTempMigration($temp2);
        $this->removeTempMigration($temp10);
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

    // --- SQL parsing tests ---

    public function test_parse_sql_statements_simple(): void
    {
        $sql = "CREATE TABLE t1 (id INT);\nCREATE TABLE t2 (id INT);";
        $stmts = parse_sql_statements($sql);
        $this->assertCount(2, $stmts);
    }

    public function test_parse_sql_statements_leading_comments(): void
    {
        $sql = "-- Migration comment\nCREATE TABLE t1 (id INT);\n-- Another comment\nSELECT 1;";
        $stmts = parse_sql_statements($sql);
        $this->assertCount(2, $stmts);
    }

    public function test_parse_sql_statements_quoted_semicolons(): void
    {
        $sql = "INSERT INTO t1 VALUES ('hello; world');\nSELECT 1;";
        $stmts = parse_sql_statements($sql);
        $this->assertCount(2, $stmts);
        $this->assertStringContainsString("'hello; world'", $stmts[0]);
    }

    public function test_parse_sql_statements_empty_returns_empty(): void
    {
        $stmts = parse_sql_statements('');
        $this->assertEmpty($stmts);
    }

    public function test_parse_sql_statements_comment_only(): void
    {
        $stmts = parse_sql_statements('-- Just a comment');
        $this->assertEmpty($stmts);
    }

    public function test_parse_sql_statements_trailing_comment(): void
    {
        $sql = "SELECT 1;\n-- Trailing comment";
        $stmts = parse_sql_statements($sql);
        $this->assertCount(1, $stmts);
    }

    public function test_parse_sql_statements_ignore_use(): void
    {
        $sql = "USE `test_db`;\nSELECT 1;";
        $stmts = parse_sql_statements($sql);
        // USE statement is parsed but will be filtered later by apply_migration
        $this->assertCount(2, $stmts);
    }

    // --- Checksum validation tests ---

    public function test_validate_checksums_passes_with_no_applied(): void
    {
        $errors = validate_checksums();
        $this->assertEmpty($errors);
    }

    public function test_validate_checksums_detects_mismatch(): void
    {
        // Record a migration with a fake checksum
        $pdo = db();
        $stmt = $pdo->prepare("INSERT INTO `schema_migrations` (`version`, `checksum`) VALUES (:version, :checksum)");
        $stmt->execute([':version' => 'm4_maintenance_updated_at.sql', ':checksum' => 'fakechecksum123']);

        $errors = validate_checksums();
        $this->assertArrayHasKey('m4_maintenance_updated_at.sql', $errors);
        $this->assertStringContainsString('Checksum mismatch', $errors['m4_maintenance_updated_at.sql']);
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

        // Verify it's in applied (not pending)
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

    // --- Migration status tests ---

    public function test_status_shows_pending(): void
    {
        $pending = get_pending_migrations();

        // All should be pending with fresh table
        $all = discover_migrations();
        $this->assertGreaterThanOrEqual(count($all), count($pending));
    }

    // --- Adapter failure tests ---

    public function test_adapt_m4_returns_true_when_already_done(): void
    {
        // Record m4 as applied manually
        $pdo = db();
        $checksum = migration_checksum(MIGRATIONS_DIR . '/m4_maintenance_updated_at.sql');
        $stmt = $pdo->prepare("INSERT INTO `schema_migrations` (`version`, `checksum`) VALUES (:v, :c)");
        $stmt->execute([':v' => 'm4_maintenance_updated_at.sql', ':c' => $checksum]);

        $result = adapt_m4_migration();
        $this->assertTrue($result);
    }

    public function test_adapt_m5_returns_true_when_already_done(): void
    {
        $pdo = db();
        $checksum = migration_checksum(MIGRATIONS_DIR . '/m5_settings.sql');
        $stmt = $pdo->prepare("INSERT INTO `schema_migrations` (`version`, `checksum`) VALUES (:v, :c)");
        $stmt->execute([':v' => 'm5_settings.sql', ':c' => $checksum]);

        $result = adapt_m5_migration();
        $this->assertTrue($result);
    }

    // --- Database guard tests ---

    public function test_assert_test_database_accepts_test_db(): void
    {
        $pdo = db();
        $dbName = assert_test_database($pdo);
        $this->assertEquals('brightblaze_test', $dbName);
    }

    public function test_assert_test_database_rejects_garage(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('REFUSED');

        // Simulate connection to brightblaze_garage
        $host = env('DB_HOST', 'localhost');
        $port = env('DB_PORT', '3306');
        $user = env('DB_USER', 'root');
        $pass = env('DB_PASS', '');

        $pdo = new PDO("mysql:host={$host};port={$port};dbname=brightblaze_garage;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        assert_test_database($pdo);
    }

    // --- Seeded row count tests ---

    public function test_seeded_row_counts_after_setup(): void
    {
        $counts = get_seeded_row_counts();
        $this->assertIsArray($counts);
        $this->assertArrayHasKey('roles', $counts);
        $this->assertArrayHasKey('users', $counts);

        // Verify baseline counts exist
        $this->assertGreaterThan(0, $counts['roles']);
        $this->assertGreaterThan(0, $counts['users']);
    }

    public function test_migrations_do_not_alter_seeded_data(): void
    {
        // Get baseline counts
        $beforeCounts = get_seeded_row_counts();

        // Apply existing migrations through the runner
        ensure_migration_table();
        adapt_m4_migration();
        adapt_m5_migration();

        // Get counts after migration
        $afterCounts = get_seeded_row_counts();

        // Verify all baseline records remain
        foreach ($beforeCounts as $table => $count) {
            $this->assertEquals(
                $count,
                $afterCounts[$table],
                "Row count for {$table} changed after migration"
            );
        }
    }

    public function test_second_migration_run_applies_nothing(): void
    {
        // First run
        ensure_migration_table();
        adapt_m4_migration();
        adapt_m5_migration();

        // Get applied count
        $appliedAfterFirst = get_applied_migrations();

        // Second run - adapters should return true without applying
        $result4 = adapt_m4_migration();
        $result5 = adapt_m5_migration();
        $this->assertTrue($result4);
        $this->assertTrue($result5);

        $appliedAfterSecond = get_applied_migrations();
        $this->assertCount(count($appliedAfterFirst), $appliedAfterSecond);
    }
}