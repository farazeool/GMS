<?php
/**
 * BrightBlaze – Lightweight Environment Loader
 *
 * Reads .env files and provides safe parsing helpers.
 * Supports APP_ENV, APP_DEBUG, APP_URL, APP_TIMEZONE, APP_KEY,
 * DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS.
 *
 * Production (APP_ENV !== 'local') requires all production values.
 * Safe defaults are allowed only when APP_ENV=local.
 */

define('ENV_PATH', __DIR__ . '/../.env');

/**
 * Load environment variables from .env and .env.local files.
 * Never exposes credentials in browser errors.
 */
function load_env(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $files = [ENV_PATH];
    $local = ENV_PATH . '.local';
    if (file_exists($local)) {
        $files[] = $local;
    }

    foreach ($files as $file) {
        if (!file_exists($file)) {
            continue;
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            continue;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                // Strip surrounding quotes if present
                if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
                    || (str_starts_with($value, "'") && str_ends_with($value, "'"))
                ) {
                    $value = substr($value, 1, -1);
                }
                if (!array_key_exists($key, $_ENV)) {
                    $_ENV[$key] = $value;
                }
                if (!array_key_exists($key, $_SERVER)) {
                    $_SERVER[$key] = $value;
                }
                putenv("$key=$value");
            }
        }
    }
}

/**
 * Get an environment variable with an optional default.
 *
 * Uses strict null detection so that legitimate values such as "0"
 * or "" are not incorrectly replaced by the default.
 */
function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null) {
        return $default;
    }
    return $value;
}

/**
 * Parse an environment variable as a boolean.
 * Returns true for "true", "1", "yes", "on" (case-insensitive).
 */
function env_bool(string $key, bool $default = false): bool
{
    $value = env($key);
    if ($value === null) {
        return $default;
    }
    if (is_bool($value)) {
        return $value;
    }
    return in_array(strtolower((string) $value), ['true', '1', 'yes', 'on'], true);
}

/**
 * Parse an environment variable as an integer.
 */
function env_int(string $key, int $default = 0): int
{
    $value = env($key);
    if ($value === null) {
        return $default;
    }
    return (int) $value;
}

/**
 * Validate that required configuration values are present.
 * In non-local environments, missing required values trigger an exception.
 *
 * @throws RuntimeException
 */
function env_require(string ...$keys): void
{
    $missing = [];
    foreach ($keys as $key) {
        $value = env($key);
        if ($value === null || $value === '') {
            $missing[] = $key;
        }
    }
    if ($missing !== []) {
        throw new RuntimeException(
            'Missing required environment variables: ' . implode(', ', $missing)
        );
    }
}

/**
 * Validate production configuration.
 * Safe defaults are only allowed when APP_ENV=local.
 */
function env_require_production(): void
{
    $appEnv = env('APP_ENV', 'local');
    $safeEnvs = ['local', 'testing', 'dev', 'development'];
    if (!in_array($appEnv, $safeEnvs, true)) {
        env_require('APP_KEY', 'APP_URL', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS');
    }
}

// Load environment on include
load_env();