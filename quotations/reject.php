<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/commercial.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . base_url('quotations/index.php')); exit; }
verify_csrf();

$id = (int)($_POST['id'] ?? 0);
db()->prepare('UPDATE quotations SET status = ? WHERE id = ? AND status = ?')
    ->execute(['rejected', $id, 'draft']);
set_flash('success', 'Quotation rejected.');
header('Location: ' . base_url('quotations/view.php?id=' . $id));