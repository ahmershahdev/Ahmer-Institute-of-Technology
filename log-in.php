<?php
session_start();

// Redirect logged-in users directly to dashboard
if (isset($_SESSION['student_id'])) {
    header("Location: dashboard.php");
    exit;
}

require_once 'backend/data.php';
require_once 'backend/security.php';

$error_message = '';
$csp_nonce = ait_bootstrap_security();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ait_validate_csrf_post();

    // Use filter_input for cleaner superglobal handling
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($_POST['email']) || empty($password)) {
        $error_message = "Please fill in all fields.";
    } elseif (!$email) {
        $error_message = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("SELECT id, password, name FROM students WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $student = $result->fetch_assoc();

            if (password_verify($password, $student['password'])) {
                session_regenerate_id(true);

                $_SESSION['student_id'] = $student['id'];
                $_SESSION['student_email'] = $email;
                $_SESSION['student_name'] = $student['name'];

                if ($remember) {
                    $cookie_token = bin2hex(random_bytes(32)); // Increased token entropy to 256 bits
                    // Set secure cookie flags dynamically
                    $is_secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
                    setcookie("student_remember", $cookie_token, [
                        'expires' => time() + (86400 * 30),
                        'path' => '/',
                        'domain' => '',
                        'secure' => $is_secure,
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                }

                header("Location: dashboard.php");
                exit;
            }
        }

        // Generic error message to prevent user enumeration
        $error_message = "Incorrect email or password combination.";
        $stmt->close();
    }
}

// Safely close connection only if initialized
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
    <title>Student Login</title>
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
            background-image: url('./assets/images/background/university.png');
            background-size: cover;
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
            color: #dc3545;
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

        .form-box img {
            max-width: 120px;
            margin: 0 auto 20px;
            display: block;
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
            color: #212529;
            font-weight: bold;
        }

        .input-with-icon {
            position: relative;
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

        .input-with-icon .right-icon {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 20px;
            z-index: 10;
            cursor: pointer;
            transition: color 0.3s;
        }

        .input-with-icon .right-icon:hover {
            color: #333;
        }

        .input-with-icon input {
            padding-left: 45px !important;
            height: 48px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }

        .input-with-icon input.has-right-icon {
            padding-right: 45px !important;
        }

        .input-with-icon input:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            background-color: #ffffff;
            outline: 0;
        }

        .btn {
            padding: 10px;
            font-weight: 500;
            letter-spacing: 0.5px;
            border-radius: 4px;
        }
    </style>
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
            <img src="./assets/images/logo/ait_logo.png" alt="AIT Logo">
            <h4 class="form-header">Student Login</h4>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger py-2 text-center mb-3" style="font-size: 14px;" role="alert">
                    <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo ait_csrf_field(); ?>
                <div class="mb-3 input-with-icon">
                    <span class="material-icons left-icon">mail</span>
                    <input type="email" class="form-control" name="email" placeholder="Email Address" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : ''; ?>" required autocomplete="email">
                </div>

                <div class="mb-3 input-with-icon">
                    <span class="material-icons left-icon">lock</span>
                    <input type="password" class="form-control has-right-icon" id="passwordInput" name="password" placeholder="Password" required autocomplete="current-password">
                    <span class="material-icons right-icon" id="togglePassword">visibility</span>
                </div>

                <div class="mb-4 form-check">
                    <input type="checkbox" class="form-check-input" id="inputCheckbox" name="remember" <?php echo isset($_POST['remember']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="inputCheckbox" style="font-size:14px; font-weight:500;">Remember me</label>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Sign In</button>
                    <a href="registration.php" class="btn btn-outline-success">Register</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script nonce="<?php echo htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('passwordInput');

            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    this.textContent = type === 'password' ? 'visibility' : 'visibility_off';
                });
            }
        });
    </script>
</body>

</html>