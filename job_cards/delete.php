<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . base_url('job_cards/index.php'));
    exit;
}

verify_csrf();

$id = (int) ($_POST['id'] ?? 0);

$stmt = db()->prepare('SELECT job_number FROM job_cards WHERE id = ?');
$stmt->execute([$id]);
$job = $stmt->fetch();

if (!$job) {
    set_flash('danger', 'Job card not found.');
} else {
    $stmt = db()->prepare('DELETE FROM job_cards WHERE id = ?');
    $stmt->execute([$id]);
    set_flash('success', 'Job card "' . $job['job_number'] . '" deleted, including its service notes.');
}

header('Location: ' . base_url('job_cards/index.php'));
exit;
