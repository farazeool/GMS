<?php
/**
 * BrightBlaze – Maintenance Mode
 *
 * Maintenance mode is enabled by setting APP_MAINTENANCE=true in .env
 * or by creating a file named maintenance_on in the project root.
 *
 * When active, authenticated admin users may still access the application.
 * All other requests receive a 503 response.
 */

/**
 * Check whether maintenance mode is active.
 */
function maintenance_mode(): bool
{
    $env = env_bool('APP_MAINTENANCE', false);
    $file = __DIR__ . '/../maintenance_on';
    if (file_exists($file)) {
        return true;
    }
    return $env;
}

/**
 * Render the maintenance mode page and exit.
 */
function render_maintenance_page(): void
{
    http_response_code(503);
    header('Retry-After: 3600');
    ?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Temporarily Unavailable · BrightBlaze Garage</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; display: flex; align-items: center; min-height: 100vh; }
  </style>
</head>
<body>
  <div class="container text-center">
    <h1 class="display-1 text-danger mb-4">503</h1>
    <h2 class="mb-3">Temporarily Unavailable</h2>
    <p class="text-muted mb-4">We are performing scheduled maintenance. Please check back soon.</p>
  </div>
</body>
</html><?php
    exit;
}
