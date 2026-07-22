<?php
/**
 * BrightBlaze – Durable Sync Queue
 * Manages the sync_queue table for pending, syncing, completed, failed,
 * and retry_scheduled operations.
 */

class SyncQueue
{
    private PDO $pdo;

    // Max attempts before permanent failure
    private const MAX_RETRY_ATTEMPTS = 10;
    // Base delay in minutes for exponential backoff (doubles each retry)
    private const RETRY_BASE_DELAY = 1;
    // Max delay cap in minutes
    private const RETRY_MAX_DELAY = 480; // 8 hours

    /**
     * Queue status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_SYNCING = 'syncing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_RETRY = 'retry_scheduled';

    /**
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Enqueue a sync operation.
     *
     * @param string $entityType Table name (customers, vehicles, etc.)
     * @param string $entityUuid UUID of the record
     * @param string $operation create | update | delete
     * @param array $payload Full record data
     * @return int Queue item ID
     */
    public function enqueue(string $entityType, string $entityUuid, string $operation, array $payload): int
    {
        // Prevent duplicate queue entries for the same entity+operation
        $existing = $this->findDuplicate($entityType, $entityUuid);
        if ($existing !== null) {
            // Update the payload of the existing pending item
            $this->updatePendingItem($existing, $payload);
            return $existing;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO `sync_queue` (`entity_type`, `entity_uuid`, `operation`, `payload`, `status`)
             VALUES (?, ?, ?, ?, 'pending')"
        );
        $stmt->execute([$entityType, $entityUuid, $operation, json_encode($payload)]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Get items pending processing.
     * Orders by scheduled_at ascending (retry scheduling).
     *
     * @param int $limit Max items to return
     * @return array List of pending items
     */
    public function getPendingItems(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT `id`, `uuid`, `entity_type`, `entity_uuid`, `operation`, `payload`,
                    `attempt_count`, `last_error`, `scheduled_at`
             FROM `sync_queue`
             WHERE (`status` = 'pending' OR `status` = 'retry_scheduled')
               AND `scheduled_at` <= NOW()
             ORDER BY `scheduled_at` ASC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mark an item as currently syncing.
     */
    public function markSyncing(int $itemId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE `sync_queue` SET
                `status` = 'syncing',
                `started_at` = NOW(),
                `attempt_count` = `attempt_count` + 1
             WHERE `id` = ?"
        );
        $stmt->execute([$itemId]);
    }

    /**
     * Mark an item as completed.
     */
    public function markCompleted(int $itemId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE `sync_queue` SET
                `status` = 'completed',
                `completed_at` = NOW()
             WHERE `id` = ?"
        );
        $stmt->execute([$itemId]);
    }

    /**
     * Mark an item as failed and schedule retry with exponential backoff.
     */
    public function markFailed(int $itemId, string $error = ''): void
    {
        // Get current attempt count
        $stmt = $this->pdo->prepare("SELECT `attempt_count` FROM `sync_queue` WHERE `id` = ?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            return;
        }

        $attempts = (int) $item['attempt_count'];
        $attempts++; // Already incremented by markSyncing

        if ($attempts >= self::MAX_RETRY_ATTEMPTS) {
            // Permanent failure
            $stmt = $this->pdo->prepare(
                "UPDATE `sync_queue` SET
                    `status` = 'failed',
                    `last_error` = ?,
                    `completed_at` = NOW()
                 WHERE `id` = ?"
            );
            $stmt->execute([$error, $itemId]);
        } else {
            // Schedule retry with exponential backoff
            $delayMinutes = min(
                self::RETRY_BASE_DELAY * pow(2, $attempts - 1),
                self::RETRY_MAX_DELAY
            );
            $delayMinutes += random_int(0, 5); // Jitter to avoid thundering herd

            $stmt = $this->pdo->prepare(
                "UPDATE `sync_queue` SET
                    `status` = 'retry_scheduled',
                    `last_error` = ?,
                    `scheduled_at` = DATE_ADD(NOW(), INTERVAL ? MINUTE)
                 WHERE `id` = ?"
            );
            $stmt->execute([$error, $delayMinutes, $itemId]);
        }
    }

    /**
     * Get queue statistics.
     */
    public function getStats(): array
    {
        $stats = [
            'pending' => 0,
            'syncing' => 0,
            'completed' => 0,
            'failed' => 0,
            'retry_scheduled' => 0,
        ];

        try {
            $stmt = $this->pdo->query(
                "SELECT `status`, COUNT(*) as `count` FROM `sync_queue` GROUP BY `status`"
            );
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (isset($stats[$row['status']])) {
                    $stats[$row['status']] = (int) $row['count'];
                }
            }
        } catch (PDOException $e) {
            // Queue table might not exist yet
        }

        return $stats;
    }

    /**
     * Get recent queue items for debugging/display.
     */
    public function getRecentItems(int $limit = 20, string $status = null): array
    {
        $sql = "SELECT `id`, `uuid`, `entity_type`, `entity_uuid`, `operation`, `status`,
                       `attempt_count`, `last_error`, `scheduled_at`, `created_at`, `completed_at`
                FROM `sync_queue`";

        if ($status !== null) {
            $stmt = $this->pdo->prepare("{$sql} WHERE `status` = ? ORDER BY `id` DESC LIMIT ?");
            $stmt->execute([$status, $limit]);
        } else {
            $stmt = $this->pdo->prepare("{$sql} ORDER BY `id` DESC LIMIT ?");
            $stmt->execute([$limit]);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Clear completed items older than specified days.
     */
    public function purgeCompleted(int $daysOld = 7): int
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM `sync_queue` WHERE `status` = 'completed' AND `completed_at` < DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $stmt->execute([$daysOld]);
        return $stmt->rowCount();
    }

    /**
     * Clean up stuck syncing items (recovery after crash).
     */
    public function recoverStuckItems(int $timeoutMinutes = 30): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE `sync_queue` SET
                `status` = 'pending',
                `last_error` = 'recovered_after_timeout'
             WHERE `status` = 'syncing'
               AND `started_at` < DATE_SUB(NOW(), INTERVAL ? MINUTE)"
        );
        $stmt->execute([$timeoutMinutes]);
        return $stmt->rowCount();
    }

    /**
     * Retry all failed items.
     */
    public function retryAllFailed(): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE `sync_queue` SET
                `status` = 'pending',
                `scheduled_at` = NOW()
             WHERE `status` = 'failed'"
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Cancel/remove a specific queue item.
     */
    public function cancelItem(int $itemId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM `sync_queue` WHERE `id` = ?");
        $stmt->execute([$itemId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Check for duplicate pending entry for the same entity+operation.
     * Returns the queue item ID if found, null otherwise.
     */
    private function findDuplicate(string $entityType, string $entityUuid): ?int
    {
        $stmt = $this->pdo->prepare(
            "SELECT `id` FROM `sync_queue`
             WHERE `entity_type` = ?
               AND `entity_uuid` = ?
               AND `status` IN ('pending', 'syncing', 'retry_scheduled')
             LIMIT 1"
        );
        $stmt->execute([$entityType, $entityUuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['id'] : null;
    }

    /**
     * Update the payload of an existing pending queue item.
     */
    private function updatePendingItem(int $itemId, array $payload): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE `sync_queue` SET `payload` = ? WHERE `id` = ?"
        );
        $stmt->execute([json_encode($payload), $itemId]);
    }
}