<?php
/**
 * BrightBlaze – Synchronization Change Tracking Helpers
 * Lightweight functions for CRUD operations to queue sync changes.
 */

if (!function_exists('track_change')) {
    /**
     * Record a change to a synchronizable entity and queue it for sync.
     *
     * @param string $entityType Entity type:tableName (customers, vehicles, job_cards, etc.)
     * @param string $operation  create | update | delete
     * @param int    $recordId   Local record ID
     * @return int|null Queue item ID or null if not tracked
     */
    function track_change(string $entityType, string $operation, int $recordId): ?int
    {
        // Only track in production or testing environments
        $appEnv = env('APP_ENV', 'local');
        if (!in_array($appEnv, ['local', 'testing', 'production'], true)) {
            return null;
        }

        // Late-require to avoid circular deps
        if (!class_exists('SyncQueue')) {
            require_once __DIR__ . '/../sync/SyncQueue.php';
        }
        if (!class_exists('ChangeTracker')) {
            require_once __DIR__ . '/../sync/ChangeTracker.php';
        }

        try {
            $queue = new SyncQueue(db());
            $tracker = new ChangeTracker(db(), $queue);
            return $tracker->trackChange($entityType, $operation, $recordId);
        } catch (Throwable $e) {
            // Non-fatal: log and continue
            if (function_exists('log_error')) {
                log_error('[SYNC] track_change failed: ' . $e->getMessage(), [
                    'entity' => $entityType,
                    'operation' => $operation,
                    'record_id' => $recordId,
                ]);
            }
            return null;
        }
    }
}

if (!function_exists('track_delete')) {
    /**
     * Record a soft-delete and queue for sync.
     * Expects the entity to already have deleted_at set by the caller.
     */
    function track_delete(string $entityType, int $recordId): ?int
    {
        return track_change($entityType, 'delete', $recordId);
    }
}