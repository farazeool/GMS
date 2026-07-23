<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/uuid.php';
require_once __DIR__ . '/../includes/portal_session.php';

portal_start_session();
portal_require_login();

$customerId = portal_customer_id();
$invoices = db()->prepare('SELECT i.*, jc.job_number FROM invoices i LEFT JOIN job_cards jc ON jc.id=i.job_card_id WHERE i.customer_id = ? AND i.deleted_at IS NULL ORDER BY i.created_at DESC LIMIT 50')
            ->execute([portal_customer_id()])->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'My Invoices';
$active = 'invoices';
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="bb-page-title">My Invoices <span class="badge text-bg-secondary"><?= count($invoices) ?></span></h1>
</div>
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Invoice #</th><th>Job</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        <?php if (empty($invoices)): ?><tr><td colspan="7" class="bb-empty">No invoices yet.</td></tr><?php endif; ?>
        <?php foreach ($invoices as $i): ?>
          <tr>
            <td class="fw-semibold"><a class="text-decoration-none" href="<?= base_url('portal/invoices/view.php?id=' . $i['id']) ?>"><?= e($i['invoice_number']) ?></a></td>
            <td><?= e($i['job_number'] ?? '—') ?></td>
            <td class="fw-semibold"><?= format_kwd($i['total']) ?></td>
            <td class="text-success"><?= format_kwd($i['paid_amount']) ?></td>
            <td class="<?= (float)$i['balance'] > 0 ? 'text-danger fw-semibold' : '' ?>"><?= format_kwd($i['balance']) ?></td>
            <td><span class="badge bg-<?= $i['status']==='paid'?'success':($i['status']==='overdue'?'danger':'secondary') ?>"><?= e($i['status']) ?></span></td>
            <td class="text-muted small"><?= date('Y-m-d', strtotime($i['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>