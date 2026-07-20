<?php
/**
 * BrightBlaze – Production-safe Error Handling and Logging
 *
 * Registers an exception handler, error handler, and shutdown handler that:
 *   - Displays friendly production error pages (no stack traces, SQL, or paths).
 *   - Shows detailed error information only when APP_DEBUG=true.
 *   - Logs errors to a runtime log file outside public web access.
 *   - Redacts secrets (DB_PASS, APP_KEY, tokens, etc.) from log output.
 *
 * The log directory is created at:
 *   __DIR__ . '/../storage/logs/'
 *
 * This file should be required early in the bootstrap, typically in config/config.php.
 */

/**
 * Get the runtime log directory, creating it if necessary.
 */
function log_directory(): string
{
    $dir = __DIR__ . '/../storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return realpath($dir) ?: $dir;
}

/**
 * Redact sensitive values from log messages.
 *
 * Never log: database passwords, APP_KEY, API keys, authentication tokens,
 * session identifiers, complete connection strings, or SQL containing sensitive values.
 */
function redact_secrets(string $message): string
{
    // Redact DB_PASS values (anything after 'DB_PASS=' or 'password=' etc.)
    $message = preg_replace(
        '/(DB_PASS\s*=\s*)([^\s,;}]+)/i',
        '$1******',
        $message
    );

    // Redact APP_KEY values
    $message = preg_replace(
        '/(APP_KEY\s*=\s*)([^\s,;}]+)/i',
        '$1******',
        $message
    );

    // Redact DSN strings containing passwords
    $message = preg_replace(
        '/password=([^\s;]+)/i',
        'password=******',
        $message
    );

    // Redact PDO connection strings
    $message = preg_replace(
        '/(mysql:host=)[^;]+(;port=)[^;]+(;dbname=)[^;]+/i',
        '$1******$2******$3******',
        $message
    );

    // Redact Authorization headers and Bearer tokens
    $message = preg_replace(
        '/(Authorization:\s*Bearer\s+)\S+/i',
        '$1******',
        $message
    );

    // Redact Set-Cookie session IDs
    $message = preg_replace(
        '/(PHPSESSID|session_id)=[^;\s]+/i',
        '$1=******',
        $message
    );

    return $message;
}

/**
 * Write a message to the runtime log file.
 * The message is redacted before writing.
 */
function log_error(string $message, array $context = []): void
{
    $dir   = log_directory();
    $file  = $dir . '/brightblaze-' . date('Y-m-d') . '.log';
    $time  = date('Y-m-d H:i:s T');
    $redacted = redact_secrets($message);

    if (!empty($context)) {
        $contextJson = @json_encode($context, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_SLASHES);
        $redacted .= ' | Context: ' . $contextJson;
    }

    $line = "[{$time}] {$redacted}" . PHP_EOL;
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Render a user-facing error page.
 * In production (APP_DEBUG=false), shows a safe generic message.
 * In development (APP_DEBUG=true), shows detailed error information.
 */
function render_error_page(string $title, string $message, array $details = []): void
{
    if (!headers_sent()) {
        http_response_code(500);
    }

    $debug = function_exists('env_bool') ? env_bool('APP_DEBUG', false) : false;

    // CLI-safe output: plain text instead of HTML
    if (php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg') {
        fwrite(STDERR, "ERROR: {$title}" . PHP_EOL);
        fwrite(STDERR, "{$message}" . PHP_EOL);
        if (!empty($details)) {
            fwrite(STDERR, print_r($details, true) . PHP_EOL);
        }
        exit(1);
    }

    if (!$debug) {
        // Production-safe output: no stack traces, no SQL, no paths
        ?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Error · BrightBlaze Garage</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; display: flex; align-items: center; min-height: 100vh; }
  </style>
</head>
<body>
  <div class="container text-center">
    <h1 class="display-1 text-danger mb-4">500</h1>
    <h2 class="mb-3">Something went wrong</h2>
    <p class="text-muted mb-4">An unexpected error occurred. Please try again later or contact the system administrator.</p>
    <a href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>" class="btn btn-primary">Return to Dashboard</a>
  </div>
</body>
</html><?php
        exit;
    }

    // Development output with safe details
    ?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Error · BrightBlaze Garage</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="container py-5">
    <h1 class="text-danger"><?= e($title) ?></h1>
    <div class="alert alert-danger"><?= e($message) ?></div>
    <?php if (!empty($details)): ?>
      <h4 class="mt-4">Details</h4>
      <pre class="bg-light p-3 border rounded"><code><?= e(print_r($details, true)) ?></code></pre>
    <?php endif; ?>
    <a href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>" class="btn btn-primary mt-3">Return to Dashboard</a>
  </div>
</body>
</html><?php
    exit;
}

/**
 * Exception handler for uncaught exceptions.
 */
function handle_exception(Throwable $e): void
{
    $message = 'Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    log_error($message);

    // In development, include the full trace in details
    $debug = function_exists('env_bool') ? env_bool('APP_DEBUG', false) : false;
    $details = $debug ? ['trace' => $e->getTraceAsString()] : [];

    render_error_page('Internal Server Error', 'An unexpected error occurred.', $details);
}

/**
 * Error handler for PHP errors (converts to ErrorException).
 */
function handle_error(int $severity, string $message, string $file, int $line): bool
{
    if (!(error_reporting() & $severity)) {
        // Error level not included in error_reporting
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
}

/**
 * Shutdown handler for fatal errors.
 */
function handle_shutdown(): void
{
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $message = 'Fatal Error: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line'];
        log_error($message);

        $debug = function_exists('env_bool') ? env_bool('APP_DEBUG', false) : false;
        $details = $debug ? ['type' => $error['type']] : [];
        render_error_page('Fatal Error', 'A fatal error occurred.', $details);
    }
}

// Register handlers only when not running PHPUnit tests
// (PHPUnit has its own error handling that we must not override)
if (!defined('PHPUNIT_RUNNING') || !PHPUNIT_RUNNING) {
    set_exception_handler('handle_exception');
    set_error_handler('handle_error');
    error_reporting(E_ALL);
    register_shutdown_function('handle_shutdown');
}
