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
        // Reset the loaded flag by reloading env.php
        // The static $loaded flag in load_env() prevents double-loading,
        // so we directly set $_ENV values for testing
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['APP_DEBUG'] = 'false';
        $_ENV['APP_TIMEZONE'] = 'Asia/Kuwait';
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_PORT'] = '3306';
        $_ENV['DB_NAME'] = 'brightblaze_test';
        $_ENV['DB_USER'] = 'root';
        $_ENV['DB_PASS'] = '';
    }
}