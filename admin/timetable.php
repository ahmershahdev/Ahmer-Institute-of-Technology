<?php
require_once __DIR__ . '/../backend/session.php';
ait_start_secure_session();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../backend/pdo.php';
require_once __DIR__ . '/../backend/security.php';
require_once __DIR__ . '/../backend/rbac.php';

$csp_nonce = ait_bootstrap_security();
$pdo = ait_pdo();

$current_admin_id = (int) $_SESSION['admin_id'];
$stmt = $pdo->prepare('SELECT * FROM admins WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $current_admin_id]);
$current_admin = $stmt->fetch();

if (!$current_admin || (int) $current_admin['is_active'] !== 1) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

ait_require_admin_permission($pdo, $current_admin, 'manage_timetable');

$msg = '';
$msg_type = 'success';
$dayNames = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ait_validate_csrf_post();
    ait_rate_limit('admin-timetable-action', 40, 300);
    $action = (string) ($_POST['action_type'] ?? '');

    try {
        if ($action === 'create_slot' || $action === 'update_slot') {
            $subjectId = (int) ($_POST['subject_id'] ?? 0);
            $teacherId = (int) ($_POST['teacher_id'] ?? 0);
            $day = max(1, min(6, (int) ($_POST['day_of_week'] ?? 1)));
            $start = trim((string) ($_POST['start_time'] ?? ''));
            $end = trim((string) ($_POST['end_time'] ?? ''));
            $room = trim((string) ($_POST['room'] ?? '')) ?: null;
            $section = trim((string) ($_POST['section'] ?? '')) ?: 'A';

            if ($subjectId < 1 || !preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end) || $start >= $end) {
                throw new RuntimeException('Choose a subject and a valid start/end time.');
            }

            $ownsTeacher = true;
            if ($teacherId > 0) {
                $check = $pdo->prepare('SELECT 1 FROM teacher_subjects WHERE teacher_id = :tid AND subject_id = :sid LIMIT 1');
                $check->execute(['tid' => $teacherId, 'sid' => $subjectId]);
                $ownsTeacher = (bool) $check->fetchColumn();
            }
            if (!$ownsTeacher) {
                throw new RuntimeException('That teacher is not assigned to the selected subject.');
            }

            if ($action === 'create_slot') {
                $pdo->prepare('INSERT INTO timetable_slots (subject_id, teacher_id, day_of_week, start_time, end_time, room, section) VALUES (:sid, :tid, :day, :start, :end, :room, :section)')
                    ->execute(['sid' => $subjectId, 'tid' => $teacherId ?: null, 'day' => $day, 'start' => $start, 'end' => $end, 'room' => $room, 'section' => $section]);
                ait_log_audit($pdo, 'admin', $current_admin_id, 'create_timetable_slot', 'timetable_slot', (int) $pdo->lastInsertId(), [], $current_admin['name']);
                $msg = 'Timetable slot created.';
            } else {
                $slotId = (int) ($_POST['slot_id'] ?? 0);
                $pdo->prepare('UPDATE timetable_slots SET subject_id = :sid, teacher_id = :tid, day_of_week = :day, start_time = :start, end_time = :end, room = :room, section = :section WHERE id = :id')
                    ->execute(['sid' => $subjectId, 'tid' => $teacherId ?: null, 'day' => $day, 'start' => $start, 'end' => $end, 'room' => $room, 'section' => $section, 'id' => $slotId]);
                ait_log_audit($pdo, 'admin', $current_admin_id, 'update_timetable_slot', 'timetable_slot', $slotId, [], $current_admin['name']);
                $msg = 'Timetable slot updated.';
            }
        } elseif ($action === 'delete_slot') {
            $slotId = (int) ($_POST['slot_id'] ?? 0);
            $pdo->prepare('DELETE FROM timetable_slots WHERE id = :id')->execute(['id' => $slotId]);
            ait_log_audit($pdo, 'admin', $current_admin_id, 'delete_timetable_slot', 'timetable_slot', $slotId, [], $current_admin['name']);
            $msg = 'Timetable slot deleted.';
        }
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $msg_type = 'danger';
    }
}

$departments = $pdo->query('SELECT code, name FROM departments ORDER BY name')->fetchAll();
$deptFilter = trim((string) ($_GET['department'] ?? ($departments[0]['code'] ?? '')));

$subjectsForDept = $pdo->prepare('SELECT id, code, name, semester FROM subjects WHERE department_code = :dept ORDER BY semester, code');
$subjectsForDept->execute(['dept' => $deptFilter]);
$subjectsForDept = $subjectsForDept->fetchAll();
$subjectIdsForDept = array_column($subjectsForDept, 'id');

$slots = [];
if ($subjectIdsForDept !== []) {
    $in = implode(',', array_fill(0, count($subjectIdsForDept), '?'));
    $slotStmt = $pdo->prepare("
        SELECT t.*, s.code AS subject_code, s.name AS subject_name, te.name AS teacher_name
        FROM timetable_slots t
        JOIN subjects s ON s.id = t.subject_id
        LEFT JOIN teachers te ON te.id = t.teacher_id
        WHERE t.subject_id IN ({$in})
        ORDER BY t.day_of_week, t.start_time
    ");
    $slotStmt->execute($subjectIdsForDept);
    $slots = $slotStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Timetable | AIT Admin</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon/ait.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style nonce="<?= htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        body { background: radial-gradient(circle at 10% 10%, rgba(14, 165, 233, .18), transparent 35%), linear-gradient(135deg, #08111f 0%, #102a3d 52%, #0b1726 100%); font-family: 'Inter', system-ui, -apple-system, sans-serif; color: #e2e8f0; min-height: 100vh; padding-bottom: 60px; }
        .page-shell { max-width: 1100px; margin: 0 auto; padding: 32px 20px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 24px; }
        .card-panel { background: rgba(15, 23, 42, .55); border: 1px solid rgba(148, 163, 184, .18); border-radius: 14px; backdrop-filter: blur(10px); }
        .table { color: #e2e8f0; margin-bottom: 0; }
        .table > :not(caption) > * > * { background: transparent; color: #e2e8f0; border-color: rgba(148, 163, 184, .15); }
        .form-control, .form-select { background: rgba(8, 15, 26, .6); border: 1px solid rgba(148, 163, 184, .25); color: #e2e8f0; }
        .form-control:focus, .form-select:focus { background: rgba(8, 15, 26, .8); color: #fff; border-color: #38bdf8; box-shadow: 0 0 0 .2rem rgba(56, 189, 248, .25); }
        a.back-link { color: #94a3b8; text-decoration: none; }
        a.back-link:hover { color: #e2e8f0; }
    </style>
</head>

<body>
    <div class="page-shell">
        <div class="topbar">
            <div>
                <a href="dashboard.php" class="back-link"><i class="bi bi-arrow-left me-1"></i>Back to dashboard</a>
                <h1 class="h4 fw-bold mt-2 mb-0">Timetable</h1>
                <p class="text-muted small mb-0"><?= count($slots); ?> slot(s) for <?= htmlspecialchars($deptFilter); ?></p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSlotModal"><i class="bi bi-plus-lg me-1"></i>Add slot</button>
        </div>

        <?php if ($msg !== ''): ?>
            <div class="alert alert-<?= $msg_type; ?> alert-dismissible fade show"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card-panel p-3 mb-3">
            <form method="get" class="d-flex gap-2 align-items-end flex-wrap">
                <div>
                    <label class="form-label small mb-1">Department</label>
                    <select name="department" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= htmlspecialchars($d['code']); ?>" <?= $deptFilter === $d['code'] ? 'selected' : ''; ?>><?= htmlspecialchars($d['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <div class="card-panel table-responsive">
            <table class="table table-hover align-middle m-0">
                <thead><tr><th>Day</th><th>Time</th><th>Subject</th><th>Teacher</th><th>Room</th><th>Section</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if ($slots === []): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No timetable slots for this department yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($slots as $slot): ?>
                    <tr>
                        <td><?= $dayNames[(int) $slot['day_of_week']] ?? '—'; ?></td>
                        <td><?= substr($slot['start_time'], 0, 5); ?>–<?= substr($slot['end_time'], 0, 5); ?></td>
                        <td><?= htmlspecialchars($slot['subject_code'] . ' — ' . $slot['subject_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars((string) ($slot['teacher_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars((string) $slot['room'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars($slot['section'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this slot?');">
                                <?= ait_csrf_field(); ?>
                                <input type="hidden" name="action_type" value="delete_slot">
                                <input type="hidden" name="slot_id" value="<?= $slot['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="createSlotModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-light">
                <form method="post">
                    <?= ait_csrf_field(); ?>
                    <input type="hidden" name="action_type" value="create_slot">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title">Add Timetable Slot</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body row g-2">
                        <div class="col-12"><label class="form-label small">Subject *</label>
                            <select class="form-select" name="subject_id" id="slotSubjectSelect" required>
                                <option value="">Choose a subject...</option>
                                <?php foreach ($subjectsForDept as $s): ?>
                                    <option value="<?= (int) $s['id']; ?>"><?= htmlspecialchars('S' . $s['semester'] . ' — ' . $s['code'] . ' ' . $s['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label small">Teacher (must be assigned to this subject)</label>
                            <select class="form-select" name="teacher_id" id="slotTeacherSelect">
                                <option value="">Unassigned</option>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label small">Day *</label>
                            <select class="form-select" name="day_of_week">
                                <?php foreach ($dayNames as $num => $label): ?><option value="<?= $num; ?>"><?= $label; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label small">Start *</label><input type="time" class="form-control" name="start_time" required></div>
                        <div class="col-md-3"><label class="form-label small">End *</label><input type="time" class="form-control" name="end_time" required></div>
                        <div class="col-md-6"><label class="form-label small">Room</label><input class="form-control" name="room" placeholder="A-101"></div>
                        <div class="col-md-6"><label class="form-label small">Section</label><input class="form-control" name="section" value="A"></div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Slot</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script nonce="<?= htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        (function () {
            var subjectSelect = document.getElementById('slotSubjectSelect');
            var teacherSelect = document.getElementById('slotTeacherSelect');
            if (!subjectSelect || !teacherSelect) return;
            subjectSelect.addEventListener('change', function () {
                var subjectId = this.value;
                teacherSelect.innerHTML = '<option value="">Loading...</option>';
                if (!subjectId) {
                    teacherSelect.innerHTML = '<option value="">Unassigned</option>';
                    return;
                }
                fetch('catalog_teachers_for_subject.php?subject_id=' + encodeURIComponent(subjectId))
                    .then(function (r) { return r.json(); })
                    .then(function (list) {
                        var html = '<option value="">Unassigned</option>';
                        list.forEach(function (t) {
                            html += '<option value="' + t.id + '">' + t.name + ' (' + t.teacher_code + ')</option>';
                        });
                        teacherSelect.innerHTML = html;
                    })
                    .catch(function () { teacherSelect.innerHTML = '<option value="">Unassigned</option>'; });
            });
        })();
    </script>
</body>

</html>
