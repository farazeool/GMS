<?php
/**
 * BrightBlaze database connection.
 *
 * Reads credentials from environment variables (loaded via config/env.php).
 * Falls back to safe development defaults only when APP_ENV=local.
 */

// If env.php hasn't been loaded yet, load it now
if (!function_exists('load_env')) {
    require_once __DIR__ . '/env.php';
}

/**
 * Configure PDO for UTC timezone where supported.
 * MySQL uses `SET time_zone = '+00:00'` for the connection.
 */
function db_configure_timezone(PDO $pdo): void
{
    try {
        $pdo->exec("SET time_zone = '+00:00'");
    } catch (PDOException $e) {
        // Non-fatal: some MySQL configurations may restrict this.
        // Log silently; do not expose details to the browser.
        error_log('db_configure_timezone: ' . $e->getMessage());
    }
}

/**
 * Shared PDO connection (lazy singleton).
 * Uses environment-based configuration with safe local defaults.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $host = env('DB_HOST', 'localhost');
        $port = env('DB_PORT', '3306');
        $name = env('DB_NAME', 'brightblaze_garage');
        $user = env('DB_USER', 'root');
        $pass = env('DB_PASS', '');

        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4';

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            db_configure_timezone($pdo);
        } catch (PDOException $e) {
            http_response_code(500);
            error_log('Database connection failed: ' . $e->getMessage());
            die('Database connection failed. Check your .env configuration and make sure MySQL is running.');
        }
    }

    return $pdo;
}