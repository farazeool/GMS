<?php
/**
 * Role-based sidebar navigation. Set $active in each page before including the header.
 */

function bb_nav_item(string $key, string $href, string $icon, string $label): void
{
    global $active;
    $isActive = (isset($active) && $active === $key);
    $class = $isActive ? ' active' : '';
    $current = $isActive ? ' aria-current="page"' : '';
    echo '<a class="nav-link' . $class . '" href="' . base_url($href) . '"' . $current . '>'
        . '<i class="bi ' . $icon . '" aria-hidden="true"></i> ' . $label . '</a>' . PHP_EOL;
}
?>
<aside class="bb-sidebar d-flex flex-column p-3" id="bbSidebar">
  <div class="bb-brand mb-4">
    <i class="bi bi-fire" aria-hidden="true"></i> Bright<span>Blaze</span>
    <small class="d-block">Garage Management</small>
  </div>
  <nav class="nav flex-column gap-1" aria-label="Main navigation">
    <?php if (is_admin()): ?>
      <?php
      bb_nav_item('dashboard', 'admin/dashboard.php', 'bi-speedometer2', 'Dashboard');
      bb_nav_item('customers', 'customers/index.php', 'bi-people', 'Customers');
      bb_nav_item('vehicles', 'vehicles/index.php', 'bi-car-front', 'Vehicle Registry');
      bb_nav_item('job_cards', 'job_cards/index.php', 'bi-card-checklist', 'Job Cards');
      bb_nav_item('maintenance', 'maintenance/index.php', 'bi-tools', 'Maintenance Records');
      bb_nav_item('reports', 'reports/index.php', 'bi-graph-up', 'Reports');
      bb_nav_item('users', 'users/index.php', 'bi-person-gear', 'Users &amp; Roles');
      bb_nav_item('settings', 'admin/settings.php', 'bi-gear', 'System Settings');
      ?>
    <?php else: ?>
      <?php
      bb_nav_item('dashboard', 'technician/dashboard.php', 'bi-speedometer2', 'Dashboard');
      bb_nav_item('job_cards', 'job_cards/index.php', 'bi-card-checklist', 'My Job Cards');
      ?>
    <?php endif; ?>
  </nav>
  <div class="mt-auto bb-user pt-3">
    <div class="small">
      <?= e(current_user_name()) ?><br>
      <span class="bb-role text-uppercase"><?= e(current_role()) ?></span>
    </div>
    <a class="btn btn-sm btn-outline-light mt-2 w-100" href="<?= base_url('auth/logout.php') ?>">
      <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Sign out
    </a>
  </div>
</aside>
