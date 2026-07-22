<?php
/**
 * BrightBlaze – Health Check Endpoint
 *
 * Returns only safe, non-sensitive status information.
 * No database credentials, paths, or internal details are exposed.
 */

require_once __DIR__ . '/config/config.php';

header('Content-Type: application/json');

$response = [
    'status'    => 'healthy',
    'app'       => APP_NAME,
    'version'   => defined('APP_VERSION') ? APP_VERSION : 'unknown',
    'timestamp' => gmdate('c'),
];

try {
    $pdo = db();
    $stmt = $pdo->query('SELECT 1');
    $stmt->fetch();
    $response['database'] = 'connected';
} catch (Throwable $e) {
    $response['status'] = 'unhealthy';
    $response['database'] = 'error';
    http_response_code(503);
}

if (function_exists('maintenance_mode') && maintenance_mode()) {
    $response['status'] = 'maintenance';
    $response['message'] = 'Application is in maintenance mode.';
    http_response_code(503);
}

echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit;
