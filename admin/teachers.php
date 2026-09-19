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
    ait_start_secure_session();
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

$is_super_admin = $current_admin['role'] === 'super_admin';
ait_require_admin_permission($pdo, $current_admin, 'manage_teachers');

$msg = '';
$msg_type = 'success';

$departments = $pdo->query('SELECT code, name FROM departments WHERE is_active = 1 ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ait_validate_csrf_post();
    ait_rate_limit('admin-teachers-action', 40, 300);
    $action = (string) ($_POST['action_type'] ?? '');

    try {
        if ($action === 'create_teacher') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_SANITIZE_EMAIL);
            $department_code = trim((string) ($_POST['department_code'] ?? '')) ?: null;
            $designation = trim((string) ($_POST['designation'] ?? '')) ?: null;
            $phone = trim((string) ($_POST['phone'] ?? '')) ?: null;
            $nic = trim((string) ($_POST['nic'] ?? '')) ?: null;
            $age = ($_POST['age'] ?? '') !== '' ? (int) $_POST['age'] : null;
            $gender = trim((string) ($_POST['gender'] ?? '')) ?: null;
            $caste = trim((string) ($_POST['caste'] ?? '')) ?: null;
            $religion = trim((string) ($_POST['religion'] ?? '')) ?: null;
            $salary = ($_POST['salary'] ?? '') !== '' ? (float) $_POST['salary'] : null;
            $working_time = trim((string) ($_POST['working_time'] ?? '')) ?: null;
            $joining_date = trim((string) ($_POST['joining_date'] ?? '')) ?: null;

            if ($name === '' || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('A valid name and email are required.');
            }

            $exists = $pdo->prepare('SELECT 1 FROM teachers WHERE email = :email LIMIT 1');
            $exists->execute(['email' => $email]);
            if ($exists->fetchColumn()) {
                throw new RuntimeException('A teacher with that email already exists.');
            }

            $temp_password = ait_generate_temp_password();
            $hash = password_hash($temp_password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);

            $insert = $pdo->prepare('INSERT INTO teachers (name, email, password, department_code, designation, phone, nic, age, gender, caste, religion, salary, working_time, joining_date, must_change_password, created_by) VALUES (:name, :email, :password, :department_code, :designation, :phone, :nic, :age, :gender, :caste, :religion, :salary, :working_time, :joining_date, 1, :created_by)');
            $insert->execute([
                'name' => $name, 'email' => $email, 'password' => $hash,
                'department_code' => $department_code, 'designation' => $designation,
                'phone' => $phone, 'nic' => $nic, 'age' => $age, 'gender' => $gender,
                'caste' => $caste, 'religion' => $religion, 'salary' => $salary,
                'working_time' => $working_time, 'joining_date' => $joining_date ?: null,
                'created_by' => $current_admin_id,
            ]);
            $newTeacherId = (int) $pdo->lastInsertId();
            $teacherCode = ait_generate_account_code($pdo, 'teachers', 'teacher_code', 'T');
            $pdo->prepare('UPDATE teachers SET teacher_code = :code WHERE id = :id')->execute(['code' => $teacherCode, 'id' => $newTeacherId]);

            ait_log_audit($pdo, 'admin', $current_admin_id, 'create_teacher', 'teacher', $newTeacherId, ['email' => $email, 'department_code' => $department_code], $current_admin['name']);

            $msg = "Teacher '{$name}' created as {$teacherCode}. Temporary password: {$temp_password} (share this securely; they must change it on first login).";
        } elseif ($action === 'update_teacher') {
            $id = (int) ($_POST['teacher_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $department_code = trim((string) ($_POST['department_code'] ?? '')) ?: null;
            $designation = trim((string) ($_POST['designation'] ?? '')) ?: null;
            $phone = trim((string) ($_POST['phone'] ?? '')) ?: null;
            $salary = ($_POST['salary'] ?? '') !== '' ? (float) $_POST['salary'] : null;
            $working_time = trim((string) ($_POST['working_time'] ?? '')) ?: null;

            if ($id < 1 || $name === '') {
                throw new RuntimeException('Invalid teacher update request.');
            }

            $update = $pdo->prepare('UPDATE teachers SET name = :name, department_code = :department_code, designation = :designation, phone = :phone, salary = :salary, working_time = :working_time WHERE id = :id');
            $update->execute(['name' => $name, 'department_code' => $department_code, 'designation' => $designation, 'phone' => $phone, 'salary' => $salary, 'working_time' => $working_time, 'id' => $id]);

            ait_log_audit($pdo, 'admin', $current_admin_id, 'update_teacher', 'teacher', $id, [], $current_admin['name']);
            $msg = 'Teacher profile updated.';
        } elseif ($action === 'toggle_teacher') {
            $id = (int) ($_POST['teacher_id'] ?? 0);
            $stmtT = $pdo->prepare('SELECT is_active FROM teachers WHERE id = :id');
            $stmtT->execute(['id' => $id]);
            $isActive = (int) $stmtT->fetchColumn();
            $new = $isActive ? 0 : 1;
            $pdo->prepare('UPDATE teachers SET is_active = :active WHERE id = :id')->execute(['active' => $new, 'id' => $id]);
            ait_log_audit($pdo, 'admin', $current_admin_id, $new ? 'enable_teacher' : 'disable_teacher', 'teacher', $id, [], $current_admin['name']);
            $msg = $new ? 'Teacher account re-enabled.' : 'Teacher account disabled.';
        } elseif ($action === 'reset_teacher_password') {
            $id = (int) ($_POST['teacher_id'] ?? 0);
            $temp_password = ait_generate_temp_password();
            $hash = password_hash($temp_password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE teachers SET password = :password, must_change_password = 1, failed_attempts = 0, locked_until = NULL WHERE id = :id')->execute(['password' => $hash, 'id' => $id]);
            ait_log_audit($pdo, 'admin', $current_admin_id, 'reset_teacher_password', 'teacher', $id, [], $current_admin['name']);
            $msg = "Password reset. New temporary password: {$temp_password}";
        } elseif ($action === 'delete_teacher') {
            $id = (int) ($_POST['teacher_id'] ?? 0);
            $pdo->prepare('DELETE FROM teachers WHERE id = :id')->execute(['id' => $id]);
            ait_log_audit($pdo, 'admin', $current_admin_id, 'delete_teacher', 'teacher', $id, [], $current_admin['name']);
            $msg = 'Teacher account deleted.';
        }
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $msg_type = 'danger';
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$deptFilter = trim((string) ($_GET['department'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(name LIKE :search OR email LIKE :search OR teacher_code LIKE :search)';
    $params['search'] = '%' . $search . '%';
}
if ($deptFilter !== '') {
    $where[] = 'department_code = :dept';
    $params['dept'] = $deptFilter;
}
$whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM teachers {$whereSql}");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$listStmt = $pdo->prepare("SELECT * FROM teachers {$whereSql} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}");
$listStmt->execute($params);
$teachers = $listStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Manage Teachers | AIT Admin</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon/ait.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style nonce="<?= htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        body {
            background: radial-gradient(circle at 10% 10%, rgba(14, 165, 233, .18), transparent 35%), linear-gradient(135deg, #08111f 0%, #102a3d 52%, #0b1726 100%);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: #e2e8f0;
            min-height: 100vh;
            padding-bottom: 60px;
        }

        .page-shell {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 20px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }

        .card-panel {
            background: rgba(15, 23, 42, .55);
            border: 1px solid rgba(148, 163, 184, .18);
            border-radius: 14px;
            backdrop-filter: blur(10px);
        }

        .table {
            color: #e2e8f0;
            margin-bottom: 0;
        }

        .table > :not(caption) > * > * {
            background: transparent;
            color: #e2e8f0;
            border-color: rgba(148, 163, 184, .15);
        }

        .form-control, .form-select {
            background: rgba(8, 15, 26, .6);
            border: 1px solid rgba(148, 163, 184, .25);
            color: #e2e8f0;
        }

        .form-control:focus, .form-select:focus {
            background: rgba(8, 15, 26, .8);
            color: #fff;
            border-color: #38bdf8;
            box-shadow: 0 0 0 .2rem rgba(56, 189, 248, .25);
        }

        .form-control::placeholder { color: #94a3b8; }
        .teacher-code { font-family: 'Space Grotesk', monospace; color: #38bdf8; }
        a.back-link { color: #94a3b8; text-decoration: none; }
        a.back-link:hover { color: #e2e8f0; }
    </style>
</head>

<body>
    <div class="page-shell">
        <div class="topbar">
            <div>
                <a href="dashboard.php" class="back-link"><i class="bi bi-arrow-left me-1"></i>Back to dashboard</a>
                <h1 class="h4 fw-bold mt-2 mb-0">Manage Teachers</h1>
                <p class="text-muted small mb-0"><?= $total; ?> teacher account(s)</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTeacherModal"><i class="bi bi-person-plus me-1"></i>Add Teacher</button>
        </div>

        <?php if ($msg !== ''): ?>
            <div class="alert alert-<?= $msg_type; ?> alert-dismissible fade show"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card-panel p-3 mb-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small">Search</label>
                    <input type="text" name="q" class="form-control" placeholder="Name, email, or teacher code" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Department</label>
                    <select name="department" class="form-select">
                        <option value="">All departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= htmlspecialchars($d['code']); ?>" <?= $deptFilter === $d['code'] ? 'selected' : ''; ?>><?= htmlspecialchars($d['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-light w-100" type="submit">Filter</button>
                </div>
            </form>
        </div>

        <div class="card-panel table-responsive">
            <table class="table table-hover align-middle m-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($teachers === []): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No teachers found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($teachers as $t): ?>
                        <tr>
                            <td><span class="teacher-code"><?= htmlspecialchars($t['teacher_code']); ?></span></td>
                            <td>
                                <?= htmlspecialchars($t['name']); ?>
                                <div><a class="small back-link" href="../teachers/<?= htmlspecialchars($t['teacher_code']); ?>" target="_blank">View public profile <i class="bi bi-box-arrow-up-right"></i></a></div>
                            </td>
                            <td><?= htmlspecialchars($t['email']); ?></td>
                            <td><?= htmlspecialchars($t['department_code'] ?? '—'); ?></td>
                            <td><?= htmlspecialchars($t['designation'] ?? '—'); ?></td>
                            <td>
                                <?php if ((int) $t['is_active'] === 1): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td class="d-flex gap-1 flex-wrap">
                                <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#editTeacherModal<?= $t['id']; ?>"><i class="bi bi-pencil"></i></button>
                                <form method="post" class="d-inline" onsubmit="return confirm('Reset password for <?= htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8'); ?>?');">
                                    <?= ait_csrf_field(); ?>
                                    <input type="hidden" name="action_type" value="reset_teacher_password">
                                    <input type="hidden" name="teacher_id" value="<?= $t['id']; ?>">
                                    <button class="btn btn-sm btn-outline-warning" type="submit"><i class="bi bi-key"></i></button>
                                </form>
                                <form method="post" class="d-inline">
                                    <?= ait_csrf_field(); ?>
                                    <input type="hidden" name="action_type" value="toggle_teacher">
                                    <input type="hidden" name="teacher_id" value="<?= $t['id']; ?>">
                                    <button class="btn btn-sm btn-outline-info" type="submit"><i class="bi bi-power"></i></button>
                                </form>
                                <form method="post" class="d-inline" onsubmit="return confirm('Permanently delete this teacher account?');">
                                    <?= ait_csrf_field(); ?>
                                    <input type="hidden" name="action_type" value="delete_teacher">
                                    <input type="hidden" name="teacher_id" value="<?= $t['id']; ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>

                        <div class="modal fade" id="editTeacherModal<?= $t['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content bg-dark text-light">
                                    <form method="post">
                                        <?= ait_csrf_field(); ?>
                                        <input type="hidden" name="action_type" value="update_teacher">
                                        <input type="hidden" name="teacher_id" value="<?= $t['id']; ?>">
                                        <div class="modal-header border-secondary">
                                            <h5 class="modal-title">Edit <?= htmlspecialchars($t['name']); ?></h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-2"><label class="form-label small">Name</label><input class="form-control" name="name" value="<?= htmlspecialchars($t['name']); ?>" required></div>
                                            <div class="mb-2"><label class="form-label small">Department</label>
                                                <select class="form-select" name="department_code">
                                                    <option value="">—</option>
                                                    <?php foreach ($departments as $d): ?>
                                                        <option value="<?= htmlspecialchars($d['code']); ?>" <?= $t['department_code'] === $d['code'] ? 'selected' : ''; ?>><?= htmlspecialchars($d['name']); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-2"><label class="form-label small">Designation</label><input class="form-control" name="designation" value="<?= htmlspecialchars((string) $t['designation']); ?>"></div>
                                            <div class="mb-2"><label class="form-label small">Phone</label><input class="form-control" name="phone" value="<?= htmlspecialchars((string) $t['phone']); ?>"></div>
                                            <div class="mb-2"><label class="form-label small">Salary (PKR)</label><input type="number" step="0.01" class="form-control" name="salary" value="<?= htmlspecialchars((string) $t['salary']); ?>"></div>
                                            <div class="mb-2"><label class="form-label small">Working time</label><input class="form-control" name="working_time" value="<?= htmlspecialchars((string) $t['working_time']); ?>" placeholder="e.g. Mon-Fri, 9am-4pm"></div>
                                        </div>
                                        <div class="modal-footer border-secondary">
                                            <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">Save changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="mt-3">
                <ul class="pagination justify-content-center">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?= $p; ?>&q=<?= urlencode($search); ?>&department=<?= urlencode($deptFilter); ?>"><?= $p; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="createTeacherModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark text-light">
                <form method="post">
                    <?= ait_csrf_field(); ?>
                    <input type="hidden" name="action_type" value="create_teacher">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title">Add Teacher</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body row g-2">
                        <div class="col-md-6"><label class="form-label small">Full name *</label><input class="form-control" name="name" required></div>
                        <div class="col-md-6"><label class="form-label small">Email *</label><input type="email" class="form-control" name="email" required></div>
                        <div class="col-md-6"><label class="form-label small">Department</label>
                            <select class="form-select" name="department_code">
                                <option value="">—</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?= htmlspecialchars($d['code']); ?>"><?= htmlspecialchars($d['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label small">Designation</label><input class="form-control" name="designation" placeholder="Assistant Professor"></div>
                        <div class="col-md-6"><label class="form-label small">Phone</label><input class="form-control" name="phone"></div>
                        <div class="col-md-6"><label class="form-label small">NIC</label><input class="form-control" name="nic" placeholder="42101-1234567-1"></div>
                        <div class="col-md-3"><label class="form-label small">Age</label><input type="number" min="21" max="75" class="form-control" name="age"></div>
                        <div class="col-md-3"><label class="form-label small">Gender</label>
                            <select class="form-select" name="gender"><option>Male</option><option>Female</option><option>Other</option></select>
                        </div>
                        <div class="col-md-6"><label class="form-label small">Caste</label><input class="form-control" name="caste"></div>
                        <div class="col-md-6"><label class="form-label small">Religion</label><input class="form-control" name="religion"></div>
                        <div class="col-md-4"><label class="form-label small">Salary (PKR)</label><input type="number" step="0.01" class="form-control" name="salary"></div>
                        <div class="col-md-4"><label class="form-label small">Working time</label><input class="form-control" name="working_time" placeholder="Mon-Fri, 9am-4pm"></div>
                        <div class="col-md-4"><label class="form-label small">Joining date</label><input type="date" class="form-control" name="joining_date"></div>
                        <div class="col-12"><div class="form-text">A secure temporary password is generated automatically and shown once after creation. The teacher must change it on first login.</div></div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Teacher</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
