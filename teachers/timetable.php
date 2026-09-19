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

$slotsStmt = $pdo->prepare('
    SELECT t.day_of_week, t.start_time, t.end_time, t.room, t.section, s.code, s.name
    FROM timetable_slots t
    INNER JOIN subjects s ON s.id = t.subject_id
    WHERE t.teacher_id = :tid
    ORDER BY t.day_of_week, t.start_time
');
$slotsStmt->execute(['tid' => $teacher_id]);
$slots = $slotsStmt->fetchAll();

$dayNames = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
$byDay = [];
foreach ($slots as $slot) {
    $byDay[(int) $slot['day_of_week']][] = $slot;
}

$pageTitle = 'Timetable';
$activePage = 'timetable';
require __DIR__ . '/partials/shell_start.php';
?>

<?php if ($slots === []): ?>
    <div class="panel"><div class="empty-state">No timetable slots have been scheduled for you yet.</div></div>
<?php else: ?>
    <?php foreach ($dayNames as $dayNum => $dayLabel): if (empty($byDay[$dayNum])) { continue; } ?>
        <div class="panel">
            <div class="panel-header"><h2><?= $dayLabel; ?></h2></div>
            <div class="panel-body" style="padding:0;">
                <table class="data-table">
                    <thead><tr><th>Time</th><th>Subject</th><th>Room</th><th>Section</th></tr></thead>
                    <tbody>
                    <?php foreach ($byDay[$dayNum] as $slot): ?>
                        <tr>
                            <td><?= substr($slot['start_time'], 0, 5); ?>–<?= substr($slot['end_time'], 0, 5); ?></td>
                            <td><?= htmlspecialchars($slot['code'] . ' — ' . $slot['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars((string) $slot['room'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?= htmlspecialchars($slot['section'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/shell_end.php'; ?>
