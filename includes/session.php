<?php
/**
 * PHP session handling + role-based access control.
 * Include config/config.php before this file.
 */

require_once __DIR__ . '/security.php';

// Configure secure session parameters before starting the session.
if (session_status() === PHP_SESSION_NONE) {
    $sec = security_settings();

    $cookiePath = parse_url(BASE_URL, PHP_URL_PATH) ?: '/';
    $cookieDomain = '';

    session_set_cookie_params([
        'lifetime' => $sec['session_lifetime'] * 60,
        'path'     => $cookiePath,
        'domain'   => $cookieDomain,
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_name('BBSESSION');
    session_start();
}

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';

function is_logged_in(): bool
{
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $sec = security_settings();

    $idleTimeout = $sec['session_idle_timeout'] * 60;
    $lifetime = $sec['session_lifetime'] * 60;

    if ($idleTimeout > 0 && isset($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity']) > $idleTimeout) {
        session_destroy();
        return false;
    }

    if ($lifetime > 0 && isset($_SESSION['created_at']) && (time() - (int) $_SESSION['created_at']) > $lifetime) {
        session_destroy();
        return false;
    }

    $_SESSION['last_activity'] = time();

    return true;
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
