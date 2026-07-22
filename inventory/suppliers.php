<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/inventory.php';
require_role('admin');
$model = new InventorySupplier();
$suppliers = $model->all();
$page_title = 'Suppliers';
$active = 'inventory';
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="bb-page-title">Suppliers</h1>
  <a class="btn btn-bb" href="<?= base_url('inventory/supplier_form.php') ?>"><i class="bi bi-plus-lg"></i> Add Supplier</a>
</div>
<div class="card"><div class="card-body p-0"><div class="table-responsive">
<table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Name</th><th>Contact</th><th>Phone</th><th>Email</th><th>Actions</th></tr></thead><tbody>
<?php if (!$suppliers): ?><tr><td colspan="5" class="bb-empty">No suppliers yet.</td></tr><?php endif; ?>
<?php foreach ($suppliers as $s): ?>
<tr><td class="fw-semibold"><?= e($s['name']) ?></td><td><?= e($s['contact_person'] ?? '—') ?></td><td><?= e($s['phone'] ?? '—') ?></td><td><?= e($s['email'] ?? '—') ?></td>
<td class="bb-actions"><a class="btn btn-sm btn-outline-primary" href="<?= base_url('inventory/supplier_form.php?id=' . $s['id']) ?>"><i class="bi bi-pencil"></i></a>
<form method="post" action="<?= base_url('inventory/supplier_delete.php') ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-outline-danger" type="submit" data-confirm="Delete <?= e($s['name']) ?>?"><i class="bi bi-trash"></i></button></form></td></tr>
<?php endforeach; ?></tbody></table></div></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>