<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/user_helpers.php';

require_role('admin');

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;

$user = ['full_name' => '', 'username' => '', 'email' => '', 'phone' => '', 'role_id' => 0, 'is_active' => 1];
$existing = null;

if ($isEdit) {
    $existing = get_user_row($id);
    if (!$existing) {
        set_flash('danger', 'User not found.');
        header('Location: ' . base_url('users/index.php'));
        exit;
    }
    $user = $existing;
}

$roles = roles_list();
$roleIds = array_map(static fn ($r) => (int) $r['id'], $roles);
$adminRoleId = 0;
foreach ($roles as $r) {
    if ($r['name'] === 'admin') {
        $adminRoleId = (int) $r['id'];
    }
}

$isSelf = $isEdit && $id === current_user_id();
$errors = [];
$password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $user['full_name'] = trim($_POST['full_name'] ?? '');
    $user['username']  = trim($_POST['username'] ?? '');
    $user['email']     = trim($_POST['email'] ?? '');
    $user['phone']     = trim($_POST['phone'] ?? '');
    $user['role_id']   = (int) ($_POST['role_id'] ?? 0);
    $user['is_active'] = isset($_POST['is_active']) ? 1 : 0;
    $password          = $_POST['password'] ?? '';

    if ($user['full_name'] === '') {
        $errors[] = 'Full name is required.';
    }
    if ($user['username'] === '') {
        $errors[] = 'Username is required.';
    } else {
        $stmt = db()->prepare('SELECT id FROM users WHERE username = ? AND id <> ?');
        $stmt->execute([$user['username'], $id]);
        if ($stmt->fetch()) {
            $errors[] = 'This username is already taken.';
        }
    }
    if ($user['email'] !== '' && !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email address is not valid.';
    }
    if (!in_array($user['role_id'], $roleIds, true)) {
        $errors[] = 'Please select a valid role.';
    }

    if (!$isEdit && $password === '') {
        $errors[] = 'Password is required for a new user.';
    }
    if ($password !== '' && strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    // Lockout protection: never lose the last active administrator.
    if ($isSelf && $user['is_active'] !== 1) {
        $errors[] = 'You cannot deactivate your own account.';
    }
    if ($isEdit && $existing['role_name'] === 'admin' && (int) $existing['is_active'] === 1
        && ($user['role_id'] !== $adminRoleId || $user['is_active'] !== 1)
        && active_admin_count() <= 1) {
        $errors[] = 'This is the only active administrator. Add another active admin before demoting or deactivating this account.';
    }

    if (!$errors) {
        $email = $user['email'] !== '' ? $user['email'] : null;
        $phone = $user['phone'] !== '' ? $user['phone'] : null;

        if ($isEdit) {
            $stmt = db()->prepare('UPDATE users SET full_name = ?, username = ?, email = ?, phone = ?, role_id = ?, is_active = ? WHERE id = ?');
            $stmt->execute([$user['full_name'], $user['username'], $email, $phone, $user['role_id'], $user['is_active'], $id]);
            if ($password !== '') {
                $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            }
            set_flash('success', 'User updated successfully.');
        } else {
            $stmt = db()->prepare('INSERT INTO users (full_name, username, email, phone, password_hash, role_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$user['full_name'], $user['username'], $email, $phone, password_hash($password, PASSWORD_DEFAULT), $user['role_id'], $user['is_active']]);
            set_flash('success', 'User "' . $user['username'] . '" created successfully.');
        }
        header('Location: ' . base_url('users/index.php'));
        exit;
    }
}

$page_title = $isEdit ? 'Edit User' : 'Add User';
$active = 'users';

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h1 class="bb-page-title"><?= $isEdit ? 'Edit User' : 'Add User' ?></h1>
  <a class="btn btn-outline-secondary" href="<?= base_url('users/index.php') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Users</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger bb-form-narrow" role="alert">
    <strong>Please fix the following:</strong>
    <ul class="mb-0">
      <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card bb-form-narrow">
  <div class="card-body p-4">
    <form method="post" action="<?= base_url('users/form.php' . ($isEdit ? '?id=' . $id : '')) ?>" autocomplete="off">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="full_name">Full Name <span class="bb-required" aria-hidden="true">*</span></label>
          <input class="form-control" type="text" id="full_name" name="full_name" value="<?= e($user['full_name']) ?>" maxlength="120" required>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="username">Username <span class="bb-required" aria-hidden="true">*</span></label>
          <input class="form-control" type="text" id="username" name="username" value="<?= e($user['username']) ?>" maxlength="80" required>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="email">Email</label>
          <input class="form-control" type="email" id="email" name="email" value="<?= e($user['email'] ?? '') ?>" maxlength="120">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="phone">Phone</label>
          <input class="form-control" type="text" id="phone" name="phone" value="<?= e($user['phone'] ?? '') ?>" maxlength="20" placeholder="+965 XXXX XXXX">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="role_id">Role <span class="bb-required" aria-hidden="true">*</span></label>
          <select class="form-select" id="role_id" name="role_id" required>
            <option value="">— Select role —</option>
            <?php foreach ($roles as $r): ?>
              <option value="<?= (int) $r['id'] ?>" <?= (int) $user['role_id'] === (int) $r['id'] ? 'selected' : '' ?>><?= e(ucfirst($r['name'])) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="password"><?= $isEdit ? 'New Password' : 'Password' ?> <?= $isEdit ? '' : '<span class="bb-required" aria-hidden="true">*</span>' ?></label>
          <input class="form-control" type="password" id="password" name="password" minlength="8" autocomplete="new-password"
                 <?= $isEdit ? 'placeholder="Leave blank to keep current password"' : 'required' ?>>
          <div class="form-text">Minimum 8 characters. Stored securely as a bcrypt hash.</div>
        </div>
        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (int) $user['is_active'] === 1 ? 'checked' : '' ?> <?= $isSelf ? 'disabled' : '' ?>>
            <label class="form-check-label" for="is_active">Active (can sign in)</label>
            <?php if ($isSelf): ?>
              <input type="hidden" name="is_active" value="1">
              <div class="form-text">You cannot deactivate your own account.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button class="btn btn-bb" type="submit"><i class="bi bi-check-lg" aria-hidden="true"></i> <?= $isEdit ? 'Save Changes' : 'Create User' ?></button>
        <a class="btn btn-outline-secondary" href="<?= base_url('users/index.php') ?>">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
