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
  <h1 class="bb-page-title"><i class="bi bi-graph-up bb-text-orange" aria-hidden="true"></i> Reports</h1>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-bb" href="<?= base_url('reports/export.php' . ($exportQuery ? '?' . $exportQuery : '')) ?>"><i class="bi bi-download" aria-hidden="true"></i> Export CSV</a>
    <button class="btn btn-outline-secondary" type="button" onclick="window.print()"><i class="bi bi-printer" aria-hidden="true"></i> Print</button>
  </div>
</div>

<div class="mb-3 no-print">
  <span class="me-2 text-muted small">Quick reports:</span>
  <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('reports/index.php') ?>">All job cards</a>
  <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('reports/index.php?preset=completed&generate=1') ?>">Completed services</a>
  <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('reports/index.php?preset=active&generate=1') ?>">Pending / in-progress</a>
  <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('reports/index.php?start=' . date('Y-m-01') . '&end=' . date('Y-m-d') . '&generate=1') ?>">This month</a>
</div>

<div class="card mb-3 no-print">
  <div class="card-body py-3">
    <form class="row g-2 align-items-end" method="get" action="<?= base_url('reports/index.php') ?>">
      <input type="hidden" name="generate" value="1">
      <div class="col-md-2">
        <label class="form-label small mb-1" for="start">Start date</label>
        <input class="form-control" type="date" id="start" name="start" value="<?= e($f['start']) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1" for="end">End date</label>
        <input class="form-control" type="date" id="end" name="end" value="<?= e($f['end']) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1" for="status">Status</label>
        <select class="form-select" id="status" name="status">
          <option value="">All</option>
          <?php foreach (JOB_STATUSES as $s): ?>
            <option value="<?= e($s) ?>" <?= $s === $f['status'] ? 'selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1" for="technician">Technician</label>
        <select class="form-select" id="technician" name="technician">
          <option value="0">All</option>
          <?php foreach ($technicians as $t): ?>
            <option value="<?= (int) $t['id'] ?>" <?= $f['technician'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1" for="category">Category</label>
        <select class="form-select" id="category" name="category">
          <option value="">All</option>
          <?php foreach (SERVICE_CATEGORIES as $cat): ?>
            <option value="<?= e($cat) ?>" <?= $cat === $f['category'] ? 'selected' : '' ?>><?= e($cat) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1" for="priority">Priority</label>
        <select class="form-select" id="priority" name="priority">
          <option value="">All</option>
          <?php foreach (JOB_PRIORITIES as $p): ?>
            <option value="<?= e($p) ?>" <?= $p === $f['priority'] ? 'selected' : '' ?>><?= e($p) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label small mb-1" for="customer">Customer</label>
        <select class="form-select" id="customer" name="customer">
          <option value="0">All</option>
          <?php foreach ($customersList as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= $f['customer'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label small mb-1" for="plate">Vehicle plate</label>
        <input class="form-control" type="text" id="plate" name="plate" value="<?= e($f['plate']) ?>" placeholder="e.g. 8/24173 (vehicle history)">
      </div>
      <div class="col-md-4">
        <button class="btn btn-bb-orange" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i> Generate</button>
        <a class="btn btn-outline-secondary" href="<?= base_url('reports/index.php') ?>">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card bb-stat bb-stat-accent-info"><div class="card-body py-3"><div class="bb-stat-value"><?= $summary['total'] ?></div><div class="bb-stat-label">Total Job Cards</div></div></div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card bb-stat bb-stat-accent-success"><div class="card-body py-3"><div class="bb-stat-value"><?= $summary['completed'] ?></div><div class="bb-stat-label">Completed</div></div></div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card bb-stat bb-stat-accent-warning"><div class="card-body py-3"><div class="bb-stat-value"><?= $summary['in_progress'] ?></div><div class="bb-stat-label">In Progress</div></div></div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card bb-stat bb-stat-accent-info"><div class="card-body py-3"><div class="bb-stat-value"><?= $summary['pending'] + $summary['assigned'] ?></div><div class="bb-stat-label">Pending / Assigned</div></div></div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card bb-stat bb-stat-accent-primary"><div class="card-body py-3"><div class="bb-stat-value"><?= $summary['cancelled'] ?></div><div class="bb-stat-label">Cancelled</div></div></div>
  </div>
  <div class="col-6 col-md-4 col-xl-2">
    <div class="card bb-stat bb-stat-accent-primary"><div class="card-body py-3"><div class="bb-stat-value"><?= $summary['high'] ?></div><div class="bb-stat-label">High Priority</div></div></div>
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
  <div class="card-header d-flex align-items-center gap-2">
    <h2 class="h6 mb-0">Detailed Job Card Report</h2>
    <span class="badge text-bg-secondary"><?= count($rows) ?></span>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Job #</th><th>Customer</th><th>Plate</th><th>Category</th><th>Technician</th>
            <th>Priority</th><th>Status</th><th>Created</th><th>Est. Completion</th><th>Completed</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="10" class="bb-empty">No job cards match the selected filters.</td></tr>
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
      <div class="card-header"><h2 class="h6 mb-0">Monthly Summary</h2></div>
      <div class="card-body">
        <?php if (!$monthly): ?><p class="bb-empty mb-0">No data.</p><?php else: ?>
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Month</th><th class="bb-num">Jobs</th><th class="bb-num">Completed</th><th class="bb-num">Cancelled</th></tr></thead>
            <tbody>
              <?php foreach ($monthly as $month => $m): ?>
                <tr><td><?= e($month) ?></td><td class="bb-num"><?= $m['total'] ?></td><td class="bb-num"><?= $m['completed'] ?></td><td class="bb-num"><?= $m['cancelled'] ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><h2 class="h6 mb-0">Technician Workload</h2></div>
      <div class="card-body">
        <?php if (!$workload): ?><p class="bb-empty mb-0">No data.</p><?php else: ?>
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Technician</th><th class="bb-num">Jobs</th><th class="bb-num">Active</th><th class="bb-num">Completed</th></tr></thead>
            <tbody>
              <?php foreach ($workload as $name => $w): ?>
                <tr><td><?= e($name) ?></td><td class="bb-num"><?= $w['total'] ?></td><td class="bb-num"><?= $w['active'] ?></td><td class="bb-num"><?= $w['completed'] ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><h2 class="h6 mb-0">Service Categories</h2></div>
      <div class="card-body">
        <?php if (!$byCategory): ?><p class="bb-empty mb-0">No data.</p><?php else: ?>
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Category</th><th class="bb-num">Jobs</th><th class="bb-num">Completed</th></tr></thead>
            <tbody>
              <?php foreach ($byCategory as $cat => $c): ?>
                <tr><td><?= e($cat) ?></td><td class="bb-num"><?= $c['total'] ?></td><td class="bb-num"><?= $c['completed'] ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
