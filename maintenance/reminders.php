<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_role('admin');

$page_title = 'Service Reminders';
$active = 'maintenance';

$reminders = db()->query("SELECT sr.*, v.plate_number, v.make, v.model, c.name AS cname FROM service_reminders sr JOIN vehicles v ON v.id=sr.vehicle_id JOIN customers c ON c.id=v.customer_id ORDER BY sr.next_due_date ASC")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h1 class="bb-page-title">Service Reminders <span class="badge text-bg-secondary"><?= count($reminders) ?></span></h1>
  <a class="btn btn-bb" href="<?= base_url('maintenance/reminder_form.php') ?>"><i class="bi bi-plus-lg"></i> New Reminder</a>
</div>
<div class="card"><div class="card-body p-0"><div class="table-responsive">
<table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Vehicle</th><th>Owner</th><th>Description</th><th>Type</th><th>Interval</th><th>Next Due</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
<?php if (empty($reminders)): ?><tr><td colspan="8" class="bb-empty">No reminders set. <a href="<?= base_url('maintenance/reminder_form.php') ?>">Create one</a>.</td></tr><?php endif; ?>
<?php foreach ($reminders as $r): ?>
<?php $overdue = $r['next_due_date'] && $r['next_due_date'] < date('Y-m-d') && $r['status']==='active'; ?>
<tr class="<?= $overdue ? 'table-danger' : '' ?>">
  <td class="fw-semibold"><?= e($r['plate_number']) ?> — <?= e($r['make']) ?> <?= e($r['model']) ?></td>
  <td><?= e($r['cname']) ?></td>
  <td><?= e($r['description']) ?></td>
  <td><span class="badge bg-info"><?= e($r['reminder_type']) ?></span></td>
  <td class="text-muted small"><?= $r['reminder_type']==='mileage' ? e($r['interval_value']) . ' km' : ($r['reminder_type']==='months' ? e($r['interval_value']) . ' months' : e($r['interval_value'])) ?></td>
  <td class="small <?= $overdue ? 'fw-bold' : '' ?>"><?= e($r['next_due_date'] ?? '—') ?></td>
  <td><span class="badge bg-<?= $r['status']==='active'?($overdue?'danger':'success'):($r['status']==='triggered'?'warning':'secondary') ?>"><?= e($r['status']) ?></span></td>
  <td class="bb-actions"><form method="post" action="<?= base_url('maintenance/reminder_complete.php') ?>" class="d-inline"><input type="hidden" name="id" value="<?= $r['id'] ?>"><?= csrf_field() ?>
    <button class="btn btn-sm btn-outline-success" type="submit"><i class="bi bi-check"></i> Complete</button></form></td></tr>
<?php endforeach; ?>
</tbody></table></div></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>