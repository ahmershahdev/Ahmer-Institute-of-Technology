<?php
require_once __DIR__ . '/../backend/session.php';
ait_start_secure_session();

if (!isset($_SESSION['teacher_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../backend/security.php';
require_once __DIR__ . '/../backend/pdo.php';
require_once __DIR__ . '/../backend/rbac.php';
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
$subjectIds = array_column($mySubjects, 'id');

$msg = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ait_validate_csrf_post();
    ait_rate_limit('teacher-announcements', 20, 300);
    $title = trim((string) ($_POST['title'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));
    $subjectId = (int) ($_POST['subject_id'] ?? 0);

    $ownsSubject = $subjectId === 0 || in_array($subjectId, $subjectIds, true);

    if ($title === '' || $body === '' || !$ownsSubject) {
        $msg = 'Please provide a title, message, and a subject you are assigned to.';
        $msg_type = 'danger';
    } else {
        $insert = $pdo->prepare('INSERT INTO announcements (author_type, author_id, department_code, subject_id, title, body) VALUES ("teacher", :author_id, :dept, :subject_id, :title, :body)');
        $insert->execute([
            'author_id' => $teacher_id, 'dept' => $teacher['department_code'],
            'subject_id' => $subjectId ?: null, 'title' => $title, 'body' => $body,
        ]);
        ait_log_audit($pdo, 'teacher', $teacher_id, 'post_announcement', 'announcement', (int) $pdo->lastInsertId(), ['subject_id' => $subjectId], $teacher['name']);
        $msg = 'Announcement posted.';
    }
}

$listStmt = $pdo->prepare('SELECT a.*, s.code AS subject_code FROM announcements a LEFT JOIN subjects s ON s.id = a.subject_id WHERE a.author_type = "teacher" AND a.author_id = :id ORDER BY a.created_at DESC LIMIT 20');
$listStmt->execute(['id' => $teacher_id]);
$announcements = $listStmt->fetchAll();

$pageTitle = 'Announcements';
$activePage = 'announcements';
require __DIR__ . '/partials/shell_start.php';
?>

<?php if ($msg !== ''): ?><div class="form-alert-portal <?= $msg_type; ?>"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

<div class="panel">
    <div class="panel-header"><h2>Post an announcement</h2></div>
    <div class="panel-body">
        <form method="post">
            <?= ait_csrf_field(); ?>
            <div class="form-field">
                <label>Subject (optional — leave blank for a general announcement)</label>
                <select name="subject_id">
                    <option value="">General</option>
                    <?php foreach ($mySubjects as $s): ?>
                        <option value="<?= (int) $s['id']; ?>"><?= htmlspecialchars($s['code'] . ' — ' . $s['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field"><label>Title</label><input type="text" name="title" maxlength="200" required></div>
            <div class="form-field"><label>Message</label><textarea name="body" rows="4" required></textarea></div>
            <button class="btn-primary-portal" type="submit"><i class="bi bi-send"></i> Post</button>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><h2>Your announcements</h2></div>
    <div class="panel-body" style="padding:0;">
        <?php if ($announcements === []): ?>
            <div class="empty-state">You haven't posted anything yet.</div>
        <?php else: ?>
            <table class="data-table">
                <thead><tr><th>Date</th><th>Subject</th><th>Title</th><th>Message</th></tr></thead>
                <tbody>
                <?php foreach ($announcements as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars(date('M j, Y', strtotime($a['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= $a['subject_code'] ? htmlspecialchars($a['subject_code'], ENT_QUOTES, 'UTF-8') : '<span class="pill pill-muted">General</span>'; ?></td>
                        <td><?= htmlspecialchars($a['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($a['body'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/partials/shell_end.php'; ?>
