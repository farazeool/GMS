<?php
/**
 * Development helper: generate a bcrypt hash compatible with password_verify().
 *
 * Usage (browser): http://localhost/brightblaze/database/hash_password.php?password=YourNewPassword
 * Usage (CLI):     php hash_password.php YourNewPassword
 *
 * Paste the resulting hash into the users.password_hash column in phpMyAdmin.
 * DELETE THIS FILE in production.
 */

$password = PHP_SAPI === 'cli' ? ($argv[1] ?? '') : ($_GET['password'] ?? '');

header('Content-Type: text/plain; charset=utf-8');

if ($password === '') {
    echo "Provide a password, e.g. ?password=MySecret123 or: php hash_password.php MySecret123\n";
    exit;
}

echo password_hash($password, PASSWORD_DEFAULT) . "\n";
