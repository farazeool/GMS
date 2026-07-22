<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/commercial.php';
require_role('admin');

$status = $_GET['status'] ?? '';
$sql = "SELECT n.* FROM notifications n";
$params = [];
if ($status !== '' && in_array($status, ['pending','sent','failed','read'])) {
    $sql .= " WHERE n.status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY n.created_at DESC";
$notifications = db()->prepare($sql)->execute($params)->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Notifications';
$active = 'notifications';
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="bb-page-title">Notifications <span class="badge text-bg-secondary"><?= count($notifications) ?></span></h1>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="<?= base_url('admin/notifications.php?status=pending') ?>">Pending</a>
    <a class="btn btn-outline-secondary" href="<?= base_url('admin/notifications.php?status=sent') ?>">Sent</a>
    <a class="btn btn-outline-secondary" href="<?= base_url('admin/notifications.php?status=failed') ?>">Failed</a>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Time</th><th>Type</th><th>Channel</th><th>Recipient</th><th>Subject</th><th>Status</th></tr></thead><tbody>
        <?php if (empty($notifications)): ?>
          <tr><td colspan="6" class="bb-empty">No notifications.</td></tr>
        <?php else: ?>
          <?php foreach ($notifications as $n): ?>
          <tr>
            <td class="text-muted small"><?= date('Y-m-d H:i', strtotime($n['created_at'])) ?></td>
            <td><span class="badge bg-info"><?= e($n['type']) ?></span></td>
            <td><span class="badge bg-secondary"><?= e($n['channel']) ?></span></td>
            <td><?= e($n['recipient_type']) ?> #<?= e($n['recipient_id'] ?? '—') ?></td>
            <td><?= e($n['subject']) ?></td>
            <td><span class="badge bg-<?= $n['status']==='sent'?'success':($n['status']==='failed'?'danger':($n['status']==='read'?'info':'secondary')) ?>"><?= e($n['status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody></table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>