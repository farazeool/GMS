<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';

require_role('admin');

$page_title = 'Customers';
$active = 'customers';

$q = trim($_GET['q'] ?? '');

$sql = "SELECT c.*, (SELECT COUNT(*) FROM vehicles v WHERE v.customer_id = c.id AND v.deleted_at IS NULL) AS vehicle_count
        FROM customers c
        WHERE c.deleted_at IS NULL";
$params = [];
if ($q !== '') {
    $sql .= ' WHERE c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$sql .= ' ORDER BY c.created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h1 class="bb-page-title">Customers <span class="badge text-bg-secondary align-middle"><?= count($customers) ?></span></h1>
  <a class="btn btn-bb" href="<?= base_url('customers/form.php') ?>"><i class="bi bi-person-plus" aria-hidden="true"></i> Add Customer</a>
</div>

<div class="card mb-3">
  <div class="card-body py-3">
    <form class="row g-2" method="get" action="<?= base_url('customers/index.php') ?>">
      <div class="col-md-6 col-lg-5">
        <label class="visually-hidden" for="customerSearch">Search customers</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
          <input class="form-control" type="text" id="customerSearch" name="q" value="<?= e($q) ?>" placeholder="Search by name, phone, or email">
        </div>
      </div>
      <div class="col-auto">
        <button class="btn btn-bb-orange" type="submit">Search</button>
        <?php if ($q !== ''): ?>
          <a class="btn btn-outline-secondary" href="<?= base_url('customers/index.php') ?>">Clear</a>
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
            <th>Name</th><th>Phone</th><th>Email</th><th>Vehicles</th><th>Created</th><th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$customers): ?>
            <tr><td colspan="6" class="bb-empty">
              <?= $q !== '' ? 'No customers match your search.' : 'No customers yet. Click “Add Customer” to create the first one.' ?>
            </td></tr>
          <?php endif; ?>
          <?php foreach ($customers as $c): ?>
            <tr>
              <td class="fw-semibold"><a class="text-decoration-none" href="<?= base_url('customers/view.php?id=' . (int) $c['id']) ?>"><?= e($c['name']) ?></a></td>
              <td><?= e($c['phone']) ?></td>
              <td><?= e($c['email'] ?? '') ?: '<span class="text-muted">—</span>' ?></td>
              <td><span class="badge text-bg-secondary"><?= (int) $c['vehicle_count'] ?></span></td>
              <td class="text-muted small"><?= format_date($c['created_at']) ?></td>
              <td class="bb-actions">
                <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('customers/view.php?id=' . (int) $c['id']) ?>" aria-label="View <?= e($c['name']) ?>" title="View"><i class="bi bi-eye" aria-hidden="true"></i></a>
                <a class="btn btn-sm btn-outline-primary" href="<?= base_url('customers/form.php?id=' . (int) $c['id']) ?>" aria-label="Edit <?= e($c['name']) ?>" title="Edit"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                <form method="post" action="<?= base_url('customers/delete.php') ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Delete <?= e($c['name']) ?>" title="Delete"
                          data-confirm="Delete customer <?= e($c['name']) ?>? This will also delete their vehicles and job history.">
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
