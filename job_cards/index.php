<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/job_helpers.php';

require_login();

$page_title = 'Job Cards';
$active = 'job_cards';

$q        = trim($_GET['q'] ?? '');
$status   = $_GET['status'] ?? '';
$priority = $_GET['priority'] ?? '';
$category = $_GET['category'] ?? '';
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');

if (!in_array($status, JOB_STATUSES, true)) {
    $status = '';
}
if (!in_array($priority, JOB_PRIORITIES, true)) {
    $priority = '';
}
if (!in_array($category, SERVICE_CATEGORIES, true)) {
    $category = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}

$sql = 'SELECT jc.*, c.name AS customer_name, v.plate_number, v.make, v.model,
               u.full_name AS technician_name
        FROM job_cards jc
        JOIN customers c ON c.id = jc.customer_id
        JOIN vehicles v ON v.id = jc.vehicle_id
        LEFT JOIN users u ON u.id = jc.technician_id
        WHERE 1 = 1';
$params = [];

if (!is_admin()) {
    $sql .= ' AND jc.technician_id = ?';
    $params[] = current_user_id();
}
if ($q !== '') {
    $sql .= ' AND (jc.job_number LIKE ? OR c.name LIKE ? OR v.plate_number LIKE ? OR u.full_name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($status !== '') {
    $sql .= ' AND jc.status = ?';
    $params[] = $status;
}
if ($priority !== '') {
    $sql .= ' AND jc.priority = ?';
    $params[] = $priority;
}
if ($category !== '') {
    $sql .= ' AND jc.service_category = ?';
    $params[] = $category;
}
if ($dateFrom !== '') {
    $sql .= ' AND DATE(jc.created_at) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $sql .= ' AND DATE(jc.created_at) <= ?';
    $params[] = $dateTo;
}
$sql .= ' ORDER BY jc.created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h4 class="fw-bold mb-0"><?= is_admin() ? 'Job Cards' : 'My Job Cards' ?> <span class="badge text-bg-secondary fs-6"><?= count($jobs) ?></span></h4>
  <?php if (is_admin()): ?>
    <a class="btn btn-bb" href="<?= base_url('job_cards/form.php') ?>"><i class="bi bi-plus-lg"></i> New Job Card</a>
  <?php endif; ?>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form class="row g-2 align-items-end" method="get" action="<?= base_url('job_cards/index.php') ?>">
      <div class="col-md-4 col-lg-3">
        <label class="form-label small mb-1">Search</label>
        <input class="form-control" type="text" name="q" value="<?= e($q) ?>" placeholder="Job #, customer, plate, technician">
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Status</label>
        <select class="form-select" name="status">
          <option value="">All</option>
          <?php foreach (JOB_STATUSES as $s): ?>
            <option value="<?= e($s) ?>" <?= $s === $status ? 'selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Priority</label>
        <select class="form-select" name="priority">
          <option value="">All</option>
          <?php foreach (JOB_PRIORITIES as $p): ?>
            <option value="<?= e($p) ?>" <?= $p === $priority ? 'selected' : '' ?>><?= e($p) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 col-lg-2">
        <label class="form-label small mb-1">Category</label>
        <select class="form-select" name="category">
          <option value="">All</option>
          <?php foreach (SERVICE_CATEGORIES as $cat): ?>
            <option value="<?= e($cat) ?>" <?= $cat === $category ? 'selected' : '' ?>><?= e($cat) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 col-lg-1">
        <label class="form-label small mb-1">From</label>
        <input class="form-control" type="date" name="date_from" value="<?= e($dateFrom) ?>">
      </div>
      <div class="col-md-2 col-lg-1">
        <label class="form-label small mb-1">To</label>
        <input class="form-control" type="date" name="date_to" value="<?= e($dateTo) ?>">
      </div>
      <div class="col-auto">
        <button class="btn btn-bb-orange" type="submit">Filter</button>
        <a class="btn btn-outline-secondary" href="<?= base_url('job_cards/index.php') ?>">Clear</a>
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
            <th>Job #</th><th>Customer</th><th>Vehicle</th><th>Category</th>
            <th>Technician</th><th>Priority</th><th>Status</th><th>Created</th><th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$jobs): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">No job cards match your search or filters.</td></tr>
          <?php endif; ?>
          <?php foreach ($jobs as $job): ?>
            <tr>
              <td class="fw-semibold"><a class="text-decoration-none" href="<?= base_url('job_cards/view.php?id=' . (int) $job['id']) ?>"><?= e($job['job_number']) ?></a></td>
              <td><?= e($job['customer_name']) ?></td>
              <td><?= e($job['make'] . ' ' . $job['model']) ?> <span class="text-muted small">(<?= e($job['plate_number']) ?>)</span></td>
              <td><?= e($job['service_category']) ?></td>
              <td><?= e($job['technician_name'] ?? 'Unassigned') ?></td>
              <td><?= priority_badge($job['priority']) ?></td>
              <td><?= status_badge($job['status']) ?></td>
              <td class="text-muted small"><?= format_date($job['created_at']) ?></td>
              <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('job_cards/view.php?id=' . (int) $job['id']) ?>" title="View"><i class="bi bi-eye"></i></a>
                <?php if (is_admin()): ?>
                  <a class="btn btn-sm btn-outline-primary" href="<?= base_url('job_cards/form.php?id=' . (int) $job['id']) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                  <form class="d-inline" method="post" action="<?= base_url('job_cards/delete.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $job['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete"
                            data-confirm="Delete job card <?= e($job['job_number']) ?>? Its service notes will also be deleted.">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
