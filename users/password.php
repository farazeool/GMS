<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/user_helpers.php';

require_role('admin');

$id = (int) ($_GET['id'] ?? 0);
$user = get_user_row($id);

if (!$user) {
    set_flash('danger', 'User not found.');
    header('Location: ' . base_url('users/index.php'));
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    foreach (validate_password_policy($password) as $policyError) {
        $errors[] = $policyError;
    }

    if (!$errors) {
        $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
        set_flash('success', 'Password updated for "' . $user['username'] . '".');
        header('Location: ' . base_url('users/index.php'));
        exit;
    }
}

$page_title = 'Reset Password';
$active = 'users';

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h1 class="bb-page-title">Reset Password</h1>
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
    <p class="mb-3">
      Setting a new password for <strong><?= e($user['full_name']) ?></strong>
      (<code><?= e($user['username']) ?></code>, <?= e($user['role_name']) ?>).
    </p>
    <form method="post" action="<?= base_url('users/password.php?id=' . $id) ?>" autocomplete="off">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label" for="password">New Password <span class="bb-required" aria-hidden="true">*</span></label>
        <input class="form-control" type="password" id="password" name="password" minlength="8" required autocomplete="new-password">
        <div class="form-text">Minimum 8 characters. Stored securely as a bcrypt hash.</div>
      </div>
      <div class="mb-4">
        <label class="form-label" for="password_confirm">Confirm New Password <span class="bb-required" aria-hidden="true">*</span></label>
        <input class="form-control" type="password" id="password_confirm" name="password_confirm" minlength="8" required autocomplete="new-password">
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-bb" type="submit"><i class="bi bi-key" aria-hidden="true"></i> Set New Password</button>
        <a class="btn btn-outline-secondary" href="<?= base_url('users/index.php') ?>">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
