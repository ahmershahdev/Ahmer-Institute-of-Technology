<?php
require_once __DIR__ . '/../backend/session.php';
ait_start_secure_session();

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../backend/security.php';
require_once __DIR__ . '/../backend/pdo.php';
$csp_nonce = ait_bootstrap_security();
$pdo = ait_pdo();
$staff_id = (int) $_SESSION['staff_id'];

$stmt = $pdo->prepare('SELECT * FROM staff WHERE id = :id AND is_active = 1 LIMIT 1');
$stmt->execute(['id' => $staff_id]);
$staff = $stmt->fetch();

if (!$staff) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}
if ((int) $staff['must_change_password'] === 1) {
    header('Location: change-password.php');
    exit;
}
if ($staff['role_type'] !== 'hod' || !$staff['department_code']) {
    http_response_code(403);
    die('This page is only available to a Head of Department.');
}

$roleLabels = ['hod' => 'Head of Department', 'security' => 'Security Guard', 'worker' => 'Worker', 'clerical' => 'Clerical Staff', 'other' => 'Staff'];
$roleLabel = $roleLabels[$staff['role_type']] ?? 'Staff';

$deptStmt = $pdo->prepare('SELECT name FROM departments WHERE code = :code LIMIT 1');
$deptStmt->execute(['code' => $staff['department_code']]);
$deptName = $deptStmt->fetchColumn();

$teachersStmt = $pdo->prepare('
    SELECT t.teacher_code, t.name, t.designation, t.email,
        (SELECT COUNT(*) FROM teacher_subjects ts WHERE ts.teacher_id = t.id) AS subject_count
    FROM teachers t WHERE t.department_code = :code AND t.is_active = 1 ORDER BY t.name
');
$teachersStmt->execute(['code' => $staff['department_code']]);
$teachers = $teachersStmt->fetchAll();

$subjectsStmt = $pdo->prepare('
    SELECT s.code, s.name, s.semester, s.credit_hours,
        (SELECT COUNT(*) FROM teacher_subjects ts WHERE ts.subject_id = s.id) AS teacher_count
    FROM subjects s WHERE s.department_code = :code AND s.is_active = 1 ORDER BY s.semester, s.name
');
$subjectsStmt->execute(['code' => $staff['department_code']]);
$subjects = $subjectsStmt->fetchAll();

$pageTitle = 'Department Overview';
$activePage = 'department';
require __DIR__ . '/partials/shell_start.php';
?>

<div style="margin-bottom:20px;">
    <h1 style="font:600 24px 'Space Grotesk',sans-serif;margin:0 0 6px;"><?= htmlspecialchars((string) $deptName, ENT_QUOTES, 'UTF-8'); ?></h1>
    <p style="color:var(--muted);margin:0;"><?= count($teachers); ?> teachers &middot; <?= count($subjects); ?> subjects</p>
</div>

<div class="panel">
    <div class="panel-header"><h2>Teachers</h2></div>
    <div class="panel-body" style="padding:0;">
        <table class="data-table">
            <thead><tr><th>Code</th><th>Name</th><th>Designation</th><th>Subjects taught</th></tr></thead>
            <tbody>
            <?php foreach ($teachers as $t): ?>
                <tr>
                    <td><?= htmlspecialchars($t['teacher_code'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars((string) $t['designation'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= (int) $t['subject_count']; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Subjects</h2></div>
    <div class="panel-body" style="padding:0;">
        <table class="data-table">
            <thead><tr><th>Code</th><th>Subject</th><th>Semester</th><th>Credit hours</th><th>Teachers assigned</th></tr></thead>
            <tbody>
            <?php foreach ($subjects as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= (int) $s['semester']; ?></td>
                    <td><?= (int) $s['credit_hours']; ?></td>
                    <td><?= (int) $s['teacher_count']; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/partials/shell_end.php'; ?>
