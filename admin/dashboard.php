<?php
require_once __DIR__ . '/../backend/session.php';
ait_start_secure_session();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once '../backend/data.php';
require_once '../backend/security.php';
require_once '../backend/rbac.php';

$csp_nonce = ait_bootstrap_security();

$current_admin_id = intval($_SESSION['admin_id']);

// Fetch current logged-in admin data & check expiration
$me_stmt = $conn->prepare("SELECT * FROM admins WHERE id = ? LIMIT 1");
$me_stmt->bind_param("i", $current_admin_id);
$me_stmt->execute();
$current_admin = $me_stmt->get_result()->fetch_assoc();
$me_stmt->close();

if (!$current_admin) {
    session_destroy();
    header("Location: login.php");
    exit;
}

if ((int) $current_admin['is_active'] !== 1) {
    session_destroy();
    header("Location: login.php?error=inactive");
    exit;
}

// Check expiration for Sub-Admins
if ($current_admin['role'] !== 'super_admin' && !empty($current_admin['expires_at'])) {
    if (strtotime($current_admin['expires_at']) <= time()) {
        session_destroy();
        header("Location: login.php?error=expired");
        exit;
    }
}

$is_super_admin = ($current_admin['role'] === 'super_admin');
$current_admin_permissions = [];
if (!$is_super_admin) {
    $perm_res = $conn->prepare('SELECT permission_key FROM admin_permissions WHERE admin_id = ?');
    $perm_res->bind_param('i', $current_admin_id);
    $perm_res->execute();
    $perm_rows = $perm_res->get_result();
    while ($perm_row = $perm_rows->fetch_assoc()) {
        $current_admin_permissions[] = $perm_row['permission_key'];
    }
    $perm_res->close();
}
$msg = '';
$msg_type = 'success';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    ait_validate_csrf_post();
    ait_rate_limit('admin-dashboard-action', 30, 300);
    $action = $_POST['action_type'];
    $idempotency_payload = $_POST;
    unset($idempotency_payload['csrf_token']);
    $request_key = hash('sha256', $action . '|' . serialize($idempotency_payload));
    $_SESSION['ait_admin_action_keys'] = array_filter(
        $_SESSION['ait_admin_action_keys'] ?? [],
        static fn($created_at): bool => (int) $created_at > time() - 1800
    );
    if (isset($_SESSION['ait_admin_action_keys'][$request_key])) {
        header('Location: dashboard.php');
        exit;
    }

    if ($action === 'save_semester_control') {
        $target_student = (int) ($_POST['student_id'] ?? 0);
        $semester = max(1, min(8, (int) ($_POST['semester'] ?? 1)));
        $fee_enabled = !empty($_POST['semester_fee_enabled']) ? 1 : 0;
        $exam_enabled = !empty($_POST['exam_challan_enabled']) ? 1 : 0;
        $slip_enabled = !empty($_POST['exam_slip_enabled']) ? 1 : 0;
        $override = !empty($_POST['attendance_override']) ? 1 : 0;
        $override_percent = ($_POST['attendance_override_percent'] ?? '') === '' ? null : max(0, min(100, (float) $_POST['attendance_override_percent']));
        $override_percent_value = $override_percent === null ? null : (string) $override_percent;
        $current_semester = max(1, min(8, (int) ($_POST['current_semester'] ?? $semester)));
        $student_update = $conn->prepare('UPDATE students SET current_semester = ? WHERE id = ?');
        $student_update->bind_param('ii', $current_semester, $target_student);
        $student_update->execute();
        $student_update->close();
        $upsert = $conn->prepare('INSERT INTO student_semesters (student_id, semester, semester_fee_enabled, exam_challan_enabled, exam_slip_enabled, attendance_override, attendance_override_percent, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE semester_fee_enabled = VALUES(semester_fee_enabled), exam_challan_enabled = VALUES(exam_challan_enabled), exam_slip_enabled = VALUES(exam_slip_enabled), attendance_override = VALUES(attendance_override), attendance_override_percent = VALUES(attendance_override_percent), updated_by = VALUES(updated_by)');
        $upsert->bind_param('iiiiisii', $target_student, $semester, $fee_enabled, $exam_enabled, $slip_enabled, $override, $override_percent_value, $current_admin_id);
        $upsert->execute();
        $upsert->close();
        $fee_status = $fee_enabled ? 'unpaid' : 'disabled';
        $exam_status = $exam_enabled ? 'unpaid' : 'disabled';
        foreach ([['semester_fee', $fee_status, 50000], ['exam_fee', $exam_status, 3500]] as [$type, $status, $amount]) {
            $challan_no = 'AIT-S' . $semester . '-' . strtoupper(substr(hash('sha256', $target_student . '|' . $semester . '|' . $type), 0, 8));
            $challan = $conn->prepare('INSERT INTO semester_challans (student_id, semester, challan_type, challan_no, amount, due_date, status) VALUES (?, ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 14 DAY), ?) ON DUPLICATE KEY UPDATE amount = VALUES(amount), status = IF(status IN (\'uploaded\', \'verified\'), status, VALUES(status))');
            $challan->bind_param('iissds', $target_student, $semester, $type, $challan_no, $amount, $status);
            $challan->execute();
            $challan->close();
        }
        $msg = 'Semester access and challan controls updated.';
    } elseif ($action === 'review_semester_challan') {
        $challan_id = (int) ($_POST['challan_id'] ?? 0);
        $decision = $_POST['decision'] === 'verified' ? 'verified' : 'rejected';
        $reason = trim((string) ($_POST['decline_reason'] ?? ''));
        $review = $conn->prepare('UPDATE semester_challans SET status = ?, decline_reason = ?, verified_by = ?, verified_at = NOW() WHERE id = ? AND status = \'uploaded\'');
        $review->bind_param('ssii', $decision, $reason, $current_admin_id, $challan_id);
        $review->execute();
        $review->close();
        if ($decision === 'verified') {
            $release = $conn->prepare("UPDATE student_semesters ss JOIN semester_challans sc ON sc.student_id = ss.student_id AND sc.semester = ss.semester SET ss.exam_slip_enabled = 1 WHERE sc.id = ? AND sc.challan_type = 'exam_fee'");
            $release->bind_param('i', $challan_id);
            $release->execute();
            $release->close();
        }
        $msg = 'Semester challan review saved.';
    } elseif ($action === 'review_attendance_appeal') {
        $appeal_id = (int) ($_POST['appeal_id'] ?? 0);
        $decision = $_POST['decision'] === 'approved' ? 'approved' : 'rejected';
        $note = trim((string) ($_POST['admin_note'] ?? ''));
        $review = $conn->prepare('UPDATE attendance_appeals SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = \'pending\'');
        $review->bind_param('ssii', $decision, $note, $current_admin_id, $appeal_id);
        $review->execute();
        $review->close();
        if ($decision === 'approved') {
            $override = $conn->prepare("UPDATE student_semesters ss JOIN attendance_appeals aa ON aa.student_id = ss.student_id AND aa.semester = ss.semester SET ss.appeal_status = 'approved', ss.attendance_override = 1, ss.admin_note = ? WHERE aa.id = ?");
            $override->bind_param('si', $note, $appeal_id);
            $override->execute();
            $override->close();
        } else {
            $reject = $conn->prepare("UPDATE student_semesters ss JOIN attendance_appeals aa ON aa.student_id = ss.student_id AND aa.semester = ss.semester SET ss.appeal_status = 'rejected', ss.attendance_override = 0, ss.admin_note = ? WHERE aa.id = ?");
            $reject->bind_param('si', $note, $appeal_id);
            $reject->execute();
            $reject->close();
        }
        $msg = 'Attendance appeal review saved.';
    }

    // 1. UPDATE PUBLIC SITE CONTENT
    if ($action === 'save_site_content' && $is_super_admin) {
        $content = [
            'home_eyebrow' => trim($_POST['home_eyebrow'] ?? ''),
            'home_headline' => trim($_POST['home_headline'] ?? ''),
            'home_intro' => trim($_POST['home_intro'] ?? ''),
            'admissions_ribbon' => trim($_POST['admissions_ribbon'] ?? ''),
        ];
        try {
            $content_stmt = $conn->prepare('INSERT INTO site_content (content_key, content_value, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE content_value = VALUES(content_value), updated_by = VALUES(updated_by)');
            foreach ($content as $content_key => $content_value) {
                $content_stmt->bind_param('ssi', $content_key, $content_value, $current_admin_id);
                $content_stmt->execute();
            }
            $content_stmt->close();
            $msg = 'Public site content updated successfully.';
        } catch (Throwable $e) {
            $msg = 'Public content could not be updated until the latest database schema is imported.';
            $msg_type = 'danger';
        }
    }

    // 2. UPDATE APPLICATION STATUS
    elseif ($action === 'update_status' && $is_super_admin) {
        $app_id = intval($_POST['app_id']);
        $status = ($_POST['status'] === 'approved') ? 'approved' : 'rejected';
        $review_note = trim($_POST['review_note'] ?? '');
        $reviewed_at = date('Y-m-d H:i:s');

        try {
            $conn->begin_transaction();
            $lock_app = $conn->prepare('SELECT id, status FROM applications WHERE id = ? FOR UPDATE');
            $lock_app->bind_param('i', $app_id);
            $lock_app->execute();
            $locked_app = $lock_app->get_result()->fetch_assoc();
            if (!$locked_app) {
                throw new RuntimeException('Application not found.');
            }
            $lock_app->close();

            $allowed_review_states = ['applied', 'challan_uploaded', 'verified'];
            if ($locked_app['status'] !== $status && !in_array($locked_app['status'], $allowed_review_states, true)) {
                throw new RuntimeException('Application has already reached a final decision.');
            }

            $update_stmt = $conn->prepare("UPDATE applications SET status = ?, review_note = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ?");
            $update_stmt->bind_param("ssisi", $status, $review_note, $current_admin_id, $reviewed_at, $app_id);
            $update_stmt->execute();
            $update_stmt->close();

            if ($status === 'approved') {
                $student_stmt = $conn->prepare('SELECT s.id, s.student_code, a.full_name, a.email, a.program_id, a.degree_level, a.department, p.code AS program_code, p.degree_level AS program_degree FROM applications a JOIN students s ON s.id = a.student_id LEFT JOIN programs p ON p.id = a.program_id WHERE a.id = ? FOR UPDATE');
                $student_stmt->bind_param('i', $app_id);
                $student_stmt->execute();
                $admitted_student = $student_stmt->get_result()->fetch_assoc();
                $student_stmt->close();

                if ($admitted_student && empty($admitted_student['student_code'])) {
                    $admission_year = (int) date('Y');
                    $program_name = strtolower((string) ($admitted_student['full_name'] ?? ''));
                    $department_name = strtolower((string) ($admitted_student['department'] ?: ''));
                    $program_code = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($admitted_student['program_code'] ?: '')));
                    if ($program_code === '') {
                        $program_code = match (true) {
                            str_contains($department_name, 'computer science') => 'CS',
                            str_contains($department_name, 'software') => 'SE',
                            str_contains($department_name, 'social work') => 'SW',
                            str_contains($department_name, 'artificial intelligence') => 'AI',
                            str_contains($department_name, 'electrical') => 'EE',
                            str_contains($department_name, 'mechanical') => 'ME',
                            default => 'GEN',
                        };
                    }
                    $degree_code = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($admitted_student['program_degree'] ?: $admitted_student['degree_level'] ?: 'BS')));
                    $department_code = substr($degree_code . $program_code, 0, 12);
                    $roll_stmt = $conn->prepare('SELECT COALESCE(MAX(roll_number), 0) + 1 AS next_roll FROM students WHERE admission_year = ? AND department_code = ? FOR UPDATE');
                    $roll_stmt->bind_param('is', $admission_year, $department_code);
                    $roll_stmt->execute();
                    $roll_number = (int) $roll_stmt->get_result()->fetch_assoc()['next_roll'];
                    $roll_stmt->close();
                    $student_code = substr((string) $admission_year, -2) . $department_code . str_pad((string) $roll_number, 3, '0', STR_PAD_LEFT);
                    $temporary_password = 'AIT-' . strtoupper(bin2hex(random_bytes(4)));
                    $hashed_password = password_hash($temporary_password, PASSWORD_DEFAULT);
                    $credential_stmt = $conn->prepare('UPDATE students SET student_code = ?, department_code = ?, admission_year = ?, roll_number = ?, password = ?, must_change_password = 1, admitted_at = NOW() WHERE id = ?');
                    $credential_stmt->bind_param('ssiisi', $student_code, $department_code, $admission_year, $roll_number, $hashed_password, $admitted_student['id']);
                    $credential_stmt->execute();
                    $credential_stmt->close();
                    $msg = "Application #{$app_id} approved. Student ID: {$student_code} | Temporary password: {$temporary_password}";
                }

                $challan_check = $conn->prepare('SELECT id FROM challans WHERE application_id = ? FOR UPDATE');
                $challan_check->bind_param('i', $app_id);
                $challan_check->execute();
                $has_challan = $challan_check->get_result()->num_rows === 1;
                $challan_check->close();

                if (!$has_challan) {
                    $challan_no = 'AIT-' . date('Y') . '-' . str_pad((string) $app_id, 5, '0', STR_PAD_LEFT);
                    $challan_stmt = $conn->prepare("INSERT INTO challans (application_id, bank_name, challan_no, amount, due_date, status) VALUES (?, 'HBL', ?, 3500.00, DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'unpaid')");
                    $challan_stmt->bind_param('is', $app_id, $challan_no);
                    $challan_stmt->execute();
                    $challan_stmt->close();
                }
            }

            $conn->commit();
            $msg = "Application #{$app_id} status updated to {$status}.";
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Admin application approval failed: ' . $e->getMessage());
            $msg = 'The application update could not be completed safely. Please try again.';
            $msg_type = 'danger';
        }
    } elseif ($action === 'update_status') {
        $msg = 'Only a super administrator can approve or reject applications.';
        $msg_type = 'danger';
    }

    // 2. SUPER ADMIN: CREATE SUB-ADMIN
    elseif ($action === 'create_subadmin' && $is_super_admin) {
        $name = trim($_POST['name']);
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $password = trim($_POST['password']);
        $role_title = trim($_POST['role_title']);
        $duration_days = intval($_POST['duration_days']);

        if (!empty($name) && !empty($email) && !empty($password) && !empty($role_title)) {
            $hashed_pass = password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
            $expires_at = ($duration_days > 0) ? date('Y-m-d H:i:s', strtotime("+{$duration_days} days")) : NULL;

            $add_stmt = $conn->prepare("INSERT INTO admins (name, email, password, role, role_title, expires_at) VALUES (?, ?, ?, 'sub_admin', ?, ?)");
            $add_stmt->bind_param("sssss", $name, $email, $hashed_pass, $role_title, $expires_at);

            if ($add_stmt->execute()) {
                $new_subadmin_id = $conn->insert_id;
                $granted_permissions = array_values(array_intersect((array) ($_POST['permissions'] ?? []), ait_admin_permission_keys()));
                if ($granted_permissions !== []) {
                    $perm_stmt = $conn->prepare('INSERT INTO admin_permissions (admin_id, permission_key, granted_by) VALUES (?, ?, ?)');
                    foreach ($granted_permissions as $perm_key) {
                        $perm_stmt->bind_param('isi', $new_subadmin_id, $perm_key, $current_admin_id);
                        $perm_stmt->execute();
                    }
                    $perm_stmt->close();
                }
                $audit_stmt = $conn->prepare("INSERT INTO audit_log (actor_type, actor_id, actor_label, action, target_type, target_id, meta, ip_address) VALUES ('admin', ?, ?, 'create_subadmin', 'admin', ?, ?, ?)");
                $audit_meta = json_encode(['role_title' => $role_title, 'permissions' => $granted_permissions]);
                $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
                $audit_stmt->bind_param('isiss', $current_admin_id, $current_admin['name'], $new_subadmin_id, $audit_meta, $ip);
                $audit_stmt->execute();
                $audit_stmt->close();
                $msg = "Sub-Admin '{$name}' created successfully.";
            } else {
                $msg = "Error creating sub-admin: " . $conn->error;
                $msg_type = 'danger';
            }
            $add_stmt->close();
        }
    }

    // 3. SUPER ADMIN: TERMINATE SUB-ADMIN
    elseif ($action === 'terminate_subadmin' && $is_super_admin) {
        $target_id = intval($_POST['target_admin_id']);

        if ($target_id !== $current_admin_id) {
            $del_stmt = $conn->prepare("DELETE FROM admins WHERE id = ? AND role = 'sub_admin'");
            $del_stmt->bind_param("i", $target_id);
            $del_stmt->execute();
            $del_stmt->close();
            $audit_stmt = $conn->prepare("INSERT INTO audit_log (actor_type, actor_id, actor_label, action, target_type, target_id, ip_address) VALUES ('admin', ?, ?, 'terminate_subadmin', 'admin', ?, ?)");
            $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            $audit_stmt->bind_param('isis', $current_admin_id, $current_admin['name'], $target_id, $ip);
            $audit_stmt->execute();
            $audit_stmt->close();
            $msg = "Sub-Admin terminated successfully.";
        }
    }

    // 4. SUB-ADMIN / ALL: UPDATE OWN CREDENTIALS
    elseif ($action === 'update_profile') {
        $new_name = trim($_POST['name']);
        $new_email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $new_password = trim($_POST['password']);

        if (!empty($new_name) && !empty($new_email)) {
            if (!empty($new_password)) {
                $hashed_pass = password_hash($new_password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
                $up_stmt = $conn->prepare("UPDATE admins SET name = ?, email = ?, password = ? WHERE id = ?");
                $up_stmt->bind_param("sssi", $new_name, $new_email, $hashed_pass, $current_admin_id);
            } else {
                $up_stmt = $conn->prepare("UPDATE admins SET name = ?, email = ? WHERE id = ?");
                $up_stmt->bind_param("ssi", $new_name, $new_email, $current_admin_id);
            }
            $up_stmt->execute();
            $up_stmt->close();

            $_SESSION['admin_name'] = $new_name;
            $_SESSION['admin_email'] = $new_email;
            $msg = "Profile updated successfully.";
        }
    }

    // 5. SUB-ADMIN: TRANSFER SUB-ADMINSHIP
    elseif ($action === 'transfer_subadmin' && !$is_super_admin) {
        $new_owner_name = trim($_POST['transfer_name']);
        $new_owner_email = filter_var(trim($_POST['transfer_email']), FILTER_SANITIZE_EMAIL);
        $new_owner_pass = trim($_POST['transfer_password']);

        if (!empty($new_owner_name) && !empty($new_owner_email) && !empty($new_owner_pass)) {
            $hashed_pass = password_hash($new_owner_pass, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);

            $tr_stmt = $conn->prepare("UPDATE admins SET name = ?, email = ?, password = ? WHERE id = ?");
            $tr_stmt->bind_param("sssi", $new_owner_name, $new_owner_email, $hashed_pass, $current_admin_id);
            $tr_stmt->execute();
            $tr_stmt->close();

            session_destroy();
            header("Location: login.php?msg=transferred");
            exit;
        }
    }

    // 6. SUB-ADMIN: DELETE OWN ACCOUNT
    elseif ($action === 'delete_self' && !$is_super_admin) {
        $self_del = $conn->prepare("DELETE FROM admins WHERE id = ? AND role = 'sub_admin'");
        $self_del->bind_param("i", $current_admin_id);
        $self_del->execute();
        $self_del->close();

        session_destroy();
        header("Location: login.php?msg=deleted");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && isset($request_key)) {
    $_SESSION['ait_admin_action_keys'][$request_key] = time();
}

// Fetch Metrics & Data
$total_apps = $conn->query("SELECT COUNT(*) as count FROM applications")->fetch_assoc()['count'] ?? 0;
$pending_apps = $conn->query("SELECT COUNT(*) as count FROM applications WHERE status IN ('applied', 'challan_uploaded', 'verified')")->fetch_assoc()['count'] ?? 0;
$challan_apps = $conn->query("SELECT COUNT(*) as count FROM applications WHERE status = 'challan_uploaded'")->fetch_assoc()['count'] ?? 0;
$approved_apps = $conn->query("SELECT COUNT(*) as count FROM applications WHERE status = 'approved'")->fetch_assoc()['count'] ?? 0;
$rejected_apps = $conn->query("SELECT COUNT(*) as count FROM applications WHERE status = 'rejected'")->fetch_assoc()['count'] ?? 0;
$review_progress = $total_apps > 0 ? round((($approved_apps + $rejected_apps) / $total_apps) * 100) : 0;
$slip_ready_percent = $total_apps > 0 ? round(($approved_apps / $total_apps) * 100) : 0;

$applications_result = $conn->query("SELECT a.*, s.email as student_email FROM applications a LEFT JOIN students s ON a.student_id = s.id ORDER BY a.id DESC");
$subadmins_result = $conn->query("SELECT * FROM admins WHERE role = 'sub_admin' ORDER BY id DESC");
$semester_students_result = $conn->query("SELECT id, name, student_code, department_code, current_semester FROM students WHERE student_code IS NOT NULL AND is_active = 1 ORDER BY student_code");
$semester_challans_result = $conn->query("SELECT sc.*, s.name AS student_name, s.student_code FROM semester_challans sc JOIN students s ON s.id = sc.student_id WHERE sc.status = 'uploaded' ORDER BY sc.updated_at DESC");
$attendance_appeals_result = $conn->query("SELECT aa.*, s.name AS student_name, s.student_code FROM attendance_appeals aa JOIN students s ON s.id = aa.student_id WHERE aa.status = 'pending' ORDER BY aa.created_at DESC");
$reports_result = null;
try {
    $reports_result = $conn->query("SELECT r.*, s.name AS student_name, s.student_code FROM student_report_requests r JOIN students s ON s.id = r.student_id ORDER BY r.created_at DESC");
} catch (Throwable $e) {
    $reports_result = null;
}
$site_content = [];
try {
    $site_content_result = $conn->query("SELECT content_key, content_value FROM site_content WHERE content_key IN ('home_eyebrow', 'home_headline', 'home_intro', 'admissions_ribbon')");
    while ($content_row = $site_content_result->fetch_assoc()) {
        $site_content[$content_row['content_key']] = $content_row['content_value'];
    }
} catch (Throwable $e) {
    $site_content = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $is_super_admin ? 'Super Admin' : 'Sub Admin'; ?> Dashboard</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon/ait.ico">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style nonce="<?php echo htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        :root {
            --sidebar-width: 260px;
            --header-height: 60px;
            --bg-dark: #0f172a;
            --border-dark: #334155;
        }

        body {
            background: radial-gradient(circle at 10% 10%, rgba(14, 165, 233, .18), transparent 35%), linear-gradient(135deg, #08111f 0%, #102a3d 52%, #0b1726 100%);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            overflow-x: hidden;
            color: #e2e8f0;
        }

        .admin-navbar {
            background: rgba(5, 15, 28, .78) !important;
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            height: var(--header-height);
            z-index: 1030;
            border-bottom: 1px solid var(--border-dark);
        }

        .navbar-brand-box {
            width: var(--sidebar-width);
            display: flex;
            align-items: center;
            padding-left: 1.25rem;
        }

        .brand-mark {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font: 700 18px "Space Grotesk", sans-serif;
            color: #fff;
            margin-right: 14px;
            letter-spacing: .02em;
        }

        .brand-mark i {
            color: #38bdf8;
        }

        /* Hide hamburger button by default on desktop */
        .nav-toggle-btn {
            display: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #94a3b8;
            background: transparent;
            border: 0;
            padding: 0 15px;
            align-items: center;
        }

        .sidebar {
            width: var(--sidebar-width);
            height: calc(100vh - var(--header-height));
            position: fixed;
            top: var(--header-height);
            left: 0;
            background: rgba(5, 15, 28, .68);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            border-right: 1px solid rgba(148, 163, 184, .18);
            z-index: 1020;
            transition: left 0.3s ease;
        }

        .sidebar-menu {
            list-style: none;
            padding: 15px 0;
            margin: 0;
        }

        .sidebar-item a {
            display: flex;
            align-items: center;
            padding: 12px 24px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .sidebar-item a:hover,
        .sidebar-item.active a {
            color: #38bdf8;
            background-color: rgba(56, 189, 248, 0.08);
        }

        .sidebar-item .bi {
            font-size: 1.1rem;
            margin-right: 12px;
        }

        .logout-form {
            margin: 0;
        }

        .sidebar-item-button {
            width: 100%;
            display: flex;
            align-items: center;
            padding: 12px 24px;
            border: 0;
            color: #94a3b8;
            background: transparent;
            font-size: .9rem;
            font-weight: 500;
            text-align: left;
            cursor: pointer;
        }

        .sidebar-item-button:hover {
            color: #f87171;
            background: rgba(248, 113, 113, .08);
        }

        .sidebar-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1015;
            display: none;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            margin-top: var(--header-height);
            min-height: calc(100vh - var(--header-height));
        }

        .stat-card,
        .table-card {
            background: rgba(15, 35, 52, .68);
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, .2);
            box-shadow: 0 18px 45px rgba(0, 0, 0, .18);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            color: #e2e8f0;
        }

        .stat-card {
            padding: 20px;
        }

        .table-card {
            overflow: hidden;
        }

        .table-card .table,
        .table-card .table-light,
        .table-card .bg-light {
            --bs-table-bg: transparent;
            --bs-table-color: #e2e8f0;
            background: transparent !important;
            color: #e2e8f0;
        }

        .table-card .table> :not(caption)>*>* {
            border-color: rgba(148, 163, 184, .16);
        }

        .text-muted {
            color: #9fb0c2 !important;
        }

        .modal-content {
            color: #e2e8f0;
            background: rgba(9, 25, 42, .88);
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 16px;
            box-shadow: 0 24px 80px rgba(0, 0, 0, .42);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        .modal-header,
        .modal-footer {
            border-color: rgba(148, 163, 184, .16);
        }

        .modal-header,
        .modal-footer,
        .modal-body {
            background: transparent !important;
        }

        .modal .form-control,
        .modal .form-select,
        .modal textarea {
            color: #e2e8f0;
            background: rgba(2, 9, 17, .62);
            border-color: rgba(148, 163, 184, .25);
        }

        .modal .form-control:focus,
        .modal .form-select:focus,
        .modal textarea:focus {
            color: #fff;
            background: rgba(2, 9, 17, .8);
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, .14);
        }

        .modal .form-control::placeholder,
        .modal textarea::placeholder {
            color: #7890a6;
        }

        .modal .btn-light {
            color: #dbeafe;
            background: rgba(148, 163, 184, .14);
            border-color: rgba(148, 163, 184, .24);
        }

        .modal .btn-light:hover {
            color: #fff;
            background: rgba(148, 163, 184, .24);
        }

        .modal .btn-close {
            filter: invert(1) grayscale(1);
            opacity: .8;
        }

        .modal-backdrop.show {
            opacity: .72;
        }

        .modal .bg-dark {
            background: rgba(5, 15, 28, .9) !important;
        }

        .modal .table-light {
            --bs-table-bg: rgba(148, 163, 184, .1);
            --bs-table-color: #e2e8f0;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }

        /* Show sidebar backdrop only when menu is active on mobile */
        body.sidebar-open .sidebar-backdrop {
            display: block;
        }

        /* Responsive Mobile Layout */
        @media (max-width: 991.98px) {
            .nav-toggle-btn {
                display: flex !important;
            }

            .sidebar {
                left: -var(--sidebar-width);
            }

            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            body.sidebar-open .sidebar {
                left: 0;
            }

            .navbar-brand-box {
                width: auto;
            }
        }

        @media (min-width: 992px) {
            body.sidebar-open .sidebar {
                left: 0;
            }

            body.sidebar-open .sidebar-backdrop {
                display: none;
            }
        }
    </style>
    <script src="../assets/js/theme.js"></script>
</head>

<body>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Header Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark admin-navbar fixed-top p-0">
        <div class="container-fluid p-0 h-100 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center h-100">
                <div class="navbar-brand-box">
                    <span class="brand-mark"><i class="bi bi-mortarboard-fill"></i> AIT</span>
                    <span class="text-white fw-bold fs-6 d-none d-sm-inline"><?= $is_super_admin ? 'Super Admin' : htmlspecialchars($current_admin['role_title']); ?></span>
                </div>
                <button type="button" class="nav-toggle-btn" id="sidebarToggle" aria-label="Toggle navigation" aria-expanded="false">
                    <i class="bi bi-list"></i>
                </button>
            </div>
            <div class="pe-3 pe-md-4 text-white d-flex align-items-center gap-2 gap-md-3">
                <span class="small text-slate-300">
                    <i class="bi bi-shield-lock me-1"></i><span class="d-none d-sm-inline"><?= htmlspecialchars($current_admin['name']); ?></span>
                </span>
                <button class="btn btn-sm btn-outline-light border-0" data-bs-toggle="modal" data-bs-target="#profileModal" title="Settings">
                    <i class="bi bi-gear"></i>
                </button>
                <form method="post" action="logout.php" class="d-inline logout-form">
                    <?php echo ait_csrf_field(); ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger border-0 logout-link" title="Sign Out"><i class="bi bi-power"></i></button>
                </form>
            </div>
        </div>
    </nav>

    <!-- Sidebar Navigation -->
    <aside class="sidebar" id="sidebarMenu">
        <ul class="sidebar-menu">
            <li class="sidebar-item active">
                <a class="nav-tab-link" data-target="section-dashboard"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
            </li>
            <li class="sidebar-item">
                <a class="nav-tab-link" data-target="section-applications"><i class="bi bi-file-earmark-text"></i><span>Applications</span></a>
            </li>
            <li class="sidebar-item">
                <a class="nav-tab-link" data-target="section-reports"><i class="bi bi-flag"></i><span>Student Reports</span></a>
            </li>
            <li class="sidebar-item">
                <a class="nav-tab-link" data-target="section-semesters"><i class="bi bi-calendar3"></i><span>Semester Controls</span></a>
            </li>
            <?php if ($is_super_admin || in_array('manage_teachers', $current_admin_permissions, true)): ?>
                <li class="sidebar-item">
                    <a href="teachers.php"><i class="bi bi-person-video3"></i><span>Manage Teachers</span></a>
                </li>
            <?php endif; ?>
            <?php if ($is_super_admin || in_array('manage_staff', $current_admin_permissions, true)): ?>
                <li class="sidebar-item">
                    <a href="staff.php"><i class="bi bi-person-badge"></i><span>Manage Staff</span></a>
                </li>
            <?php endif; ?>
            <?php if ($is_super_admin || in_array('manage_departments', $current_admin_permissions, true)): ?>
                <li class="sidebar-item">
                    <a href="catalog.php"><i class="bi bi-building"></i><span>Departments &amp; Subjects</span></a>
                </li>
            <?php endif; ?>
            <?php if ($is_super_admin || in_array('manage_timetable', $current_admin_permissions, true)): ?>
                <li class="sidebar-item">
                    <a href="timetable.php"><i class="bi bi-calendar-week"></i><span>Timetable</span></a>
                </li>
            <?php endif; ?>
            <?php if ($is_super_admin): ?>
                <li class="sidebar-item">
                    <a class="nav-tab-link" data-target="section-subadmins"><i class="bi bi-people"></i><span>Manage Sub-Admins</span></a>
                </li>
                <li class="sidebar-item">
                    <a class="nav-tab-link" data-target="section-site-content"><i class="bi bi-pencil-square"></i><span>Public Site Content</span></a>
                </li>
            <?php endif; ?>
            <li class="sidebar-item">
                <form method="post" action="logout.php" class="logout-form">
                    <?php echo ait_csrf_field(); ?>
                    <button type="submit" class="sidebar-item-button logout-link"><i class="bi bi-box-arrow-right"></i><span>Sign Out</span></button>
                </form>
            </li>
        </ul>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content">
        <?php if (!empty($msg)): ?>
            <div class="alert alert-<?= $msg_type; ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($msg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- SECTION 1: DASHBOARD OVERVIEW -->
        <div id="section-dashboard" class="content-section">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h4 class="fw-bold m-0">Admission Control Center</h4>
                    <small class="text-muted">Role: <strong><?= htmlspecialchars($current_admin['role_title']); ?></strong></small>
                </div>
                <?php if (!$is_super_admin): ?>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#transferModal">
                            <i class="bi bi-arrow-left-right me-1"></i> Transfer Access
                        </button>
                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteSelfModal">
                            <i class="bi bi-trash me-1"></i> Delete Account
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Metric Stat Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="stat-card d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small fw-medium">Total Apps</div>
                            <h3 class="fw-bold m-0 mt-1"><?= $total_apps; ?></h3>
                        </div>
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-folder2-open"></i></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small fw-medium">Review Queue</div>
                            <h3 class="fw-bold m-0 mt-1"><?= $pending_apps + $challan_apps; ?></h3>
                        </div>
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-hourglass-split"></i></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small fw-medium">Approved</div>
                            <h3 class="fw-bold m-0 mt-1"><?= $approved_apps; ?></h3>
                        </div>
                        <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle"></i></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card d-flex align-items-center justify-content-between">
                        <div>
                            <div class="text-muted small fw-medium">Rejected</div>
                            <h3 class="fw-bold m-0 mt-1"><?= $rejected_apps; ?></h3>
                        </div>
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-x-circle"></i></div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-lg-7">
                    <div class="stat-card h-100">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <div class="text-muted small fw-medium">Processing Progress</div>
                                <h5 class="fw-bold mb-0">Application Review Flow</h5>
                            </div>
                            <span class="badge bg-dark"><?= $review_progress; ?>%</span>
                        </div>
                        <div class="progress" style="height: 12px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $review_progress; ?>%;" aria-valuenow="<?= $review_progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-2 small text-muted">
                            <span>Submitted</span>
                            <span>Challan</span>
                            <span>Decision</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="stat-card h-100">
                        <div class="text-muted small fw-medium mb-1">Test Slip Readiness</div>
                        <h5 class="fw-bold mb-2"><?= $slip_ready_percent; ?>%</h5>
                        <div class="progress" style="height: 12px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $slip_ready_percent; ?>%;" aria-valuenow="<?= $slip_ready_percent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <p class="small text-muted mt-2 mb-0">Approved applications are the only ones that can generate a test slip.</p>
                    </div>
                </div>
            </div>
        </div>
        <!-- SECTION 2: APPLICATIONS TAB -->
        <div id="section-applications" class="content-section d-none">
            <div class="table-card mb-4">
                <div class="p-3 border-bottom bg-light">
                    <h6 class="fw-bold m-0">Submitted Student Applications</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle m-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Applicant</th>
                                <th>CNIC</th>
                                <th>Program Pref</th>
                                <th>Challan</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($applications_result && $applications_result->num_rows > 0): ?>
                                <?php while ($row = $applications_result->fetch_assoc()):
                                    $challan_paid = !empty($row['challan_pic']);
                                    $status = strtolower($row['status'] ?? 'applied');
                                    $badgeClass = match ($status) {
                                        'approved' => 'bg-success',
                                        'rejected' => 'bg-danger',
                                        default => 'bg-warning text-dark'
                                    };
                                ?>
                                    <tr>
                                        <td>#<?= $row['id']; ?></td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($row['full_name'] ?? 'N/A'); ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($row['student_email'] ?? $row['email'] ?? ''); ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($row['cnic'] ?? 'N/A'); ?></td>
                                        <td><?= htmlspecialchars($row['pref_1'] ?? $row['department'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if ($challan_paid): ?>
                                                <span class="badge bg-success-subtle text-success border border-success"><i class="bi bi-check-circle me-1"></i> Paid</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary border"><i class="bi bi-clock me-1"></i> Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $badgeClass; ?>"><?= strtoupper($status); ?></span>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-info text-white me-1" data-bs-toggle="modal" data-bs-target="#appDetailModal<?= $row['id']; ?>" title="View Full Details">
                                                <i class="bi bi-eye"></i> Review
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- FULL APPLICATION DETAIL MODAL -->
                                    <div class="modal fade" id="appDetailModal<?= $row['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                            <div class="modal-content">
                                                <div class="modal-header bg-dark text-white">
                                                    <h5 class="modal-title fs-6 fw-bold">Application Details — #<?= $row['id']; ?> (<?= htmlspecialchars($row['full_name'] ?? 'N/A'); ?>)</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body text-start">

                                                    <!-- Personal Information -->
                                                    <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="bi bi-person-vcard me-2"></i>Personal Information</h6>
                                                    <div class="row g-3 mb-4 small">
                                                        <div class="col-md-4"><strong>Full Name:</strong><br><?= htmlspecialchars($row['full_name'] ?? 'N/A'); ?></div>
                                                        <div class="col-md-4"><strong>Father Name:</strong><br><?= htmlspecialchars($row['father_name'] ?? 'N/A'); ?></div>
                                                        <div class="col-md-4"><strong>Student CNIC:</strong><br><?= htmlspecialchars($row['cnic'] ?? 'N/A'); ?></div>
                                                        <div class="col-md-4"><strong>Father CNIC:</strong><br><?= htmlspecialchars($row['father_cnic'] ?? 'N/A'); ?></div>
                                                        <div class="col-md-4"><strong>DOB:</strong><br><?= htmlspecialchars($row['dob'] ?? 'N/A'); ?></div>
                                                        <div class="col-md-4"><strong>Gender:</strong><br><?= htmlspecialchars($row['gender'] ?? 'N/A'); ?></div>
                                                        <div class="col-md-4"><strong>Phone:</strong><br><?= htmlspecialchars($row['phone'] ?? 'N/A'); ?></div>
                                                        <div class="col-md-4"><strong>Email:</strong><br><?= htmlspecialchars($row['student_email'] ?? $row['email'] ?? 'N/A'); ?></div>
                                                        <div class="col-md-4"><strong>Domicile / District:</strong><br><?= htmlspecialchars($row['domicile_province'] ?? 'N/A'); ?> / <?= htmlspecialchars($row['district_city'] ?? 'N/A'); ?></div>
                                                    </div>

                                                    <!-- Academic Information -->
                                                    <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="bi bi-book me-2"></i>Academic Marks</h6>
                                                    <div class="table-responsive mb-4">
                                                        <table class="table table-sm table-bordered text-center small">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th>Level</th>
                                                                    <th>Board</th>
                                                                    <th>Roll No</th>
                                                                    <th>Marks Obtained</th>
                                                                    <th>Total Marks</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <tr>
                                                                    <td><strong>Matriculation</strong></td>
                                                                    <td><?= htmlspecialchars($row['matric_board'] ?? 'N/A'); ?></td>
                                                                    <td><?= htmlspecialchars($row['matric_roll'] ?? 'N/A'); ?></td>
                                                                    <td><?= htmlspecialchars($row['matric_obtained_marks'] ?? 'N/A'); ?></td>
                                                                    <td><?= htmlspecialchars($row['matric_total_marks'] ?? 'N/A'); ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <td><strong>Intermediate</strong></td>
                                                                    <td><?= htmlspecialchars($row['inter_board'] ?? 'N/A'); ?></td>
                                                                    <td><?= htmlspecialchars($row['inter_roll'] ?? 'N/A'); ?></td>
                                                                    <td><?= htmlspecialchars($row['inter_obtained_marks'] ?? 'N/A'); ?></td>
                                                                    <td><?= htmlspecialchars($row['inter_total_marks'] ?? 'N/A'); ?></td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>

                                                    <!-- Program Choice & Challan -->
                                                    <h6 class="fw-bold text-primary border-bottom pb-2 mb-3"><i class="bi bi-receipt me-2"></i>Program Choice & Challan Receipt</h6>
                                                    <div class="row g-3 small">
                                                        <div class="col-md-6">
                                                            <p class="mb-1"><strong>Campus:</strong> <?= htmlspecialchars($row['campus'] ?? 'N/A'); ?></p>
                                                            <p class="mb-1"><strong>1st Preference:</strong> <?= htmlspecialchars($row['pref_1'] ?? $row['department'] ?? 'N/A'); ?></p>
                                                            <p class="mb-1"><strong>Shift:</strong> <?= htmlspecialchars($row['shift'] ?? 'Morning'); ?></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <p class="fw-bold mb-2">Paid Bank Challan:</p>
                                                            <?php if (!empty($row['challan_pic'])): ?>
                                                                <a class="btn btn-sm btn-outline-primary" href="../student/download_file.php?path=<?= rawurlencode($row['challan_pic']); ?>" target="_blank"><i class="bi bi-file-earmark-arrow-down me-1"></i> View uploaded challan slip</a>
                                                            <?php else: ?>
                                                                <span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i> No challan slip uploaded yet.</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <h6 class="fw-bold text-primary border-bottom pb-2 mb-3 mt-4"><i class="bi bi-chat-left-text me-2"></i>Review Note</h6>
                                                    <form method="POST" action="dashboard.php" class="mt-3">
                                                        <?php echo ait_csrf_field(); ?>
                                                        <input type="hidden" name="action_type" value="update_status">
                                                        <input type="hidden" name="app_id" value="<?= $row['id']; ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label small fw-bold">Message for applicant</label>
                                                            <textarea class="form-control" name="review_note" rows="3" placeholder="Explain what needs correction or why the application was rejected."><?= htmlspecialchars($row['review_note'] ?? ''); ?></textarea>
                                                        </div>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <button type="submit" name="status" value="approved" class="btn btn-success" onclick="return confirm('Approve application #<?= $row['id']; ?>?');">Approve</button>
                                                            <button type="submit" name="status" value="rejected" class="btn btn-danger" onclick="return confirm('Reject application #<?= $row['id']; ?>?');">Reject</button>
                                                        </div>
                                                    </form>

                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No student applications submitted yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="section-reports" class="content-section d-none">
            <div class="table-card">
                <div class="p-3 border-bottom bg-light">
                    <h6 class="fw-bold m-0">Student Reports</h6>
                    <p class="small text-muted mb-0 mt-1">Issues submitted from enrolled student dashboards.</p>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle m-0">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Type</th>
                                <th>Subject</th>
                                <th>Message</th>
                                <th>Status</th>
                                <th>Received</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($reports_result && $reports_result->num_rows > 0): ?>
                                <?php while ($report = $reports_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($report['student_name']); ?></strong><br><small class="text-muted"><?= htmlspecialchars($report['student_code']); ?></small></td>
                                        <td><span class="badge bg-warning text-dark"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $report['report_type']))); ?></span></td>
                                        <td><?= htmlspecialchars($report['subject']); ?></td>
                                        <td class="text-wrap" style="min-width:260px;max-width:420px;"><?= nl2br(htmlspecialchars($report['message'])); ?></td>
                                        <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $report['status']))); ?></td>
                                        <td><?= htmlspecialchars(date('d M Y H:i', strtotime($report['created_at']))); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?><tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No student reports have been submitted.</td>
                                </tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="section-semesters" class="content-section d-none">
            <div class="table-card mb-4">
                <div class="p-3 border-bottom bg-light">
                    <h6 class="fw-bold m-0">Semester access controls</h6>
                    <p class="small text-muted mb-0 mt-1">Only admin controls fee challans, exam eligibility, attendance overrides, and exam-slip release.</p>
                </div>
                <div class="p-3">
                    <form method="post" class="row g-2 align-items-end"><input type="hidden" name="action_type" value="save_semester_control"><?php echo ait_csrf_field(); ?><div class="col-md-3"><label class="form-label small">Student</label><select name="student_id" class="form-select" required><?php if ($semester_students_result): while ($semester_student = $semester_students_result->fetch_assoc()): ?><option value="<?= $semester_student['id']; ?>"><?= htmlspecialchars($semester_student['student_code'] . ' · ' . $semester_student['name']); ?></option><?php endwhile;
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        endif; ?></select></div>
                        <div class="col-md-2"><label class="form-label small">Semester</label><select name="semester" class="form-select"><?php for ($term = 1; $term <= 8; $term++): ?><option value="<?= $term; ?>">Semester <?= $term; ?></option><?php endfor; ?></select></div>
                        <div class="col-md-2"><label class="form-label small">Student current semester</label><select name="current_semester" class="form-select"><?php for ($term = 1; $term <= 8; $term++): ?><option value="<?= $term; ?>"><?= $term; ?></option><?php endfor; ?></select></div>
                        <div class="col-md-2"><label class="form-label small">Override attendance %</label><input class="form-control" type="number" name="attendance_override_percent" min="0" max="100" step="0.01" placeholder="Optional"></div>
                        <div class="col-md-5 d-flex flex-wrap gap-3 align-items-center"><label class="form-check"><input class="form-check-input" type="checkbox" name="semester_fee_enabled" value="1"> Fee challan</label><label class="form-check"><input class="form-check-input" type="checkbox" name="exam_challan_enabled" value="1"> Exam challan</label><label class="form-check"><input class="form-check-input" type="checkbox" name="exam_slip_enabled" value="1"> Exam slip</label><label class="form-check"><input class="form-check-input" type="checkbox" name="attendance_override" value="1"> Attendance override</label><button class="btn btn-primary" type="submit">Save controls</button></div>
                    </form>
                </div>
            </div>
            <div class="table-card mb-4">
                <div class="p-3 border-bottom bg-light">
                    <h6 class="fw-bold m-0">Uploaded semester challans</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle m-0">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Semester</th>
                                <th>Type</th>
                                <th>Receipt</th>
                                <th>Review</th>
                            </tr>
                        </thead>
                        <tbody><?php if ($semester_challans_result && $semester_challans_result->num_rows): while ($term_challan = $semester_challans_result->fetch_assoc()): ?><tr>
                                        <td><?= htmlspecialchars($term_challan['student_code'] . ' · ' . $term_challan['student_name']); ?></td>
                                        <td><?= (int) $term_challan['semester']; ?></td>
                                        <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $term_challan['challan_type']))); ?></td>
                                        <td><?php if ($term_challan['receipt_file']): ?><a href="../<?= htmlspecialchars(ltrim($term_challan['receipt_file'], './')); ?>" target="_blank">View receipt ↗</a><?php endif; ?></td>
                                        <td>
                                            <form method="post" class="d-flex gap-2"><input type="hidden" name="action_type" value="review_semester_challan"><?php echo ait_csrf_field(); ?><input type="hidden" name="challan_id" value="<?= $term_challan['id']; ?>"><input class="form-control form-control-sm" name="decline_reason" placeholder="Reason if rejecting"><button class="btn btn-sm btn-success" name="decision" value="verified">Accept</button><button class="btn btn-sm btn-danger" name="decision" value="rejected">Reject</button></form>
                                        </td>
                                    </tr><?php endwhile;
                                    else: ?><tr>
                                    <td colspan="5" class="text-center text-muted py-3">No uploaded semester challans awaiting review.</td>
                                </tr><?php endif; ?></tbody>
                    </table>
                </div>
            </div>
            <div class="table-card">
                <div class="p-3 border-bottom bg-light">
                    <h6 class="fw-bold m-0">Attendance appeals</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle m-0">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Semester</th>
                                <th>Reason</th>
                                <th>Review</th>
                            </tr>
                        </thead>
                        <tbody><?php if ($attendance_appeals_result && $attendance_appeals_result->num_rows): while ($appeal = $attendance_appeals_result->fetch_assoc()): ?><tr>
                                        <td><?= htmlspecialchars($appeal['student_code'] . ' · ' . $appeal['student_name']); ?></td>
                                        <td><?= (int) $appeal['semester']; ?></td>
                                        <td><?= nl2br(htmlspecialchars($appeal['reason'])); ?></td>
                                        <td>
                                            <form method="post" class="d-flex gap-2"><input type="hidden" name="action_type" value="review_attendance_appeal"><?php echo ait_csrf_field(); ?><input type="hidden" name="appeal_id" value="<?= $appeal['id']; ?>"><input class="form-control form-control-sm" name="admin_note" placeholder="Admin note"><button class="btn btn-sm btn-success" name="decision" value="approved">Approve</button><button class="btn btn-sm btn-danger" name="decision" value="rejected">Reject</button></form>
                                        </td>
                                    </tr><?php endwhile;
                                    else: ?><tr>
                                    <td colspan="4" class="text-center text-muted py-3">No attendance appeals awaiting review.</td>
                                </tr><?php endif; ?></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SECTION 3: SUB-ADMINS TAB (Super Admin Only) -->
        <?php if ($is_super_admin): ?>
            <div id="section-subadmins" class="content-section d-none">
                <div class="table-card">
                    <div class="p-3 border-bottom bg-light d-flex align-items-center justify-content-between">
                        <h6 class="fw-bold m-0">Sub-Admin Management</h6>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createSubadminModal">
                            <i class="bi bi-person-plus me-1"></i> Create Sub-Admin
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle m-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role Title</th>
                                    <th>Permissions</th>
                                    <th>Access Duration</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($subadmins_result && $subadmins_result->num_rows > 0): ?>
                                    <?php while ($sub = $subadmins_result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($sub['name']); ?></div>
                                            </td>
                                            <td><?= htmlspecialchars($sub['email']); ?></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($sub['role_title']); ?></span></td>
                                            <td>
                                                <?php
                                                $sub_perm_stmt = $conn->prepare('SELECT permission_key FROM admin_permissions WHERE admin_id = ?');
                                                $sub_perm_stmt->bind_param('i', $sub['id']);
                                                $sub_perm_stmt->execute();
                                                $sub_perm_rows = $sub_perm_stmt->get_result();
                                                $sub_perm_list = [];
                                                while ($sp = $sub_perm_rows->fetch_assoc()) {
                                                    $sub_perm_list[] = $sp['permission_key'];
                                                }
                                                $sub_perm_stmt->close();
                                                echo $sub_perm_list === [] ? '<span class="text-muted small">None</span>' : implode(' ', array_map(static fn($p) => '<span class="badge bg-info text-dark me-1 mb-1">' . htmlspecialchars($p) . '</span>', $sub_perm_list));
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                if (empty($sub['expires_at'])) {
                                                    echo '<span class="badge bg-success">Lifetime</span>';
                                                } else {
                                                    $is_expired = strtotime($sub['expires_at']) <= time();
                                                    if ($is_expired) {
                                                        echo '<span class="badge bg-danger">Expired</span>';
                                                    } else {
                                                        echo '<span class="badge bg-info text-dark">Expires: ' . date('Y-m-d H:i', strtotime($sub['expires_at'])) . '</span>';
                                                    }
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <form method="POST" action="dashboard.php" onsubmit="return confirm('Terminate this sub-admin account?');" class="d-inline">
                                                    <?php echo ait_csrf_field(); ?>
                                                    <input type="hidden" name="action_type" value="terminate_subadmin">
                                                    <input type="hidden" name="target_admin_id" value="<?= $sub['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i> Terminate</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No sub-admins created yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div id="section-site-content" class="content-section d-none">
                <div class="table-card p-4">
                    <div class="mb-4">
                        <h5 class="fw-bold mb-1">Public site content</h5>
                        <p class="text-muted small mb-0">Update the homepage messaging and admissions ribbon without editing PHP files.</p>
                    </div>
                    <form method="POST" action="dashboard.php" class="row g-3">
                        <?php echo ait_csrf_field(); ?>
                        <input type="hidden" name="action_type" value="save_site_content">
                        <div class="col-md-6"><label class="form-label small fw-bold">Homepage eyebrow</label><input class="form-control" name="home_eyebrow" value="<?= htmlspecialchars($site_content['home_eyebrow'] ?? 'Ahmer Institute for Technology'); ?>" required></div>
                        <div class="col-md-6"><label class="form-label small fw-bold">Admissions ribbon</label><input class="form-control" name="admissions_ribbon" value="<?= htmlspecialchars($site_content['admissions_ribbon'] ?? 'Fall 2026 admissions are open'); ?>" required></div>
                        <div class="col-12"><label class="form-label small fw-bold">Homepage headline</label><input class="form-control" name="home_headline" value="<?= htmlspecialchars($site_content['home_headline'] ?? 'Build a future that feels possible.'); ?>" required></div>
                        <div class="col-12"><label class="form-label small fw-bold">Homepage introduction</label><textarea class="form-control" name="home_intro" rows="4" required><?= htmlspecialchars($site_content['home_intro'] ?? 'A forward-looking university for people who want to think clearly, make boldly, and leave a mark that matters.'); ?></textarea></div>
                        <div class="col-12"><button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i> Save public content</button></div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- MODALS -->
    <?php if ($is_super_admin): ?>
        <div class="modal fade" id="createSubadminModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="dashboard.php">
                        <?php echo ait_csrf_field(); ?>
                        <input type="hidden" name="action_type" value="create_subadmin">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold">Create New Sub-Admin</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Full Name</label>
                                <input type="text" name="name" class="form-control" required placeholder="e.g. John Doe">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Email Address</label>
                                <input type="email" name="email" class="form-control" required placeholder="subadmin@ait.edu.pk">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Password</label>
                                <input type="password" name="password" class="form-control" required placeholder="Password">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Custom Role Title</label>
                                <input type="text" name="role_title" class="form-control" required placeholder="e.g. Admission Verifier">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Access Duration (Days)</label>
                                <input type="number" name="duration_days" class="form-control" min="0" value="7" placeholder="0 for Unlimited">
                                <div class="form-text">Set to 0 for lifetime access.</div>
                            </div>
                            <div class="mb-1">
                                <label class="form-label small fw-bold">Permissions</label>
                                <div class="form-text mb-2">Only the checked areas will be usable by this sub-admin.</div>
                                <?php foreach (AIT_PERMISSIONS as $perm_key => $perm_desc): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="<?= htmlspecialchars($perm_key); ?>" id="perm_<?= htmlspecialchars($perm_key); ?>">
                                        <label class="form-check-label small" for="perm_<?= htmlspecialchars($perm_key); ?>"><?= htmlspecialchars($perm_desc); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create Account</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="modal fade" id="profileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="dashboard.php">
                    <?php echo ait_csrf_field(); ?>
                    <input type="hidden" name="action_type" value="update_profile">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Account Credentials</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Name</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($current_admin['name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($current_admin['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">New Password (leave blank to keep current)</label>
                            <input type="password" name="password" class="form-control">
                        </div>
                        <?php if (!$is_super_admin): ?>
                            <div class="mb-1">
                                <label class="form-label small fw-bold">Your permissions</label>
                                <div class="form-text mb-2">Granted by a super admin. Contact them to request changes.</div>
                                <?php if ($current_admin_permissions === []): ?>
                                    <span class="badge bg-secondary">No permissions granted yet</span>
                                <?php else: ?>
                                    <?php foreach ($current_admin_permissions as $perm_key): ?>
                                        <span class="badge bg-info text-dark me-1 mb-1"><?= htmlspecialchars(AIT_PERMISSIONS[$perm_key] ?? $perm_key); ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (!$is_super_admin): ?>
        <div class="modal fade" id="transferModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="dashboard.php">
                        <?php echo ait_csrf_field(); ?>
                        <input type="hidden" name="action_type" value="transfer_subadmin">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold text-warning">Transfer Sub-Adminship</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-muted">Transfer your active access to someone else. You will be logged out immediately.</p>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">New Assignee Name</label>
                                <input type="text" name="transfer_name" class="form-control" required placeholder="New Name">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">New Assignee Email</label>
                                <input type="email" name="transfer_email" class="form-control" required placeholder="newadmin@ait.edu.pk">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">New Assignee Password</label>
                                <input type="password" name="transfer_password" class="form-control" required placeholder="New Password">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning">Confirm Transfer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="deleteSelfModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="dashboard.php">
                        <?php echo ait_csrf_field(); ?>
                        <input type="hidden" name="action_type" value="delete_self">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold text-danger">Delete Account</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>Are you sure you want to permanently delete your sub-admin account?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Yes, Delete Account</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script nonce="<?php echo htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        document.addEventListener('DOMContentLoaded', function() {
            // 1. Tab Navigation Handler
            const navLinks = document.querySelectorAll('.nav-tab-link');
            const sections = document.querySelectorAll('.content-section');

            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();

                    // Remove active status from all items
                    document.querySelectorAll('.sidebar-item').forEach(item => item.classList.remove('active'));
                    this.parentElement.classList.add('active');

                    // Hide all content sections
                    sections.forEach(sec => sec.classList.add('d-none'));

                    // Show selected target section
                    const targetId = this.getAttribute('data-target');
                    const targetSection = document.getElementById(targetId);
                    if (targetSection) {
                        targetSection.classList.remove('d-none');
                    }

                    // Close mobile menu if open
                    document.body.classList.remove('sidebar-open');
                });
            });

            // 2. Mobile Sidebar Toggle
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarBackdrop = document.getElementById('sidebarBackdrop');
            let allowNavigation = false;
            const dashboardGuardActive = true;

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    document.body.classList.toggle('sidebar-open');
                    this.setAttribute('aria-expanded', document.body.classList.contains('sidebar-open') ? 'true' : 'false');
                });
            }

            if (sidebarBackdrop) {
                sidebarBackdrop.addEventListener('click', function() {
                    document.body.classList.remove('sidebar-open');
                });
            }

            document.querySelectorAll('.logout-link').forEach(function(link) {
                link.addEventListener('click', function(event) {
                    if (!window.confirm('Sign out of the admin dashboard? Unsaved form values will be lost.')) {
                        event.preventDefault();
                        return;
                    }
                    allowNavigation = true;
                });
            });

            let formDirty = false;
            document.querySelectorAll('form').forEach(function(form) {
                form.addEventListener('input', function() {
                    formDirty = true;
                });
                form.addEventListener('submit', function() {
                    formDirty = false;
                    allowNavigation = true;
                });
            });
            window.addEventListener('beforeunload', function(event) {
                if ((dashboardGuardActive || formDirty) && !allowNavigation) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });

            window.addEventListener('resize', function() {
                if (window.innerWidth >= 992) document.body.classList.remove('sidebar-open');
            });
        });
    </script>
</body>

</html>
</body>

</html>
