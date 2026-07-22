# Architecture

This project is a raw PHP 8.2+ uptime monitoring application with no framework and no Composer dependencies.

## Request Flow

1. Public PHP entry files load `includes/init.php`.
2. `includes/init.php` starts a hardened session, loads helpers, and registers the class autoloader.
3. Pages call `require_login()` or `Auth::requireRole()` when authentication is required.
4. Pages query the database through `classes/Database.php`, which wraps PDO prepared statements.
5. Shared layout files in `includes/` render the sidebar, top bar, flash messages, and assets.

## Main Components

- `classes/Database.php`: PDO connection and query helpers.
- `classes/Auth.php`: login, logout, role checks, session timeout, login throttling.
- `classes/Csrf.php`: CSRF token generation and verification.
- `classes/MonitorChecker.php`: HTTP, API, database, TCP, ping, and SSL checks.
- `classes/NotificationService.php`: HTML email, SMTP, PHP mail fallback, and Telegram notifications.
- `classes/Setting.php`: application settings with secret decryption.
- `classes/Secret.php`: optional encryption for SMTP and Telegram secrets.
- `classes/Security.php`: target redaction and private-target blocking.

## Monitoring Flow

1. Cron runs `cron/check_monitors.php` every minute.
2. `MonitorChecker::runDueChecks()` loads active monitors whose interval has elapsed.
3. Each monitor is checked according to `monitor_type`.
4. Results are written to `monitor_results`.
5. Failed checks create an open incident if one does not already exist.
6. Successful checks resolve open incidents.
7. Failure, recovery, and SSL expiry alerts are sent through configured notification groups.
8. Dashboards and reports read `monitor_results` and `incidents` for charts and tables.

## Security Model

Administrators can manage users and settings. Operators can manage monitors and notification groups. Viewers can read dashboards, incidents, reports, and status data.

Private, loopback, and reserved targets are blocked by default to reduce SSRF risk in shared or public hosting environments. If a trusted internal deployment needs to monitor private services, set `allow_private_monitor_targets` to `true` in `config/config.php`.

Secrets are not shown back in the dashboard after saving. When `secret_key` is changed from the default placeholder, SMTP passwords and Telegram bot tokens are encrypted before storage.

## Public Surfaces

- `/status.php`: public status page.
- `/badge.php?monitor=ID`: SVG status badge.
- `/cron/check_monitors.php?key=...`: optional browser cron endpoint; refuses the default key and returns only summary JSON.

Everything else is intended for authenticated use.
