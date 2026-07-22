<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/sync_helpers.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . base_url('customers/index.php'));
    exit;
}

verify_csrf();

$id = (int) ($_POST['id'] ?? 0);

$stmt = db()->prepare('SELECT name FROM customers WHERE id = ?');
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    set_flash('danger', 'Customer not found.');
} else {
    // Soft delete - mark as deleted_at instead of hard delete
    $stmt = db()->prepare('UPDATE customers SET deleted_at = NOW(), sync_status = "pending" WHERE id = ?');
    $stmt->execute([$id]);

    // Track for sync
    track_delete('customers', $id);

    set_flash('success', 'Customer "' . $customer['name'] . '" deleted, including linked vehicles and job history.');
}

header('Location: ' . base_url('customers/index.php'));
exit;
