<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/uuid.php';
require_once __DIR__ . '/../includes/portal_session.php';

portal_start_session();
portal_require_login();

$customerId = portal_customer_id();
$jobs = db()->prepare('SELECT jc.*, v.plate_number, v.make, v.model FROM job_cards jc JOIN vehicles v ON v.id=jc.vehicle_id WHERE jc.customer_id = ? AND jc.deleted_at IS NULL ORDER BY jc.created_at DESC')
          ->execute([$customerId])->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Service History';
$active = 'jobs';
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="bb-page-title">Service History <span class="badge text-bg-secondary"><?= count($jobs)</span</h1>
</div>
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Job #</th><th>Vehicle</th><th>Category</th><th>Status</th><th>Created</th><th>Completed</th</tr</thead>
        <tbody>
        <?php if (empty($jobs)): ?>
          <tr><td colspan="6" class="bb-empty">No service history yet</td</tr>
        <?php else: foreach ($jobs as $j): ?>
          <tr>
            <td class="fw-semibold"><a class="text-decoration-none" href="<?= base_url('portal/jobs/view.php?id=' . $j['id']) ?>"><?= e($j['job_number'])</a></td>
            <td><?= e($j['plate_number'] . ' — ' . $j['make'] . ' ' . $j['model'])</td>
            <td><span class="badge bg-primary"><?= e($j['service_category'])</span</td>
            <td><?= status_badge($j['status'])</td>
            <td class="text-muted small"><?= date('Y-m-d', strtotime($j['created_at']))</td>
            <td class="text-muted small"><?= $j['completed_at'] ? date('Y-m-d', strtotime($j['completed_at'])) : '—'</td>
         </tr>
        <?php endforeach; ?>
       </tbody>
     </table>
   </div>
 </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>