<?php
/**
 * BrightBlaze – Customer Portal Session Management
 * Handles secure session management for customer portal users.
 */

if (!function_exists('portal_start_session')) {
    function portal_start_session(): void
    {
        // If session is already active, do nothing
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // If session not started but headers already sent, just start session
        // without trying to set cookie params or session name
        if (headers_sent()) {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            $cookieParams = [
                'lifetime' => 0,
                'path' => parse_url(base_url('portal/'), PHP_URL_PATH),
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ];
            session_set_cookie_params($cookieParams);
            session_name('BB_PORTAL');
            session_start();
        }
    }
}

if (!function_exists('portal_require_login')) {
    function portal_require_login(): void
    {
        portal_start_session();
        if (empty($_SESSION['portal_customer_id'])) {
            header('Location: ' . base_url('portal/login.php'));
            exit;
        }
    }
}

if (!function_exists('portal_customer_id')) {
    function portal_customer_id(): int
    {
        return (int) ($_SESSION['portal_customer_id'] ?? 0);
    }
}

if (!function_exists('portal_is_logged_in')) {
    function portal_is_logged_in(): bool
    {
        return !empty($_SESSION['portal_customer_id']);
    }
}

if (!function_exists('portal_customer_name')) {
    function portal_customer_name(): string
    {
        return $_SESSION['portal_customer_name'] ?? '';
    }
}

if (!function_exists('portal_login')) {
    function portal_login(int $customerId, string $customerName): void
    {
        portal_start_session();
        if (!headers_sent()) {
            session_regenerate_id(true);
        }
        $_SESSION['portal_customer_id'] = $customerId;
        $_SESSION['portal_customer_name'] = $customerName;
    }
}

if (!function_exists('portal_logout')) {
    function portal_logout(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            if (ini_get('session.use_cookies') && !headers_sent()) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params['path'], $params['domain'],
                    $params['secure'], $params['httponly']);
            }
            session_destroy();
        }
    }
}