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

ait_require_admin_permission($pdo, $current_admin, 'manage_departments');

$msg = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ait_validate_csrf_post();
    ait_rate_limit('admin-catalog-action', 40, 300);
    $action = (string) ($_POST['action_type'] ?? '');

    try {
        if ($action === 'update_department') {
            $code = trim((string) ($_POST['department_code'] ?? ''));
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($code === '' || $name === '') {
                throw new RuntimeException('Department name is required.');
            }
            $pdo->prepare('UPDATE departments SET name = :name WHERE code = :code')->execute(['name' => $name, 'code' => $code]);
            ait_log_audit($pdo, 'admin', $current_admin_id, 'update_department', 'department', null, ['code' => $code, 'name' => $name], $current_admin['name']);
            $msg = "Department {$code} updated.";
        } elseif ($action === 'create_subject') {
            $department_code = trim((string) ($_POST['department_code'] ?? ''));
            $code = trim((string) ($_POST['code'] ?? ''));
            $name = trim((string) ($_POST['name'] ?? ''));
            $semester = max(1, min(8, (int) ($_POST['semester'] ?? 1)));
            $credit_hours = max(1, min(6, (int) ($_POST['credit_hours'] ?? 3)));

            if ($department_code === '' || $code === '' || $name === '') {
                throw new RuntimeException('Department, code, and name are required.');
            }

            $insert = $pdo->prepare('INSERT INTO subjects (department_code, code, name, semester, credit_hours, is_active) VALUES (:dept, :code, :name, :semester, :credit, 1)');
            $insert->execute(['dept' => $department_code, 'code' => $code, 'name' => $name, 'semester' => $semester, 'credit' => $credit_hours]);
            ait_log_audit($pdo, 'admin', $current_admin_id, 'create_subject', 'subject', (int) $pdo->lastInsertId(), ['code' => $code], $current_admin['name']);
            $msg = "Subject {$code} created.";
        } elseif ($action === 'update_subject') {
            $id = (int) ($_POST['subject_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $credit_hours = max(1, min(6, (int) ($_POST['credit_hours'] ?? 3)));
            $is_active = !empty($_POST['is_active']) ? 1 : 0;

            if ($id < 1 || $name === '') {
                throw new RuntimeException('Invalid subject update request.');
            }
            $pdo->prepare('UPDATE subjects SET name = :name, credit_hours = :credit, is_active = :active WHERE id = :id')
                ->execute(['name' => $name, 'credit' => $credit_hours, 'active' => $is_active, 'id' => $id]);
            ait_log_audit($pdo, 'admin', $current_admin_id, 'update_subject', 'subject', $id, [], $current_admin['name']);
            $msg = 'Subject updated.';
        } elseif ($action === 'delete_subject') {
            $id = (int) ($_POST['subject_id'] ?? 0);
            $pdo->prepare('DELETE FROM subjects WHERE id = :id')->execute(['id' => $id]);
            ait_log_audit($pdo, 'admin', $current_admin_id, 'delete_subject', 'subject', $id, [], $current_admin['name']);
            $msg = 'Subject deleted.';
        }
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $msg_type = 'danger';
    }
}

$departments = $pdo->query('
    SELECT d.code, d.name, d.is_active,
        (SELECT COUNT(*) FROM teachers t WHERE t.department_code = d.code AND t.is_active = 1) AS teacher_count,
        (SELECT COUNT(*) FROM subjects s WHERE s.department_code = d.code) AS subject_count,
        hs.name AS hod_name
    FROM departments d
    LEFT JOIN staff hs ON hs.id = d.hod_staff_id
    ORDER BY d.name
')->fetchAll();

$deptFilter = trim((string) ($_GET['department'] ?? ($departments[0]['code'] ?? '')));
$semesterFilter = (int) ($_GET['semester'] ?? 0);

$where = ['department_code = :dept'];
$params = ['dept' => $deptFilter];
if ($semesterFilter >= 1 && $semesterFilter <= 8) {
    $where[] = 'semester = :semester';
    $params['semester'] = $semesterFilter;
}
$subjectsStmt = $pdo->prepare('SELECT * FROM subjects WHERE ' . implode(' AND ', $where) . ' ORDER BY semester, code');
$subjectsStmt->execute($params);
$subjects = $subjectsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments &amp; Subjects | AIT Admin</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon/ait.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style nonce="<?= htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        body { background: radial-gradient(circle at 10% 10%, rgba(14, 165, 233, .18), transparent 35%), linear-gradient(135deg, #08111f 0%, #102a3d 52%, #0b1726 100%); font-family: 'Inter', system-ui, -apple-system, sans-serif; color: #e2e8f0; min-height: 100vh; padding-bottom: 60px; }
        .page-shell { max-width: 1200px; margin: 0 auto; padding: 32px 20px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 24px; }
        .card-panel { background: rgba(15, 23, 42, .55); border: 1px solid rgba(148, 163, 184, .18); border-radius: 14px; backdrop-filter: blur(10px); }
        .table { color: #e2e8f0; margin-bottom: 0; }
        .table > :not(caption) > * > * { background: transparent; color: #e2e8f0; border-color: rgba(148, 163, 184, .15); }
        .form-control, .form-select { background: rgba(8, 15, 26, .6); border: 1px solid rgba(148, 163, 184, .25); color: #e2e8f0; }
        .form-control:focus, .form-select:focus { background: rgba(8, 15, 26, .8); color: #fff; border-color: #38bdf8; box-shadow: 0 0 0 .2rem rgba(56, 189, 248, .25); }
        a.back-link { color: #94a3b8; text-decoration: none; }
        a.back-link:hover { color: #e2e8f0; }
        .dept-row.active-dept { outline: 2px solid #38bdf8; }
    </style>
</head>

<body>
    <div class="page-shell">
        <div class="topbar">
            <div>
                <a href="dashboard.php" class="back-link"><i class="bi bi-arrow-left me-1"></i>Back to dashboard</a>
                <h1 class="h4 fw-bold mt-2 mb-0">Departments &amp; Subjects</h1>
                <p class="text-muted small mb-0"><?= count($departments); ?> departments</p>
            </div>
        </div>

        <?php if ($msg !== ''): ?>
            <div class="alert alert-<?= $msg_type; ?> alert-dismissible fade show"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card-panel table-responsive mb-4">
            <table class="table table-hover align-middle m-0">
                <thead><tr><th>Code</th><th>Name</th><th>HOD</th><th>Teachers</th><th>Subjects</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($departments as $d): ?>
                    <tr class="dept-row <?= $d['code'] === $deptFilter ? 'active-dept' : ''; ?>">
                        <td><?= htmlspecialchars($d['code']); ?></td>
                        <td>
                            <form method="post" class="d-flex gap-2">
                                <?= ait_csrf_field(); ?>
                                <input type="hidden" name="action_type" value="update_department">
                                <input type="hidden" name="department_code" value="<?= htmlspecialchars($d['code']); ?>">
                                <input class="form-control form-control-sm" name="name" value="<?= htmlspecialchars($d['name']); ?>">
                                <button class="btn btn-sm btn-outline-light" type="submit"><i class="bi bi-save"></i></button>
                            </form>
                        </td>
                        <td><?= htmlspecialchars($d['hod_name'] ?? '—'); ?></td>
                        <td><?= (int) $d['teacher_count']; ?></td>
                        <td><?= (int) $d['subject_count']; ?></td>
                        <td><a class="btn btn-sm btn-outline-info" href="?department=<?= urlencode($d['code']); ?>">View subjects</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card-panel p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <h2 class="h6 fw-bold m-0">Subjects — <?= htmlspecialchars($deptFilter); ?></h2>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createSubjectModal"><i class="bi bi-plus-lg me-1"></i>Add subject</button>
            </div>
            <form method="get" class="d-flex gap-2 align-items-end flex-wrap">
                <input type="hidden" name="department" value="<?= htmlspecialchars($deptFilter); ?>">
                <div>
                    <label class="form-label small mb-1">Semester</label>
                    <select name="semester" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="0">All semesters</option>
                        <?php for ($s = 1; $s <= 8; $s++): ?>
                            <option value="<?= $s; ?>" <?= $semesterFilter === $s ? 'selected' : ''; ?>>Semester <?= $s; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </form>
        </div>

        <div class="card-panel table-responsive">
            <table class="table table-hover align-middle m-0">
                <thead><tr><th>Code</th><th>Name</th><th>Semester</th><th>Credit hours</th><th>Active</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if ($subjects === []): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No subjects found.</td></tr>
                <?php endif; ?>
                <?php foreach ($subjects as $s): $formId = 'editSubject' . (int) $s['id']; ?>
                    <form id="<?= $formId; ?>" method="post" style="display:none;">
                        <?= ait_csrf_field(); ?>
                        <input type="hidden" name="action_type" value="update_subject">
                        <input type="hidden" name="subject_id" value="<?= $s['id']; ?>">
                    </form>
                    <tr>
                        <td><?= htmlspecialchars($s['code']); ?></td>
                        <td><input class="form-control form-control-sm" form="<?= $formId; ?>" name="name" value="<?= htmlspecialchars($s['name']); ?>"></td>
                        <td><?= (int) $s['semester']; ?></td>
                        <td><input type="number" form="<?= $formId; ?>" name="credit_hours" min="1" max="6" class="form-control form-control-sm" style="width:70px;" value="<?= (int) $s['credit_hours']; ?>"></td>
                        <td class="text-center"><input type="checkbox" form="<?= $formId; ?>" name="is_active" value="1" <?= (int) $s['is_active'] === 1 ? 'checked' : ''; ?>></td>
                        <td>
                            <button class="btn btn-sm btn-outline-light" form="<?= $formId; ?>" type="submit"><i class="bi bi-save"></i></button>
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this subject?');">
                                <?= ait_csrf_field(); ?>
                                <input type="hidden" name="action_type" value="delete_subject">
                                <input type="hidden" name="subject_id" value="<?= $s['id']; ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="createSubjectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-light">
                <form method="post">
                    <?= ait_csrf_field(); ?>
                    <input type="hidden" name="action_type" value="create_subject">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title">Add Subject to <?= htmlspecialchars($deptFilter); ?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body row g-2">
                        <input type="hidden" name="department_code" value="<?= htmlspecialchars($deptFilter); ?>">
                        <div class="col-md-6"><label class="form-label small">Subject code *</label><input class="form-control" name="code" required></div>
                        <div class="col-md-6"><label class="form-label small">Semester *</label>
                            <select class="form-select" name="semester">
                                <?php for ($s = 1; $s <= 8; $s++): ?><option value="<?= $s; ?>"><?= $s; ?></option><?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label small">Name *</label><input class="form-control" name="name" required></div>
                        <div class="col-md-6"><label class="form-label small">Credit hours</label><input type="number" min="1" max="6" class="form-control" name="credit_hours" value="3"></div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Subject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
