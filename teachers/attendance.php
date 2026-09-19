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

function ait_teacher_owns_subject(PDO $pdo, int $teacherId, int $subjectId): bool
{
    $check = $pdo->prepare('SELECT 1 FROM teacher_subjects WHERE teacher_id = :tid AND subject_id = :sid LIMIT 1');
    $check->execute(['tid' => $teacherId, 'sid' => $subjectId]);

    return (bool) $check->fetchColumn();
}

$msg = '';
$msg_type = 'success';
$subjectId = (int) ($_GET['subject_id'] ?? ($_POST['subject_id'] ?? 0));
$date = trim((string) ($_GET['class_date'] ?? ($_POST['class_date'] ?? date('Y-m-d'))));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ait_validate_csrf_post();
    ait_rate_limit('teacher-attendance', 40, 300);

    if (!ait_teacher_owns_subject($pdo, $teacher_id, $subjectId)) {
        $msg = 'You are not assigned to that subject.';
        $msg_type = 'danger';
    } else {
        $statuses = (array) ($_POST['status'] ?? []);
        $upsert = $pdo->prepare('INSERT INTO attendance (student_id, subject_id, class_date, status) VALUES (:student_id, :subject_id, :class_date, :status) ON DUPLICATE KEY UPDATE status = VALUES(status)');
        foreach ($statuses as $studentId => $status) {
            if (!in_array($status, ['present', 'absent', 'late', 'excused'], true)) {
                continue;
            }
            $upsert->execute(['student_id' => (int) $studentId, 'subject_id' => $subjectId, 'class_date' => $date, 'status' => $status]);
        }
        $msg = 'Attendance saved for ' . $date . '.';
    }
}

$owns = $subjectId > 0 && ait_teacher_owns_subject($pdo, $teacher_id, $subjectId);
$roster = [];
if ($owns) {
    $rosterStmt = $pdo->prepare('
        SELECT st.id, st.name, st.student_code,
            (SELECT status FROM attendance a WHERE a.subject_id = :sid1 AND a.student_id = st.id AND a.class_date = :date1) AS today_status
        FROM student_subjects ss
        INNER JOIN students st ON st.id = ss.student_id
        WHERE ss.subject_id = :sid2
        ORDER BY st.name
    ');
    $rosterStmt->execute(['sid1' => $subjectId, 'date1' => $date, 'sid2' => $subjectId]);
    $roster = $rosterStmt->fetchAll();
}

$pageTitle = 'Attendance';
$activePage = 'attendance';
require __DIR__ . '/partials/shell_start.php';
?>

<?php if ($msg !== ''): ?><div class="form-alert-portal <?= $msg_type; ?>"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

<div class="panel">
    <div class="panel-header"><h2>Mark attendance</h2></div>
    <div class="panel-body">
        <form method="get" style="display:flex;gap:16px;flex-wrap:wrap;align-items:end;">
            <div class="form-field" style="min-width:260px;">
                <label>Subject</label>
                <select name="subject_id" onchange="this.form.submit()">
                    <option value="">Choose a subject...</option>
                    <?php foreach ($mySubjects as $s): ?>
                        <option value="<?= (int) $s['id']; ?>" <?= $subjectId === (int) $s['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($s['code'] . ' — ' . $s['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label>Class date</label>
                <input type="date" name="class_date" value="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8'); ?>" onchange="this.form.submit()">
            </div>
        </form>
    </div>
</div>

<?php if ($subjectId && !$owns): ?>
    <div class="form-alert-portal danger">You are not assigned to that subject.</div>
<?php elseif ($owns): ?>
    <div class="panel">
        <div class="panel-header"><h2><?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8'); ?> &mdash; <?= count($roster); ?> students</h2></div>
        <div class="panel-body">
            <?php if ($roster === []): ?>
                <div class="empty-state">No students are enrolled in this subject yet.</div>
            <?php else: ?>
                <form method="post">
                    <?= ait_csrf_field(); ?>
                    <input type="hidden" name="subject_id" value="<?= $subjectId; ?>">
                    <input type="hidden" name="class_date" value="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8'); ?>">
                    <table class="data-table">
                        <thead><tr><th>Student</th><th>Present</th><th>Absent</th><th>Late</th><th>Excused</th></tr></thead>
                        <tbody>
                        <?php foreach ($roster as $st): $current = $st['today_status'] ?? 'present'; ?>
                            <tr>
                                <td><?= htmlspecialchars($st['student_code'] . ' — ' . $st['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <?php foreach (['present', 'absent', 'late', 'excused'] as $opt): ?>
                                    <td style="text-align:center;"><input type="radio" name="status[<?= (int) $st['id']; ?>]" value="<?= $opt; ?>" <?= $current === $opt ? 'checked' : ''; ?>></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="margin-top:16px;"><button class="btn-primary-portal" type="submit"><i class="bi bi-save"></i> Save attendance</button></div>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/partials/shell_end.php'; ?>
