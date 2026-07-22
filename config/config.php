<?php

declare(strict_types=1);

return [
    'app_name' => 'Uptime Monitor',
    'base_url' => '',
    'timezone' => 'UTC',
    'session_name' => 'uptime_monitor_session',
    'secure_cookies' => false,
    'cron_web_key' => 'change-this-long-random-key',
    'secret_key' => 'change-this-32-byte-secret-key',
    'allow_private_monitor_targets' => false,
    'follow_http_redirects' => false,
    'public_status_show_targets' => false,
    'debug' => false,
];
