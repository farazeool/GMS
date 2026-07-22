<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_role('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . base_url('inventory/suppliers.php')); exit; }
verify_csrf();
db()->prepare('DELETE FROM inventory_suppliers WHERE id = ?')->execute([(int)$_POST['id']]);
set_flash('success', 'Supplier deleted.');
header('Location: ' . base_url('inventory/suppliers.php'));