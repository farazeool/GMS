<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_role('admin');

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;

$vehicles = db()->query('SELECT v.id, v.plate_number, v.make, v.model, c.name AS cname FROM vehicles v JOIN customers c ON c.id=v.customer_id WHERE v.deleted_at IS NULL ORDER BY c.name, v.plate_number')->fetchAll(PDO::FETCH_ASSOC);

$r = ['vehicle_id' => 0, 'reminder_type' => 'mileage', 'interval_value' => 5000, 'description' => '', 'next_due_date' => '', 'next_due_odometer' => ''];

if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM service_reminders WHERE id = ?');
    $stmt->execute([$id]);
    $r = $stmt->fetch();
    if (!$r) { set_flash('danger', 'Not found.'); header('Location: ' . base_url('maintenance/reminders.php')); exit; }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $r['vehicle_id'] = (int)($_POST['vehicle_id'] ?? 0);
    $r['reminder_type'] = $_POST['reminder_type'] ?? 'mileage';
    $r['interval_value'] = (int)($_POST['interval_value'] ?? 0);
    $r['description'] = trim($_POST['description'] ?? '');
    $r['next_due_date'] = trim($_POST['next_due_date'] ?? '');
    $r['next_due_odometer'] = trim($_POST['next_due_odometer'] ?? '');

    if ($r['vehicle_id'] <= 0) $errors[] = 'Vehicle required.';
    if ($r['interval_value'] <= 0) $errors[] = 'Interval value must be positive.';

    if (!$errors) {
        if ($isEdit) {
            db()->prepare('UPDATE service_reminders SET vehicle_id=?, reminder_type=?, interval_value=?, description=?, next_due_date=?, next_due_odometer=? WHERE id=?')
                ->execute([$r['vehicle_id'], $r['reminder_type'], $r['interval_value'], $r['description'], $r['next_due_date']?:null, $r['next_due_odometer']?:null, $id]);
        } else {
            db()->prepare('INSERT INTO service_reminders (uuid, vehicle_id, reminder_type, interval_value, description, next_due_date, next_due_odometer) VALUES (?,?,?,?,?,?,?)')
                ->execute([uuid_generate(), $r['vehicle_id'], $r['reminder_type'], $r['interval_value'], $r['description'], $r['next_due_date']?:null, $r['next_due_odometer']?:null]);
        }
        set_flash('success', 'Reminder saved.');
        header('Location: ' . base_url('maintenance/reminders.php')); exit;
    }
}

$page_title = $isEdit ? 'Edit Reminder' : 'New Reminder';
$active = 'maintenance';
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between mb-4"><h1 class="bb-page-title"><?= $isEdit ? 'Edit' : 'Add' ?> Reminder</h1>
<a class="btn btn-outline-secondary" href="<?= base_url('maintenance/reminders.php') ?>"><i class="bi bi-arrow-left"></i> Back</a></div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="card bb-form-narrow"><div class="card-body p-4">
<form method="post"><?= csrf_field() ?>
<div class="row g-3">
  <div class="col-md-6"><label class="form-label">Vehicle *</label><select class="form-select" name="vehicle_id" required><?php foreach($vehicles as $v): ?><option value="<?= $v['id'] ?>" <?= (int)($r['vehicle_id']??0)===$v['id']?'selected':'' ?>><?= e($v['plate_number'] . ' — ' . $v['make'] . ' ' . $v['model'] . ' (' . $v['cname'] . ')') ?></option><?php endforeach; ?></select></div>
  <div class="col-md-6"><label class="form-label">Type *</label><select class="form-select" name="reminder_type"><option value="mileage" <?= ($r['reminder_type']??'')==='mileage'?'selected':'' ?>>Mileage</option><option value="months" <?= ($r['reminder_type']??'')==='months'?'selected':'' ?>>Months</option><option value="custom" <?= ($r['reminder_type']??'')==='custom'?'selected':'' ?>>Custom</option></select></div>
  <div class="col-md-6"><label class="form-label">Interval Value *</label><input class="form-control" type="number" name="interval_value" value="<?= e((string)($r['interval_value']??'')) ?>" required></div>
  <div class="col-md-6"><label class="form-label">Description *</label><input class="form-control" name="description" value="<?= e($r['description']) ?>" maxlength="255" required></div>
  <div class="col-md-6"><label class="form-label">Next Due Date</label><input class="form-control" type="date" name="next_due_date" value="<?= e($r['next_due_date'] ?? '') ?>"></div>
  <div class="col-md-6"><label class="form-label">Next Due Odometer (km)</label><input class="form-control" type="number" name="next_due_odometer" value="<?= e($r['next_due_odometer'] ?? '') ?>"></div>
</div>
<div class="mt-4"><button class="btn btn-bb"><i class="bi bi-check-lg"></i> Save</button> <a class="btn btn-outline-secondary" href="<?= base_url('maintenance/reminders.php') ?>">Cancel</a></div>
</form></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>