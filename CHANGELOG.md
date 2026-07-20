# Changelog

## Stage 2A – Security foundation (2026-07-20)

- Added environment-based configuration with `.env.example`, `config/env.php`, and safe parsing helpers (`env`, `env_bool`, `env_int`, `env_require`).
- Replaced hardcoded database credentials in `config/database.php` with environment-variable-based configuration.
- Added `config/config.php` integration: loads `env.php`, sets `APP_TIMEZONE`, computes `BASE_URL` from `APP_URL`.
- Added `includes/datetime.php`: `datetime_create()`, `format_datetime()`, `format_date()`, `now_utc()`, `now_local()`, `utc_to_local()`, `local_to_utc()`, `format_kwd()`.
- Added `includes/error_handler.php`: production-safe error pages, development debug output, runtime logging with secret redaction, CLI-safe text output.
- Added `bin/migrate.php`: plain-PHP migration runner with status and up commands, `schema_migrations` tracking table, existing Milestone 4/5 adaptation, idempotent reruns, failed-migration safety.
- Added `phpunit.xml.dist`, `tests/bootstrap.php`, `tests/BaseTestCase.php` with isolated test database (`brightblaze_test`).
- 63 PHPUnit tests covering environment loading, boolean/int parsing, production validation, secret redaction, date/timezone helpers, migration discovery/ordering/tracking/failure-safety/rerun.
- Added `.gitignore` excluding `.env`, runtime logs, backups, PHPUnit cache, and generated artifacts.
- Added `storage/logs/.gitkeep` for the runtime log directory.
- Added `docs/CONFIGURATION.md` documenting environment setup, production requirements, secret handling, and timezone policy.
- Added `docs/MIGRATIONS.md` documenting migration usage, safety guarantees, existing migrations, and upgrade flow.
- Updated `README.md` with links to new documentation.
- Updated `includes/functions.php` to remove `format_date()` (moved to `datetime.php`).
- All PHP files pass syntax checking (`php -l`).
- `git diff --check` reports no whitespace errors.

## Unreleased

- Added repository-wide Copilot instructions for the BrightBlaze codebase.
- Added a current-state audit covering implemented functionality, risks, debt, missing tests, and the v1.0 path.
- Added an architecture overview for the plain PHP / MySQL application structure.
- Updated the README to describe BrightBlaze as Milestones 1 through 5 rather than a Phase 1 foundation only.
