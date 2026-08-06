<?php
session_start();
require_once 'backend/data.php'; // Using your existing database connection file
require_once 'backend/security.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: log-in.php");
    exit();
}

ait_bootstrap_security();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ait_validate_csrf_post();

    $student_id  = $_SESSION['student_id'];
    $first_name  = trim($_POST['first_name'] ?? '');
    $last_name   = trim($_POST['last_name'] ?? '');
    $full_name   = trim($_POST['full_name'] ?? '');
    $father_name = trim($_POST['father_name'] ?? '');
    $caste       = trim($_POST['caste'] ?? '');
    $department  = trim($_POST['department'] ?? '');
    $state       = trim($_POST['state'] ?? '');
    $city        = trim($_POST['city'] ?? '');
    $cnic        = trim($_POST['cnic'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $email       = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $country     = "Pakistan";

    // Basic validation
    if (empty($first_name) || empty($last_name) || empty($full_name) || empty($department) || empty($cnic)) {
        die("Error: Please fill in all required fields.");
    }

    // Input names matched to database doc_type ENUM
    $files_to_upload = [
        'doc_10th'     => '10th_marksheet',
        'doc_12th'     => '12th_marksheet',
        'doc_bform'    => 'b_form',
        'doc_domicile' => 'domicile',
        'profile_pic'  => 'profile_picture'
    ];

    // Begin MySQLi Transaction
    $conn->begin_transaction();

    try {
        // 1. Insert Application Details
        $stmt = $conn->prepare("INSERT INTO applications 
            (student_id, first_name, last_name, full_name, father_name, caste, department, country, state, city, cnic, phone, email, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'applied')");

        $stmt->bind_param(
            "issssssssssss",
            $student_id,
            $first_name,
            $last_name,
            $full_name,
            $father_name,
            $caste,
            $department,
            $country,
            $state,
            $city,
            $cnic,
            $phone,
            $email
        );

        if (!$stmt->execute()) {
            throw new Exception("Failed to insert application: " . $stmt->error);
        }

        $application_id = $conn->insert_id;
        $stmt->close();

        // 2. Handle File Uploads & Save Paths
        $doc_stmt = $conn->prepare("INSERT INTO documents (application_id, doc_type, file_url) VALUES (?, ?, ?)");

        foreach ($files_to_upload as $input_name => $db_doc_type) {
            if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES[$input_name];
                $new_filename = $application_id . "_" . $db_doc_type . "_" . time();
                $destination = ait_store_uploaded_asset($file, 'uploads/', $new_filename);

                $doc_stmt->bind_param("iss", $application_id, $db_doc_type, $destination);
                $doc_stmt->execute();
            } else {
                throw new Exception("Missing required file: {$input_name}.");
            }
        }
        $doc_stmt->close();

        // 3. Generate HBL Challan Record
        $challan_no = "AIT-" . date('Y') . "-" . str_pad($application_id, 5, '0', STR_PAD_LEFT);
        $amount     = 3500.00;

        $challan_stmt = $conn->prepare("INSERT INTO challans (application_id, bank_name, challan_no, amount, status) VALUES (?, 'HBL', ?, ?, 'unpaid')");
        $challan_stmt->bind_param("isd", $application_id, $challan_no, $amount);
        $challan_stmt->execute();
        $challan_stmt->close();

        // Commit transaction if all operations succeed
        $conn->commit();
        $conn->close();

        header("Location: dashboard.php?status=success");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $conn->close();
        die("Application Submission Error: " . $e->getMessage());
    }
}
