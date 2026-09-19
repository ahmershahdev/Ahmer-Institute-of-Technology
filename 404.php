<?php
http_response_code(404);
require_once __DIR__ . '/backend/session.php';
ait_start_secure_session();

$redirect_url = isset($_SESSION['student_id']) ? 'dashboard' : 'login';
$btn_text = isset($_SESSION['student_id']) ? 'Go to Dashboard' : 'Go to Login';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>404 - Page Not Found</title>

    <link rel="icon" type="image/x-icon" href="assets/images/favicon/ait.ico" />
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body,
        html {
            margin: 0;
            padding: 0;
            width: 100vw;
            height: 100vh;
            font-family: 'Roboto', sans-serif;
            background-color: #0f111a;
            overflow: hidden;
            perspective: 1000px;
            user-select: none;
        }

        /* Particle Canvas overlay */
        #particleCanvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 10;
        }

        /* Full Page Container Layout */
        .full-page-container {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            padding: 60px 20px;
            position: relative;
            z-index: 2;
        }

        /* Ambient floating background spheres */
        .ambient-sphere {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.3;
            pointer-events: none;
            animation: floatAmbient 12s ease-in-out infinite alternate;
        }

        .sphere-1 {
            width: 500px;
            height: 500px;
            background: #007bff;
            top: -150px;
            left: -150px;
        }

        .sphere-2 {
            width: 550px;
            height: 550px;
            background: #239cb3;
            bottom: -200px;
            right: -150px;
            animation-delay: -6s;
        }

        @keyframes floatAmbient {
            0% {
                transform: translate(0, 0) scale(1);
            }

            100% {
                transform: translate(80px, 100px) scale(1.2);
            }
        }

        /* Error Content Wrapper */
        .error-wrapper {
            width: 100%;
            max-width: 900px;
            margin: auto;
            text-align: center;
            transition: transform 0.1s ease-out;
            transform-style: preserve-3d;
            z-index: 3;
        }

        /* Continuous floating motion */
        .float-container {
            animation: continuousFloat 4s ease-in-out infinite alternate;
            transform-style: preserve-3d;
        }

        .float-container.dizzy-frozen {
            animation-play-state: paused;
        }

        @keyframes continuousFloat {
            0% {
                transform: translateY(-12px);
            }

            100% {
                transform: translateY(16px);
            }
        }

        /* Interactive 3D Ball Avatar */
        .character-sphere {
            width: 160px;
            height: 160px;
            margin: 0 auto 30px auto;
            border-radius: 50%;
            background: radial-gradient(circle at 35% 35%, #00d2ff, #007bff 60%, #082449 100%);
            box-shadow: 0 20px 45px rgba(0, 198, 255, 0.45), inset -8px -8px 25px rgba(0, 0, 0, 0.5);
            position: relative;
            transform: translateZ(80px);
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            transition: transform 0.1s ease, box-shadow 0.3s ease, filter 0.3s ease;
            animation: spherePulse 3s ease-in-out infinite alternate;
        }

        .character-sphere:active {
            transform: translateZ(70px) scale(0.92);
        }

        .character-sphere.dizzy {
            filter: hue-rotate(180deg) saturate(1.5);
            animation-play-state: paused;
        }

        @keyframes spherePulse {
            0% {
                box-shadow: 0 15px 35px rgba(0, 198, 255, 0.35), inset -8px -8px 25px rgba(0, 0, 0, 0.5);
            }

            100% {
                box-shadow: 0 30px 60px rgba(0, 198, 255, 0.7), inset -8px -8px 25px rgba(0, 0, 0, 0.5);
            }
        }

        /* Eyes & Pupils */
        .eyes-container {
            display: flex;
            gap: 26px;
            position: relative;
            top: -5px;
            pointer-events: none;
        }

        .eye {
            width: 30px;
            height: 38px;
            background-color: #ffffff;
            border-radius: 50%;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            animation: blinkEye 4s infinite;
        }

        .pupil {
            width: 14px;
            height: 14px;
            background-color: #0f111a;
            border-radius: 50%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            transition: transform 0.04s linear;
        }

        .pupil::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 5px;
            height: 5px;
            background-color: #ffffff;
            border-radius: 50%;
        }

        /* Eye Rolling Dizzy Animation */
        .eye.rolling {
            animation: none !important;
        }

        .eye.rolling .pupil {
            animation: rollEyes 0.8s linear infinite !important;
        }

        @keyframes rollEyes {
            0% {
                transform: translate(-50%, -50%) rotate(0deg) translateY(-8px) rotate(0deg);
            }

            100% {
                transform: translate(-50%, -50%) rotate(360deg) translateY(-8px) rotate(-360deg);
            }
        }

        @keyframes blinkEye {

            0%,
            92%,
            98%,
            100% {
                transform: scaleY(1);
            }

            95% {
                transform: scaleY(0.05);
            }
        }

        /* Typography */
        .error-code {
            font-size: clamp(100px, 15vw, 180px);
            font-weight: 700;
            margin: 0;
            line-height: 0.9;
            background: linear-gradient(45deg, #239cb3, #00c6ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 15px 25px rgba(24, 152, 146, 0.35));
            transform: translateZ(60px);
        }

        .error-title {
            color: #ffffff;
            font-size: clamp(20px, 4vw, 32px);
            font-weight: 700;
            margin-top: 20px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 3px;
            transform: translateZ(40px);
        }

        .error-message {
            color: #8b949e;
            font-size: clamp(14px, 2vw, 18px);
            max-width: 550px;
            margin: 0 auto 35px auto;
            line-height: 1.6;
            transform: translateZ(30px);
        }

        .btn-action {
            background: linear-gradient(45deg, #007bff, #00c6ff);
            border: none;
            color: #ffffff;
            padding: 14px 40px;
            font-size: 16px;
            font-weight: 500;
            border-radius: 50px;
            text-decoration: none;
            box-shadow: 0 4px 20px rgba(0, 123, 255, 0.45);
            transition: all 0.3s ease;
            display: inline-block;
            transform: translateZ(50px);
        }

        .btn-action:hover {
            background: linear-gradient(45deg, #00c6ff, #007bff);
            box-shadow: 0 8px 28px rgba(0, 198, 255, 0.7);
            color: #ffffff;
            transform: translateZ(55px) scale(1.06);
        }
    </style>
</head>

<body>

    <canvas id="particleCanvas"></canvas>

    <div class="ambient-sphere sphere-1"></div>
    <div class="ambient-sphere sphere-2"></div>

    <div class="full-page-container">
        <div></div> <!-- Top spacer -->

        <div class="error-wrapper" id="errorContainer">
            <div class="float-container" id="floatContainer">
                <div class="character-sphere" id="interactiveBall" title="Click me!">
                    <div class="eyes-container">
                        <div class="eye">
                            <div class="pupil"></div>
                        </div>
                        <div class="eye">
                            <div class="pupil"></div>
                        </div>
                    </div>
                </div>

                <h1 class="error-code">404</h1>
                <div class="error-title" id="errorTitle">Lost in Space</div>
                <p class="error-message" id="errorMessage">The link you followed might be broken, or the page may have been removed permanently.</p>
                <a href="<?php echo htmlspecialchars($redirect_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn-action" id="actionBtn"><?php echo htmlspecialchars($btn_text, ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
        </div>

        <div class="footer-spacer"></div> <!-- Bottom spacer -->
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('errorContainer');
            const floatContainer = document.getElementById('floatContainer');
            const pupils = document.querySelectorAll('.pupil');
            const eyes = document.querySelectorAll('.eye');
            const orb = document.getElementById('interactiveBall');
            const btn = document.getElementById('actionBtn');
            const errorTitle = document.getElementById('errorTitle');
            const canvas = document.getElementById('particleCanvas');
            const ctx = canvas.getContext('2d');

            // Web Audio API Sound Generator
            let audioCtx = null;

            function getAudioContext() {
                if (!audioCtx) {
                    audioCtx = new(window.AudioContext || window.webkitAudioContext)();
                }
                if (audioCtx.state === 'suspended') {
                    audioCtx.resume();
                }
                return audioCtx;
            }

            // Sound 1: Button Hover (Soft Sci-Fi Beep)
            function playHoverSound() {
                try {
                    const ctx = getAudioContext();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();

                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(440, ctx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.1);

                    gain.gain.setValueAtTime(0.05, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.1);

                    osc.connect(gain);
                    gain.connect(ctx.destination);

                    osc.start();
                    osc.stop(ctx.currentTime + 0.1);
                } catch (e) {}
            }

            // Sound 2: Orb Click (Energetic Bubble Burst)
            function playClickSound() {
                try {
                    const ctx = getAudioContext();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();

                    osc.type = 'triangle';
                    osc.frequency.setValueAtTime(220, ctx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(660, ctx.currentTime + 0.15);

                    gain.gain.setValueAtTime(0.2, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.15);

                    osc.connect(gain);
                    gain.connect(ctx.destination);

                    osc.start();
                    osc.stop(ctx.currentTime + 0.15);
                } catch (e) {}
            }

            // Sound 3: Dizzy Easter Egg Sound (Descending Wobble)
            function playDizzySound() {
                try {
                    const ctx = getAudioContext();
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();

                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(600, ctx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(120, ctx.currentTime + 0.8);

                    gain.gain.setValueAtTime(0.2, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.8);

                    osc.connect(gain);
                    gain.connect(ctx.destination);

                    osc.start();
                    osc.stop(ctx.currentTime + 0.8);
                } catch (e) {}
            }

            btn.addEventListener('mouseenter', playHoverSound);

            // Resize canvas to full screen
            function resizeCanvas() {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
            }
            resizeCanvas();
            window.addEventListener('resize', resizeCanvas);

            // Particle System
            let particles = [];
            const colors = ['#00d2ff', '#007bff', '#239cb3', '#ffffff', '#00c6ff'];

            class Particle {
                constructor(x, y) {
                    this.x = x;
                    this.y = y;
                    this.radius = Math.random() * 5 + 2;
                    this.color = colors[Math.floor(Math.random() * colors.length)];
                    const angle = Math.random() * Math.PI * 2;
                    const speed = Math.random() * 8 + 3;
                    this.vx = Math.cos(angle) * speed;
                    this.vy = Math.sin(angle) * speed;
                    this.alpha = 1;
                    this.decay = Math.random() * 0.02 + 0.015;
                    this.gravity = 0.1;
                }

                update() {
                    this.vx *= 0.98;
                    this.vy *= 0.98;
                    this.vy += this.gravity;
                    this.x += this.vx;
                    this.y += this.vy;
                    this.alpha -= this.decay;
                }

                draw() {
                    ctx.save();
                    ctx.globalAlpha = Math.max(0, this.alpha);
                    ctx.beginPath();
                    ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
                    ctx.fillStyle = this.color;
                    ctx.shadowColor = this.color;
                    ctx.shadowBlur = 12;
                    ctx.fill();
                    ctx.restore();
                }
            }

            function animateParticles() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                for (let i = particles.length - 1; i >= 0; i--) {
                    particles[i].update();
                    particles[i].draw();
                    if (particles[i].alpha <= 0) {
                        particles.splice(i, 1);
                    }
                }
                requestAnimationFrame(animateParticles);
            }
            animateParticles();

            // Click Counter & 2-Minute Time Window Logic
            let clickTimestamps = [];
            let isFrozen = false;

            orb.addEventListener('click', function(e) {
                if (isFrozen) return;

                playClickSound();

                const now = Date.now();
                clickTimestamps.push(now);

                // Filter out clicks older than 2 minutes (120,000ms)
                clickTimestamps = clickTimestamps.filter(timestamp => now - timestamp <= 120000);

                // Spawn 60 explosion particles
                const rect = orb.getBoundingClientRect();
                const centerX = rect.left + rect.width / 2;
                const centerY = rect.top + rect.height / 2;

                for (let i = 0; i < 60; i++) {
                    particles.push(new Particle(centerX, centerY));
                }

                // Temporary click squeeze animation
                container.style.transform = `scale(0.96)`;
                setTimeout(() => {
                    if (!isFrozen) container.style.transform = `scale(1)`;
                }, 100);

                // Trigger Dizzy State on 5 Clicks within 2 minutes
                if (clickTimestamps.length >= 5) {
                    triggerDizzyState();
                }
            });

            function triggerDizzyState() {
                isFrozen = true;
                clickTimestamps = [];

                playDizzySound();

                // Apply dizzy classes
                orb.classList.add('dizzy');
                floatContainer.classList.add('dizzy-frozen');
                eyes.forEach(eye => eye.classList.add('rolling'));

                const originalTitle = errorTitle.innerText;
                errorTitle.innerText = "System Overload!";

                // 60 Second Freeze Timer
                setTimeout(() => {
                    isFrozen = false;
                    orb.classList.remove('dizzy');
                    floatContainer.classList.remove('dizzy-frozen');
                    eyes.forEach(eye => eye.classList.remove('rolling'));
                    errorTitle.innerText = originalTitle;
                }, 60000); // 1 minute (60,000 ms)
            }

            // 3D Parallax Tilt & Pupil Tracking
            document.addEventListener('mousemove', function(e) {
                if (isFrozen) return;

                const xAxis = (window.innerWidth / 2 - e.clientX) / 30;
                const yAxis = (window.innerHeight / 2 - e.clientY) / 30;
                container.style.transform = `rotateY(${xAxis}deg) rotateX(${yAxis}deg)`;

                pupils.forEach(pupil => {
                    const eye = pupil.parentElement;
                    const rect = eye.getBoundingClientRect();
                    const eyeCenterX = rect.left + rect.width / 2;
                    const eyeCenterY = rect.top + rect.height / 2;

                    const deltaX = e.clientX - eyeCenterX;
                    const deltaY = e.clientY - eyeCenterY;
                    const angle = Math.atan2(deltaY, deltaX);
                    const distance = Math.min(Math.hypot(deltaX, deltaY) / 15, 7);

                    const pupilX = Math.cos(angle) * distance;
                    const pupilY = Math.sin(angle) * distance;

                    pupil.style.transform = `translate(calc(-50% + ${pupilX}px), calc(-50% + ${pupilY}px))`;
                });
            });

            // Reset positioning on mouse leave
            document.addEventListener('mouseleave', function() {
                if (isFrozen) return;
                container.style.transform = 'rotateY(0deg) rotateX(0deg)';
                pupils.forEach(pupil => {
                    pupil.style.transform = 'translate(-50%, -50%)';
                });
            });
        });
    </script>
</body>

</html>