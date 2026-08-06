<?php
session_start();

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

require_once '../backend/data.php';
require_once '../backend/security.php';

$error_message = '';
$superadmin_secret_key = ait_env('SUPERADMIN_SECRET_KEY', '');

$csp_nonce = ait_bootstrap_security();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ait_validate_csrf_post();

    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password'] ?? '');
    $secret_key = trim($_POST['secret_key'] ?? '');

    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("SELECT id, password, name, role FROM admins WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $admin = $result->fetch_assoc();

            $requires_secret_key = ($admin['role'] === 'super_admin');
            $secret_key_valid = !$requires_secret_key || ($superadmin_secret_key !== '' && hash_equals($superadmin_secret_key, $secret_key));

            if (password_verify($password, $admin['password']) && $secret_key_valid) {
                session_regenerate_id(true);

                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_email'] = $email;
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_role'] = $admin['role'];

                header("Location: dashboard.php");
                exit;
            } else {
                $error_message = "Invalid administrative credentials.";
            }
        } else {
            $error_message = "Invalid administrative credentials.";
        }
        $stmt->close();
    }
}
$conn->close();
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
            max-width: 440px;
            padding: 20px;
        }

        .login-card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 20px;
            border: 1px solid var(--card-border);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
            padding: 44px 36px;
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
        }

        .brand-logo {
            max-width: 72px;
            display: block;
            margin: 0 auto 16px;
            filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.3));
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
            margin-bottom: 28px;
            font-weight: 400;
        }

        .input-group-custom {
            position: relative;
            margin-bottom: 18px;
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
            color: #6b7280;
            font-size: 20px;
            cursor: pointer;
            transition: color 0.2s ease;
            user-select: none;
        }

        .input-wrapper .icon-right:hover {
            color: var(--text-main);
        }

        .form-control-custom {
            width: 100%;
            height: 48px;
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

        /* Loading Spinner */
        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
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

            <?php if (!empty($error_message)): ?>
                <div class="alert-custom" role="alert">
                    <span class="material-symbols-outlined" style="font-size: 18px;">error</span>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="loginForm">
                <?php echo ait_csrf_field(); ?>

                <div class="input-group-custom">
                    <label for="emailInput">Administrator Email</label>
                    <div class="input-wrapper">
                        <span class="material-symbols-outlined icon-left">person</span>
                        <input type="email" id="emailInput" class="form-control-custom" name="email" placeholder="admin@domain.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required autocomplete="username">
                    </div>
                </div>

                <div class="input-group-custom">
                    <label for="secretKeyInput">Super Admin Key</label>
                    <div class="input-wrapper">
                        <span class="material-symbols-outlined icon-left">key</span>
                        <input type="password" id="secretKeyInput" class="form-control-custom" name="secret_key" placeholder="••••••••••••" value="" required autocomplete="current-password">
                    </div>
                </div>

                <div class="input-group-custom mb-4">
                    <label for="passwordInput">Account Password</label>
                    <div class="input-wrapper">
                        <span class="material-symbols-outlined icon-left">lock</span>
                        <input type="password" id="passwordInput" class="form-control-custom has-right-icon" name="password" placeholder="••••••••••••" required autocomplete="current-password">
                        <span class="material-symbols-outlined icon-right" id="togglePassword">visibility</span>
                    </div>
                </div>

                <button type="submit" class="btn-admin" id="submitBtn">
                    <span id="btnText">Authenticate</span>
                    <span class="material-symbols-outlined" id="btnIcon" style="font-size: 18px;">arrow_forward</span>
                    <div class="spinner" id="btnSpinner"></div>
                </button>
            </form>
        </div>
    </div>

    <script nonce="<?php echo htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('passwordInput');
            const loginForm = document.getElementById('loginForm');
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnIcon = document.getElementById('btnIcon');
            const btnSpinner = document.getElementById('btnSpinner');

            // Password Toggle Visibility
            togglePassword.addEventListener('click', function() {
                const isPassword = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                this.textContent = isPassword ? 'visibility_off' : 'visibility';
            });

            // Interactive Form Loading Feedback
            loginForm.addEventListener('submit', function() {
                submitBtn.style.pointerEvents = 'none';
                submitBtn.style.opacity = '0.85';
                btnText.textContent = 'Authenticating...';
                btnIcon.style.display = 'none';
                btnSpinner.style.display = 'inline-block';
            });
        });
    </script>
</body>

</html>