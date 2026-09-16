<?php

if (!function_exists('ait_start_secure_session')) {
    function ait_start_secure_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        session_start();

        $now = time();
        if (!empty($_SESSION['ait_last_activity']) && ($now - (int) $_SESSION['ait_last_activity']) > 1800) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['ait_last_activity'] = $now;
    }
}
