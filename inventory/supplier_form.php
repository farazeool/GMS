<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_role('admin');

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$s = ['name' => '', 'contact_person' => '', 'phone' => '', 'email' => '', 'address' => ''];

if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM inventory_suppliers WHERE id = ?');
    $stmt->execute([$id]);
    $s = $stmt->fetch();
    if (!$s) { set_flash('danger', 'Supplier not found.'); header('Location: ' . base_url('inventory/suppliers.php')); exit; }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $s['name'] = trim($_POST['name'] ?? '');
    $s['contact_person'] = trim($_POST['contact_person'] ?? '');
    $s['phone'] = trim($_POST['phone'] ?? '');
    $s['email'] = trim($_POST['email'] ?? '');
    $s['address'] = trim($_POST['address'] ?? '');
    if ($s['name'] === '') $errors[] = 'Name is required.';
    if ($s['email'] !== '' && !filter_var($s['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalid.';
    if (!$errors) {
        if ($isEdit) {
            db()->prepare('UPDATE inventory_suppliers SET name=?, contact_person=?, phone=?, email=?, address=? WHERE id=?')
                ->execute([$s['name'], $s['contact_person'], $s['phone'], $s['email'], $s['address'], $id]);
        } else {
            $id = db()->query('SELECT COALESCE(MAX(id),0)+1 FROM inventory_suppliers')->fetchColumn();
            db()->prepare('INSERT INTO inventory_suppliers (id, uuid, name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$id, uuid_generate(), $s['name'], $s['contact_person'], $s['phone'], $s['email'], $s['address']]);
        }
        set_flash('success', 'Supplier saved.');
        header('Location: ' . base_url('inventory/suppliers.php')); exit;
    }
}

$page_title = $isEdit ? 'Edit Supplier' : 'Add Supplier';
$active = 'inventory';
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="bb-page-title"><?= $isEdit ? 'Edit' : 'Add' ?> Supplier</h1>
  <a class="btn btn-outline-secondary" href="<?= base_url('inventory/suppliers.php') ?>"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="card bb-form-narrow"><div class="card-body p-4">
<form method="post"><?= csrf_field() ?>
<div class="row g-3">
  <div class="col-md-6"><label class="form-label">Name *</label><input class="form-control" name="name" value="<?= e($s['name']) ?>" required></div>
  <div class="col-md-6"><label class="form-label">Contact Person</label><input class="form-control" name="contact_person" value="<?= e($s['contact_person']) ?>"></div>
  <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= e($s['phone']) ?>"></div>
  <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e($s['email']) ?>"></div>
  <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2"><?= e($s['address']) ?></textarea></div>
</div>
<div class="mt-4"><button class="btn btn-bb"><i class="bi bi-check-lg"></i> Save</button>
<a class="btn btn-outline-secondary" href="<?= base_url('inventory/suppliers.php') ?>">Cancel</a></div>
</form></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>