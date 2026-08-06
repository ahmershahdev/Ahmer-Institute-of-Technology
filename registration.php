<?php
session_start();

if (isset($_SESSION['student_id'])) {
    header("Location: dashboard.php");
    exit;
}

require_once 'backend/data.php';
require_once 'backend/security.php';

$error_message = '';
$success_message = '';

$csp_nonce = ait_bootstrap_security();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ait_validate_csrf_post();
    $name = trim($_POST['name']);
    $fatherName = trim($_POST['fatherName']);
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $cnic = trim($_POST['cnic']);
    $dob = trim($_POST['dob']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $retype_password = $_POST['retype_password'];

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
                $success_message = "Registration successful! You can now log in.";
                unset($_POST);
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

    <link rel="icon" type="image/png" href="assets/images/muet-logo.webp" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            --btn-gradient: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            --btn-hover: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            --glass-bg: rgba(255, 255, 255, 0.88);
            --glass-border: rgba(255, 255, 255, 0.5);
            --text-main: #0f172a;
            --text-muted: #64748b;
        }

        body,
        html {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(rgba(15, 23, 42, 0.65), rgba(15, 23, 42, 0.65)),
                url('./assets/images/background/university.png') no-repeat center fixed;
            background-size: cover;
            color: var(--text-main);
        }

        .page-wrapper {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 40px 15px;
        }

        .header-card {
            text-align: center;
            margin-bottom: 24px;
            color: #ffffff;
            max-width: 650px;
        }

        .header-card h2 {
            font-weight: 700;
            font-size: 1.75rem;
            letter-spacing: -0.02em;
            margin-bottom: 8px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .header-card p {
            font-size: 0.9rem;
            color: #cbd5e1;
            margin-bottom: 0;
            line-height: 1.5;
        }

        .form-container {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 36px 32px;
            width: 100%;
            max-width: 900px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
        }

        .brand-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .brand-header img {
            max-width: 90px;
            height: auto;
            margin-bottom: 12px;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
        }

        .brand-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-main);
            margin: 0;
        }

        .form-group-custom {
            margin-bottom: 18px;
        }

        .form-label-custom {
            color: var(--text-main);
            font-size: 0.825rem;
            font-weight: 600;
            margin-bottom: 6px;
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

        .input-group-custom .field-icon.right-icon {
            left: auto;
            right: 12px;
            pointer-events: auto;
            cursor: pointer;
        }

        .input-group-custom .field-icon.right-icon:hover {
            color: var(--text-main);
        }

        .input-group-custom .form-control {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #1e293b;
            padding-left: 42px;
            padding-right: 14px;
            height: 44px;
            font-size: 0.875rem;
            border-radius: 8px;
            transition: all 0.2s ease-in-out;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .input-group-custom .form-control.has-right-icon {
            padding-right: 42px;
        }

        .input-group-custom .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
            background: #ffffff;
        }

        .input-group-custom .form-control:focus+.field-icon {
            color: #2563eb;
        }

        .btn-submit {
            background: var(--btn-gradient);
            border: none;
            color: #ffffff;
            font-weight: 600;
            font-size: 0.95rem;
            border-radius: 8px;
            padding: 12px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-submit:hover:not(:disabled) {
            background: var(--btn-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
        }

        .btn-submit:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            box-shadow: none;
        }

        .error {
            color: #dc2626;
            font-size: 0.775rem;
            font-weight: 500;
            margin-top: 4px;
            min-height: 18px;
        }

        .login-link-container {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-top: 16px;
        }

        .login-link-container a {
            color: #2563eb;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .login-link-container a:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        footer.page-copyright {
            margin-top: 24px;
            color: #cbd5e1;
            font-size: 0.775rem;
            text-align: center;
            letter-spacing: 0.05em;
        }

        .alert {
            border-radius: 8px;
            border: none;
            font-size: 0.875rem;
            font-weight: 500;
        }
    </style>
</head>

<body>
    <div class="page-wrapper">
        <div class="header-card">
            <h2>Undergraduate Admissions</h2>
            <p>Session 2026-2027 &bull; Please provide a valid email and CNIC/B-Form to register successfully.</p>
        </div>

        <div class="form-container">
            <div class="brand-header">
                <img src="./assets/images/logo/ait_logo.png" alt="AIT Logo" />
                <h3>Register New Candidate</h3>
            </div>

            <!-- Server-Side Alerts Dynamic Blocks -->
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger py-2 text-center mb-3" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success py-2 text-center mb-3" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="registerForm" autocomplete="off" novalidate>
                <?php echo ait_csrf_field(); ?>
                <div class="row">
                    <div class="col-md-4 form-group-custom">
                        <label for="name" class="form-label-custom">Candidate's Full Name</label>
                        <div class="input-group-custom">
                            <span class="material-symbols-outlined field-icon">person</span>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required minlength="3" maxlength="50" pattern="^[a-zA-Z\s]+$" placeholder="Full Name" autocomplete="one-time-code" />
                        </div>
                        <div class="error" id="name_error"></div>
                    </div>

                    <div class="col-md-4 form-group-custom">
                        <label for="fatherName" class="form-label-custom">Father's Name</label>
                        <div class="input-group-custom">
                            <span class="material-symbols-outlined field-icon">family_restroom</span>
                            <input type="text" class="form-control" id="fatherName" name="fatherName" value="<?php echo isset($_POST['fatherName']) ? htmlspecialchars($_POST['fatherName']) : ''; ?>" required minlength="3" maxlength="50" pattern="^[a-zA-Z\s]+$" placeholder="Father's Name" autocomplete="one-time-code" />
                        </div>
                        <div class="error" id="fatherName_error"></div>
                    </div>

                    <div class="col-md-4 form-group-custom">
                        <label for="email" class="form-label-custom">Email Address</label>
                        <div class="input-group-custom">
                            <span class="material-symbols-outlined field-icon">mail</span>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required maxlength="100" placeholder="name@example.com" />
                        </div>
                        <div class="error" id="email_error"></div>
                    </div>

                    <div class="col-md-4 form-group-custom">
                        <label for="cnic" class="form-label-custom">CNIC / B-Form</label>
                        <div class="input-group-custom">
                            <span class="material-symbols-outlined field-icon">badge</span>
                            <input type="text" class="form-control cnic" id="cnic" name="cnic" value="<?php echo isset($_POST['cnic']) ? htmlspecialchars($_POST['cnic']) : ''; ?>" minlength="15" maxlength="15" pattern="^\d{5}-\d{7}-\d{1}$" required placeholder="XXXXX-XXXXXXX-X" />
                        </div>
                        <div class="error" id="cnic_error"></div>
                    </div>

                    <div class="col-md-4 form-group-custom">
                        <label for="dob" class="form-label-custom">Date of Birth</label>
                        <div class="input-group-custom">
                            <span class="material-symbols-outlined field-icon">calendar_today</span>
                            <input type="date" class="form-control" id="dob" name="dob" value="<?php echo isset($_POST['dob']) ? htmlspecialchars($_POST['dob']) : ''; ?>" max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>" required />
                        </div>
                        <div class="error" id="dob_error"></div>
                    </div>

                    <div class="col-md-4 form-group-custom">
                        <label for="phone" class="form-label-custom">Mobile Number</label>
                        <div class="input-group-custom">
                            <span class="material-symbols-outlined field-icon">call</span>
                            <input type="text" class="form-control phone" id="phone" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required minlength="13" maxlength="13" pattern="^\+923\d{9}$" placeholder="+923000000000" />
                        </div>
                        <div class="error" id="phone_error"></div>
                    </div>

                    <div class="col-md-6 form-group-custom">
                        <label for="password" class="form-label-custom">Password</label>
                        <div class="input-group-custom">
                            <span class="material-symbols-outlined field-icon">lock</span>
                            <input type="password" class="form-control has-right-icon" id="password" name="password" required minlength="8" maxlength="64" placeholder="••••••••" />
                            <span class="material-symbols-outlined field-icon right-icon toggle-password" data-target="password">visibility</span>
                        </div>
                        <div class="error" id="password_error"></div>
                    </div>

                    <div class="col-md-6 form-group-custom">
                        <label for="retype_password" class="form-label-custom">Confirm Password</label>
                        <div class="input-group-custom">
                            <span class="material-symbols-outlined field-icon">lock_reset</span>
                            <input type="password" class="form-control has-right-icon" id="retype_password" name="retype_password" required minlength="8" maxlength="64" placeholder="••••••••" />
                            <span class="material-symbols-outlined field-icon right-icon toggle-password" data-target="retype_password">visibility</span>
                        </div>
                        <div class="error" id="retype_password_error"></div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6 mx-auto">
                        <button type="submit" id="submitBtn" class="btn btn-submit w-100">Complete Registration</button>
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
            <p>&copy; 2026 ALL RIGHTS RESERVED.</p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script nonce="<?php echo htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        document.addEventListener('DOMContentLoaded', function() {
            const togglePasswords = document.querySelectorAll('.toggle-password');
            togglePasswords.forEach(function(toggle) {
                toggle.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const passwordInput = document.getElementById(targetId);
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    this.textContent = type === 'password' ? 'visibility' : 'visibility_off';
                });
            });

            const cnicInput = document.getElementById('cnic');
            cnicInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 13) {
                    value = value.substring(0, 13);
                }
                let formattedValue = '';
                if (value.length > 0) {
                    formattedValue += value.substring(0, 5);
                }
                if (value.length >= 5) {
                    formattedValue += '-' + value.substring(5, 12);
                }
                if (value.length >= 12) {
                    formattedValue += '-' + value.substring(12, 13);
                }
                e.target.value = formattedValue;
            });

            const phoneInput = document.getElementById('phone');
            phoneInput.addEventListener('input', function(e) {
                let val = e.target.value;
                if (!val.startsWith('+92')) {
                    e.target.value = '+92' + val.replace(/^\+?92?/, '').replace(/\D/g, '');
                } else {
                    e.target.value = '+92' + val.substring(3).replace(/\D/g, '');
                }
            });

            const form = document.getElementById('registerForm');
            const submitBtn = document.getElementById('submitBtn');

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                document.querySelectorAll('.error').forEach(el => el.textContent = '');

                let isValid = true;

                const name = document.getElementById('name').value.trim();
                const nameRegex = /^[a-zA-Z\s]{3,50}$/;
                if (!nameRegex.test(name)) {
                    document.getElementById('name_error').textContent = 'Enter a valid name (letters only, 3-50 characters).';
                    isValid = false;
                }

                const fatherName = document.getElementById('fatherName').value.trim();
                if (!nameRegex.test(fatherName)) {
                    document.getElementById('fatherName_error').textContent = 'Enter a valid father name (letters only, 3-50 characters).';
                    isValid = false;
                }

                const email = document.getElementById('email').value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    document.getElementById('email_error').textContent = 'Enter a valid email address.';
                    isValid = false;
                }

                const cnic = document.getElementById('cnic').value.trim();
                const cnicRegex = /^\d{5}-\d{7}-\d{1}$/;
                if (!cnicRegex.test(cnic)) {
                    document.getElementById('cnic_error').textContent = 'The CNIC field is required.';
                    isValid = false;
                }

                const dob = document.getElementById('dob').value;
                if (!dob) {
                    document.getElementById('dob_error').textContent = 'Please select a date of birth.';
                    isValid = false;
                }

                const phone = document.getElementById('phone').value.trim();
                const phoneRegex = /^\+923\d{9}$/;
                if (!phoneRegex.test(phone)) {
                    document.getElementById('phone_error').textContent = 'Phone must be in format: +923XXXXXXXXX';
                    isValid = false;
                }

                const password = document.getElementById('password').value;
                const passRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d\w\W]{8,64}$/;
                if (!passRegex.test(password)) {
                    document.getElementById('password_error').textContent = 'Min 8 chars, at least 1 uppercase, 1 lowercase, and 1 number.';
                    isValid = false;
                }

                const retypePassword = document.getElementById('retype_password').value;
                if (password !== retypePassword) {
                    document.getElementById('retype_password_error').textContent = 'Passwords do not match.';
                    isValid = false;
                }

                if (isValid) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Processing...';
                    form.submit();
                }
            });
        });
    </script>
</body>

</html>