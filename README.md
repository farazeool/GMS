# BrightBlaze – Garage Management & Job Card System

A full-stack garage management system for a Kuwait-based garage, built with **plain PHP**, **MySQL**, **Bootstrap 5**, and **JavaScript**. Designed to run locally on **XAMPP**.

## Phase 1 (this branch)

Foundation only:

- XAMPP-compatible folder structure
- MySQL database schema + realistic Kuwait seed data (`database/brightblaze.sql`)
- PDO database connection (`config/database.php`)
- Login / logout with PHP sessions (`auth/`)
- Role-based access control (admin, technician)
- Admin dashboard shell with live stats (`admin/dashboard.php`)
- Technician dashboard shell (`technician/dashboard.php`)
- Module placeholders for the next phases (customers, vehicles, job cards, maintenance, reports, users)

## Requirements

- [XAMPP](https://www.apachefriends.org/) with PHP 8.0+ and MySQL/MariaDB

## Setup on XAMPP

1. Copy this project folder into `C:\xampp\htdocs\brightblaze` (Windows) or `/opt/lampp/htdocs/brightblaze` (Linux).
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. Open phpMyAdmin at `http://localhost/phpmyadmin`.
4. Go to **Import**, choose `database/brightblaze.sql`, and click **Go**. This creates the `brightblaze_garage` database, all tables, and seed data.
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
