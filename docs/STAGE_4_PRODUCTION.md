# Stage 4 — Production Readiness

## Overview

Stage 4 transforms BrightBlaze from a polished development project into a production-ready application. All changes preserve the existing UI, business logic, and data model.

## Production features implemented

### Configuration
- Enhanced `.env.example` with security defaults (session lifetime, idle timeout, login throttling, password policy, encryption key)
- Strict production validation in `config/env.php`:
  - Missing required variables throw `RuntimeException` in non-local environments
  - `APP_URL` must start with `http://` or `https://`
  - `DB_PORT` validated as a valid TCP port
  - `DB_HOST` must not be empty in production
- Centralized application version constant `APP_VERSION` in `config/config.php`
- Maintenance mode integrated into bootstrap (`config/config.php`)

### Security hardening
- Explicit session cookie parameters in `includes/session.php`:
  - `HttpOnly` enabled
  - `SameSite=Lax`
  - `Secure` enabled when HTTPS is detected
  - Session name set to `BBSESSION`
- Idle session timeout (default 30 minutes, configurable via `SESSION_IDLE_TIMEOUT`)
- Absolute session lifetime (default 120 minutes, configurable via `SESSION_LIFETIME`)
- Login throttling and temporary account lockout in `includes/security.php`:
  - Tracks failed attempts in `login_attempts` table
  - Default: 5 attempts, 15-minute lockout
  - Configurable environment variables
- Security-event audit logging (`log_security_event()`):
  - Logs login success/failure, logout, lockout events
  - Captures timestamp, IP, user agent, authenticated user (when available)
- Password policy enforcement (`validate_password_policy()`):
  - Configurable minimum length (default 8)
  - Optional uppercase, number, and special character requirements
  - Enforced on new user creation and password reset
- Sensitive configuration encryption (`encrypt_value()` / `decrypt_value()`):
  - AES-256-CBC encryption for `sync_api_key` and other sensitive settings
  - Key derived from `ENCRYPTION_KEY` environment variable
- Last-administrator safeguard already present
- Self-deactivation safeguard already present
- All data access uses prepared PDO statements
- All output escaped via `e()` helper

### HTTP security headers
Added to `includes/header.php`:
- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`
- `Strict-Transport-Security` when HTTPS is detected
- `Content-Security-Policy` allowing only self, CDN scripts/styles, and data URIs for images

### Logging
- Centralized logging via `includes/error_handler.php`:
  - Request timestamp, message, and structured context
  - Automatic redaction of secrets (passwords, tokens, API keys, session IDs, connection strings)
- Security event logging via `includes/security.php`:
  - `log_security_event()` wraps `log_error()` with structured security context
  - Includes IP, user agent, and authenticated user details

### Operations
- Health check endpoint at `health.php`:
  - Reports application name, version, timestamp
  - Reports database connectivity status
  - Reports maintenance mode status
  - Exposes no credentials, paths, or internal details
- Maintenance mode via `includes/maintenance.php`:
  - Activated by `APP_MAINTENANCE=true` or `maintenance_on` file
  - Admin users may still access during maintenance
  - 503 response with `Retry-After` header

### Backup and restore
- `bin/backup.php`: Creates timestamped SQL dumps of the configured database
- `bin/restore.php`: Restores from a SQL dump with safety confirmations
  - Refuses to restore into production database unless `APP_ENV=local`

## Security improvements

| Control | Status | File |
|---|---|---|
| Explicit cookie parameters | Confirmed | `includes/session.php` |
| HttpOnly | Confirmed | `includes/session.php` |
| SameSite=Lax | Confirmed | `includes/session.php` |
| Secure when HTTPS | Confirmed | `includes/session.php` |
| Idle session timeout | Confirmed | `includes/session.php` |
| Absolute session timeout | Confirmed | `includes/session.php` |
| Login throttling | Confirmed | `includes/security.php` |
| Failed login tracking | Confirmed | `includes/security.php` |
| Temporary account lockout | Confirmed | `includes/security.php` |
| Password policy enforcement | Confirmed | `includes/security.php` |
| Security-event audit logging | Confirmed | `includes/security.php` |
| Encryption for sensitive config | Confirmed | `includes/security.php` |
| HTTP security headers | Confirmed | `includes/header.php` |
| Maintenance mode | Confirmed | `includes/maintenance.php` |
| Health check endpoint | Confirmed | `health.php` |
| Backup tool | Confirmed | `bin/backup.php` |
| Restore tool | Confirmed | `bin/restore.php` |

## Configuration improvements

- `.env.example` expanded with security section
- `env_require_production()` now validates URL scheme, DB host, and DB port
- `env_int()` rejects non-numeric values instead of coercing to 0
- Session cookie path derived from `BASE_URL`
- Centralized `security_settings()` reads from env or database

## Logging improvements

- All errors logged with timestamp and redacted context
- Security events include IP, user agent, user identity
- Secrets automatically redacted before writing to log files

## Database hardening

- Migration runner with checksum validation already present (`bin/migrate.php`)
- PDO exceptions thrown with meaningful messages
- UTC timezone configured on connection (`config/database.php`)
- Test database guard prevents accidental production writes (`tests/bootstrap.php`)

## CSV export security

- `reports/export.php` now prepends `'` to cells starting with `=`, `-`, `+`, `@`, tab, CR, or LF to prevent spreadsheet formula injection
- `X-Content-Type-Options: nosniff` header added to export response

## No upload implementation

No file upload endpoints exist in the application. Upload validation was not required.

## Performance

- No duplicate queries identified in primary workflows
- Session start happens once per request
- Error handler bootstrap is minimal

## Production checklist

- [x] `.env.example` documented
- [x] Production configuration validation
- [x] Security headers configured
- [x] Session cookie hardening
- [x] Login throttling and lockout
- [x] Password policy
- [x] Security event logging
- [x] Sensitive value encryption
- [x] Health check endpoint
- [x] Maintenance mode
- [x] Backup and restore tools
- [x] CSV export hardening
- [x] PHP lint passes
- [x] PHPUnit setup/validation
- [x] No secrets committed
- [x] No vendor/ committed

## Deployment steps

1. Copy `.env.example` to `.env`
2. Set `APP_ENV=production`
3. Set `APP_DEBUG=false`
4. Configure `APP_URL` with HTTPS scheme
5. Set database credentials
6. Generate encryption key with `php bin/encryption_key.php` and set `ENCRYPTION_KEY`
7. Run `php bin/migrate.php up`
8. Configure web server to serve the application
9. Ensure `storage/logs/` is writable by the web server
10. Ensure `.env` is outside the web root or protected by server config

## Backup procedure

```bash
# Create a timestamped backup
php bin/backup.php

# Restore from a backup (local only)
php bin/restore.php database/backups/brightblaze_20260101_000000.sql
```

Backups should be stored outside the application directory and included in off-site rotation.

## Maintenance mode

```bash
# Enable
touch maintenance_on
# or set APP_MAINTENAGE=true in .env

# Disable
rm maintenance_on
# or set APP_MAINTENANCE=false in .env
```

## Required environment variables

| Variable | Local default | Production required |
|---|---|---|
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | `false` |
| `APP_URL` | `http://localhost/brightblaze` | Yes |
| `APP_TIMEZONE` | `Asia/Kuwait` | Recommended |
| `DB_HOST` | `localhost` | Yes |
| `DB_PORT` | `3306` | Yes |
| `DB_NAME` | `brightblaze_garage` | Yes |
| `DB_USER` | `root` | Yes |
| `DB_PASS` | *(empty)* | Yes |
| `ENCRYPTION_KEY` | *(empty)* | Recommended |
| `SESSION_LIFETIME` | `120` | Recommended |
| `SESSION_IDLE_TIMEOUT` | `30` | Recommended |
| `MAX_LOGIN_ATTEMPTS` | `5` | Recommended |
| `LOCKOUT_DURATION` | `15` | Recommended |
| `PASSWORD_MIN_LENGTH` | `8` | Recommended |
| `PASSWORD_REQUIRE_UPPERCASE` | `true` | Recommended |
| `PASSWORD_REQUIRE_NUMBER` | `true` | Recommended |
| `PASSWORD_REQUIRE_SPECIAL` | `true` | Recommended |
