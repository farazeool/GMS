<?php
/**
 * BrightBlaze – Synchronization Manager
 * Core sync orchestration for offline-first architecture.
 */

require_once __DIR__ . '/../includes/uuid.php';

/**
 * Synchronization Manager
 * Coordinates all sync operations: queue processing, conflict resolution,
 * state tracking, and remote communication.
 */
class SyncManager
{
    private PDO $pdo;
    private SyncQueue $queue;
    private SyncState $state;
    private ConflictResolver $conflictResolver;

    private const MAX_CONCURRENT_SYNC = 3;
    private const SYNC_TIMEOUT = 30;

    /**
     * @param PDO $pdo Database connection
     * @param SyncQueue $queue Queue processor
     * @param SyncState $state State tracker
     * @param ConflictResolver $conflictResolver Conflict handler
     */
    public function __construct(
        PDO $pdo,
        SyncQueue $queue,
        SyncState $state,
        ConflictResolver $conflictResolver
    ) {
        $this->pdo = $pdo;
        $this->queue = $queue;
        $this->state = $state;
        $this->conflictResolver = $conflictResolver;
    }

    /**
     * Create a SyncManager instance with default dependencies.
     */
    public static function createDefault(PDO $pdo): self
    {
        $queue = new SyncQueue($pdo);
        $state = new SyncState($pdo);
        $conflictResolver = new ConflictResolver($pdo);

        return new self($pdo, $queue, $state, $conflictResolver);
    }

    /**
     * Get current synchronization status.
     */
    public function getStatus(): array
    {
        $queueStats = $this->queue->getStats();
        $lastSyncAt = $this->state->get('last_sync_at');
        $syncMode = $this->state->get('sync_mode');
        $pendingConflicts = $this->countPendingConflicts();

        return [
            'mode' => $syncMode,
            'is_online' => $this->isOnline(),
            'last_sync_at' => $lastSyncAt,
            'pending_count' => $queueStats['pending'],
            'syncing_count' => $queueStats['syncing'],
            'completed_count' => $queueStats['completed'],
            'failed_count' => $queueStats['failed'],
            'queue_length' => array_sum($queueStats),
            'pending_conflicts' => $pendingConflicts,
            'version' => (int) $this->state->get('sync_version'),
        ];
    }

    /**
     * Check if the system is currently online.
     */
    public function isOnline(): bool
    {
        $isOnline = $this->state->get('is_online');
        return $isOnline !== '0' && $isOnline !== 'false';
    }

    /**
     * Update online status.
     */
    public function setOnline(bool $online): void
    {
        $this->state->set('is_online', $online ? '1' : '0');
    }

    /**
     * Process pending sync queue items.
     * Returns array of processed items with their results.
     */
    public function processSyncBatch(int $maxItems = self::MAX_CONCURRENT_SYNC): array
    {
        $results = [];

        // Get pending items ready for sync
        $pendingItems = $this->queue->getPendingItems($maxItems);

        foreach ($pendingItems as $item) {
            $result = $this->syncItem($item);
            $results[] = $result;
        }

        // Update last sync time if any items were processed
        if (!empty($results)) {
            $this->state->set('last_push_at', date('Y-m-d H:i:s'));
        }

        return $results;
    }

    /**
     * Sync a single queue item.
     */
    private function syncItem(array $item): array
    {
        $itemId = $item['id'];
        $entityType = $item['entity_type'];
        $entityUuid = $item['entity_uuid'];
        $operation = $item['operation'];

        try {
            // Mark as syncing
            $this->queue->markSyncing($itemId);

            // Fetch the current entity state
            $entityData = $this->fetchEntity($entityType, $entityUuid);

            // Check for conflicts
            if ($operation === 'update' && $entityData) {
                $hasConflict = $this->conflictResolver->detectConflict(
                    $entityType,
                    $entityUuid,
                    $item['payload']
                );

                if ($hasConflict) {
                    $this->queue->markFailed($itemId, 'conflict_detected');
                    return [
                        'item_id' => $itemId,
                        'status' => 'conflict',
                        'entity' => $entityType,
                        'uuid' => $entityUuid,
                    ];
                }
            }

            // Execute the sync operation
            $success = $this->executeSyncOperation($entityType, $operation, $entityData, $item['payload']);

            if ($success) {
                $this->queue->markCompleted($itemId);
                $this->updateEntitySyncState($entityType, $entityUuid);

                return [
                    'item_id' => $itemId,
                    'status' => 'completed',
                    'entity' => $entityType,
                    'uuid' => $entityUuid,
                    'operation' => $operation,
                ];
            } else {
                throw new RuntimeException('Sync operation failed');
            }
        } catch (Exception $e) {
            $this->queue->markFailed($itemId, $e->getMessage());
            $this->logSyncActivity('error', $e->getMessage(), [
                'item_id' => $itemId,
                'entity' => $entityType,
                'uuid' => $entityUuid,
            ]);

            return [
                'item_id' => $itemId,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'entity' => $entityType,
                'uuid' => $entityUuid,
            ];
        }
    }

    /**
     * Execute a sync operation (placeholder for remote API calls).
     */
    private function executeSyncOperation(string $entityType, string $operation, ?array $currentData, array $payload): bool
    {
        // For now, mark as synced locally
        // Real implementation would call RemoteApiClient
        $this->state->set('last_sync_at', date('Y-m-d H:i:s'));
        $this->state->increment('sync_version');

        return true;
    }

    /**
     * Fetch current entity data by type and UUID.
     */
    private function fetchEntity(string $entityType, string $uuid): ?array
    {
        $tableMap = [
            'customers' => 'customers',
            'vehicles' => 'vehicles',
            'job_cards' => 'job_cards',
            'service_notes' => 'service_notes',
            'maintenance_records' => 'maintenance_records',
            'users' => 'users',
            'roles' => 'roles',
            'settings' => 'settings',
        ];

        $table = $tableMap[$entityType] ?? null;
        if ($table === null) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM `{$table}` WHERE `uuid` = ? AND `deleted_at` IS NULL"
            );
            $stmt->execute([$uuid]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Update entity sync state after successful sync.
     */
    private function updateEntitySyncState(string $entityType, string $uuid): void
    {
        $tableMap = [
            'customers' => 'customers',
            'vehicles' => 'vehicles',
            'job_cards' => 'job_cards',
            'service_notes' => 'service_notes',
            'maintenance_records' => 'maintenance_records',
            'users' => 'users',
            'roles' => 'roles',
            'settings' => 'settings',
        ];

        $table = $tableMap[$entityType] ?? null;
        if ($table === null) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE `{$table}` SET
                    `sync_status` = 'synced',
                    `sync_version` = `sync_version` + 1,
                    `last_synced_at` = NOW()
                WHERE `uuid` = ?"
            );
            $stmt->execute([$uuid]);
        } catch (PDOException $e) {
            // Non-fatal: log but don't fail
        }
    }

    /**
     * Count pending conflicts.
     */
    private function countPendingConflicts(): int
    {
        try {
            $stmt = $this->pdo->query(
                "SELECT COUNT(*) FROM sync_conflicts WHERE status = 'detected'"
            );
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Log sync activity.
     */
    private function logSyncActivity(string $level, string $message, array $context = []): void
    {
        if (function_exists('log_error')) {
            log_error("[SYNC] {$message}", $context);
        }
    }
}