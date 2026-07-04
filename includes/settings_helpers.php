<?php
/**
 * System settings helpers (Milestone 5).
 * Key/value storage designed so a future milestone can add optional online sync.
 */

const DEFAULT_SETTINGS = [
    'garage_name'       => 'BrightBlaze Garage',
    'business_phone'    => '',
    'business_email'    => '',
    'business_address'  => '',
    'currency'          => 'KWD',
    'installation_mode' => 'local',
    'sync_mode'         => 'local_only',
    'cloud_api_url'     => '',
    'sync_api_key'      => '',
    'last_sync_at'      => '',
    'sync_status'       => 'not_configured',
];

const INSTALLATION_MODES = [
    'local'      => 'Local (this computer only)',
    'shared_lan' => 'Shared on local network (LAN)',
];

const SYNC_MODES = [
    'local_only'  => 'Local Only',
    'online_sync' => 'Online Sync Enabled',
];

/**
 * All settings merged over defaults. Falls back to defaults if the
 * settings table does not exist yet (migration not run).
 */
function get_settings(): array
{
    $settings = DEFAULT_SETTINGS;
    try {
        $rows = db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
        foreach ($rows as $row) {
            if (array_key_exists($row['setting_key'], $settings)) {
                $settings[$row['setting_key']] = (string) ($row['setting_value'] ?? '');
            }
        }
    } catch (PDOException $e) {
        // Settings table missing: run database/migrations/m5_settings.sql.
    }
    return $settings;
}

function get_setting(string $key, string $default = ''): string
{
    $settings = get_settings();
    return $settings[$key] ?? $default;
}

/**
 * Persist settings (insert or update). updated_at is maintained by MySQL,
 * which keeps the data ready for future sync/conflict handling.
 */
function save_settings(array $pairs): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    foreach ($pairs as $key => $value) {
        $stmt->execute([$key, $value]);
    }
}
