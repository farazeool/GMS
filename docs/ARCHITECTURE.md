# BrightBlaze Architecture

## Overview

BrightBlaze is a monolithic, XAMPP-friendly PHP application backed by MySQL/MariaDB. It intentionally keeps the deployment model simple: one database, one codebase, server-rendered pages, and Bootstrap 5 for the UI.

## Request Flow

1. `index.php` redirects authenticated users to the correct dashboard.
2. `config/config.php` loads app constants and the shared PDO connection.
3. `includes/session.php` starts the session and exposes login, role, flash, and access-control helpers.
4. Page controllers under `admin/`, `customers/`, `vehicles/`, `job_cards/`, `maintenance/`, `reports/`, and `users/` run the query and workflow logic.
5. `includes/header.php`, `includes/sidebar.php`, and `includes/footer.php` render the shared layout.

## Code Organization

- `config/` contains application constants and the database connection.
- `includes/` contains shared helpers for escaping, sessions, CSRF protection, job workflows, reporting, settings, and user operations.
- Feature folders contain self-contained controller-and-view pages for each module.
- `database/` contains the schema, seed data, and backward-compatible migrations.
- `assets/` contains the shared CSS and JavaScript used by the UI.

## Domain Areas

- Authentication and authorization are handled through session state and role checks.
- Customer and vehicle records are the master data that anchor job cards and maintenance records.
- Job cards are the primary operational workflow and drive service notes, completion logic, and maintenance syncing.
- Maintenance records summarize completed work and can be reviewed and edited independently.
- Reports are generated from job-card data with filter support and CSV export.
- Settings store garage profile data and sync placeholders for a future offline/cloud design.

## Data And Time Model

- Monetary values use three decimal places and default to KWD.
- The current schema stores timestamps with MySQL timestamp columns, but the repository still needs an explicit UTC storage and Asia/Kuwait presentation policy.
- Seed data and operational records are designed for Kuwait-based garage usage.

## Security Model

- Readable pages require login.
- Admin-only pages require the admin role.
- Technicians are restricted to their assigned job cards and permitted workflow transitions.
- Mutating forms use CSRF tokens where applicable.
- Output is escaped through shared helpers before rendering.

## Reporting And Export

- Reporting logic lives in shared helpers and is consumed by the reports pages.
- CSV export is generated server-side and logs the export action.
- Print-friendly page styling is handled in the shared front-end assets.

## Future Sync Direction

The repository includes sync-related settings so a future milestone can add remote synchronization without changing the core architecture. The remaining work is to design a queue, conflict strategy, API transport, and reconciliation flow. Cloud synchronization is not part of this PR.

## Operational Notes

- The codebase is designed to remain runnable in XAMPP without additional infrastructure.
- Migration scripts are intended to evolve the existing schema without breaking installed data.
- Working modules should only be replaced with documented justification and a matching migration plan.