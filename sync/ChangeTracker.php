<?php
/**
 * BrightBlaze – Change Tracker
 * Automatically tracks changes to syncable entities and queues them for sync.
 */

class ChangeTracker
{
    private PDO $pdo;
    private SyncQueue $queue;

    /**
     * Entities that support synchronization.
     */
    private const SYNCABLE_ENTITIES = [
        'customers' => ['table' => 'customers', 'uuid_field' => 'uuid'],
        'vehicles' => ['table' => 'vehicles', 'uuid_field' => 'uuid'],
        'job_cards' => ['table' => 'job_cards', 'uuid_field' => 'uuid'],
        'service_notes' => ['table' => 'service_notes', 'uuid_field' => 'uuid'],
        'maintenance_records' => ['table' => 'maintenance_records', 'uuid_field' => 'uuid'],
        'users' => ['table' => 'users', 'uuid_field' => 'uuid'],
        'settings' => ['table' => 'settings', 'uuid_field' => 'uuid'],
    ];

    /**
     * @param PDO $pdo Database connection
     * @param SyncQueue $queue Queue for sync operations
     */
    public function __construct(PDO $pdo, SyncQueue $queue)
    {
        $this->pdo = $pdo;
        $this->queue = $queue;
    }

    /**
     * Record a change to a synchronizable entity.
     *
     * @param string $entityType Entity type (customers, vehicles, etc.)
     * @param string $operation create | update | delete
     * @param int $recordId Local record ID
     * @return int|null Queue item ID or null if not tracked
     */
    public function trackChange(string $entityType, string $operation, int $recordId): ?int
    {
        if (!isset(self::SYNCABLE_ENTITIES[$entityType])) {
            return null;
        }

        $entityConfig = self::SYNCABLE_ENTITIES[$entityType];
        $table = $entityConfig['table'];
        $uuidField = $entityConfig['uuid_field'];

        try {
            // Fetch the current state of the record
            $stmt = $this->pdo->prepare(
                "SELECT * FROM `{$table}` WHERE `id` = ?"
            );
            $stmt->execute([$recordId]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($operation === 'delete') {
                // For soft delete, mark deleted_at
                $this->markDeleted($table, $recordId);
                $payload = ['id' => $recordId, 'deleted_at' => date('Y-m-d H:i:s')];
            } else {
                $payload = $record ?? ['id' => $recordId];
            }

            $entityUuid = $payload[$uuidField] ?? null;

            if (empty($entityUuid)) {
                // Generate UUID if missing
                $entityUuid = uuid_generate();
                $this->updateUuid($table, $recordId, $entityUuid, $uuidField);
                $payload[$uuidField] = $entityUuid;
            }

            // Update sync status on the record
            $this->markPending($table, $entityUuid);

            // Enqueue for sync
            $queueId = $this->queue->enqueue($entityType, $entityUuid, $operation, $payload);

            $this->logChange('info', "Tracked {$operation} on {$entityType} #{$recordId}", [
                'entity' => $entityType,
                'uuid' => $entityUuid,
                'operation' => $operation,
                'queue_id' => $queueId,
            ]);

            return $queueId;
        } catch (PDOException $e) {
            $this->logChange('error', "Failed to track change: " . $e->getMessage(), [
                'entity' => $entityType,
                'operation' => $operation,
                'record_id' => $recordId,
            ]);
            return null;
        }
    }

    /**
     * Mark a record as soft-deleted.
     */
    private function markDeleted(string $table, int $recordId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE `{$table}` SET
                `deleted_at` = NOW(),
                `sync_status` = 'pending'
             WHERE `id` = ? AND `deleted_at` IS NULL"
        );
        $stmt->execute([$recordId]);
    }

    /**
     * Mark a record as pending sync.
     */
    private function markPending(string $table, string $uuid): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE `{$table}` SET
                `sync_status` = 'pending',
                `sync_version` = `sync_version` + 1
             WHERE `uuid` = ?"
        );
        $stmt->execute([$uuid]);
    }

    /**
     * Update UUID for a record if it's missing.
     */
    private function updateUuid(string $table, int $recordId, string $uuid, string $uuidField): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE `{$table}` SET `{$uuidField}` = ? WHERE `id` = ? AND (`{$uuidField}` = '' OR `{$uuidField}` IS NULL)"
        );
        $stmt->execute([$uuid, $recordId]);
    }

    /**
     * Get all pending changes for a specific entity type.
     */
    public function getPendingChanges(string $entityType, int $limit = 100): array
    {
        if (!isset(self::SYNCABLE_ENTITIES[$entityType])) {
            return [];
        }

        $table = self::SYNCABLE_ENTITIES[$entityType]['table'];

        $stmt = $this->pdo->prepare(
            "SELECT * FROM `{$table}`
             WHERE `sync_status` = 'pending'
             ORDER BY `id` ASC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get change statistics for all entity types.
     */
    public function getChangeStats(): array
    {
        $stats = [];

        foreach (self::SYNCABLE_ENTITIES as $entityType => $config) {
            $table = $config['table'];
            try {
                $stmt = $this->pdo->query(
                    "SELECT `sync_status`, COUNT(*) as `count`
                     FROM `{$table}`
                     GROUP BY `sync_status`"
                );
                $entityStats = ['synced' => 0, 'pending' => 0, 'conflict' => 0];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $entityStats[$row['sync_status']] = (int) $row['count'];
                }
                $stats[$entityType] = $entityStats;
            } catch (PDOException $e) {
                $stats[$entityType] = ['synced' => 0, 'pending' => 0, 'conflict' => 0];
            }
        }

        return $stats;
    }

    /**
     * Get total count of pending changes across all entities.
     */
    public function getTotalPendingCount(): int
    {
        $total = 0;
        foreach (self::SYNCABLE_ENTITIES as $entityType => $config) {
            $table = $config['table'];
            try {
                $stmt = $this->pdo->query(
                    "SELECT COUNT(*) as `count` FROM `{$table}` WHERE `sync_status` = 'pending'"
                );
                $total += (int) $stmt->fetchColumn();
            } catch (PDOException $e) {
                // Skip
            }
        }
        return $total;
    }

    /**
     * Mark records as synced after successful sync.
     */
    public function markSynced(string $entityType, string $uuid): void
    {
        if (!isset(self::SYNCABLE_ENTITIES[$entityType])) {
            return;
        }

        $table = self::SYNCABLE_ENTITIES[$entityType]['table'];

        $stmt = $this->pdo->prepare(
            "UPDATE `{$table}` SET
                `sync_status` = 'synced',
                `last_synced_at` = NOW()
             WHERE `uuid` = ?"
        );
        $stmt->execute([$uuid]);
    }

    /**
     * Handle conflict detection by marking the entity.
     */
    public function markConflict(string $entityType, string $uuid): void
    {
        if (!isset(self::SYNCABLE_ENTITIES[$entityType])) {
            return;
        }

        $table = self::SYNCABLE_ENTITIES[$entityType]['table'];

        $stmt = $this->pdo->prepare(
            "UPDATE `{$table}` SET `sync_status` = 'conflict' WHERE `uuid` = ?"
        );
        $stmt->execute([$uuid]);
    }

    /**
     * Check if an entity type is syncable.
     */
    public function isSyncable(string $entityType): bool
    {
        return isset(self::SYNCABLE_ENTITIES[$entityType]);
    }

    /**
     * Get list of all syncable entity types.
     */
    public function getSyncableTypes(): array
    {
        return array_keys(self::SYNCABLE_ENTITIES);
    }

    /**
     * Log change tracking activity.
     */
    private function logChange(string $level, string $message, array $context = []): void
    {
        if (function_exists('log_error')) {
            log_error("[CHANGE_TRACKER] {$message}", $context);
        }
    }
}