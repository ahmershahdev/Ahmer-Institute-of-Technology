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
$error_message = '';
$success = false;

$accountStmt = $pdo->prepare('SELECT name, email, password, must_change_password FROM staff WHERE id = :id AND is_active = 1 LIMIT 1');
$accountStmt->execute(['id' => $staff_id]);
$account = $accountStmt->fetch();
if (!$account) {
    $_SESSION = [];
    header('Location: login.php');
    exit;
}
$forced = (int) $account['must_change_password'] === 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ait_validate_csrf_post();
    ait_rate_limit('staff-change-password', 5, 900, 'staff:' . $staff_id);
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    $policy = ait_password_policy_error($new, [$account['name'], strtok((string) $account['email'], '@')]);

    if (!ait_password_verify($current, $account['password'])) {
        $error_message = $forced ? 'The temporary password is not correct.' : 'Your current password is not correct.';
    } elseif ($policy !== null) {
        $error_message = $policy;
    } elseif ($new !== $confirm) {
        $error_message = 'The two new passwords do not match.';
    } elseif (hash_equals($current, $new)) {
        $error_message = 'Choose a password different from the current one.';
    } else {
        $stmt = $pdo->prepare('UPDATE staff SET password = :password, must_change_password = 0 WHERE id = :id');
        $stmt->execute(['password' => ait_password_hash($new), 'id' => $staff_id]);
        session_regenerate_id(true);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Change Password | AIT Teacher Portal</title>
    <link rel="icon" type="image/x-icon" href="<?= ait_e(ait_url('assets/images/favicon/ait.ico')); ?>">
    <link rel="stylesheet" href="<?= ait_e(ait_url('assets/css/public.css')); ?>">
    <script src="<?= ait_e(ait_url('assets/js/theme.js')); ?>"></script>
</head>

<body>
    <main class="portal-card" style="max-width:440px;margin:60px auto;padding:40px;background:var(--glass);border:1px solid var(--glass-border);border-radius:12px;">
        <p class="eyebrow">Staff portal</p>
        <h1 style="font:600 26px 'Space Grotesk',sans-serif;"><?= $forced ? 'Set a new password' : 'Change your password'; ?></h1>
        <p style="color:var(--muted);font-size:13px;"><?= $forced ? 'This account was created or reset by an administrator. Choose a private password before continuing.' : 'Enter your current password, then choose a new one.'; ?></p>
        <?php if ($error_message !== ''): ?><div class="form-alert is-error" role="alert"><?= ait_e($error_message); ?></div><?php endif; ?>
        <?php if ($success): ?>
            <div class="form-alert is-success" role="status">Password updated. <a href="dashboard.php">Continue to your dashboard &rarr;</a></div>
        <?php else: ?>
            <form method="post" style="display:grid;gap:14px;">
                <?= ait_csrf_field(); ?>
                <label><?= $forced ? 'Temporary password' : 'Current password'; ?><input type="password" name="current_password" required autocomplete="current-password"></label>
                <label>New password<input type="password" name="new_password" minlength="10" maxlength="72" required autocomplete="new-password" placeholder="At least 10 characters"></label>
                <label>Repeat new password<input type="password" name="confirm_password" minlength="10" maxlength="72" required autocomplete="new-password"></label>
                <small class="field-hint">Use 10 or more characters with an uppercase letter, a lowercase letter, and a number. Avoid your name or email.</small>
                <button class="button button-coral" type="submit">Save password</button>
            </form>
            <?php if (!$forced): ?><p style="margin-top:16px"><a href="dashboard.php">&larr; Back to dashboard</a></p><?php endif; ?>
        <?php endif; ?>
    </main>
</body>

</html>
