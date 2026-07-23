<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/uuid.php';
require_once __DIR__ . '/../includes/portal_session.php';
require_once __DIR__ . '/../includes/commercial.php';

portal_start_session();
portal_require_login();

$customerId = portal_customer_id();
$vehicles = db()->prepare('SELECT v.*, (SELECT COUNT(*) FROM job_cards jc WHERE jc.vehicle_id = v.id) AS job_count
                           FROM vehicles v WHERE v.customer_id = ? AND v.deleted_at IS NULL ORDER BY v.created_at DESC')
              ->execute([$customerId])->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'My Vehicles';
$active = 'vehicles';
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="bb-page-title">My Vehicles <span class="badge text-bg-secondary"><?= count($vehicles) ?></span></h1>
  <a class="btn btn-bb" href="<?= base_url('vehicles/form.php') ?>"><i class="bi bi-car-front"></i> Add Vehicle</a>
</div>
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Plate</th><th>Make/Model</th><th>Year</th><th>Color</th><th>VIN</th><th class="text-end">Jobs</th></tr></thead>
        <tbody>
        <?php if (empty($vehicles)): ?>
          <tr><td colspan="6" class="bb-empty">No vehicles yet. <a href="<?= base_url('vehicles/form.php') ?>">Add one</a>.</td></tr>
        <?php else: foreach ($vehicles as $v): ?>
          <tr>
            <td class="fw-semibold"><a class="text-decoration-none" href="<?= base_url('vehicles/view.php?id=' . $v['id']) ?>"><?= e($v['plate_number']) ?></a></td>
            <td><?= e($v['make'] . ' ' . $v['model']) ?></td>
            <td><?= (int)$v['year'] ?: '—' ?></td>
            <td><?= e($v['color'] ?? '—') ?></td>
            <td><?= e($v['vin'] ?? '—') ?></td>
            <td class="text-end"><span class="badge bg-secondary"><?= (int)$v['job_count'] ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>