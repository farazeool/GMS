# BrightBlaze – Garage Management & Job Card System

BrightBlaze is a **PHP + MySQL** garage operations system for local workshop use, designed for **XAMPP/MAMP/Laragon-style local server setups**.

## Current Milestone Status

- ✅ Milestone 1–5 core modules (auth, RBAC, dashboards, customers, vehicles, job cards, notes, maintenance, reports, users, settings)
- ✅ Milestone 6 polish/documentation updates
- ✅ Milestone 7 basic real sync-ready architecture (offline-first, manual sync, sync logs)
- ✅ Milestone 8 Windows packaging guidance (`PACKAGING.md`)

## Key Features

- Login/logout with role-based access (admin, technician)
- Admin dashboard + technician dashboard
- Customer management
- Vehicle registry
- Job card lifecycle + technician assignment
- Technician workflow controls + service notes
- Maintenance records
- Reports + CSV export
- Users & Roles management
- System Settings (garage profile, installation mode, sync mode)
- Sync Dashboard (manual sync + sync logs)

## Tech Stack

- PHP (plain, no framework)
- MySQL/MariaDB
- Bootstrap 5
- JavaScript
- phpMyAdmin-compatible SQL

---

## Setup (XAMPP)

1. Copy project to:
   - Windows: `C:\xampp\htdocs\brightblaze`
   - Linux: `/opt/lampp/htdocs/brightblaze`
2. Start **Apache** and **MySQL**.
3. Open `http://localhost/phpmyadmin`.
4. Import:
   - `/home/runner/work/GMS/GMS/database/brightblaze.sql`
5. For existing installs (upgrade path), run migrations in order:
   - `/home/runner/work/GMS/GMS/database/migrations/m4_maintenance_updated_at.sql`
   - `/home/runner/work/GMS/GMS/database/migrations/m5_settings.sql`
   - `/home/runner/work/GMS/GMS/database/migrations/m6_sync_engine.sql`
6. If your DB credentials differ from defaults, edit:
   - `/home/runner/work/GMS/GMS/config/database.php`
7. If folder name is not `brightblaze`, edit:
   - `/home/runner/work/GMS/GMS/config/config.php` (`BASE_URL`)
8. Open:
   - `http://localhost/brightblaze/`

## Setup (MAMP)

1. Copy project to your MAMP htdocs folder (commonly `/Applications/MAMP/htdocs/brightblaze`).
2. Start servers in MAMP.
3. Import `/home/runner/work/GMS/GMS/database/brightblaze.sql` in phpMyAdmin.
4. Update `/home/runner/work/GMS/GMS/config/database.php` with MAMP DB credentials/port if required.
5. Update `BASE_URL` in `/home/runner/work/GMS/GMS/config/config.php` if folder name differs.
6. Open local URL from MAMP (commonly `http://localhost:8888/brightblaze/`).

---

## Demo Login Credentials

All seeded demo users use password: `password`

| Username | Role | Name |
|---|---|---|
| admin | Admin | Yousef Al-Mutairi |
| hamad | Technician | Hamad Al-Enezi |
| rajesh | Technician | Rajesh Kumar |
| joseph | Technician | Joseph Mathew |

> Change all demo passwords before any real deployment.

---

## Sync Engine (Milestone 7)

- System always works offline first.
- Sync is **manual** from Admin → Sync Dashboard.
- Sync reads Cloud API URL + Sync API Key from System Settings.
- If cloud config is missing, system stays Local Only.
- Sync attempts a real HTTP API call; on failure, records are marked failed and a safe log is stored.
- API keys are never shown in plain text after save.

### Synced entities

- customers
- vehicles
- job_cards
- service_notes
- maintenance_records

---

## Security Notes

- Deactivated users are blocked on their next request.
- CSRF tokens are enforced for POST forms.
- Passwords use `password_hash` / `password_verify`.
- `/home/runner/work/GMS/GMS/database/hash_password.php` is now **CLI-only**.

---

## Screenshots Placeholders

Add screenshots under:
- `/home/runner/work/GMS/GMS/docs/screenshots/`

Suggested file names:
- `01-login.png`
- `02-admin-dashboard.png`
- `03-technician-dashboard.png`
- `04-job-card-view.png`
- `05-reports.png`
- `06-system-settings.png`
- `07-sync-dashboard.png`

---

## Documentation Index

- `/home/runner/work/GMS/GMS/COMPLETION_AUDIT.md`
- `/home/runner/work/GMS/GMS/TESTING.md`
- `/home/runner/work/GMS/GMS/PROJECT_REPORT_SUMMARY.md`
- `/home/runner/work/GMS/GMS/PACKAGING.md`

---

## Portfolio / CV Explanation

This project demonstrates practical full-stack delivery in a non-framework PHP environment:

- role-based business workflows
- secure session/auth handling
- relational data modeling and migrations
- reporting and exports
- offline-first architecture with sync readiness
- deployment-focused documentation

It is suitable as a portfolio/CV project for showcasing applied software engineering in small-business operations systems.
