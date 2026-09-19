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
$error_message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ait_validate_csrf_post();
    ait_rate_limit('teacher-change-password', 5, 900);
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (strlen($new) < 10) {
        $error_message = 'Your new password must be at least 10 characters.';
    } elseif ($new !== $confirm) {
        $error_message = 'Passwords do not match.';
    } else {
        $hash = password_hash($new, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('UPDATE teachers SET password = :password, must_change_password = 0 WHERE id = :id');
        $stmt->execute(['password' => $hash, 'id' => $teacher_id]);
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
    <title>Set a New Password | AIT</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon/ait.ico">
    <link rel="stylesheet" href="../assets/css/public.css">
</head>

<body>
    <main class="portal-card" style="max-width:420px;margin:60px auto;padding:40px;background:var(--glass);border:1px solid var(--glass-border);border-radius:12px;">
        <p class="eyebrow">Teacher portal</p>
        <h1 style="font:600 26px 'Space Grotesk',sans-serif;">Set a new password</h1>
        <p style="color:var(--muted);font-size:13px;">This account was created by an administrator. Choose a permanent password before continuing.</p>
        <?php if ($error_message !== ''): ?><div class="form-alert is-error"><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <?php if ($success): ?>
            <div class="form-alert">Password updated. <a href="dashboard.php">Continue to your dashboard &rarr;</a></div>
        <?php else: ?>
            <form method="post" style="display:grid;gap:14px;">
                <?= ait_csrf_field(); ?>
                <label>New password<input type="password" name="new_password" minlength="10" required></label>
                <label>Confirm new password<input type="password" name="confirm_password" minlength="10" required></label>
                <button class="button button-coral" type="submit">Save password</button>
            </form>
        <?php endif; ?>
    </main>
</body>

</html>
