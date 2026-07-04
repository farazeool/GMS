<?php
/**
 * BrightBlaze application configuration.
 * Adjust BASE_URL if the project folder inside htdocs is not "brightblaze".
 */

define('APP_NAME', 'BrightBlaze Garage');
define('BASE_URL', '/brightblaze/');

require_once __DIR__ . '/database.php';

/**
 * Build an absolute URL path inside the application.
 */
function base_url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}
