<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';

require_role('admin');

$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    'SELECT mr.*, v.plate_number, v.make, v.model, v.customer_id,
            c.name AS customer_name,
            jc.id AS job_id, jc.job_number, jc.status AS job_status, jc.service_category,
            u.full_name AS technician_name
     FROM maintenance_records mr
     JOIN vehicles v ON v.id = mr.vehicle_id
     JOIN customers c ON c.id = v.customer_id
     LEFT JOIN job_cards jc ON jc.id = mr.job_card_id
     LEFT JOIN users u ON u.id = jc.technician_id
     WHERE mr.id = ?'
);
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    set_flash('danger', 'Maintenance record not found.');
    header('Location: ' . base_url('maintenance/index.php'));
    exit;
}

$page_title = 'Maintenance Record #' . $id;
$active = 'maintenance';

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h1 class="bb-page-title"><i class="bi bi-tools bb-text-orange" aria-hidden="true"></i> Maintenance Record #<?= (int) $record['id'] ?></h1>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-secondary" href="<?= base_url('maintenance/form.php?id=' . (int) $record['id']) ?>"><i class="bi bi-pencil" aria-hidden="true"></i> Edit</a>
    <a class="btn btn-outline-secondary" href="<?= base_url('maintenance/index.php') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header"><h2 class="h6 mb-0">Record Details</h2></div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-4">Description</dt><dd class="col-8 bb-prewrap"><?= e($record['description']) ?></dd>
          <dt class="col-4">Service Date</dt><dd class="col-8"><?= format_date($record['service_date']) ?></dd>
          <dt class="col-4">Cost (KWD)</dt><dd class="col-8"><?= $record['cost'] !== null ? e(number_format((float) $record['cost'], 3)) : '—' ?></dd>
          <dt class="col-4">Odometer (km)</dt><dd class="col-8"><?= $record['odometer_km'] !== null ? e(number_format((int) $record['odometer_km'])) : '—' ?></dd>
          <dt class="col-4">Created</dt><dd class="col-8"><?= format_date($record['created_at'], 'd M Y H:i') ?></dd>
          <dt class="col-4">Last Updated</dt><dd class="col-8 mb-0"><?= isset($record['updated_at']) && $record['updated_at'] ? format_date($record['updated_at'], 'd M Y H:i') : '—' ?></dd>
        </dl>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card mb-3">
      <div class="card-header"><h2 class="h6 mb-0">Vehicle</h2></div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-4">Plate</dt>
          <dd class="col-8"><a class="text-decoration-none bb-mono" href="<?= base_url('vehicles/view.php?id=' . (int) $record['vehicle_id']) ?>"><?= e($record['plate_number']) ?></a></dd>
          <dt class="col-4">Vehicle</dt><dd class="col-8"><?= e($record['make'] . ' ' . $record['model']) ?></dd>
          <dt class="col-4">Owner</dt>
          <dd class="col-8 mb-0"><a class="text-decoration-none" href="<?= base_url('customers/view.php?id=' . (int) $record['customer_id']) ?>"><?= e($record['customer_name']) ?></a></dd>
        </dl>
      </div>
    </div>
    <div class="card">
      <div class="card-header"><h2 class="h6 mb-0">Linked Job Card</h2></div>
      <div class="card-body">
        <?php if ($record['job_id']): ?>
          <dl class="row mb-0">
            <dt class="col-4">Job #</dt>
            <dd class="col-8"><a class="text-decoration-none" href="<?= base_url('job_cards/view.php?id=' . (int) $record['job_id']) ?>"><?= e($record['job_number']) ?></a></dd>
            <dt class="col-4">Category</dt><dd class="col-8"><?= e($record['service_category']) ?></dd>
            <dt class="col-4">Technician</dt><dd class="col-8"><?= e($record['technician_name'] ?? 'Unassigned') ?></dd>
            <dt class="col-4">Status</dt><dd class="col-8 mb-0"><?= status_badge($record['job_status']) ?></dd>
          </dl>
        <?php else: ?>
          <p class="text-muted mb-0">The linked job card no longer exists.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
