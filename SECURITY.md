# Security Policy

## Production Hardening Checklist

- Change `secret_key` in `config/config.php` before saving SMTP passwords or Telegram bot tokens.
- Change `cron_web_key` before enabling browser-based cron.
- Set `secure_cookies` to `true` when the site runs over HTTPS.
- Keep `debug` set to `false` in production.
- Keep `allow_private_monitor_targets` set to `false` unless the deployment intentionally monitors internal services.
- Remove ZIP files, database dumps, backups, and exported files from the public webroot.
- Change the default administrator password immediately after first login.
- Use a dedicated low-privilege MySQL user for this application.
- Restrict `/cron` by IP or use CLI cron where possible.
- Keep PHP, MySQL/MariaDB, and server packages patched.

## Built-In Protections

- PDO prepared statements.
- Password hashing with `password_hash()`.
- CSRF tokens on state-changing forms.
- Session ID regeneration after login.
- Secure session cookie options.
- Role-based access control.
- Login attempt throttling.
- Output escaping.
- JSON escaping for inline chart data.
- CSV formula injection mitigation.
- Secret redaction in alerts, reports, public status, and CSV export.
- Optional at-rest encryption for SMTP passwords and Telegram bot tokens.
- Private/reserved monitor target blocking by default to reduce SSRF risk.
- Apache rules that deny common dump/archive files and sensitive source directories.

## Reporting Issues

If this is used as a public project, ask users to report vulnerabilities privately through GitHub Security Advisories or a private maintainer email. Do not request vulnerability details in public issues.
