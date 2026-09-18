<?php
require_once __DIR__ . '/backend/session.php';
ait_start_secure_session();
if (!isset($_SESSION['student_id'])) {
    header('Location: student-login');
    exit;
}
require_once __DIR__ . '/backend/data.php';
require_once __DIR__ . '/backend/security.php';
require_once __DIR__ . '/backend/site.php';

$student_id = (int) $_SESSION['student_id'];
$csp_nonce = ait_bootstrap_security();
$message = '';
$message_type = 'success';
$selected_semester = max(1, min(8, (int) ($_GET['semester'] ?? 1)));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_report') {
    ait_validate_csrf_post();
    ait_rate_limit('student-report', 5, 900);
    $report_type = $_POST['report_type'] ?? 'other';
    $report_subject = trim((string) ($_POST['report_subject'] ?? ''));
    $report_message = trim((string) ($_POST['report_message'] ?? ''));
    $allowed_report_types = ['attendance', 'marks', 'fee', 'profile', 'technical', 'other'];
    if (!in_array($report_type, $allowed_report_types, true) || $report_subject === '' || $report_message === '') {
        $message = 'Choose a report type and describe the issue before sending it.';
        $message_type = 'danger';
    } else {
        $report_stmt = $conn->prepare('INSERT INTO student_report_requests (student_id, report_type, subject, message) VALUES (?, ?, ?, ?)');
        $report_stmt->bind_param('isss', $student_id, $report_type, $report_subject, $report_message);
        $report_stmt->execute();
        $report_stmt->close();
        $message = 'Your report was sent to student services.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_appeal') {
    ait_validate_csrf_post();
    ait_rate_limit('attendance-appeal', 3, 900);
    $appeal_semester = max(1, min(8, (int) ($_POST['semester'] ?? 1)));
    $appeal_reason = trim((string) ($_POST['appeal_reason'] ?? ''));
    if ($appeal_reason === '') {
        $message = 'Please explain why you are requesting an attendance review.';
        $message_type = 'danger';
    } else {
        $appeal_stmt = $conn->prepare("INSERT INTO attendance_appeals (student_id, semester, reason) VALUES (?, ?, ?)");
        $appeal_stmt->bind_param('iis', $student_id, $appeal_semester, $appeal_reason);
        $appeal_stmt->execute();
        $appeal_stmt->close();
        $message = 'Your attendance appeal was sent to the admin for review.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    ait_validate_csrf_post();
    $current_password = (string) ($_POST['current_password'] ?? '');
    $new_password = (string) ($_POST['new_password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');
    $password_stmt = $conn->prepare('SELECT password FROM students WHERE id = ? LIMIT 1');
    $password_stmt->bind_param('i', $student_id);
    $password_stmt->execute();
    $password_row = $password_stmt->get_result()->fetch_assoc();
    $password_stmt->close();
    if (!$password_row || !password_verify($current_password, $password_row['password'])) {
        $message = 'Your current password is not correct.';
        $message_type = 'danger';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,64}$/', $new_password) || $new_password !== $confirm_password) {
        $message = 'Use 8-64 characters with upper, lower, and number, and make both new passwords match.';
        $message_type = 'danger';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_password = $conn->prepare('UPDATE students SET password = ?, must_change_password = 0 WHERE id = ?');
        $update_password->bind_param('si', $hashed_password, $student_id);
        $update_password->execute();
        $update_password->close();
        $message = 'Password updated successfully.';
    }
}

$student_stmt = $conn->prepare('SELECT s.name, s.email, s.student_code, s.department_code, s.admission_year, s.current_semester, s.address AS student_address, s.profile_picture, s.phone AS student_phone, s.cnic AS student_cnic, COALESCE(a.pref_1, a.department, s.department_code) AS program, COALESCE(a.cnic, s.cnic) AS display_cnic, COALESCE(a.phone, s.phone) AS display_phone, COALESCE(a.permanent_address, a.postal_address, s.address) AS display_address, COALESCE(d.file_url, s.profile_picture) AS display_picture FROM students s LEFT JOIN applications a ON a.student_id = s.id AND a.status = \'approved\' LEFT JOIN documents d ON d.application_id = a.id AND d.doc_type = \'profile_picture\' AND d.is_current = 1 WHERE s.id = ? ORDER BY a.id DESC LIMIT 1');
$student_stmt->bind_param('i', $student_id);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();
$student_stmt->close();
if (!$student || empty($student['student_code'])) {
    header('Location: dashboard');
    exit;
}
$current_semester = max(1, min(8, (int) ($student['current_semester'] ?? 1)));
$selected_semester = min($selected_semester, $current_semester);
$semester_control = ['attendance_override' => 0, 'attendance_override_percent' => null, 'appeal_status' => 'none', 'admin_note' => '', 'semester_fee_enabled' => 0, 'exam_challan_enabled' => 0, 'exam_slip_enabled' => 0];
$control_stmt = $conn->prepare('SELECT attendance_override, attendance_override_percent, appeal_status, admin_note, semester_fee_enabled, exam_challan_enabled, exam_slip_enabled FROM student_semesters WHERE student_id = ? AND semester = ? LIMIT 1');
$control_stmt->bind_param('ii', $student_id, $selected_semester);
$control_stmt->execute();
$semester_control = array_merge($semester_control, $control_stmt->get_result()->fetch_assoc() ?: []);
$control_stmt->close();

$subjects = $attendance = $marks = $materials = [];
try {
    $subject_stmt = $conn->prepare('SELECT sub.id, sub.code, sub.name, sub.semester AS current_semester, sub.credit_hours, sub.teacher_name, COALESCE(ROUND(100 * SUM(CASE WHEN att.status IN (\'present\', \'late\') THEN 1 ELSE 0 END) / NULLIF(COUNT(att.id), 0), 0), 0) AS attendance_percent FROM students st JOIN subjects sub ON sub.department_code = st.department_code LEFT JOIN attendance att ON att.student_id = st.id AND att.subject_id = sub.id WHERE st.id = ? AND sub.semester = ? AND sub.is_active = 1 GROUP BY sub.id, sub.code, sub.name, sub.semester, sub.credit_hours, sub.teacher_name ORDER BY sub.code');
    $subject_stmt->bind_param('ii', $student_id, $selected_semester);
    $subject_stmt->execute();
    $subjects = $subject_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $subject_stmt->close();
    $attendance_stmt = $conn->prepare('SELECT sub.code, sub.name, COUNT(att.id) AS total_classes, SUM(att.status IN (\'present\', \'late\')) AS attended FROM attendance att JOIN subjects sub ON sub.id = att.subject_id WHERE att.student_id = ? AND sub.semester = ? GROUP BY sub.id, sub.code, sub.name ORDER BY sub.code');
    $attendance_stmt->bind_param('ii', $student_id, $selected_semester);
    $attendance_stmt->execute();
    $attendance = $attendance_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $attendance_stmt->close();
    $marks_stmt = $conn->prepare('SELECT sub.code, sub.name, em.exam_name, em.marks_obtained, em.total_marks FROM exam_marks em JOIN subjects sub ON sub.id = em.subject_id WHERE em.student_id = ? AND sub.semester = ? AND em.published_at IS NOT NULL ORDER BY em.published_at DESC, sub.code');
    $marks_stmt->bind_param('ii', $student_id, $selected_semester);
    $marks_stmt->execute();
    $marks = $marks_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $marks_stmt->close();
    $materials_stmt = $conn->prepare('SELECT m.title, m.description, m.file_url, m.published_at, COALESCE(sub.code, \'ALL\') AS subject_code FROM study_materials m LEFT JOIN subjects sub ON sub.id = m.subject_id LEFT JOIN students st ON st.id = ? WHERE m.subject_id IS NULL OR sub.department_code = st.department_code GROUP BY m.id, m.title, m.description, m.file_url, m.published_at, sub.code ORDER BY m.published_at DESC');
    $materials_stmt->bind_param('i', $student_id);
    $materials_stmt->execute();
    $materials = $materials_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $materials_stmt->close();
} catch (Throwable $exception) {
    $message = 'Academic records are ready once your department publishes them.';
    $message_type = 'info';
}
$semester_attendance = $attendance ? (int) round(array_sum(array_map(static fn($row) => (int) $row['attended'], $attendance)) / max(1, array_sum(array_map(static fn($row) => (int) $row['total_classes'], $attendance))) * 100) : 0;
if ($semester_control['attendance_override_percent'] !== null) $semester_attendance = (int) round((float) $semester_control['attendance_override_percent']);
$eligible_for_exam = $semester_attendance >= 75 || (int) $semester_control['attendance_override'] === 1;
$semester_challans = [];
$semester_challan_stmt = $conn->prepare('SELECT id, challan_type, challan_no, amount, due_date, status, decline_reason, receipt_file FROM semester_challans WHERE student_id = ? AND semester = ? ORDER BY challan_type');
$semester_challan_stmt->bind_param('ii', $student_id, $selected_semester);
$semester_challan_stmt->execute();
$semester_challan_result = $semester_challan_stmt->get_result();
while ($semester_challan = $semester_challan_result->fetch_assoc()) $semester_challans[$semester_challan['challan_type']] = $semester_challan;
$semester_challan_stmt->close();
$challan = null;
try {
    $challan_stmt = $conn->prepare('SELECT a.id AS application_id, a.status AS application_status, c.challan_no, c.amount, c.bank_name, c.due_date, c.status AS challan_status, c.paid_at, a.challan_pic FROM applications a LEFT JOIN challans c ON c.application_id = a.id WHERE a.student_id = ? ORDER BY a.id DESC LIMIT 1');
    $challan_stmt->bind_param('i', $student_id);
    $challan_stmt->execute();
    $challan = $challan_stmt->get_result()->fetch_assoc() ?: null;
    $challan_stmt->close();
} catch (Throwable $exception) {
    $challan = null;
}
$attendance_labels = array_map(static fn(array $row): string => (string) $row['code'], $attendance);
$attendance_values = array_map(static fn(array $row): int => (int) round(((int) $row['attended'] / max(1, (int) $row['total_classes'])) * 100), $attendance);
function student_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<?php ait_public_header('Student Dashboard', 'students'); ?>
<style nonce="<?= student_escape($csp_nonce); ?>">
    .student-portal {
        min-height: 75vh;
        padding: 60px 8vw 90px;
    }

    .student-hero {
        display: flex;
        justify-content: space-between;
        gap: 24px;
        align-items: end;
        margin-bottom: 36px;
    }

    .student-hero h1 {
        font: 600 clamp(38px, 5vw, 68px)/1 "Space Grotesk", sans-serif;
        margin: 0 0 12px;
        letter-spacing: -.04em;
    }

    .student-hero p:not(.eyebrow) {
        color: var(--muted);
        margin: 0;
    }

    .student-id {
        padding: 18px 22px;
        background: var(--teal-dark);
        color: #fff;
        border-left: 3px solid var(--coral);
        min-width: 220px;
    }

    .student-id small {
        display: block;
        color: #b9d7cc;
        font-size: 10px;
        letter-spacing: .12em;
        text-transform: uppercase;
    }

    .student-id strong {
        display: block;
        color: var(--yellow);
        font: 600 25px "Space Grotesk", sans-serif;
        margin-top: 5px;
    }

    .portal-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 34px;
        margin-top: 28px;
    }

    .portal-stat,
    .portal-panel {
        padding: 22px;
        background: var(--glass);
        border: 1px solid var(--glass-border);
        border-radius: 10px;
        box-shadow: 0 16px 38px rgba(7, 61, 61, .07);
    }

    .portal-stat i {
        color: var(--coral);
        font-size: 24px;
    }

    .portal-stat strong {
        display: block;
        font: 600 27px "Space Grotesk", sans-serif;
        margin: 16px 0 4px;
    }

    .portal-stat span {
        color: var(--muted);
        font-size: 12px;
    }

    .portal-layout {
        display: grid;
        grid-template-columns: 1.45fr .8fr;
        gap: 16px;
    }

    .portal-panel+.portal-panel {
        margin-top: 16px;
    }

    .portal-panel h2 {
        font: 600 25px "Space Grotesk", sans-serif;
        margin: 0 0 18px;
    }

    .portal-panel table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }

    .portal-panel th,
    .portal-panel td {
        padding: 12px 8px;
        border-bottom: 1px solid var(--line);
        text-align: left;
    }

    .portal-panel th {
        color: var(--muted);
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: .08em;
    }

    .portal-panel td {
        color: var(--ink);
    }

    .progress-line {
        height: 6px;
        background: var(--line);
        border-radius: 8px;
        overflow: hidden;
        min-width: 70px;
    }

    .progress-line span {
        display: block;
        height: 100%;
        background: var(--teal);
    }

    .material-item {
        padding: 13px 0;
        border-bottom: 1px solid var(--line);
    }

    .material-item strong {
        display: block;
        font-size: 13px;
    }

    .material-item small {
        color: var(--muted);
    }

    .portal-form {
        display: grid;
        gap: 10px;
    }

    .portal-form label {
        color: var(--muted);
        font-size: 11px;
        font-weight: 700;
    }

    .portal-form input {
        width: 100%;
        margin-top: 5px;
        padding: 11px 12px;
        border: 1px solid var(--line);
        border-radius: 6px;
        background: var(--glass);
        color: var(--ink);
    }

    .portal-form button {
        border: 0;
        cursor: pointer;
        justify-content: center;
    }

    .student-nav {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding: 14px 8vw;
        background: var(--teal-dark);
        color: #f3fbf6;
    }

    .student-nav strong {
        color: var(--yellow);
        font-family: "Space Grotesk", sans-serif;
    }

    .student-nav-links {
        display: flex;
        align-items: center;
        gap: 16px;
        font-size: 12px;
    }

    .student-nav-links a {
        color: #d9efdf;
    }

    .student-nav-links a:hover {
        color: var(--yellow);
    }

    .profile-panel {
        display: grid;
        grid-template-columns: 92px 1fr;
        gap: 22px;
        align-items: center;
    }

    .profile-picture {
        width: 92px;
        height: 92px;
        object-fit: cover;
        border-radius: 50%;
        border: 3px solid var(--coral);
        background: var(--teal-dark);
    }

    .profile-placeholder {
        display: grid;
        place-items: center;
        color: var(--yellow);
        font: 600 30px "Space Grotesk", sans-serif;
    }

    .profile-details {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px 22px;
    }

    .profile-details div {
        display: grid;
        gap: 3px;
    }

    .profile-details small {
        color: var(--muted);
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: .1em;
    }

    .profile-details span {
        font-size: 13px;
    }

    .challan-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
    }

    .button-small {
        padding: 10px 13px;
        font-size: 12px;
    }

    .upload-label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 13px;
        background: var(--glass);
        border: 1px solid var(--line);
        color: var(--teal);
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
    }

    .upload-label input {
        display: none;
    }

    .chart-wrap {
        min-height: 250px;
        position: relative;
    }

    .report-form select {
        width: 100%;
        margin-top: 5px;
        padding: 11px 12px;
        border: 1px solid var(--line);
        border-radius: 6px;
        background: var(--glass);
        color: var(--ink);
    }

    .report-form textarea {
        width: 100%;
        margin-top: 5px;
        min-height: 90px;
        padding: 11px 12px;
        border: 1px solid var(--line);
        border-radius: 6px;
        background: var(--glass);
        color: var(--ink);
        resize: vertical;
    }

    .semester-tag {
        display: inline-block;
        margin-top: 5px;
        color: var(--coral);
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: .08em;
    }

    .semester-switcher {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin: 0 0 24px;
    }

    .semester-switcher a {
        padding: 10px 13px;
        border: 1px solid var(--line);
        color: var(--muted);
        font-size: 12px;
        font-weight: 700;
        background: var(--glass);
    }

    .semester-switcher a.active {
        background: var(--teal);
        color: #fff;
        border-color: var(--teal);
    }

    .eligibility-banner {
        padding: 15px 18px;
        margin-bottom: 16px;
        border-left: 3px solid var(--coral);
        background: rgba(239, 106, 80, .1);
        color: var(--ink);
        font-size: 13px;
    }

    .eligibility-banner.is-eligible {
        border-color: var(--teal);
        background: rgba(11, 119, 114, .1);
    }

    .semester-challan-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .semester-challan {
        border: 1px solid var(--line);
        padding: 16px;
    }

    .semester-challan h3 {
        font: 600 18px "Space Grotesk", sans-serif;
        margin: 0 0 8px;
    }

    .semester-challan p {
        color: var(--muted);
        font-size: 12px;
        margin: 0 0 14px;
    }

    .disabled-action {
        opacity: .55;
        cursor: not-allowed;
    }

    .appeal-form {
        margin-top: 14px;
        padding-top: 14px;
        border-top: 1px solid var(--line);
    }

    @media(max-width:800px) {

        .student-nav {
            padding: 14px 5vw;
            align-items: flex-start;
            flex-direction: column;
            gap: 10px;
        }

        .student-hero,
        .portal-layout {
            display: block
        }

        .student-id {
            margin-top: 24px
        }

        .portal-grid {
            grid-template-columns: repeat(2, 1fr)
        }

        .portal-panel {
            margin-top: 16px;
            overflow-x: auto
        }

        .portal-panel table {
            min-width: 500px
        }

        .semester-challan-grid {
            grid-template-columns: 1fr;
        }

        .profile-panel {
            grid-template-columns: 1fr;
            text-align: center;
        }

        .profile-picture {
            margin: auto;
        }

        .profile-details {
            text-align: left;
        }
    }
</style>
<nav class="student-nav" aria-label="Student navigation"><strong><?= student_escape($student['name']); ?> · <?= student_escape($student['student_code']); ?></strong>
    <div class="student-nav-links"><a href="#profile">Profile</a><a href="#payments">Payments</a><a href="#reports">Report an issue</a>
        <form method="post" action="logout" style="display:inline"><?= ait_csrf_field(); ?><button type="submit" style="border:0;background:none;color:inherit;padding:0;cursor:pointer;font:inherit">Sign out ↗</button></form>
    </div>
</nav>
<main class="student-portal">
    <header class="student-hero">
        <div>
            <p class="eyebrow">Enrolled student portal</p>
            <h1>Welcome, <?= student_escape($student['name']); ?>.</h1>
            <p><?= student_escape((string) ($student['program'] ?: 'Your academic journey at AIT')); ?> · <?= student_escape((string) $student['admission_year']); ?></p>
        </div>
        <div class="student-id"><small>Student ID</small><strong><?= student_escape($student['student_code']); ?></strong><small><?= student_escape((string) $student['email']); ?></small></div>
    </header>
    <?php if ($message !== ''): ?><div class="form-alert is-<?= student_escape($message_type); ?>"><?= student_escape($message); ?></div><?php endif; ?>
    <nav class="semester-switcher" aria-label="Semester switcher"><?php for ($semester = 1; $semester <= $current_semester; $semester++): ?><a class="<?= $semester === $selected_semester ? 'active' : ''; ?>" href="student-dashboard?semester=<?= $semester; ?>">Semester <?= $semester; ?><?= $semester === $current_semester ? ' · Current' : ''; ?></a><?php endfor; ?></nav>
    <div class="eligibility-banner <?= $eligible_for_exam ? 'is-eligible' : ''; ?>"><strong>Semester <?= $selected_semester; ?> eligibility:</strong> <?= $semester_attendance; ?>% attendance. <?php if ($eligible_for_exam): ?>You are eligible for the exam process.<?php else: ?>Exam challan and exam slip are locked below the 75% requirement. Submit an appeal to admin if you have a valid reason.<?php endif; ?><?php if ($semester_control['admin_note']): ?><br><span><?= student_escape($semester_control['admin_note']); ?></span><?php endif; ?></div>
    <section class="portal-panel profile-panel" id="profile">
        <?php if (!empty($student['display_picture'])): ?><img class="profile-picture" src="<?= student_escape($student['display_picture']); ?>" alt="Profile picture of <?= student_escape($student['name']); ?>"><?php else: ?><div class="profile-picture profile-placeholder"><?= student_escape(strtoupper(substr($student['name'], 0, 1))); ?></div><?php endif; ?>
        <div class="profile-details">
            <div><small>Full name</small><span><?= student_escape($student['name']); ?></span></div>
            <div><small>Roll number</small><span><?= student_escape($student['student_code']); ?></span></div>
            <div><small>CNIC / NIC</small><span><?= student_escape((string) ($student['display_cnic'] ?: 'Not provided')); ?></span></div>
            <div><small>Phone</small><span><?= student_escape((string) ($student['display_phone'] ?: 'Not provided')); ?></span></div>
            <div><small>Email</small><span><?= student_escape($student['email']); ?></span></div>
            <div><small>Address</small><span><?= student_escape((string) ($student['display_address'] ?: 'Not provided')); ?></span></div>
        </div>
    </section>
    <section class="portal-panel" id="payments">
        <h2>Semester <?= $selected_semester; ?> fee challans</h2>
        <div class="semester-challan-grid">
            <?php foreach (['semester_fee' => 'Semester fee', 'exam_fee' => 'Exam fee'] as $challan_type => $challan_label): $term_challan = $semester_challans[$challan_type] ?? null;
                $is_exam = $challan_type === 'exam_fee'; ?>
                <article class="semester-challan">
                    <h3><?= $challan_label; ?></h3>
                    <p><?php if (!$term_challan || $term_challan['status'] === 'disabled'): ?>Not enabled by admin yet.<?php else: ?><?= student_escape((string) $term_challan['challan_no']); ?> · PKR <?= number_format((float) $term_challan['amount'], 2); ?> · <?= student_escape(ucfirst((string) $term_challan['status'])); ?><?php endif; ?><?php if ($term_challan && $term_challan['decline_reason']): ?><br><strong>Admin reason:</strong> <?= student_escape($term_challan['decline_reason']); ?><?php endif; ?></p><?php if ($term_challan && $term_challan['status'] !== 'disabled' && (!$is_exam || $eligible_for_exam)): ?><a class="button <?= $is_exam ? 'button-coral' : ''; ?> button-small" href="generate_semester_challan.php?semester=<?= $selected_semester; ?>&type=<?= $challan_type; ?>" target="_blank">Print challan <span>↗</span></a><?php if (!in_array($term_challan['status'], ['uploaded', 'verified'], true)): ?><form method="post" action="upload_semester_challan.php" enctype="multipart/form-data" style="display:inline"><?= ait_csrf_field(); ?><input type="hidden" name="semester" value="<?= $selected_semester; ?>"><input type="hidden" name="challan_type" value="<?= $challan_type; ?>"><label class="upload-label">Upload paid copy<input type="file" name="receipt" accept="image/jpeg,image/png,application/pdf" required onchange="this.form.submit()"></label></form><?php else: ?><span class="form-alert is-success" style="margin:0">Submitted for admin verification.</span><?php endif; ?><?php else: ?><span class="disabled-action">Unavailable</span><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if (!$eligible_for_exam && $semester_control['appeal_status'] !== 'pending' && $semester_control['appeal_status'] !== 'approved'): ?><form class="portal-form appeal-form" method="post"><?= ait_csrf_field(); ?><input type="hidden" name="action" value="submit_appeal"><input type="hidden" name="semester" value="<?= $selected_semester; ?>"><label>Attendance appeal reason<textarea name="appeal_reason" required placeholder="Explain your valid reason and supporting details."></textarea></label><button class="button button-small" type="submit">Submit attendance appeal <span>↗</span></button></form><?php elseif ($semester_control['appeal_status'] === 'pending'): ?><p style="color:var(--muted);font-size:12px;margin-top:14px">Your attendance appeal is pending admin review.</p><?php endif; ?>
    </section>
    <section class="portal-grid">
        <div class="portal-stat"><i class="bi bi-journal-bookmark"></i><strong><?= count($subjects); ?></strong><span>Enrolled subjects</span></div>
        <div class="portal-stat"><i class="bi bi-calendar2-check"></i><strong><?= $attendance ? round(array_sum(array_map(static fn($row) => (int) $row['attended'], $attendance)) / max(1, array_sum(array_map(static fn($row) => (int) $row['total_classes'], $attendance))) * 100) . '%' : '—'; ?></strong><span>Overall attendance</span></div>
        <div class="portal-stat"><i class="bi bi-graph-up-arrow"></i><strong><?= count($marks); ?></strong><span>Published marks</span></div>
        <div class="portal-stat"><i class="bi bi-folder2-open"></i><strong><?= count($materials); ?></strong><span>Learning materials</span></div>
    </section>
    <div class="portal-layout">
        <div>
            <section class="portal-panel">
                <h2>Subjects, credit hours & teachers</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Teacher</th>
                            <th>Credits</th>
                            <th>Attendance</th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($subjects as $subject): ?><tr>
                                <td><strong><?= student_escape($subject['code']); ?></strong><br><?= student_escape($subject['name']); ?><span class="semester-tag">Semester <?= (int) $subject['current_semester']; ?></span></td>
                                <td><?= student_escape((string) ($subject['teacher_name'] ?: 'To be assigned')); ?></td>
                                <td><?= student_escape((string) $subject['credit_hours']); ?></td>
                                <td>
                                    <div class="progress-line"><span style="width:<?= (int) $subject['attendance_percent']; ?>%"></span></div><small><?= (int) $subject['attendance_percent']; ?>%</small>
                                </td>
                            </tr><?php endforeach; ?><?php if (!$subjects): ?><tr>
                                <td colspan="4">Your subjects will appear here after registration by the department.</td>
                            </tr><?php endif; ?></tbody>
                </table>
            </section>
            <section class="portal-panel">
                <h2>Exam marks</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Exam</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($marks as $mark): ?><tr>
                                <td><?= student_escape($mark['code']); ?> · <?= student_escape($mark['name']); ?></td>
                                <td><?= student_escape($mark['exam_name']); ?></td>
                                <td><strong><?= student_escape((string) $mark['marks_obtained']); ?> / <?= student_escape((string) $mark['total_marks']); ?></strong></td>
                            </tr><?php endforeach; ?><?php if (!$marks): ?><tr>
                                <td colspan="3">No published exam marks yet.</td>
                            </tr><?php endif; ?></tbody>
                </table>
            </section>
        </div>
        <aside>
            <section class="portal-panel">
                <h2>Learning materials</h2><?php foreach ($materials as $material): ?><div class="material-item"><strong><?= student_escape($material['title']); ?></strong><small><?= student_escape($material['subject_code']); ?> · <?= student_escape((string) $material['description']); ?></small><br><a class="text-link" href="<?= student_escape($material['file_url']); ?>" target="_blank" rel="noopener">Open material ↗</a></div><?php endforeach; ?><?php if (!$materials): ?><p style="color:var(--muted);font-size:13px">No materials have been published yet.</p><?php endif; ?>
            </section>
            <section class="portal-panel">
                <h2>Change password</h2>
                <form class="portal-form" method="post"><?= ait_csrf_field(); ?><input type="hidden" name="action" value="change_password"><label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label><label>New password<input type="password" name="new_password" required autocomplete="new-password"></label><label>Confirm new password<input type="password" name="confirm_password" required autocomplete="new-password"></label><button class="button button-coral" type="submit">Update password <span>↗</span></button></form>
            </section>
        </aside>
    </div>
    <section class="portal-layout" style="margin-top:16px">
        <section class="portal-panel">
            <h2>Attendance overview</h2>
            <div class="chart-wrap"><canvas id="attendanceChart" aria-label="Attendance by subject"></canvas><?php if (!$attendance): ?><p style="color:var(--muted);font-size:13px">Attendance will appear after your teachers record classes.</p><?php endif; ?></div>
        </section>
        <section class="portal-panel" id="reports">
            <h2>Report to student services</h2>
            <form class="portal-form report-form" method="post"><?= ait_csrf_field(); ?><input type="hidden" name="action" value="submit_report"><label>Report type<select name="report_type">
                        <option value="attendance">Attendance</option>
                        <option value="marks">Exam marks</option>
                        <option value="fee">Fee / challan</option>
                        <option value="profile">Profile correction</option>
                        <option value="technical">Technical problem</option>
                        <option value="other">Other</option>
                    </select></label><label>Subject<input type="text" name="report_subject" maxlength="180" required placeholder="Short summary"></label><label>Message<textarea name="report_message" maxlength="4000" required placeholder="Tell student services what you need."></textarea></label><button class="button button-coral" type="submit">Send report <span>↗</span></button></form>
        </section>
    </section>
    <?php $exam_challan = $semester_challans['exam_fee'] ?? null;
    $exam_ready = $exam_challan && $exam_challan['status'] === 'verified' && $eligible_for_exam && (int) $semester_control['exam_slip_enabled'] === 1; ?>
    <section class="portal-panel" style="margin-top:16px">
        <h2>Semester <?= $selected_semester; ?> exam slip</h2>
        <p style="color:var(--muted);font-size:13px">Exam slips are released by admin after the exam challan is verified and attendance eligibility is confirmed.</p><?php if ($exam_ready): ?><a class="button button-coral button-small" href="generate_slip.php?semester=<?= $selected_semester; ?>" target="_blank">Print exam slip <span>↗</span></a><?php else: ?><span class="disabled-action">Exam slip is not available yet.</span><?php endif; ?>
    </section>
</main>
<script nonce="<?= student_escape($csp_nonce); ?>">
    window.addEventListener('load', function() {
        const canvas = document.getElementById('attendanceChart');
        if (!canvas || typeof Chart === 'undefined') return;
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: <?= json_encode($attendance_labels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
                datasets: [{
                    label: 'Attendance %',
                    data: <?= json_encode($attendance_values); ?>,
                    backgroundColor: '#0b7772',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: value => value + '%'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    });
</script>
<?php ait_public_footer(); ?>