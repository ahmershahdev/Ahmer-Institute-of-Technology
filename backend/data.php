<?php
require_once __DIR__ . '/env.php';

ait_load_env(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

define('DB_HOST', ait_env('DB_HOST', 'localhost'));
define('DB_USER', ait_env('DB_USER', 'syedahmershah'));
define('DB_PASS', ait_env('DB_PASS', 'ahmarKH@N2006'));
define('DB_NAME', ait_env('DB_NAME', 'muet'));

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    error_log($e->getMessage());
    die("Database Connection Error: Could not connect to the system database. Please try again later.");
}
