# TESTING GUIDE

## 1) Environment

- PHP 8+
- MySQL/MariaDB
- Apache (XAMPP/MAMP/Laragon)
- Imported database from `/home/runner/work/GMS/GMS/database/brightblaze.sql`

For upgraded installs, run migrations including:
- `/home/runner/work/GMS/GMS/database/migrations/m6_sync_engine.sql`

## 2) Baseline Static Check

Run:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

Expected: no syntax errors.

## 3) Functional Smoke Tests

### Auth & RBAC
- Login as `admin` and `hamad`.
- Confirm admin sees full sidebar.
- Confirm technician sees only technician-allowed pages.
- Deactivate a logged-in user from admin panel, then refresh that user session page; verify forced logout.

### Customer / Vehicle
- Add, edit, view customer.
- Add, edit, view vehicle.

### Job Card Workflow
- Create job card (pending/assigned rules).
- Add service note.
- Move assigned → in progress → completed.
- Verify maintenance record auto-created when completed.

### Reports
- Open reports page.
- Apply filters.
- Export CSV and verify file downloads.

### Users
- Create user.
- Activate/deactivate user.
- Admin reset password.
- Self-service password change from user sidebar.

### System Settings
- Update garage profile fields.
- Save online sync config; verify API key masked afterwards.

### Sync Dashboard
- Open Sync Dashboard.
- If not configured: verify Local Only warning behavior.
- If configured: click Manual Sync Now and verify result log entry.

## 4) Security Checks

- Ensure POST endpoints require valid CSRF token.
- Ensure deactivated users cannot continue active sessions.
- Ensure API key is not rendered in plaintext after save.
- Ensure `/home/runner/work/GMS/GMS/database/hash_password.php` is blocked via browser (403).

## 5) Known Limitations

- Sync currently handles upsert-style payloads for tracked entities.
- Delete propagation to cloud is not yet implemented.
