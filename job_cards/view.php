<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/job_helpers.php';

require_login();

$id = (int) ($_GET['id'] ?? 0);
$job = get_job($id);

if (!$job || !can_access_job($job)) {
    set_flash('danger', 'Job card not found or you do not have access to it.');
    header('Location: ' . base_url('job_cards/index.php'));
    exit;
}

$stmt = db()->prepare(
    'SELECT sn.*, u.full_name AS author_name
     FROM service_notes sn
     LEFT JOIN users u ON u.id = sn.user_id
     WHERE sn.job_card_id = ?
     ORDER BY sn.created_at DESC'
);
$stmt->execute([$id]);
$notes = $stmt->fetchAll();
$noteCount = count($notes);

if (is_admin()) {
    $allowedStatuses = array_values(array_diff(JOB_STATUSES, [$job['status']]));
} else {
    $allowedStatuses = TECH_STATUS_TRANSITIONS[$job['status']] ?? [];
}

$canAddNote = is_admin() || $job['status'] !== 'Cancelled';

$page_title = $job['job_number'];
$active = 'job_cards';

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 no-print">
  <h4 class="fw-bold mb-0">
    <i class="bi bi-card-checklist bb-text-orange"></i> <?= e($job['job_number']) ?>
    <?= status_badge($job['status']) ?> <?= priority_badge($job['priority']) ?>
  </h4>
  <div class="d-flex gap-2 flex-wrap">
    <button class="btn btn-outline-secondary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    <?php if (is_admin()): ?>
      <a class="btn btn-bb-orange" href="<?= base_url('job_cards/form.php?id=' . $id) ?>"><i class="bi bi-pencil"></i> Edit</a>
    <?php endif; ?>
    <a class="btn btn-outline-secondary" href="<?= base_url('job_cards/index.php') ?>"><i class="bi bi-arrow-left"></i> Back</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card mb-3">
      <div class="card-header bg-white fw-bold">Job Card <?= e($job['job_number']) ?></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <dl class="row mb-0">
              <dt class="col-5">Customer</dt>
              <dd class="col-7">
                <?php if (is_admin()): ?>
                  <a class="text-decoration-none" href="<?= base_url('customers/view.php?id=' . (int) $job['customer_id']) ?>"><?= e($job['customer_name']) ?></a>
                <?php else: ?>
                  <?= e($job['customer_name']) ?>
                <?php endif; ?>
              </dd>
              <dt class="col-5">Phone</dt><dd class="col-7"><?= e($job['customer_phone']) ?></dd>
              <dt class="col-5">Vehicle</dt>
              <dd class="col-7">
                <?php $vehicleLabel = $job['make'] . ' ' . $job['model'] . ($job['vehicle_year'] ? ' (' . $job['vehicle_year'] . ')' : ''); ?>
                <?php if (is_admin()): ?>
                  <a class="text-decoration-none" href="<?= base_url('vehicles/view.php?id=' . (int) $job['vehicle_id']) ?>"><?= e($vehicleLabel) ?></a>
                <?php else: ?>
                  <?= e($vehicleLabel) ?>
                <?php endif; ?>
              </dd>
              <dt class="col-5">Plate</dt><dd class="col-7"><?= e($job['plate_number']) ?></dd>
              <dt class="col-5">Color</dt><dd class="col-7 mb-0"><?= e($job['vehicle_color'] ?? '') ?: '—' ?></dd>
            </dl>
          </div>
          <div class="col-md-6">
            <dl class="row mb-0">
              <dt class="col-5">Category</dt><dd class="col-7"><?= e($job['service_category']) ?></dd>
              <dt class="col-5">Technician</dt><dd class="col-7"><?= e($job['technician_name'] ?? 'Unassigned') ?></dd>
              <dt class="col-5">Priority</dt><dd class="col-7"><?= priority_badge($job['priority']) ?></dd>
              <dt class="col-5">Status</dt><dd class="col-7"><?= status_badge($job['status']) ?></dd>
              <dt class="col-5">Est. Completion</dt><dd class="col-7"><?= format_date($job['estimated_completion']) ?></dd>
              <dt class="col-5">Created</dt><dd class="col-7"><?= format_date($job['created_at'], 'd M Y H:i') ?></dd>
              <dt class="col-5">Completed</dt><dd class="col-7 mb-0"><?= $job['completed_at'] ? format_date($job['completed_at'], 'd M Y H:i') : '—' ?></dd>
            </dl>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header bg-white fw-bold">Problem Description</div>
      <div class="card-body"><p class="mb-0" style="white-space: pre-line;"><?= e($job['problem_description']) ?></p></div>
    </div>

    <div class="card">
      <div class="card-header bg-white fw-bold">Service Notes <span class="badge text-bg-secondary"><?= $noteCount ?></span></div>
      <div class="card-body">
        <?php if (!$notes): ?>
          <p class="text-muted">No service notes yet. At least one note is required before this job can be completed.</p>
        <?php else: ?>
          <?php foreach ($notes as $i => $note): ?>
            <?php if ($i > 0): ?><hr class="my-3"><?php endif; ?>
            <div>
              <div class="d-flex justify-content-between flex-wrap">
                <strong><?= e($note['author_name'] ?? 'Former staff member') ?></strong>
                <span class="text-muted small"><?= format_date($note['created_at'], 'd M Y H:i') ?></span>
              </div>
              <p class="mb-0 mt-1" style="white-space: pre-line;"><?= e($note['note']) ?></p>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($canAddNote): ?>
          <form class="mt-3 no-print" method="post" action="<?= base_url('job_cards/note.php') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <label class="form-label fw-semibold" for="note">Add Service Note</label>
            <textarea class="form-control" id="note" name="note" rows="3" required placeholder="What was checked, done, or found?"></textarea>
            <button class="btn btn-bb mt-2" type="submit"><i class="bi bi-plus-lg"></i> Add Note</button>
          </form>
        <?php else: ?>
          <div class="alert alert-light border small mt-3 mb-0 no-print"><i class="bi bi-info-circle"></i> This job card is cancelled and can no longer be updated.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-4 no-print">
    <div class="card">
      <div class="card-header bg-white fw-bold">Update Status</div>
      <div class="card-body">
        <p class="mb-2">Current status: <?= status_badge($job['status']) ?></p>
        <?php if ($allowedStatuses): ?>
          <form method="post" action="<?= base_url('job_cards/status.php') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <select class="form-select mb-2" name="status" required>
              <?php foreach ($allowedStatuses as $s): ?>
                <option value="<?= e($s) ?>"><?= e($s) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-bb w-100" type="submit"><i class="bi bi-arrow-repeat"></i> Update Status</button>
          </form>
        <?php else: ?>
          <p class="text-muted small mb-0">No status changes are available for this job card.</p>
        <?php endif; ?>
        <?php if ($job['status'] !== 'Completed' && $noteCount === 0): ?>
          <div class="alert alert-warning small mt-3 mb-0">
            <i class="bi bi-exclamation-triangle"></i> Add at least one service note before this job can be marked Completed.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
