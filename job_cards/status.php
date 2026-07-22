<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/job_helpers.php';
require_once __DIR__ . '/../includes/sync_helpers.php';

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

$newStatus = $_POST['status'] ?? '';

if (!is_admin()) {
    // Technicians may only follow the defined workflow, and cannot touch cancelled jobs.
    $allowed = TECH_STATUS_TRANSITIONS[$job['status']] ?? [];
    if (!in_array($newStatus, $allowed, true)) {
        set_flash('danger', 'You are not allowed to make this status change.');
        header('Location: ' . base_url('job_cards/view.php?id=' . $id));
        exit;
    }
}

$error = apply_status_change($job, $newStatus);

if ($error !== null) {
    set_flash('danger', $error);
} else {
    track_change('job_cards', 'update', $id);
    set_flash('success', 'Status updated to ' . $newStatus . '.');
}

header('Location: ' . base_url('job_cards/view.php?id=' . $id));
exit;
