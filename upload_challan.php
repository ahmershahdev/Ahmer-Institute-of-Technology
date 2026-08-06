<?php
session_start();
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

    $file = $_FILES['challan_pic'];

    try {
        $new_file_name = 'challan_' . $student_id . '_' . time();
        $target_filepath = ait_store_uploaded_asset($file, './uploads/challans/', $new_file_name);

        $stmt = $conn->prepare("UPDATE applications SET challan_pic = ?, status = 'challan_uploaded' WHERE student_id = ?");
        $stmt->bind_param("si", $target_filepath, $student_id);

        if ($stmt->execute()) {
            $stmt->close();
            header("Location: dashboard.php?status=success");
            exit();
        } else {
            echo "Database error: " . $conn->error;
        }
    } catch (RuntimeException $exception) {
        die($exception->getMessage());
    }
} else {
    header("Location: dashboard.php");
    exit();
}
