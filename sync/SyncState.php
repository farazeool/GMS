<?php
/**
 * BrightBlaze – Sync State Tracker
 * Persistent key/value store for synchronization state.
 */

class SyncState
{
    private PDO $pdo;

    /**
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get a state value by key.
     */
    public function get(string $key): string
    {
        try {
            $stmt = $this->pdo->prepare("SELECT `key_value` FROM `sync_state` WHERE `key_name` = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (string) $row['key_value'] : '';
        } catch (PDOException $e) {
            return '';
        }
    }

    /**
     * Set a state value by key.
     */
    public function set(string $key, string $value): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO `sync_state` (`key_name`, `key_value`) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE `key_value` = VALUES(`key_value`)"
            );
            $stmt->execute([$key, $value]);
        } catch (PDOException $e) {
            // Non-fatal
        }
    }

    /**
     * Increment a numeric state value.
     */
    public function increment(string $key): int
    {
        $current = (int) $this->get($key);
        $new = $current + 1;
        $this->set($key, (string) $new);
        return $new;
    }

    /**
     * Get all state values.
     */
    public function getAll(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT `key_name`, `key_value` FROM `sync_state`");
            $result = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $result[$row['key_name']] = $row['key_value'];
            }
            return $result;
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Reset sync state to initial values.
     */
    public function reset(): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE `sync_state` SET `key_value` = '' WHERE `key_name` IN ('last_sync_at', 'last_push_at', 'last_pull_at')"
            );
            $stmt->execute();
            $this->set('sync_version', '1');
            $this->set('sync_mode', 'local_only');
        } catch (PDOException $e) {
            // Non-fatal
        }
    }
}