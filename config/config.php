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

require_once __DIR__ . '/database.php';

/**
 * Build an absolute URL path inside the application.
 */
function base_url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}
