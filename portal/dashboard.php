<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/uuid.php';
require_once __DIR__ . '/../includes/portal_session.php';

portal_start_session();
portal_require_login();

$customerId = portal_customer_id();
$customerName = portal_customer_name();

$vehicles = db()->prepare('SELECT v.*, (SELECT COUNT(*) FROM job_cards jc WHERE jc.vehicle_id = v.id AND jc.deleted_at IS NULL) AS job_count FROM vehicles v WHERE v.customer_id = ? AND v.deleted_at IS NULL ORDER BY v.created_at DESC')
            ->execute([$customerId])->fetchAll(PDO::FETCH_ASSOC);
$jobs = db()->prepare('SELECT jc.*, v.plate_number, v.make, v.model FROM job_cards jc JOIN vehicles v ON v.id=jc.vehicle_id WHERE jc.customer_id=? AND jc.deleted_at IS NULL ORDER BY jc.created_at DESC LIMIT 10')
        ->execute([$customerId])->fetchAll(PDO::FETCH_ASSOC);
$invoices = db()->prepare('SELECT i.* FROM invoices i WHERE i.customer_id=? AND i.deleted_at IS NULL ORDER BY i.created_at DESC LIMIT 10')
            ->execute([$customerId])->fetchAll(PDO::FETCH_ASSOC);
$quotations = db()->prepare('SELECT q.*, v.plate_number, v.make, v.model FROM quotations q LEFT JOIN vehicles v ON v.id=q.vehicle_id WHERE q.customer_id=? ORDER BY q.created_at DESC LIMIT 10')
              ->execute([$customerId])->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'My Dashboard';
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h1 class="bb-page-title">Welcome, <?= e($customerName) ?></h1>
  <a class="btn btn-outline-secondary" href="<?= base_url('portal/logout.php') ?>"><i class="bi bi-box-arrow-right"></i> Sign Out</a>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="card bb-stat"><div class="card-body d-flex justify-content-between align-items-center">
    <div><div class="bb-stat-value"><?= count($vehicles) ?></div><div class="bb-stat-label">Vehicles</div></div><i class="bi bi-car-front bb-stat-icon"></i>
  </div></div></div>
  <div class="col-6 col-md-3"><div class="card bb-stat bb-stat-accent-info"><div class="card-body d-flex justify-content-between align-items-center">
    <div><div class="bb-stat-value"><?= count($jobs) ?></div><div class="bb-stat-label">Recent Jobs</div></div><i class="bi bi-card-checklist bb-stat-icon"></i>
  </div></div></div>
  <div class="col-6 col-md-3"><div class="card bb-stat bb-stat-accent-warning"><div class="card-body d-flex justify-content-between align-items-center">
    <div><div class="bb-stat-value"><?= count($invoices) ?></div><div class="bb-stat-label">Invoices</div></div><i class="bi bi-receipt bb-stat-icon"></i>
  </div></div></div>
  <div class="col-6 col-md-3"><div class="card bb-stat bb-stat-accent-primary"><div class="card-body d-flex justify-content-between align-items-center">
    <div><div class="bb-stat-value"><?= count($quotations) ?></div><div class="bb-stat-label">Quotations</div></div><i class="bi bi-file-earmark-text bb-stat-icon"></i>
  </div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card"><div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-car-front"></i> My Vehicles</h2></div>
    <div class="card-body p-0">
      <?php if (empty($vehicles)): ?><div class="bb-empty text-center py-4"><a href="<?= base_url('vehicles/form.php') ?>">Register a vehicle</a></div>
      <?php else: ?><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Plate</th><th>Make/Model</th><th>Year</th><th class="text-end">Jobs</th></tr></thead><tbody>
      <?php foreach ($vehicles as $v): ?>
        <tr><td class="fw-semibold"><?= e($v['plate_number']) ?></td><td><?= e($v['make'] . ' ' . $v['model']) ?></td><td><?= (int)$v['year'] ?: '—' ?></td><td class="text-end"><span class="badge bg-secondary"><?= (int)$v['job_count'] ?></span></td></tr>
      <?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div></div>
  </div>
  <div class="col-lg-6">
    <div class="card"><div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-card-checklist"></i> Service History</h2></div>
    <div class="card-body p-0">
      <?php if (empty($jobs)): ?><div class="bb-empty text-center py-4">No service history yet.</div>
      <?php else: ?><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Job #</th><th>Vehicle</th><th>Status</th><th>Date</th></tr></thead><tbody>
      <?php foreach ($jobs as $j): ?>
        <tr><td class="fw-semibold"><?= e($j['job_number']) ?></td><td><?= e($j['plate_number'] . ' — ' . $j['make'] . ' ' . $j['model']) ?></td><td><span class="badge bg-<?= $j['status']==='Completed'?'success':($j['status']==='Cancelled'?'danger':($j['status']==='Pending'?'secondary':'info')) ?>"><?= e($j['status']) ?></span></td><td class="text-muted small"><?= date('Y-m-d', strtotime($j['created_at'])) ?></td></tr>
      <?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div></div>
  </div>
  <div class="col-lg-6">
    <div class="card"><div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-receipt"></i> Invoices</h2></div>
    <div class="card-body p-0">
      <?php if (empty($invoices)): ?><div class="bb-empty text-center py-4">No invoices yet.</div>
      <?php else: ?><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Invoice #</th><th>Total</th><th>Paid</th><th>Status</th><th>Date</th></tr></thead><tbody>
      <?php foreach ($invoices as $i): ?>
        <tr><td class="fw-semibold"><?= e($i['invoice_number']) ?></td><td><?= format_kwd($i['total']) ?></td><td class="text-success"><?= format_kwd($i['paid_amount']) ?></td><td><span class="badge bg-<?= $i['status']==='paid'?'success':($i['status']==='overdue'?'danger':'secondary') ?>"><?= e($i['status']) ?></span></td><td class="text-muted small"><?= date('Y-m-d', strtotime($i['created_at'])) ?></td></tr>
      <?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div></div>
  </div>
  <div class="col-lg-6">
    <div class="card"><div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-file-earmark-text"></i> Quotations</h2></div>
    <div class="card-body p-0">
      <?php if (empty($quotations)): ?><div class="bb-empty text-center py-4">No quotations yet.</div>
      <?php else: ?>
      <?php $sc = ['draft'=>'bg-secondary','approved'=>'bg-success','rejected'=>'bg-danger','converted'=>'bg-info']; ?>
      <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Quote #</th><th>Total</th><th>Status</th><th>Date</th></tr></thead><tbody>
      <?php foreach ($quotations as $q): ?>
        <tr><td class="fw-semibold"><?= e($q['quotation_number']) ?></td><td><?= format_kwd($q['total']) ?></td><td><span class="badge bg-<?= $sc[$q['status']] ?? 'bg-secondary' ?>"><?= e($q['status']) ?></span></td><td class="text-muted small"><?= date('Y-m-d', strtotime($q['created_at'])) ?></td></tr>
      <?php endforeach; ?></tbody></table></div><?php endif; ?>
    </div></div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>