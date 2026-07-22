<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/report_helpers.php';

require_role('admin');

$f = report_filters_from_get();
$rows = report_rows($f);

log_report('csv_export', $f);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="brightblaze_report_' . date('Ymd_His') . '.csv"');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

$out = fopen('php://output', 'w');

fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'Job #', 'Customer', 'Vehicle Plate', 'Service Category', 'Technician',
    'Priority', 'Status', 'Created', 'Estimated Completion', 'Completed',
]);

foreach ($rows as $row) {
    $technician = $row['technician_name'] ?? 'Unassigned';
    if (preg_match('/^\s*[=\-+@\t\r\n]/', $technician)) {
        $technician = "'" . $technician;
    }
    $customer = $row['customer_name'] ?? '';
    if (preg_match('/^\s*[=\-+@\t\r\n]/', $customer)) {
        $customer = "'" . $customer;
    }
    $plate = $row['plate_number'] ?? '';
    if (preg_match('/^\s*[=\-+@\t\r\n]/', $plate)) {
        $plate = "'" . $plate;
    }

    fputcsv($out, [
        $row['job_number'],
        $customer,
        $plate,
        $row['service_category'],
        $technician,
        $row['priority'],
        $row['status'],
        date('Y-m-d H:i', strtotime($row['created_at'])),
        $row['estimated_completion'] ?? '',
        $row['completed_at'] ? date('Y-m-d H:i', strtotime($row['completed_at'])) : '',
    ]);
}

fclose($out);
exit;
