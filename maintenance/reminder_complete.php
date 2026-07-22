<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_role('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . base_url('maintenance/reminders.php')); exit; }
verify_csrf();
db()->prepare('UPDATE service_reminders SET status = ?, next_due_odometer = CASE WHEN reminder_type="mileage" THEN next_due_odometer + interval_value ELSE next_due_odometer END, next_due_date = CASE WHEN reminder_type="months" THEN DATE_ADD(next_due_date, INTERVAL interval_value MONTH) ELSE next_due_date END WHERE id = ?')->execute(['completed', (int)$_POST['id']]);
set_flash('success', 'Reminder marked complete.');
header('Location: ' . base_url('maintenance/reminders.php'));