<?php
require_once __DIR__ . '/../backend/session.php';
ait_start_secure_session();
if (!isset($_SESSION['student_id'])) {
    ait_redirect('student-login');
    exit();
}
require_once __DIR__ . '/../backend/data.php';
require_once __DIR__ . '/../backend/security.php';

$student_id = (int) $_SESSION['student_id'];
$return_to = !empty($_SESSION['student_code']) ? 'student-dashboard' : 'dashboard';
ait_bootstrap_security();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['challan_pic'])) {
    ait_redirect($return_to);
}

ait_validate_csrf_post();
ait_rate_limit('challan-upload', 5, 900, 'student:' . $student_id);

$stored = null;
$transaction_started = false;
try {
    $conn->begin_transaction();
    $transaction_started = true;
    $lock = $conn->prepare('SELECT id, status, challan_pic FROM applications WHERE student_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE');
    $lock->bind_param('i', $student_id);
    $lock->execute();
    $application = $lock->get_result()->fetch_assoc();
    $lock->close();
    if (!$application) {
        throw new AitUserFacingError('No application was found for this account.');
    }
    if ($application['status'] !== 'approved') {
        throw new AitUserFacingError('Paid challan uploads open after your application is approved.');
    }

    try {
        $stored = ait_store_uploaded_asset($_FILES['challan_pic'], 'challans', ['allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf']]);
    } catch (RuntimeException $uploadError) {
        throw new AitUserFacingError($uploadError->getMessage(), 0, $uploadError);
    }

    $stmt = $conn->prepare('UPDATE applications SET challan_pic = ? WHERE id = ?');
    $stmt->bind_param('si', $stored['key'], $application['id']);
    $stmt->execute();
    $stmt->close();

    $archive_docs = $conn->prepare("UPDATE documents SET is_current = 0 WHERE application_id = ? AND doc_type = 'challan_receipt' AND is_current = 1");
    $archive_docs->bind_param('i', $application['id']);
    $archive_docs->execute();
    $archive_docs->close();

    $document_stmt = $conn->prepare("INSERT INTO documents (application_id, doc_type, file_url, original_name, mime_type, file_size, checksum, is_current) VALUES (?, 'challan_receipt', ?, ?, ?, ?, ?, 1)");
    $document_stmt->bind_param('isssis', $application['id'], $stored['key'], $stored['original_name'], $stored['mime'], $stored['size'], $stored['sha256']);
    $document_stmt->execute();
    $document_stmt->close();

    $challan_stmt = $conn->prepare("UPDATE challans SET status = 'uploaded', paid_at = NOW() WHERE application_id = ? AND status = 'unpaid'");
    $challan_stmt->bind_param('i', $application['id']);
    $challan_stmt->execute();
    $challan_stmt->close();

    $conn->commit();
    ait_redirect($return_to . '?status=success');
} catch (Throwable $exception) {
    if ($transaction_started) {
        $conn->rollback();
    }
    ait_storage_delete($stored['key'] ?? null);
    error_log('Paid challan upload failed: ' . $exception->getMessage());
    ait_abort(422, $exception instanceof AitUserFacingError ? $exception->getMessage() : 'The paid challan could not be uploaded. Please check the file and try again.');
}
