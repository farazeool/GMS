<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/commercial.php';
require_role('admin');

$q = db()->query('SELECT q.*, c.name AS cname, v.plate_number, v.make, v.model FROM quotations q JOIN customers c ON c.id=q.customer_id LEFT JOIN vehicles v ON v.id=q.vehicle_id ORDER BY q.created_at DESC');
$quotations = $q->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Quotations';
$active = 'quotations';
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="bb-page-title">Quotations <span class="badge text-bg-secondary"><?= count($quotations) ?></span></h1>
  <a class="btn btn-bb" href="<?= base_url('quotations/form.php') ?>"><i class="bi bi-plus-lg"></i> New Quotation</a>
</div>
<div class="card"><div class="card-body p-0"><div class="table-responsive">
<table class="table table-hover align-middle mb-0"><thead class="table-light"><tr>
<th>#</th><th>Customer</th><th>Vehicle</th><th>Status</th><th>Total</th><th>Date</th><th class="text-end">Actions</th></tr></thead><tbody>
<?php if (empty($quotations)): ?><tr><td colspan="6" class="bb-empty">No quotations yet.</td></tr><?php endif; ?>
<?php foreach ($quotations as $q):
$statusClasses = ['draft'=>'bg-secondary','approved'=>'bg-primary','rejected'=>'bg-danger','converted'=>'bg-success'];
?>
<tr><td class="fw-semibold"><?= e($q['quotation_number']) ?></td><td><?= e($q['cname']) ?></td><td><?= e($q['plate_number'] . ' — ' . $q['make'] . ' ' . $q['model']) ?></td>
<td><span class="badge <?= $statusClasses[$q['status']] ?? 'bg-secondary' ?>"><?= e($q['status']) ?></span></td>
<td class="fw-semibold"><?= format_kwd($q['total']) ?></td><td class="text-muted small"><?= date('Y-m-d', strtotime($q['created_at'])) ?></td>
<td class="bb-actions">
  <a class="btn btn-sm btn-outline-primary" href="<?= base_url('quotations/form.php?id=' . $q['id']) ?>"><i class="bi bi-pencil"></i></a>
  <?php if ($q['status'] === 'approved'): ?><a class="btn btn-sm btn-outline-success" href="<?= base_url('quotations/convert.php?id=' . $q['id']) ?>"><i class="bi bi-arrow-right-circle"></i> Convert</a><?php endif; ?>
  <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('quotations/print.php?id=' . $q['id']) ?>" target="_blank"><i class="bi bi-printer"></i></a>
</td></tr><?php endforeach; ?>
</tbody></table></div></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>