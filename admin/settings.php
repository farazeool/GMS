<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';

require_role('admin');

$page_title = 'System Settings';
$active = 'settings';

include __DIR__ . '/../includes/header.php';
?>

<h4 class="fw-bold mb-4">System Settings</h4>

<div class="card">
  <div class="card-body text-center py-5">
    <i class="bi bi-gear display-4 bb-text-orange"></i>
    <h5 class="mt-3">System Settings</h5>
    <p class="text-muted mb-0">Garage profile, working hours, and preferences will be built in the next phase.</p>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
