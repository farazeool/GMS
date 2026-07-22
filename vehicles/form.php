<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/sync_helpers.php';

require_role('admin');

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;

$vehicle = [
    'customer_id'  => (int) ($_GET['customer_id'] ?? 0),
    'plate_number' => '',
    'make'         => '',
    'model'        => '',
    'year'         => '',
    'color'        => '',
    'vin'          => '',
];

if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM vehicles WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        set_flash('danger', 'Vehicle not found.');
        header('Location: ' . base_url('vehicles/index.php'));
        exit;
    }
    $vehicle = $existing;
}

$customersList = db()->query('SELECT id, name, phone FROM customers ORDER BY name')->fetchAll();

$errors = [];
$currentYear = (int) date('Y');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $vehicle['customer_id']  = (int) ($_POST['customer_id'] ?? 0);
    $vehicle['plate_number'] = trim($_POST['plate_number'] ?? '');
    $vehicle['make']         = trim($_POST['make'] ?? '');
    $vehicle['model']        = trim($_POST['model'] ?? '');
    $vehicle['year']         = trim($_POST['year'] ?? '');
    $vehicle['color']        = trim($_POST['color'] ?? '');
    $vehicle['vin']          = strtoupper(trim($_POST['vin'] ?? ''));

    if ($vehicle['customer_id'] <= 0) {
        $errors[] = 'Please select the vehicle owner (customer).';
    } else {
        $stmt = db()->prepare('SELECT id FROM customers WHERE id = ?');
        $stmt->execute([$vehicle['customer_id']]);
        if (!$stmt->fetch()) {
            $errors[] = 'Selected customer does not exist.';
        }
    }

    if ($vehicle['plate_number'] === '') {
        $errors[] = 'Plate number is required.';
    }
    if ($vehicle['make'] === '') {
        $errors[] = 'Make is required.';
    }
    if ($vehicle['model'] === '') {
        $errors[] = 'Model is required.';
    }

    if ($vehicle['year'] !== '') {
        if (!ctype_digit((string) $vehicle['year']) || (int) $vehicle['year'] < 1950 || (int) $vehicle['year'] > $currentYear + 1) {
            $errors[] = 'Year must be a valid year between 1950 and ' . ($currentYear + 1) . '.';
        }
    }

    if ($vehicle['plate_number'] !== '') {
        $stmt = db()->prepare('SELECT id FROM vehicles WHERE plate_number = ? AND id <> ?');
        $stmt->execute([$vehicle['plate_number'], $id]);
        if ($stmt->fetch()) {
            $errors[] = 'A vehicle with this plate number already exists.';
        }
    }

    if ($vehicle['vin'] !== '') {
        $stmt = db()->prepare('SELECT id FROM vehicles WHERE vin = ? AND id <> ?');
        $stmt->execute([$vehicle['vin'], $id]);
        if ($stmt->fetch()) {
            $errors[] = 'A vehicle with this VIN/chassis number already exists.';
        }
    }

    if (!$errors) {
        $params = [
            $vehicle['customer_id'],
            $vehicle['plate_number'],
            $vehicle['make'],
            $vehicle['model'],
            $vehicle['year'] !== '' ? (int) $vehicle['year'] : null,
            $vehicle['color'] !== '' ? $vehicle['color'] : null,
            $vehicle['vin'] !== '' ? $vehicle['vin'] : null,
        ];
        if ($isEdit) {
            $params[] = $id;
            $stmt = db()->prepare('UPDATE vehicles SET customer_id = ?, plate_number = ?, make = ?, model = ?, year = ?, color = ?, vin = ? WHERE id = ?');
            $stmt->execute($params);
            set_flash('success', 'Vehicle updated successfully.');
            track_change('vehicles', 'update', $id);
        } else {
            $stmt = db()->prepare('INSERT INTO vehicles (customer_id, plate_number, make, model, year, color, vin) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute($params);
            $id = (int) db()->lastInsertId();
            set_flash('success', 'Vehicle registered successfully.');
            track_change('vehicles', 'create', $id);
        }
        header('Location: ' . base_url('vehicles/view.php?id=' . $id));
        exit;
    }
}

$page_title = $isEdit ? 'Edit Vehicle' : 'Register Vehicle';
$active = 'vehicles';

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h1 class="bb-page-title"><?= $isEdit ? 'Edit Vehicle' : 'Register Vehicle' ?></h1>
  <a class="btn btn-outline-secondary" href="<?= base_url('vehicles/index.php') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Registry</a>
</div>

<?php if (!$customersList): ?>
  <div class="alert alert-warning" role="alert">
    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> You need at least one customer before registering a vehicle.
    <a class="alert-link" href="<?= base_url('customers/form.php') ?>">Add a customer first</a>.
  </div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger" role="alert">
    <strong>Please fix the following:</strong>
    <ul class="mb-0">
      <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card bb-form-narrow">
  <div class="card-body p-4">
    <form method="post" action="<?= base_url('vehicles/form.php' . ($isEdit ? '?id=' . $id : '')) ?>">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="customer_id">Owner (Customer) <span class="bb-required" aria-hidden="true">*</span></label>
          <select class="form-select" id="customer_id" name="customer_id" required <?= !$customersList ? 'disabled' : '' ?>>
            <option value="">— Select customer —</option>
            <?php foreach ($customersList as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= (int) $vehicle['customer_id'] === (int) $c['id'] ? 'selected' : '' ?>>
                <?= e($c['name'] . ' (' . $c['phone'] . ')') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="plate_number">Plate Number <span class="bb-required" aria-hidden="true">*</span></label>
          <input class="form-control" type="text" id="plate_number" name="plate_number" value="<?= e($vehicle['plate_number']) ?>" maxlength="20" placeholder="e.g. 8/24173" required>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="make">Make <span class="bb-required" aria-hidden="true">*</span></label>
          <input class="form-control" type="text" id="make" name="make" value="<?= e($vehicle['make']) ?>" maxlength="60" placeholder="e.g. Toyota" required>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="model">Model <span class="bb-required" aria-hidden="true">*</span></label>
          <input class="form-control" type="text" id="model" name="model" value="<?= e($vehicle['model']) ?>" maxlength="60" placeholder="e.g. Land Cruiser" required>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="year">Year</label>
          <input class="form-control" type="number" id="year" name="year" value="<?= e((string) ($vehicle['year'] ?? '')) ?>" min="1950" max="<?= $currentYear + 1 ?>" placeholder="e.g. 2022">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="color">Color</label>
          <input class="form-control" type="text" id="color" name="color" value="<?= e($vehicle['color'] ?? '') ?>" maxlength="40" placeholder="e.g. White">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="vin">VIN / Chassis Number</label>
          <input class="form-control" type="text" id="vin" name="vin" value="<?= e($vehicle['vin'] ?? '') ?>" maxlength="30">
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button class="btn btn-bb" type="submit" <?= !$customersList ? 'disabled' : '' ?>>
          <i class="bi bi-check-lg" aria-hidden="true"></i> <?= $isEdit ? 'Save Changes' : 'Register Vehicle' ?>
        </button>
        <a class="btn btn-outline-secondary" href="<?= base_url('vehicles/index.php') ?>">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
