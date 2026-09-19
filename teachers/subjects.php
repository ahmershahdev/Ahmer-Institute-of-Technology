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

$subjectsStmt = $pdo->prepare('
    SELECT s.id, s.code, s.name, s.semester, s.credit_hours,
        (SELECT COUNT(*) FROM student_subjects ss WHERE ss.subject_id = s.id) AS student_count,
        (SELECT ROUND(100 * SUM(a.status = "present") / NULLIF(COUNT(*), 0), 1) FROM attendance a WHERE a.subject_id = s.id) AS avg_attendance
    FROM subjects s
    INNER JOIN teacher_subjects ts ON ts.subject_id = s.id
    WHERE ts.teacher_id = :id
    ORDER BY s.semester, s.name
');
$subjectsStmt->execute(['id' => $teacher_id]);
$subjects = $subjectsStmt->fetchAll();

$pageTitle = 'My Subjects';
$activePage = 'subjects';
require __DIR__ . '/partials/shell_start.php';
?>

<div class="panel">
    <div class="panel-header">
        <h2>My subjects (<?= count($subjects); ?>)</h2>
    </div>
    <div class="panel-body" style="padding:0;">
        <?php if ($subjects === []): ?>
            <div class="empty-state">No subjects assigned yet.</div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Code</th><th>Subject</th><th>Semester</th><th>Credit hours</th><th>Students</th><th>Avg. attendance</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($subjects as $subj): ?>
                    <tr>
                        <td><?= htmlspecialchars($subj['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($subj['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= (int) $subj['semester']; ?></td>
                        <td><?= (int) $subj['credit_hours']; ?></td>
                        <td><?= (int) $subj['student_count']; ?></td>
                        <td><?= $subj['avg_attendance'] !== null ? $subj['avg_attendance'] . '%' : '—'; ?></td>
                        <td>
                            <a class="btn-outline-portal" href="students.php?subject_id=<?= (int) $subj['id']; ?>">Roster</a>
                            <a class="btn-outline-portal" href="attendance.php?subject_id=<?= (int) $subj['id']; ?>">Attendance</a>
                            <a class="btn-outline-portal" href="marks.php?subject_id=<?= (int) $subj['id']; ?>">Marks</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/partials/shell_end.php'; ?>
