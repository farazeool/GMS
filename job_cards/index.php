<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';

require_login();

$page_title = 'Job Cards';
$active = 'job_cards';

include __DIR__ . '/../includes/header.php';
?>

<h4 class="fw-bold mb-4"><?= is_admin() ? 'Job Cards' : 'My Job Cards' ?></h4>

<div class="card">
  <div class="card-body text-center py-5">
    <i class="bi bi-card-checklist display-4 bb-text-orange"></i>
    <h5 class="mt-3">Job Card Management</h5>
    <p class="text-muted mb-0">
      <?php if (is_admin()): ?>
        Create, assign, search, and track job cards. This module will be built in the next phase.
      <?php else: ?>
        View your assigned job cards, update status, and add service notes. This module will be built in the next phase.
      <?php endif; ?>
    </p>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
