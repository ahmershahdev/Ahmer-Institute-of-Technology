<?php
require_once __DIR__ . '/backend/session.php';
ait_start_secure_session();
require_once __DIR__ . '/backend/security.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}
ait_validate_csrf_post();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
}
session_destroy();
header("Location: login");
exit();
