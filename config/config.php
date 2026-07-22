<?php
/**
 * BrightBlaze application configuration.
 * Loads environment variables first, then sets app-level constants.
 * Adjust BASE_URL if the project folder inside htdocs is not "brightblaze".
 */

require_once __DIR__ . '/env.php';

// Load error handler early to catch startup issues
require_once __DIR__ . '/../includes/error_handler.php';

define('APP_NAME', 'BrightBlaze Garage');
define('APP_VERSION', '4.0.0');

// Determine BASE_URL from APP_URL or fall back to /brightblaze/
$appUrl  = env('APP_URL', 'http://localhost/brightblaze');
$parsed  = parse_url($appUrl);
$baseUrl = ($parsed['path'] ?? '/') . '/';
define('BASE_URL', $baseUrl);

// Set the default timezone for PHP date functions
$timezone = env('APP_TIMEZONE', 'Asia/Kuwait');
date_default_timezone_set($timezone);

// Validate production configuration now that the error handler is active.
// In non-local environments, missing required values trigger a safe error page.
env_require_production();

require_once __DIR__ . '/../includes/datetime.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/maintenance.php';

require_once __DIR__ . '/database.php';

// Send HTTP security headers before any output
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if ($isHttps) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https://*.githubusercontent.com; font-src 'self' https://cdn.jsdelivr.net; connect-src 'self'; frame-ancestors 'none';");

// Maintenance mode check (skip for health checks, CLI, and auth pages)
$isCli  = php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg';
$isAuth = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/auth/');
$isHealth = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/health.php');

if (!$isCli && !$isAuth && !$isHealth && maintenance_mode()) {
    if (is_logged_in() && is_admin()) {
        // Allow admin access during maintenance
    } else {
        render_maintenance_page();
    }
}

/**
 * Build an absolute URL path inside the application.
 */
function base_url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}
