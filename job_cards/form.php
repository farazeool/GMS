<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/job_helpers.php';
require_once __DIR__ . '/../includes/sync_helpers.php';

require_role('admin');

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;

$job = [
    'customer_id'          => (int) ($_GET['customer_id'] ?? 0),
    'vehicle_id'           => (int) ($_GET['vehicle_id'] ?? 0),
    'service_category'     => '',
    'technician_id'        => 0,
    'priority'             => 'Medium',
    'status'               => 'Pending',
    'problem_description'  => '',
    'estimated_completion' => '',
];
$existing = null;

if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM job_cards WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        set_flash('danger', 'Job card not found.');
        header('Location: ' . base_url('job_cards/index.php'));
        exit;
    }
    $job = $existing;
}

$customersList = db()->query('SELECT id, name FROM customers ORDER BY name')->fetchAll();
$vehiclesList = db()->query(
    'SELECT v.id, v.plate_number, v.make, v.model, v.customer_id, c.name AS customer_name
     FROM vehicles v JOIN customers c ON c.id = v.customer_id
     ORDER BY c.name, v.plate_number'
)->fetchAll();
$technicians = technicians_list();
$technicianIds = array_map(static fn ($t) => (int) $t['id'], $technicians);

// New job cards can only start as Pending or Assigned (workflow rule 1).
$statusOptions = $isEdit ? JOB_STATUSES : ['Pending', 'Assigned'];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $job['customer_id']          = (int) ($_POST['customer_id'] ?? 0);
    $job['vehicle_id']           = (int) ($_POST['vehicle_id'] ?? 0);
    $job['service_category']     = $_POST['service_category'] ?? '';
    $job['technician_id']        = (int) ($_POST['technician_id'] ?? 0);
    $job['priority']             = $_POST['priority'] ?? '';
    $job['status']               = $_POST['status'] ?? '';
    $job['problem_description']  = trim($_POST['problem_description'] ?? '');
    $job['estimated_completion'] = trim($_POST['estimated_completion'] ?? '');

    if ($job['customer_id'] <= 0) {
        $errors[] = 'Please select a customer.';
    }

    $vehicleRow = null;
    if ($job['vehicle_id'] <= 0) {
        $errors[] = 'Please select a vehicle.';
    } else {
        $stmt = db()->prepare('SELECT id, customer_id FROM vehicles WHERE id = ?');
        $stmt->execute([$job['vehicle_id']]);
        $vehicleRow = $stmt->fetch();
        if (!$vehicleRow) {
            $errors[] = 'Selected vehicle does not exist.';
        } elseif ($job['customer_id'] > 0 && (int) $vehicleRow['customer_id'] !== $job['customer_id']) {
            $errors[] = 'Selected vehicle does not belong to the selected customer.';
        }
    }

    if (!in_array($job['service_category'], SERVICE_CATEGORIES, true)) {
        $errors[] = 'Please select a valid service category.';
    }
    if (!in_array($job['priority'], JOB_PRIORITIES, true)) {
        $errors[] = 'Please select a valid priority.';
    }
    if (!in_array($job['status'], $statusOptions, true)) {
        $errors[] = 'Please select a valid status.';
    }
    if ($job['problem_description'] === '') {
        $errors[] = 'Problem description is required.';
    }

    if ($job['technician_id'] > 0 && !in_array($job['technician_id'], $technicianIds, true)) {
        $errors[] = 'Selected technician is not a valid active technician.';
    }
    if ($job['status'] === 'Assigned' && $job['technician_id'] <= 0) {
        $errors[] = 'Assign a technician to use the Assigned status.';
    }

    if ($job['estimated_completion'] !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $job['estimated_completion']);
        if (!$d || $d->format('Y-m-d') !== $job['estimated_completion']) {
            $errors[] = 'Estimated completion date is not valid.';
        }
    }

    if ($isEdit && $job['status'] === 'Completed' && job_note_count($id) === 0) {
        $errors[] = 'This job card has no service notes. Add at least one note before marking it Completed.';
    }

    if (!$errors) {
        $technicianId = $job['technician_id'] > 0 ? $job['technician_id'] : null;
        $estimated = $job['estimated_completion'] !== '' ? $job['estimated_completion'] : null;

        if ($isEdit) {
            // Completed date rules: keep/set on Completed, clear otherwise.
            $completedAt = null;
            if ($job['status'] === 'Completed') {
                $completedAt = $existing['completed_at'] ?: date('Y-m-d H:i:s');
            }

            $stmt = db()->prepare(
                'UPDATE job_cards
                 SET customer_id = ?, vehicle_id = ?, service_category = ?, technician_id = ?,
                     priority = ?, status = ?, problem_description = ?, estimated_completion = ?, completed_at = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $job['customer_id'], $job['vehicle_id'], $job['service_category'], $technicianId,
                $job['priority'], $job['status'], $job['problem_description'], $estimated, $completedAt, $id,
            ]);
            sync_mark_record_dirty('job_cards', $id);

            if ($job['status'] === 'Completed') {
                sync_completion_maintenance($id);
            } elseif (($existing['status'] ?? '') === 'Completed') {
                $stmt = db()->prepare('DELETE FROM maintenance_records WHERE job_card_id = ?');
                $stmt->execute([$id]);
            }

            set_flash('success', 'Job card updated successfully.');
        } else {
            $jobNumber = generate_job_number();
            $stmt = db()->prepare(
                'INSERT INTO job_cards
                 (job_number, customer_id, vehicle_id, service_category, technician_id, priority, status, problem_description, estimated_completion)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $jobNumber, $job['customer_id'], $job['vehicle_id'], $job['service_category'], $technicianId,
                $job['priority'], $job['status'], $job['problem_description'], $estimated,
            ]);
            $id = (int) db()->lastInsertId();
            sync_mark_record_dirty('job_cards', $id);
            set_flash('success', 'Job card ' . $jobNumber . ' created successfully.');
        }

        header('Location: ' . base_url('job_cards/view.php?id=' . $id));
        exit;
    }
}

$page_title = $isEdit ? 'Edit Job Card' : 'New Job Card';
$active = 'job_cards';

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><?= $isEdit ? 'Edit Job Card ' . e($existing['job_number']) : 'New Job Card' ?></h4>
  <a class="btn btn-outline-secondary" href="<?= base_url('job_cards/index.php') ?>"><i class="bi bi-arrow-left"></i> Back to Job Cards</a>
</div>

<?php if (!$customersList || !$vehiclesList): ?>
  <div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i> You need at least one customer and one vehicle before creating a job card.
    <a class="alert-link" href="<?= base_url('customers/form.php') ?>">Add a customer</a> or
    <a class="alert-link" href="<?= base_url('vehicles/form.php') ?>">register a vehicle</a> first.
  </div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <strong>Please fix the following:</strong>
    <ul class="mb-0">
      <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card" style="max-width: 920px;">
  <div class="card-body p-4">
    <form method="post" action="<?= base_url('job_cards/form.php' . ($isEdit ? '?id=' . $id : '')) ?>">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="customer_id">Customer <span class="text-danger">*</span></label>
          <select class="form-select" id="customer_id" name="customer_id" required>
            <option value="">— Select customer —</option>
            <?php foreach ($customersList as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= (int) $job['customer_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="vehicle_id">Vehicle <span class="text-danger">*</span></label>
          <select class="form-select" id="vehicle_id" name="vehicle_id" required>
            <option value="">— Select vehicle —</option>
            <?php foreach ($vehiclesList as $v): ?>
              <option value="<?= (int) $v['id'] ?>" data-customer="<?= (int) $v['customer_id'] ?>"
                      <?= (int) $job['vehicle_id'] === (int) $v['id'] ? 'selected' : '' ?>>
                <?= e($v['plate_number'] . ' — ' . $v['make'] . ' ' . $v['model'] . ' (' . $v['customer_name'] . ')') ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Choose the customer first to filter their vehicles.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="service_category">Service Category <span class="text-danger">*</span></label>
          <select class="form-select" id="service_category" name="service_category" required>
            <option value="">— Select category —</option>
            <?php foreach (SERVICE_CATEGORIES as $cat): ?>
              <option value="<?= e($cat) ?>" <?= $job['service_category'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="technician_id">Assigned Technician</label>
          <select class="form-select" id="technician_id" name="technician_id">
            <option value="0">— Unassigned —</option>
            <?php foreach ($technicians as $t): ?>
              <option value="<?= (int) $t['id'] ?>" <?= (int) ($job['technician_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="priority">Priority <span class="text-danger">*</span></label>
          <select class="form-select" id="priority" name="priority" required>
            <?php foreach (JOB_PRIORITIES as $p): ?>
              <option value="<?= e($p) ?>" <?= $job['priority'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="status">Status <span class="text-danger">*</span></label>
          <select class="form-select" id="status" name="status" required>
            <?php foreach ($statusOptions as $s): ?>
              <option value="<?= e($s) ?>" <?= $job['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="estimated_completion">Estimated Completion</label>
          <input class="form-control" type="date" id="estimated_completion" name="estimated_completion" value="<?= e((string) ($job['estimated_completion'] ?? '')) ?>">
        </div>
        <div class="col-12">
          <label class="form-label" for="problem_description">Problem Description <span class="text-danger">*</span></label>
          <textarea class="form-control" id="problem_description" name="problem_description" rows="4" required placeholder="Describe the reported problem or requested service"><?= e($job['problem_description']) ?></textarea>
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button class="btn btn-bb" type="submit" <?= (!$customersList || !$vehiclesList) ? 'disabled' : '' ?>>
          <i class="bi bi-check-lg"></i> <?= $isEdit ? 'Save Changes' : 'Create Job Card' ?>
        </button>
        <a class="btn btn-outline-secondary" href="<?= base_url('job_cards/index.php') ?>">Cancel</a>
      </div>
    </form>
  </div>
</div>

<script>
// Filter the vehicle dropdown by the selected customer.
document.addEventListener('DOMContentLoaded', function () {
  var customerSelect = document.getElementById('customer_id');
  var vehicleSelect = document.getElementById('vehicle_id');
  if (!customerSelect || !vehicleSelect) { return; }

  function filterVehicles() {
    var cid = customerSelect.value;
    Array.prototype.forEach.call(vehicleSelect.options, function (opt) {
      if (!opt.value) { return; }
      var match = !cid || opt.getAttribute('data-customer') === cid;
      opt.hidden = !match;
      if (!match && opt.selected) { vehicleSelect.value = ''; }
    });
  }

  customerSelect.addEventListener('change', filterVehicles);
  filterVehicles();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
