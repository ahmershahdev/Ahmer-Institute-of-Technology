<?php
require_once __DIR__ . '/../backend/session.php';
ait_start_secure_session();

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once '../backend/security.php';
require_once '../backend/pdo.php';

$error_message = '';
$success_message = '';
$superadmin_secret_key = ait_env('SUPERADMIN_SECRET_KEY', '');
$csp_nonce = ait_bootstrap_security();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ait_validate_csrf_post();
    ait_rate_limit('admin-recovery', 3, 900);

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $secret_key = trim((string) ($_POST['secret_key'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $password_confirmation = (string) ($_POST['password_confirmation'] ?? '');

    $valid_password = preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d\W_]{12,64}$/', $password);
    $configured_key = trim((string) $superadmin_secret_key);
    $valid_request = filter_var($email, FILTER_VALIDATE_EMAIL)
        && $superadmin_secret_key !== ''
        && hash_equals($configured_key, $secret_key)
        && $valid_password
        && hash_equals($password, $password_confirmation);

    if (!$valid_request) {
        $error_message = 'Recovery could not be completed. Check the account details, security key, and password requirements.';
    } else {
        $pdo = ait_pdo();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id FROM admins WHERE email = :email AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) AND role = \'super_admin\' LIMIT 1 FOR UPDATE');
            $stmt->execute(['email' => $email]);
            $admin = $stmt->fetch();

            if ($admin) {
                $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
                $hashed_password = password_hash($password, $algo);
                $update = $pdo->prepare('UPDATE admins SET password = :password, last_login_at = NULL WHERE id = :id');
                $update->execute(['password' => $hashed_password, 'id' => $admin['id']]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Admin password recovery failed: ' . $e->getMessage());
            $error_message = 'Recovery could not be completed. Please try again.';
        }

        if ($error_message === '') {
            $success_message = 'If the account and security key were valid, the password has been updated. You can now sign in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Account Recovery | AIT</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon/ait.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0">
    <style nonce="<?php echo htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        :root {
            --ink: #eef7ff;
            --muted: #9db2c7;
            --line: rgba(180, 220, 245, .18);
            --panel: rgba(9, 25, 42, .78);
            --accent: #38bdf8;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 24px;
            overflow: hidden;
            color: var(--ink);
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: radial-gradient(circle at 15% 10%, rgba(56, 189, 248, .18), transparent 34%), radial-gradient(circle at 90% 90%, rgba(45, 212, 191, .12), transparent 32%), #040b14;
        }

        .orb {
            position: fixed;
            width: 38vw;
            aspect-ratio: 1;
            border-radius: 50%;
            pointer-events: none;
            filter: blur(2px);
            opacity: .35;
        }

        .orb.one {
            top: -25%;
            left: -14%;
            background: radial-gradient(circle, rgba(14, 165, 233, .35), transparent 68%);
        }

        .orb.two {
            right: -14%;
            bottom: -30%;
            background: radial-gradient(circle, rgba(20, 184, 166, .25), transparent 68%);
        }

        .recovery-shell {
            position: relative;
            width: min(100%, 760px);
            padding: 30px 42px;
            border: 1px solid var(--line);
            border-radius: 24px;
            background: var(--panel);
            box-shadow: 0 28px 90px rgba(0, 0, 0, .48);
            backdrop-filter: blur(22px);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }

        .brand-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: var(--accent);
            color: #04121b;
            font: 700 18px "Space Grotesk", sans-serif;
            flex-shrink: 0;
        }

        .brand-kicker {
            color: var(--accent);
            font-size: .7rem;
            font-weight: 800;
            letter-spacing: .13em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 1.8rem;
            letter-spacing: -.03em;
        }

        .intro {
            margin: 0 0 20px;
            color: var(--muted);
            font-size: .88rem;
            line-height: 1.65;
        }

        .field {
            margin-bottom: 14px;
        }

        .recovery-form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0 18px;
        }

        .recovery-form .email-field,
        .recovery-form .key-field,
        .recovery-form .submit-field {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin: 0 0 7px;
            color: #b6c9d9;
            font-size: .73rem;
            font-weight: 800;
            letter-spacing: .09em;
            text-transform: uppercase;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap>.material-symbols-outlined {
            position: absolute;
            top: 14px;
            left: 14px;
            color: #71869a;
            font-size: 20px;
            pointer-events: none;
        }

        input {
            width: 100%;
            height: 46px;
            padding: 0 46px;
            border: 1px solid var(--line);
            border-radius: 11px;
            outline: 0;
            color: var(--ink);
            background: rgba(2, 9, 17, .68);
            font: inherit;
            transition: border-color .2s, box-shadow .2s;
        }

        input::placeholder {
            color: #60758a;
        }

        input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(56, 189, 248, .15);
        }

        .input-wrap .icon-right {
            position: absolute;
            top: 9px;
            right: 10px;
            width: 32px;
            height: 32px;
            display: grid;
            place-items: center;
            border: 0;
            color: #71869a;
            background: transparent;
            cursor: pointer;
        }

        .animated-eye-toggle svg {
            width: 24px;
            height: 24px;
        }

        .animated-eye-toggle .eye-iris {
            fill: #71869a;
            transition: transform .2s, fill .2s;
            transform-origin: center;
        }

        .animated-eye-toggle.active .eye-iris {
            fill: var(--accent);
            transform: scale(.72);
        }

        .animated-eye-toggle.blinking .eye-head-group {
            animation: adminEyeBlink .22s ease;
        }

        @keyframes adminEyeBlink {
            50% {
                transform: scaleY(.08);
                transform-origin: center;
            }
        }

        .notice {
            padding: 12px 14px;
            margin-bottom: 18px;
            border-radius: 10px;
            font-size: .82rem;
            line-height: 1.5;
        }

        .notice.error {
            color: #fecaca;
            border: 1px solid rgba(248, 113, 113, .32);
            background: rgba(127, 29, 29, .25);
        }

        .notice.success {
            color: #bbf7d0;
            border: 1px solid rgba(74, 222, 128, .3);
            background: rgba(22, 101, 52, .24);
        }

        .btn-door-submit {
            position: relative;
            width: 100%;
            height: 50px;
            border: 0;
            border-radius: 11px;
            color: #fff;
            background: linear-gradient(135deg, #0284c7, #0f766e);
            box-shadow: 0 12px 25px rgba(2, 132, 199, .24);
            font: 700 .9rem 'Plus Jakarta Sans', sans-serif;
            cursor: pointer;
            overflow: hidden;
            transition: transform .2s, box-shadow .2s;
        }

        .btn-door-submit .btn-text {
            transition: opacity .3s ease, transform .3s ease;
        }

        .btn-door-submit .door-anim-container {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity .3s ease;
        }

        .btn-door-submit.anim-active .btn-text {
            opacity: 0;
            transform: translateY(-10px);
        }

        .btn-door-submit.anim-active .door-anim-container {
            opacity: 1;
        }

        @keyframes adminPersonWalk {
            0% {
                transform: translateX(-35px);
            }

            50% {
                transform: translateX(-10px);
            }

            100% {
                transform: translateX(4px) scale(.85);
                opacity: 0;
            }
        }

        @keyframes adminLegSwing {

            0%,
            100% {
                transform: rotate(-18deg);
            }

            50% {
                transform: rotate(18deg);
            }
        }

        .anim-active .walk-person {
            animation: adminPersonWalk 1.6s forwards ease-in-out;
            transform-origin: center;
        }

        .anim-active .person-leg-left {
            animation: adminLegSwing .35s infinite alternate ease-in-out;
            transform-origin: 12px 17px;
        }

        .anim-active .person-leg-right {
            animation: adminLegSwing .35s infinite alternate-reverse ease-in-out;
            transform-origin: 12px 17px;
        }

        .door-panel {
            transform-origin: 22px 12px;
            transition: transform .6s cubic-bezier(.4, 0, .2, 1);
        }

        .anim-success .door-panel {
            transform: perspective(100px) rotateY(-70deg);
        }

        .btn-door-submit.anim-success {
            background: #198754 !important;
            box-shadow: 0 0 15px rgba(25, 135, 84, .5);
        }

        @keyframes adminQuestionBounce {
            0% {
                opacity: 0;
                transform: translateY(4px) scale(.5);
            }

            60% {
                opacity: 1;
                transform: translateY(-4px) scale(1.2);
            }

            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .anim-error .question-mark {
            animation: adminQuestionBounce .4s forwards ease-out;
        }

        .btn-door-submit.anim-error {
            background: #dc2626 !important;
            box-shadow: 0 0 15px rgba(220, 38, 38, .5);
            animation: adminButtonShake .4s ease-in-out;
        }

        @keyframes adminButtonShake {

            0%,
            100% {
                transform: translateX(0);
            }

            20%,
            60% {
                transform: translateX(-5px);
            }

            40%,
            80% {
                transform: translateX(5px);
            }
        }

        .links {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 22px;
            color: var(--muted);
            font-size: .8rem;
        }

        a {
            color: #7dd3fc;
            text-decoration: none;
            font-weight: 700;
        }

        a:hover {
            color: #fff;
        }

        @media (max-width:520px) {
            .recovery-shell {
                padding: 32px 22px;
            }

            .recovery-form {
                display: block;
            }
        }
    </style>
</head>

<body>
    <div class="orb one"></div>
    <div class="orb two"></div>
    <main class="recovery-shell">
        <div class="brand"><span class="brand-icon">AIT</span>
            <div class="brand-kicker">Protected administrator recovery</div>
        </div>
        <h1>Reset admin access</h1>
        <p class="intro">Use the administrator email and configured security key to create a new password. Recovery details are never disclosed in the response.</p>
        <div id="recoveryNoticeContainer">
            <?php if ($error_message): ?><div class="notice error" role="alert"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
            <?php if ($success_message): ?><div class="notice success" role="status"><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
        </div>
        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" id="recoveryForm" class="recovery-form">
            <?php echo ait_csrf_field(); ?>
            <div class="field email-field"><label for="email">Administrator email</label>
                <div class="input-wrap"><span class="material-symbols-outlined">mail</span><input id="email" name="email" type="email" autocomplete="username" placeholder="Enter your administrator email" required value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
            </div>
            <div class="field key-field"><label for="secret_key">Super-admin security key</label>
                <div class="input-wrap"><span class="material-symbols-outlined">key</span><input id="secret_key" name="secret_key" type="password" autocomplete="one-time-code" placeholder="Enter the configured security key" required><button class="icon-right animated-eye-toggle" type="button" data-target="secret_key" aria-label="Show security key"><svg viewBox="0 0 24 24" fill="none">
                            <g class="eye-head-group">
                                <path d="M2 12S6 5 12 5S22 12 22 12S18 19 12 19S2 12 2 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                <circle class="eye-iris" cx="12" cy="12" r="3.5" />
                            </g>
                        </svg></button></div>
            </div>
            <div class="field"><label for="password">New password</label>
                <div class="input-wrap"><span class="material-symbols-outlined">lock</span><input id="password" name="password" type="password" minlength="12" maxlength="64" autocomplete="new-password" placeholder="Use 12+ characters with upper, lower case and a number" required><button class="icon-right animated-eye-toggle" type="button" data-target="password" aria-label="Show password"><svg viewBox="0 0 24 24" fill="none">
                            <g class="eye-head-group">
                                <path d="M2 12S6 5 12 5S22 12 22 12S18 19 12 19S2 12 2 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                <circle class="eye-iris" cx="12" cy="12" r="3.5" />
                            </g>
                        </svg></button></div>
            </div>
            <div class="field"><label for="password_confirmation">Confirm new password</label>
                <div class="input-wrap"><span class="material-symbols-outlined">lock_reset</span><input id="password_confirmation" name="password_confirmation" type="password" minlength="12" maxlength="64" autocomplete="new-password" placeholder="Re-enter your new password" required><button class="icon-right animated-eye-toggle" type="button" data-target="password_confirmation" aria-label="Show password"><svg viewBox="0 0 24 24" fill="none">
                            <g class="eye-head-group">
                                <path d="M2 12S6 5 12 5S22 12 22 12S18 19 12 19S2 12 2 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                <circle class="eye-iris" cx="12" cy="12" r="3.5" />
                            </g>
                        </svg></button></div>
            </div>
            <div class="submit-field"><button class="btn-door-submit" id="submitBtn" type="submit"><span class="btn-text">Update administrator password</span>
                    <div class="door-anim-container"><svg width="60" height="32" viewBox="0 0 60 32" fill="none" aria-hidden="true"><text x="10" y="8" font-size="10" font-weight="bold" fill="#ffffff" class="question-mark" opacity="0">?</text>
                            <g class="walk-person">
                                <circle cx="12" cy="6" r="3" fill="#ffffff" />
                                <line x1="12" y1="9" x2="12" y2="17" stroke="#ffffff" stroke-width="2" />
                                <line class="person-leg-left" x1="12" y1="17" x2="8" y2="25" stroke="#ffffff" stroke-width="2" stroke-linecap="round" />
                                <line class="person-leg-right" x1="12" y1="17" x2="16" y2="25" stroke="#ffffff" stroke-width="2" stroke-linecap="round" />
                            </g>
                            <rect x="22" y="4" width="16" height="24" rx="1" stroke="#ffffff" stroke-width="2" fill="none" />
                            <rect class="door-panel" x="23" y="5" width="14" height="22" fill="#ffffff" />
                            <circle class="door-panel" cx="25" cy="16" r="1" fill="#0f766e" />
                        </svg></div>
                </button></div>
        </form>
        <div class="links"><a href="login.php">Back to admin login</a></div>
    </main>
    <script nonce="<?php echo htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        const submitButton = document.getElementById('submitBtn');
        document.querySelectorAll('.animated-eye-toggle').forEach(function(toggle) {
            toggle.addEventListener('click', function() {
                const input = document.getElementById(this.dataset.target);
                const visible = input.type === 'password';
                input.type = visible ? 'text' : 'password';
                this.classList.toggle('active', visible);
                this.classList.add('blinking');
                this.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
                window.setTimeout(() => this.classList.remove('blinking'), 220);
            });
        });

        document.getElementById('recoveryForm').addEventListener('submit', function(event) {
            if (submitButton.classList.contains('anim-active')) return;
            event.preventDefault();
            const form = this;
            submitButton.classList.remove('anim-error', 'anim-success');
            submitButton.classList.add('anim-active');
            fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(response) {
                    return response.text();
                })
                .then(function(html) {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const notice = doc.querySelector('.notice');
                    const success = doc.querySelector('.notice.success');
                    submitButton.classList.add(success ? 'anim-success' : 'anim-error');
                    if (notice) document.getElementById('recoveryNoticeContainer').innerHTML = notice.outerHTML;
                    window.setTimeout(function() {
                        submitButton.classList.remove('anim-active', 'anim-error', 'anim-success');
                    }, 1800);
                })
                .catch(function() {
                    form.submit();
                });
        });
    </script>
</body>

</html>