<?php
require_once __DIR__ . '/backend/session.php';
ait_start_secure_session();
require_once __DIR__ . '/backend/data.php';
require_once __DIR__ . '/backend/security.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: student-login');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['receipt'])) {
    header('Location: student-dashboard');
    exit;
}
ait_validate_csrf_post();
ait_rate_limit('semester-challan-upload', 5, 900);
$student_id = (int) $_SESSION['student_id'];
$semester = max(1, min(8, (int) ($_POST['semester'] ?? 0)));
$type = (string) ($_POST['challan_type'] ?? '');
if (!in_array($type, ['semester_fee', 'exam_fee'], true)) {
    http_response_code(400);
    exit('Invalid challan type.');
}
$target = null;
try {
    $conn->begin_transaction();
    $lock = $conn->prepare('SELECT id, status FROM semester_challans WHERE student_id = ? AND semester = ? AND challan_type = ? FOR UPDATE');
    $lock->bind_param('iis', $student_id, $semester, $type);
    $lock->execute();
    $challan = $lock->get_result()->fetch_assoc();
    $lock->close();
    if (!$challan || !in_array($challan['status'], ['unpaid', 'rejected'], true)) throw new RuntimeException('This challan is not available for upload.');
    $target = ait_store_uploaded_asset($_FILES['receipt'], './uploads/challans/', 'semester_' . $student_id . '_' . $semester . '_' . $type . '_' . time());
    $update = $conn->prepare("UPDATE semester_challans SET receipt_file = ?, status = 'uploaded', decline_reason = NULL WHERE id = ?");
    $update->bind_param('si', $target, $challan['id']);
    $update->execute();
    $update->close();
    $conn->commit();
    header('Location: student-dashboard?semester=' . $semester . '&status=uploaded');
    exit;
} catch (Throwable $exception) {
    $conn->rollback();
    if ($target && is_file($target)) @unlink($target);
    error_log('Semester challan upload failed: ' . $exception->getMessage());
    http_response_code(422);
    exit('The semester challan could not be uploaded. Please try again.');
}
