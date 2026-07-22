<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/commercial.php';
require_role('admin');

$q = db()->prepare('SELECT q.*, c.name AS cname FROM quotations q JOIN customers c ON c.id=q.customer_id WHERE q.id=?');
$q->execute([(int)$_GET['id']]);
$q = $q->fetch();
if (!$q) { set_flash('danger', 'Not found.'); header('Location: ' . base_url('quotations/index.php')); exit; }

$lines = db()->prepare('SELECT * FROM quotation_lines WHERE quotation_id=? ORDER BY sort_order')->execute([$q['id']])->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Quotation ' . $q['quotation_number'];
$active = 'quotations';
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h1 class="bb-page-title">Quotation <?= e($q['quotation_number']) ?></h1>
  <div class="d-flex gap-2 flex-wrap">
    <span class="badge bg-<?= $q['status']==='approved'?'success':($q['status']==='rejected'?'danger':($q['status']==='converted'?'info':'secondary')) ?> fs-6"><?= e($q['status']) ?></span>
    <a class="btn btn-outline-secondary" href="<?= base_url('quotations/index.php') ?>"><i class="bi bi-arrow-left"></i> Back</a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-8">
    <div class="card"><div class="card-header d-flex justify-content-between"><h2 class="h6 mb-0">Quotation Details</h2>
    <span class="badge bg-<?= $q['status']==='approved'?'success':($q['status']==='rejected'?'danger':($q['status']==='converted'?'info':'secondary')) ?> fs-6"><?= e($q['status']) ?></span></div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">Customer</dt><dd class="col-sm-9"><?= e($q['cname']) ?></dt>
        <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><span class="badge bg-<?= $q['status']==='approved'?'success':($q['status']==='rejected'?'danger':($q['status']==='converted'?'info':'secondary')) ?>"><?= e($q['status']) ?></span></dd>
        <dt class="col-sm-3">Created</dt><dd class="col-sm-9"><?= date('Y-m-d', strtotime($q['created_at'])) ?></dt>
        <dt class="col-sm-3">Total</dt><dd class="col-sm-9 fw-bold"><?= format_kwd($q['total']) ?></td>
      </dl>
      <hr>
      <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Description</th><th class="text-end">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Total</th></tr></thead><tbody>
      <?php foreach ($lines as $l): ?>
        <tr><td><?= e($l['description']) ?></td><td class="text-end"><?= (float)$l['quantity'] ?></td><td class="text-end"><?= format_kwd($l['unit_price']) ?></td><td class="text-end"><?= format_kwd($l['line_total']) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
      <div class="row"><div class="col-md-6 offset-md-6">
        <table class="table table-sm"><tr><td>Subtotal:</td><td class="text-end"><?= format_kwd($q['subtotal']) ?></td></tr>
        <tr><td>Tax (<?= (float)$q['tax_rate'] ?>%):</td><td class="text-end"><?= format_kwd($q['tax_amount']) ?></td></tr>
        <tr><td>Discount:</td><td class="text-end">-<?= format_kwd($q['discount']) ?></td></tr>
        <tr class="fw-bold"><td>Total:</td><td class="text-end"><?= format_kwd($q['total']) ?></td></tr>
      </table>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card"><div class="card-header"><h2 class="h6 mb-0">Actions</h2></div><div class="card-body d-grid gap-2">
      <form method="post" action="<?= base_url('quotations/approve.php') ?>"><input type="hidden" name="id" value="<?= $q['id'] ?>"><?= csrf_field() ?>
        <button class="btn btn-success" type="submit" <?= $q['status']!=='draft'?'disabled':'' ?>><i class="bi bi-check-circle"></i> Approve</button></form>
      <form method="post" action="<?= base_url('quotations/reject.php') ?>" class="d-grid"><input type="hidden" name="id" value="<?= $q['id'] ?>"><?= csrf_field() ?>
        <button class="btn btn-danger" type="submit" <?= $q['status']!=='draft'?'disabled':'' ?>><i class="bi bi-x-circle"></i> Reject</button></form>
      <form method="post" action="<?= base_url('quotations/convert.php') ?>" class="d-grid"><input type="hidden" name="id" value="<?= $q['id'] ?>"><?= csrf_field() ?>
        <button class="btn btn-info" type="submit" <?= $q['status']!=='approved'?'disabled':'' ?>><i class="bi bi-arrow-right"></i> Convert to Job Card</button></form>
      <a class="btn btn-outline-secondary" href="<?= base_url('quotations/form.php?id=' . $q['id']) ?>"><i class="bi bi-pencil"></i> Edit</a>
      <a class="btn btn-outline-secondary" href="<?= base_url('quotations/print.php?id=' . $q['id']) ?>" target="_blank"><i class="bi bi-printer"></i> Print</a>
    </div></div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>