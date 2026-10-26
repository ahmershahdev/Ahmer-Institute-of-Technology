<?php
require_once __DIR__ . '/env.php';

ait_load_env(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

define('DB_HOST', ait_env('DB_HOST', 'localhost'));
define('DB_USER', ait_env('DB_USER', 'root'));
define('DB_PASS', ait_env('DB_PASS', ''));
define('DB_NAME', ait_env('DB_NAME', 'ait'));

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    $conn->set_charset("utf8mb4");
    // NOW() must agree with PHP time, or lockouts and expiries are read hours off.
    $conn->query("SET time_zone = '" . date('P') . "'");
} catch (Exception $e) {
    error_log($e->getMessage());
    require_once __DIR__ . '/error-page.php';
    ait_error_page(503);
}
