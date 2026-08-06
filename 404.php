<?php
session_start();

$redirect_url = isset($_SESSION['student_id']) ? 'dashboard.php' : 'login';
$btn_text = isset($_SESSION['student_id']) ? 'Go to Dashboard' : 'Go to Login';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>404 - Page Not Found</title>

    <link rel="icon" type="image/png" href="assets/images/muet-logo.webp" />
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body,
        html {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: 'Roboto', sans-serif;
            background-color: #0f111a;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            perspective: 1000px;
            user-select:none;
        }

        .error-wrapper {
            text-align: center;
            transition: transform 0.1s ease-out;
            transform-style: preserve-3d;
            padding: 40px;
        }

        .error-code {
            font-size: 150px;
            font-weight: 700;
            margin: 0;
            line-height: 1;
            background: linear-gradient(45deg, #239cb3, #197f9384);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 10px 20px rgba(24, 152, 146, 0.3));
            transform: translateZ(60px);
        }

        .error-title {
            color: #ffffff;
            font-size: 24px;
            font-weight: 700;
            margin-top: 20px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
            transform: translateZ(40px);
        }

        .error-message {
            color: #8b949e;
            font-size: 16px;
            max-width: 400px;
            margin: 0 auto 30px auto;
            transform: translateZ(30px);
        }

        .btn-action {
            background: linear-gradient(45deg, #007bff, #00c6ff);
            border: none;
            color: #ffffff;
            padding: 12px 30px;
            font-size: 15px;
            font-weight: 500;
            border-radius: 50px;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.4);
            transition: all 0.3s ease;
            display: inline-block;
            transform: translateZ(50px);
        }

        .btn-action:hover {
            background: linear-gradient(45deg, #00c6ff, #007bff);
            box-shadow: 0 6px 20px rgba(0, 198, 255, 0.6);
            color: #ffffff;
            transform: translateZ(55px) scale(1.05);
        }
    </style>
</head>

<body>

    <div class="error-wrapper" id="errorContainer">
        <h1 class="error-code">404</h1>
        <div class="error-title">Lost in Space</div>
        <p class="error-message">The link you followed might be broken, or the page may have been removed permanently.</p>
        <a href="<?php echo $redirect_url; ?>" class="btn-action"><?php echo $btn_text; ?></a>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('errorContainer');

            document.addEventListener('mousemove', function(e) {
                const xAxis = (window.innerWidth / 2 - e.clientX) / 5;
                const yAxis = (window.innerHeight / 2 - e.clientY) / 50;

                container.style.transform = `rotateY(${xAxis}deg) rotateX(${yAxis}deg)`;
            });

            document.addEventListener('mouseleave', function() {
                container.style.transform = 'rotateY(0deg) rotateX(0deg)';
            });
        });
    </script>
</body>

</html>