<?php
require_once __DIR__ . '/../backend/session.php';
ait_start_secure_session();
require_once __DIR__ . '/../backend/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

ait_validate_csrf_post();
unset($_SESSION['teacher_id'], $_SESSION['teacher_email'], $_SESSION['teacher_name']);
session_destroy();

header('Location: login.php');
exit;
