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
    "SELECT jc.job_number, jc.service_category, jc.priority, jc.status, jc.created_at,
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
    <h4 class="fw-bold mb-0">Admin Dashboard</h4>
    <span class="text-muted small">Welcome back, <?= e(current_user_name()) ?></span>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-bb" href="<?= base_url('job_cards/index.php') ?>"><i class="bi bi-plus-lg"></i> New Job Card</a>
    <a class="btn btn-bb-orange" href="<?= base_url('vehicles/index.php') ?>"><i class="bi bi-car-front"></i> Register Vehicle</a>
    <a class="btn btn-dark" href="<?= base_url('reports/index.php') ?>"><i class="bi bi-graph-up"></i> Generate Report</a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card bb-stat bg-bb-dark"><div class="card-body d-flex justify-content-between align-items-center">
      <div><div class="fs-3 fw-bold"><?= $totalVehicles ?></div><div class="small">Total Vehicles</div></div>
      <i class="bi bi-car-front"></i>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card bb-stat bg-bb-orange"><div class="card-body d-flex justify-content-between align-items-center">
      <div><div class="fs-3 fw-bold"><?= $activeJobs ?></div><div class="small">Active Jobs</div></div>
      <i class="bi bi-wrench-adjustable"></i>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card bb-stat bg-success"><div class="card-body d-flex justify-content-between align-items-center">
      <div><div class="fs-3 fw-bold"><?= $completed ?></div><div class="small">Completed Services</div></div>
      <i class="bi bi-check-circle"></i>
    </div></div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card bb-stat bg-bb-red"><div class="card-body d-flex justify-content-between align-items-center">
      <div><div class="fs-3 fw-bold"><?= $pending ?></div><div class="small">Pending Job Cards</div></div>
      <i class="bi bi-hourglass-split"></i>
    </div></div>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <h6 class="fw-bold mb-3"><i class="bi bi-card-checklist text-danger"></i> Recent Job Cards</h6>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Job #</th><th>Customer</th><th>Vehicle</th><th>Category</th>
            <th>Technician</th><th>Priority</th><th>Status</th><th>Created</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$recentJobs): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No job cards yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($recentJobs as $job): ?>
            <tr>
              <td class="fw-semibold"><?= e($job['job_number']) ?></td>
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
