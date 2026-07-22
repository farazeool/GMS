<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/commercial.php';
require_role('admin');

$inv = db()->prepare('SELECT * FROM invoices WHERE id=?');
$inv->execute([(int)$_GET['id']]);
$inv = $inv->fetch();
if (!$inv) { set_flash('danger', 'Invoice not found.'); header('Location: ' . base_url('invoices/index.php')); exit; }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $amount = (float)($_POST['amount'] ?? 0);
    $method = $_POST['payment_method'] ?? 'cash';
    $ref = trim($_POST['reference_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($amount <= 0) $errors[] = 'Amount must be positive.';
    if (!in_array($method, ['cash','card','bank_transfer','other'])) $method = 'cash';

    if (!$errors) {
        $result = record_payment($inv['id'], $amount, $method, $ref, $notes);
        if ($result['success']) {
            audit_log('payment', 'invoices', $inv['id'], $inv['uuid'], ['status' => $inv['status']], ['status' => $result['status']]);
            notify_invoice_created($inv['id']);
            set_flash('success', 'Payment recorded. New balance: ' . format_kwd($result['new_balance']));
            header('Location: ' . base_url('invoices/view.php?id=' . $inv['id'])); exit;
        } else {
            $errors[] = $result['error'];
        }
    }
}

$page_title = 'Payment - ' . $inv['invoice_number'];
$active = 'invoices';
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between mb-4">
  <h1 class="bb-page-title">Record Payment <?= e($inv['invoice_number']) ?></h1>
  <a class="btn btn-outline-secondary" href="<?= base_url('invoices/view.php?id=' . $inv['id']) ?>"><i class="bi bi-arrow-left"></i> Back</a>
</div>
<div class="row g-3">
  <div class="col-md-6">
    <div class="card"><div class="card-body text-center">
      <h3 class="text-muted">Total</h3>
      <h2 class="display-6"><?= format_kwd($inv['total']) ?></h2>
      <h3 class="text-muted">Paid</h3>
      <h4 class="text-success"><?= format_kwd($inv['paid_amount']) ?></h4>
      <h3 class="text-muted">Balance</h3>
      <h4 class="<?= (float)$inv['balance'] > 0 ? 'text-danger' : 'text-success' ?>"><?= format_kwd($inv['balance']) ?></h4>
    </div></div>
  </div>
  <div class="col-md-6">
    <div class="card"><div class="card-body">
      <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <form method="post"><?= csrf_field() ?>
        <div class="mb-3"><label class="form-label">Amount (KWD) *</label>
          <input class="form-control" type="number" step="0.001" name="amount" max="<?= (float)$inv['balance'] ?>" value="<?= (float)$inv['balance'] ?>" required></div>
        <div class="mb-3"><label class="form-label">Payment Method</label>
          <select class="form-select" name="payment_method">
            <option value="cash">Cash</option><option value="card">Card</option><option value="bank_transfer">Bank Transfer</option><option value="other">Other</option>
          </select></div>
        <div class="mb-3"><label class="form-label">Reference Number</label>
          <input class="form-control" name="reference_number" placeholder="Transaction ID, cheque #, etc."></div>
        <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
        <button class="btn btn-bb btn-lg w-100"><i class="bi bi-cash"></i> Record Payment</button>
      </form>
    </div></div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>