<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';

require_role('admin');

$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    'SELECT v.*, c.id AS owner_id, c.name AS owner_name, c.phone AS owner_phone,
            c.email AS owner_email, c.address AS owner_address
     FROM vehicles v
     JOIN customers c ON c.id = v.customer_id
     WHERE v.id = ?'
);
$stmt->execute([$id]);
$vehicle = $stmt->fetch();

if (!$vehicle) {
    set_flash('danger', 'Vehicle not found.');
    header('Location: ' . base_url('vehicles/index.php'));
    exit;
}

$stmt = db()->prepare(
    'SELECT jc.*, u.full_name AS technician_name
     FROM job_cards jc
     LEFT JOIN users u ON u.id = jc.technician_id
     WHERE jc.vehicle_id = ?
     ORDER BY jc.created_at DESC'
);
$stmt->execute([$id]);
$jobs = $stmt->fetchAll();

$stmt = db()->prepare('SELECT * FROM maintenance_records WHERE vehicle_id = ? ORDER BY service_date DESC');
$stmt->execute([$id]);
$records = $stmt->fetchAll();

$page_title = $vehicle['plate_number'];
$active = 'vehicles';

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h4 class="fw-bold mb-0">
    <i class="bi bi-car-front bb-text-orange"></i>
    <?= e($vehicle['make'] . ' ' . $vehicle['model']) ?>
    <span class="badge text-bg-dark"><?= e($vehicle['plate_number']) ?></span>
  </h4>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-bb-orange" href="<?= base_url('vehicles/form.php?id=' . $id) ?>"><i class="bi bi-pencil"></i> Edit</a>
    <a class="btn btn-outline-secondary" href="<?= base_url('vehicles/index.php') ?>"><i class="bi bi-arrow-left"></i> Back</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header bg-white fw-bold">Vehicle Details</div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-5">Plate</dt><dd class="col-7"><?= e($vehicle['plate_number']) ?></dd>
          <dt class="col-5">Make / Model</dt><dd class="col-7"><?= e($vehicle['make'] . ' ' . $vehicle['model']) ?></dd>
          <dt class="col-5">Year</dt><dd class="col-7"><?= e((string) ($vehicle['year'] ?? '')) ?: '—' ?></dd>
          <dt class="col-5">Color</dt><dd class="col-7"><?= e($vehicle['color'] ?? '') ?: '—' ?></dd>
          <dt class="col-5">VIN / Chassis</dt><dd class="col-7"><?= e($vehicle['vin'] ?? '') ?: '—' ?></dd>
          <dt class="col-5">Registered</dt><dd class="col-7 mb-0"><?= format_date($vehicle['created_at'], 'd M Y H:i') ?></dd>
        </dl>
      </div>
    </div>
    <div class="card">
      <div class="card-header bg-white fw-bold">Owner</div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-5">Name</dt>
          <dd class="col-7"><a class="text-decoration-none" href="<?= base_url('customers/view.php?id=' . (int) $vehicle['owner_id']) ?>"><?= e($vehicle['owner_name']) ?></a></dd>
          <dt class="col-5">Phone</dt><dd class="col-7"><?= e($vehicle['owner_phone']) ?></dd>
          <dt class="col-5">Email</dt><dd class="col-7"><?= e($vehicle['owner_email'] ?? '') ?: '—' ?></dd>
          <dt class="col-5">Address</dt><dd class="col-7 mb-0"><?= e($vehicle['owner_address'] ?? '') ?: '—' ?></dd>
        </dl>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-header bg-white fw-bold">Job Card History <span class="badge text-bg-secondary"><?= count($jobs) ?></span></div>
      <div class="card-body">
        <?php if (!$jobs): ?>
          <p class="text-muted mb-0">No job cards for this vehicle yet.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr><th>Job #</th><th>Category</th><th>Technician</th><th>Priority</th><th>Status</th><th>Created</th><th>Completed</th></tr>
              </thead>
              <tbody>
                <?php foreach ($jobs as $job): ?>
                  <tr>
                    <td class="fw-semibold"><a class="text-decoration-none" href="<?= base_url('job_cards/view.php?id=' . (int) $job['id']) ?>"><?= e($job['job_number']) ?></a></td>
                    <td><?= e($job['service_category']) ?></td>
                    <td><?= e($job['technician_name'] ?? 'Unassigned') ?></td>
                    <td><?= priority_badge($job['priority']) ?></td>
                    <td><?= status_badge($job['status']) ?></td>
                    <td class="text-muted small"><?= format_date($job['created_at']) ?></td>
                    <td class="text-muted small"><?= format_date($job['completed_at']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header bg-white fw-bold">Maintenance Records <span class="badge text-bg-secondary"><?= count($records) ?></span></div>
      <div class="card-body">
        <?php if (!$records): ?>
          <p class="text-muted mb-0">No maintenance records for this vehicle yet.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr><th>Date</th><th>Description</th><th>Cost (KWD)</th><th>Odometer (km)</th></tr>
              </thead>
              <tbody>
                <?php foreach ($records as $record): ?>
                  <tr>
                    <td class="text-muted small"><?= format_date($record['service_date']) ?></td>
                    <td><?= e($record['description']) ?></td>
                    <td><?= $record['cost'] !== null ? e(number_format((float) $record['cost'], 3)) : '—' ?></td>
                    <td><?= $record['odometer_km'] !== null ? e(number_format((int) $record['odometer_km'])) : '—' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
