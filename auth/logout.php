<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';

if (is_logged_in()) {
    log_security_event('auth', 'Logout', ['username' => current_user_name(), 'user_id' => current_user_id()]);
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    setcookie(session_name(), '', time() - 42000, '/', '', $params['secure'], true);
}

session_destroy();

header('Location: ' . base_url('auth/login.php'));
exit;
