<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/uuid.php';
require_once __DIR__ . '/../includes/portal_session.php';

portal_start_session();
portal_require_login();

$customerId = portal_customer_id();
$quotations = db()->prepare('SELECT q.*, v.plate_number, v.make, v.model FROM quotations q LEFT JOIN vehicles v ON v.id=q.vehicle_id WHERE q.customer_id=? AND q.deleted_at IS NULL ORDER BY q.created_at DESC')
            ->execute([$customerId])->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'My Quotations';
$active = 'quotations';
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="bb-page-title">My Quotations <span class="badge text-bg-secondary"><?= count($quotations) ?></span></h1>
</div>
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Quote #</th><th>Vehicle</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        <?php if (empty($quotations)): ?><tr><td colspan="5" class="bb-empty">No quotations yet.</td></tr><?php endif; ?>
        <?php foreach ($quotations as $q):
            $statusClasses = ['draft'=>'bg-secondary','approved'=>'bg-success','rejected'=>'bg-danger','converted'=>'bg-info'];
        ?>
        <tr>
          <td class="fw-semibold"><a class="text-decoration-none" href="<?= base_url('portal/quotations/view.php?id=' . $q['id']) ?>"><?= e($q['quotation_number']) ?></a></td>
          <td><?= e($q['plate_number'] . ' — ' . $q['make'] . ' ' . $q['model']) ?></td>
          <td class="fw-semibold"><?= format_kwd($q['total']) ?></td>
          <td><span class="badge bg-<?= $statusClasses[$q['status']] ?? 'bg-secondary' ?>"><?= e($q['status']) ?></span></td>
          <td class="text-muted small"><?= date('Y-m-d', strtotime($q['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>