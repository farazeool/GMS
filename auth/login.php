<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';

if (is_logged_in()) {
    header('Location: ' . base_url(is_admin() ? 'admin/dashboard.php' : 'technician/dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = db()->prepare(
            'SELECT u.id, u.username, u.full_name, u.password_hash, r.name AS role_name
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.username = ? AND u.is_active = 1
             LIMIT 1'
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = (int) $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role_name'];

            header('Location: ' . base_url($user['role_name'] === 'admin' ? 'admin/dashboard.php' : 'technician/dashboard.php'));
            exit;
        }

        $error = 'Invalid username or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in · BrightBlaze Garage</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="<?= base_url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="bb-login-page d-flex align-items-center justify-content-center">
  <div class="card bb-login-card shadow-lg">
    <div class="card-body p-4 p-md-5">
      <div class="bb-brand bb-brand-dark text-center mb-4">
        <i class="bi bi-fire"></i> Bright<span>Blaze</span>
        <small class="d-block">Garage Management &amp; Job Card System</small>
      </div>
      <?php if ($error !== ''): ?>
        <div class="alert alert-danger py-2"><?= e($error) ?></div>
      <?php endif; ?>
      <form method="post" action="<?= base_url('auth/login.php') ?>" novalidate>
        <div class="mb-3">
          <label class="form-label" for="username">Username</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input class="form-control" type="text" id="username" name="username" value="<?= e($_POST['username'] ?? '') ?>" required autofocus>
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label" for="password">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input class="form-control" type="password" id="password" name="password" required>
          </div>
        </div>
        <button class="btn btn-bb w-100" type="submit"><i class="bi bi-box-arrow-in-right"></i> Sign in</button>
      </form>
    </div>
  </div>
</body>
</html>
