<?php
declare(strict_types=1);
require_once __DIR__ . '/backend/pdo.php';
require_once __DIR__ . '/backend/cache.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$checks = ['db' => false, 'cache' => ait_cache_backend()];
try {
    $checks['db'] = (int) ait_pdo()->query('SELECT 1')->fetchColumn() === 1;
} catch (Throwable $e) {
    error_log('healthz db: ' . $e->getMessage());
}
http_response_code($checks['db'] ? 200 : 503);
echo json_encode(['status' => $checks['db'] ? 'ok' : 'degraded'] + $checks);
