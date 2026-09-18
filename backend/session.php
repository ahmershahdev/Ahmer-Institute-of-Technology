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
        $fingerprint = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $expired = !empty($_SESSION['ait_last_activity']) && ($now - (int) $_SESSION['ait_last_activity']) > 1800;
        $too_old = !empty($_SESSION['ait_session_started']) && ($now - (int) $_SESSION['ait_session_started']) > 28800;
        $fingerprint_changed = !empty($_SESSION['ait_fingerprint']) && !hash_equals((string) $_SESSION['ait_fingerprint'], $fingerprint);
        if ($expired || $too_old || $fingerprint_changed) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['ait_session_started'] ??= $now;
        $_SESSION['ait_fingerprint'] = $fingerprint;
        $_SESSION['ait_last_activity'] = $now;
    }
}
