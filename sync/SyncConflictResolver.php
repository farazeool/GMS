<?php
/**
 * BrightBlaze – Sync Conflict Resolver
 * Implements deterministic conflict resolution strategies.
 */

class SyncConflictResolver
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Conflict resolution strategies:
     * - 'local_wins': Always keep local version
     * - 'remote_wins': Always accept remote version
     * - 'merge': Attempt to merge non-conflicting fields
     * - 'timestamp': Compare updated_at timestamps
     * - 'manual': Require human intervention
     */

    /**
     * Detect conflicts between local and remote data.
     * Returns array of conflicts with resolution suggestions.
     *
     * @param string $entityType Table/entity type
     * @param string $entityUuid Entity UUID
     * @param array $localData Local record data
     * @param array $remoteData Remote record data
     * @return array Conflict info or empty array if no conflict
     */
    public function detectConflict(string $entityType, string $entityUuid, array $localData, array $remoteData): array
    {
        $localUpdated = $localData['updated_at'] ?? '';
        $remoteUpdated = $remoteData['updated_at'] ?? '';

        // Same record - no conflict
        if ($localUpdated === $remoteUpdated) {
            return [];
        }

        // Compare significant fields (excluding sync metadata)
        $excludeFields = [
            'sync_status', 'sync_version', 'last_synced_at', 'deleted_at',
            'created_at', 'updated_at', 'uuid', 'id'
        ];

        $localFields = $this->filterFields($localData, $excludeFields);
        $remoteFields = $this->filterFields($remoteData, $excludeFields);

        $diff = [];
        foreach (array_keys(array_merge($localFields, $remoteFields)) as $field) {
            $localVal = $localFields[$field] ?? null;
            $remoteVal = $remoteFields[$field] ?? null;

            if ($localVal !== $remoteVal) {
                $diff[$field] = [
                    'local' => $localVal,
                    'remote' => $remoteVal,
                ];
            }
        }

        if (empty($diff)) {
            return [];
        }

        return [
            'entity_type' => $entityType,
            'entity_uuid' => $entityUuid,
            'diff' => $diff,
            'local_updated_at' => $localUpdated,
            'remote_updated_at' => $remoteUpdated,
            'suggested_strategy' => $this->suggestStrategy($localUpdated, $remoteUpdated, $diff),
        ];
    }

    /**
     * Store a detected conflict for later resolution.
     */
    public function storeConflict(array $conflict): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO `sync_conflicts`
             (`entity_type`, `entity_uuid`, `local_data`, `remote_data`, `resolution_strategy`, `status`)
             VALUES (?, ?, ?, ?, ?, 'detected')"
        );
        $stmt->execute([
            $conflict['entity_type'],
            $conflict['entity_uuid'],
            json_encode($conflict['local_data']),
            json_encode($conflict['remote_data']),
            $conflict['suggested_strategy'] ?? 'manual',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Resolve a stored conflict with the given strategy and data.
     */
    public function resolveConflict(int $conflictId, string $strategy, array $resolvedData = [], ?int $resolvedBy = null): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE `sync_conflicts`
             SET `resolution_strategy` = ?,
                 `resolved_data` = ?,
                 `status` = 'resolved',
                 `resolved_at` = NOW(),
                 `resolved_by` = ?
             WHERE `id` = ? AND `status` IN ('detected', 'resolving')"
        );
        $result = $stmt->execute([
            $strategy,
            json_encode($resolvedData),
            $resolvedBy,
            $conflictId,
        ]);

        return $result && $stmt->rowCount() > 0;
    }

    /**
     * Get all unresolved conflicts.
     */
    public function getUnresolvedConflicts(int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM `sync_conflicts`
             WHERE `status` IN ('detected', 'resolving')
             ORDER BY `detected_at` DESC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get conflict statistics.
     */
    public function getConflictStats(): array
    {
        $stats = [
            'detected' => 0,
            'resolving' => 0,
            'resolved' => 0,
            'ignored' => 0,
        ];

        try {
            $stmt = $this->pdo->query(
                "SELECT `status`, COUNT(*) as `count` FROM `sync_conflicts` GROUP BY `status`"
            );
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (isset($stats[$row['status']])) {
                    $stats[$row['status']] = (int) $row['count'];
                }
            }
        } catch (PDOException $e) {
            // Table might not exist
        }

        return $stats;
    }

    /**
     * Resolve all conflicts using a specific strategy.
     */
    public function resolveAllWithStrategy(string $strategy): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE `sync_conflicts`
             SET `resolution_strategy` = ?,
                 `status` = 'resolved',
                 `resolved_at` = NOW()
             WHERE `status` IN ('detected', 'resolving')"
        );
        $stmt->execute([$strategy]);
        return $stmt->rowCount();
    }

    /**
     * Filter out sync metadata fields for comparison.
     */
    private function filterFields(array $data, array $exclude): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (!in_array($key, $exclude, true)) {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * Suggest a resolution strategy based on timestamps and diff.
     */
    private function suggestStrategy(string $localUpdated, string $remoteUpdated, array $diff): string
    {
        // If timestamps are available and different, newer wins
        if ($localUpdated && $remoteUpdated && $localUpdated !== $remoteUpdated) {
            return ($localUpdated > $remoteUpdated) ? 'local_wins' : 'remote_wins';
        }

        // If only local has changes (remote is unchanged), local wins
        if ($localUpdated && !$remoteUpdated) {
            return 'local_wins';
        }

        // If only remote has changes, remote wins
        if (!$localUpdated && $remoteUpdated) {
            return 'remote_wins';
        }

        // Check if diff is simple (single field) - could auto-merge
        if (count($diff) === 1) {
            return 'merge';
        }

        // Default to manual for complex conflicts
        return 'manual';
    }

    /**
     * Apply a merge strategy to combine local and remote data.
     */
    public function mergeData(array $localData, array $remoteData, array $diff, string $strategy): array
    {
        $merged = $localData;

        switch ($strategy) {
            case 'local_wins':
                return $localData;

            case 'remote_wins':
                return $remoteData;

            case 'merge':
                // For each differing field, prefer non-empty value
                foreach ($diff as $field => $values) {
                    $localVal = $values['local'];
                    $remoteVal = $values['remote'];

                    // Prefer non-null/non-empty value
                    if (!empty($remoteVal) && empty($localVal)) {
                        $merged[$field] = $remoteVal;
                    } elseif (!empty($localVal) && empty($remoteVal)) {
                        $merged[$field] = $localVal;
                    } elseif ($remoteVal !== $localVal) {
                        // Both have values - use timestamp to decide
                        $merged[$field] = ($merged['updated_at'] ?? '') > ($remoteData['updated_at'] ?? '')
                            ? $localVal : $remoteVal;
                    }
                }
                return $merged;

            case 'local_wins':
            case 'remote_wins':
            default:
                return $strategy === 'local_wins' ? $localData : $remoteData;
        }
    }
}