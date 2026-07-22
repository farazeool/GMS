<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/commercial.php';
require_role('admin');

$inv = db()->prepare('SELECT i.*, c.name AS cname, c.phone AS cphone, c.email AS cemail, c.address AS caddress, v.plate_number, v.make, v.model, jc.job_number FROM invoices i JOIN customers c ON c.id=i.customer_id LEFT JOIN vehicles v ON v.id=i.vehicle_id LEFT JOIN job_cards jc ON jc.id=i.job_card_id WHERE i.id=?');
$inv->execute([(int)$_GET['id']]);
$inv = $inv->fetch();
if (!$inv) { set_flash('danger', 'Invoice not found.'); header('Location: ' . base_url('invoices/index.php')); exit; }

$lines = db()->prepare('SELECT * FROM invoice_lines WHERE invoice_id=? ORDER BY sort_order')->execute([$inv['id']])->fetchAll(PDO::FETCH_ASSOC);
$payments = db()->prepare('SELECT * FROM payments WHERE invoice_id=? ORDER BY payment_date DESC')->execute([$inv['id']])->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Invoice ' . $inv['invoice_number'];
$active = 'invoices';
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h1 class="bb-page-title">Invoice <?= e($inv['invoice_number']) ?></h1>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-primary" href="<?= base_url('invoices/payment.php?id=' . $inv['id']) ?>"><i class="bi bi-cash"></i> Record Payment</a>
    <a class="btn btn-outline-secondary" href="<?= base_url('invoices/print.php?id=' . $inv['id']) ?>" target="_blank"><i class="bi bi-printer"></i> Print</a>
    <a class="btn btn-outline-secondary" href="<?= base_url('invoices/index.php') ?>"><i class="bi bi-arrow-left"></i> Back</a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-8">
    <div class="card"><div class="card-header d-flex justify-content-between"><h2 class="h6 mb-0">Invoice Details</h2><span class="badge <?= $inv['status']==='paid'?'bg-success':($inv['status']==='overdue'?'bg-danger':'bg-secondary') ?> fs-6"><?= e($inv['status']) ?></span></div>
    <div class="card-body">
      <div class="row mb-3"><div class="col-6"><strong>Customer:</strong> <?= e($inv['cname']) ?><br><?= e($inv['cphone']) ?><br><?= e($inv['cemail']??'') ?></div>
        <div class="col-6 text-end"><strong>Date:</strong> <?= date('Y-m-d', strtotime($inv['created_at'])) ?><br><?php if($inv['job_number']): ?><strong>Job:</strong> <?= e($inv['job_number']) ?><?php endif; ?><br><strong>Due:</strong> <?= e($inv['due_date'] ?? 'N/A') ?></div></div>
      <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead><tbody>
      <?php foreach ($lines as $l): ?>
        <tr><td><?= e($l['description']) ?></td><td class="text-end"><?= (float)$l['quantity'] ?></td><td class="text-end"><?= format_kwd($l['unit_price']) ?></td><td class="text-end"><?= format_kwd($l['line_total']) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
      <div class="row"><div class="col-md-6 offset-md-6">
        <table class="table table-sm"><tr><td>Subtotal:</td><td class="text-end"><?= format_kwd($inv['subtotal']) ?></td></tr>
        <?php if ((float)$inv['tax_amount'] > 0): ?><tr><td>Tax (<?= (float)$inv['tax_rate'] ?>%):</td><td class="text-end"><?= format_kwd($inv['tax_amount']) ?></td></tr><?php endif; ?>
        <tr class="fw-bold"><td>Total:</td><td class="text-end"><?= format_kwd($inv['total']) ?></td></tr>
        <tr><td>Paid:</td><td class="text-end text-success"><?= format_kwd($inv['paid_amount']) ?></td></tr>
        <tr class="fw-bold <?= (float)$inv['balance'] > 0 ? 'text-danger' : '' ?>"><td>Balance:</td><td class="text-end"><?= format_kwd($inv['balance']) ?></td></tr>
      </table></div></div>
      <?php if ($inv['notes']): ?><p><strong>Notes:</strong> <?= e($inv['notes']) ?></p><?php endif; ?>
    </div></div>
  </div>
  <div class="col-md-4">
    <?php if ($payments): ?>
    <div class="card"><div class="card-header"><h2 class="h6 mb-0">Payments</h2></div>
    <ul class="list-group list-group-flush"><?php foreach ($payments as $p): ?>
      <li class="list-group-item d-flex justify-content-between align-items-center">
        <div><small><?= date('Y-m-d', strtotime($p['payment_date'])) ?></small><br><span class="badge bg-info"><?= e($p['payment_method']) ?></span> <?= e($p['reference_number']??'') ?></div>
        <span class="fw-bold text-success">+<?= format_kwd($p['amount']) ?></span>
      </li>
    <?php endforeach; ?></ul></div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>