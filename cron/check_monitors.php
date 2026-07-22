<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/init.php';

$cronKey = (string) config('cron_web_key', '');
if (
    PHP_SAPI !== 'cli'
    && ($cronKey === '' || $cronKey === 'change-this-long-random-key' || !hash_equals($cronKey, (string) ($_GET['key'] ?? '')))
) {
    http_response_code(403);
    exit('Forbidden');
}

$started = microtime(true);
$checker = new MonitorChecker();
$results = $checker->runDueChecks();

if (PHP_SAPI === 'cli') {
    echo 'Checked ' . count($results) . ' monitor(s) in ' . round(microtime(true) - $started, 2) . "s\n";
    foreach ($results as $result) {
        echo '[' . strtoupper($result['status']) . '] ' . $result['monitor_name'] . ' - ' . $result['message'] . ' (' . $result['response_time'] . " ms)\n";
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['checked' => count($results), 'ok' => true], JSON_PRETTY_PRINT);
}
