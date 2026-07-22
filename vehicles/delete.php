<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/sync_helpers.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . base_url('vehicles/index.php'));
    exit;
}

verify_csrf();

$id = (int) ($_POST['id'] ?? 0);

$stmt = db()->prepare('SELECT plate_number FROM vehicles WHERE id = ?');
$stmt->execute([$id]);
$vehicle = $stmt->fetch();

if (!$vehicle) {
    set_flash('danger', 'Vehicle not found.');
} else {
    // Soft delete with sync tracking
    $stmt = db()->prepare('UPDATE vehicles SET deleted_at = NOW(), sync_status = ? WHERE id = ?');
    $stmt->execute(['pending', $id]);
    track_change('vehicles', 'delete', $id);
    set_flash('success', 'Vehicle "' . $vehicle['plate_number'] . '" deleted (soft delete with sync pending).');
}

header('Location: ' . base_url('vehicles/index.php'));
exit;
