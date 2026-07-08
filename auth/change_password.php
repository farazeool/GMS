<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';

require_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([current_user_id()]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
        $errors[] = 'Current password is incorrect.';
    }
    if (strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters long.';
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New password and confirmation do not match.';
    }
    if ($currentPassword !== '' && $newPassword !== '' && hash_equals($currentPassword, $newPassword)) {
        $errors[] = 'New password must be different from your current password.';
    }

    if (!$errors) {
        $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), current_user_id()]);
        set_flash('success', 'Your password has been changed successfully.');
        header('Location: ' . base_url(is_admin() ? 'admin/dashboard.php' : 'technician/dashboard.php'));
        exit;
    }
}

$page_title = 'Change Password';
$active = '';

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Change Password</h4>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <strong>Please fix the following:</strong>
    <ul class="mb-0">
      <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card" style="max-width: 620px;">
  <div class="card-body p-4">
    <form method="post" action="<?= base_url('auth/change_password.php') ?>" autocomplete="off">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label" for="current_password">Current Password <span class="text-danger">*</span></label>
        <input class="form-control" type="password" id="current_password" name="current_password" required autocomplete="current-password">
      </div>
      <div class="mb-3">
        <label class="form-label" for="new_password">New Password <span class="text-danger">*</span></label>
        <input class="form-control" type="password" id="new_password" name="new_password" minlength="8" required autocomplete="new-password">
        <div class="form-text">Minimum 8 characters.</div>
      </div>
      <div class="mb-4">
        <label class="form-label" for="confirm_password">Confirm New Password <span class="text-danger">*</span></label>
        <input class="form-control" type="password" id="confirm_password" name="confirm_password" minlength="8" required autocomplete="new-password">
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-bb" type="submit"><i class="bi bi-key"></i> Change Password</button>
        <a class="btn btn-outline-secondary" href="<?= base_url(is_admin() ? 'admin/dashboard.php' : 'technician/dashboard.php') ?>">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
