<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/job_helpers.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . base_url('job_cards/index.php'));
    exit;
}

verify_csrf();

$id = (int) ($_POST['id'] ?? 0);
$job = get_job($id);

if (!$job || !can_access_job($job)) {
    set_flash('danger', 'Job card not found or you do not have access to it.');
    header('Location: ' . base_url('job_cards/index.php'));
    exit;
}

if (!is_admin() && $job['status'] === 'Cancelled') {
    set_flash('warning', 'Cancelled job cards can no longer be updated.');
    header('Location: ' . base_url('job_cards/view.php?id=' . $id));
    exit;
}

$note = trim($_POST['note'] ?? '');

if ($note === '') {
    set_flash('danger', 'Service note cannot be empty.');
} else {
    $stmt = db()->prepare('INSERT INTO service_notes (job_card_id, user_id, note) VALUES (?, ?, ?)');
    $stmt->execute([$id, current_user_id(), $note]);
    set_flash('success', 'Service note added.');
}

header('Location: ' . base_url('job_cards/view.php?id=' . $id));
exit;
