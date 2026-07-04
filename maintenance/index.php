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
  <h4 class="fw-bold mb-0"><i class="bi bi-tools bb-text-orange"></i> Maintenance Records <span class="badge text-bg-secondary fs-6"><?= count($records) ?></span></h4>
</div>

<div class="alert alert-light border small">
  <i class="bi bi-info-circle"></i> Maintenance records are created automatically when a job card is marked <strong>Completed</strong>. You can edit the description, service date, cost, and odometer here.
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form class="row g-2 align-items-end" method="get" action="<?= base_url('maintenance/index.php') ?>">
      <div class="col-md-3">
        <label class="form-label small mb-1">Search</label>
        <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Plate, description, job #">
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">From</label>
        <input class="form-control" type="date" name="date_from" value="<?= e($dateFrom) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">To</label>
        <input class="form-control" type="date" name="date_to" value="<?= e($dateTo) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Category</label>
        <select class="form-select" name="category">
          <option value="">All</option>
          <?php foreach (SERVICE_CATEGORIES as $cat): ?>
            <option value="<?= e($cat) ?>" <?= $cat === $category ? 'selected' : '' ?>><?= e($cat) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Job status</label>
        <select class="form-select" name="status">
          <option value="">All</option>
          <?php foreach (JOB_STATUSES as $s): ?>
            <option value="<?= e($s) ?>" <?= $s === $status ? 'selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Technician</label>
        <select class="form-select" name="technician">
          <option value="0">All</option>
          <?php foreach ($technicians as $t): ?>
            <option value="<?= (int) $t['id'] ?>" <?= $technician === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <button class="btn btn-bb-orange" type="submit">Filter</button>
        <a class="btn btn-outline-secondary" href="<?= base_url('maintenance/index.php') ?>">Clear</a>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Service Date</th><th>Vehicle</th><th>Job #</th><th>Category</th><th>Technician</th>
            <th>Cost (KWD)</th><th>Odometer (km)</th><th>Job Status</th><th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$records): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">No maintenance records match your search or filters.</td></tr>
          <?php endif; ?>
          <?php foreach ($records as $r): ?>
            <tr>
              <td class="text-muted small"><?= format_date($r['service_date']) ?></td>
              <td>
                <a class="text-decoration-none fw-semibold" href="<?= base_url('vehicles/view.php?id=' . (int) $r['vehicle_id']) ?>"><?= e($r['plate_number']) ?></a>
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
              <td><?= $r['cost'] !== null ? e(number_format((float) $r['cost'], 3)) : '—' ?></td>
              <td><?= $r['odometer_km'] !== null ? e(number_format((int) $r['odometer_km'])) : '—' ?></td>
              <td><?= $r['job_status'] ? status_badge($r['job_status']) : '<span class="text-muted">—</span>' ?></td>
              <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('maintenance/view.php?id=' . (int) $r['id']) ?>" title="View"><i class="bi bi-eye"></i></a>
                <a class="btn btn-sm btn-outline-primary" href="<?= base_url('maintenance/form.php?id=' . (int) $r['id']) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
