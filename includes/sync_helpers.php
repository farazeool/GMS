<?php
/**
 * Milestone 7 sync helpers.
 * Real HTTP sync attempt with safe fallback to Local Only mode.
 */

require_once __DIR__ . '/settings_helpers.php';

const SYNC_TRACKED_TABLES = ['customers', 'vehicles', 'job_cards', 'service_notes', 'maintenance_records'];
const SYNC_STATUSES = ['pending', 'synced', 'failed'];

function sync_schema_ready(): bool
{
    try {
        db()->query('SELECT sync_status, synced_at, local_updated_at FROM customers LIMIT 1');
        db()->query('SELECT id FROM sync_logs LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function sync_is_configured(?array $settings = null): bool
{
    $settings = $settings ?? get_settings();
    return $settings['sync_mode'] === 'online_sync'
        && $settings['cloud_api_url'] !== ''
        && filter_var($settings['cloud_api_url'], FILTER_VALIDATE_URL)
        && $settings['sync_api_key'] !== '';
}

function sync_masked_key(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return 'Not set';
    }
    $len = strlen($value);
    if ($len <= 6) {
        return str_repeat('•', $len);
    }
    return substr($value, 0, 3) . str_repeat('•', max(3, $len - 6)) . substr($value, -3);
}

function sync_mark_record_dirty(string $table, int $id): void
{
    if ($id <= 0 || !in_array($table, SYNC_TRACKED_TABLES, true)) {
        return;
    }
    try {
        $sql = "UPDATE {$table} SET sync_status = 'pending', synced_at = NULL, local_updated_at = NOW() WHERE id = ?";
        $stmt = db()->prepare($sql);
        $stmt->execute([$id]);
    } catch (PDOException $e) {
        // Sync columns may not exist yet if migration was not run.
    }
}

function sync_log(string $level, string $message, array $context = []): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO sync_logs (level, message, context_json, created_by) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            substr($level, 0, 20),
            substr($message, 0, 500),
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
            current_user_id() ?: null,
        ]);
    } catch (PDOException $e) {
        // Ignore if sync logs table does not exist yet.
    }
}

function sync_fetch_rows(string $table, int $limit = 50): array
{
    if (!in_array($table, SYNC_TRACKED_TABLES, true)) {
        return [];
    }
    $limit = max(1, min($limit, 500));
    $sql = "SELECT * FROM {$table} WHERE sync_status IN ('pending', 'failed') ORDER BY local_updated_at ASC, id ASC LIMIT {$limit}";
    return db()->query($sql)->fetchAll();
}

function sync_payload(): array
{
    $payload = [
        'source' => 'brightblaze_local',
        'sent_at' => gmdate('c'),
        'entities' => [],
    ];

    foreach (SYNC_TRACKED_TABLES as $table) {
        $rows = sync_fetch_rows($table, 100);
        $payload['entities'][$table] = array_map(
            static function (array $row): array {
                unset($row['sync_status'], $row['synced_at']);
                $row['local_updated_at'] = $row['local_updated_at'] ?? null;
                return $row;
            },
            $rows
        );
    }

    return $payload;
}

function sync_attempt_http(string $url, string $apiKey, array $payload): array
{
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL extension is not enabled in PHP.'];
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'error' => 'Failed to initialize cURL.'];
    }

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Sync-Api-Key: ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
    ]);

    $responseBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($responseBody === false) {
        return ['ok' => false, 'error' => 'Sync request failed: ' . $curlError];
    }
    if ($statusCode < 200 || $statusCode >= 300) {
        return ['ok' => false, 'error' => 'Sync request returned HTTP ' . $statusCode . '.'];
    }

    $decoded = json_decode((string) $responseBody, true);
    if ($decoded === null && trim((string) $responseBody) !== 'null') {
        return ['ok' => false, 'error' => 'Cloud API returned invalid JSON.'];
    }

    return ['ok' => true, 'response' => $decoded, 'status_code' => $statusCode];
}

function sync_mark_batch(array $payload, string $status): void
{
    if (!in_array($status, SYNC_STATUSES, true)) {
        return;
    }
    foreach (SYNC_TRACKED_TABLES as $table) {
        $rows = $payload['entities'][$table] ?? [];
        if (!$rows) {
            continue;
        }
        $stmt = db()->prepare(
            "UPDATE {$table}
             SET sync_status = ?, synced_at = ?, local_updated_at = CASE WHEN ? = 'synced' THEN local_updated_at ELSE NOW() END
             WHERE id = ?"
        );
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $syncedAt = $status === 'synced' ? date('Y-m-d H:i:s') : null;
            $stmt->execute([$status, $syncedAt, $status, $id]);
        }
    }
}

function sync_has_pending(array $payload): bool
{
    foreach (SYNC_TRACKED_TABLES as $table) {
        if (!empty($payload['entities'][$table])) {
            return true;
        }
    }
    return false;
}

function sync_run_now(): array
{
    if (!sync_schema_ready()) {
        return ['ok' => false, 'message' => 'Sync schema is missing. Run database/migrations/m6_sync_engine.sql first.'];
    }

    $settings = get_settings();
    if (!sync_is_configured($settings)) {
        save_settings([
            'sync_status' => 'local_only',
            'last_sync_at' => '',
        ]);
        sync_log('info', 'Sync skipped because system is in Local Only mode or cloud API is not configured.');
        return ['ok' => false, 'message' => 'Local Only mode is active or cloud API settings are incomplete.'];
    }

    $payload = sync_payload();
    if (!sync_has_pending($payload)) {
        save_settings([
            'sync_status' => 'synced_idle',
            'last_sync_at' => date('Y-m-d H:i:s'),
        ]);
        sync_log('info', 'Sync run completed: no pending records.');
        return ['ok' => true, 'message' => 'No pending records to sync.'];
    }

    $result = sync_attempt_http($settings['cloud_api_url'], $settings['sync_api_key'], $payload);

    if (!$result['ok']) {
        sync_mark_batch($payload, 'failed');
        save_settings(['sync_status' => 'failed']);
        sync_log('error', 'Sync failed.', ['error' => $result['error']]);
        return ['ok' => false, 'message' => $result['error']];
    }

    sync_mark_batch($payload, 'synced');
    save_settings([
        'sync_status' => 'synced',
        'last_sync_at' => date('Y-m-d H:i:s'),
    ]);
    sync_log('info', 'Sync completed successfully.');

    return ['ok' => true, 'message' => 'Sync completed successfully.'];
}

function sync_dashboard_counts(): array
{
    if (!sync_schema_ready()) {
        return [];
    }

    $counts = [];
    foreach (SYNC_TRACKED_TABLES as $table) {
        $stmt = db()->query(
            "SELECT
                SUM(sync_status = 'pending') AS pending_count,
                SUM(sync_status = 'failed') AS failed_count,
                SUM(sync_status = 'synced') AS synced_count
             FROM {$table}"
        );
        $row = $stmt->fetch() ?: [];
        $counts[$table] = [
            'pending' => (int) ($row['pending_count'] ?? 0),
            'failed' => (int) ($row['failed_count'] ?? 0),
            'synced' => (int) ($row['synced_count'] ?? 0),
        ];
    }
    return $counts;
}

function sync_recent_logs(int $limit = 30): array
{
    if (!sync_schema_ready()) {
        return [];
    }

    $limit = max(1, min($limit, 200));
    $stmt = db()->query(
        "SELECT sl.*, u.full_name
         FROM sync_logs sl
         LEFT JOIN users u ON u.id = sl.created_by
         ORDER BY sl.id DESC
         LIMIT {$limit}"
    );
    return $stmt->fetchAll();
}
