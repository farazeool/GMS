<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';

require_role('admin');

$page_title = 'Vehicle Registry';
$active = 'vehicles';

$q = trim($_GET['q'] ?? '');

$sql = 'SELECT v.*, c.name AS customer_name
        FROM vehicles v
        JOIN customers c ON c.id = v.customer_id';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE v.plate_number LIKE ? OR c.name LIKE ? OR v.make LIKE ? OR v.model LIKE ?';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like, $like];
}
$sql .= ' ORDER BY v.created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$vehicles = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h1 class="bb-page-title">Vehicle Registry <span class="badge text-bg-secondary align-middle"><?= count($vehicles) ?></span></h1>
  <a class="btn btn-bb" href="<?= base_url('vehicles/form.php') ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> Register Vehicle</a>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form class="row g-2" method="get" action="<?= base_url('vehicles/index.php') ?>">
      <div class="col-md-6 col-lg-5">
        <label class="visually-hidden" for="q">Search vehicles</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
          <input class="form-control" type="text" id="q" name="q" value="<?= e($q) ?>" placeholder="Search by plate, customer, make, or model">
        </div>
      </div>
      <div class="col-auto">
        <button class="btn btn-bb-orange" type="submit">Search</button>
        <?php if ($q !== ''): ?>
          <a class="btn btn-outline-secondary" href="<?= base_url('vehicles/index.php') ?>">Clear</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Plate</th><th>Vehicle</th><th>Year</th><th>Color</th><th>Owner</th><th>Registered</th><th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$vehicles): ?>
            <tr><td colspan="7" class="bb-empty">
              <?= $q !== '' ? 'No vehicles match your search.' : 'No vehicles registered yet. Click “Register Vehicle” to add the first one.' ?>
            </td></tr>
          <?php endif; ?>
          <?php foreach ($vehicles as $v): ?>
            <tr>
              <td class="fw-semibold"><a class="text-decoration-none" href="<?= base_url('vehicles/view.php?id=' . (int) $v['id']) ?>"><?= e($v['plate_number']) ?></a></td>
              <td><?= e($v['make'] . ' ' . $v['model']) ?></td>
              <td><?= e((string) ($v['year'] ?? '')) ?: '—' ?></td>
              <td><?= e($v['color'] ?? '') ?: '—' ?></td>
              <td><a class="text-decoration-none" href="<?= base_url('customers/view.php?id=' . (int) $v['customer_id']) ?>"><?= e($v['customer_name']) ?></a></td>
              <td class="text-muted small"><?= format_date($v['created_at']) ?></td>
              <td class="bb-actions">
                <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('vehicles/view.php?id=' . (int) $v['id']) ?>" title="View" aria-label="View <?= e($v['plate_number']) ?>"><i class="bi bi-eye" aria-hidden="true"></i></a>
                <a class="btn btn-sm btn-outline-primary" href="<?= base_url('vehicles/form.php?id=' . (int) $v['id']) ?>" title="Edit" aria-label="Edit <?= e($v['plate_number']) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                <form method="post" action="<?= base_url('vehicles/delete.php') ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete" aria-label="Delete <?= e($v['plate_number']) ?>"
                          data-confirm="Delete vehicle <?= e($v['plate_number']) ?>? Its job history and maintenance records will also be deleted.">
                    <i class="bi bi-trash" aria-hidden="true"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
