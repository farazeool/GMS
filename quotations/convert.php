<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/job_helpers.php';
require_role('admin');

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$stmt = db()->prepare('SELECT * FROM quotations WHERE id=? AND status=? AND (job_card_id IS NULL OR job_card_id=0)');
$stmt->execute([$id, 'approved']);
$q = $stmt->fetch();
if (!$q) { set_flash('danger', 'Cannot convert this quotation.'); header('Location: ' . base_url('quotations/index.php')); exit; }

$jobNumber = generate_job_number();
db()->beginTransaction();
try {
    db()->prepare('INSERT INTO job_cards (uuid, job_number, customer_id, vehicle_id, service_category, priority, status, problem_description, created_at) VALUES (?,?,?,?,?,?,?,?,NOW())')
        ->execute([uuid_generate(), $jobNumber, $q['customer_id'], $q['vehicle_id'], 'General Service', 'Medium', 'Pending', 'From quotation ' . $q['quotation_number'] . ($q['notes'] ? ': ' . $q['notes'] : '')]);
    $jcId = (int)db()->lastInsertId();
    db()->prepare('UPDATE quotations SET job_card_id=?, status=? WHERE id=?')
        ->execute([$jcId, 'converted', $id]);
    db()->commit();
    set_flash('success', 'Quotation converted to job card ' . $jobNumber);
    header('Location: ' . base_url('job_cards/view.php?id=' . $jcId));
} catch (Throwable $e) {
    db()->rollBack();
    set_flash('danger', 'Conversion failed: ' . $e->getMessage());
    header('Location: ' . base_url('quotations/index.php'));
}
exit;