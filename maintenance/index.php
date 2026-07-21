<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/job_helpers.php';

require_role('admin');

$page_title = 'Maintenance Records';
$active = 'maintenance';

$q          = trim($_GET['q'] ?? '');
$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to'] ?? '');
$category   = $_GET['category'] ?? '';
$status     = $_GET['status'] ?? '';
$technician = (int) ($_GET['technician'] ?? 0);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}
if (!in_array($category, SERVICE_CATEGORIES, true)) {
    $category = '';
}
if (!in_array($status, JOB_STATUSES, true)) {
    $status = '';
}

$sql = 'SELECT mr.*, v.plate_number, v.make, v.model,
               jc.id AS job_id, jc.job_number, jc.status AS job_status, jc.service_category,
               u.full_name AS technician_name
        FROM maintenance_records mr
        JOIN vehicles v ON v.id = mr.vehicle_id
        LEFT JOIN job_cards jc ON jc.id = mr.job_card_id
        LEFT JOIN users u ON u.id = jc.technician_id
        WHERE 1 = 1';
$params = [];

if ($q !== '') {
    $sql .= ' AND (v.plate_number LIKE ? OR mr.description LIKE ? OR jc.job_number LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($dateFrom !== '') {
    $sql .= ' AND mr.service_date >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $sql .= ' AND mr.service_date <= ?';
    $params[] = $dateTo;
}
if ($category !== '') {
    $sql .= ' AND jc.service_category = ?';
    $params[] = $category;
}
if ($status !== '') {
    $sql .= ' AND jc.status = ?';
    $params[] = $status;
}
if ($technician > 0) {
    $sql .= ' AND jc.technician_id = ?';
    $params[] = $technician;
}
$sql .= ' ORDER BY mr.service_date DESC, mr.id DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

$technicians = technicians_list();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h1 class="bb-page-title"><i class="bi bi-tools bb-text-orange" aria-hidden="true"></i> Maintenance Records <span class="badge text-bg-secondary align-middle"><?= count($records) ?></span></h1>
</div>

<div class="alert alert-info border-0 small" role="note">
  <i class="bi bi-info-circle" aria-hidden="true"></i> Maintenance records are created automatically when a job card is marked <strong>Completed</strong>. You can edit the description, service date, cost, and odometer here.
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form class="row g-2 align-items-end" method="get" action="<?= base_url('maintenance/index.php') ?>">
      <div class="col-12 col-md-4">
        <label class="form-label small mb-1" for="filter-q">Search</label>
        <input class="form-control" type="text" id="filter-q" name="q" value="<?= e($q) ?>" placeholder="Plate, description, job #">
      </div>
      <div class="col-6 col-md-4">
        <label class="form-label small mb-1" for="filter-date-from">From</label>
        <input class="form-control" type="date" id="filter-date-from" name="date_from" value="<?= e($dateFrom) ?>">
      </div>
      <div class="col-6 col-md-4">
        <label class="form-label small mb-1" for="filter-date-to">To</label>
        <input class="form-control" type="date" id="filter-date-to" name="date_to" value="<?= e($dateTo) ?>">
      </div>
      <div class="col-6 col-md-4">
        <label class="form-label small mb-1" for="filter-category">Category</label>
        <select class="form-select" id="filter-category" name="category">
          <option value="">All</option>
          <?php foreach (SERVICE_CATEGORIES as $cat): ?>
            <option value="<?= e($cat) ?>" <?= $cat === $category ? 'selected' : '' ?>><?= e($cat) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-4">
        <label class="form-label small mb-1" for="filter-status">Job status</label>
        <select class="form-select" id="filter-status" name="status">
          <option value="">All</option>
          <?php foreach (JOB_STATUSES as $s): ?>
            <option value="<?= e($s) ?>" <?= $s === $status ? 'selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-4">
        <label class="form-label small mb-1" for="filter-technician">Technician</label>
        <select class="form-select" id="filter-technician" name="technician">
          <option value="0">All</option>
          <?php foreach ($technicians as $t): ?>
            <option value="<?= (int) $t['id'] ?>" <?= $technician === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-4">
        <button class="btn btn-bb" type="submit">Filter</button>
        <a class="btn btn-outline-secondary" href="<?= base_url('maintenance/index.php') ?>">Clear</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Service Date</th><th>Vehicle</th><th>Job #</th><th>Category</th><th>Technician</th>
            <th class="bb-num">Cost (KWD)</th><th class="bb-num">Odometer (km)</th><th>Job Status</th><th class="bb-actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$records): ?>
            <tr><td colspan="9" class="bb-empty">No maintenance records match your search or filters.</td></tr>
          <?php endif; ?>
          <?php foreach ($records as $r): ?>
            <tr>
              <td class="text-muted small"><?= format_date($r['service_date']) ?></td>
              <td>
                <a class="text-decoration-none fw-semibold bb-mono" href="<?= base_url('vehicles/view.php?id=' . (int) $r['vehicle_id']) ?>"><?= e($r['plate_number']) ?></a>
                <span class="text-muted small d-block"><?= e($r['make'] . ' ' . $r['model']) ?></span>
              </td>
              <td>
                <?php if ($r['job_id']): ?>
                  <a class="text-decoration-none" href="<?= base_url('job_cards/view.php?id=' . (int) $r['job_id']) ?>"><?= e($r['job_number']) ?></a>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td><?= e($r['service_category'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
              <td><?= e($r['technician_name'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
              <td class="bb-num"><?= $r['cost'] !== null ? e(number_format((float) $r['cost'], 3)) : '—' ?></td>
              <td class="bb-num"><?= $r['odometer_km'] !== null ? e(number_format((int) $r['odometer_km'])) : '—' ?></td>
              <td><?= $r['job_status'] ? status_badge($r['job_status']) : '<span class="text-muted">—</span>' ?></td>
              <td class="bb-actions">
                <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('maintenance/view.php?id=' . (int) $r['id']) ?>" aria-label="View maintenance record" title="View"><i class="bi bi-eye" aria-hidden="true"></i></a>
                <a class="btn btn-sm btn-outline-primary" href="<?= base_url('maintenance/form.php?id=' . (int) $r['id']) ?>" aria-label="Edit maintenance record" title="Edit"><i class="bi bi-pencil" aria-hidden="true"></i></a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
