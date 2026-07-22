# Stage 5 — Offline-First Synchronization

## Architecture Overview

BrightBlaze uses an **offline-first architecture**. The local database is always the
source of truth. All CRUD operations work without internet access. Synchronization
to a remote cloud server is optional and configurable.

```
┌─────────────────────┐      ┌──────────────────┐      ┌──────────────────┐
│  Local Application   │      │  Sync Engine      │      │  Remote Server    │
│                      │      │                   │      │                  │
│  ┌─────────────────┐ │      │  ┌─────────────┐  │      │  ┌────────────┐  │
│  │  CRUD Operations │──────►│  │ ChangeTracker│  │      │  │  API       │  │
│  │  (offline-first) │ │      │  └──────┬──────┘  │      │  │  Endpoints │  │
│  └─────────────────┘ │      │         │         │      │  └────────────┘  │
│         │            │      │  ┌──────▼──────┐  │      │        ▲         │
│  ┌──────▼───────┐    │      │  │  SyncQueue   │  │      │        │         │
│  │  Local MySQL  │    │      │  ├─────────────┤  │      │  ┌─────┴──────┐  │
│  │  (SQLite/PDO) │    │      │  │  Pending     │──├──────►  │  Cloud DB   │  │
│  │              │    │      │  │  Syncing     │  │      │  │  (mirror)   │  │
│  │  customers   │    │      │  │  Completed   │  │      │  └────────────┘  │
│  │  vehicles    │    │      │  │  Failed      │  │      │                  │
│  │  job_cards   │    │      │  │  Retry       │  │      └──────────────────┘
│  │  ...         │    │      │  └─────────────┘  │
│  └──────────────┘    │      │         │         │
│                      │      │  ┌──────▼──────┐  │
└─────────────────────┘      │  │ StateTracker │  │
                              │  └──────┬──────┘  │
                              │         │         │
                              │  ┌──────▼──────┐  │
                              │  │OnlineChecker│  │
                              │  └─────────────┘  │
                              └──────────────────┘
```

## Source of Truth Rules

| Context | Source of Truth |
|---------|----------------|
| Normal offline operation | Local database |
| During sync (push) | Send local data; remote accepts |
| During sync (pull) | Apply remote changes locally |
| Conflict (local newer) | Local wins |
| Conflict (remote newer) | Remote wins |
| Conflict (same version) | Timestamp comparison; manual if equal |

## Schema Changes

### UUID Strategy

- Each synchronizable record gets a UUID (RFC 4122 v4, 36-char string)
- UUIDs are stable — generated once, never changed
- Existing numeric `id` columns are preserved (no breaking change)
- `BEFORE INSERT` triggers auto-generate UUIDs for new rows
- Existing rows were backfilled via `bin/backfill_uuids.php`
- Unique constraints ensure UUID uniqueness

### Sync Tracking Columns (Added to All Tables)

| Column | Type | Purpose |
|--------|------|---------|
| `uuid` | CHAR(36) NOT NULL UNIQUE | Stable global identifier |
| `deleted_at` | TIMESTAMP NULL | Soft delete support |
| `sync_status` | ENUM('synced','pending','conflict') | Sync state |
| `sync_version` | INT UNSIGNED | Version counter, incremented on each change |
| `last_synced_at` | TIMESTAMP NULL | When last sync completed |

### Sync Tables

#### `sync_queue`

Durable queue for pending synchronization operations.

| Column | Type | Purpose |
|--------|------|---------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | Primary key |
| `uuid` | CHAR(36) UNIQUE | Queue item identifier |
| `entity_type` | VARCHAR(50) | Table name (customers, vehicles, etc.) |
| `entity_uuid` | CHAR(36) | UUID of the changed record |
| `operation` | ENUM('create','update','delete') | What changed |
| `payload` | JSON | Full record snapshot |
| `status` | ENUM('pending','syncing','completed','failed','retry_scheduled') | Current state |
| `attempt_count` | SMALLINT UNSIGNED | Number of sync attempts |
| `last_error` | TEXT | Error message on failure |
| `scheduled_at` | TIMESTAMP | When to try next (for retry backoff) |

#### `sync_state`

Key-value storage for synchronization metadata.

| Key | Purpose |
|-----|---------|
| `last_sync_at` | Timestamp of last sync activity |
| `last_push_at` | Timestamp of last upload |
| `last_pull_at` | Timestamp of last download |
| `sync_mode` | `local_only` or `online_sync` |
| `sync_version` | Global version counter |
| `is_online` | Cached connectivity status |

#### `sync_conflicts`

Tracks detected conflicts with full data retention.

| Column | Purpose |
|--------|---------|
| `local_data` | Complete local record |
| `remote_data` | Complete remote record |
| `resolution_strategy` | How it should be resolved |
| `resolved_data` | Result after resolution |

## Queue Lifecycle

```
created ──► pending ──► syncing ──► completed
                │                     ▲
                ▼                     │
            syncing ──► failed ───────┘
                            │
                            ▼
                     retry_scheduled ──► pending
```

### Statuses

| Status | Meaning |
|--------|---------|
| `pending` | Waiting to be processed |
| `syncing` | Currently being uploaded |
| `completed` | Successfully synced |
| `failed` | Failed after max retries |
| `retry_scheduled` | Will retry later (backoff) |

### Retry Strategy

Exponential backoff with jitter:

1st retry: 1 minute + random(0, 5) minutes
2nd retry: 2 minutes + random(0, 5) minutes
3rd retry: 4 minutes + random(0, 5) minutes
...
Max delay: 8 hours
Max attempts: 10

After 10 failed attempts, the item is permanently marked as `failed`.

### Crash Recovery

Items stuck in `syncing` for more than 30 minutes are automatically
returned to `pending` on the next sync cycle.

## Synchronization Flow

### Push (Upload)

1. Read pending queue items
2. Mark as `syncing`
3. Serialize payload as JSON
4. POST to remote API `/api/sync/push`
5. On success: mark as `completed`, update `last_synced_at`
6. On failure: increment retry, schedule backoff
7. On conflict: detect, log, store in `sync_conflicts`

### Pull (Download)

1. GET from remote API `/api/sync/pull?since=<last_pull>`
2. For each change, find or create local record by UUID
3. Check `sync_version` for conflicts
4. Apply changes with conflict detection
5. Update `sync_version` and `last_synced_at`

## Conflict Resolution

### Detection

Conflicts are detected when the same record has been modified on both
sides with different versions.

### Strategies

| Strategy | Behavior |
|----------|----------|
| `local_wins` | Keep local version; discard remote |
| `remote_wins` | Accept remote version; overwrite local |
| `merge` | Merge non-conflicting fields; newer timestamp wins |
| `manual` | Store both versions; require admin review |

### Resolution

- All conflicts are logged in `sync_conflicts`
- Admin can resolve via the Sync Dashboard
- "Resolve All" uses `local_wins` strategy by default
- Every resolution is timestamped and optionally attributed

## Soft Deletion

- Records are not hard-deleted when sync is active
- `deleted_at` is set to current timestamp
- `sync_status` changes to `pending`
- A `delete` operation is queued for sync
- Remote server handles `delete` operations by (soft/hard) deletion
- Queries filter `WHERE deleted_at IS NULL` to hide soft-deleted records

## Security Model

| Concern | Measure |
|---------|---------|
| Transport | HTTPS required in production |
| API credentials | Stored in `settings` table, encrypted at rest |
| Secret redaction | Credentials redacted from all logs |
| Input validation | Entity types are allowlisted; UUIDs validated; operations allowlisted |
| SQL injection | All queries use PDO prepared statements |
| Authorization | Sync dashboard requires `admin` role |
| CSRF | All dashboard actions require CSRF token |
| No secrets transmitted | Passwords, session IDs, DB credentials are never sent |

## Configuration Variables

| Setting | Env Key | Purpose |
|---------|---------|---------|
| Cloud API URL | `cloud_api_url` | Remote sync server endpoint |
| Sync API Key | `sync_api_key` | Authentication for sync API |
| Sync Mode | `sync_mode` | `local_only` or `online_sync` |
| Installation Mode | `installation_mode` | `local` or `shared_lan` |

These are set in the admin Settings page and stored in the `settings` table.

## Commands

### Migrations

```bash
php bin/migrate.php up
php bin/migrate.php status
```

### UUID Backfill

```bash
php bin/backfill_uuids.php
```

### Create Triggers

```bash
php bin/create_triggers.php
```

### Backup

```bash
php bin/backup.php [output.sql]
```

### Restore

```bash
php bin/restore.php <backup.sql>
```

## Troubleshooting

| Symptom | Cause | Fix |
|---------|-------|-----|
| Queue items stuck in `syncing` | Crash during sync | Run queue recovery (auto-detected after 30 min) |
| Items stuck in `pending` | sync not triggered | Click "Sync Now" in dashboard |
| Failed items not retrying | Max attempts reached | Click "Retry All Failed" |
| No sync queue entries | Change tracking not integrated | Verify CRUD hooks call `track_change()` |
| Duplicate UUID errors | Migration not applied | Run `bin/backfill_uuids.php` |
| Sync dashboard shows offline | Server unreachable | Check network and API URL configuration |

## Known Limitations

1. **Sync is admin-triggered**: Use "Sync Now" in the dashboard. Automatic
   periodic sync is not yet implemented.
2. **Remote server implementation**: The sync engine is designed for a remote
   API that follows the expected protocol. That server is not included in this
   repository.
3. **No bidirectional merge for conflicts**: The current conflict resolution
   is strategy-based (local_wins, remote_wins, merge, manual). True field-level
   three-way merge is not implemented.
4. **All-or-nothing batch sync**: The current implementation processes queue
   items in a single batch. For very large queues, this may take time.
