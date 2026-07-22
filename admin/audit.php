<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_role('admin');

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$filters = [];
$sql = "SELECT a.*, u.username, u.full_name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE 1=1";
$params = [];

if (($action = $_GET['action'] ?? '') !== '') {
    $sql .= " AND a.action = ?";
    $params[] = $action;
}
if (($entity = $_GET['entity'] ?? '') !== '') {
    $sql .= " AND a.entity_type = ?";
    $params[] = $entity;
}
if (($user = $_GET['user'] ?? 0) > 0) {
    $sql .= " AND a.user_id = ?";
    $params[] = $user;
}
if (($date = $_GET['date'] ?? '') !== '') {
    $sql .= " AND DATE(a.created_at) = ?";
    $params[] = $date;
}

$sql .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$logs = db()->prepare($sql)->execute($params)->fetchAll(PDO::FETCH_ASSOC);

// Stats for filter dropdowns
$actions = db()->query("SELECT DISTINCT action FROM audit_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$entities = db()->query("SELECT DISTINCT entity_type FROM audit_log ORDER BY entity_type")->fetchAll(PDO::FETCH_COLUMN);
$users = db()->query("SELECT id, username FROM users ORDER BY username")->fetchAll(PDO::FETCH_KEY_PAIR);

$page_title = 'Audit Log';
$active = 'audit';
include __DIR__ . '/../includes/header.php'; ?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="bb-page-title">Audit Log <span class="badge text-bg-secondary"><?= count($logs) ?></span></h1>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form class="row g-2" method="get">
      <div class="col-md-2"><select class="form-select" name="action"><option value="">All Actions</option><?php foreach($actions as $a): ?><option value="<?= e($a) ?>" <?= $action===$a?'selected':'' ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><select class="form-select" name="entity"><option value="">All Entities</option><?php foreach($entities as $e): ?><option value="<?= e($e) ?>" <?= $entity===$e?'selected':'' ?>><?= e($e) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><select class="form-select" name="user"><option value="">All Users</option><?php foreach($users as $id=>$name): ?><option value="<?= $id ?>" <?= $user===$id?'selected':'' ?>><?= e($name) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><input class="form-control" type="date" name="date" value="<?= e($date) ?>"></div>
      <div class="col-md-2"><button class="btn btn-bb-orange w-100" type="submit"><i class="bi bi-filter"></i> Filter</button></div>
      <div class="col-md-2"><a class="btn btn-outline-secondary w-100" href="<?= base_url('admin/audit.php') ?>"><i class="bi bi-x-lg"></i> Clear</a></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Time</th><th>Action</th><th>Entity</th><th>User</th><th>Changes</th></tr></thead><tbody>
        <?php if (empty($logs)): ?>
          <tr><td colspan="5" class="bb-empty">No audit entries found.</td></tr>
        <?php else: ?>
          <?php foreach ($logs as $l): ?>
          <tr>
            <td class="text-muted small"><?= date('Y-m-d H:i', strtotime($l['created_at'])) ?></td>
            <td><span class="badge bg-<?= in_array($l['action'],['created','payment'])?'success':($l['action']==='deleted'?'danger':($l['action']==='updated'?'warning':'info')) ?>"><?= e($l['action']) ?></span></td>
            <td><code class="small"><?= e($l['entity_type']) ?></code> #<?= e($l['entity_id']) ?></td>
            <td><?= e($l['full_name'] ?? $l['username'] ?? 'System') ?></td>
            <td>
              <?php if ($l['old_values'] || $l['new_values']): ?>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#diff-<?= $l['id'] ?>" aria-expanded="false">View</button>
                <div class="collapse" id="diff-<?= $l['id'] ?>"><div class="card card-body small mt-1">
                  <?php if ($l['old_values']): ?><div class="text-danger"><strong>Old:</strong> <?= e(json_encode(json_decode($l['old_values'], true), JSON_PRETTY_PRINT)) ?></div><?php endif; ?>
                  <?php if ($l['new_values']): ?><div class="text-success"><strong>New:</strong> <?= e(json_encode(json_decode($l['new_values'], true), JSON_PRETTY_PRINT)) ?></div><?php endif; ?>
                </div></div>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody></table>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>