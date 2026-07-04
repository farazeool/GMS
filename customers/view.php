<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';

require_role('admin');

$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT * FROM customers WHERE id = ?');
$stmt->execute([$id]);
$customer = $stmt->fetch();

if (!$customer) {
    set_flash('danger', 'Customer not found.');
    header('Location: ' . base_url('customers/index.php'));
    exit;
}

$stmt = db()->prepare('SELECT * FROM vehicles WHERE customer_id = ? ORDER BY created_at DESC');
$stmt->execute([$id]);
$vehicles = $stmt->fetchAll();

$page_title = $customer['name'];
$active = 'customers';

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <h4 class="fw-bold mb-0"><i class="bi bi-person-circle bb-text-orange"></i> <?= e($customer['name']) ?></h4>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-bb" href="<?= base_url('vehicles/form.php?customer_id=' . $id) ?>"><i class="bi bi-plus-lg"></i> Add Vehicle</a>
    <a class="btn btn-bb-orange" href="<?= base_url('customers/form.php?id=' . $id) ?>"><i class="bi bi-pencil"></i> Edit</a>
    <a class="btn btn-outline-secondary" href="<?= base_url('customers/index.php') ?>"><i class="bi bi-arrow-left"></i> Back</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold">Customer Details</div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-4">Phone</dt><dd class="col-8"><?= e($customer['phone']) ?></dd>
          <dt class="col-4">Email</dt><dd class="col-8"><?= e($customer['email'] ?? '') ?: '—' ?></dd>
          <dt class="col-4">Address</dt><dd class="col-8"><?= e($customer['address'] ?? '') ?: '—' ?></dd>
          <dt class="col-4">Created</dt><dd class="col-8 mb-0"><?= format_date($customer['created_at'], 'd M Y H:i') ?></dd>
        </dl>
      </div>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header bg-white fw-bold">Vehicles <span class="badge text-bg-secondary"><?= count($vehicles) ?></span></div>
      <div class="card-body">
        <?php if (!$vehicles): ?>
          <p class="text-muted mb-0">No vehicles registered for this customer yet.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr><th>Plate</th><th>Vehicle</th><th>Year</th><th>Color</th><th>Registered</th><th class="text-end">Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($vehicles as $v): ?>
                  <tr>
                    <td class="fw-semibold"><?= e($v['plate_number']) ?></td>
                    <td><?= e($v['make'] . ' ' . $v['model']) ?></td>
                    <td><?= e((string) ($v['year'] ?? '')) ?: '—' ?></td>
                    <td><?= e($v['color'] ?? '') ?: '—' ?></td>
                    <td class="text-muted small"><?= format_date($v['created_at']) ?></td>
                    <td class="text-end text-nowrap">
                      <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('vehicles/view.php?id=' . (int) $v['id']) ?>" title="View"><i class="bi bi-eye"></i></a>
                      <a class="btn btn-sm btn-outline-primary" href="<?= base_url('vehicles/form.php?id=' . (int) $v['id']) ?>" title="Edit"><i class="bi bi-pencil"></i></a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
