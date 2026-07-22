<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/inventory.php';
require_role('admin');

$itemModel = new InventoryItem();
$item = $itemModel->find((int)($_GET['id'] ?? 0));
if (!$item) { set_flash('danger', 'Item not found.'); header('Location: ' . base_url('inventory/index.php')); exit; }

$movements = $itemModel->getMovementHistory((int)$item['id']);
$low = (float)$item['quantity'] <= (float)$item['minimum_stock'] && (float)$item['minimum_stock'] > 0;
$oos = (float)$item['quantity'] <= 0;

$page_title = $item['name'];
$active = 'inventory';
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h1 class="bb-page-title"><?= e($item['name']) ?></h1>
  <div><a class="btn btn-bb" href="<?= base_url('inventory/form.php?id=' . $item['id']) ?>"><i class="bi bi-pencil"></i> Edit</a>
  <a class="btn btn-outline-secondary" href="<?= base_url('inventory/index.php') ?>"><i class="bi bi-arrow-left"></i> Back</a></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-8">
    <div class="card"><div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">SKU</dt><dd class="col-sm-9"><code><?= e($item['sku']) ?></code></dd>
        <dt class="col-sm-3">Barcode</dt><dd class="col-sm-9"><?= e($item['barcode'] ?? '—') ?></dd>
        <dt class="col-sm-3">Unit</dt><dd class="col-sm-9"><?= e($item['unit']) ?></dd>
        <dt class="col-sm-3">Category</dt><dd class="col-sm-9"><?= e($item['category_name'] ?? '—') ?></dd>
        <dt class="col-sm-3">Supplier</dt><dd class="col-sm-9"><?= e($item['supplier_name'] ?? '—') ?></dd>
        <dt class="col-sm-3">Location</dt><dd class="col-sm-9"><?= e($item['warehouse_location'] ?? '—') ?></dd>
        <dt class="col-sm-3">Description</dt><dd class="col-sm-9"><?= e($item['description'] ?? '—') ?></dd>
      </dl>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card <?= $oos ? 'border-danger' : ($low ? 'border-warning' : '') ?>"><div class="card-body text-center">
      <h3 class="display-4 fw-bold <?= $oos ? 'text-danger' : ($low ? 'text-warning' : 'text-success') ?>"><?= (float)$item['quantity'] ?></h3>
      <p class="text-muted"><?= e($item['unit']) ?> in stock</p>
      <span class="badge fs-6 <?= $oos ? 'bg-danger' : ($low ? 'bg-warning text-dark' : 'bg-success') ?>"><?= $oos ? 'Out of Stock' : ($low ? 'Low Stock' : 'In Stock') ?></span>
      <hr>
      <small class="text-muted">Min: <?= (float)$item['minimum_stock'] ?> · Reorder: <?= (float)$item['reorder_level'] ?></small>
    </div></div>
    <div class="card mt-3"><div class="card-body">
      <div class="d-flex gap-2 small">
        <div><span class="text-muted">Purchase:</span><br><strong><?= format_kwd($item['purchase_price']) ?></strong></div>
        <div class="text-end ms-auto"><span class="text-muted">Selling:</span><br><strong><?= format_kwd($item['selling_price']) ?></strong></div>
      </div>
    </div></div>
  </div>
</div>

<div class="card"><div class="card-header"><h2 class="h6 mb-0">Movement History</h2></div>
<div class="card-body p-0"><div class="table-responsive">
  <table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Date</th><th>Type</th><th>Change</th><th>Before</th><th>After</th><th>Reference</th><th>Notes</th></tr></thead><tbody>
  <?php if (empty($movements)): ?><tr><td colspan="7" class="bb-empty">No movements recorded.</td></tr><?php endif; ?>
  <?php foreach ($movements as $m): ?>
    <tr>
      <td class="small"><?= date('Y-m-d H:i', strtotime($m['created_at'])) ?></td>
      <td><span class="badge bg-<?= str_starts_with($m['type'],'purchase')||$m['type']==='return'||$m['type']==='transfer_in'?'success':'danger' ?>"><?= e($m['type']) ?></span></td>
      <td class="<?= $m['quantity_change'] > 0 ? 'text-success' : 'text-danger' ?> fw-bold"><?= $m['quantity_change'] > 0 ? '+' : '' ?><?= (float)$m['quantity_change'] ?></td>
      <td><?= (float)$m['quantity_before'] ?></td><td><?= (float)$m['quantity_after'] ?></td>
      <td><small class="text-muted"><?= e($m['reference_type']??'') ?> #<?= e($m['reference_id']??'') ?></small></td>
      <td><small><?= e($m['notes']??'') ?></small></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</div></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>