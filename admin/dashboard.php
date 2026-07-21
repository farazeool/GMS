<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';

require_role('admin');

$page_title = 'Admin Dashboard';
$active = 'dashboard';

$totalVehicles = (int) db()->query('SELECT COUNT(*) FROM vehicles')->fetchColumn();
$activeJobs    = (int) db()->query("SELECT COUNT(*) FROM job_cards WHERE status IN ('Assigned', 'In Progress')")->fetchColumn();
$completed     = (int) db()->query("SELECT COUNT(*) FROM job_cards WHERE status = 'Completed'")->fetchColumn();
$pending       = (int) db()->query("SELECT COUNT(*) FROM job_cards WHERE status = 'Pending'")->fetchColumn();

$recentJobs = db()->query(
    "SELECT jc.id, jc.job_number, jc.service_category, jc.priority, jc.status, jc.created_at,
            c.name AS customer_name, v.plate_number, v.make, v.model,
            u.full_name AS technician_name
     FROM job_cards jc
     JOIN customers c ON c.id = jc.customer_id
     JOIN vehicles v ON v.id = jc.vehicle_id
     LEFT JOIN users u ON u.id = jc.technician_id
     ORDER BY jc.created_at DESC
     LIMIT 8"
)->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <div>
    <h1 class="bb-page-title">Admin Dashboard</h1>
    <span class="bb-page-subtitle">Welcome back, <?= e(current_user_name()) ?></span>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-bb" href="<?= base_url('job_cards/form.php') ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> New Job Card</a>
    <a class="btn btn-outline-secondary" href="<?= base_url('vehicles/form.php') ?>"><i class="bi bi-car-front" aria-hidden="true"></i> Register Vehicle</a>
    <a class="btn btn-outline-secondary" href="<?= base_url('reports/index.php') ?>"><i class="bi bi-graph-up" aria-hidden="true"></i> Reports</a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card bb-stat bb-stat-accent-info"><div class="card-body d-flex justify-content-between align-items-center">
      <div><div class="bb-stat-value"><?= $totalVehicles ?></div><div class="bb-stat-label">Total Vehicles</div></div>
      <i class="bi bi-car-front bb-stat-icon" aria-hidden="true"></i>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card bb-stat bb-stat-accent-warning"><div class="card-body d-flex justify-content-between align-items-center">
      <div><div class="bb-stat-value"><?= $activeJobs ?></div><div class="bb-stat-label">Active Jobs</div></div>
      <i class="bi bi-wrench-adjustable bb-stat-icon" aria-hidden="true"></i>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card bb-stat bb-stat-accent-success"><div class="card-body d-flex justify-content-between align-items-center">
      <div><div class="bb-stat-value"><?= $completed ?></div><div class="bb-stat-label">Completed Services</div></div>
      <i class="bi bi-check-circle bb-stat-icon" aria-hidden="true"></i>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card bb-stat bb-stat-accent-primary"><div class="card-body d-flex justify-content-between align-items-center">
      <div><div class="bb-stat-value"><?= $pending ?></div><div class="bb-stat-label">Pending Job Cards</div></div>
      <i class="bi bi-hourglass-split bb-stat-icon" aria-hidden="true"></i>
    </div></div>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex align-items-center gap-2">
    <i class="bi bi-card-checklist bb-text-orange" aria-hidden="true"></i>
    <h2 class="h6 mb-0">Recent Job Cards</h2>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Job #</th><th>Customer</th><th>Vehicle</th><th>Category</th>
            <th>Technician</th><th>Priority</th><th>Status</th><th>Created</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$recentJobs): ?>
            <tr><td colspan="8" class="bb-empty">No job cards yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($recentJobs as $job): ?>
            <tr>
              <td class="fw-semibold"><a class="text-decoration-none" href="<?= base_url('job_cards/view.php?id=' . (int) $job['id']) ?>"><?= e($job['job_number']) ?></a></td>
              <td><?= e($job['customer_name']) ?></td>
              <td><?= e($job['make'] . ' ' . $job['model']) ?> <span class="text-muted small">(<?= e($job['plate_number']) ?>)</span></td>
              <td><?= e($job['service_category']) ?></td>
              <td><?= e($job['technician_name'] ?? 'Unassigned') ?></td>
              <td><?= priority_badge($job['priority']) ?></td>
              <td><?= status_badge($job['status']) ?></td>
              <td class="text-muted small"><?= format_date($job['created_at'], 'd M Y H:i') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
