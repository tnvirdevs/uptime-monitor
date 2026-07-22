# Cron Setup

Run the monitor checker every minute. The script decides which monitors are due based on each monitor's configured interval.

## cPanel / Shared Hosting

```text
* * * * * /usr/local/bin/php /home/USERNAME/public_html/uptime-monitor/cron/check_monitors.php >/dev/null 2>&1
```

## VPS

```text
* * * * * /usr/bin/php /var/www/uptime-monitor/cron/check_monitors.php >/dev/null 2>&1
```

## Browser Fallback

If CLI cron is unavailable, call this URL every minute from a hosting cron tool:

```text
https://your-domain.example/cron/check_monitors.php?key=change-this-long-random-key
```

Change `cron_web_key` in `config/config.php` before using browser-based cron.
The endpoint refuses the default placeholder key.
