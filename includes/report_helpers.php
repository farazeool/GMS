<?php
/**
 * Report filter parsing, querying, summaries, and logging (Milestone 4).
 * Require after config/config.php and includes/session.php.
 */

require_once __DIR__ . '/job_helpers.php';

const REPORT_PRESETS = ['completed', 'active'];

/**
 * Read and sanitize report filters from the query string.
 */
function report_filters_from_get(): array
{
    $f = [
        'start'      => trim($_GET['start'] ?? ''),
        'end'        => trim($_GET['end'] ?? ''),
        'status'     => $_GET['status'] ?? '',
        'technician' => (int) ($_GET['technician'] ?? 0),
        'category'   => $_GET['category'] ?? '',
        'priority'   => $_GET['priority'] ?? '',
        'customer'   => (int) ($_GET['customer'] ?? 0),
        'plate'      => trim($_GET['plate'] ?? ''),
        'preset'     => $_GET['preset'] ?? '',
    ];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['start'])) {
        $f['start'] = '';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['end'])) {
        $f['end'] = '';
    }
    if (!in_array($f['status'], JOB_STATUSES, true)) {
        $f['status'] = '';
    }
    if (!in_array($f['category'], SERVICE_CATEGORIES, true)) {
        $f['category'] = '';
    }
    if (!in_array($f['priority'], JOB_PRIORITIES, true)) {
        $f['priority'] = '';
    }
    if (!in_array($f['preset'], REPORT_PRESETS, true)) {
        $f['preset'] = '';
    }

    return $f;
}

/**
 * Fetch job card rows matching the report filters.
 */
function report_rows(array $f): array
{
    $sql = 'SELECT jc.*, c.name AS customer_name, v.plate_number, u.full_name AS technician_name
            FROM job_cards jc
            JOIN customers c ON c.id = jc.customer_id
            JOIN vehicles v ON v.id = jc.vehicle_id
            LEFT JOIN users u ON u.id = jc.technician_id
            WHERE 1 = 1';
    $params = [];

    if ($f['preset'] === 'completed') {
        $sql .= " AND jc.status = 'Completed'";
    } elseif ($f['preset'] === 'active') {
        $sql .= " AND jc.status IN ('Pending', 'Assigned', 'In Progress')";
    }

    if ($f['start'] !== '') {
        $sql .= ' AND DATE(jc.created_at) >= ?';
        $params[] = $f['start'];
    }
    if ($f['end'] !== '') {
        $sql .= ' AND DATE(jc.created_at) <= ?';
        $params[] = $f['end'];
    }
    if ($f['status'] !== '') {
        $sql .= ' AND jc.status = ?';
        $params[] = $f['status'];
    }
    if ($f['technician'] > 0) {
        $sql .= ' AND jc.technician_id = ?';
        $params[] = $f['technician'];
    }
    if ($f['category'] !== '') {
        $sql .= ' AND jc.service_category = ?';
        $params[] = $f['category'];
    }
    if ($f['priority'] !== '') {
        $sql .= ' AND jc.priority = ?';
        $params[] = $f['priority'];
    }
    if ($f['customer'] > 0) {
        $sql .= ' AND jc.customer_id = ?';
        $params[] = $f['customer'];
    }
    if ($f['plate'] !== '') {
        $sql .= ' AND v.plate_number LIKE ?';
        $params[] = '%' . $f['plate'] . '%';
    }

    $sql .= ' ORDER BY jc.created_at DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Compute summary figures for a set of report rows.
 */
function report_summary(array $rows): array
{
    $summary = [
        'total'          => count($rows),
        'completed'      => 0,
        'pending'        => 0,
        'assigned'       => 0,
        'in_progress'    => 0,
        'cancelled'      => 0,
        'high'           => 0,
        'top_technician' => null,
        'top_category'   => null,
    ];

    $techCounts = [];
    $catCounts = [];

    foreach ($rows as $row) {
        switch ($row['status']) {
            case 'Completed':   $summary['completed']++;   break;
            case 'Pending':     $summary['pending']++;     break;
            case 'Assigned':    $summary['assigned']++;    break;
            case 'In Progress': $summary['in_progress']++; break;
            case 'Cancelled':   $summary['cancelled']++;   break;
        }
        if ($row['priority'] === 'High') {
            $summary['high']++;
        }
        if (!empty($row['technician_name'])) {
            $techCounts[$row['technician_name']] = ($techCounts[$row['technician_name']] ?? 0) + 1;
        }
        $catCounts[$row['service_category']] = ($catCounts[$row['service_category']] ?? 0) + 1;
    }

    if ($techCounts) {
        arsort($techCounts);
        $summary['top_technician'] = array_key_first($techCounts) . ' (' . reset($techCounts) . ' jobs)';
    }
    if ($catCounts) {
        arsort($catCounts);
        $summary['top_category'] = array_key_first($catCounts) . ' (' . reset($catCounts) . ' jobs)';
    }

    return $summary;
}

/**
 * Log a generated/exported report into report_logs.
 */
function log_report(string $type, array $filters): void
{
    $stmt = db()->prepare('INSERT INTO report_logs (user_id, report_type, filters) VALUES (?, ?, ?)');
    $stmt->execute([current_user_id(), $type, json_encode(array_filter($filters))]);
}
