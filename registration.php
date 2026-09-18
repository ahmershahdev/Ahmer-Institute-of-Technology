<?php
require_once __DIR__ . '/backend/session.php';
ait_start_secure_session();

if (isset($_SESSION['student_id'])) {
    header("Location: dashboard");
    exit;
}

require_once 'backend/data.php';
require_once 'backend/security.php';

$error_message = '';
$success_message = '';

$csp_nonce = ait_bootstrap_security();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ait_validate_csrf_post();
    $name            = trim($_POST['name'] ?? '');
    $fatherName      = trim($_POST['fatherName'] ?? '');
    $email           = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $cnic            = trim($_POST['cnic'] ?? '');
    $dob             = trim($_POST['dob'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');
    $password        = $_POST['password'] ?? '';
    $retype_password = $_POST['retype_password'] ?? '';

    if (empty($name) || empty($fatherName) || empty($email) || empty($cnic) || empty($dob) || empty($phone) || empty($password)) {
        $error_message = "All fields are required.";
    } elseif (!preg_match("/^[a-zA-Z\s]{3,50}$/", $name)) {
        $error_message = "Enter a valid candidate name (letters and spaces only, 3-50 characters).";
    } elseif (!preg_match("/^[a-zA-Z\s]{3,50}$/", $fatherName)) {
        $error_message = "Enter a valid father name (letters and spaces only, 3-50 characters).";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } elseif (!preg_match("/^\d{5}-\d{7}-\d{1}$/", $cnic)) {
        $error_message = "CNIC format must be XXXXX-XXXXXXX-X.";
    } elseif (date_diff(date_create($dob), date_create('today'))->y < 18) {
        $error_message = "Candidates must be at least 18 years old to register.";
    } elseif (!preg_match("/^\+923\d{9}$/", $phone)) {
        $error_message = "Phone number must be in the format: +923XXXXXXXXX.";
    } elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d\w\W]{8,64}$/", $password)) {
        $error_message = "Password must be 8-64 characters long with at least 1 uppercase letter, 1 lowercase letter, and 1 number.";
    } elseif ($password !== $retype_password) {
        $error_message = "Passwords do not match.";
    } else {
        $check_stmt = $conn->prepare("SELECT id FROM students WHERE email = ? OR cnic = ? LIMIT 1");
        $check_stmt->bind_param("ss", $email, $cnic);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result && $check_result->num_rows > 0) {
            $error_message = "An account with this Email or CNIC already exists.";
            $check_stmt->close();
        } else {
            $check_stmt->close();

            $algo = PASSWORD_BCRYPT;
            if (defined('PASSWORD_ARGON2ID')) {
                $algo = PASSWORD_ARGON2ID;
            }

            $hashed_password = password_hash($password, $algo);

            $stmt = $conn->prepare("INSERT INTO students (name, father_name, email, cnic, dob, phone, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $name, $fatherName, $email, $cnic, $dob, $phone, $hashed_password);

            if ($stmt->execute()) {
                $success_message = "Registration successful! Redirecting to login...";
                $_POST = [];
            } else {
                $error_message = "An unexpected error occurred. Please try again later.";
            }
            $stmt->close();
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Registration | Admissions Portal</title>

    <link rel="icon" type="image/png" href="assets/images/favicon/favicon.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-blue: #1e40af;
            --primary-hover: #1d4ed8;
            --glass-bg: rgba(255, 255, 255, 0.94);
            --glass-border: rgba(255, 255, 255, 0.6);
            --text-main: #0f172a;
            --text-muted: #64748b;
            --input-border: #cbd5e1;
            --error-color: #dc2626;
            --success-color: #16a34a;
        }

        body,
        html {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(rgba(15, 23, 42, 0.75), rgba(15, 23, 42, 0.75)),
                url('./assets/images/background/university.png') no-repeat center center fixed;
            background-size: cover;
            color: var(--text-main);
        }

        .page-wrapper {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 30px 15px;
        }

        .header-card {
            text-align: center;
            margin-bottom: 20px;
            color: #ffffff;
            max-width: 650px;
        }

        .header-card h1 {
            font-weight: 700;
            font-size: 1.85rem;
            letter-spacing: -0.02em;
            margin-bottom: 6px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.4);
        }

        .header-card p {
            font-size: 0.925rem;
            color: #cbd5e1;
            margin-bottom: 0;
        }

        .form-container {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 32px 28px;
            width: 100%;
            max-width: 860px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .brand-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .brand-header img {
            max-width: 80px;
            height: auto;
            margin-bottom: 10px;
        }

        .brand-header h2 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 0;
        }

        .form-group-custom {
            margin-bottom: 16px;
        }

        .form-label-custom {
            color: var(--text-main);
            font-size: 0.825rem;
            font-weight: 600;
            margin-bottom: 5px;
            display: block;
        }

        .input-group-custom {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-group-custom .field-icon {
            position: absolute;
            left: 12px;
            color: #94a3b8;
            font-size: 20px;
            pointer-events: none;
            transition: color 0.2s ease;
            z-index: 5;
        }

        .input-group-custom .form-control {
            background: #ffffff;
            border: 1px solid var(--input-border);
            color: #1e293b;
            padding-left: 42px;
            padding-right: 14px;
            height: 44px;
            font-size: 0.875rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .input-group-custom .form-control.has-right-icon {
            padding-right: 48px;
        }

        .input-group-custom .form-control:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.15);
        }

        .input-group-custom .form-control.is-invalid {
            border-color: var(--error-color);
            background-image: none;
        }

        .input-group-custom .form-control.is-valid {
            border-color: var(--success-color);
            background-image: none;
        }

        /* --- ANIMATED EYE TOGGLE & LIGHT BEAM STYLES --- */
        .animated-eye-toggle {
            position: absolute;
            right: 8px;
            width: 32px;
            height: 32px;
            cursor: pointer;
            z-index: 6;
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
            fill: #64748b;
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
            border-radius: 8px;
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

        .input-group-custom.eye-active .eye-beam-projection {
            opacity: 1;
            background: linear-gradient(270deg, rgba(30, 64, 175, 0.22) 0%, rgba(30, 64, 175, 0.08) 55%, rgba(30, 64, 175, 0.01) 100%);
            box-shadow: inset 0 0 14px rgba(30, 64, 175, 0.25), 0 0 10px rgba(30, 64, 175, 0.2);
        }

        /* Password Strength Indicator */
        .password-strength-wrapper {
            margin-top: 6px;
            display: none;
        }

        .password-strength-bar {
            height: 4px;
            border-radius: 2px;
            background-color: #e2e8f0;
            overflow: hidden;
            display: flex;
        }

        .password-strength-bar div {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }

        .password-hint {
            font-size: 0.725rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .error {
            color: var(--error-color);
            font-size: 0.75rem;
            font-weight: 500;
            margin-top: 4px;
            min-height: 16px;
        }

        /* --- DOOR SUBMIT BUTTON STYLES FOR REGISTRATION --- */
        .btn-door-submit {
            position: relative;
            height: 48px;
            background: var(--primary-blue);
            border: none;
            color: #ffffff;
            font-weight: 600;
            font-size: 0.95rem;
            border-radius: 8px;
            padding: 12px;
            overflow: hidden;
            transition: background-color 0.4s ease, box-shadow 0.4s ease, transform 0.2s ease;
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-door-submit:hover:not(:disabled) {
            background: var(--primary-hover);
            box-shadow: 0 6px 16px rgba(30, 64, 175, 0.35);
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

        /* Active Running Animation */
        .btn-door-submit.anim-active .btn-text {
            opacity: 0;
            transform: translateY(-10px);
        }

        .btn-door-submit.anim-active .door-anim-container {
            opacity: 1;
        }

        /* Walking Person Animation */
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

        /* Door 3D Swing */
        .door-panel {
            transform-origin: 22px 12px;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .anim-success .door-panel {
            transform: perspective(100px) rotateY(-70deg);
        }

        .btn-door-submit.anim-success {
            background-color: var(--success-color) !important;
            box-shadow: 0 0 16px rgba(22, 163, 74, 0.5);
        }

        /* Invalid Error State */
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
            background-color: var(--error-color) !important;
            box-shadow: 0 0 16px rgba(220, 38, 38, 0.5);
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

        .login-link-container {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-top: 16px;
        }

        .login-link-container a {
            color: var(--primary-blue);
            font-weight: 600;
            text-decoration: none;
        }

        .login-link-container a:hover {
            text-decoration: underline;
        }

        footer.page-copyright {
            margin-top: 20px;
            color: #cbd5e1;
            font-size: 0.775rem;
            text-align: center;
        }

        /* Dashboard-aligned glass surface */
        body,
        html {
            background-color: #04091a;
            background-image: linear-gradient(rgba(4, 9, 26, 0.64), rgba(4, 9, 26, 0.8)), url('./assets/images/background/university.png');
        }

        .header-card h1 {
            color: #f1f5f9;
        }

        .form-container {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(24px) saturate(160%);
            -webkit-backdrop-filter: blur(24px) saturate(160%);
            border-color: rgba(255, 255, 255, 0.16);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.48);
        }

        .brand-header h2,
        .form-label-custom {
            color: #f1f5f9;
        }

        .brand-header img {
            filter: drop-shadow(0 6px 18px rgba(20, 184, 166, 0.25));
        }

        .input-group-custom .form-control {
            background: rgba(4, 9, 26, 0.36);
            border-color: rgba(255, 255, 255, 0.16);
            color: #f8fafc;
        }

        .input-group-custom .form-control::placeholder {
            color: rgba(203, 213, 225, 0.62);
        }

        .input-group-custom .form-control:focus {
            background: rgba(4, 9, 26, 0.52);
            border-color: #14b8a6;
            box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.18);
        }

        .input-group-custom .field-icon {
            color: #5eead4;
        }

        .password-hint,
        .login-link-container {
            color: rgba(241, 245, 249, 0.64);
        }

        .password-strength-bar {
            background-color: rgba(255, 255, 255, 0.16);
        }

        .login-link-container a {
            color: #5eead4;
        }

        .login-link-container a:hover {
            color: #ccfbf1;
        }

        .btn-door-submit {
            background: linear-gradient(135deg, #0d9488, #0f766e);
            box-shadow: 0 8px 20px rgba(13, 148, 136, 0.25);
        }

        .btn-door-submit:hover:not(:disabled) {
            background: linear-gradient(135deg, #14b8a6, #0d9488);
            box-shadow: 0 10px 24px rgba(20, 184, 166, 0.34);
        }
    </style>
    <script src="./assets/js/theme.js"></script>
</head>

<body>
    <div class="page-wrapper">
        <div class="header-card">
            <h1>Undergraduate Admissions Portal</h1>
            <p>Session 2026–2027 &bull; Enter your details carefully to create your candidate account.</p>
        </div>

        <div class="form-container">
            <div class="brand-header">
                <img src="./assets/images/logo/ait_logo.png" alt="University Logo" />
                <h2>Candidate Registration</h2>
            </div>

            <div id="alertContainer">
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger py-2 text-center mb-3" role="alert">
                        <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success py-2 text-center mb-3" role="alert">
                        <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
            </div>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" id="registerForm" novalidate>
                <?php echo ait_csrf_field(); ?>
                <div class="row">
                    <!-- Candidate Full Name -->
                    <div class="col-md-4 form-group-custom">
                        <label for="name" class="form-label-custom">Full Name (as per Matric/CNIC)</label>
                        <div class="input-group-custom">
                            <span class="material-symbols-outlined field-icon">person</span>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8') : ''; ?>" required minlength="3" maxlength="50" placeholder="Syed Ahmer Shah" autocomplete="name" />
                        </div>
                        <div class="error" id="name_error"></div>
                    </div>

                    <!-- Father's Name -->
                    <div class="col-md-4 form-group-custom">
                        <label for="fatherName" class="form-label-custom">Father's Full Name</label>
                        <div class="input-group-custom">
                            <span class="material-symbols-outlined field-icon">family_restroom</span>
                            <input type="text" class="form-control" id="fatherName" name="fatherName" value="<?php echo isset($_POST['fatherName']) ? htmlspecialchars($_POST['fatherName'], ENT_QUOTES, 'UTF-8') : ''; ?>" required minlength="3" maxlength="50" placeholder="Father's Full Name" />
                        </div>
                        <div class="error" id="fatherName_error"></div>
                    </div>

                    <!-- Email Address -->
                    <div class="col-md-4 form-group-custom">
                        <label for="email" class="form-label-custom">Email Address</label>
                        <div class="input-group-custom">
                            <span class="material-symbols-outlined field-icon">mail</span>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : ''; ?>" required maxlength="100" placeholder="candidate@example.com" autocomplete="email" />
                        </div>
                        <div class="error" id="email_error"></div>
                    </div>

                    <!-- CNIC / B-Form -->
                    <div class="col-md-4 form-group-custom">
                        <label for="cnic" class="form-label-custom">CNIC / B-Form Number</label>
                        <div class="input-group-custom">
                            <span class="material-symbols-outlined field-icon">badge</span>
                            <input type="text" class="form-control" id="cnic" name="cnic" value="<?php echo isset($_POST['cnic']) ? htmlspecialchars($_POST['cnic'], ENT_QUOTES, 'UTF-8') : ''; ?>" required placeholder="41304-1234567-1" inputmode="numeric" />
                        </div>
                        <div class="error" id="cnic_error"></div>
                    </div>

                    <!-- Date of Birth -->
                    <div class="col-md-4 form-group-custom">
                        <label for="dob" class="form-label-custom">Date of Birth</label>
                        <div class="input-group-custom">
                            <span class="material-symbols-outlined field-icon">calendar_today</span>
                            <input type="date" class="form-control" id="dob" name="dob" value="<?php echo isset($_POST['dob']) ? htmlspecialchars($_POST['dob'], ENT_QUOTES, 'UTF-8') : ''; ?>" max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>" required />
                        </div>
                        <div class="error" id="dob_error"></div>
                    </div>

                    <!-- Mobile Number -->
                    <div class="col-md-4 form-group-custom">
                        <label for="phone" class="form-label-custom">Mobile Number</label>
                        <div class="input-group-custom">
                            <span class="material-symbols-outlined field-icon">call</span>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone'], ENT_QUOTES, 'UTF-8') : ''; ?>" required placeholder="+923001234567" inputmode="tel" />
                        </div>
                        <div class="error" id="phone_error"></div>
                    </div>

                    <!-- Password -->
                    <div class="col-md-6 form-group-custom">
                        <label for="password" class="form-label-custom">Create Password</label>
                        <div class="input-group-custom">
                            <span class="material-symbols-outlined field-icon">lock</span>
                            <input type="password" class="form-control has-right-icon" id="password" name="password" required minlength="8" maxlength="64" placeholder="••••••••" autocomplete="new-password" />
                            <div class="eye-beam-projection"></div>
                            <button type="button" class="animated-eye-toggle toggle-password" data-target="password" aria-label="Toggle password visibility">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <g class="eye-head-group">
                                        <path class="eye-lid" d="M2 12S6 5 12 5S22 12 22 12S18 19 12 19S2 12 2 12Z" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        <circle class="eye-iris" cx="12" cy="12" r="3.5" />
                                    </g>
                                </svg>
                            </button>
                        </div>
                        <div class="password-strength-wrapper" id="strengthWrapper">
                            <div class="password-strength-bar">
                                <div id="strengthBar"></div>
                            </div>
                            <div class="password-hint" id="strengthHint">Min. 8 characters with 1 uppercase, 1 lowercase & 1 number.</div>
                        </div>
                        <div class="error" id="password_error"></div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="col-md-6 form-group-custom">
                        <label for="retype_password" class="form-label-custom">Confirm Password</label>
                        <div class="input-group-custom">
                            <span class="material-symbols-outlined field-icon">lock_reset</span>
                            <input type="password" class="form-control has-right-icon" id="retype_password" name="retype_password" required minlength="8" maxlength="64" placeholder="••••••••" autocomplete="new-password" />
                            <div class="eye-beam-projection"></div>
                            <button type="button" class="animated-eye-toggle toggle-password" data-target="retype_password" aria-label="Toggle password visibility">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <g class="eye-head-group">
                                        <path class="eye-lid" d="M2 12S6 5 12 5S22 12 22 12S18 19 12 19S2 12 2 12Z" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        <circle class="eye-iris" cx="12" cy="12" r="3.5" />
                                    </g>
                                </svg>
                            </button>
                        </div>
                        <div class="error" id="retype_password_error"></div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6 mx-auto">
                        <button type="submit" id="submitBtn" class="btn btn-door-submit w-100">
                            <span class="btn-text">Complete Registration</span>
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
                                    <circle class="door-panel" cx="25" cy="16" r="1" fill="#1e40af" />
                                </svg>
                            </div>
                        </button>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 text-center login-link-container">
                        <span>Already registered? <a href="login">Sign In Here</a></span>
                    </div>
                </div>
            </form>
        </div>

        <footer class="page-copyright">
            <p>&copy; <?php echo date('Y'); ?> All Rights Reserved.</p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script nonce="<?php echo htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            const submitBtn = document.getElementById('submitBtn');

            // 1. Interactive Animated Eye & Password Visibility Toggle
            document.querySelectorAll('.toggle-password').forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const targetInput = document.getElementById(targetId);
                    const parentContainer = this.closest('.input-group-custom');
                    const isPassword = targetInput.getAttribute('type') === 'password';

                    this.classList.add('blinking');
                    setTimeout(() => this.classList.remove('blinking'), 220);

                    if (isPassword) {
                        targetInput.setAttribute('type', 'text');
                        this.classList.add('active');
                        parentContainer.classList.add('eye-active');
                    } else {
                        targetInput.setAttribute('type', 'password');
                        this.classList.remove('active');
                        parentContainer.classList.remove('eye-active');
                    }
                });
            });

            // 2. Smart Auto-Formatting for CNIC (XXXXX-XXXXXXX-X)
            const cnicInput = document.getElementById('cnic');
            cnicInput.addEventListener('input', function(e) {
                let digits = e.target.value.replace(/\D/g, '').substring(0, 13);
                let formatted = '';
                if (digits.length > 0) formatted += digits.substring(0, 5);
                if (digits.length >= 5) formatted += '-' + digits.substring(5, 12);
                if (digits.length >= 12) formatted += '-' + digits.substring(12, 13);
                e.target.value = formatted;
                validateField(cnicInput, /^\d{5}-\d{7}-\d{1}$/, 'cnic_error', 'Invalid format. Use: XXXXX-XXXXXXX-X');
            });

            // 3. Smart Auto-Formatting for Phone (+923XXXXXXXXX)
            const phoneInput = document.getElementById('phone');
            phoneInput.addEventListener('focus', function() {
                if (!this.value) this.value = '+923';
            });
            phoneInput.addEventListener('input', function(e) {
                let val = e.target.value;
                if (!val.startsWith('+92')) {
                    val = '+92' + val.replace(/^\+?92?/, '').replace(/\D/g, '');
                } else {
                    val = '+92' + val.substring(3).replace(/\D/g, '');
                }
                e.target.value = val.substring(0, 13);
                validateField(phoneInput, /^\+923\d{9}$/, 'phone_error', 'Must be in format: +923XXXXXXXXX');
            });

            // 4. Live Password Strength Indicator
            const passInput = document.getElementById('password');
            const strengthWrapper = document.getElementById('strengthWrapper');
            const strengthBar = document.getElementById('strengthBar');

            passInput.addEventListener('focus', () => strengthWrapper.style.display = 'block');
            passInput.addEventListener('input', function() {
                const val = this.value;
                let score = 0;
                if (val.length >= 8) score++;
                if (/[A-Z]/.test(val)) score++;
                if (/[a-z]/.test(val)) score++;
                if (/\d/.test(val)) score++;
                if (/[\W_]/.test(val)) score++;

                const width = (score / 5) * 100;
                strengthBar.style.width = width + '%';

                if (score <= 2) {
                    strengthBar.style.backgroundColor = '#dc2626';
                } else if (score <= 4) {
                    strengthBar.style.backgroundColor = '#f59e0b';
                } else {
                    strengthBar.style.backgroundColor = '#16a34a';
                }

                validateField(passInput, /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d\w\W]{8,64}$/, 'password_error', 'Password needs 8+ chars with uppercase, lowercase & number.');
            });

            // 5. General Real-Time Field Validator
            function validateField(inputEl, regex, errorElId, errorMsg) {
                const errContainer = document.getElementById(errorElId);
                const val = inputEl.value.trim();

                if (!val) {
                    inputEl.classList.remove('is-valid', 'is-invalid');
                    errContainer.textContent = '';
                    return false;
                }

                if (regex.test(val)) {
                    inputEl.classList.remove('is-invalid');
                    inputEl.classList.add('is-valid');
                    errContainer.textContent = '';
                    return true;
                } else {
                    inputEl.classList.remove('is-valid');
                    inputEl.classList.add('is-invalid');
                    errContainer.textContent = errorMsg;
                    return false;
                }
            }

            // Real-time validation listeners
            const nameRegex = /^[a-zA-Z\s]{3,50}$/;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            document.getElementById('name').addEventListener('input', function() {
                validateField(this, nameRegex, 'name_error', 'Letters only (3-50 characters).');
            });

            document.getElementById('fatherName').addEventListener('input', function() {
                validateField(this, nameRegex, 'fatherName_error', 'Letters only (3-50 characters).');
            });

            document.getElementById('email').addEventListener('input', function() {
                validateField(this, emailRegex, 'email_error', 'Enter a valid email address.');
            });

            document.getElementById('retype_password').addEventListener('input', function() {
                const errContainer = document.getElementById('retype_password_error');
                if (this.value !== passInput.value) {
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                    errContainer.textContent = 'Passwords do not match.';
                } else {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                    errContainer.textContent = '';
                }
            });

            // 6. Form Submission Handling & Door Animation integration
            form.addEventListener('submit', function(e) {
                let isValid = true;

                if (!validateField(document.getElementById('name'), nameRegex, 'name_error', 'Valid name required (3-50 letters).')) isValid = false;
                if (!validateField(document.getElementById('fatherName'), nameRegex, 'fatherName_error', 'Valid father name required (3-50 letters).')) isValid = false;
                if (!validateField(document.getElementById('email'), emailRegex, 'email_error', 'Valid email address required.')) isValid = false;
                if (!validateField(cnicInput, /^\d{5}-\d{7}-\d{1}$/, 'cnic_error', 'Valid CNIC format required.')) isValid = false;
                if (!validateField(phoneInput, /^\+923\d{9}$/, 'phone_error', 'Valid phone format required.')) isValid = false;
                if (!validateField(passInput, /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d\w\W]{8,64}$/, 'password_error', 'Password does not meet requirements.')) isValid = false;

                const dobVal = document.getElementById('dob').value;
                if (!dobVal) {
                    document.getElementById('dob_error').textContent = 'Please select your Date of Birth.';
                    isValid = false;
                }

                const retypeInput = document.getElementById('retype_password');
                if (retypeInput.value !== passInput.value || !retypeInput.value) {
                    document.getElementById('retype_password_error').textContent = 'Passwords do not match.';
                    retypeInput.classList.add('is-invalid');
                    isValid = false;
                }

                // If client validation fails
                if (!isValid) {
                    e.preventDefault();
                    submitBtn.classList.add('anim-active', 'anim-error');
                    const firstInvalid = form.querySelector('.is-invalid');
                    if (firstInvalid) firstInvalid.focus();

                    setTimeout(() => {
                        submitBtn.classList.remove('anim-active', 'anim-error');
                    }, 1800);
                    return;
                }

                // Async submission execution with door animation
                if (!submitBtn.classList.contains('anim-active')) {
                    e.preventDefault();
                    submitBtn.classList.remove('anim-error', 'anim-success');
                    submitBtn.classList.add('anim-active');

                    const formData = new FormData(form);

                    fetch(form.action, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        }).then(response => response.text())
                        .then(html => {
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            const errorAlert = doc.querySelector('.alert-danger');
                            const successAlert = doc.querySelector('.alert-success');

                            if (successAlert) {
                                submitBtn.classList.add('anim-success');
                                document.getElementById('alertContainer').innerHTML = successAlert.outerHTML;
                                setTimeout(() => {
                                    window.location.href = 'login';
                                }, 1200);
                            } else if (errorAlert) {
                                submitBtn.classList.add('anim-error');
                                document.getElementById('alertContainer').innerHTML = errorAlert.outerHTML;
                                setTimeout(() => {
                                    submitBtn.classList.remove('anim-active', 'anim-error');
                                }, 2000);
                            } else {
                                form.submit();
                            }
                        }).catch(() => {
                            form.submit();
                        });
                }
            });
        });
    </script>
</body>

</html>