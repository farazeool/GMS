<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/user_helpers.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . base_url('users/index.php'));
    exit;
}

verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
$user = get_user_row($id);

if (!$user) {
    set_flash('danger', 'User not found.');
} elseif ($id === current_user_id()) {
    set_flash('danger', 'You cannot deactivate your own account.');
} elseif ((int) $user['is_active'] === 1 && $user['role_name'] === 'admin' && active_admin_count() <= 1) {
    set_flash('danger', 'This is the only active administrator. Add another active admin first.');
} else {
    $newStatus = (int) $user['is_active'] === 1 ? 0 : 1;
    $stmt = db()->prepare('UPDATE users SET is_active = ? WHERE id = ?');
    $stmt->execute([$newStatus, $id]);
    set_flash('success', 'User "' . $user['username'] . '" ' . ($newStatus ? 'activated' : 'deactivated') . '.');
}

header('Location: ' . base_url('users/index.php'));
exit;
