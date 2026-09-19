<?php
require_once __DIR__ . '/../backend/session.php';
ait_start_secure_session();

if (isset($_SESSION['student_id'], $_SESSION['student_code'])) {
    header('Location: student-dashboard');
    exit;
}

require_once __DIR__ . '/../backend/data.php';
require_once __DIR__ . '/../backend/security.php';

$error_message = '';
$csp_nonce = ait_bootstrap_security();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ait_validate_csrf_post();
    ait_rate_limit('student-login', 5, 900);
    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        $error_message = 'Enter your student ID or registered email and password.';
    } else {
        $stmt = $conn->prepare('SELECT id, password, name, email, student_code, locked_until FROM students WHERE (student_code = ? OR email = ?) AND student_code IS NOT NULL AND is_active = 1 LIMIT 1');
        $stmt->bind_param('ss', $identifier, $identifier);
        $stmt->execute();
        $student = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($student && ait_is_locked_out($student['locked_until'])) {
            $error_message = 'This account is temporarily locked due to repeated failed sign-in attempts. Please try again later.';
        } elseif ($student && password_verify($password, $student['password'])) {
            session_regenerate_id(true);
            $_SESSION['student_id'] = $student['id'];
            $_SESSION['student_email'] = $student['email'];
            $_SESSION['student_code'] = $student['student_code'];
            $_SESSION['student_name'] = $student['name'];
            ait_clear_failed_login($conn, 'students', (int) $student['id']);
            header('Location: student-dashboard');
            exit;
        } else {
            if ($student) {
                ait_register_failed_login($conn, 'students', (int) $student['id']);
            }
            $error_message = 'Incorrect student ID or password combination.';
        }
    }
}

$conn->close();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Enrolled Student Login | AIT</title>
    <link rel="icon" type="image/x-icon" href="assets/images/favicon/ait.ico">
    <link rel="stylesheet" href="assets/css/public.css">
    <style nonce="<?= htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .student-login {
            width: min(100%, 980px);
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: var(--glass);
            border: 1px solid var(--glass-border);
            box-shadow: 0 24px 70px rgba(7, 61, 61, .14);
        }

        .student-login-intro {
            padding: 54px;
            background: var(--teal-dark);
            color: #f3fbf6;
        }

        .student-login-intro h1 {
            font: 600 clamp(38px, 5vw, 66px)/.98 "Space Grotesk", sans-serif;
            letter-spacing: -.04em;
            margin: 0 0 20px;
        }

        .student-login-intro p:not(.eyebrow) {
            color: #c6dcd4;
            line-height: 1.7;
        }

        .student-login-form {
            padding: 54px;
        }

        .student-login-form h2 {
            font: 600 30px "Space Grotesk", sans-serif;
            margin: 0 0 10px;
        }

        .student-login-form>p:not(.eyebrow) {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 28px;
        }

        .student-login-form form {
            display: grid;
            gap: 16px;
        }

        .student-login-form label {
            display: grid;
            gap: 7px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
        }

        .student-login-form input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid var(--line);
            border-radius: 6px;
            background: var(--glass);
            color: var(--ink);
        }

        .student-login-form input:focus {
            outline: 2px solid var(--coral);
            outline-offset: 1px;
        }

        .student-login-form button {
            border: 0;
            cursor: pointer;
            justify-content: center;
        }

        .student-login-links {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-top: 22px;
            font-size: 12px;
        }

        .student-login-links a {
            color: var(--teal);
            font-weight: 700;
        }

        @media(max-width:700px) {
            .student-login {
                grid-template-columns: 1fr;
            }

            .student-login-intro,
            .student-login-form {
                padding: 32px 26px;
            }
        }
    </style>
</head>

<body>
    <main class="student-login">
        <section class="student-login-intro">
            <p class="eyebrow">Enrolled student portal</p>
            <h1>Your AIT day, in one place.</h1>
            <p>Use the student ID and temporary password issued by the admissions office after your admission is approved.</p>
        </section>
        <section class="student-login-form">
            <p class="eyebrow">Student access</p>
            <h2>Sign in to your dashboard.</h2>
            <p>This portal is only for successfully admitted students. There is no student self-signup.</p>
            <?php if ($error_message !== ''): ?><div class="form-alert is-error"><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
            <form method="post" action="student-login">
                <?= ait_csrf_field(); ?>
                <label>Student ID or registered email<input type="text" name="identifier" required autocomplete="username" placeholder="26BSCS001"></label>
                <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
                <button class="button button-coral" type="submit">Open student dashboard <span>↗</span></button>
            </form>
            <div class="student-login-links"><a href="login">Applicant login ↗</a><a href="home">AIT home ↗</a></div>
        </section>
    </main>
</body>

</html>
