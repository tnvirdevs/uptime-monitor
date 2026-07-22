SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET NAMES utf8mb4;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('administrator', 'operator', 'viewer') NOT NULL DEFAULT 'viewer',
    status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(80) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts (ip_address, username, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_name VARCHAR(120) NOT NULL,
    email_enabled TINYINT(1) NOT NULL DEFAULT 1,
    telegram_enabled TINYINT(1) NOT NULL DEFAULT 0,
    email_recipients TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE monitors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    monitor_name VARCHAR(160) NOT NULL,
    monitor_type ENUM('website', 'https', 'api', 'database', 'tcp', 'tcp_port', 'ping', 'ssl') NOT NULL DEFAULT 'website',
    target VARCHAR(500) NOT NULL,
    port INT UNSIGNED NULL,
    check_interval INT UNSIGNED NOT NULL DEFAULT 5,
    timeout INT UNSIGNED NOT NULL DEFAULT 10,
    expected_status_code INT UNSIGNED NOT NULL DEFAULT 200,
    keyword VARCHAR(190) NULL,
    ssl_monitor TINYINT(1) NOT NULL DEFAULT 0,
    notification_group INT UNSIGNED NULL,
    status ENUM('online', 'offline', 'paused') NOT NULL DEFAULT 'online',
    ssl_expires_at DATETIME NULL,
    last_checked_at DATETIME NULL,
    last_notified_at DATETIME NULL,
    retry_attempts INT UNSIGNED NOT NULL DEFAULT 3,
    notification_cooldown INT UNSIGNED NOT NULL DEFAULT 30,
    maintenance_mode TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_type (monitor_type),
    INDEX idx_group (notification_group),
    CONSTRAINT fk_monitors_notification_group FOREIGN KEY (notification_group) REFERENCES notification_groups(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE monitor_results (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    monitor_id INT UNSIGNED NOT NULL,
    status ENUM('online', 'offline') NOT NULL,
    response_time INT UNSIGNED NOT NULL DEFAULT 0,
    http_code INT UNSIGNED NULL,
    message VARCHAR(500) NOT NULL,
    checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_monitor_checked (monitor_id, checked_at),
    INDEX idx_checked (checked_at),
    CONSTRAINT fk_results_monitor FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE incidents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    monitor_id INT UNSIGNED NOT NULL,
    started_at DATETIME NOT NULL,
    resolved_at DATETIME NULL,
    duration INT UNSIGNED NULL,
    reason VARCHAR(500) NOT NULL,
    INDEX idx_monitor_started (monitor_id, started_at),
    INDEX idx_resolved (resolved_at),
    CONSTRAINT fk_incidents_monitor FOREIGN KEY (monitor_id) REFERENCES monitors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_name VARCHAR(120) NOT NULL DEFAULT 'Uptime Monitor',
    timezone VARCHAR(80) NOT NULL DEFAULT 'UTC',
    smtp_host VARCHAR(190) NULL,
    smtp_port INT UNSIGNED NOT NULL DEFAULT 587,
    smtp_username VARCHAR(190) NULL,
    smtp_password TEXT NULL,
    smtp_from_name VARCHAR(120) NULL,
    smtp_from_email VARCHAR(190) NULL,
    telegram_bot_token TEXT NULL,
    telegram_chat_id VARCHAR(120) NULL,
    default_timeout INT UNSIGNED NOT NULL DEFAULT 10,
    default_check_interval INT UNSIGNED NOT NULL DEFAULT 5,
    application_logo VARCHAR(500) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (id, full_name, username, email, password, role, status, created_at) VALUES
(1, 'System Administrator', 'admin', 'admin@example.com', '$2y$10$llcw8Cbuww90KW1dYB6Rn.98iM0JyTiC1VBT1WveVKz99VqbhFLpG', 'administrator', 'active', NOW());

INSERT INTO notification_groups (id, group_name, email_enabled, telegram_enabled, email_recipients) VALUES
(1, 'Primary Operations', 1, 1, 'admin@example.com'),
(2, 'Email Only', 1, 0, 'admin@example.com');

INSERT INTO settings (id, site_name, timezone, smtp_host, smtp_port, smtp_username, smtp_password, smtp_from_name, smtp_from_email, telegram_bot_token, telegram_chat_id, default_timeout, default_check_interval, application_logo) VALUES
(1, 'Uptime Monitor', 'UTC', '', 587, '', '', 'Uptime Monitor', 'monitor@example.com', '', '', 10, 5, '');

INSERT INTO monitors (id, monitor_name, monitor_type, target, port, check_interval, timeout, expected_status_code, keyword, ssl_monitor, notification_group, status, ssl_expires_at, last_checked_at, retry_attempts, notification_cooldown, maintenance_mode, created_at) VALUES
(1, 'Marketing Website', 'https', 'https://example.com', 443, 5, 10, 200, 'Example Domain', 1, 1, 'online', DATE_ADD(NOW(), INTERVAL 90 DAY), DATE_SUB(NOW(), INTERVAL 2 MINUTE), 3, 30, 0, DATE_SUB(NOW(), INTERVAL 35 DAY)),
(2, 'Customer App', 'website', 'https://app.example.com', 443, 5, 10, 200, '', 1, 1, 'online', DATE_ADD(NOW(), INTERVAL 45 DAY), DATE_SUB(NOW(), INTERVAL 4 MINUTE), 3, 30, 0, DATE_SUB(NOW(), INTERVAL 35 DAY)),
(3, 'Public API', 'api', 'https://api.github.com', 443, 10, 10, 200, 'current_user_url', 1, 1, 'online', DATE_ADD(NOW(), INTERVAL 60 DAY), DATE_SUB(NOW(), INTERVAL 8 MINUTE), 3, 30, 0, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(4, 'Primary Database', 'database', 'mysql://monitor_user:change_me@db.example.com:3306/appdb', 3306, 15, 8, 200, '', 0, 2, 'offline', NULL, DATE_SUB(NOW(), INTERVAL 6 MINUTE), 3, 30, 0, DATE_SUB(NOW(), INTERVAL 25 DAY)),
(5, 'SSH Gateway', 'tcp', 'gateway.example.com', 22, 10, 5, 200, '', 0, 1, 'online', NULL, DATE_SUB(NOW(), INTERVAL 7 MINUTE), 3, 30, 0, DATE_SUB(NOW(), INTERVAL 22 DAY)),
(6, 'Redis Cache', 'tcp', 'cache.example.com', 6379, 5, 5, 200, '', 0, 1, 'offline', NULL, DATE_SUB(NOW(), INTERVAL 3 MINUTE), 3, 30, 0, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(7, 'Network Ping', 'ping', '8.8.8.8', 80, 5, 5, 200, '', 0, 2, 'online', NULL, DATE_SUB(NOW(), INTERVAL 1 MINUTE), 3, 30, 0, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(8, 'SSL Expiry Watch', 'ssl', 'https://staging.example.com', 443, 60, 10, 200, '', 1, 1, 'online', DATE_ADD(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 45 MINUTE), 3, 1440, 0, DATE_SUB(NOW(), INTERVAL 15 DAY));

INSERT INTO incidents (monitor_id, started_at, resolved_at, duration, reason) VALUES
(2, DATE_SUB(NOW(), INTERVAL 9 DAY), DATE_ADD(DATE_SUB(NOW(), INTERVAL 9 DAY), INTERVAL 18 MINUTE), 1080, 'HTTP 502 returned by upstream.'),
(3, DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_ADD(DATE_SUB(NOW(), INTERVAL 6 DAY), INTERVAL 7 MINUTE), 420, 'Expected keyword was not found in response.'),
(4, DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, NULL, 'Database connection refused.'),
(6, DATE_SUB(NOW(), INTERVAL 5 HOUR), NULL, NULL, 'TCP cache.example.com:6379 unavailable.');

INSERT INTO monitor_results (monitor_id, status, response_time, http_code, message, checked_at) VALUES
(1, 'online', 112, 200, 'HTTP check passed.', DATE_SUB(NOW(), INTERVAL 23 HOUR)),
(1, 'online', 118, 200, 'HTTP check passed.', DATE_SUB(NOW(), INTERVAL 18 HOUR)),
(1, 'online', 101, 200, 'HTTP check passed.', DATE_SUB(NOW(), INTERVAL 12 HOUR)),
(1, 'online', 107, 200, 'HTTP check passed.', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(1, 'online', 99, 200, 'HTTP check passed.', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(2, 'online', 210, 200, 'HTTP check passed.', DATE_SUB(NOW(), INTERVAL 23 HOUR)),
(2, 'offline', 892, 502, 'Expected HTTP 200, got 502.', DATE_SUB(NOW(), INTERVAL 20 HOUR)),
(2, 'online', 240, 200, 'HTTP check passed.', DATE_SUB(NOW(), INTERVAL 18 HOUR)),
(2, 'online', 221, 200, 'HTTP check passed.', DATE_SUB(NOW(), INTERVAL 10 HOUR)),
(2, 'online', 230, 200, 'HTTP check passed.', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(3, 'online', 185, 200, 'HTTP check passed.', DATE_SUB(NOW(), INTERVAL 21 HOUR)),
(3, 'online', 190, 200, 'HTTP check passed.', DATE_SUB(NOW(), INTERVAL 15 HOUR)),
(3, 'online', 176, 200, 'HTTP check passed.', DATE_SUB(NOW(), INTERVAL 9 HOUR)),
(3, 'online', 181, 200, 'HTTP check passed.', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(4, 'online', 44, NULL, 'Database connection passed.', DATE_SUB(NOW(), INTERVAL 21 HOUR)),
(4, 'offline', 5000, NULL, 'Database connection refused.', DATE_SUB(NOW(), INTERVAL 12 HOUR)),
(4, 'offline', 5001, NULL, 'Database connection refused.', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(4, 'offline', 5000, NULL, 'Database connection refused.', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(5, 'online', 38, NULL, 'TCP gateway.example.com:22 is reachable.', DATE_SUB(NOW(), INTERVAL 22 HOUR)),
(5, 'online', 35, NULL, 'TCP gateway.example.com:22 is reachable.', DATE_SUB(NOW(), INTERVAL 13 HOUR)),
(5, 'online', 39, NULL, 'TCP gateway.example.com:22 is reachable.', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(6, 'online', 30, NULL, 'TCP cache.example.com:6379 is reachable.', DATE_SUB(NOW(), INTERVAL 20 HOUR)),
(6, 'online', 31, NULL, 'TCP cache.example.com:6379 is reachable.', DATE_SUB(NOW(), INTERVAL 10 HOUR)),
(6, 'offline', 5000, NULL, 'TCP cache.example.com:6379 unavailable.', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
(6, 'offline', 5000, NULL, 'TCP cache.example.com:6379 unavailable.', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(7, 'online', 24, NULL, 'Ping check passed.', DATE_SUB(NOW(), INTERVAL 23 HOUR)),
(7, 'online', 26, NULL, 'Ping check passed.', DATE_SUB(NOW(), INTERVAL 16 HOUR)),
(7, 'online', 22, NULL, 'Ping check passed.', DATE_SUB(NOW(), INTERVAL 8 HOUR)),
(7, 'online', 25, NULL, 'Ping check passed.', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(8, 'online', 88, NULL, 'SSL certificate is valid.', DATE_SUB(NOW(), INTERVAL 23 HOUR)),
(8, 'online', 91, NULL, 'SSL certificate is valid.', DATE_SUB(NOW(), INTERVAL 12 HOUR)),
(8, 'online', 89, NULL, 'SSL certificate is valid.', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(1, 'online', 105, 200, 'HTTP check passed.', DATE_SUB(NOW(), INTERVAL 14 DAY)),
(1, 'online', 108, 200, 'HTTP check passed.', DATE_SUB(NOW(), INTERVAL 10 DAY)),
(2, 'offline', 1200, 502, 'Expected HTTP 200, got 502.', DATE_SUB(NOW(), INTERVAL 9 DAY)),
(2, 'online', 245, 200, 'HTTP check passed.', DATE_SUB(NOW(), INTERVAL 8 DAY)),
(3, 'offline', 400, 200, 'Expected keyword was not found in response.', DATE_SUB(NOW(), INTERVAL 6 DAY)),
(3, 'online', 184, 200, 'HTTP check passed.', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(4, 'offline', 5000, NULL, 'Database connection refused.', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(5, 'online', 36, NULL, 'TCP gateway.example.com:22 is reachable.', DATE_SUB(NOW(), INTERVAL 1 DAY));
