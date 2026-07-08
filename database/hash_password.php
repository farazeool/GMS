<?php
/**
 * Development helper: generate a bcrypt hash compatible with password_verify().
 *
 * Usage (CLI only): php hash_password.php YourNewPassword
 *
 * Paste the resulting hash into the users.password_hash column if needed.
 * DELETE THIS FILE in production builds.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden. This helper is CLI-only for local development.\n";
    exit;
}

$password = $argv[1] ?? '';

header('Content-Type: text/plain; charset=utf-8');

if ($password === '') {
    echo "Provide a password, e.g.: php hash_password.php MySecret123\n";
    exit;
}

echo password_hash($password, PASSWORD_DEFAULT) . "\n";
