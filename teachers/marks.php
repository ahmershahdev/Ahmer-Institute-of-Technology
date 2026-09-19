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

function ait_teacher_owns_subject_marks(PDO $pdo, int $teacherId, int $subjectId): bool
{
    $check = $pdo->prepare('SELECT 1 FROM teacher_subjects WHERE teacher_id = :tid AND subject_id = :sid LIMIT 1');
    $check->execute(['tid' => $teacherId, 'sid' => $subjectId]);

    return (bool) $check->fetchColumn();
}

$examOptions = ['Quiz 1', 'Quiz 2', 'Midterm', 'Assignment', 'Final'];
$msg = '';
$msg_type = 'success';
$subjectId = (int) ($_GET['subject_id'] ?? ($_POST['subject_id'] ?? 0));
$examName = trim((string) ($_GET['exam_name'] ?? ($_POST['exam_name'] ?? 'Midterm')));
if (!in_array($examName, $examOptions, true)) {
    $examName = 'Midterm';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ait_validate_csrf_post();
    ait_rate_limit('teacher-marks', 40, 300);

    if (!ait_teacher_owns_subject_marks($pdo, $teacher_id, $subjectId)) {
        $msg = 'You are not assigned to that subject.';
        $msg_type = 'danger';
    } else {
        $marks = (array) ($_POST['marks'] ?? []);
        $totalMarks = max(1, (float) ($_POST['total_marks'] ?? 100));
        $upsert = $pdo->prepare('INSERT INTO exam_marks (student_id, subject_id, exam_name, marks_obtained, total_marks, published_at) VALUES (:student_id, :subject_id, :exam_name, :marks, :total, NOW()) ON DUPLICATE KEY UPDATE marks_obtained = VALUES(marks_obtained), total_marks = VALUES(total_marks), published_at = VALUES(published_at)');
        foreach ($marks as $studentId => $value) {
            if ($value === '') {
                continue;
            }
            $obtained = max(0, min($totalMarks, (float) $value));
            $upsert->execute(['student_id' => (int) $studentId, 'subject_id' => $subjectId, 'exam_name' => $examName, 'marks' => $obtained, 'total' => $totalMarks]);
        }
        $msg = "Marks saved for {$examName}.";
    }
}

$owns = $subjectId > 0 && ait_teacher_owns_subject_marks($pdo, $teacher_id, $subjectId);
$roster = [];
if ($owns) {
    $rosterStmt = $pdo->prepare('
        SELECT st.id, st.name, st.student_code,
            (SELECT marks_obtained FROM exam_marks em WHERE em.subject_id = :sid1 AND em.student_id = st.id AND em.exam_name = :exam1) AS current_marks,
            (SELECT total_marks FROM exam_marks em WHERE em.subject_id = :sid2 AND em.student_id = st.id AND em.exam_name = :exam2) AS current_total
        FROM student_subjects ss
        INNER JOIN students st ON st.id = ss.student_id
        WHERE ss.subject_id = :sid3
        ORDER BY st.name
    ');
    $rosterStmt->execute(['sid1' => $subjectId, 'exam1' => $examName, 'sid2' => $subjectId, 'exam2' => $examName, 'sid3' => $subjectId]);
    $roster = $rosterStmt->fetchAll();
}
$defaultTotal = $roster !== [] && $roster[0]['current_total'] !== null ? $roster[0]['current_total'] : 100;

$pageTitle = 'Marks & Grades';
$activePage = 'marks';
require __DIR__ . '/partials/shell_start.php';
?>

<?php if ($msg !== ''): ?><div class="form-alert-portal <?= $msg_type; ?>"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

<div class="panel">
    <div class="panel-header"><h2>Enter marks</h2></div>
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
                <label>Exam</label>
                <select name="exam_name" onchange="this.form.submit()">
                    <?php foreach ($examOptions as $opt): ?>
                        <option value="<?= $opt; ?>" <?= $examName === $opt ? 'selected' : ''; ?>><?= $opt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($subjectId && !$owns): ?>
    <div class="form-alert-portal danger">You are not assigned to that subject.</div>
<?php elseif ($owns): ?>
    <div class="panel">
        <div class="panel-header"><h2><?= htmlspecialchars($examName, ENT_QUOTES, 'UTF-8'); ?> &mdash; <?= count($roster); ?> students</h2></div>
        <div class="panel-body">
            <?php if ($roster === []): ?>
                <div class="empty-state">No students are enrolled in this subject yet.</div>
            <?php else: ?>
                <form method="post">
                    <?= ait_csrf_field(); ?>
                    <input type="hidden" name="subject_id" value="<?= $subjectId; ?>">
                    <input type="hidden" name="exam_name" value="<?= htmlspecialchars($examName, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="form-field" style="max-width:200px;">
                        <label>Total marks</label>
                        <input type="number" name="total_marks" step="0.01" value="<?= htmlspecialchars((string) $defaultTotal, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <table class="data-table">
                        <thead><tr><th>Student</th><th>Marks obtained</th></tr></thead>
                        <tbody>
                        <?php foreach ($roster as $st): ?>
                            <tr>
                                <td><?= htmlspecialchars($st['student_code'] . ' — ' . $st['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><input type="number" step="0.01" min="0" name="marks[<?= (int) $st['id']; ?>]" value="<?= $st['current_marks'] !== null ? htmlspecialchars((string) $st['current_marks'], ENT_QUOTES, 'UTF-8') : ''; ?>" style="width:120px;padding:6px 10px;border:1px solid var(--line);border-radius:6px;background:var(--glass);color:var(--ink);"></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div style="margin-top:16px;"><button class="btn-primary-portal" type="submit"><i class="bi bi-save"></i> Save marks</button></div>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/partials/shell_end.php'; ?>
