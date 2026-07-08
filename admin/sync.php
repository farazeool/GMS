<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/sync_helpers.php';

require_role('admin');

$page_title = 'Sync Dashboard';
$active = 'sync';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $result = sync_run_now();
    set_flash($result['ok'] ? 'success' : 'warning', $result['message']);
    header('Location: ' . base_url('admin/sync.php'));
    exit;
}

$settings = get_settings();
$counts = sync_dashboard_counts();
$logs = sync_recent_logs(40);
$schemaReady = sync_schema_ready();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h4 class="fw-bold mb-0"><i class="bi bi-arrow-repeat bb-text-orange"></i> Sync Dashboard</h4>
  <form method="post" action="<?= base_url('admin/sync.php') ?>">
    <?= csrf_field() ?>
    <button class="btn btn-bb" type="submit"><i class="bi bi-cloud-arrow-up"></i> Manual Sync Now</button>
  </form>
</div>

<div class="alert alert-light border small">
  <i class="bi bi-info-circle"></i>
  Current mode: <strong><?= e($settings['sync_mode'] === 'online_sync' ? 'Online Sync Enabled' : 'Local Only') ?></strong> ·
  Cloud API: <code><?= e($settings['cloud_api_url'] !== '' ? $settings['cloud_api_url'] : 'Not configured') ?></code> ·
  API key: <strong><?= e(sync_masked_key($settings['sync_api_key'])) ?></strong> ·
  Last sync: <strong><?= e($settings['last_sync_at'] !== '' ? $settings['last_sync_at'] : 'Never') ?></strong>
</div>

<div class="card mb-3">
  <div class="card-header bg-white fw-bold">Pending/Synced by Entity</div>
  <div class="card-body">
    <?php if (!$schemaReady): ?>
      <div class="alert alert-warning mb-0">
        Run <code>/home/runner/work/GMS/GMS/database/migrations/m6_sync_engine.sql</code> in phpMyAdmin to enable sync tables/columns.
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr><th>Entity</th><th>Pending</th><th>Failed</th><th>Synced</th></tr>
        </thead>
        <tbody>
          <?php foreach ($counts as $entity => $c): ?>
            <tr>
              <td class="fw-semibold"><?= e($entity) ?></td>
              <td><span class="badge text-bg-warning"><?= (int) $c['pending'] ?></span></td>
              <td><span class="badge text-bg-danger"><?= (int) $c['failed'] ?></span></td>
              <td><span class="badge text-bg-success"><?= (int) $c['synced'] ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-header bg-white fw-bold">Recent Sync Logs</div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr><th>Time</th><th>Level</th><th>Message</th><th>By</th></tr>
        </thead>
        <tbody>
          <?php if (!$logs): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">No sync logs yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td class="text-muted small"><?= e($log['created_at']) ?></td>
              <td><span class="badge <?= $log['level'] === 'error' ? 'text-bg-danger' : 'text-bg-secondary' ?>"><?= e($log['level']) ?></span></td>
              <td>
                <?= e($log['message']) ?>
                <?php if (!empty($log['context_json'])): ?>
                  <details class="small mt-1"><summary>Details</summary><pre class="mb-0"><?= e($log['context_json']) ?></pre></details>
                <?php endif; ?>
              </td>
              <td><?= e($log['full_name'] ?? 'System') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
