# BrightBlaze Repository Instructions

- Preserve the existing plain PHP, MySQL/MariaDB, Bootstrap 5, and JavaScript architecture.
- Use PDO prepared statements for all database access.
- Require authorization and CSRF protection for every applicable action.
- Escape all HTML output.
- Store timestamps in UTC and display them using Asia/Kuwait.
- Store monetary values with three decimal places and use KWD by default.
- Never commit passwords, API tokens, database credentials, or production secrets.
- Keep migrations backward-compatible.
- Never delete or replace working modules without documented justification.
- Run all available tests before completing a task.
- Every feature PR must include documentation, migrations, and tests where applicable.
- Keep the application XAMPP-compatible and avoid introducing framework rewrites.