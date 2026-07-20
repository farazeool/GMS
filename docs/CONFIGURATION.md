# BrightBlaze Configuration

## Environment Variables

Configuration is managed through a `.env` file in the project root. Copy `.env.example` to `.env` and adjust as needed.

### Local Development (XAMPP)

```bash
# .env (XAMPP defaults)
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost/brightblaze
APP_TIMEZONE=Asia/Kuwait
APP_KEY=

DB_HOST=localhost
DB_PORT=3306
DB_NAME=brightblaze_garage
DB_USER=root
DB_PASS=
```

### Production

Production requires the following values to be set:

```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-garage-domain.com
APP_TIMEZONE=Asia/Kuwait
APP_KEY=base64:your-generated-key

DB_HOST=your-db-host
DB_PORT=3306
DB_NAME=your-db-name
DB_USER=your-db-user
DB_PASS=your-db-password
```

### Required Production Values

When `APP_ENV` is not `local`, the system requires:

- `APP_KEY` – Application encryption key
- `DB_HOST` – Database host
- `DB_PORT` – Database port
- `DB_NAME` – Database name
- `DB_USER` – Database user
- `DB_PASS` – Database password

Missing values will cause a `RuntimeException`.

## Environment Functions

| Function | Description |
|---|---|
| `env(string $key, mixed $default)` | Get environment value with optional default |
| `env_bool(string $key, bool $default)` | Parse boolean (true: "true","1","yes","on") |
| `env_int(string $key, int $default)` | Parse integer |
| `env_require(string ...$keys)` | Throw if required keys are missing |
| `env_require_production()` | Validate production requirements |

## Secret Handling

- `.env` and `.env.local` are excluded from Git via `.gitignore`.
- Only `.env.example` is committed (contains placeholders only).
- Production errors never expose credentials, stack traces, or filesystem paths.
- The `redact_secrets()` function hides database passwords, APP_KEY, tokens, and connection strings from logs.

## Timezone Policy

- Database connections are configured to use UTC (`SET time_zone = '+00:00'`).
- PHP processes timestamps in the configured `APP_TIMEZONE` (default: `Asia/Kuwait`).
- User-facing dates and times are displayed in `Asia/Kuwait`.
- Historical timestamps stored before this policy are not reinterpreted.
- Monetary values use three decimal places (KWD).

## XAMPP Commands

```bash
# View migration status
/Applications/XAMPP/xamppfiles/bin/php bin/migrate.php status

# Apply pending migrations
/Applications/XAMPP/xamppfiles/bin/php bin/migrate.php up