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
 * It does NOT depend on includes/functions.php (e() is not available yet).
 */

/**
 * Safe HTML-escaping for use before the application view helpers are loaded.
 * This is an internal fallback only; production pages should use e().
 */
function _e_safe(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

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
 * Recursively redact sensitive keys and values from an array/string.
 *
 * Sensitive keys (matched case-insensitively):
 *   password, pass, secret, token, authorization, cookie, session,
 *   api_key, sync_api_key, app_key, db_pass
 */
function redact_sensitive_value(mixed $value, string $key = ''): mixed
{
    $sensitiveKeys = [
        'password', 'pass', 'secret', 'token', 'authorization',
        'cookie', 'session', 'api_key', 'sync_api_key', 'app_key', 'db_pass',
    ];

    // If this value corresponds to a sensitive key, redact entirely.
    // Match exact key names (case-insensitive) to avoid over-redacting
    // nested arrays whose parent keys happen to contain sensitive substrings.
    $keyLower = strtolower($key);
    foreach ($sensitiveKeys as $sk) {
        if ($keyLower === $sk) {
            return '******';
        }
    }

    if (is_array($value)) {
        $result = [];
        foreach ($value as $k => $v) {
            $result[$k] = redact_sensitive_value($v, (string) $k);
        }
        return $result;
    }

    if (is_string($value)) {
        return redact_secrets($value);
    }

    return $value;
}

/**
 * Redact sensitive values from log messages.
 *
 * Never log: database passwords, APP_KEY, API keys, authentication tokens,
 * session identifiers, complete connection strings, or SQL containing sensitive values.
 */
function redact_secrets(string $message): string
{
    // Redact DB_PASS values (anything after 'DB_PASS=')
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

    // Redact PDO connection strings (host, port, dbname)
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

    // Redact inline api_key, sync_api_key, secret, token values (key=value patterns)
    $message = preg_replace(
        '/\b(api_key|sync_api_key|secret|token)\s*=\s*[^\s,;}]+/i',
        '$1=******',
        $message
    );

    return $message;
}

/**
 * Write a message to the runtime log file.
 * Both the message and context are redacted before writing.
 */
function log_error(string $message, array $context = []): void
{
    $dir   = log_directory();
    $file  = $dir . '/brightblaze-' . date('Y-m-d') . '.log';
    $time  = date('Y-m-d H:i:s T');

    // Redact the message first
    $redacted = redact_secrets($message);

    // Redact context recursively and JSON-encode
    if (!empty($context)) {
        $safeContext = redact_sensitive_value($context);
        $contextJson = @json_encode($safeContext, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_SLASHES);
        if ($contextJson !== false) {
            $redacted .= ' | Context: ' . $contextJson;
            // Apply final string-based redaction to the combined line
            $redacted = redact_secrets($redacted);
        }
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
        // Use internal escaping (_e_safe) instead of e() which may not be loaded yet
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
    <a href="<?= _e_safe(defined('BASE_URL') ? BASE_URL : '/') ?>" class="btn btn-primary">Return to Dashboard</a>
  </div>
</body>
</html><?php
        exit;
    }

    // Development output with safe details (use _e_safe for escaping)
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
    <h1 class="text-danger"><?= _e_safe($title) ?></h1>
    <div class="alert alert-danger"><?= _e_safe($message) ?></div>
    <?php if (!empty($details)): ?>
      <h4 class="mt-4">Details</h4>
      <pre class="bg-light p-3 border rounded"><code><?= _e_safe(print_r($details, true)) ?></code></pre>
    <?php endif; ?>
    <a href="<?= _e_safe(defined('BASE_URL') ? BASE_URL : '/') ?>" class="btn btn-primary mt-3">Return to Dashboard</a>
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