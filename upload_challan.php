<?php
require_once __DIR__ . '/backend/session.php';
ait_start_secure_session();
if (!isset($_SESSION['student_id'])) {
    header("Location: log-in.php");
    exit();
}

require_once './backend/data.php';
require_once './backend/security.php';

$student_id = $_SESSION['student_id'];

ait_bootstrap_security();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['challan_pic'])) {
    ait_validate_csrf_post();
    ait_rate_limit('challan-upload', 5, 900);

    $file = $_FILES['challan_pic'];

    try {
        $new_file_name = 'challan_' . $student_id . '_' . time();
        $target_filepath = ait_store_uploaded_asset($file, './uploads/challans/', $new_file_name);
        $old_filepath = null;

        $transaction_started = false;
        $conn->begin_transaction();
        $transaction_started = true;
        $lock = $conn->prepare('SELECT id, status, challan_pic FROM applications WHERE student_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $lock->bind_param('i', $student_id);
        $lock->execute();
        $application = $lock->get_result()->fetch_assoc();
        $old_filepath = $application['challan_pic'] ?? null;
        $lock->close();

        if (!$application) {
            throw new RuntimeException('No application was found for this account.');
        }

        if ($application['status'] !== 'approved') {
            throw new RuntimeException('Paid challan uploads are available after application approval.');
        }

        $stmt = $conn->prepare('UPDATE applications SET challan_pic = ? WHERE id = ?');
        $stmt->bind_param('si', $target_filepath, $application['id']);

        if ($stmt->execute()) {
            $stmt->close();
            $receipt_mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: 'application/octet-stream';
            $receipt_checksum = hash_file('sha256', $file['tmp_name']);
            $receipt_size = (int) $file['size'];
            $archive_docs = $conn->prepare("UPDATE documents SET is_current = 0 WHERE application_id = ? AND doc_type = 'challan_receipt' AND is_current = 1");
            $archive_docs->bind_param('i', $application['id']);
            $archive_docs->execute();
            $archive_docs->close();
            $document_stmt = $conn->prepare("INSERT INTO documents (application_id, doc_type, file_url, original_name, mime_type, file_size, checksum, is_current) VALUES (?, 'challan_receipt', ?, ?, ?, ?, ?, 1)");
            $document_stmt->bind_param('isssis', $application['id'], $target_filepath, $file['name'], $receipt_mime, $receipt_size, $receipt_checksum);
            $document_stmt->execute();
            $document_stmt->close();
            $challan_stmt = $conn->prepare("UPDATE challans SET status = 'uploaded', paid_at = NOW() WHERE application_id = ? AND status = 'unpaid'");
            $challan_stmt->bind_param('i', $application['id']);
            $challan_stmt->execute();
            $challan_stmt->close();
            $conn->commit();
            if ($old_filepath && is_file($old_filepath) && $old_filepath !== $target_filepath) {
                @unlink($old_filepath);
            }
            header("Location: dashboard.php?status=success");
            exit();
        } else {
            $conn->rollback();
            error_log('Paid challan database update failed: ' . $conn->error);
            throw new RuntimeException('The paid challan could not be saved. Please try again.');
        }
    } catch (Throwable $exception) {
        if (!empty($transaction_started)) {
            $conn->rollback();
        }
        if (!empty($target_filepath) && is_file($target_filepath)) {
            @unlink($target_filepath);
        }
        error_log('Paid challan upload failed: ' . $exception->getMessage());
        die('The paid challan could not be uploaded. Please check the file and try again.');
    }
} else {
    header("Location: dashboard.php");
    exit();
}
