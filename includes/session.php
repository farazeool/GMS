<?php
/**
 * PHP session handling + role-based access control.
 * Include config/config.php before this file.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function current_user_name(): string
{
    return $_SESSION['full_name'] ?? '';
}

function current_role(): string
{
    return $_SESSION['role'] ?? '';
}

function is_admin(): bool
{
    return current_role() === 'admin';
}

/**
 * Require an authenticated session. Redirects to the login page otherwise.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . base_url('auth/login.php'));
        exit;
    }
}

/**
 * Require a specific role. Users with another role are sent to their own dashboard.
 */
function require_role(string $role): void
{
    require_login();
    if (current_role() !== $role) {
        set_flash('warning', 'You do not have permission to access that page.');
        header('Location: ' . base_url(is_admin() ? 'admin/dashboard.php' : 'technician/dashboard.php'));
        exit;
    }
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $flashes;
}
