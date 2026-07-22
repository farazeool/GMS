<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/inventory.php';
require_role('admin');

$catModel = new InventoryCategory();
$suppliers = new InventorySupplier();

$categories = $catModel->all();
$page_title = 'Categories';
$active = 'inventory';
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="bb-page-title">Categories</h1>
  <a class="btn btn-bb" href="<?= base_url('inventory/category_form.php') ?>"><i class="bi bi-plus-lg"></i> Add</a>
</div>
<div class="card"><div class="card-body p-0"><div class="table-responsive">
<table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Name</th><th>Items</th><th>Actions</th></tr></thead><tbody>
<?php if (!$categories): ?><tr><td colspan="3" class="bb-empty">No categories yet.</td></tr><?php endif; ?>
<?php foreach ($categories as $c):
$count = db()->prepare('SELECT COUNT(*) FROM inventory_items WHERE category_id=?')->execute([$c['id']])->fetchColumn();
?>
<tr><td class="fw-semibold"><?= e($c['name']) ?></td><td><span class="badge text-bg-secondary"><?= (int)$count ?></span></td>
<td class="bb-actions"><a class="btn btn-sm btn-outline-primary" href="<?= base_url('inventory/category_form.php?id=' . $c['id']) ?>"><i class="bi bi-pencil"></i></a>
<form method="post" action="<?= base_url('inventory/category_delete.php') ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit" data-confirm="Delete <?= e($c['name']) ?>?"><i class="bi bi-trash"></i></button></form></td></tr>
<?php endforeach; ?>
</tbody></table></div></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>