<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/commercial.php';
require_once __DIR__ . '/../includes/inventory.php';
require_role('admin');

// Revenue this month
$rev = db()->prepare("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status='paid' AND MONTH(paid_date)=MONTH(CURDATE()) AND YEAR(paid_date)=YEAR(CURDATE())");
$rev->execute();
$monthRevenue = (float)$rev->fetchColumn();

// Revenue last month
$revLast = db()->prepare("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status='paid' AND MONTH(paid_date)=MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(paid_date)=YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))");
$revLast->execute();
$lastMonthRevenue = (float)$revLast->fetchColumn();

// Jobs completed this month
$jobs = db()->prepare("SELECT COUNT(*) FROM job_cards WHERE status='Completed' AND MONTH(completed_at)=MONTH(CURDATE()) AND YEAR(completed_at)=YEAR(CURDATE())");
$jobs->execute();
$jobsCompleted = (int)$jobs->fetchColumn();

// Average repair time (days from created to completed)
$avgTime = db()->prepare("SELECT AVG(DATEDIFF(completed_at, created_at)) FROM job_cards WHERE status='Completed' AND completed_at IS NOT NULL AND MONTH(completed_at)=MONTH(CURDATE()) AND YEAR(completed_at)=YEAR(CURDATE())");
$avgTime->execute();
$avgRepairDays = round((float)$avgTime->fetchColumn(), 1);

// Top repairs
$topRepairs = db()->query("SELECT service_category, COUNT(*) as cnt FROM job_cards WHERE status='Completed' GROUP BY service_category ORDER BY cnt DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Technician productivity
$techProd = db()->query("SELECT u.full_name, COUNT(*) as completed FROM job_cards jc JOIN users u ON u.id=jc.technician_id WHERE jc.status='Completed' AND MONTH(jc.completed_at)=MONTH(CURDATE()) AND YEAR(jc.completed_at)=YEAR(CURDATE()) GROUP BY u.id ORDER BY completed DESC")->fetchAll(PDO::FETCH_ASSOC);

// Monthly revenue trend (last 6 months)
$months = [];
$revenueTrend = [];
for ($i = 5; $i >= 0; $i--) {
    $d = new DateTime("-$i months");
    $m = $d->format('Y-m');
    $months[] = $m;
    $rev = db()->prepare("SELECT COALESCE(SUM(total),0) FROM invoices WHERE status='paid' AND DATE_FORMAT(paid_date, '%Y-%m') = ?");
    $rev->execute([$m]);
    $revenueTrend[] = (float)$rev->fetchColumn();
}

// Customer retention
$repeatCustomers = db()->prepare("SELECT COUNT(DISTINCT customer_id) as cnt FROM job_cards WHERE status='Completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) HAVING COUNT(*) > 1");
$repeatCustomers->execute();
$repeatCount = (int)$repeatCustomers->fetchColumn();

// Top vehicles
$topVehicles = db()->query("SELECT v.plate_number, v.make, v.model, COUNT(*) as job_count FROM job_cards jc JOIN vehicles v ON v.id=jc.vehicle_id WHERE jc.status='Completed' GROUP BY v.id ORDER BY job_count DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Inventory value
$invVal = db()->query("SELECT COALESCE(SUM(quantity * selling_price),0) FROM inventory_items WHERE deleted_at IS NULL AND is_active=1")->fetchColumn();
$invValue = (float)$invVal;

// Low stock count
$itemModel = new InventoryItem();
$lowCount = count($itemModel->getLowStock());
$oosCount = count($itemModel->getOutOfStock());

$page_title = 'Admin Dashboard';
$active = 'dashboard';
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h1 class="bb-page-title">Admin Dashboard</h1>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="card bb-stat bb-stat-accent-info"><div class="card-body d-flex justify-content-between align-items-center">
    <div><div class="bb-stat-value"><?= format_kwd($monthRevenue) ?></div><div class="bb-stat-label">This Month Revenue</div></div>
    <i class="bi bi-cash-coin bb-stat-icon" aria-hidden="true"></i>
  </div></div></div>
  <div class="col-6 col-md-3"><div class="card bb-stat bb-stat-accent-success"><div class="card-body d-flex justify-content-between align-items-center">
    <div><div class="bb-stat-value"><?= $jobsCompleted ?></div><div class="bb-stat-label">Jobs Completed</div></div>
    <i class="bi bi-check-circle bb-stat-icon" aria-hidden="true"></i>
  </div></div></div>
  <div class="col-6 col-md-3"><div class="card bb-stat bb-stat-accent-warning"><div class="card-body d-flex justify-content-between align-items-center">
    <div><div class="bb-stat-value"><?= $avgRepairDays ?></div><div class="bb-stat-label">Avg Repair (days)</div></div>
    <i class="bi bi-hourglass-split bb-stat-icon" aria-hidden="true"></i>
  </div></div></div>
  <div class="col-6 col-md-3"><div class="card bb-stat bb-stat-accent-info"><div class="card-body d-flex justify-content-between align-items-center">
    <div><div class="bb-stat-value"><?= format_kwd($invValue) ?></div><div class="bb-stat-label">Inventory Value</div></div>
    <i class="bi bi-box-seam bb-stat-icon" aria-hidden="true"></i>
  </div></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-8"><div class="card"><div class="card-header d-flex justify-content-between"><h2 class="h6 mb-0">Revenue Trend (6 months)</h2></div><div class="card-body"><canvas id="revenueChart" height="200"></canvas></div></div></div>
  <div class="col-lg-4"><div class="card"><div class="card-header"><h2 class="h6 mb-0">Top Repairs</h2></div><div class="card-body">
    <?php if (empty($topRepairs)): ?><p class="text-muted mb-0">No data</p><?php else: ?><ul class="list-group list-group-flush"><?php foreach ($topRepairs as $tr): ?><li class="list-group-item px-0 d-flex justify-content-between"><span><?= e($tr['service_category']) ?></span><span class="badge text-bg-primary"><?= (int)$tr['cnt'] ?></span></li><?php endforeach; ?></ul><?php endif; ?>
  </div></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6"><div class="card"><div class="card-header"><h2 class="h6 mb-0">Technician Productivity</h2></div><div class="card-body">
    <?php if (empty($techProd)): ?><p class="text-muted mb-0">No data</p><?php else: ?><ul class="list-group list-group-flush"><?php foreach ($techProd as $tp): ?><li class="list-group-item px-0 d-flex justify-content-between"><span><?= e($tp['full_name']) ?></span><span class="badge bg-primary"><?= (int)$tp['completed'] ?></span></li><?php endforeach; ?></ul><?php endif; ?>
  </div></div></div>
  <div class="col-lg-6"><div class="card"><div class="card-header"><h2 class="h6 mb-0">Top Vehicles</h2></div><div class="card-body">
    <?php if (empty($topVehicles)): ?><p class="text-muted mb-0">No data</p><?php else: ?><ul class="list-group list-group-flush"><?php foreach ($topVehicles as $tv): ?><li class="list-group-item px-0 d-flex justify-content-between"><span><?= e($tv['plate_number'] . ' — ' . $tv['make'] . ' ' . $tv['model']) ?></span><span class="badge bg-secondary"><?= (int)$tv['job_count'] ?></span></li><?php endforeach; ?></ul><?php endif; ?>
  </div></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-4"><div class="card bb-stat bb-stat-accent-info"><div class="card-body d-flex justify-content-between align-items-center">
    <div><div class="bb-stat-value"><?= $repeatCount ?></div><div class="bb-stat-label">Repeat Customers (12m)</div></div>
    <i class="bi bi-people bb-stat-icon" aria-hidden="true"></i>
  </div></div></div>
  <div class="col-lg-4"><div class="card bb-stat bb-stat-accent-warning"><div class="card-body d-flex justify-content-between align-items-center">
    <div><div class="bb-stat-value"><?= $lowCount ?></div><div class="bb-stat-label">Low Stock Items</div></div>
    <i class="bi bi-exclamation-triangle bb-stat-icon" aria-hidden="true"></i>
  </div></div></div>
  <div class="col-lg-4"><div class="card bb-stat bb-stat-accent-danger"><div class="card-body d-flex justify-content-between align-items-center">
    <div><div class="bb-stat-value"><?= $oosCount ?></div><div class="bb-stat-label">Out of Stock</div></div>
    <i class="bi bi-exclamation-octagon bb-stat-icon" aria-hidden="true"></i>
  </div></div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('revenueChart');
if (ctx) {
  new Chart(ctx, {
    type: 'bar',
    data: { labels: <?= json_encode($months) ?>, datasets: [{ label: 'Revenue (KWD)', data: <?= json_encode($revenueTrend) ?>, backgroundColor: 'rgba(230,126,34,0.7)', borderColor: 'rgba(230,126,34,1)', borderWidth: 1 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: v => v + ' KWD' } } } }
  });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>