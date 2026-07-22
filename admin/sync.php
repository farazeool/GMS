<?php
/**
 * BrightBlaze – Synchronization Dashboard
 * Admin interface for monitoring and controlling synchronization.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../sync/SyncManager.php';

require_role('admin');

$page_title = 'Synchronization Dashboard';
$active = 'sync';

$syncManager = SyncManager::createDefault(db());
$status = $syncManager->getStatus();

// Handle sync actions
$action = $_POST['action'] ?? '';
$message = '';
$messageType = 'info';

if ($action === 'sync_now' && $status['mode'] !== 'local_only') {
    $results = $syncManager->processSyncBatch();
    if (!empty($results)) {
        $message = "Processed " . count($results) . " sync item(s)";
        $messageType = 'success';
    } else {
        $message = "No items to sync";
        $messageType = 'info';
    }
    $status = $syncManager->getStatus();
} elseif ($action === 'retry_failed') {
    $queue = new SyncQueue(db());
    $retried = $queue->retryAllFailed();
    $message = "Retrying $retried failed item(s)";
    $messageType = 'success';
    $status = $syncManager->getStatus();
} elseif ($action === 'clear_completed') {
    $queue = new SyncQueue(db());
    $purged = $queue->purgeCompleted(7);
    $message = "Purged $purged completed item(s)";
    $messageType = 'success';
    $status = $syncManager->getStatus();
} elseif ($action === 'resolve_conflicts') {
    $resolver = new SyncConflictResolver(db());
    $resolved = $resolver->resolveAllWithStrategy('local_wins');
    $message = "Resolved $resolved conflict(s) with local_wins strategy";
    $messageType = 'success';
    $status = $syncManager->getStatus();
} elseif ($action === 'check_connectivity') {
    $state = new SyncState(db());
    $apiClient = new RemoteApiClient(db());
    $checker = new OnlineChecker(db(), $state, $apiClient);
    $check = $checker->check();
    $message = $check['online'] ? 'Online - connectivity confirmed' : 'Offline - no connectivity detected';
    $messageType = $check['online'] ? 'success' : 'warning';
    $status = $syncManager->getStatus();
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h1 class="bb-page-title">Synchronization Dashboard</h1>
        <span class="bb-page-subtitle">Offline-first data synchronization management</span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <form method="post" class="d-inline">
            <input type="hidden" name="action" value="sync_now">
            <button type="submit" class="btn btn-bb" <?= $status['mode'] === 'local_only' ? 'disabled' : '' ?>>
                <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i> Sync Now
            </button>
        </form>
        <form method="post" class="d-inline">
            <input type="hidden" name="action" value="retry_failed">
            <button type="submit" class="btn btn-outline-warning">
                <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Retry Failed
            </button>
        </form>
        <form method="post" class="d-inline">
            <input type="hidden" name="action" value="clear_completed">
            <button type="submit" class="btn btn-outline-secondary">
                <i class="bi bi-trash" aria-hidden="true"></i> Clear Completed
            </button>
        </form>
        <form method="post" class="d-inline">
            <input type="hidden" name="action" value="check_connectivity">
            <button type="submit" class="btn btn-outline-info">
                <i class="bi bi-wifi" aria-hidden="true"></i> Check Connectivity
            </button>
        </form>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType === 'success' ? 'success' : ($messageType === 'warning' ? 'warning' : 'info') ?> alert-dismissible fade show" role="alert">
        <?= e($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card bb-stat bb-stat-accent-primary">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="bb-stat-value"><?= $status['is_online'] ? '<span class="badge bg-success">Online</span>' : '<span class="badge bg-danger">Offline</span>' ?></div>
                    <div class="bb-stat-label">Connectivity</div>
                </div>
                <i class="bi bi-<?= $status['is_online'] ? 'wifi' : 'wifi-off' ?> bb-stat-icon" aria-hidden="true"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card bb-stat bb-stat-accent-info">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="bb-stat-value"><?= e($status['mode']) ?></div>
                    <div class="bb-stat-label">Sync Mode</div>
                </div>
                <i class="bi bi-cloud-sync bb-stat-icon" aria-hidden="true"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card bb-stat bb-stat-accent-success">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="bb-stat-value"><?= $status['last_sync_at'] ?: 'Never' ?></div>
                    <div class="bb-stat-label">Last Sync</div>
                </div>
                <i class="bi bi-clock-history bb-stat-icon" aria-hidden="true"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card bb-stat bb-stat-accent-warning">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="bb-stat-value"><?= $status['pending_conflicts'] ?></div>
                    <div class="bb-stat-label">Conflicts</div>
                </div>
                <i class="bi bi-exclamation-triangle bb-stat-icon" aria-hidden="true"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card bb-stat bb-stat-accent-info">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="bb-stat-value"><?= $status['queue_length'] ?></div>
                    <div class="bb-stat-label">Total Queued</div>
                </div>
                <i class="bi bi-queue bb-stat-icon" aria-hidden="true"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card bb-stat bb-stat-accent-primary">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="bb-stat-value"><?= $status['pending_count'] ?></div>
                    <div class="bb-stat-label">Pending</div>
                </div>
                <i class="bi bi-hourglass-split bb-stat-icon" aria-hidden="true"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card bb-stat bb-stat-accent-warning">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="bb-stat-value"><?= $status['syncing_count'] ?></div>
                    <div class="bb-stat-label">Syncing</div>
                </div>
                <i class="bi bi-arrow-repeat bb-stat-icon" aria-hidden="true"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card bb-stat bb-stat-accent-danger">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="bb-stat-value"><?= $status['failed_count'] ?></div>
                    <div class="bb-stat-label">Failed</div>
                </div>
                <i class="bi bi-x-circle bb-stat-icon" aria-hidden="true"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h6 mb-0">Sync Queue</h2>
                <span class="badge bg-secondary"><?= $status['queue_length'] ?> items</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Entity</th>
                                <th>Operation</th>
                                <th>Status</th>
                                <th>Attempts</th>
                                <th>Scheduled</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $queue = new SyncQueue(db());
                            $items = $queue->getRecentItems(20);
                            if (empty($items)):
                            ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No sync items</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td class="text-muted small">#<?= (int) $item['id'] ?></td>
                                        <td><code class="small"><?= e($item['entity_type']) ?></code></td>
                                        <td>
                                            <span class="badge bg-<?= $item['operation'] === 'create' ? 'success' : ($item['operation'] === 'update' ? 'primary' : 'danger') ?>">
                                                <?= e($item['operation']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?=
                                                $item['status'] === 'completed' ? 'success' :
                                                ($item['status'] === 'syncing' ? 'info' :
                                                ($item['status'] === 'failed' ? 'danger' :
                                                ($item['status'] === 'retry_scheduled' ? 'warning' : 'secondary'))) ?>">
                                                <?= e($item['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center"><?= (int) $item['attempt_count'] ?></td>
                                        <td class="text-muted small"><?= $item['scheduled_at'] ? date('M j H:i', strtotime($item['scheduled_at'])) : '—' ?></td>
                                        <td class="text-muted small"><?= date('M j H:i', strtotime($item['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">
                <h2 class="h6 mb-0">Conflicts</h2>
            </div>
            <div class="card-body">
                <?php
                $resolver = new SyncConflictResolver(db());
                $conflicts = $resolver->getUnresolvedConflicts(10);
                if (empty($conflicts)):
                ?>
                    <p class="text-muted mb-0">No unresolved conflicts</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($conflicts as $conflict): ?>
                            <li class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <code class="small"><?= e($conflict['entity_type']) ?></code>
                                        <br><small class="text-muted"><?= e($conflict['entity_uuid']) ?></small>
                                    </div>
                                    <span class="badge bg-warning text-dark"><?= e($conflict['resolution_strategy'] ?? 'manual') ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="mt-2">
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="resolve_conflicts">
                            <button type="submit" class="btn btn-sm btn-outline-warning">
                                <i class="bi bi-check-all" aria-hidden="true"></i> Resolve All (Local Wins)
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h2 class="h6 mb-0">Sync State</h2>
            </div>
            <div class="card-body">
                <?php
                $state = new SyncState(db());
                $stateData = $state->getAll();
                if (!empty($stateData)):
                ?>
                    <dl class="row mb-0">
                        <?php foreach ($stateData as $key => $value): ?>
                            <dt class="col-5 text-muted small"><?= e($key) ?></dt>
                            <dd class="col-7 small"><code><?= e($value) ?></code></dd>
                        <?php endforeach; ?>
                    </dl>
                <?php else: ?>
                    <p class="text-muted mb-0">No state data</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="h6 mb-0">Actions</h2>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <form method="post" class="d-grid">
                        <input type="hidden" name="action" value="retry_failed">
                        <button type="submit" class="btn btn-outline-warning">
                            <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Retry All Failed
                        </button>
                    </form>
                    <form method="post" class="d-grid">
                        <input type="hidden" name="action" value="clear_completed">
                        <button type="submit" class="btn btn-outline-secondary">
                            <i class="bi bi-trash" aria-hidden="true"></i> Clear Completed (7+ days)
                        </button>
                    </form>
                    <form method="post" class="d-grid">
                        <input type="hidden" name="action" value="resolve_conflicts">
                        <button type="submit" class="btn btn-outline-warning">
                            <i class="bi bi-check-all" aria-hidden="true"></i> Resolve Conflicts (Local Wins)
                        </button>
                    </form>
                    <form method="post" class="d-grid">
                        <input type="hidden" name="action" value="check_connectivity">
                        <button type="submit" class="btn btn-outline-info">
                            <i class="bi bi-wifi" aria-hidden="true"></i> Check Connectivity
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>