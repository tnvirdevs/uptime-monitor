<?php

declare(strict_types=1);

final class NotificationService
{
    public function notifyMonitor(array $monitor, string $event, string $message): void
    {
        $group = null;
        if (!empty($monitor['notification_group'])) {
            $group = Database::fetch('SELECT * FROM notification_groups WHERE id = :id', ['id' => $monitor['notification_group']]);
        }

        $settings = Setting::all();
        $subject = $this->headerValue('[' . ($settings['site_name'] ?? 'Uptime Monitor') . '] ' . $monitor['monitor_name'] . ' ' . ucfirst($event));
        $html = $this->renderEmail($monitor, $event, $message);

        if (!$group || (int) ($group['email_enabled'] ?? 0) === 1) {
            foreach ($this->recipients($group) as $recipient) {
                $this->sendEmail($recipient, $subject, $html);
            }
        }

        if ($group && (int) ($group['telegram_enabled'] ?? 0) === 1) {
            $this->sendTelegram($this->telegramText($monitor, $event, $message));
        }
    }

    private function recipients(?array $group): array
    {
        $emails = [];
        if ($group && !empty($group['email_recipients'])) {
            $emails = array_map('trim', explode(',', (string) $group['email_recipients']));
        }

        if (!$emails) {
            $admins = Database::fetchAll('SELECT email FROM users WHERE status = "active" AND role IN ("administrator", "operator")');
            $emails = array_column($admins, 'email');
        }

        return array_values(array_filter($emails, fn (string $email): bool => $this->safeEmail($email) === $email));
    }

    private function renderEmail(array $monitor, string $event, string $message): string
    {
        $color = $event === 'recovered' ? '#198754' : ($event === 'ssl' ? '#ffc107' : '#dc3545');

        return '<div style="font-family:Arial,sans-serif;line-height:1.5;color:#18212f">'
            . '<h2 style="color:' . $color . '">Monitor ' . e(ucfirst($event)) . '</h2>'
            . '<p><strong>' . e((string) $monitor['monitor_name']) . '</strong></p>'
            . '<p>' . e($message) . '</p>'
            . '<p><strong>Target:</strong> ' . e(redact_target((string) $monitor['target'])) . '</p>'
            . '<p><strong>Time:</strong> ' . date('Y-m-d H:i:s') . '</p>'
            . '</div>';
    }

    private function sendEmail(string $to, string $subject, string $html): void
    {
        $settings = Setting::all();
        if (!empty($settings['smtp_host'])) {
            $this->sendSmtp($to, $subject, $html, $settings);
            return;
        }

        $from = $this->safeEmail((string) ($settings['smtp_from_email'] ?: 'monitor@localhost'));
        $fromName = $this->headerValue((string) ($settings['smtp_from_name'] ?: 'Uptime Monitor'));
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $fromName . ' <' . $from . '>',
        ];
        @mail($to, $this->headerValue($subject), $html, implode("\r\n", $headers));
    }

    private function sendSmtp(string $to, string $subject, string $html, array $settings): void
    {
        $host = $this->smtpHost((string) $settings['smtp_host']);
        if ($host === '') {
            return;
        }

        $port = (int) ($settings['smtp_port'] ?: 587);
        $timeout = 15;
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            error_log("SMTP connection failed: {$errstr}");
            return;
        }

        stream_set_timeout($socket, $timeout);
        $this->smtpRead($socket);
        $this->smtpWrite($socket, 'EHLO ' . $this->smtpHost((string) ($_SERVER['SERVER_NAME'] ?? 'localhost')));
        $this->smtpRead($socket);

        if ($port === 587) {
            $this->smtpWrite($socket, 'STARTTLS');
            $this->smtpRead($socket);
            @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->smtpWrite($socket, 'EHLO ' . $this->smtpHost((string) ($_SERVER['SERVER_NAME'] ?? 'localhost')));
            $this->smtpRead($socket);
        }

        if (!empty($settings['smtp_username'])) {
            $this->smtpWrite($socket, 'AUTH LOGIN');
            $this->smtpRead($socket);
            $this->smtpWrite($socket, base64_encode((string) $settings['smtp_username']));
            $this->smtpRead($socket);
            $this->smtpWrite($socket, base64_encode((string) $settings['smtp_password']));
            $this->smtpRead($socket);
        }

        $from = $this->safeEmail((string) ($settings['smtp_from_email'] ?: $settings['smtp_username']));
        $fromName = $this->headerValue((string) ($settings['smtp_from_name'] ?: 'Uptime Monitor'));
        $headers = [
            'From: ' . $fromName . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . $this->headerValue($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
        ];

        $this->smtpWrite($socket, 'MAIL FROM:<' . $from . '>');
        $this->smtpRead($socket);
        $this->smtpWrite($socket, 'RCPT TO:<' . $to . '>');
        $this->smtpRead($socket);
        $this->smtpWrite($socket, 'DATA');
        $this->smtpRead($socket);
        fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . $html . "\r\n.\r\n");
        $this->smtpRead($socket);
        $this->smtpWrite($socket, 'QUIT');
        fclose($socket);
    }

    private function smtpWrite($socket, string $command): void
    {
        fwrite($socket, $command . "\r\n");
    }

    private function smtpRead($socket): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }

        return $response;
    }

    private function sendTelegram(string $text): void
    {
        $settings = Setting::all();
        if (empty($settings['telegram_bot_token']) || empty($settings['telegram_chat_id'])) {
            return;
        }

        $url = 'https://api.telegram.org/bot' . rawurlencode((string) $settings['telegram_bot_token']) . '/sendMessage';
        $payload = http_build_query([
            'chat_id' => $settings['telegram_chat_id'],
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            curl_exec($ch);
            curl_close($ch);
            return;
        }

        @file_get_contents($url . '?' . $payload);
    }

    private function telegramText(array $monitor, string $event, string $message): string
    {
        $prefix = ['failed' => '[DOWN]', 'recovered' => '[UP]', 'ssl' => '[SSL]'][$event] ?? '[INFO]';

        return $prefix . ' <b>' . e((string) $monitor['monitor_name']) . '</b>' . "\n"
            . e($message) . "\n"
            . '<b>Target:</b> ' . e(redact_target((string) $monitor['target'])) . "\n"
            . '<b>Time:</b> ' . date('Y-m-d H:i:s');
    }

    private function headerValue(string $value): string
    {
        return trim(str_replace(["\r", "\n"], '', $value));
    }

    private function safeEmail(string $email): string
    {
        $email = $this->headerValue($email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : 'monitor@localhost';
    }

    private function smtpHost(string $host): string
    {
        return preg_replace('/[^A-Za-z0-9.\-]/', '', $host) ?? '';
    }
}
