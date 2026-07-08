<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/sync_helpers.php';

require_role('admin');

$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    'SELECT mr.*, v.plate_number, v.make, v.model, jc.job_number
     FROM maintenance_records mr
     JOIN vehicles v ON v.id = mr.vehicle_id
     LEFT JOIN job_cards jc ON jc.id = mr.job_card_id
     WHERE mr.id = ?'
);
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    set_flash('danger', 'Maintenance record not found.');
    header('Location: ' . base_url('maintenance/index.php'));
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $record['description'] = trim($_POST['description'] ?? '');
    $record['service_date'] = trim($_POST['service_date'] ?? '');
    $costInput = trim($_POST['cost'] ?? '');
    $odoInput = trim($_POST['odometer_km'] ?? '');

    if ($record['description'] === '') {
        $errors[] = 'Description is required.';
    }

    $d = DateTime::createFromFormat('Y-m-d', $record['service_date']);
    if (!$d || $d->format('Y-m-d') !== $record['service_date']) {
        $errors[] = 'Service date is not valid.';
    }

    if ($costInput !== '' && (!is_numeric($costInput) || (float) $costInput < 0)) {
        $errors[] = 'Cost must be a positive number (KWD).';
    }
    if ($odoInput !== '' && (!ctype_digit($odoInput))) {
        $errors[] = 'Odometer must be a whole positive number (km).';
    }

    $record['cost'] = $costInput !== '' ? $costInput : null;
    $record['odometer_km'] = $odoInput !== '' ? $odoInput : null;

    if (!$errors) {
        $stmt = db()->prepare('UPDATE maintenance_records SET description = ?, service_date = ?, cost = ?, odometer_km = ? WHERE id = ?');
        $stmt->execute([
            $record['description'],
            $record['service_date'],
            $record['cost'] !== null ? round((float) $record['cost'], 3) : null,
            $record['odometer_km'] !== null ? (int) $record['odometer_km'] : null,
            $id,
        ]);
        sync_mark_record_dirty('maintenance_records', $id);
        set_flash('success', 'Maintenance record updated successfully.');
        header('Location: ' . base_url('maintenance/view.php?id=' . $id));
        exit;
    }
}

$page_title = 'Edit Maintenance Record';
$active = 'maintenance';

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Edit Maintenance Record #<?= (int) $record['id'] ?></h4>
  <a class="btn btn-outline-secondary" href="<?= base_url('maintenance/view.php?id=' . (int) $record['id']) ?>"><i class="bi bi-arrow-left"></i> Back to Record</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <strong>Please fix the following:</strong>
    <ul class="mb-0">
      <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card" style="max-width: 720px;">
  <div class="card-body p-4">
    <div class="alert alert-light border small">
      <i class="bi bi-link-45deg"></i>
      Vehicle: <strong><?= e($record['plate_number'] . ' — ' . $record['make'] . ' ' . $record['model']) ?></strong>
      · Job card: <strong><?= e($record['job_number'] ?? 'No longer exists') ?></strong>
      <span class="d-block text-muted">Vehicle and job card linkage cannot be changed. Records are created automatically from completed job cards.</span>
    </div>
    <form method="post" action="<?= base_url('maintenance/form.php?id=' . (int) $record['id']) ?>">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label" for="description">Description <span class="text-danger">*</span></label>
          <textarea class="form-control" id="description" name="description" rows="3" maxlength="255" required><?= e($record['description']) ?></textarea>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="service_date">Service Date <span class="text-danger">*</span></label>
          <input class="form-control" type="date" id="service_date" name="service_date" value="<?= e((string) $record['service_date']) ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="cost">Cost (KWD)</label>
          <input class="form-control" type="number" step="0.001" min="0" id="cost" name="cost" value="<?= e($record['cost'] !== null ? (string) $record['cost'] : '') ?>" placeholder="e.g. 45.500">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="odometer_km">Odometer (km)</label>
          <input class="form-control" type="number" min="0" id="odometer_km" name="odometer_km" value="<?= e($record['odometer_km'] !== null ? (string) $record['odometer_km'] : '') ?>" placeholder="e.g. 40120">
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button class="btn btn-bb" type="submit"><i class="bi bi-check-lg"></i> Save Changes</button>
        <a class="btn btn-outline-secondary" href="<?= base_url('maintenance/view.php?id=' . (int) $record['id']) ?>">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
