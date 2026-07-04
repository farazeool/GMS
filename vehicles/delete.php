<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';

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
    $stmt = db()->prepare('DELETE FROM vehicles WHERE id = ?');
    $stmt->execute([$id]);
    set_flash('success', 'Vehicle "' . $vehicle['plate_number'] . '" deleted, including its job history and maintenance records.');
}

header('Location: ' . base_url('vehicles/index.php'));
exit;
