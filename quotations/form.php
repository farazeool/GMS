<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/commercial.php';
require_role('admin');

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;

$customers = db()->query('SELECT id, name FROM customers ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$vehicles = db()->query('SELECT v.id, v.plate_number, v.make, v.model, v.customer_id, c.name AS cname FROM vehicles v JOIN customers c ON c.id=v.customer_id ORDER BY c.name, v.plate_number')->fetchAll(PDO::FETCH_ASSOC);

$q = ['customer_id' => 0, 'vehicle_id' => 0, 'status' => 'draft', 'subtotal' => 0, 'tax_rate' => 0, 'tax_amount' => 0, 'discount' => 0, 'total' => 0, 'notes' => ''];

if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM quotations WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) { set_flash('danger', 'Not found.'); header('Location: ' . base_url('quotations/index.php')); exit; }
    $q = $existing;
    $lines = db()->prepare('SELECT * FROM quotation_lines WHERE quotation_id=? ORDER BY sort_order')->execute([$id])->fetchAll(PDO::FETCH_ASSOC);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $data = [
        'customer_id' => (int)($_POST['customer_id'] ?? 0),
        'vehicle_id' => (int)($_POST['vehicle_id'] ?? 0),
        'tax_rate' => (float)($_POST['tax_rate'] ?? 0),
        'discount' => (float)($_POST['discount'] ?? 0),
        'notes' => trim($_POST['notes'] ?? ''),
    ];
    if ($data['customer_id'] <= 0) $errors[] = 'Customer required.';
    if ($data['vehicle_id'] <= 0) $errors[] = 'Vehicle required.';

    if (!$errors) {
        if ($isEdit) {
            db()->prepare('UPDATE quotations SET customer_id=?, vehicle_id=?, tax_rate=?, discount=?, notes=? WHERE id=?')
                ->execute([$data['customer_id'], $data['vehicle_id'], $data['tax_rate'], $data['discount'], $data['notes'], $id]);
        } else {
            $num = generate_quotation_number();
            db()->prepare('INSERT INTO quotations (uuid, quotation_number, customer_id, vehicle_id, tax_rate, discount, notes, status, created_by) VALUES (?,?,?,?,?,?,?,"draft",?)')
                ->execute([uuid_generate(), $num, $data['customer_id'], $data['vehicle_id'], $data['tax_rate'], $data['discount'], $data['notes'], (int)($_SESSION['user_id']??0)]);
            $id = (int)db()->lastInsertId();
        }
        header('Location: ' . base_url('quotations/form.php?id=' . $id)); exit;
    }
}

$page_title = $isEdit ? 'Edit Quotation' : 'New Quotation';
$active = 'quotations';
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="bb-page-title"><?= $isEdit ? 'Edit' : 'New' ?> Quotation</h1>
  <a class="btn btn-outline-secondary" href="<?= base_url('quotations/index.php') ?>"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="card"><div class="card-body p-4">
<form method="post"><?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label">Customer *</label>
      <select class="form-select" name="customer_id" required><option value="">—</option>
      <?php foreach($customers as $c): ?><option value="<?= $c['id'] ?>" <?= (int)($data['customer_id']??0)===$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select></div>
    <div class="col-md-4"><label class="form-label">Vehicle *</label>
      <select class="form-select" name="vehicle_id" required><option value="">—</option>
      <?php foreach($vehicles as $v): ?><option value="<?= $v['id'] ?>" data-customer="<?= $v['customer_id'] ?>" <?= (int)($data['vehicle_id']??0)===$v['id']?'selected':'' ?>><?= e($v['plate_number'] . ' — ' . $v['make'] . ' ' . $v['model'] . ' (' . $v['cname'] . ')') ?></option><?php endforeach; ?>
    </select></div>
    <div class="col-md-4"><label class="form-label">Tax Rate (%)</label>
      <input class="form-control" type="number" step="0.01" name="tax_rate" value="<?= e((string)($data['tax_rate']??0)) ?>"></div>
    <div class="col-md-4"><label class="form-label">Discount</label>
      <input class="form-control" type="number" step="0.001" name="discount" value="<?= e((string)($data['discount']??0)) ?>"></div>
    <div class="col-12"><label class="form-label">Notes</label>
      <textarea class="form-control" name="notes" rows="2"><?= e($data['notes'] ?? '') ?></textarea></div>
  </div>
  <div class="mt-4"><button class="btn btn-bb" type="submit"><i class="bi bi-check-lg"></i> Save</button>
  <a class="btn btn-outline-secondary" href="<?= base_url('quotations/index.php') ?>">Cancel</a></div>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>