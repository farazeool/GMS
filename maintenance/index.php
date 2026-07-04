<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';

require_role('admin');

$page_title = 'Maintenance Records';
$active = 'maintenance';

include __DIR__ . '/../includes/header.php';
?>

<h4 class="fw-bold mb-4">Maintenance Records</h4>

<div class="card">
  <div class="card-body text-center py-5">
    <i class="bi bi-tools display-4 bb-text-orange"></i>
    <h5 class="mt-3">Maintenance Records</h5>
    <p class="text-muted mb-0">Track service history per vehicle. This module will be built in the next phase.</p>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
