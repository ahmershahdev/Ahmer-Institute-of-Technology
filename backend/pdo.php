<?php
require_once __DIR__ . '/env.php';

ait_load_env(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

if (!function_exists('ait_pdo')) {
    function ait_pdo(): PDO
    {
        static $pdo = null;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $host = ait_env('DB_HOST', 'localhost');
        $name = ait_env('DB_NAME', 'ait');
        $user = ait_env('DB_USER', 'root');
        $pass = ait_env('DB_PASS', '');

        $pdo = new PDO(
            'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4',
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return $pdo;
    }
}
