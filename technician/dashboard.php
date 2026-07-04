<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';

require_role('technician');

$page_title = 'My Dashboard';
$active = 'dashboard';

$uid = current_user_id();

function tech_jobs(int $uid, string $where): array
{
    $stmt = db()->prepare(
        "SELECT jc.id, jc.job_number, jc.service_category, jc.priority, jc.status,
                jc.estimated_completion, v.plate_number, v.make, v.model, c.name AS customer_name
         FROM job_cards jc
         JOIN vehicles v ON v.id = jc.vehicle_id
         JOIN customers c ON c.id = jc.customer_id
         WHERE jc.technician_id = ? AND $where
         ORDER BY FIELD(jc.priority, 'High', 'Medium', 'Low'), jc.created_at DESC"
    );
    $stmt->execute([$uid]);
    return $stmt->fetchAll();
}

$assigned       = tech_jobs($uid, "jc.status = 'Assigned'");
$inProgress     = tech_jobs($uid, "jc.status = 'In Progress'");
$completedToday = tech_jobs($uid, "jc.status = 'Completed' AND DATE(jc.completed_at) = CURDATE()");

include __DIR__ . '/../includes/header.php';

function render_job_list(array $jobs, string $empty): void
{
    if (!$jobs) {
        echo '<p class="text-muted small mb-0">' . e($empty) . '</p>';
        return;
    }
    echo '<ul class="list-group list-group-flush">';
    foreach ($jobs as $job) {
        echo '<li class="list-group-item px-0">';
        echo '<div class="d-flex justify-content-between align-items-start gap-2">';
        echo '<div><span class="fw-semibold">' . e($job['job_number']) . '</span><br>';
        echo '<span class="small">' . e($job['make'] . ' ' . $job['model']) . ' · ' . e($job['plate_number']) . '</span><br>';
        echo '<span class="small text-muted">' . e($job['service_category']) . ' — ' . e($job['customer_name']) . '</span></div>';
        echo '<div class="text-end">' . priority_badge($job['priority']) . '<br><span class="small text-muted">Due ' . e(format_date($job['estimated_completion'])) . '</span></div>';
        echo '</div></li>';
    }
    echo '</ul>';
}
?>

<div class="mb-4">
  <h4 class="fw-bold mb-0">Technician Dashboard</h4>
  <span class="text-muted small">Welcome, <?= e(current_user_name()) ?></span>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="bi bi-inbox text-primary"></i> Assigned to Me <span class="badge text-bg-secondary"><?= count($assigned) ?></span></div>
      <div class="card-body"><?php render_job_list($assigned, 'No newly assigned jobs.'); ?></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="bi bi-wrench-adjustable bb-text-orange"></i> In Progress <span class="badge text-bg-secondary"><?= count($inProgress) ?></span></div>
      <div class="card-body"><?php render_job_list($inProgress, 'No jobs in progress.'); ?></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold"><i class="bi bi-check-circle text-success"></i> Completed Today <span class="badge text-bg-secondary"><?= count($completedToday) ?></span></div>
      <div class="card-body"><?php render_job_list($completedToday, 'Nothing completed today yet.'); ?></div>
    </div>
  </div>
</div>

<div class="alert alert-light border mt-4 small mb-0">
  <i class="bi bi-info-circle"></i> Status updates and service notes will be available in the <strong>Job Cards</strong> module (next phase).
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
