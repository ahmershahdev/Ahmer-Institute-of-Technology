<?php
/**
 * The only way stored uploads leave the server. Files live outside the web
 * root (UPLOAD_ROOT); this script checks who is asking, checks that they own or
 * may review the file, and streams it with headers that stop browsers from
 * executing or sniffing it.
 */
require_once __DIR__ . '/../backend/session.php';
ait_start_secure_session();
require_once __DIR__ . '/../backend/data.php';
require_once __DIR__ . '/../backend/security.php';
ait_bootstrap_security();

$key = ait_storage_key_normalize((string) ($_GET['path'] ?? ''));
if ($key === null) {
    ait_abort(404, 'File not found.');
}

$student_id = (int) ($_SESSION['student_id'] ?? 0);
$admin_id = (int) ($_SESSION['admin_id'] ?? 0);
if ($student_id < 1 && $admin_id < 1) {
    ait_abort(403, 'Please sign in to view this file.');
}

ait_rate_limit('file-download', 120, 300, $admin_id > 0 ? 'admin:' . $admin_id : 'student:' . $student_id);

// Legacy rows may store "./uploads/..." — compare on the normalised form.
$legacy = './' . $key;
$display_name = null;
$allowed = false;

if ($admin_id > 0) {
    $stmt = $conn->prepare("SELECT a.role, (SELECT COUNT(*) FROM admin_permissions p WHERE p.admin_id = a.id AND p.permission_key IN ('manage_admissions', 'manage_students')) AS grants FROM admins a WHERE a.id = ? AND a.is_active = 1 AND (a.expires_at IS NULL OR a.expires_at > NOW()) LIMIT 1");
    $stmt->bind_param('i', $admin_id);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $allowed = $admin && ($admin['role'] === 'super_admin' || (int) $admin['grants'] > 0);
}

if (!$allowed && $student_id > 0) {
    $stmt = $conn->prepare("
        SELECT d.original_name FROM documents d JOIN applications a ON a.id = d.application_id
         WHERE a.student_id = ? AND d.file_url IN (?, ?)
        UNION ALL
        SELECT NULL FROM applications a WHERE a.student_id = ? AND a.challan_pic IN (?, ?)
        UNION ALL
        SELECT NULL FROM semester_challans sc WHERE sc.student_id = ? AND sc.receipt_file IN (?, ?)
        LIMIT 1");
    $stmt->bind_param('issississ', $student_id, $key, $legacy, $student_id, $key, $legacy, $student_id, $key, $legacy);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $allowed = $row !== null;
    $display_name = $row['original_name'] ?? null;
}

$path = $allowed ? ait_storage_path($key) : null;
if ($path === null) {
    // Same response for "not yours" and "missing" so file names cannot be probed.
    ait_abort(404, 'File not found.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true)) {
    ait_abort(404, 'File not found.');
}

$extension = $mime === 'application/pdf' ? 'pdf' : explode('/', $mime)[1];
$download_name = ait_sanitize_filename(pathinfo((string) ($display_name ?: 'ait-document'), PATHINFO_FILENAME)) . '.' . $extension;
$disposition = !empty($_GET['inline']) ? 'inline' : 'attachment';

header_remove('Content-Security-Policy');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: ' . $disposition . '; filename="' . addcslashes(preg_replace('/[^\x20-\x7E]/', '_', $download_name), '"\\') . '"; filename*=UTF-8\'\'' . rawurlencode($download_name));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Cross-Origin-Resource-Policy: same-origin');
header('X-Robots-Tag: noindex, nofollow');
readfile($path);
