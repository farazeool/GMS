<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/settings_helpers.php';

require_role('admin');

$page_title = 'System Settings';
$active = 'settings';

$settings = get_settings();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $settings['garage_name']       = trim($_POST['garage_name'] ?? '');
    $settings['business_phone']    = trim($_POST['business_phone'] ?? '');
    $settings['business_email']    = trim($_POST['business_email'] ?? '');
    $settings['business_address']  = trim($_POST['business_address'] ?? '');
    $settings['currency']          = strtoupper(trim($_POST['currency'] ?? 'KWD'));
    $settings['installation_mode'] = $_POST['installation_mode'] ?? 'local';
    $settings['sync_mode']         = $_POST['sync_mode'] ?? 'local_only';
    $settings['cloud_api_url']     = trim($_POST['cloud_api_url'] ?? '');
    $settings['sync_api_key']      = trim($_POST['sync_api_key'] ?? '');

    if ($settings['garage_name'] === '') {
        $errors[] = 'Garage/business name is required.';
    }
    if ($settings['business_email'] !== '' && !filter_var($settings['business_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Business email is not valid.';
    }
    if (!preg_match('/^[A-Z]{3}$/', $settings['currency'])) {
        $errors[] = 'Currency must be a 3-letter code (e.g. KWD).';
    }
    if (!array_key_exists($settings['installation_mode'], INSTALLATION_MODES)) {
        $errors[] = 'Please select a valid installation mode.';
    }
    if (!array_key_exists($settings['sync_mode'], SYNC_MODES)) {
        $errors[] = 'Please select a valid sync mode.';
    }

    if ($settings['sync_mode'] === 'online_sync') {
        if ($settings['cloud_api_url'] === '' || !filter_var($settings['cloud_api_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'A valid Cloud API URL is required when online sync is enabled.';
        }
        if ($settings['sync_api_key'] === '') {
            $errors[] = 'A Sync API Key is required when online sync is enabled.';
        }
    }

    if (!$errors) {
        // Derive sync status; the sync engine itself arrives in a future milestone.
        if ($settings['sync_mode'] === 'local_only') {
            $settings['sync_status'] = 'local_only';
        } else {
            $settings['sync_status'] = 'configured_pending';
        }

        save_settings([
            'garage_name'       => $settings['garage_name'],
            'business_phone'    => $settings['business_phone'],
            'business_email'    => $settings['business_email'],
            'business_address'  => $settings['business_address'],
            'currency'          => $settings['currency'],
            'installation_mode' => $settings['installation_mode'],
            'sync_mode'         => $settings['sync_mode'],
            'cloud_api_url'     => $settings['cloud_api_url'],
            'sync_api_key'      => $settings['sync_api_key'],
            'sync_status'       => $settings['sync_status'],
        ]);
        set_flash('success', 'System settings saved.');
        header('Location: ' . base_url('admin/settings.php'));
        exit;
    }
}

$syncStatusLabels = [
    'not_configured'     => ['text-bg-secondary', 'Not configured'],
    'local_only'         => ['bg-bb-dark', 'Local Only'],
    'configured_pending' => ['text-bg-warning', 'Configured – sync engine coming in a future update'],
];
$statusInfo = $syncStatusLabels[$settings['sync_status']] ?? $syncStatusLabels['not_configured'];

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><i class="bi bi-gear bb-text-orange"></i> System Settings</h4>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <strong>Please fix the following:</strong>
    <ul class="mb-0">
      <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="post" action="<?= base_url('admin/settings.php') ?>">
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header bg-white fw-bold"><i class="bi bi-shop"></i> Garage Profile</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label" for="garage_name">Garage / Business Name <span class="text-danger">*</span></label>
            <input class="form-control" type="text" id="garage_name" name="garage_name" value="<?= e($settings['garage_name']) ?>" maxlength="120" required>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="business_phone">Business Phone</label>
              <input class="form-control" type="text" id="business_phone" name="business_phone" value="<?= e($settings['business_phone']) ?>" maxlength="20" placeholder="+965 XXXX XXXX">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="business_email">Business Email</label>
              <input class="form-control" type="email" id="business_email" name="business_email" value="<?= e($settings['business_email']) ?>" maxlength="120">
            </div>
            <div class="col-md-8">
              <label class="form-label" for="business_address">Business Address</label>
              <input class="form-control" type="text" id="business_address" name="business_address" value="<?= e($settings['business_address']) ?>" maxlength="255" placeholder="Block, street, area, Kuwait">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="currency">Default Currency</label>
              <input class="form-control" type="text" id="currency" name="currency" value="<?= e($settings['currency']) ?>" maxlength="3" pattern="[A-Za-z]{3}" required>
              <div class="form-text">3-letter code, default KWD.</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header bg-white fw-bold"><i class="bi bi-cloud-arrow-up"></i> Installation &amp; Sync</div>
        <div class="card-body">
          <div class="alert alert-light border small">
            <i class="bi bi-info-circle"></i> Online backup/sync is planned for a future update. These settings prepare the system for it; no data leaves this installation yet.
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="installation_mode">Installation Mode</label>
              <select class="form-select" id="installation_mode" name="installation_mode">
                <?php foreach (INSTALLATION_MODES as $value => $label): ?>
                  <option value="<?= e($value) ?>" <?= $settings['installation_mode'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="sync_mode">Sync Mode</label>
              <select class="form-select" id="sync_mode" name="sync_mode">
                <?php foreach (SYNC_MODES as $value => $label): ?>
                  <option value="<?= e($value) ?>" <?= $settings['sync_mode'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label" for="cloud_api_url">Cloud API URL</label>
              <input class="form-control" type="url" id="cloud_api_url" name="cloud_api_url" value="<?= e($settings['cloud_api_url']) ?>" maxlength="255" placeholder="https://api.example.com/brightblaze">
            </div>
            <div class="col-12">
              <label class="form-label" for="sync_api_key">Sync API Key</label>
              <input class="form-control" type="password" id="sync_api_key" name="sync_api_key" value="<?= e($settings['sync_api_key']) ?>" maxlength="255" autocomplete="off">
            </div>
            <div class="col-md-6">
              <label class="form-label">Last Sync</label>
              <div class="form-control-plaintext fw-semibold">
                <?= $settings['last_sync_at'] !== '' ? e($settings['last_sync_at']) : 'Never' ?>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Sync Status</label>
              <div><span class="badge <?= e($statusInfo[0]) ?>"><?= e($statusInfo[1]) ?></span></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-3 d-flex gap-2">
    <button class="btn btn-bb" type="submit"><i class="bi bi-check-lg"></i> Save Settings</button>
  </div>
</form>

<script>
// Enable the cloud fields only when online sync is selected.
document.addEventListener('DOMContentLoaded', function () {
  var syncMode = document.getElementById('sync_mode');
  var apiUrl = document.getElementById('cloud_api_url');
  var apiKey = document.getElementById('sync_api_key');
  if (!syncMode || !apiUrl || !apiKey) { return; }

  function toggleCloudFields() {
    var online = syncMode.value === 'online_sync';
    apiUrl.disabled = !online;
    apiKey.disabled = !online;
  }

  syncMode.addEventListener('change', toggleCloudFields);
  toggleCloudFields();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
