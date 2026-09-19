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

ait_require_admin_permission($pdo, $current_admin, 'manage_staff');

$msg = '';
$msg_type = 'success';
$roleTypes = ['hod' => 'HOD', 'security' => 'Security Guard', 'worker' => 'Worker', 'clerical' => 'Clerical', 'other' => 'Other'];
$departments = $pdo->query('SELECT code, name FROM departments WHERE is_active = 1 ORDER BY name')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ait_validate_csrf_post();
    ait_rate_limit('admin-staff-action', 40, 300);
    $action = (string) ($_POST['action_type'] ?? '');

    try {
        if ($action === 'create_staff') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_SANITIZE_EMAIL);
            $role_title = trim((string) ($_POST['role_title'] ?? '')) ?: null;
            $role_type = in_array($_POST['role_type'] ?? '', array_keys($roleTypes), true) ? $_POST['role_type'] : 'other';
            $department_code = trim((string) ($_POST['department_code'] ?? '')) ?: null;
            $phone = trim((string) ($_POST['phone'] ?? '')) ?: null;
            $nic = trim((string) ($_POST['nic'] ?? '')) ?: null;
            $salary = ($_POST['salary'] ?? '') !== '' ? (float) $_POST['salary'] : null;
            $joining_date = trim((string) ($_POST['joining_date'] ?? '')) ?: null;

            if ($name === '' || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('A valid name and email are required.');
            }
            $exists = $pdo->prepare('SELECT 1 FROM staff WHERE email = :email LIMIT 1');
            $exists->execute(['email' => $email]);
            if ($exists->fetchColumn()) {
                throw new RuntimeException('A staff member with that email already exists.');
            }

            $temp_password = ait_generate_temp_password();
            $hash = password_hash($temp_password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
            $insert = $pdo->prepare('INSERT INTO staff (name, email, password, role_title, role_type, department_code, phone, nic, salary, joining_date, must_change_password, created_by) VALUES (:name, :email, :password, :role_title, :role_type, :department_code, :phone, :nic, :salary, :joining_date, 1, :created_by)');
            $insert->execute([
                'name' => $name, 'email' => $email, 'password' => $hash, 'role_title' => $role_title,
                'role_type' => $role_type, 'department_code' => $department_code, 'phone' => $phone,
                'nic' => $nic, 'salary' => $salary, 'joining_date' => $joining_date ?: null, 'created_by' => $current_admin_id,
            ]);
            $newStaffId = (int) $pdo->lastInsertId();
            $staffCode = ait_generate_account_code($pdo, 'staff', 'staff_code', 'S');
            $pdo->prepare('UPDATE staff SET staff_code = :code WHERE id = :id')->execute(['code' => $staffCode, 'id' => $newStaffId]);

            if ($role_type === 'hod' && $department_code) {
                $pdo->prepare('UPDATE departments SET hod_staff_id = :sid WHERE code = :code')->execute(['sid' => $newStaffId, 'code' => $department_code]);
            }

            ait_log_audit($pdo, 'admin', $current_admin_id, 'create_staff', 'staff', $newStaffId, ['role_type' => $role_type, 'department_code' => $department_code], $current_admin['name']);
            $msg = "Staff member '{$name}' created as {$staffCode}. Temporary password: {$temp_password}";
        } elseif ($action === 'toggle_staff') {
            $id = (int) ($_POST['staff_id'] ?? 0);
            $stmtS = $pdo->prepare('SELECT is_active FROM staff WHERE id = :id');
            $stmtS->execute(['id' => $id]);
            $new = ((int) $stmtS->fetchColumn()) ? 0 : 1;
            $pdo->prepare('UPDATE staff SET is_active = :active WHERE id = :id')->execute(['active' => $new, 'id' => $id]);
            ait_log_audit($pdo, 'admin', $current_admin_id, $new ? 'enable_staff' : 'disable_staff', 'staff', $id, [], $current_admin['name']);
            $msg = $new ? 'Staff account re-enabled.' : 'Staff account disabled.';
        } elseif ($action === 'reset_staff_password') {
            $id = (int) ($_POST['staff_id'] ?? 0);
            $temp_password = ait_generate_temp_password();
            $hash = password_hash($temp_password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE staff SET password = :password, must_change_password = 1, failed_attempts = 0, locked_until = NULL WHERE id = :id')->execute(['password' => $hash, 'id' => $id]);
            ait_log_audit($pdo, 'admin', $current_admin_id, 'reset_staff_password', 'staff', $id, [], $current_admin['name']);
            $msg = "Password reset. New temporary password: {$temp_password}";
        } elseif ($action === 'delete_staff') {
            $id = (int) ($_POST['staff_id'] ?? 0);
            $pdo->prepare('DELETE FROM staff WHERE id = :id')->execute(['id' => $id]);
            ait_log_audit($pdo, 'admin', $current_admin_id, 'delete_staff', 'staff', $id, [], $current_admin['name']);
            $msg = 'Staff account deleted.';
        }
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $msg_type = 'danger';
    }
}

$roleFilter = trim((string) ($_GET['role_type'] ?? ''));
$where = [];
$params = [];
if ($roleFilter !== '' && array_key_exists($roleFilter, $roleTypes)) {
    $where[] = 'role_type = :role_type';
    $params['role_type'] = $roleFilter;
}
$whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
$staffList = $pdo->prepare("SELECT * FROM staff {$whereSql} ORDER BY id DESC");
$staffList->execute($params);
$staffRows = $staffList->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Manage Staff | AIT Admin</title>
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

        .page-shell { max-width: 1100px; margin: 0 auto; padding: 32px 20px; }
        .topbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 24px; }
        .card-panel { background: rgba(15, 23, 42, .55); border: 1px solid rgba(148, 163, 184, .18); border-radius: 14px; backdrop-filter: blur(10px); }
        .table { color: #e2e8f0; margin-bottom: 0; }
        .table > :not(caption) > * > * { background: transparent; color: #e2e8f0; border-color: rgba(148, 163, 184, .15); }
        .form-control, .form-select { background: rgba(8, 15, 26, .6); border: 1px solid rgba(148, 163, 184, .25); color: #e2e8f0; }
        .form-control:focus, .form-select:focus { background: rgba(8, 15, 26, .8); color: #fff; border-color: #38bdf8; box-shadow: 0 0 0 .2rem rgba(56, 189, 248, .25); }
        .staff-code { font-family: 'Space Grotesk', monospace; color: #38bdf8; }
        a.back-link { color: #94a3b8; text-decoration: none; }
        a.back-link:hover { color: #e2e8f0; }
    </style>
</head>

<body>
    <div class="page-shell">
        <div class="topbar">
            <div>
                <a href="dashboard.php" class="back-link"><i class="bi bi-arrow-left me-1"></i>Back to dashboard</a>
                <h1 class="h4 fw-bold mt-2 mb-0">Manage Staff</h1>
                <p class="text-muted small mb-0">HODs, security guards, workers, and clerical staff</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createStaffModal"><i class="bi bi-person-plus me-1"></i>Add Staff</button>
        </div>

        <?php if ($msg !== ''): ?>
            <div class="alert alert-<?= $msg_type; ?> alert-dismissible fade show"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card-panel p-3 mb-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small">Role type</label>
                    <select name="role_type" class="form-select">
                        <option value="">All roles</option>
                        <?php foreach ($roleTypes as $key => $label): ?>
                            <option value="<?= $key; ?>" <?= $roleFilter === $key ? 'selected' : ''; ?>><?= $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2"><button class="btn btn-outline-light w-100" type="submit">Filter</button></div>
            </form>
        </div>

        <div class="card-panel table-responsive">
            <table class="table table-hover align-middle m-0">
                <thead>
                    <tr><th>Code</th><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if ($staffRows === []): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No staff found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($staffRows as $s): ?>
                        <tr>
                            <td><span class="staff-code"><?= htmlspecialchars($s['staff_code']); ?></span></td>
                            <td><?= htmlspecialchars($s['name']); ?></td>
                            <td><?= htmlspecialchars($s['email']); ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($roleTypes[$s['role_type']] ?? $s['role_type']); ?></span><?php if ($s['role_title']): ?><div class="small text-muted"><?= htmlspecialchars($s['role_title']); ?></div><?php endif; ?></td>
                            <td><?= htmlspecialchars($s['department_code'] ?? '—'); ?></td>
                            <td><?php if ((int) $s['is_active'] === 1): ?><span class="badge bg-success">Active</span><?php else: ?><span class="badge bg-secondary">Disabled</span><?php endif; ?></td>
                            <td class="d-flex gap-1 flex-wrap">
                                <form method="post" class="d-inline" onsubmit="return confirm('Reset password?');">
                                    <?= ait_csrf_field(); ?>
                                    <input type="hidden" name="action_type" value="reset_staff_password">
                                    <input type="hidden" name="staff_id" value="<?= $s['id']; ?>">
                                    <button class="btn btn-sm btn-outline-warning" type="submit"><i class="bi bi-key"></i></button>
                                </form>
                                <form method="post" class="d-inline">
                                    <?= ait_csrf_field(); ?>
                                    <input type="hidden" name="action_type" value="toggle_staff">
                                    <input type="hidden" name="staff_id" value="<?= $s['id']; ?>">
                                    <button class="btn btn-sm btn-outline-info" type="submit"><i class="bi bi-power"></i></button>
                                </form>
                                <form method="post" class="d-inline" onsubmit="return confirm('Permanently delete this staff account?');">
                                    <?= ait_csrf_field(); ?>
                                    <input type="hidden" name="action_type" value="delete_staff">
                                    <input type="hidden" name="staff_id" value="<?= $s['id']; ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="createStaffModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-light">
                <form method="post">
                    <?= ait_csrf_field(); ?>
                    <input type="hidden" name="action_type" value="create_staff">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title">Add Staff Member</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body row g-2">
                        <div class="col-md-6"><label class="form-label small">Full name *</label><input class="form-control" name="name" required></div>
                        <div class="col-md-6"><label class="form-label small">Email *</label><input type="email" class="form-control" name="email" required></div>
                        <div class="col-md-6"><label class="form-label small">Role type</label>
                            <select class="form-select" name="role_type">
                                <?php foreach ($roleTypes as $key => $label): ?><option value="<?= $key; ?>"><?= $label; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label small">Role title</label><input class="form-control" name="role_title" placeholder="e.g. Head of Department"></div>
                        <div class="col-md-6"><label class="form-label small">Department</label>
                            <select class="form-select" name="department_code">
                                <option value="">—</option>
                                <?php foreach ($departments as $d): ?><option value="<?= htmlspecialchars($d['code']); ?>"><?= htmlspecialchars($d['name']); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label small">Phone</label><input class="form-control" name="phone"></div>
                        <div class="col-md-6"><label class="form-label small">NIC</label><input class="form-control" name="nic"></div>
                        <div class="col-md-6"><label class="form-label small">Salary (PKR)</label><input type="number" step="0.01" class="form-control" name="salary"></div>
                        <div class="col-md-6"><label class="form-label small">Joining date</label><input type="date" class="form-control" name="joining_date"></div>
                        <div class="col-12"><div class="form-text">A secure temporary password is generated automatically and shown once after creation.</div></div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Staff</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
