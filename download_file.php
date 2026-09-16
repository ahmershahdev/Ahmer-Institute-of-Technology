<?php
require_once __DIR__ . '/backend/session.php';
ait_start_secure_session();
require_once __DIR__ . '/backend/data.php';
require_once __DIR__ . '/backend/security.php';
ait_bootstrap_security();

$requested = str_replace('\\', '/', trim((string) ($_GET['path'] ?? '')));
$relative = preg_replace('#^\./#', '', $requested);
$relative = preg_replace('#^uploads/#', '', $relative);
if ($relative === '' || str_contains($relative, '..') || !preg_match('#^[A-Za-z0-9_./-]+$#', $relative)) {
    http_response_code(404);
    exit('File not found.');
}

$student_id = (int) ($_SESSION['student_id'] ?? 0);
$admin_id = (int) ($_SESSION['admin_id'] ?? 0);
if ($student_id < 1 && $admin_id < 1) {
    http_response_code(403);
    exit('Unauthorized.');
}

$canonical = 'uploads/' . $relative;
$allowed = false;
if ($student_id > 0) {
    $stmt = $conn->prepare("SELECT 1 FROM applications a LEFT JOIN documents d ON d.application_id = a.id WHERE a.student_id = ? AND (REPLACE(COALESCE(a.challan_pic, ''), './', '') = ? OR REPLACE(COALESCE(d.file_url, ''), './', '') = ?) LIMIT 1");
    $stmt->bind_param('iss', $student_id, $canonical, $canonical);
    $stmt->execute();
    $allowed = $stmt->get_result()->num_rows === 1;
    $stmt->close();
} else {
    $stmt = $conn->prepare('SELECT 1 FROM admins WHERE id = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1');
    $stmt->bind_param('i', $admin_id);
    $stmt->execute();
    $allowed = $stmt->get_result()->num_rows === 1;
    $stmt->close();
}

$base_dir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads');
$file_path = realpath($base_dir . DIRECTORY_SEPARATOR . $relative);
if (!$allowed || $base_dir === false || $file_path === false || strncmp($file_path, $base_dir . DIRECTORY_SEPARATOR, strlen($base_dir . DIRECTORY_SEPARATOR)) !== 0 || !is_file($file_path)) {
    http_response_code(404);
    exit('File not found.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file_path) ?: 'application/octet-stream';
$allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
if (!in_array($mime, $allowed_mimes, true)) {
    http_response_code(404);
    exit('File not found.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($file_path));
header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
header('X-Content-Type-Options: nosniff');
readfile($file_path);
