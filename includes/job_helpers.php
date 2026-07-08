<?php
/**
 * Job card constants, helpers, and workflow rules (Milestone 3).
 * Require after config/config.php and includes/session.php.
 */

require_once __DIR__ . '/sync_helpers.php';

const JOB_STATUSES   = ['Pending', 'Assigned', 'In Progress', 'Completed', 'Cancelled'];
const JOB_PRIORITIES = ['Low', 'Medium', 'High'];
const SERVICE_CATEGORIES = [
    'General Service',
    'Engine Repair',
    'AC Repair',
    'Electrical',
    'Brakes & Suspension',
    'Bodywork & Paint',
    'Tyres & Alignment',
    'Diagnostics',
];

/** Status transitions technicians may perform: current status => allowed new statuses. */
const TECH_STATUS_TRANSITIONS = [
    'Assigned'    => ['In Progress'],
    'In Progress' => ['Completed'],
];

/**
 * Generate the next readable job number, e.g. JC-2026-0011.
 */
function generate_job_number(): string
{
    $prefix = 'JC-' . date('Y') . '-';

    $stmt = db()->prepare('SELECT job_number FROM job_cards WHERE job_number LIKE ? ORDER BY job_number DESC LIMIT 1');
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $seq = $last ? ((int) substr((string) $last, strlen($prefix))) + 1 : 1;

    // Guard against collisions (e.g. manual inserts).
    $check = db()->prepare('SELECT COUNT(*) FROM job_cards WHERE job_number = ?');
    do {
        $number = sprintf('%s%04d', $prefix, $seq);
        $check->execute([$number]);
        $exists = (int) $check->fetchColumn() > 0;
        $seq++;
    } while ($exists);

    return $number;
}

/**
 * Fetch a job card with customer, vehicle, and technician details.
 */
function get_job(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT jc.*, c.name AS customer_name, c.phone AS customer_phone,
                v.plate_number, v.make, v.model, v.year AS vehicle_year, v.color AS vehicle_color,
                u.full_name AS technician_name
         FROM job_cards jc
         JOIN customers c ON c.id = jc.customer_id
         JOIN vehicles v ON v.id = jc.vehicle_id
         LEFT JOIN users u ON u.id = jc.technician_id
         WHERE jc.id = ?'
    );
    $stmt->execute([$id]);
    $job = $stmt->fetch();
    return $job ?: null;
}

/**
 * Admins can access any job card; technicians only their own.
 */
function can_access_job(array $job): bool
{
    return is_admin() || (int) ($job['technician_id'] ?? 0) === current_user_id();
}

/**
 * Active users with the technician role.
 */
function technicians_list(): array
{
    return db()->query(
        "SELECT u.id, u.full_name
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE r.name = 'technician' AND u.is_active = 1
         ORDER BY u.full_name"
    )->fetchAll();
}

function job_note_count(int $jobId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM service_notes WHERE job_card_id = ?');
    $stmt->execute([$jobId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Apply a status change with workflow rules:
 * - Assigned requires a technician.
 * - Completed requires at least one service note; sets completed_at (kept if already set)
 *   and creates/updates the linked maintenance record.
 * - Any non-Completed status clears completed_at; leaving Completed removes the
 *   auto-created maintenance record.
 *
 * Returns an error message on failure, or null on success.
 */
function apply_status_change(array $job, string $newStatus): ?string
{
    if (!in_array($newStatus, JOB_STATUSES, true)) {
        return 'Invalid status.';
    }
    if ($newStatus === 'Assigned' && empty($job['technician_id'])) {
        return 'Assign a technician before setting the status to Assigned.';
    }
    if ($newStatus === 'Completed' && job_note_count((int) $job['id']) === 0) {
        return 'Add at least one service note before marking this job card as Completed.';
    }

    if ($newStatus === 'Completed') {
        $stmt = db()->prepare('UPDATE job_cards SET status = ?, completed_at = COALESCE(completed_at, NOW()) WHERE id = ?');
        $stmt->execute([$newStatus, $job['id']]);
        sync_mark_record_dirty('job_cards', (int) $job['id']);
        sync_completion_maintenance((int) $job['id']);
    } else {
        $stmt = db()->prepare('UPDATE job_cards SET status = ?, completed_at = NULL WHERE id = ?');
        $stmt->execute([$newStatus, $job['id']]);
        sync_mark_record_dirty('job_cards', (int) $job['id']);
        if (($job['status'] ?? '') === 'Completed') {
            $stmt = db()->prepare('DELETE FROM maintenance_records WHERE job_card_id = ?');
            $stmt->execute([$job['id']]);
        }
    }

    return null;
}

/**
 * Create or update the maintenance record linked to a completed job card.
 */
function sync_completion_maintenance(int $jobId): void
{
    $job = get_job($jobId);
    if (!$job) {
        return;
    }

    $description = $job['service_category'] . ': ' . mb_substr($job['problem_description'], 0, 180);

    $stmt = db()->prepare('SELECT id FROM maintenance_records WHERE job_card_id = ?');
    $stmt->execute([$jobId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = db()->prepare('UPDATE maintenance_records SET description = ?, service_date = CURDATE() WHERE id = ?');
        $stmt->execute([$description, $existing['id']]);
        sync_mark_record_dirty('maintenance_records', (int) $existing['id']);
    } else {
        $stmt = db()->prepare('INSERT INTO maintenance_records (vehicle_id, job_card_id, description, service_date) VALUES (?, ?, ?, CURDATE())');
        $stmt->execute([$job['vehicle_id'], $jobId, $description]);
        sync_mark_record_dirty('maintenance_records', (int) db()->lastInsertId());
    }
}
