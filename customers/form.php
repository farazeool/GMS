<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';

require_role('admin');

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;

$customer = ['name' => '', 'phone' => '', 'email' => '', 'address' => ''];

if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM customers WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        set_flash('danger', 'Customer not found.');
        header('Location: ' . base_url('customers/index.php'));
        exit;
    }
    $customer = $existing;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $customer['name']    = trim($_POST['name'] ?? '');
    $customer['phone']   = trim($_POST['phone'] ?? '');
    $customer['email']   = trim($_POST['email'] ?? '');
    $customer['address'] = trim($_POST['address'] ?? '');

    if ($customer['name'] === '') {
        $errors[] = 'Full name is required.';
    }
    if ($customer['phone'] === '') {
        $errors[] = 'Phone number is required.';
    }
    if ($customer['email'] !== '' && !filter_var($customer['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email address is not valid.';
    }

    if (!$errors) {
        if ($isEdit) {
            $stmt = db()->prepare('UPDATE customers SET name = ?, phone = ?, email = ?, address = ? WHERE id = ?');
            $stmt->execute([$customer['name'], $customer['phone'], $customer['email'] !== '' ? $customer['email'] : null, $customer['address'] !== '' ? $customer['address'] : null, $id]);
            set_flash('success', 'Customer updated successfully.');
        } else {
            $stmt = db()->prepare('INSERT INTO customers (name, phone, email, address) VALUES (?, ?, ?, ?)');
            $stmt->execute([$customer['name'], $customer['phone'], $customer['email'] !== '' ? $customer['email'] : null, $customer['address'] !== '' ? $customer['address'] : null]);
            $id = (int) db()->lastInsertId();
            set_flash('success', 'Customer added successfully.');
        }
        header('Location: ' . base_url('customers/view.php?id=' . $id));
        exit;
    }
}

$page_title = $isEdit ? 'Edit Customer' : 'Add Customer';
$active = 'customers';

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><?= $isEdit ? 'Edit Customer' : 'Add Customer' ?></h4>
  <a class="btn btn-outline-secondary" href="<?= base_url('customers/index.php') ?>"><i class="bi bi-arrow-left"></i> Back to Customers</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <strong>Please fix the following:</strong>
    <ul class="mb-0">
      <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card" style="max-width: 720px;">
  <div class="card-body p-4">
    <form method="post" action="<?= base_url('customers/form.php' . ($isEdit ? '?id=' . $id : '')) ?>">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="name">Full Name <span class="text-danger">*</span></label>
          <input class="form-control" type="text" id="name" name="name" value="<?= e($customer['name']) ?>" maxlength="120" required>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="phone">Phone Number <span class="text-danger">*</span></label>
          <input class="form-control" type="text" id="phone" name="phone" value="<?= e($customer['phone']) ?>" maxlength="20" placeholder="+965 XXXX XXXX" required>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="email">Email</label>
          <input class="form-control" type="email" id="email" name="email" value="<?= e($customer['email'] ?? '') ?>" maxlength="120">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="address">Address</label>
          <input class="form-control" type="text" id="address" name="address" value="<?= e($customer['address'] ?? '') ?>" maxlength="255" placeholder="Block, street, area">
        </div>
      </div>
      <div class="mt-4 d-flex gap-2">
        <button class="btn btn-bb" type="submit"><i class="bi bi-check-lg"></i> <?= $isEdit ? 'Save Changes' : 'Add Customer' ?></button>
        <a class="btn btn-outline-secondary" href="<?= base_url('customers/index.php') ?>">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
