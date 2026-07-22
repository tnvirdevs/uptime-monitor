# Deployment Guide

## 1. Upload Files

Upload the project to a PHP-enabled hosting folder, for example:

```text
public_html/uptime-monitor
```

Do not upload local ZIP archives or backup files into the public webroot. The source project includes `.gitignore` so generated ZIP files are not committed.

## 2. Create Database

Create a MySQL or MariaDB database and user in cPanel, Plesk, DirectAdmin, phpMyAdmin, or your VPS.

Import:

```text
database.sql
```

## 3. Configure Database

Edit:

```text
config/database.php
```

Set:

```php
'host' => 'localhost',
'database' => 'your_database',
'username' => 'your_database_user',
'password' => 'your_database_password',
```

Use a database user dedicated to this application.

## 4. Configure Application

Edit:

```text
config/config.php
```

Recommended production values:

```php
'base_url' => 'https://your-domain.com/uptime-monitor',
'timezone' => 'Asia/Riyadh',
'secure_cookies' => true,
'cron_web_key' => 'use-a-long-random-secret',
'secret_key' => 'use-a-different-long-random-secret',
'allow_private_monitor_targets' => false,
'follow_http_redirects' => false,
'public_status_show_targets' => false,
'debug' => false,
```

Set `secret_key` before saving SMTP passwords or Telegram bot tokens.

## 5. Login

Default login:

```text
Username: admin
Password: Admin@12345
```

Change the admin password immediately.

## 6. Configure SMTP and Telegram

In the dashboard:

```text
Settings -> SMTP Email
Settings -> Telegram
```

Then configure recipients in:

```text
Notifications
```

## 7. Configure Cron

Run every minute:

```text
* * * * * /usr/bin/php /path/to/uptime-monitor/cron/check_monitors.php >/dev/null 2>&1
```

On some shared hosts:

```text
* * * * * /usr/local/bin/php /home/USER/public_html/uptime-monitor/cron/check_monitors.php >/dev/null 2>&1
```

If CLI cron is unavailable, use the protected browser endpoint after changing `cron_web_key`:

```text
https://your-domain.com/uptime-monitor/cron/check_monitors.php?key=your-long-random-secret
```

## 8. Public Status Page

Public status page:

```text
/status.php
```

SVG badge:

```text
/badge.php?monitor=1
```

Targets are hidden on the public status page by default.

## 9. First Production Test

1. Create a monitor.
2. Click `Check Now`.
3. Confirm a row appears under Latest Checks.
4. Run cron manually from SSH.
5. Confirm reports and incidents update correctly.
