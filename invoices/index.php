<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/commercial.php';
require_role('admin');
$page_title = 'Invoices';
$active = 'invoices';
$status = $_GET['status'] ?? '';
$sql = "SELECT i.*, c.name AS cname FROM invoices i JOIN customers c ON c.id=i.customer_id WHERE i.deleted_at IS NULL";
$params = [];
if ($status !== '' && in_array($status, ['draft','sent','partial','paid','overdue','cancelled'])) {
    $sql .= " AND i.status = ?"; $params[] = $status;
}
$sql .= " ORDER BY i.created_at DESC";
$invoices = db()->prepare($sql)->execute($params)->fetchAll(PDO::FETCH_ASSOC);
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h1 class="bb-page-title">Invoices <span class="badge text-bg-secondary"><?= count($invoices) ?></span></h1>
  <div><a class="btn btn-bb" href="<?= base_url('invoices/form.php') ?>"><i class="bi bi-plus-lg"></i> New Invoice</a></div>
</div>
<div class="card mb-3"><div class="card-body py-3">
<form class="row g-2"><div class="col-auto"><select class="form-select" name="status"><option value="">All Statuses</option>
<option value="draft" <?= $status==='draft'?'selected':'' ?>>Draft</option><option value="sent" <?= $status==='sent'?'selected':'' ?>>Sent</option>
<option value="partial" <?= $status==='partial'?'selected':'' ?>>Partial</option><option value="paid" <?= $status==='paid'?'selected':'' ?>>Paid</option>
<option value="overdue" <?= $status==='overdue'?'selected':'' ?>>Overdue</option><option value="cancelled" <?= $status==='cancelled'?'selected':'' ?>>Cancelled</option>
</select></div><div class="col-auto"><button class="btn btn-bb-orange">Filter</button><?php if($status): ?><a class="btn btn-outline-secondary ms-1" href="<?= base_url('invoices/index.php') ?>">Clear</a><?php endif; ?></div></form>
</div></div>
<div class="card"><div class="card-body p-0"><div class="table-responsive">
<table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Invoice #</th><th>Customer</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Date</th><th class="text-end">Actions</th></tr></thead><tbody>
<?php if (empty($invoices)): ?><tr><td colspan="8" class="bb-empty">No invoices found.</td></tr><?php endif; ?>
<?php foreach ($invoices as $i): ?>
<?php $statusClasses = ['draft'=>'bg-secondary','sent'=>'bg-primary','partial'=>'bg-warning text-dark','paid'=>'bg-success','overdue'=>'bg-danger','cancelled'=>'bg-secondary']; ?>
<tr>
  <td class="fw-semibold"><a class="text-decoration-none" href="<?= base_url('invoices/view.php?id=' . $i['id']) ?>"><?= e($i['invoice_number']) ?></a></td>
  <td><?= e($i['cname']) ?></td>
  <td><?= format_kwd($i['total']) ?></td>
  <td><?= format_kwd($i['paid_amount']) ?></td>
  <td><?= format_kwd($i['balance']) ?></td>
  <td><span class="badge <?= $statusClasses[$i['status']] ?? 'bg-secondary' ?>"><?= e($i['status']) ?></span></td>
  <td class="small text-muted"><?= date('Y-m-d', strtotime($i['created_at'])) ?></td>
  <td class="bb-actions">
    <a class="btn btn-sm btn-outline-primary" href="<?= base_url('invoices/view.php?id=' . $i['id']) ?>"><i class="bi bi-eye"></i></a>
    <a class="btn btn-sm btn-outline-success" href="<?= base_url('invoices/payment.php?id=' . $i['id']) ?>"><i class="bi bi-cash"></i></a>
  </td>
</tr>
<?php endforeach; ?></tbody></table></div></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>