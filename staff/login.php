<?php
require_once __DIR__ . '/../backend/session.php';
ait_start_secure_session();

if (isset($_SESSION['staff_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../backend/security.php';
require_once __DIR__ . '/../backend/pdo.php';

$error_message = '';
$csp_nonce = ait_bootstrap_security();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ait_validate_csrf_post();
    ait_rate_limit('staff-login', 5, 900);

    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = (string) ($_POST['password'] ?? '');

    if (empty($email) || empty($password) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email and password.';
    } else {
        $stmt = ait_pdo()->prepare('SELECT id, password, name, locked_until, must_change_password FROM staff WHERE email = :email AND is_active = 1 LIMIT 1');
        $stmt->execute(['email' => $email]);
        $staff = $stmt->fetch();

        if ($staff && ait_is_locked_out($staff['locked_until'])) {
            $error_message = 'This account is temporarily locked due to repeated failed sign-in attempts. Please try again later.';
        } elseif ($staff && password_verify($password, $staff['password'])) {
            session_regenerate_id(true);
            $_SESSION['staff_id'] = $staff['id'];
            $_SESSION['staff_email'] = $email;
            $_SESSION['staff_name'] = $staff['name'];

            ait_clear_failed_login(ait_pdo(), 'staff', (int) $staff['id']);
            $login_stmt = ait_pdo()->prepare('UPDATE staff SET last_login_at = NOW() WHERE id = :id');
            $login_stmt->execute(['id' => $staff['id']]);

            if ((int) $staff['must_change_password'] === 1) {
                header('Location: change-password.php');
                exit;
            }

            header('Location: dashboard.php');
            exit;
        } else {
            if ($staff) {
                ait_register_failed_login(ait_pdo(), 'staff', (int) $staff['id']);
            }
            $error_message = 'Invalid staff credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Portal Login | AIT</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon/ait.ico">
    <link rel="stylesheet" href="../assets/css/public.css">
    <style nonce="<?= htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .portal-card {
            width: min(100%, 420px);
            padding: 40px;
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
        }

        .portal-card h1 {
            font: 600 28px "Space Grotesk", sans-serif;
            margin: 0 0 8px;
        }

        .portal-card p {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 24px;
        }

        .portal-card form {
            display: grid;
            gap: 14px;
        }

        .portal-card label {
            display: grid;
            gap: 6px;
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
        }

        .portal-card input {
            padding: 12px 14px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: var(--glass);
            color: var(--ink);
        }

        .form-alert.is-error {
            margin-bottom: 16px;
        }
    </style>
</head>

<body>
    <main class="portal-card">
        <p class="eyebrow">Staff portal</p>
        <h1>Sign in</h1>
        <p>Administrative staff access to operational tools.</p>
        <?php if ($error_message !== ''): ?><div class="form-alert is-error"><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        <form method="post" action="login.php">
            <?= ait_csrf_field(); ?>
            <label>Email<input type="email" name="email" required autocomplete="username"></label>
            <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
            <button class="button button-coral" type="submit">Sign in <span>&#8599;</span></button>
        </form>
    </main>
</body>

</html>
