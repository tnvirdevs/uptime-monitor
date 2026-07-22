<?php
require __DIR__ . '/includes/init.php';

$id = (int) ($_GET['monitor'] ?? 0);
$monitor = $id ? Database::fetch('SELECT monitor_name, status FROM monitors WHERE id = :id', ['id' => $id]) : null;
$status = $monitor['status'] ?? 'unknown';
$label = $monitor['monitor_name'] ?? 'monitor';
$color = ['online' => '#198754', 'offline' => '#dc3545', 'paused' => '#6c757d'][$status] ?? '#6c757d';

header('Content-Type: image/svg+xml');
echo '<svg xmlns="http://www.w3.org/2000/svg" width="220" height="28" role="img" aria-label="' . e($label . ' ' . $status) . '">';
echo '<rect width="220" height="28" fill="#111827" rx="4"/>';
echo '<rect x="140" width="80" height="28" fill="' . $color . '" rx="4"/>';
echo '<text x="10" y="18" fill="#fff" font-family="Arial" font-size="12">' . e(substr($label, 0, 20)) . '</text>';
echo '<text x="158" y="18" fill="#fff" font-family="Arial" font-size="12">' . e($status) . '</text>';
echo '</svg>';
