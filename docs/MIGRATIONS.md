# BrightBlaze Database Migrations

## Overview

Migrations are managed by a plain-PHP runner at `bin/migrate.php`. The runner discovers `.sql` files in `database/migrations/`, applies them in alphabetical order, and tracks each applied migration in the `schema_migrations` table.

## Commands

```bash
# Show migration status (applied vs pending)
/Applications/XAMPP/xamppfiles/bin/php bin/migrate.php status

# Apply all pending migrations
/Applications/XAMPP/xamppfiles/bin/php bin/migrate.php up
```

## Migration Files

Migration files are SQL files placed in `database/migrations/`. They are applied in alphabetical order by filename.

### Naming Convention

```
m1_description.sql
m2_another_change.sql
m3_feature_add.sql
```

### Requirements

- Use `CREATE TABLE IF NOT EXISTS` for new tables (idempotent).
- Use `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` patterns where possible.
- Do not include `USE database_name;` statements (the runner skips them).
- Do not include destructive `DROP TABLE` statements.
- Each migration should be backward-compatible with existing data.

## Migration Tracking

The `schema_migrations` table records:

| Column | Description |
|---|---|
| `version` | Migration filename (primary key) |
| `applied_at` | Timestamp when the migration was applied |
| `checksum` | SHA-256 hash of the migration file |

## Safety

- A failed migration is never recorded as applied.
- The runner stops on the first failure.
- Repeated execution is safe (already-applied migrations are skipped).
- The runner never resets, erases, or re-imports the database.

## Existing Migrations

The runner adapts existing Milestone 4 and 5 migrations:

- **m4_maintenance_updated_at.sql** – Adds `updated_at` column to `maintenance_records` (skipped if column already exists).
- **m5_settings.sql** – Creates the `settings` table with seed data (uses `CREATE TABLE IF NOT EXISTS` and `INSERT IGNORE`).

## Test Database

Tests use an isolated `brightblaze_test` database. The test database is created and destroyed during test execution and never touches the production `brightblaze_garage` database.

## Upgrade Considerations

1. Run `php bin/migrate.php status` to see current state.
2. Run `php bin/migrate.php up` to apply pending migrations.
3. Verify the migration was applied with `php bin/migrate.php status`.

## Rollback

The migration runner does not support rollback. To revert a migration, create a new migration that reverses the change.