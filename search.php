<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/commercial.php';
require_once __DIR__ . '/../includes/inventory.php';
require_login();

$q = trim($_GET['q'] ?? '');
$results = [];

if ($q !== '') {
    $results = global_search($q);
}

$itemModel = new InventoryItem();
$lowStock = $itemModel->getLowStock();
$outOfStock = $itemModel->getOutOfStock();

$page_title = 'Search & Inventory Status';
$active = 'search';
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="bb-page-title">Global Search</h1>
</div>

<div class="card mb-4"><div class="card-body py-3">
<form class="row g-2" method="get"><div class="col-md-6">
<div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span>
<input class="form-control form-control-lg" type="text" name="q" value="<?= e($q) ?>" placeholder="Search customers, vehicles, job cards, invoices, inventory..." required autofocus></div></div>
<div class="col-auto"><button class="btn btn-bb btn-lg" type="submit"><i class="bi bi-search"></i> Search</button></div></form></div></div>

<?php if ($q !== ''): ?>
<div class="row g-3 mb-4">
  <?php foreach (['customers'=>'Customers','vehicles'=>'Vehicles','job_cards'=>'Job Cards','invoices'=>'Invoices','inventory'=>'Inventory'] as $key=>$label): ?>
    <?php if (!empty($results[$key])): ?>
    <div class="col-md-4"><div class="card h-100"><div class="card-header"><h2 class="h6 mb-0"><?= e($label) ?> <span class="badge text-bg-secondary"><?= count($results[$key]) ?></span></h2></div>
      <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0"><thead class="table-light"><tr>
      <?php if ($key==='customers'): ?><th>Name</th><th>Phone</th><th>Email</th><?php elseif($key==='vehicles'): ?><th>Plate</th><th>Make/Model</th><th>Owner</th><?php elseif($key==='job_cards'): ?><th>Job #</th><th>Customer</th><th>Status</th><?php elseif($key==='invoices'): ?><th>Invoice #</th><th>Customer</th><th>Total</th><th>Status</th><?php else: ?><th>SKU</th><th>Name</th><th>Stock</th></tr><?php endif; ?></thead><tbody>
      <?php foreach ($results[$key] as $r): ?><tr>
      <?php if ($key==='customers'): ?><td><a class="text-decoration-none" href="<?= base_url('customers/view.php?id='.$r['id']) ?>"><?= e($r['name']) ?></a></td><td><?= e($r['phone']) ?></td><td><?= e($r['email']??'') ?></td><?php elseif($key==='vehicles'): ?><td><?= e($r['plate_number']) ?></td><td><?= e($r['make'].' '.$r['model']) ?></td><td><?= e($r['cname']) ?></td><?php elseif($key==='job_cards'): ?><td><a class="text-decoration-none" href="<?= base_url('job_cards/view.php?id='.$r['id']) ?>"><?= e($r['job_number']) ?></a></td><td><?= e($r['cname']) ?></td><td><?= e($r['status']) ?></td><?php elseif($key==='invoices'): ?><td><?= e($r['invoice_number']) ?></td><td><?= e($r['cname']) ?></td><td class="text-end"><?= format_kwd($r['total']) ?></td><td><span class="badge bg-<?= $r['status']==='paid'?'success':($r['status']==='overdue'?'danger':'secondary') ?>"><?= e($r['status']) ?></span></td><?php else: ?><td class="fw-semibold"><a class="text-decoration-none" href="<?= base_url('inventory/view.php?id='.$r['id']) ?>"><?= e($r['name']) ?></a></td><td><?= e($r['sku']) ?></td><td><span class="badge <?= (float)$r['quantity']<=0?'bg-danger':(((float)$r['quantity']<=(float)$r['minimum_stock']&&(float)$r['minimum_stock']>0)?'bg-warning text-dark':'bg-success') ?>"><?= (float)$r['quantity'] ?></span></td><?php endif; ?>
      </tr><?php endforeach; ?>
      </tbody></table></div></div></div>
    <?php endif; ?>
  <?php endforeach; ?>
</div>

<?php if ($q !== '' && empty(array_filter($results))): ?>
  <div class="alert alert-info">No results found for "<strong><?= e($q) ?></strong>".</div>
<?php endif; ?>

<?php else: ?>
<div class="row g-3 mb-4">
  <div class="col-md-6"><div class="card"><div class="card-header d-flex justify-content-between"><h2 class="h6 mb-0"><i class="bi bi-exclamation-triangle text-warning"></i> Low Stock</h2><span class="badge text-bg-warning"><?= count($lowStock) ?></span></div><div class="card-body">
  <?php if (empty($lowStock)): ?><p class="text-muted mb-0">No low stock items.</p><?php else: ?>
  <ul class="list-group list-group-flush"><?php foreach ($lowStock as $i): ?><li class="list-group-item px-0 d-flex justify-content-between"><span class="fw-semibold"><?= e($i['name']) ?></span><span class="badge bg-warning text-dark"><?= (float)$i['quantity'] ?> / <?= (float)$i['minimum_stock'] ?></span></li><?php endforeach; ?></ul><?php endif; ?>
  </div></div></div>
  <div class="col-md-6"><div class="card"><div class="card-header d-flex justify-content-between"><h2 class="h6 mb-0"><i class="bi bi-exclamation-octagon text-danger"></i> Out of Stock</h2><span class="badge bg-danger"><?= count($outOfStock) ?></span></div><div class="card-body">
  <?php if (empty($outOfStock)): ?><p class="text-muted mb-0">All items in stock.</p><?php else: ?>
  <ul class="list-group list-group-flush"><?php foreach ($outOfStock as $i): ?><li class="list-group-item px-0 d-flex justify-content-between"><span class="fw-semibold text-danger"><?= e($i['name']) ?></span><span class="badge bg-danger">Out</span></li><?php endforeach; ?></ul><?php endif; ?>
  </div></div></div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>