<?php
require_once __DIR__ . '/backend/session.php';
ait_start_secure_session();

// Applicant login for admissions, challans, and test slips.
if (isset($_SESSION['student_id'])) {
    header(!empty($_SESSION['student_code']) ? "Location: student-login" : "Location: dashboard");
    exit;
}

require_once 'backend/data.php';
require_once 'backend/security.php';

$error_message = '';
$csp_nonce = ait_bootstrap_security();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ait_validate_csrf_post();
    ait_rate_limit('applicant-login', 5, 900);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = (string) ($_POST['password'] ?? '');

    if (!$email || $password === '') {
        $error_message = 'Please fill in all fields.';
    } else {
        $stmt = $conn->prepare('SELECT id, password, name, email, student_code, locked_until FROM students WHERE email = ? AND is_active = 1 LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $student = $result->fetch_assoc();
            if (ait_is_locked_out($student['locked_until'])) {
                $error_message = 'This account is temporarily locked due to repeated failed sign-in attempts. Please try again later.';
            } elseif (password_verify($password, $student['password']) && empty($student['student_code'])) {
                session_regenerate_id(true);
                $_SESSION['student_id'] = $student['id'];
                $_SESSION['student_email'] = $student['email'];
                $_SESSION['student_name'] = $student['name'];
                ait_clear_failed_login($conn, 'students', (int) $student['id']);
                header('Location: dashboard');
                exit;
            } else {
                ait_register_failed_login($conn, 'students', (int) $student['id']);
                $error_message = 'Use the enrolled student portal after admission approval.';
            }
        } else {
            $error_message = 'Use the enrolled student portal after admission approval.';
        }
        $stmt->close();
    }
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <title>Applicant Login</title>
    <link rel="icon" type="image/x-icon" href="assets/images/favicon/ait.ico">
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600&display=swap">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/style.css">

    <style>
        body,
        html {
            margin: 0;
            padding: 0;
            height: 100%;
            width: 100%;
            font-family: 'Roboto', sans-serif;
            background-color: #f4f6f9;
            background-image: radial-gradient(circle at 15% 15%, rgba(15, 118, 110, 0.14), transparent 40%), radial-gradient(circle at 85% 85%, rgba(239, 106, 80, 0.12), transparent 45%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            display: flex;
            flex-wrap: wrap;
            max-width: 900px;
            width: 100%;
            margin: 20px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .info-box,
        .form-box {
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .info-box {
            flex: 1 1 400px;
            background-color: #f8f9fa;
            border-right: 1px solid #e9ecef;
        }

        .info-box h4 {
            margin-bottom: 20px;
            font-weight: 600;
            text-align: center;
        }

        .info-box p {
            color: #333;
            font-size: 15px;
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .form-box {
            flex: 1 1 350px;
            background-color: #ffffff;
        }

        .form-box .brand-mark {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            display: grid;
            place-items: center;
            border-radius: 16px;
            background: #0f766e;
            color: #fff;
            font: 800 22px "Space Grotesk", Arial, sans-serif;
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
            color: #212529;
            font-weight: bold;
        }

        .input-with-icon {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-with-icon .left-icon {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 20px;
            z-index: 10;
        }

        .input-with-icon input {
            padding-left: 45px !important;
            height: 48px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
            position: relative;
            z-index: 1;
            width: 100%;
        }

        .input-with-icon input.has-right-icon {
            padding-right: 48px !important;
        }

        .input-with-icon input:focus {
            border-color: #80bdff !important;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
            background-color: #ffffff !important;
            outline: 0;
        }

        /* --- ANIMATED EYE TOGGLE & LIGHT BEAM STYLES --- */
        .animated-eye-toggle {
            position: absolute;
            right: 10px;
            width: 32px;
            height: 32px;
            cursor: pointer;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            padding: 0;
            outline: none;
        }

        .eye-head-group {
            transform-origin: 12px 12px;
            transition: transform 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .eye-iris {
            transform-origin: 12px 12px;
            transition: transform 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
            fill: #6c757d;
        }

        .eye-lid {
            transform-origin: 12px 12px;
            transition: transform 0.25s ease;
        }

        .eye-beam-projection {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            border-radius: 4px;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.4s ease, box-shadow 0.4s ease;
            z-index: 2;
        }

        .animated-eye-toggle.active .eye-head-group {
            transform: translateY(-2px) rotate(-18deg);
        }

        .animated-eye-toggle.active .eye-iris {
            transform: translate(-3.5px, -1px);
            fill: #1e40af;
        }

        @keyframes eyeBlink {
            0% {
                transform: scaleY(1);
            }

            40% {
                transform: scaleY(0.1);
            }

            80% {
                transform: scaleY(1);
            }
        }

        .animated-eye-toggle.blinking .eye-head-group {
            animation: eyeBlink 0.22s ease-in-out;
        }

        .input-with-icon.eye-active .eye-beam-projection {
            opacity: 1;
            background: linear-gradient(270deg, rgba(30, 64, 175, 0.22) 0%, rgba(30, 64, 175, 0.08) 55%, rgba(30, 64, 175, 0.01) 100%);
            box-shadow: inset 0 0 14px rgba(30, 64, 175, 0.25), 0 0 10px rgba(30, 64, 175, 0.2);
        }

        /* --- DOOR ANIMATION SIGN-IN BUTTON STYLES --- */
        .btn-door-submit {
            position: relative;
            height: 48px;
            background-color: #0d6efd;
            border: none;
            color: #ffffff;
            font-weight: 500;
            letter-spacing: 0.5px;
            border-radius: 6px;
            overflow: hidden;
            transition: background-color 0.4s ease, box-shadow 0.4s ease, transform 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-door-submit .btn-text {
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .btn-door-submit .door-anim-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }

        /* Running State */
        .btn-door-submit.anim-active .btn-text {
            opacity: 0;
            transform: translateY(-10px);
        }

        .btn-door-submit.anim-active .door-anim-container {
            opacity: 1;
        }

        /* Person Walking Animation */
        @keyframes personWalk {
            0% {
                transform: translateX(-35px);
            }

            50% {
                transform: translateX(-10px);
            }

            100% {
                transform: translateX(4px) scale(0.85);
                opacity: 0;
            }
        }

        @keyframes legSwing {

            0%,
            100% {
                transform: rotate(-18deg);
            }

            50% {
                transform: rotate(18deg);
            }
        }

        .anim-active .walk-person {
            animation: personWalk 1.6s forwards ease-in-out;
            transform-origin: center;
        }

        .anim-active .person-leg-left {
            animation: legSwing 0.35s infinite alternate ease-in-out;
            transform-origin: 12px 17px;
        }

        .anim-active .person-leg-right {
            animation: legSwing 0.35s infinite alternate-reverse ease-in-out;
            transform-origin: 12px 17px;
        }

        /* Door Opening Perspective Effect */
        .door-panel {
            transform-origin: 22px 12px;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .anim-success .door-panel {
            transform: perspective(100px) rotateY(-70deg);
        }

        .btn-door-submit.anim-success {
            background-color: #198754 !important;
            box-shadow: 0 0 15px rgba(25, 135, 84, 0.5);
        }

        /* Invalid / Error State */
        @keyframes questionBounce {
            0% {
                opacity: 0;
                transform: translateY(4px) scale(0.5);
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
            animation: questionBounce 0.4s forwards ease-out;
        }

        .btn-door-submit.anim-error {
            background-color: #dc2626 !important;
            box-shadow: 0 0 15px rgba(220, 38, 38, 0.5);
            animation: btnShake 0.4s ease-in-out;
        }

        @keyframes btnShake {

            0%,
            100% {
                transform: translateX(0);
            }

            20%,
            60% {
                transform: translateX(-6px);
            }

            40%,
            80% {
                transform: translateX(6px);
            }
        }

        .btn {
            padding: 10px;
            font-weight: 500;
            letter-spacing: 0.5px;
            border-radius: 4px;
        }

        /* Dashboard-aligned glass surface */
        body,
        html {
            background-color: #04091a;
            background-image: radial-gradient(circle at 20% 20%, rgba(20, 184, 166, 0.18), transparent 42%), radial-gradient(circle at 80% 80%, rgba(239, 106, 80, 0.14), transparent 48%);
            color: #f1f5f9;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(24px) saturate(160%);
            -webkit-backdrop-filter: blur(24px) saturate(160%);
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 20px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.48);
        }

        .info-box,
        .form-box {
            background: rgba(255, 255, 255, 0.06);
        }

        .info-box {
            border-right: 1px solid rgba(255, 255, 255, 0.12);
        }

        .info-box h4 {
            color: #2dd4bf;
        }

        .info-box p,
        .info-box .text-dark,
        .form-header,
        .form-check-label {
            color: rgba(241, 245, 249, 0.82) !important;
        }

        .form-box .brand-mark {
            box-shadow: 0 6px 18px rgba(20, 184, 166, 0.25);
        }

        .input-with-icon input {
            background: rgba(4, 9, 26, 0.36);
            border-color: rgba(255, 255, 255, 0.16);
            color: #f8fafc;
        }

        .input-with-icon input::placeholder {
            color: rgba(203, 213, 225, 0.62);
        }

        .input-with-icon input:focus {
            background: rgba(4, 9, 26, 0.52);
            border-color: #14b8a6;
            box-shadow: 0 0 0 0.2rem rgba(20, 184, 166, 0.18);
        }

        .input-with-icon .left-icon {
            color: #5eead4;
        }

        .eye-iris {
            fill: #99f6e4;
        }

        .btn-door-submit {
            background: linear-gradient(135deg, #0d9488, #0f766e);
            box-shadow: 0 8px 20px rgba(13, 148, 136, 0.25);
        }

        .btn-door-submit:hover:not(:disabled) {
            background: linear-gradient(135deg, #14b8a6, #0d9488);
            box-shadow: 0 10px 24px rgba(20, 184, 166, 0.34);
        }

        .btn-outline-success {
            color: #5eead4;
            border-color: rgba(45, 212, 191, 0.5);
        }

        .btn-outline-success:hover {
            background: rgba(20, 184, 166, 0.16);
            border-color: #2dd4bf;
            color: #ccfbf1;
        }

        @media (max-width: 700px) {
            .login-container {
                margin: 14px;
            }

            .info-box,
            .form-box {
                padding: 28px 24px;
            }

            .info-box {
                border-right: 0;
                border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            }
        }
    </style>
    <script src="./assets/js/theme.js"></script>
</head>

<body>
    <div class="login-container">
        <div class="info-box">
            <h4>INSTRUCTIONS</h4>
            <p>
                1. <b>To avoid delays/complications in the verification process, please do not pay fees through Fund Transfer, Easy Paisa, or Jazz Cash.</b>
            </p>
            <p>
                2. There is no need to submit any document or printed Application Form physically at the Admission Office.
            </p>
            <p>
                3. There is no admission window open at the moment; deadlines will appear here when registration is active.
            </p>
            <hr>
            <p class="mb-0 text-dark">
                <em>Note:</em> Mention your CNIC number with queries. You will receive a reply within 24 hours. <br><b>No physical visit required.</b>
            </p>
        </div>

        <div class="form-box">
            <div class="brand-mark">AIT</div>
            <h4 class="form-header">Applicant Login</h4>

            <div id="errorAlertContainer">
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger py-2 text-center mb-3" style="font-size: 14px;" role="alert">
                        <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
            </div>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" id="loginForm">
                <?php echo ait_csrf_field(); ?>
                <div class="mb-3 input-with-icon">
                    <span class="material-icons left-icon">mail</span>
                    <input type="email" class="form-control" name="email" id="emailInput" placeholder="Email Address" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : ''; ?>" required autocomplete="email">
                </div>

                <div class="mb-3 input-with-icon">
                    <span class="material-icons left-icon">lock</span>
                    <input type="password" class="form-control has-right-icon" id="passwordInput" name="password" placeholder="Password" required autocomplete="current-password">
                    <div class="eye-beam-projection"></div>
                    <button type="button" class="animated-eye-toggle" id="togglePassword" aria-label="Toggle password visibility">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <g class="eye-head-group">
                                <path class="eye-lid" d="M2 12S6 5 12 5S22 12 22 12S18 19 12 19S2 12 2 12Z" stroke="#6c757d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                <circle class="eye-iris" cx="12" cy="12" r="3.5" />
                            </g>
                        </svg>
                    </button>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-door-submit" id="btnSubmit">
                        <span class="btn-text">Sign In</span>
                        <div class="door-anim-container">
                            <svg width="60" height="32" viewBox="0 0 60 32" fill="none">
                                <!-- Question Mark Emblem for Invalid State -->
                                <text x="10" y="8" font-size="10" font-weight="bold" fill="#ffffff" class="question-mark" opacity="0">?</text>

                                <!-- Walking Person Stick Figure -->
                                <g class="walk-person">
                                    <circle cx="12" cy="6" r="3" fill="#ffffff" />
                                    <line x1="12" y1="9" x2="12" y2="17" stroke="#ffffff" stroke-width="2" />
                                    <line class="person-leg-left" x1="12" y1="17" x2="8" y2="25" stroke="#ffffff" stroke-width="2" stroke-linecap="round" />
                                    <line class="person-leg-right" x1="12" y1="17" x2="16" y2="25" stroke="#ffffff" stroke-width="2" stroke-linecap="round" />
                                </g>

                                <!-- Door Frame -->
                                <rect x="22" y="4" width="16" height="24" rx="1" stroke="#ffffff" stroke-width="2" fill="none" />
                                <!-- Inner Door Panel -->
                                <rect class="door-panel" x="23" y="5" width="14" height="22" fill="#ffffff" />
                                <!-- Door Knob -->
                                <circle class="door-panel" cx="25" cy="16" r="1" fill="#0d6efd" />
                            </svg>
                        </div>
                    </button>
                    <a href="register" class="btn btn-outline-success">Register</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script nonce="<?php echo htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('passwordInput');
            const loginForm = document.getElementById('loginForm');
            const btnSubmit = document.getElementById('btnSubmit');

            // 1. Password Visibility Eye Toggle
            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function() {
                    const parentContainer = this.closest('.input-with-icon');
                    const isPassword = passwordInput.getAttribute('type') === 'password';

                    this.classList.add('blinking');
                    setTimeout(() => this.classList.remove('blinking'), 220);

                    if (isPassword) {
                        passwordInput.setAttribute('type', 'text');
                        this.classList.add('active');
                        parentContainer.classList.add('eye-active');
                    } else {
                        passwordInput.setAttribute('type', 'password');
                        this.classList.remove('active');
                        parentContainer.classList.remove('eye-active');
                    }
                });
            }

            // 2. Door Animation on Form Submission
            if (loginForm && btnSubmit) {
                loginForm.addEventListener('submit', function(e) {
                    // Prevent immediate submit to run visual animation state
                    if (!btnSubmit.classList.contains('anim-active')) {
                        e.preventDefault();

                        // Reset animation state classes
                        btnSubmit.classList.remove('anim-error', 'anim-success');
                        btnSubmit.classList.add('anim-active');

                        const formData = new FormData(loginForm);

                        // Async validation check
                        fetch(loginForm.action, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        }).then(response => {
                            if (response.redirected) {
                                // Success - Credentials valid
                                btnSubmit.classList.add('anim-success');
                                setTimeout(() => {
                                    window.location.href = response.url;
                                }, 800);
                            } else {
                                return response.text();
                            }
                        }).then(html => {
                            if (html) {
                                // Check if response indicates server redirect or error html back
                                const parser = new DOMParser();
                                const doc = parser.parseFromString(html, 'text/html');
                                const errorAlert = doc.querySelector('.alert-danger');

                                if (!errorAlert && doc.querySelector('title')?.innerText.includes('Dashboard')) {
                                    btnSubmit.classList.add('anim-success');
                                    setTimeout(() => {
                                        window.location.href = 'dashboard';
                                    }, 800);
                                } else {
                                    // Error - Invalid Login
                                    btnSubmit.classList.add('anim-error');
                                    if (errorAlert) {
                                        document.getElementById('errorAlertContainer').innerHTML = errorAlert.outerHTML;
                                    }

                                    // Reset button to normal after 2 seconds
                                    setTimeout(() => {
                                        btnSubmit.classList.remove('anim-active', 'anim-error');
                                    }, 2000);
                                }
                            }
                        }).catch(() => {
                            // Native fallback submit
                            loginForm.submit();
                        });
                    }
                });
            }
        });
    </script>
</body>

</html>