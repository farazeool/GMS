<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/inventory.php';
require_once __DIR__ . '/../includes/commercial.php';
require_role('admin');

$itemModel = new InventoryItem();

$totalItems = (int) db()->query("SELECT COUNT(*) FROM inventory_items WHERE deleted_at IS NULL")->fetchColumn();
$totalValue = (float) db()->query("SELECT SUM(quantity * purchase_price) FROM inventory_items WHERE deleted_at IS NULL")->fetchColumn();
$lowStockCount = count($itemModel->getLowStock());
$oosCount = count($itemModel->getOutOfStock());

$topCategories = db()->query("
    SELECT c.name, COUNT(i.id) AS cnt, SUM(i.quantity * i.purchase_price) AS value
    FROM inventory_items i JOIN inventory_categories c ON c.id=i.category_id
    WHERE i.deleted_at IS NULL GROUP BY c.id ORDER BY cnt DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$recentMovements = db()->query("
    SELECT im.*, i.name AS item_name, i.sku
    FROM inventory_movements im JOIN inventory_items i ON i.id=im.item_id
    ORDER BY im.created_at DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Inventory Dashboard';
$active = 'inventory';
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h1 class="bb-page-title">Inventory Dashboard</h1>
  <a class="btn btn-bb" href="<?= base_url('inventory/index.php') ?>"><i class="bi bi-plus-lg"></i> Add Item</a>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="card bb-stat"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="bb-stat-value"><?= number_format($totalItems) ?></div><div class="bb-stat-label">Total Items</div></div><i class="bi bi-box-seam bb-stat-icon"></i></div></div></div>
  <div class="col-6 col-md-3"><div class="card bb-stat bb-stat-accent-info"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="bb-stat-value"><?= format_kwd($totalValue) ?></div><div class="bb-stat-label">Inventory Value</div></div><i class="bi bi-currency-dollar bb-stat-icon"></i></div></div></div>
  <div class="col-6 col-md-3"><div class="card bb-stat bb-stat-accent-warning"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="bb-stat-value"><?= $lowStockCount ?></div><div class="bb-stat-label">Low Stock</div></div><i class="bi bi-exclamation-triangle bb-stat-icon"></i></div></div></div>
  <div class="col-6 col-md-3"><div class="card bb-stat bb-stat-accent-danger"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="bb-stat-value"><?= $oosCount ?></div><div class="bb-stat-label">Out of Stock</div></div><i class="bi bi-x-circle bb-stat-icon"></i></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card"><div class="card-header d-flex justify-content-between align-items-center">
      <h2 class="h6 mb-0">Top Categories</h2><span class="badge bg-secondary"><?= count($topCategories) ?></span></div>
    <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Category</th><th class="text-end">Items</th><th class="text-end">Value</th></tr></thead><tbody>
    <?php foreach ($topCategories as $c): ?>
    <tr><td><?= e($c['name']) ?></td><td class="text-end"><span class="badge text-bg-secondary"><?= (int)$c['cnt'] ?></span></td><td class="text-end"><?= format_kwd((float)$c['value']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div></div></div>
  </div>
  <div class="col-lg-4">
    <div class="card"><div class="card-header d-flex justify-content-between align-items-center">
      <h2 class="h6 mb-0">Recent Movements</h2><span class="badge bg-secondary"><?= count($recentMovements) ?></span></div>
    <div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Item</th><th>Type</th><th class="text-end">Change</th><th class="text-end">After</th><th class="text-muted small">Time</th></tr></thead><tbody>
    <?php foreach ($recentMovements as $m): ?>
    <tr><td class="fw-semibold small"><?= e($m['item_name']) ?></td><td><span class="badge bg-<?= $m['type']==='purchase'||$m['type']==='return'||$m['type']==='transfer_in'?'success':'danger' ?>"><?= e($m['type']) ?></span></td>
    <td class="text-end fw-bold <?= $m['quantity_change']>0?'text-success':'text-danger' ?>"><?= $m['quantity_change']>0?'+':'' ?><?= (float)$m['quantity_change'] ?></td>
    <td class="text-end"><?= (float)$m['quantity_after'] ?></td><td class="text-muted small"><?= date('M j H:i', strtotime($m['created_at'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div></div></div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>