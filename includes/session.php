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

    $stmt = db()->prepare(
        'SELECT u.id, u.full_name, u.username, u.is_active, r.name AS role_name
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.id = ?
         LIMIT 1'
    );
    $stmt->execute([current_user_id()]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['is_active'] !== 1) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        session_start();
        set_flash('warning', 'Your account is inactive. Please contact an administrator.');
        header('Location: ' . base_url('auth/login.php'));
        exit;
    }

    // Keep session claims aligned with current DB state.
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role_name'];
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
