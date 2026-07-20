# BrightBlaze – Garage Management & Job Card System

A full-stack garage management system for a Kuwait-based garage, built with **plain PHP**, **MySQL**, **Bootstrap 5**, and **JavaScript**. Designed to run locally on **XAMPP**.

## Milestones

BrightBlaze is now organized around five working milestones that build on the same plain PHP / MySQL codebase.

### Milestone 1 - Foundation

- XAMPP-compatible folder structure
- MySQL database schema + realistic Kuwait seed data (`database/brightblaze.sql`)
- PDO database connection (`config/database.php`)
- Login / logout with PHP sessions (`auth/`)
- Role-based access control (admin, technician)
- Admin dashboard shell with live stats (`admin/dashboard.php`)
- Technician dashboard shell (`technician/dashboard.php`)
- Shared layout, helper, and CSRF/session utilities (`includes/`)

### Milestone 2 - Core Master Data

- Customer management (`customers/`)
- Vehicle registry (`vehicles/`)
- Customer-to-vehicle relationships and history views

### Milestone 3 - Job Card Workflow

- Job card creation, editing, listing, and detail views (`job_cards/`)
- Technician assignment and workflow status rules
- Service notes and completion checks
- Automatic maintenance record creation from completed job cards

### Milestone 4 - Maintenance and Reporting

- Maintenance record browsing, editing, and detail views (`maintenance/`)
- Operational reporting with filters and CSV export (`reports/`)
- Report logging for generated exports

### Milestone 5 - Administration and Settings

- User and role management (`users/`)
- Password reset and activation controls
- System settings for garage profile, currency, and sync placeholders (`admin/settings.php`)
- Local-only deployment support for a future sync architecture

Cloud synchronization is intentionally not implemented yet.

## Requirements

- [XAMPP](https://www.apachefriends.org/) with PHP 8.0+ and MySQL/MariaDB

## Setup on XAMPP

1. Copy this project folder into `C:\xampp\htdocs\brightblaze` (Windows) or `/opt/lampp/htdocs/brightblaze` (Linux).
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Open phpMyAdmin at `http://localhost/phpmyadmin`.
4. Go to **Import**, choose `database/brightblaze.sql`, and click **Go**. This creates the `brightblaze_garage` database, all tables, and seed data.
   - **Upgrading an existing install?** Do not re-import. Instead run the scripts in `database/migrations/` in order (`m4_maintenance_updated_at.sql`, then `m5_settings.sql`) via phpMyAdmin > SQL.
5. If your MySQL credentials differ from the XAMPP defaults (`root` with empty password), edit `config/database.php`.
6. If you used a folder name other than `brightblaze`, update `BASE_URL` in `config/config.php`.
7. Open `http://localhost/brightblaze/` in your browser.

## Default logins

All seeded accounts use the password: `password`

| Username | Role       | Name             |
|----------|------------|------------------|
| admin    | Admin      | Yousef Al-Mutairi|
| hamad    | Technician | Hamad Al-Enezi   |
| rajesh   | Technician | Rajesh Kumar     |
| joseph   | Technician | Joseph Mathew    |

> **Important:** change these passwords before real use. You can generate a new hash with `http://localhost/brightblaze/database/hash_password.php?password=YourNewPassword` and paste it into the `users.password_hash` column in phpMyAdmin. Delete `hash_password.php` in production.

## Folder structure

```
brightblaze/
├── admin/           Admin dashboard + system settings
├── assets/css/      BrightBlaze theme
├── assets/js/       Shared JavaScript
├── auth/            Login / logout
├── config/          App config + PDO connection
├── customers/       Customer management (next phase)
├── database/        SQL schema + seed data
├── includes/        Session, RBAC, layout partials
├── job_cards/       Job card management (next phase)
├── maintenance/     Maintenance records (next phase)
├── reports/         Reports (next phase)
├── technician/      Technician dashboard
├── users/           Users & roles (next phase)
└── index.php        Entry point (role-based redirect)
```

## Project docs

- [Architecture overview](docs/ARCHITECTURE.md)
- [Current state audit](docs/CURRENT_STATE_AUDIT.md)
