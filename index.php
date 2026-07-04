<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/session.php';

if (!is_logged_in()) {
    header('Location: ' . base_url('auth/login.php'));
    exit;
}

header('Location: ' . base_url(is_admin() ? 'admin/dashboard.php' : 'technician/dashboard.php'));
exit;
