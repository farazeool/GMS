<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_role('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . base_url('inventory/categories.php')); exit; }
verify_csrf();
db()->prepare('DELETE FROM inventory_categories WHERE id = ?')->execute([(int)$_POST['id']]);
set_flash('success', 'Category deleted.');
header('Location: ' . base_url('inventory/categories.php'));