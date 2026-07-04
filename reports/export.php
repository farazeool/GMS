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

$out = fopen('php://output', 'w');

// UTF-8 BOM so Excel opens the file correctly.
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'Job #', 'Customer', 'Vehicle Plate', 'Service Category', 'Technician',
    'Priority', 'Status', 'Created', 'Estimated Completion', 'Completed',
]);

foreach ($rows as $row) {
    fputcsv($out, [
        $row['job_number'],
        $row['customer_name'],
        $row['plate_number'],
        $row['service_category'],
        $row['technician_name'] ?? 'Unassigned',
        $row['priority'],
        $row['status'],
        date('Y-m-d H:i', strtotime($row['created_at'])),
        $row['estimated_completion'] ?? '',
        $row['completed_at'] ? date('Y-m-d H:i', strtotime($row['completed_at'])) : '',
    ]);
}

fclose($out);
exit;
