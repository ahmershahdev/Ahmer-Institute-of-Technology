<?php
require_once __DIR__ . '/../backend/session.php';
ait_start_secure_session();

if (!isset($_SESSION['teacher_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../backend/security.php';
require_once __DIR__ . '/../backend/pdo.php';
$csp_nonce = ait_bootstrap_security();
$pdo = ait_pdo();
$teacher_id = (int) $_SESSION['teacher_id'];

$stmt = $pdo->prepare('SELECT * FROM teachers WHERE id = :id AND is_active = 1 LIMIT 1');
$stmt->execute(['id' => $teacher_id]);
$teacher = $stmt->fetch();

if (!$teacher) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

if ((int) $teacher['must_change_password'] === 1) {
    header('Location: change-password.php');
    exit;
}

$deptStmt = $pdo->prepare('SELECT name FROM departments WHERE code = :code LIMIT 1');
$deptStmt->execute(['code' => $teacher['department_code']]);
$departmentName = $deptStmt->fetchColumn() ?: 'Unassigned';

$subjectsStmt = $pdo->prepare('SELECT s.id, s.code, s.name, s.semester, s.credit_hours FROM subjects s INNER JOIN teacher_subjects ts ON ts.subject_id = s.id WHERE ts.teacher_id = :id ORDER BY s.semester, s.name');
$subjectsStmt->execute(['id' => $teacher_id]);
$assignedSubjects = $subjectsStmt->fetchAll();
$subjectIds = array_column($assignedSubjects, 'id');

$studentCount = 0;
$avgAttendance = null;
$pendingGrading = 0;
$upcomingSlots = [];

if ($subjectIds !== []) {
    $in = implode(',', array_fill(0, count($subjectIds), '?'));

    $studentStmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM student_subjects WHERE subject_id IN ({$in})");
    $studentStmt->execute($subjectIds);
    $studentCount = (int) $studentStmt->fetchColumn();

    $attStmt = $pdo->prepare("SELECT ROUND(100 * SUM(status = 'present') / NULLIF(COUNT(*), 0), 1) FROM attendance WHERE subject_id IN ({$in})");
    $attStmt->execute($subjectIds);
    $avgAttendance = $attStmt->fetchColumn();

    $pendingStmt = $pdo->prepare("SELECT COUNT(DISTINCT ss.student_id) FROM student_subjects ss WHERE ss.subject_id IN ({$in}) AND NOT EXISTS (SELECT 1 FROM exam_marks em WHERE em.subject_id = ss.subject_id AND em.student_id = ss.student_id)");
    $pendingStmt->execute($subjectIds);
    $pendingGrading = (int) $pendingStmt->fetchColumn();

    $slotStmt = $pdo->prepare("SELECT t.day_of_week, t.start_time, t.end_time, t.room, s.code, s.name FROM timetable_slots t JOIN subjects s ON s.id = t.subject_id WHERE t.teacher_id = :tid ORDER BY t.day_of_week, t.start_time LIMIT 5");
    $slotStmt->execute(['tid' => $teacher_id]);
    $upcomingSlots = $slotStmt->fetchAll();
}

$dayNames = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'];

$pageTitle = 'Dashboard';
$activePage = 'dashboard';
require __DIR__ . '/partials/shell_start.php';
?>

<div style="margin-bottom:20px;">
    <p class="eyebrow" style="margin-bottom:4px;">Welcome back</p>
    <h1 style="font:600 26px 'Space Grotesk',sans-serif;margin:0 0 6px;"><?= htmlspecialchars($teacher['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
    <p style="color:var(--muted);margin:0;">
        <?= htmlspecialchars((string) ($teacher['designation'] ?? 'Faculty member'), ENT_QUOTES, 'UTF-8'); ?>
        &middot; <?= htmlspecialchars($departmentName, ENT_QUOTES, 'UTF-8'); ?>
        &middot; Teacher ID <?= htmlspecialchars($teacher['teacher_code'], ENT_QUOTES, 'UTF-8'); ?>
        &middot; <a href="/AIT/teachers/<?= htmlspecialchars($teacher['teacher_code'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Public profile</a>
    </p>
</div>

<div class="kpi-row">
    <div class="kpi-card"><div class="kpi-label">Assigned subjects</div><div class="kpi-value"><?= count($assignedSubjects); ?></div></div>
    <div class="kpi-card"><div class="kpi-label">Total students</div><div class="kpi-value"><?= $studentCount; ?></div></div>
    <div class="kpi-card"><div class="kpi-label">Avg. attendance</div><div class="kpi-value"><?= $avgAttendance !== null ? $avgAttendance . '%' : '—'; ?></div></div>
    <div class="kpi-card"><div class="kpi-label">Pending grading</div><div class="kpi-value"><?= $pendingGrading; ?></div></div>
</div>

<div class="panel">
    <div class="panel-header">
        <h2>Your subjects</h2>
        <a class="btn-outline-portal" href="subjects.php">View all</a>
    </div>
    <div class="panel-body" style="padding:0;">
        <?php if ($assignedSubjects === []): ?>
            <div class="empty-state">No subjects have been assigned to you yet. Contact your department HOD or admin.</div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Code</th><th>Subject</th><th>Semester</th><th>Credit hours</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($assignedSubjects, 0, 8) as $subj): ?>
                    <tr>
                        <td><?= htmlspecialchars($subj['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($subj['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= (int) $subj['semester']; ?></td>
                        <td><?= (int) $subj['credit_hours']; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <h2>Upcoming classes</h2>
        <a class="btn-outline-portal" href="timetable.php">Full timetable</a>
    </div>
    <div class="panel-body" style="padding:0;">
        <?php if ($upcomingSlots === []): ?>
            <div class="empty-state">No timetable slots recorded for you yet.</div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Day</th><th>Time</th><th>Subject</th><th>Room</th></tr></thead>
                <tbody>
                <?php foreach ($upcomingSlots as $slot): ?>
                    <tr>
                        <td><?= $dayNames[(int) $slot['day_of_week']] ?? '—'; ?></td>
                        <td><?= substr($slot['start_time'], 0, 5); ?>–<?= substr($slot['end_time'], 0, 5); ?></td>
                        <td><?= htmlspecialchars($slot['code'] . ' — ' . $slot['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars((string) $slot['room'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/partials/shell_end.php'; ?>
