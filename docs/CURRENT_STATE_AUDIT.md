# Current State Audit

## Implemented Functionality

- Plain PHP and MySQL/MariaDB application structure that runs on XAMPP.
- Session-based authentication with role-based access control for admin and technician users.
- Shared helpers for CSRF protection, escaping, flash messages, job workflow rules, reporting, settings, and user utilities.
- Admin dashboard with summary metrics and recent job activity.
- Technician dashboard with assigned, in-progress, and recently completed work.
- Customer management with create, edit, view, search, and delete flows.
- Vehicle registry tied to customers, including history views for job cards and maintenance records.
- Job card workflow with assignment, status changes, service notes, completion checks, and automatic maintenance record syncing.
- Maintenance record browsing, detail, and edit flows.
- Reporting with filters, summary metrics, printable views, and CSV export.
- User management with create/edit flows, password reset, activation toggles, and lockout protection for the last active admin.
- System settings for garage profile, currency, installation mode, and sync placeholders.
- Seeded schema and migration scripts for the current database state.

## Incomplete Functionality

- Cloud synchronization is not implemented.
- Offline-first syncing, local change queues, and conflict resolution are not implemented.
- Deployment automation, environment templates, and production rollout tooling are not implemented.
- Test coverage is absent.
- Centralized timezone handling is not implemented consistently across all pages and exports.
- Some operational safeguards such as rate limiting, audit trails, and backup/restore tooling are not present.
- Reporting is functional but limited to the existing CSV and print flows.

## Security Risks

- Database credentials are hardcoded in `config/database.php` and must be replaced before production use.
- The sync API key is stored in the database as plain text in the current settings design.
- Login does not appear to have rate limiting, lockout throttling, or multi-factor authentication.
- Logout is triggered through a GET request rather than a CSRF-protected POST flow.
- CSV export does not currently neutralize formula injection payloads.
- Timezone handling depends on runtime defaults rather than an explicit UTC-to-Asia/Kuwait policy.

## Database Risks

- The schema uses MySQL timestamps and `NOW()`/`CURDATE()` defaults without an explicit UTC policy.
- Existing-install migrations are manually ordered and not fully self-healing if rerun out of sequence.
- The schema and migration strategy are tightly coupled to `brightblaze_garage`, which increases upgrade friction if database names change.
- Some data lifecycle rules rely on application code rather than database constraints alone.
- There is no documented backup, restore, or verification workflow for schema changes.

## Technical Debt

- Presentation, query, and workflow logic are mixed together in the PHP pages.
- Repeated filtering and query-building patterns appear across multiple modules.
- There is no central date/time formatting helper with timezone conversion.
- Inline page scripts are used instead of a shared client-side layer.
- Pagination and bulk actions are not implemented for larger data sets.
- The settings screen already exposes sync-related fields, but the actual sync engine is still a future milestone.

## Missing Tests

- No unit tests for helpers, validators, or workflow functions.
- No integration tests for authentication, authorization, CSRF, or role boundary checks.
- No regression tests for job status transitions, completion rules, or maintenance sync behavior.
- No tests for report filters, report logging, or CSV output.
- No database migration tests or schema verification tests.
- No automated UI or end-to-end coverage for the CRUD modules.

## Missing Deployment Support

- No Docker or container-based deployment configuration.
- No environment sample file or secrets management pattern.
- No CI pipeline for linting, tests, or artifact validation.
- No release automation or tagged deployment process.
- No documented production hardening checklist.

## Missing Offline/Cloud Synchronization Components

- No sync service or API client.
- No local write queue or retry mechanism.
- No conflict detection or merge strategy.
- No sync audit log or reconciliation tooling.
- No remote health-check or backlog visibility.
- No upload/download synchronization flow for customers, vehicles, job cards, maintenance, or settings.

## Prioritized Path To A Production-Ready v1.0

1. Normalize security and data handling first: explicit UTC storage, Asia/Kuwait display helpers, safer secret handling, and POST-based destructive flows.
2. Add automated tests for authentication, CSRF, role boundaries, workflow transitions, settings validation, and report/export behavior.
3. Introduce repeatable deployment support: environment samples, backup/restore guidance, and CI lint/test checks.
4. Tighten database migration practices so upgrades are repeatable and backward-compatible across existing installs.
5. Harden reporting and exports, including CSV safety and more robust date handling.
6. Design and implement offline/cloud synchronization only after the local application is stable and test-covered.