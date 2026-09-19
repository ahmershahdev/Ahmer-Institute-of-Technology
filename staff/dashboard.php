<?php
require_once __DIR__ . '/../backend/session.php';
ait_start_secure_session();

if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../backend/security.php';
require_once __DIR__ . '/../backend/pdo.php';
$csp_nonce = ait_bootstrap_security();
$pdo = ait_pdo();
$staff_id = (int) $_SESSION['staff_id'];

$stmt = $pdo->prepare('SELECT * FROM staff WHERE id = :id AND is_active = 1 LIMIT 1');
$stmt->execute(['id' => $staff_id]);
$staff = $stmt->fetch();

if (!$staff) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}

if ((int) $staff['must_change_password'] === 1) {
    header('Location: change-password.php');
    exit;
}

$roleLabels = ['hod' => 'Head of Department', 'security' => 'Security Guard', 'worker' => 'Worker', 'clerical' => 'Clerical Staff', 'other' => 'Staff'];
$roleLabel = $roleLabels[$staff['role_type']] ?? 'Staff';

$deptName = null;
$teacherCount = null;
$subjectCount = null;
if ($staff['role_type'] === 'hod' && $staff['department_code']) {
    $deptStmt = $pdo->prepare('SELECT name FROM departments WHERE code = :code LIMIT 1');
    $deptStmt->execute(['code' => $staff['department_code']]);
    $deptName = $deptStmt->fetchColumn();

    $tCountStmt = $pdo->prepare('SELECT COUNT(*) FROM teachers WHERE department_code = :code AND is_active = 1');
    $tCountStmt->execute(['code' => $staff['department_code']]);
    $teacherCount = (int) $tCountStmt->fetchColumn();

    $sCountStmt = $pdo->prepare('SELECT COUNT(*) FROM subjects WHERE department_code = :code AND is_active = 1');
    $sCountStmt->execute(['code' => $staff['department_code']]);
    $subjectCount = (int) $sCountStmt->fetchColumn();
}

$pageTitle = 'Dashboard';
$activePage = 'dashboard';
require __DIR__ . '/partials/shell_start.php';
?>

<div style="margin-bottom:20px;">
    <p class="eyebrow" style="margin-bottom:4px;">Welcome back</p>
    <h1 style="font:600 26px 'Space Grotesk',sans-serif;margin:0 0 6px;"><?= htmlspecialchars($staff['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
    <p style="color:var(--muted);margin:0;">
        <?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?>
        <?php if ($deptName): ?> &middot; <?= htmlspecialchars($deptName, ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
        &middot; Staff ID <?= htmlspecialchars((string) $staff['staff_code'], ENT_QUOTES, 'UTF-8'); ?>
    </p>
</div>

<?php if ($staff['role_type'] === 'hod'): ?>
    <div class="kpi-row">
        <div class="kpi-card"><div class="kpi-label">Department</div><div class="kpi-value" style="font-size:18px;"><?= htmlspecialchars((string) $deptName, ENT_QUOTES, 'UTF-8'); ?></div></div>
        <div class="kpi-card"><div class="kpi-label">Active teachers</div><div class="kpi-value"><?= $teacherCount; ?></div></div>
        <div class="kpi-card"><div class="kpi-label">Subjects offered</div><div class="kpi-value"><?= $subjectCount; ?></div></div>
    </div>
    <div class="panel">
        <div class="panel-header"><h2>Department overview</h2><a class="btn-outline-portal" href="department.php">Full view</a></div>
        <div class="panel-body">
            <p style="color:var(--muted);margin:0;">View your department's full teacher roster and subject catalog on the Department Overview page.</p>
        </div>
    </div>
<?php else: ?>
    <div class="panel">
        <div class="panel-header"><h2>Your role</h2></div>
        <div class="panel-body">
            <p style="color:var(--muted);margin:0;">Operational tools specific to the <?= htmlspecialchars(strtolower($roleLabel), ENT_QUOTES, 'UTF-8'); ?> role are coming to this portal soon. Contact the admin office for any account or scheduling changes.</p>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/partials/shell_end.php'; ?>
