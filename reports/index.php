<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/report_helpers.php';

require_role('admin');

$page_title = 'Reports';
$active = 'reports';

$f = report_filters_from_get();
$generated = isset($_GET['generate']);

$rows = report_rows($f);
$summary = report_summary($rows);

if ($generated) {
    log_report('report_view', $f);
}

// Groupings for the monthly / technician / category report tables.
$monthly = [];
$workload = [];
$byCategory = [];
foreach ($rows as $row) {
    $m = date('M Y', strtotime($row['created_at']));
    $monthly[$m] = $monthly[$m] ?? ['total' => 0, 'completed' => 0, 'cancelled' => 0];
    $monthly[$m]['total']++;
    if ($row['status'] === 'Completed') {
        $monthly[$m]['completed']++;
    }
    if ($row['status'] === 'Cancelled') {
        $monthly[$m]['cancelled']++;
    }

    $t = $row['technician_name'] ?? 'Unassigned';
    $workload[$t] = $workload[$t] ?? ['total' => 0, 'completed' => 0, 'active' => 0];
    $workload[$t]['total']++;
    if ($row['status'] === 'Completed') {
        $workload[$t]['completed']++;
    }
    if (in_array($row['status'], ['Assigned', 'In Progress'], true)) {
        $workload[$t]['active']++;
    }

    $cat = $row['service_category'];
    $byCategory[$cat] = $byCategory[$cat] ?? ['total' => 0, 'completed' => 0];
    $byCategory[$cat]['total']++;
    if ($row['status'] === 'Completed') {
        $byCategory[$cat]['completed']++;
    }
}
uasort($workload, static fn ($a, $b) => $b['total'] <=> $a['total']);
uasort($byCategory, static fn ($a, $b) => $b['total'] <=> $a['total']);

$technicians = technicians_list();
$customersList = db()->query('SELECT id, name FROM customers ORDER BY name')->fetchAll();

$exportQuery = http_build_query(array_filter($f));

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 no-print">
  <h4 class="fw-bold mb-0"><i class="bi bi-graph-up bb-text-orange"></i> Reports</h4>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-bb" href="<?= base_url('reports/export.php' . ($exportQuery ? '?' . $exportQuery : '')) ?>"><i class="bi bi-download"></i> Export CSV</a>
    <button class="btn btn-outline-secondary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
  </div>
</div>

<div class="mb-3 no-print">
  <span class="me-2 text-muted small">Quick reports:</span>
  <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('reports/index.php') ?>">All job cards</a>
  <a class="btn btn-sm btn-outline-success" href="<?= base_url('reports/index.php?preset=completed&generate=1') ?>">Completed services</a>
  <a class="btn btn-sm btn-outline-primary" href="<?= base_url('reports/index.php?preset=active&generate=1') ?>">Pending / in-progress</a>
  <a class="btn btn-sm btn-outline-dark" href="<?= base_url('reports/index.php?start=' . date('Y-m-01') . '&end=' . date('Y-m-d') . '&generate=1') ?>">This month</a>
</div>

<div class="card mb-3 no-print">
  <div class="card-body py-3">
    <form class="row g-2 align-items-end" method="get" action="<?= base_url('reports/index.php') ?>">
      <input type="hidden" name="generate" value="1">
      <div class="col-md-2">
        <label class="form-label small mb-1">Start date</label>
        <input class="form-control" type="date" name="start" value="<?= e($f['start']) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">End date</label>
        <input class="form-control" type="date" name="end" value="<?= e($f['end']) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Status</label>
        <select class="form-select" name="status">
          <option value="">All</option>
          <?php foreach (JOB_STATUSES as $s): ?>
            <option value="<?= e($s) ?>" <?= $s === $f['status'] ? 'selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Technician</label>
        <select class="form-select" name="technician">
          <option value="0">All</option>
          <?php foreach ($technicians as $t): ?>
            <option value="<?= (int) $t['id'] ?>" <?= $f['technician'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Category</label>
        <select class="form-select" name="category">
          <option value="">All</option>
          <?php foreach (SERVICE_CATEGORIES as $cat): ?>
            <option value="<?= e($cat) ?>" <?= $cat === $f['category'] ? 'selected' : '' ?>><?= e($cat) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Priority</label>
        <select class="form-select" name="priority">
          <option value="">All</option>
          <?php foreach (JOB_PRIORITIES as $p): ?>
            <option value="<?= e($p) ?>" <?= $p === $f['priority'] ? 'selected' : '' ?>><?= e($p) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Customer</label>
        <select class="form-select" name="customer">
          <option value="0">All</option>
          <?php foreach ($customersList as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= $f['customer'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Vehicle plate</label>
        <input class="form-control" type="text" name="plate" value="<?= e($f['plate']) ?>" placeholder="e.g. 8/24173 (vehicle history)">
      </div>
      <div class="col-auto">
        <button class="btn btn-bb-orange" type="submit"><i class="bi bi-funnel"></i> Generate</button>
        <a class="btn btn-outline-secondary" href="<?= base_url('reports/index.php') ?>">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card bb-stat bg-bb-dark"><div class="card-body py-3"><div class="fs-4 fw-bold"><?= $summary['total'] ?></div><div class="small">Total Job Cards</div></div></div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card bb-stat bg-success"><div class="card-body py-3"><div class="fs-4 fw-bold"><?= $summary['completed'] ?></div><div class="small">Completed</div></div></div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card bb-stat bg-bb-orange"><div class="card-body py-3"><div class="fs-4 fw-bold"><?= $summary['in_progress'] ?></div><div class="small">In Progress</div></div></div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card bb-stat" style="background:#6c757d;"><div class="card-body py-3"><div class="fs-4 fw-bold"><?= $summary['pending'] + $summary['assigned'] ?></div><div class="small">Pending / Assigned</div></div></div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card bb-stat" style="background:#212226;"><div class="card-body py-3"><div class="fs-4 fw-bold"><?= $summary['cancelled'] ?></div><div class="small">Cancelled</div></div></div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card bb-stat bg-bb-red"><div class="card-body py-3"><div class="fs-4 fw-bold"><?= $summary['high'] ?></div><div class="small">High Priority</div></div></div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-6">
    <div class="card"><div class="card-body py-3">
      <span class="text-muted small">Most active technician</span>
      <div class="fw-bold"><?= $summary['top_technician'] !== null ? e($summary['top_technician']) : '—' ?></div>
    </div></div>
  </div>
  <div class="col-md-6">
    <div class="card"><div class="card-body py-3">
      <span class="text-muted small">Most common service category</span>
      <div class="fw-bold"><?= $summary['top_category'] !== null ? e($summary['top_category']) : '—' ?></div>
    </div></div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header bg-white fw-bold">Detailed Job Card Report <span class="badge text-bg-secondary"><?= count($rows) ?></span></div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Job #</th><th>Customer</th><th>Plate</th><th>Category</th><th>Technician</th>
            <th>Priority</th><th>Status</th><th>Created</th><th>Est. Completion</th><th>Completed</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="10" class="text-center text-muted py-4">No job cards match the selected filters.</td></tr>
          <?php endif; ?>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td class="fw-semibold"><a class="text-decoration-none" href="<?= base_url('job_cards/view.php?id=' . (int) $row['id']) ?>"><?= e($row['job_number']) ?></a></td>
              <td><?= e($row['customer_name']) ?></td>
              <td><?= e($row['plate_number']) ?></td>
              <td><?= e($row['service_category']) ?></td>
              <td><?= e($row['technician_name'] ?? 'Unassigned') ?></td>
              <td><?= priority_badge($row['priority']) ?></td>
              <td><?= status_badge($row['status']) ?></td>
              <td class="text-muted small"><?= format_date($row['created_at']) ?></td>
              <td class="text-muted small"><?= format_date($row['estimated_completion']) ?></td>
              <td class="text-muted small"><?= $row['completed_at'] ? format_date($row['completed_at']) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold">Monthly Summary</div>
      <div class="card-body">
        <?php if (!$monthly): ?><p class="text-muted mb-0">No data.</p><?php else: ?>
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Month</th><th>Jobs</th><th>Completed</th><th>Cancelled</th></tr></thead>
            <tbody>
              <?php foreach ($monthly as $month => $m): ?>
                <tr><td><?= e($month) ?></td><td><?= $m['total'] ?></td><td><?= $m['completed'] ?></td><td><?= $m['cancelled'] ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold">Technician Workload</div>
      <div class="card-body">
        <?php if (!$workload): ?><p class="text-muted mb-0">No data.</p><?php else: ?>
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Technician</th><th>Jobs</th><th>Active</th><th>Completed</th></tr></thead>
            <tbody>
              <?php foreach ($workload as $name => $w): ?>
                <tr><td><?= e($name) ?></td><td><?= $w['total'] ?></td><td><?= $w['active'] ?></td><td><?= $w['completed'] ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold">Service Categories</div>
      <div class="card-body">
        <?php if (!$byCategory): ?><p class="text-muted mb-0">No data.</p><?php else: ?>
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Category</th><th>Jobs</th><th>Completed</th></tr></thead>
            <tbody>
              <?php foreach ($byCategory as $cat => $c): ?>
                <tr><td><?= e($cat) ?></td><td><?= $c['total'] ?></td><td><?= $c['completed'] ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
