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
        echo '<div><a class="fw-semibold text-decoration-none" href="' . base_url('job_cards/view.php?id=' . (int) $job['id']) . '">' . e($job['job_number']) . '</a><br>';
        echo '<span class="small">' . e($job['make'] . ' ' . $job['model']) . ' · ' . e($job['plate_number']) . '</span><br>';
        echo '<span class="small text-muted">' . e($job['service_category']) . ' — ' . e($job['customer_name']) . '</span></div>';
        echo '<div class="text-end">' . priority_badge($job['priority']) . '<br><span class="small text-muted">Due ' . e(format_date($job['estimated_completion'])) . '</span></div>';
        echo '</div></li>';
    }
    echo '</ul>';
}
?>

<div class="mb-4">
  <h1 class="bb-page-title">Technician Dashboard</h1>
  <span class="bb-page-subtitle">Welcome, <?= e(current_user_name()) ?></span>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-inbox bb-text-orange" aria-hidden="true"></i>
        <h2 class="h6 mb-0">Assigned to Me</h2>
        <span class="badge bb-status-assigned ms-auto"><?= count($assigned) ?></span>
      </div>
      <div class="card-body"><?php render_job_list($assigned, 'No newly assigned jobs.'); ?></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-wrench-adjustable bb-text-orange" aria-hidden="true"></i>
        <h2 class="h6 mb-0">In Progress</h2>
        <span class="badge bb-status-in-progress ms-auto"><?= count($inProgress) ?></span>
      </div>
      <div class="card-body"><?php render_job_list($inProgress, 'No jobs in progress.'); ?></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-check-circle bb-text-orange" aria-hidden="true"></i>
        <h2 class="h6 mb-0">Completed Today</h2>
        <span class="badge bb-status-completed ms-auto"><?= count($completedToday) ?></span>
      </div>
      <div class="card-body"><?php render_job_list($completedToday, 'Nothing completed today yet.'); ?></div>
    </div>
  </div>
</div>

<div class="alert alert-info border-0 mt-4 small mb-0" role="note">
  <i class="bi bi-info-circle" aria-hidden="true"></i> Open a job card to update its status and add service notes.
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
