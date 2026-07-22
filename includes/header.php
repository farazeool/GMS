<?php
if (!isset($page_title)) {
    $page_title = APP_NAME;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title) ?> · BrightBlaze Garage</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="<?= base_url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>
<div class="bb-topbar">
  <button class="btn btn-sm btn-outline-light" id="bbSidebarToggle" type="button"
          aria-controls="bbSidebar" aria-expanded="false" aria-label="Open navigation menu">
    <i class="bi bi-list" aria-hidden="true"></i>
  </button>
  <span class="bb-brand"><i class="bi bi-fire" aria-hidden="true"></i> Bright<span>Blaze</span></span>
</div>
<div class="bb-layout d-flex">
  <?php include __DIR__ . '/sidebar.php'; ?>
  <button class="bb-backdrop" id="bbBackdrop" type="button" tabindex="-1" aria-label="Close navigation menu" hidden></button>
  <main class="bb-content" id="bbMain">
    <div class="bb-content-inner">
    <?php $flashes = get_flashes(); ?>
    <?php foreach ($flashes as $flash): ?>
      <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endforeach; ?>
