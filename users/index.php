<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/user_helpers.php';

require_role('admin');

$page_title = 'Users & Roles';
$active = 'users';

$q = trim($_GET['q'] ?? '');

$sql = 'SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR r.name LIKE ?';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like, $like];
}
$sql .= ' ORDER BY u.full_name';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$adminCount = active_admin_count();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h1 class="bb-page-title"><i class="bi bi-person-gear bb-text-orange" aria-hidden="true"></i> Users &amp; Roles <span class="badge text-bg-secondary align-middle"><?= count($users) ?></span></h1>
  <a class="btn btn-bb" href="<?= base_url('users/form.php') ?>"><i class="bi bi-person-plus" aria-hidden="true"></i> Add User</a>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form class="row g-2" method="get" action="<?= base_url('users/index.php') ?>">
      <div class="col-md-6 col-lg-5">
        <label class="visually-hidden" for="userSearch">Search users</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
          <input class="form-control" type="text" id="userSearch" name="q" value="<?= e($q) ?>" placeholder="Search by name, username, email, or role">
        </div>
      </div>
      <div class="col-auto">
        <button class="btn btn-bb" type="submit">Search</button>
        <?php if ($q !== ''): ?>
          <a class="btn btn-outline-secondary" href="<?= base_url('users/index.php') ?>">Clear</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th>
            <th>Created</th><th>Updated</th><th class="bb-actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$users): ?>
            <tr><td colspan="8" class="bb-empty">No users match your search.</td></tr>
          <?php endif; ?>
          <?php foreach ($users as $u): ?>
            <?php
            $isSelf = (int) $u['id'] === current_user_id();
            $isLastActiveAdmin = $u['role_name'] === 'admin' && (int) $u['is_active'] === 1 && $adminCount <= 1;
            ?>
            <tr>
              <td class="fw-semibold">
                <?= e($u['full_name']) ?>
                <?php if ($isSelf): ?><span class="badge bb-text-orange">You</span><?php endif; ?>
              </td>
              <td><?= e($u['username']) ?></td>
              <td><?= e($u['email'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
              <td><?= role_badge($u['role_name']) ?></td>
              <td><?= active_badge((int) $u['is_active']) ?></td>
              <td class="text-muted small"><?= format_date($u['created_at']) ?></td>
              <td class="text-muted small"><?= format_date($u['updated_at'] ?? null) ?></td>
              <td class="bb-actions">
                <a class="btn btn-sm btn-outline-primary" href="<?= base_url('users/form.php?id=' . (int) $u['id']) ?>" aria-label="Edit <?= e($u['full_name']) ?>" title="Edit"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('users/password.php?id=' . (int) $u['id']) ?>" aria-label="Reset password for <?= e($u['full_name']) ?>" title="Reset password"><i class="bi bi-key" aria-hidden="true"></i></a>
                <?php if (!$isSelf && !((int) $u['is_active'] === 1 && $isLastActiveAdmin)): ?>
                  <form method="post" action="<?= base_url('users/status.php') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                    <?php if ((int) $u['is_active'] === 1): ?>
                      <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Deactivate <?= e($u['full_name']) ?>" title="Deactivate"
                              data-confirm="Deactivate <?= e($u['full_name']) ?>? They will no longer be able to sign in.">
                        <i class="bi bi-person-x" aria-hidden="true"></i>
                      </button>
                    <?php else: ?>
                      <button class="btn btn-sm btn-outline-success" type="submit" aria-label="Activate <?= e($u['full_name']) ?>" title="Activate"
                              data-confirm="Activate <?= e($u['full_name']) ?>?">
                        <i class="bi bi-person-check" aria-hidden="true"></i>
                      </button>
                    <?php endif; ?>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="text-muted small mb-0 mt-3">
      <i class="bi bi-shield-lock" aria-hidden="true"></i> Users cannot be deleted, only deactivated, to preserve job card and service note history.
      You cannot deactivate your own account or the only active administrator.
    </p>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
