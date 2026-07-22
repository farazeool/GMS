<?php

use PHPUnit\Framework\TestCase;

/**
 * Base test case for BrightBlaze Stage 2A tests.
 * Provides database setup/teardown and common helpers.
 */
abstract class BaseTestCase extends TestCase
{
    /**
     * Set up the test database schema once before all tests in a class.
     */
    public static function setUpTestDatabase(): void
    {
        if (!function_exists('setup_test_database')) {
            require_once __DIR__ . '/bootstrap.php';
        }
        setup_test_database();
    }

    /**
     * Tear down the test database after all tests in a class.
     */
    public static function tearDownTestDatabase(): void
    {
        if (!function_exists('teardown_test_database')) {
            require_once __DIR__ . '/bootstrap.php';
        }
        teardown_test_database();
    }

    /**
     * Load the env.php and reinitialize environment for testing.
     */
    protected function reloadEnv(): void
    {
        // Restore from bootstrap snapshot so that individual
        // EnvironmentTest tests that called putenv('DB_PASS=')
        // or unset($_ENV['DB_PASS']) don't pollute subsequent
        // test classes (e.g. MigrationTest).
        //
        // The constants are defined in tests/bootstrap.php after
        // env.php / config.php have been loaded.
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['APP_DEBUG'] = 'false';
        $_ENV['APP_TIMEZONE'] = 'Asia/Kuwait';
        $_ENV['DB_HOST'] = defined('BOOTSTRAP_DB_HOST') ? BOOTSTRAP_DB_HOST : 'localhost';
        $_ENV['DB_PORT'] = defined('BOOTSTRAP_DB_PORT') ? BOOTSTRAP_DB_PORT : '3306';
        $_ENV['DB_NAME'] = defined('BOOTSTRAP_DB_NAME') ? BOOTSTRAP_DB_NAME : 'brightblaze_test';
        $_ENV['DB_USER'] = defined('BOOTSTRAP_DB_USER') ? BOOTSTRAP_DB_USER : 'root';
        $_ENV['DB_PASS'] = defined('BOOTSTRAP_DB_PASS') ? BOOTSTRAP_DB_PASS : '';
    }
}