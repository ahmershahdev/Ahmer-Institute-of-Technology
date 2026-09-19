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

$mySubjects = $pdo->prepare('SELECT s.id, s.code, s.name FROM subjects s INNER JOIN teacher_subjects ts ON ts.subject_id = s.id WHERE ts.teacher_id = :id ORDER BY s.semester, s.name');
$mySubjects->execute(['id' => $teacher_id]);
$mySubjects = $mySubjects->fetchAll();

$subjectId = (int) ($_GET['subject_id'] ?? 0);
$owns = false;
foreach ($mySubjects as $s) {
    if ((int) $s['id'] === $subjectId) {
        $owns = true;
        break;
    }
}

$roster = [];
if ($owns) {
    $rosterStmt = $pdo->prepare('
        SELECT st.id, st.name, st.student_code, st.email,
            (SELECT ROUND(100 * SUM(a.status = "present") / NULLIF(COUNT(*), 0), 1) FROM attendance a WHERE a.subject_id = :sid1 AND a.student_id = st.id) AS attendance_pct,
            (SELECT marks_obtained FROM exam_marks em WHERE em.subject_id = :sid2 AND em.student_id = st.id AND em.exam_name = "Final" LIMIT 1) AS final_marks
        FROM student_subjects ss
        INNER JOIN students st ON st.id = ss.student_id
        WHERE ss.subject_id = :sid3
        ORDER BY st.name
    ');
    $rosterStmt->execute(['sid1' => $subjectId, 'sid2' => $subjectId, 'sid3' => $subjectId]);
    $roster = $rosterStmt->fetchAll();
}

$pageTitle = 'Students';
$activePage = 'students';
require __DIR__ . '/partials/shell_start.php';
?>

<div class="panel">
    <div class="panel-header">
        <h2>Select a subject</h2>
    </div>
    <div class="panel-body">
        <form method="get" class="form-field" style="max-width:420px;">
            <label>Subject</label>
            <select name="subject_id" onchange="this.form.submit()">
                <option value="">Choose a subject...</option>
                <?php foreach ($mySubjects as $s): ?>
                    <option value="<?= (int) $s['id']; ?>" <?= $subjectId === (int) $s['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($s['code'] . ' — ' . $s['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<?php if ($subjectId && !$owns): ?>
    <div class="form-alert-portal danger">You are not assigned to that subject.</div>
<?php elseif ($owns): ?>
    <div class="panel">
        <div class="panel-header"><h2>Roster (<?= count($roster); ?> students)</h2></div>
        <div class="panel-body" style="padding:0;">
            <?php if ($roster === []): ?>
                <div class="empty-state">No students are enrolled in this subject yet.</div>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Student code</th><th>Name</th><th>Email</th><th>Attendance</th><th>Final marks</th></tr></thead>
                    <tbody>
                    <?php foreach ($roster as $st): ?>
                        <tr>
                            <td><?= htmlspecialchars((string) $st['student_code'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($st['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($st['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= $st['attendance_pct'] !== null ? $st['attendance_pct'] . '%' : '—'; ?></td>
                            <td><?= $st['final_marks'] !== null ? htmlspecialchars((string) $st['final_marks'], ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/partials/shell_end.php'; ?>
