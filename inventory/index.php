<?php require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/inventory.php';
require_role('admin');
$page_title = 'Inventory';
$active = 'inventory';
$itemModel = new InventoryItem();
$catModel = new InventoryCategory();
$supplierModel = new InventorySupplier();
$filters = [];
if (($_GET['q'] ?? '') !== '') { $filters['q'] = $_GET['q']; }
if (($_GET['category_id'] ?? 0) > 0) { $filters['category_id'] = (int)$_GET['category_id']; }
if (($_GET['supplier_id'] ?? 0) > 0) { $filters['supplier_id'] = (int)$_GET['supplier_id']; }
if (isset($_GET['low_stock'])) { $filters['low_stock'] = true; }
$items = $itemModel->all($filters);
$categories = $catModel->all();
$suppliers = $supplierModel->all();
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h1 class="bb-page-title">Inventory <span class="badge text-bg-secondary align-middle"><?= count($items) ?></span></h1>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-secondary" href="<?= base_url('inventory/categories.php') ?>"><i class="bi bi-tags"></i> Categories</a>
    <a class="btn btn-outline-secondary" href="<?= base_url('inventory/suppliers.php') ?>"><i class="bi bi-truck"></i> Suppliers</a>
    <a class="btn btn-bb" href="<?= base_url('inventory/form.php') ?>"><i class="bi bi-plus-lg"></i> Add Item</a>
  </div>
</div>
<div class="card mb-3">
  <div class="card-body py-3">
    <form class="row g-2" method="get">
      <div class="col-md-4"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" type="text" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Search by name, SKU, or barcode"></div></div>
      <div class="col-md-2"><select class="form-select" name="category_id"><option value="">All Categories</option><?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>" <?= (int)($filters['category_id']??0)===$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><select class="form-select" name="supplier_id"><option value="">All Suppliers</option><?php foreach($suppliers as $s): ?><option value="<?= $s['id'] ?>" <?= (int)($filters['supplier_id']??0)===$s['id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-auto"><div class="form-check pt-2"><input class="form-check-input" type="checkbox" id="ls" name="low_stock" value="1" <?= isset($filters['low_stock'])?'checked':'' ?>><label class="form-check-label" for="ls">Low Stock</label></div></div>
      <div class="col-auto"><button class="btn btn-bb-orange" type="submit">Filter</button><?php if(count($filters)): ?><a class="btn btn-outline-secondary ms-1" href="<?= base_url('inventory/index.php') ?>">Clear</a><?php endif; ?></div>
    </form>
  </div>
</div>
<div class="card"><div class="card-body p-0"><div class="table-responsive">
  <table class="table table-hover align-middle mb-0"><thead class="table-light"><tr>
    <th>SKU</th><th>Name</th><th>Category</th><th>Stock</th><th>Purchase</th><th>Selling</th><th>Location</th><th class="text-end">Actions</th>
  </tr></thead><tbody>
  <?php if (empty($items)): ?><tr><td colspan="8" class="bb-empty">No inventory items found.</td></tr><?php endif; ?>
  <?php foreach ($items as $item): ?>
    <?php $low = (float)$item['quantity'] <= (float)$item['minimum_stock'] && (float)$item['minimum_stock'] > 0; ?>
    <?php $oos = (float)$item['quantity'] <= 0; ?>
    <tr class="<?= $oos ? 'table-danger' : ($low ? 'table-warning' : '') ?>">
      <td><code class="small"><?= e($item['sku']) ?></code></td>
      <td class="fw-semibold"><a class="text-decoration-none" href="<?= base_url('inventory/view.php?id=' . $item['id']) ?>"><?= e($item['name']) ?></a></td>
      <td><small class="text-muted"><?= e($item['category_name'] ?? '—') ?></small></td>
      <td>
        <span class="badge <?= $oos ? 'bg-danger' : ($low ? 'bg-warning text-dark' : 'bg-success') ?>" title="<?= $oos ? 'Out of Stock' : ($low ? 'Low Stock' : 'In Stock') ?>">
          <?= (float) $item['quantity'] ?> <?= e($item['unit']) ?>
        </span>
      </td>
      <td><?= format_kwd($item['purchase_price']) ?></td>
      <td><?= format_kwd($item['selling_price']) ?></td>
      <td><small class="text-muted"><?= e($item['warehouse_location'] ?? '—') ?></small></td>
      <td class="bb-actions">
        <a class="btn btn-sm btn-outline-primary" href="<?= base_url('inventory/form.php?id=' . $item['id']) ?>"><i class="bi bi-pencil"></i></a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('inventory/movement.php?id=' . $item['id']) ?>"><i class="bi bi-arrow-left-right"></i></a>
        <form method="post" action="<?= base_url('inventory/delete.php') ?>" class="d-inline">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $item['id'] ?>">
          <button class="btn btn-sm btn-outline-danger" type="submit" data-confirm="Delete <?= e($item['name']) ?>?"><i class="bi bi-trash"></i></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</div></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>