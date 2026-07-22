<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_role('admin');

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$c = ['name' => '', 'description' => ''];

if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM inventory_categories WHERE id = ?');
    $stmt->execute([$id]);
    $c = $stmt->fetch();
    if (!$c) { set_flash('danger', 'Not found.'); header('Location: ' . base_url('inventory/categories.php')); exit; }
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $c['name'] = trim($_POST['name'] ?? '');
    $c['description'] = trim($_POST['description'] ?? '');
    if ($c['name'] === '') $errors[] = 'Name required.';
    if (!$errors) {
        if ($isEdit) {
            db()->prepare('UPDATE inventory_categories SET name=?, description=? WHERE id=?')->execute([$c['name'], $c['description'], $id]);
        } else {
            db()->prepare('INSERT INTO inventory_categories (uuid, name, description) VALUES (?, ?, ?)')
                ->execute([uuid_generate(), $c['name'], $c['description']]);
        }
        set_flash('success', 'Category saved.');
        header('Location: ' . base_url('inventory/categories.php')); exit;
    }
}

$page_title = $isEdit ? 'Edit Category' : 'Add Category';
$active = 'inventory';
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between mb-4"><h1 class="bb-page-title"><?= $isEdit ? 'Edit' : 'Add' ?> Category</h1>
<a class="btn btn-outline-secondary" href="<?= base_url('inventory/categories.php') ?>"><i class="bi bi-arrow-left"></i> Back</a></div>
<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="card bb-form-narrow"><div class="card-body p-4">
<form method="post"><?= csrf_field() ?>
<div class="mb-3"><label class="form-label">Name *</label><input class="form-control" name="name" value="<?= e($c['name']) ?>" required></div>
<div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"><?= e($c['description']) ?></textarea></div>
<div class="mt-4"><button class="btn btn-bb"><i class="bi bi-check-lg"></i> Save</button> <a class="btn btn-outline-secondary" href="<?= base_url('inventory/categories.php') ?>">Cancel</a></div>
</form>
<?php include __DIR__ . '/../includes/footer.php'; ?>