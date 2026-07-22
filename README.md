# Uptime Monitor

A production-ready raw PHP 8.2+ uptime monitoring system for websites, APIs, HTTPS/SSL, MySQL/MariaDB, TCP ports, and ping targets. It uses PDO, Bootstrap 5, vanilla JavaScript, Chart.js, cron-based checks, incidents, reports, email alerts, and Telegram alerts.

## Requirements

- PHP 8.2+
- MySQL 5.7+ or MariaDB 10.4+
- Apache with `.htaccess` support, or Nginx with equivalent routing
- PHP extensions: `pdo_mysql`, `openssl`
- Recommended extensions: `curl`
- Cron access, cPanel cron, or a web cron service

## Installation

1. Upload the project files to your hosting account.
2. Create a MySQL or MariaDB database.
3. Import `database.sql`.
4. Edit `config/database.php` with your database credentials.
5. Edit `config/config.php` and set `base_url`, `timezone`, `secure_cookies`, `cron_web_key`, and `secret_key`.
6. Configure the cron job in `cron/README.md`.
7. Visit `/login.php` and sign in.

## Default Login

- Username: `admin`
- Password: `Admin@12345`

The first successful login rotates the bundled bootstrap hash using PHP `password_hash()` for the exact default password. Change the password immediately after deployment.

## Folder Structure

```text
uptime-monitor/
admin/
assets/
classes/
config/
cron/
includes/

dashboard.php
monitors.php
incidents.php
notifications.php
reports.php
users.php
settings.php
status.php
badge.php
login.php
logout.php

database.sql
README.md
.htaccess
index.php
404.php
```

## Database Import

Use phpMyAdmin, Adminer, MySQL CLI, cPanel database tools, or your hosting panel import tool.

```bash
mysql -u your_user -p your_database < database.sql
```

Then update:

```php
// config/database.php
'host' => 'localhost',
'port' => 3306,
'database' => 'your_database',
'username' => 'your_user',
'password' => 'your_password',
```

## Cron Setup

Run every minute:

```text
* * * * * /usr/bin/php /path/to/uptime-monitor/cron/check_monitors.php >/dev/null 2>&1
```

The script only checks monitors whose interval is due, so a one-minute cron is safe for monitors configured at 1, 5, 10, 15, 30, and 60 minutes.

If CLI cron is unavailable, use the protected web endpoint:

```text
https://your-domain.example/cron/check_monitors.php?key=change-this-long-random-key
```

Change `cron_web_key` in `config/config.php` first.

The web cron endpoint refuses the shipped placeholder key. CLI cron does not require the web key.

## Monitor Types

- Website HTTP/HTTPS: validates status code, response time, optional keyword, optional SSL expiry.
- API Endpoint: validates expected HTTP status and optional response keyword.
- Database: use `mysql://user:pass@host:3306/database` as the target.
- TCP Port: checks whether `target:port` is reachable.
- Ping: uses ICMP if `exec()` is available, then falls back to TCP.
- SSL Certificate: reads certificate expiry and warns inside 14 days.

Private, loopback, and reserved monitor targets are blocked by default to reduce SSRF risk on public hosting. Set `allow_private_monitor_targets` to `true` only when you intentionally monitor internal assets and trust your operators.

## Notifications

### Email

Configure SMTP in `settings.php`:

- SMTP host
- SMTP port
- SMTP username
- SMTP password
- Sender name
- Sender email

If SMTP host is blank, PHP `mail()` is used as a fallback. Notification groups can define comma-separated recipients. If a group has no recipients, active administrators and operators are notified.

Set `secret_key` in `config/config.php` before saving SMTP passwords or Telegram bot tokens. Secrets are not rendered back into the settings form. With a non-default `secret_key`, SMTP passwords and Telegram bot tokens are encrypted before storage in the database.

### Telegram

1. Create a bot with BotFather.
2. Add the bot to a chat or channel.
3. Put the bot token and chat ID in `settings.php`.
4. Enable Telegram on the notification group used by a monitor.

## Reports

Reports support daily, weekly, and monthly periods:

- Uptime percentage
- Average response time
- Downtime duration
- Number of failures
- Recovery time
- Availability, response, and downtime charts
- CSV export of monitor history

## Public Status Page and Badges

Public status page:

```text
/status.php
```

Monitor badge:

```text
/badge.php?monitor=1
```

## Security

Implemented safeguards:

- PDO prepared statements
- Password hashing with `password_hash()`
- Session ID regeneration on login
- Secure cookie options
- CSRF tokens for state-changing forms
- Output escaping
- Role-based access control
- Login attempt throttling
- Secret redaction in alerts, public status output, reports, and CSV exports
- Optional at-rest encryption for SMTP passwords and Telegram bot tokens
- Private/reserved monitor target blocking by default to reduce SSRF risk
- Basic Apache security headers
- Directory listing disabled

For production, set `secure_cookies` to `true` in `config/config.php` after enabling HTTPS.

Recommended production values in `config/config.php`:

```php
'base_url' => 'https://your-domain.com/uptime-monitor',
'timezone' => 'Asia/Riyadh',
'secure_cookies' => true,
'cron_web_key' => 'use-a-long-random-secret',
'secret_key' => 'use-a-different-long-random-secret-before-saving-secrets',
'allow_private_monitor_targets' => false,
'follow_http_redirects' => false,
'public_status_show_targets' => false,
'debug' => false,
```

Do not leave `.zip`, `.sql`, backups, or exported database files inside the public webroot. The included `.htaccess` denies common archive and dump extensions, but removing them from webroot is safer and also protects Nginx deployments.

## Nginx Notes

Point the server root to the project directory and route missing files to `404.php`:

```nginx
location / {
    try_files $uri $uri/ /404.php;
}

location ~ /(database\.sql|README\.md)$ {
    deny all;
}

location ~* \.(sql|zip|tar|gz|7z|rar|bak|env|ini|log|md)$ {
    deny all;
}

location ~ ^/(classes|config|includes)/ {
    deny all;
}
```

## Troubleshooting

- Blank page: enable `debug` in `config/config.php` temporarily and check PHP error logs.
- Cannot connect to database: confirm database credentials and that `pdo_mysql` is enabled.
- Cron does not run: execute `php cron/check_monitors.php` manually from SSH and inspect output.
- HTTPS checks fail: ensure PHP OpenSSL is enabled and CA certificates are available on the server.
- Telegram messages fail: confirm bot token, chat ID, and that the bot can post in the target chat.
- SMTP fails: confirm port, credentials, TLS support, and whether your host blocks outbound SMTP.
- Private/internal monitor target is blocked: set `allow_private_monitor_targets` to `true` only if that is intentional.
- SMTP/Telegram secret cannot be saved: change `secret_key` in `config/config.php` first.

## Deployment Checklist

- Import `database.sql`.
- Update `config/database.php`.
- Update `config/config.php`.
- Change `secret_key` and `cron_web_key`.
- Change the default admin password.
- Configure SMTP and Telegram.
- Create a one-minute cron job.
- Confirm `/dashboard.php`, `/status.php`, and `cron/check_monitors.php` work.
