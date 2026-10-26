<?php
require_once __DIR__ . '/../backend/session.php';
ait_start_secure_session();
require_once __DIR__ . '/../backend/data.php';
require_once __DIR__ . '/../backend/security.php';

if (!isset($_SESSION['student_id'])) {
    ait_redirect('student-login');
    exit;
}
ait_bootstrap_security();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['receipt'])) {
    ait_redirect('student-dashboard');
    exit;
}
ait_validate_csrf_post();
$student_id = (int) $_SESSION['student_id'];
ait_rate_limit('semester-challan-upload', 5, 900, 'student:' . $student_id);
$semester = max(1, min(8, (int) ($_POST['semester'] ?? 0)));
$type = (string) ($_POST['challan_type'] ?? '');
if (!in_array($type, ['semester_fee', 'exam_fee'], true)) {
    ait_abort(400, 'Invalid challan type.');
}

$stored = null;
try {
    $conn->begin_transaction();
    $lock = $conn->prepare('SELECT id, status FROM semester_challans WHERE student_id = ? AND semester = ? AND challan_type = ? FOR UPDATE');
    $lock->bind_param('iis', $student_id, $semester, $type);
    $lock->execute();
    $challan = $lock->get_result()->fetch_assoc();
    $lock->close();
    if (!$challan || !in_array($challan['status'], ['unpaid', 'rejected'], true)) {
        throw new AitUserFacingError('This challan is not open for upload. It may already be under review.');
    }
    try {
        $stored = ait_store_uploaded_asset($_FILES['receipt'], 'challans', ['allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf']]);
    } catch (RuntimeException $uploadError) {
        throw new AitUserFacingError($uploadError->getMessage(), 0, $uploadError);
    }
    $update = $conn->prepare("UPDATE semester_challans SET receipt_file = ?, status = 'uploaded', decline_reason = NULL WHERE id = ?");
    $update->bind_param('si', $stored['key'], $challan['id']);
    $update->execute();
    $update->close();
    $conn->commit();
    ait_redirect('student-dashboard?semester=' . $semester . '&status=uploaded');
} catch (Throwable $exception) {
    $conn->rollback();
    ait_storage_delete($stored['key'] ?? null);
    error_log('Semester challan upload failed: ' . $exception->getMessage());
    ait_abort(422, $exception instanceof AitUserFacingError ? $exception->getMessage() : 'The semester challan could not be uploaded. Please try again.');
}
