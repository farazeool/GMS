<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/uuid.php';
require_once __DIR__ . '/../includes/portal_session.php';
require_once __DIR__ . '/../includes/csrf.php';

portal_start_session();

if (portal_is_logged_in()) {
    header('Location: ' . base_url('portal/dashboard.php'));
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $errors[] = 'Email and password are required.';
    } else {
        $result = portal_login($email, $password);
        if ($result['ok']) {
            header('Location: ' . base_url('portal/dashboard.php'));
            exit;
        }
        $errors[] = $result['error'];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Customer Portal · BrightBlaze Garage</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="<?= base_url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="bb-login-page d-flex align-items-center justify-content-center">
  <div class="card bb-login-card">
    <div class="card-body p-4 p-md-5">
      <h1 class="bb-brand bb-brand-dark text-center mb-4 h4">
        <i class="bi bi-fire" aria-hidden="true"></i> Bright<span>Blaze</span>
        <small class="d-block">Customer Portal</small>
      </h1>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger py-2" role="alert">
          <?php foreach ($errors as $e): ?><div><?= e($e) ?></div><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= base_url('portal/login.php') ?>" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label" for="email">Email</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope" aria-hidden="true"></i></span>
            <input class="form-control" type="email" id="email" name="email" autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
          </div>
        </div>
        <div class="mb-4">
          <label class="form-label" for="password">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock" aria-hidden="true"></i></span>
            <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
          </div>
        </div>
        <button class="btn btn-bb w-100" type="submit">Sign in</button>
      </form>

      <p class="text-center small text-muted mt-4 mb-0">
        Need help? Contact your service advisor.
      </p>
    </div>
  </div>
</body>
</html>