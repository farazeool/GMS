<?php
/**
 * MySQL connection settings (XAMPP defaults).
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'brightblaze_garage');
define('DB_USER', 'root');
define('DB_PASS', '');

/**
 * Shared PDO connection (lazy singleton).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('Database connection failed. Check config/database.php and make sure MySQL is running and the brightblaze_garage database is imported.');
        }
    }

    return $pdo;
}
