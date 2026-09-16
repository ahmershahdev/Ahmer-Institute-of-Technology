<?php
require_once __DIR__ . '/../backend/session.php';
ait_start_secure_session();

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

require_once '../backend/security.php';
require_once '../backend/pdo.php';

$error_message = '';
$superadmin_secret_key = ait_env('SUPERADMIN_SECRET_KEY', '');

$csp_nonce = ait_bootstrap_security();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ait_validate_csrf_post();
    ait_rate_limit('admin-login', 5, 900);

    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password'] ?? '');
    $secret_key = trim($_POST['secret_key'] ?? '');

    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        $stmt = ait_pdo()->prepare('SELECT id, password, name, role FROM admins WHERE email = :email AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1');
        $stmt->execute(['email' => $email]);
        $admin = $stmt->fetch();

        if ($admin) {

            $requires_secret_key = ($admin['role'] === 'super_admin');
            $secret_key_valid = !$requires_secret_key || ($superadmin_secret_key !== '' && hash_equals($superadmin_secret_key, $secret_key));

            if (password_verify($password, $admin['password']) && $secret_key_valid) {
                session_regenerate_id(true);

                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_email'] = $email;
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_role'] = $admin['role'];

                $login_stmt = ait_pdo()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = :id');
                $login_stmt->execute(['id' => $admin['id']]);

                header("Location: dashboard.php");
                exit;
            } else {
                $error_message = "Invalid administrative credentials.";
            }
        } else {
            $error_message = "Invalid administrative credentials.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Security Gateway</title>

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

    <style nonce="<?php echo htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        :root {
            --bg-color: #030712;
            --card-bg: rgba(17, 24, 39, 0.75);
            --card-border: rgba(255, 255, 255, 0.08);
            --primary-accent: #38bdf8;
            --primary-glow: rgba(56, 189, 248, 0.35);
            --input-bg: rgba(3, 7, 18, 0.6);
            --text-main: #f9fafb;
            --text-muted: #9ca3af;
        }

        body,
        html {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* Dynamic Background Radial Elements */
        .bg-glow-1 {
            position: absolute;
            top: -10%;
            left: -10%;
            width: 50vw;
            height: 50vw;
            background: radial-gradient(circle, rgba(56, 189, 248, 0.12) 0%, rgba(0, 0, 0, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .bg-glow-2 {
            position: absolute;
            bottom: -15%;
            right: -10%;
            width: 60vw;
            height: 60vw;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.1) 0%, rgba(0, 0, 0, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        .login-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 760px;
            padding: 20px;
        }

        .login-card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 20px;
            border: 1px solid var(--card-border);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            padding: 30px 42px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(56, 189, 248, 0.1);
            border: 1px solid rgba(56, 189, 248, 0.2);
            color: var(--primary-accent);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 20px;
            user-select: none;
        }

        .brand-logo {
            max-width: 72px;
            display: block;
            margin: 0 auto 16px;
            filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.3));
            user-select: none;
        }

        .form-header {
            color: var(--text-main);
            font-weight: 800;
            font-size: 1.5rem;
            letter-spacing: -0.02em;
            margin-bottom: 6px;
            text-align: center;
        }

        .sub-header {
            text-align: center;
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-bottom: 22px;
            font-weight: 400;
        }

        .input-group-custom {
            position: relative;
            margin-bottom: 14px;
        }

        .admin-login-form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0 18px;
        }

        .admin-login-form .email-field,
        .admin-login-form .submit-field {
            grid-column: 1 / -1;
        }

        .input-group-custom label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper .icon-left {
            position: absolute;
            left: 14px;
            color: #6b7280;
            font-size: 20px;
            transition: color 0.2s ease;
            pointer-events: none;
        }

        .input-wrapper .icon-right {
            position: absolute;
            right: 14px;
            width: 32px;
            height: 32px;
            border: 0;
            background: transparent;
            color: #6b7280;
            cursor: pointer;
            display: grid;
            place-items: center;
            transition: color 0.2s ease, transform 0.2s ease;
            user-select: none;
        }

        .input-wrapper .icon-right:hover {
            color: var(--text-main);
        }

        .animated-eye-toggle svg {
            width: 24px;
            height: 24px;
        }

        .animated-eye-toggle .eye-iris {
            fill: #6b7280;
            transition: transform .2s ease, fill .2s ease;
            transform-origin: center;
        }

        .animated-eye-toggle .eye-lid {
            transition: d .2s ease, stroke .2s ease;
        }

        .animated-eye-toggle.active .eye-iris {
            fill: var(--primary-accent);
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

        .form-control-custom {
            width: 100%;
            height: 46px;
            padding: 0 16px 0 44px;
            background-color: var(--input-bg);
            border: 1px solid var(--card-border);
            border-radius: 10px;
            font-size: 0.9rem;
            color: var(--text-main);
            font-family: inherit;
            transition: all 0.2s ease;
        }

        .form-control-custom.has-right-icon {
            padding-right: 44px;
        }

        .form-control-custom::placeholder {
            color: #4b5563;
        }

        .form-control-custom:focus {
            outline: none;
            border-color: var(--primary-accent);
            box-shadow: 0 0 0 4px var(--primary-glow);
            background-color: rgba(3, 7, 18, 0.85);
        }

        .form-control-custom:focus+.icon-left,
        .input-wrapper:focus-within .icon-left {
            color: var(--primary-accent);
        }

        .btn-admin {
            width: 100%;
            height: 50px;
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            border: none;
            color: #ffffff;
            font-weight: 700;
            font-size: 0.95rem;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35);
            margin-top: 10px;
        }

        .btn-admin:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(2, 132, 199, 0.5);
            background: linear-gradient(135deg, #38bdf8 0%, #0284c7 100%);
        }

        .btn-admin:active {
            transform: translateY(0);
        }

        .btn-door-submit {
            position: relative;
            height: 50px;
            background: linear-gradient(135deg, #0284c7, #0f766e);
            border: 0;
            color: #fff;
            font-weight: 700;
            border-radius: 10px;
            overflow: hidden;
            transition: background-color .4s ease, box-shadow .4s ease, transform .2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
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

        .recovery-link {
            display: block;
            margin-top: 16px;
            color: #7dd3fc;
            text-align: center;
            font-size: .8rem;
            font-weight: 700;
            text-decoration: none;
        }

        @media (max-width: 620px) {
            .login-wrapper {
                padding: 12px;
            }

            .login-card {
                padding: 26px 22px;
            }

            .admin-login-form {
                display: block;
            }
        }

        .recovery-link:hover {
            color: #fff;
        }

        .alert-custom {
            background-color: rgba(127, 29, 29, 0.3);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.825rem;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <div class="bg-glow-1"></div>
    <div class="bg-glow-2"></div>

    <div class="login-wrapper">
        <div class="login-card">
            <div class="text-center">
                <span class="brand-badge">
                    <span class="material-symbols-outlined" style="font-size: 14px;">verified_user</span> Restricted Gateway
                </span>
            </div>

            <img src="../assets/images/logo/ait_logo.png" alt="AIT Admin" class="brand-logo" onerror="this.style.display='none'">
            <h1 class="form-header">Super Admin</h1>
            <p class="sub-header">Enter elevated credentials to continue</p>

            <div id="errorAlertContainer">
                <?php if (!empty($error_message)): ?>
                    <div class="alert-custom" role="alert">
                        <span class="material-symbols-outlined" style="font-size: 18px;">error</span>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="loginForm" class="admin-login-form">
                <?php echo ait_csrf_field(); ?>

                <div class="input-group-custom email-field">
                    <label for="emailInput">Administrator Email</label>
                    <div class="input-wrapper">
                        <span class="material-symbols-outlined icon-left">person</span>
                        <input type="email" id="emailInput" class="form-control-custom" name="email" placeholder="Enter your administrator email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required autocomplete="username">
                    </div>
                </div>

                <div class="input-group-custom">
                    <label for="secretKeyInput">Super Admin Key</label>
                    <div class="input-wrapper">
                        <span class="material-symbols-outlined icon-left">key</span>
                        <input type="password" id="secretKeyInput" class="form-control-custom has-right-icon" name="secret_key" placeholder="Enter the configured security key" value="" required autocomplete="one-time-code">
                        <button type="button" class="icon-right animated-eye-toggle" data-target="secretKeyInput" aria-label="Show security key">
                            <svg viewBox="0 0 24 24" fill="none">
                                <g class="eye-head-group">
                                    <path class="eye-lid" d="M2 12S6 5 12 5S22 12 22 12S18 19 12 19S2 12 2 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    <circle class="eye-iris" cx="12" cy="12" r="3.5" />
                                </g>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="input-group-custom mb-4">
                    <label for="passwordInput">Account Password</label>
                    <div class="input-wrapper">
                        <span class="material-symbols-outlined icon-left">lock</span>
                        <input type="password" id="passwordInput" class="form-control-custom has-right-icon" name="password" placeholder="Enter your administrator password" required autocomplete="current-password">
                        <button type="button" class="icon-right animated-eye-toggle" id="togglePassword" data-target="passwordInput" aria-label="Show password">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <g class="eye-head-group">
                                    <path class="eye-lid" d="M2 12S6 5 12 5S22 12 22 12S18 19 12 19S2 12 2 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    <circle class="eye-iris" cx="12" cy="12" r="3.5" />
                                </g>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="submit-field">
                    <button type="submit" class="btn-door-submit" id="submitBtn">
                        <span class="btn-text">Authenticate</span>
                        <div class="door-anim-container">
                            <svg width="60" height="32" viewBox="0 0 60 32" fill="none" aria-hidden="true">
                                <text x="10" y="8" font-size="10" font-weight="bold" fill="#ffffff" class="question-mark" opacity="0">?</text>
                                <g class="walk-person">
                                    <circle cx="12" cy="6" r="3" fill="#ffffff" />
                                    <line x1="12" y1="9" x2="12" y2="17" stroke="#ffffff" stroke-width="2" />
                                    <line class="person-leg-left" x1="12" y1="17" x2="8" y2="25" stroke="#ffffff" stroke-width="2" stroke-linecap="round" />
                                    <line class="person-leg-right" x1="12" y1="17" x2="16" y2="25" stroke="#ffffff" stroke-width="2" stroke-linecap="round" />
                                </g>
                                <rect x="22" y="4" width="16" height="24" rx="1" stroke="#ffffff" stroke-width="2" fill="none" />
                                <rect class="door-panel" x="23" y="5" width="14" height="22" fill="#ffffff" />
                                <circle class="door-panel" cx="25" cy="16" r="1" fill="#0f766e" />
                            </svg>
                        </div>
                    </button>
                    <a class="recovery-link" href="forgot-password.php">Forgot admin password or credentials?</a>
                </div>
            </form>
        </div>
    </div>

    <script nonce="<?php echo htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        document.addEventListener('DOMContentLoaded', function() {
            const toggles = document.querySelectorAll('.animated-eye-toggle');
            const loginForm = document.getElementById('loginForm');
            const submitBtn = document.getElementById('submitBtn');

            toggles.forEach(function(toggle) {
                toggle.addEventListener('click', function() {
                    const input = document.getElementById(this.dataset.target || 'passwordInput');
                    const visible = input.type === 'password';
                    input.type = visible ? 'text' : 'password';
                    this.classList.toggle('active', visible);
                    this.classList.add('blinking');
                    this.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
                    window.setTimeout(() => this.classList.remove('blinking'), 220);
                });
            });

            loginForm.addEventListener('submit', function(event) {
                if (submitBtn.classList.contains('anim-active')) return;
                event.preventDefault();
                submitBtn.classList.remove('anim-error', 'anim-success');
                submitBtn.classList.add('anim-active');
                const formData = new FormData(loginForm);
                fetch(loginForm.action, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(function(response) {
                        if (response.redirected) {
                            submitBtn.classList.add('anim-success');
                            window.setTimeout(() => window.location.href = response.url, 800);
                            return null;
                        }
                        return response.text();
                    })
                    .then(function(html) {
                        if (!html) return;
                        const doc = new DOMParser().parseFromString(html, 'text/html');
                        const errorAlert = doc.querySelector('.alert-custom');
                        submitBtn.classList.add('anim-error');
                        if (errorAlert) document.getElementById('errorAlertContainer').innerHTML = errorAlert.outerHTML;
                        window.setTimeout(() => submitBtn.classList.remove('anim-active', 'anim-error'), 2000);
                    })
                    .catch(function() {
                        loginForm.submit();
                    });
            });
        });
    </script>
</body>

</html>