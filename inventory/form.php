<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/inventory.php';
require_role('admin');

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;
$itemModel = new InventoryItem();
$catModel = new InventoryCategory();
$supplierModel = new InventorySupplier();
$item = $isEdit ? ($itemModel->find($id) ?? []) : [];
$categories = $catModel->all();
$suppliers = $supplierModel->all();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $data = [
        'sku' => trim($_POST['sku'] ?? ''),
        'barcode' => trim($_POST['barcode'] ?? ''),
        'name' => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
        'supplier_id' => !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null,
        'purchase_price' => (float) ($_POST['purchase_price'] ?? 0),
        'selling_price' => (float) ($_POST['selling_price'] ?? 0),
        'quantity' => (float) ($_POST['quantity'] ?? 0),
        'minimum_stock' => (float) ($_POST['minimum_stock'] ?? 0),
        'reorder_level' => (float) ($_POST['reorder_level'] ?? 0),
        'warehouse_location' => trim($_POST['warehouse_location'] ?? ''),
        'unit' => trim($_POST['unit'] ?? 'piece'),
    ];
    if ($data['sku'] === '') { $errors[] = 'SKU is required.'; }
    if ($data['name'] === '') { $errors[] = 'Item name is required.'; }
    if (!$errors) {
        if ($isEdit) {
            $itemModel->update($id, $data);
            set_flash('success', 'Item updated.');
        } else {
            if ($itemModel->findBySku($data['sku'])) {
                $errors[] = 'SKU already exists.';
            } else {
                $id = $itemModel->create($data);
                set_flash('success', 'Item created.');
            }
        }
        if (!$errors) { header('Location: ' . base_url('inventory/view.php?id=' . $id)); exit; }
    }
}

$page_title = $isEdit ? 'Edit Item' : 'Add Inventory Item';
$active = 'inventory';
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h1 class="bb-page-title"><?= $isEdit ? 'Edit Item' : 'New Inventory Item' ?></h1>
  <a class="btn btn-outline-secondary" href="<?= base_url('inventory/index.php') ?>"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<?php if ($errors): ?>
  <div class="alert alert-danger"><strong>Please fix:</strong><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<div class="card"><div class="card-body p-4">
<form method="post">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-4"><label class="form-label">SKU *</label><input class="form-control" name="sku" value="<?= e($item['sku'] ?? '') ?>" required></div>
    <div class="col-md-4"><label class="form-label">Barcode</label><input class="form-control" name="barcode" value="<?= e($item['barcode'] ?? '') ?>"></div>
    <div class="col-md-4"><label class="form-label">Unit</label><select class="form-select" name="unit"><option value="piece" <?= ($item['unit']??'piece')==='piece'?'selected':'' ?>>Piece</option><option value="liter" <?= ($item['unit']??'')==='liter'?'selected':'' ?>>Liter</option><option value="kg" <?= ($item['unit']??'')==='kg'?'selected':'' ?>>Kg</option><option value="box" <?= ($item['unit']??'')==='box'?'selected':'' ?>>Box</option><option value="meter" <?= ($item['unit']??'')==='meter'?'selected':'' ?>>Meter</option></select></div>
    <div class="col-12"><label class="form-label">Item Name *</label><input class="form-control" name="name" value="<?= e($item['name'] ?? '') ?>" required></div>
    <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"><?= e($item['description'] ?? '') ?></textarea></div>
    <div class="col-md-4"><label class="form-label">Category</label><select class="form-select" name="category_id"><option value="">—</option><?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>" <?= (int)($item['category_id']??0)===$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label">Supplier</label><select class="form-select" name="supplier_id"><option value="">—</option><?php foreach($suppliers as $s): ?><option value="<?= $s['id'] ?>" <?= (int)($item['supplier_id']??0)===$s['id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><label class="form-label">Warehouse Location</label><input class="form-control" name="warehouse_location" value="<?= e($item['warehouse_location'] ?? '') ?>"></div>
    <div class="col-md-3"><label class="form-label">Purchase Price</label><div class="input-group"><span class="input-group-text">KD</span><input class="form-control" type="number" step="0.001" name="purchase_price" value="<?= e((string)($item['purchase_price']??'0')) ?>"></div></div>
    <div class="col-md-3"><label class="form-label">Selling Price</label><div class="input-group"><span class="input-group-text">KD</span><input class="form-control" type="number" step="0.001" name="selling_price" value="<?= e((string)($item['selling_price']??'0')) ?>"></div></div>
    <div class="col-md-2"><label class="form-label">Quantity</label><input class="form-control" type="number" step="0.01" name="quantity" value="<?= e((string)($item['quantity']??'0')) ?>"></div>
    <div class="col-md-2"><label class="form-label">Min Stock</label><input class="form-control" type="number" step="1" name="minimum_stock" value="<?= e((string)($item['minimum_stock']??'0')) ?>"></div>
    <div class="col-md-2"><label class="form-label">Reorder Level</label><input class="form-control" type="number" step="1" name="reorder_level" value="<?= e((string)($item['reorder_level']??'0')) ?>"></div>
  </div>
  <div class="mt-4"><button class="btn btn-bb"><i class="bi bi-check-lg"></i> <?= $isEdit ? 'Save' : 'Create' ?></button></div>
</form>
</div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>