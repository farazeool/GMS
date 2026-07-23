<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/uuid.php';
require_once __DIR__ . '/../includes/portal_session.php';
require_once __DIR__ . '/../includes/csrf.php';

portal_start_session();
portal_logout();
header('Location: ' . base_url('portal/login.php'));
exit;