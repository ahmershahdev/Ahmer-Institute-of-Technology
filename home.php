<?php
require_once __DIR__ . '/backend/security.php';

$csp_nonce = ait_bootstrap_security();
$student_logged_in = isset($_SESSION['student_id']);

$feature_cards = [
    ['icon' => 'bi-mortarboard-fill', 'title' => 'Academic Excellence', 'text' => 'Modern degree pathways, merit-based admissions, and a student-first academic structure designed for long-term success.'],
    ['icon' => 'bi-building-fill', 'title' => 'Campus Experience', 'text' => 'A polished university presence with a clear admissions flow, public information areas, and a focused student workspace.'],
    ['icon' => 'bi-people-fill', 'title' => 'Expert Faculty', 'text' => 'Teach, mentor, and guide students through a structured environment that supports classroom learning and career readiness.'],
    ['icon' => 'bi-graph-up-arrow', 'title' => 'Career Progress', 'text' => 'Admission and review systems are designed to support progression, verification, and a smooth path to the final slip.'],
    ['icon' => 'bi-pc-display-horizontal', 'title' => 'Digital Admissions', 'text' => 'Apply online, upload documents, track your application, and let the admin team review everything through one workflow.'],
    ['icon' => 'bi-shield-check', 'title' => 'Secure Access', 'text' => 'CSRF protection, secure sessions, and admin secret-key access keep the critical workflow protected.'],
    ['icon' => 'bi-file-earmark-text', 'title' => 'Document Control', 'text' => 'Guided document upload rules, image compression, and readable sample references help students submit better files.'],
    ['icon' => 'bi-lightning-charge-fill', 'title' => 'Fast Workflow', 'text' => 'The dashboard is structured for quick status checks, challan uploads, review notes, and approved-slip generation.'],
    ['icon' => 'bi-geo-alt-fill', 'title' => 'Student Services', 'text' => 'Admissions support, help desk access, contact details, and policy pages make the public site feel complete.'],
    ['icon' => 'bi-star-fill', 'title' => 'AIT Identity', 'text' => 'A modern, branded university experience with a clear visual system, responsive layout, and polished presentation.'],
];

$home_stats = [
    ['value' => '12+', 'label' => 'Programs & tracks'],
    ['value' => '4K+', 'label' => 'Expected applicants'],
    ['value' => '24/7', 'label' => 'Portal access'],
    ['value' => '100%', 'label' => 'Responsive layout'],
];

$admission_steps = [
    ['step' => '01', 'title' => 'Register', 'text' => 'Create your profile and start the application journey from a clean student onboarding flow.'],
    ['step' => '02', 'title' => 'Apply Online', 'text' => 'Enter personal, academic, and document information inside the structured dashboard panes.'],
    ['step' => '03', 'title' => 'Upload Docs', 'text' => 'Submit required files with size control, image compression, and visual examples for guidance.'],
    ['step' => '04', 'title' => 'Get Reviewed', 'text' => 'The admin team verifies the submission, leaves notes if needed, and approves qualified applications.'],
];

$policy_cards = [
    ['id' => 'terms', 'title' => 'Terms of Service', 'text' => 'Rules for using the portal, student conduct, and the overall admission process.'],
    ['id' => 'privacy', 'title' => 'Privacy Policy', 'text' => 'How student information, uploads, and review data should be handled responsibly.'],
    ['id' => 'support', 'title' => 'Help Desk', 'text' => 'Support contacts, document help, account assistance, and admissions guidance.'],
];

$footer_links = [
    ['label' => 'Home', 'href' => '#top'],
    ['label' => 'About AIT', 'href' => '#about'],
    ['label' => 'Programs', 'href' => '#programs'],
    ['label' => 'Admissions', 'href' => '#admissions'],
    ['label' => 'Apply Online', 'href' => 'registration.php'],
    ['label' => 'Student Login', 'href' => 'log-in.php'],
    ['label' => 'Student Dashboard', 'href' => 'dashboard.php'],
    ['label' => 'FAQ', 'href' => '#faq'],
    ['label' => 'Contact', 'href' => '#contact'],
    ['label' => 'Scholarships', 'href' => '#scholarships'],
    ['label' => 'Campus Life', 'href' => '#life'],
    ['label' => 'Terms of Service', 'href' => '#terms'],
    ['label' => 'Privacy Policy', 'href' => '#privacy'],
    ['label' => 'Help Desk', 'href' => '#support'],
];

$carousel_slides = [
    [
        'image' => './assets/images/home/home-slide-1.svg',
        'eyebrow' => 'Admissions 2026',
        'title' => 'A polished admissions portal for Ahmer Institute of Technology.',
        'text' => 'From registration to document review, AIT guides students through a clean, secure, and responsive online admission experience.',
    ],
    [
        'image' => './assets/images/home/home-slide-2.svg',
        'eyebrow' => 'Academic Planning',
        'title' => 'Structured programs, visible guidance, and a clear path from application to approval.',
        'text' => 'Use the home page to discover programs, support pages, and the student workflow before moving into the dashboard.',
    ],
    [
        'image' => './assets/images/home/home-slide-3.svg',
        'eyebrow' => 'Student Life',
        'title' => 'Beautiful UI, useful sections, and a presentation that feels like a real university website.',
        'text' => 'Replace the carousel visuals with your own campus photos whenever you are ready to showcase the university identity.',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ahmer Institute of Technology (AIT) | Home</title>
    <meta name="description" content="Official home page for Ahmer Institute of Technology (AIT) with admissions, programs, support, and student portal access.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style nonce="<?php echo htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        :root {
            --bg: #eef4f7;
            --surface: rgba(255, 255, 255, 0.88);
            --surface-strong: #ffffff;
            --ink: #0f172a;
            --muted: #64748b;
            --brand: #0f766e;
            --brand-2: #115e59;
            --accent: #f59e0b;
            --shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
            --radius: 28px;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(15, 118, 110, 0.12), transparent 28%),
                radial-gradient(circle at top right, rgba(245, 158, 11, 0.14), transparent 24%),
                linear-gradient(180deg, #f8fbfc 0%, var(--bg) 36%, #e8f0f5 100%);
            overflow-x: hidden;
        }

        .home-navbar {
            background: rgba(15, 23, 42, 0.78);
            backdrop-filter: blur(18px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .brand-badge {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f766e 0%, #0f172a 100%);
            color: #fff;
            font-weight: 900;
            letter-spacing: 0.08em;
            box-shadow: 0 12px 24px rgba(15, 118, 110, 0.28);
        }

        .brand-copy {
            line-height: 1.1;
        }

        .brand-copy span {
            display: block;
            font-size: 0.78rem;
            color: rgba(255, 255, 255, 0.7);
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .brand-copy strong {
            color: #fff;
            font-size: 1rem;
        }

        .hero-shell {
            padding: 8.25rem 0 2rem;
        }

        .hero-panel {
            border-radius: 34px;
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.96) 0%, rgba(15, 118, 110, 0.96) 52%, rgba(17, 94, 89, 0.92) 100%);
            color: #fff;
            padding: 2rem;
            box-shadow: var(--shadow);
            overflow: hidden;
            position: relative;
        }

        .hero-panel::before,
        .hero-panel::after {
            content: '';
            position: absolute;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.09);
            pointer-events: none;
        }

        .hero-panel::before {
            width: 220px;
            height: 220px;
            top: -80px;
            right: -60px;
        }

        .hero-panel::after {
            width: 140px;
            height: 140px;
            bottom: -50px;
            left: -30px;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 0.9rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.11);
            border: 1px solid rgba(255, 255, 255, 0.16);
            text-transform: uppercase;
            letter-spacing: 0.18em;
            font-size: 0.72rem;
            font-weight: 800;
        }

        .hero-title {
            font-size: clamp(2.3rem, 4.4vw, 4.55rem);
            font-weight: 900;
            line-height: 0.98;
            margin: 1rem 0 1rem;
            max-width: 11ch;
        }

        .hero-copy {
            max-width: 58ch;
            color: rgba(255, 255, 255, 0.88);
            font-size: 1.03rem;
            line-height: 1.8;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            margin-top: 1.6rem;
        }

        .btn-hero {
            border: 0;
            border-radius: 999px;
            padding: 0.95rem 1.2rem;
            font-weight: 800;
            letter-spacing: 0.02em;
            box-shadow: 0 16px 34px rgba(0, 0, 0, 0.16);
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
        }

        .btn-hero:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .btn-brand {
            background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
            color: #111827;
        }

        .btn-ghost {
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .hero-stats {
            margin-top: 1.7rem;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.9rem;
        }

        .stat-pill {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 20px;
            padding: 0.95rem 1rem;
            backdrop-filter: blur(8px);
        }

        .stat-pill strong {
            display: block;
            font-size: 1.45rem;
            font-weight: 900;
            line-height: 1;
        }

        .stat-pill span {
            color: rgba(255, 255, 255, 0.76);
            font-size: 0.82rem;
        }

        .hero-carousel-shell {
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 30px;
            padding: 0.9rem;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(12px);
            perspective: 1600px;
            transform-style: preserve-3d;
        }

        .hero-slide-card {
            background: rgba(255, 255, 255, 0.92);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 18px 44px rgba(15, 23, 42, 0.18);
            transform: perspective(1400px) rotateY(-10deg) rotateX(2deg) scale(0.98);
            transition: transform 0.7s ease, opacity 0.7s ease;
        }

        .carousel-item.active .hero-slide-card {
            transform: perspective(1400px) rotateY(0deg) rotateX(0deg) scale(1);
        }

        .hero-slide-image {
            min-height: 360px;
            position: relative;
            overflow: hidden;
            background: #0f172a;
        }

        .hero-slide-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transform: scale(1.02);
        }

        .hero-slide-image::after {
            content: '';
            position: absolute;
            inset: auto 0 0 0;
            height: 44%;
            background: linear-gradient(180deg, transparent 0%, rgba(15, 23, 42, 0.22) 45%, rgba(15, 23, 42, 0.86) 100%);
        }

        .hero-slide-copy {
            position: absolute;
            left: 1.2rem;
            right: 1.2rem;
            bottom: 1.2rem;
            z-index: 1;
            color: #fff;
        }

        .hero-slide-copy .eyebrow {
            display: inline-flex;
            align-items: center;
            padding: 0.4rem 0.7rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.18);
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .hero-slide-copy h3 {
            margin: 0.75rem 0 0.45rem;
            font-size: clamp(1.4rem, 2.2vw, 2rem);
            font-weight: 900;
            max-width: 15ch;
        }

        .hero-slide-copy p {
            margin-bottom: 0;
            max-width: 34ch;
            color: rgba(255, 255, 255, 0.84);
            line-height: 1.7;
        }

        .section-wrap {
            padding: 2.4rem 0;
        }

        .section-head {
            margin-bottom: 1.4rem;
        }

        .section-head .eyebrow {
            color: var(--brand);
            text-transform: uppercase;
            letter-spacing: 0.18em;
            font-size: 0.74rem;
            font-weight: 900;
        }

        .section-head h2 {
            font-size: clamp(1.8rem, 3vw, 2.8rem);
            font-weight: 900;
            margin-bottom: 0.55rem;
        }

        .section-head p {
            color: var(--muted);
            max-width: 70ch;
            margin-bottom: 0;
        }

        .feature-card,
        .policy-card,
        .admission-step,
        .support-card {
            background: var(--surface);
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 24px;
            padding: 1.2rem;
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.06);
            height: 100%;
            backdrop-filter: blur(10px);
        }

        .feature-card .icon,
        .policy-card .icon,
        .support-card .icon {
            width: 54px;
            height: 54px;
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.45rem;
            color: #fff;
            background: linear-gradient(135deg, var(--brand) 0%, var(--brand-2) 100%);
            margin-bottom: 0.9rem;
        }

        .feature-card h3,
        .policy-card h3,
        .admission-step h3,
        .support-card h3 {
            font-size: 1.05rem;
            font-weight: 800;
            margin-bottom: 0.55rem;
        }

        .feature-card p,
        .policy-card p,
        .admission-step p,
        .support-card p {
            color: var(--muted);
            margin-bottom: 0;
            line-height: 1.7;
        }

        .feature-card .meta,
        .policy-card .meta {
            margin-top: 0.95rem;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--brand);
        }

        .admission-step {
            position: relative;
            overflow: hidden;
        }

        .admission-step .step-no {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 2.2rem;
            font-weight: 900;
            color: rgba(15, 118, 110, 0.12);
        }

        .admission-step .badge-step {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: rgba(15, 118, 110, 0.1);
            color: var(--brand);
            border-radius: 999px;
            padding: 0.35rem 0.7rem;
            font-size: 0.74rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 0.9rem;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 1rem;
        }

        .admission-grid,
        .policy-grid,
        .support-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 1rem;
        }

        .split-panel {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(233, 245, 244, 0.98) 100%);
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 30px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .split-media {
            min-height: 100%;
            background: linear-gradient(180deg, rgba(15, 118, 110, 0.1) 0%, rgba(15, 23, 42, 0.04) 100%);
            padding: 1.25rem;
        }

        .split-media img {
            width: 100%;
            border-radius: 22px;
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.12);
        }

        .check-list {
            list-style: none;
            padding-left: 0;
            margin: 1rem 0 0;
        }

        .check-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            margin-bottom: 0.8rem;
            color: var(--muted);
            line-height: 1.7;
        }

        .check-list i {
            color: var(--brand);
            font-size: 1.1rem;
            margin-top: 0.15rem;
        }

        .mini-pill-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            margin-top: 1rem;
        }

        .mini-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.55rem 0.8rem;
            border-radius: 999px;
            background: rgba(15, 118, 110, 0.08);
            color: var(--brand-2);
            font-weight: 800;
            font-size: 0.82rem;
        }

        .faq-item {
            background: var(--surface);
            border: 1px solid rgba(15, 23, 42, 0.08);
            border-radius: 20px;
            overflow: hidden;
        }

        .faq-item .accordion-button {
            background: transparent;
            font-weight: 800;
            box-shadow: none;
        }

        .faq-item .accordion-body {
            color: var(--muted);
            line-height: 1.8;
        }

        .home-footer {
            margin-top: 2.6rem;
            background: linear-gradient(180deg, #0f172a 0%, #0f111f 100%);
            color: #cbd5e1;
            border-top-left-radius: 32px;
            border-top-right-radius: 32px;
            overflow: hidden;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.3fr 1fr 1fr 1fr;
            gap: 1.5rem;
        }

        .footer-links {
            list-style: none;
            padding-left: 0;
            margin-bottom: 0;
        }

        .footer-links li+li {
            margin-top: 0.65rem;
        }

        .footer-links a {
            color: #cbd5e1;
            text-decoration: none;
            transition: color 0.2s ease, padding-left 0.2s ease;
        }

        .footer-links a:hover {
            color: #fff;
            padding-left: 0.25rem;
        }

        .footer-note {
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            margin-top: 1.5rem;
            padding-top: 1rem;
            color: rgba(203, 213, 225, 0.8);
            font-size: 0.93rem;
        }

        .scroll-top {
            position: fixed;
            right: 1.1rem;
            bottom: 1.1rem;
            width: 52px;
            height: 52px;
            border: 0;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f766e 0%, #f59e0b 100%);
            color: #fff;
            box-shadow: 0 16px 32px rgba(15, 23, 42, 0.24);
            opacity: 0;
            transform: translateY(14px) scale(0.9);
            pointer-events: none;
            transition: opacity 0.25s ease, transform 0.25s ease;
            z-index: 1050;
        }

        .scroll-top.show {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }

        .scroll-top:hover {
            color: #fff;
        }

        .section-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(15, 118, 110, 0.1);
            color: var(--brand);
            border-radius: 999px;
            padding: 0.45rem 0.8rem;
            font-size: 0.74rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 0.85rem;
        }

        @media (max-width: 1199.98px) {
            .feature-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .admission-grid,
            .policy-grid,
            .support-grid,
            .footer-grid,
            .hero-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 991.98px) {
            .hero-shell {
                padding-top: 7rem;
            }

            .hero-panel {
                padding: 1.4rem;
            }

            .hero-title {
                max-width: none;
            }

            .footer-grid,
            .admission-grid,
            .policy-grid,
            .support-grid,
            .hero-stats {
                grid-template-columns: 1fr;
            }

            .hero-slide-image {
                min-height: 300px;
            }
        }

        @media (max-width: 767.98px) {
            .feature-grid {
                grid-template-columns: 1fr;
            }

            .hero-actions {
                flex-direction: column;
            }

            .btn-hero {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body id="top">
    <nav class="navbar navbar-expand-lg navbar-dark home-navbar fixed-top">
        <div class="container py-2">
            <a class="navbar-brand d-flex align-items-center gap-3" href="home">
                <span class="brand-badge">AIT</span>
                <span class="brand-copy">
                    <span>Ahmer Institute of Technology</span>
                    <strong>Admissions & Student Portal</strong>
                </span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#homeNav" aria-controls="homeNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="homeNav">
                <div class="navbar-nav align-items-lg-center gap-lg-1 ms-auto">
                    <a class="nav-link text-white-50 fw-semibold" href="#about">About</a>
                    <a class="nav-link text-white-50 fw-semibold" href="#programs">Programs</a>
                    <a class="nav-link text-white-50 fw-semibold" href="#admissions">Admissions</a>
                    <a class="nav-link text-white-50 fw-semibold" href="#faq">FAQ</a>
                    <a class="nav-link text-white-50 fw-semibold" href="#contact">Contact</a>
                    <a class="btn btn-sm btn-brand ms-lg-2 mt-2 mt-lg-0 rounded-pill px-3 fw-bold" href="registration.php">Apply Online</a>
                    <?php if ($student_logged_in): ?>
                        <a class="btn btn-sm btn-ghost ms-lg-2 mt-2 mt-lg-0 rounded-pill px-3 fw-bold" href="dashboard.php">Dashboard</a>
                    <?php else: ?>
                        <a class="btn btn-sm btn-ghost ms-lg-2 mt-2 mt-lg-0 rounded-pill px-3 fw-bold" href="log-in.php">Student Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <main class="hero-shell">
        <div class="container">
            <section class="hero-panel">
                <div class="row align-items-center g-4">
                    <div class="col-lg-6">
                        <span class="hero-eyebrow"><i class="bi bi-lightning-charge-fill"></i> Complete University Website</span>
                        <h1 class="hero-title">Build your future with AIT.</h1>
                        <p class="hero-copy">Ahmer Institute of Technology is presented as a modern university website with admissions, public information, student services, and a secure review workflow. The UI is designed to feel polished, responsive, and ready for real campus content.</p>
                        <div class="hero-actions">
                            <a class="btn btn-hero btn-brand" href="registration.php"><i class="bi bi-person-plus-fill me-2"></i>Start Application</a>
                            <a class="btn btn-hero btn-ghost" href="#programs"><i class="bi bi-grid-fill me-2"></i>Explore Programs</a>
                            <a class="btn btn-hero btn-ghost" href="#contact"><i class="bi bi-telephone-fill me-2"></i>Contact Desk</a>
                        </div>
                        <div class="hero-stats">
                            <?php foreach ($home_stats as $stat): ?>
                                <div class="stat-pill">
                                    <strong><?php echo htmlspecialchars($stat['value']); ?></strong>
                                    <span><?php echo htmlspecialchars($stat['label']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="hero-carousel-shell">
                            <div id="aitHeroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="4500">
                                <div class="carousel-indicators">
                                    <?php foreach ($carousel_slides as $index => $slide): ?>
                                        <button type="button" data-bs-target="#aitHeroCarousel" data-bs-slide-to="<?php echo $index; ?>" class="<?php echo $index === 0 ? 'active' : ''; ?>" <?php echo $index === 0 ? 'aria-current="true"' : ''; ?> aria-label="Slide <?php echo $index + 1; ?>"></button>
                                    <?php endforeach; ?>
                                </div>
                                <div class="carousel-inner">
                                    <?php foreach ($carousel_slides as $index => $slide): ?>
                                        <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                            <div class="hero-slide-card">
                                                <div class="hero-slide-image">
                                                    <img src="<?php echo htmlspecialchars($slide['image']); ?>" alt="<?php echo htmlspecialchars($slide['title']); ?>">
                                                    <div class="hero-slide-copy">
                                                        <span class="eyebrow"><?php echo htmlspecialchars($slide['eyebrow']); ?></span>
                                                        <h3><?php echo htmlspecialchars($slide['title']); ?></h3>
                                                        <p><?php echo htmlspecialchars($slide['text']); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button class="carousel-control-prev" type="button" data-bs-target="#aitHeroCarousel" data-bs-slide="prev">
                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Previous</span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#aitHeroCarousel" data-bs-slide="next">
                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Next</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section-wrap" id="highlights">
                <div class="section-head">
                    <span class="eyebrow">Highlights</span>
                    <h2>Ten core strengths of the AIT portal.</h2>
                    <p>These cards describe the university experience from admissions and security to student support and future campus expansion.</p>
                </div>
                <div class="feature-grid">
                    <?php foreach ($feature_cards as $card): ?>
                        <article class="feature-card">
                            <div class="icon"><i class="bi <?php echo htmlspecialchars($card['icon']); ?>"></i></div>
                            <h3><?php echo htmlspecialchars($card['title']); ?></h3>
                            <p><?php echo htmlspecialchars($card['text']); ?></p>
                            <div class="meta"><i class="bi bi-arrow-right"></i> Learn more through the portal</div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="section-wrap" id="admissions">
                <div class="section-head">
                    <span class="eyebrow">Admissions Flow</span>
                    <h2>A clean application journey from sign-up to approval.</h2>
                    <p>The process is intentionally simple: register, complete the dashboard, upload documents, and wait for review. Students always know what comes next.</p>
                </div>
                <div class="admission-grid">
                    <?php foreach ($admission_steps as $step): ?>
                        <article class="admission-step">
                            <div class="step-no"><?php echo htmlspecialchars($step['step']); ?></div>
                            <span class="badge-step">Step <?php echo htmlspecialchars($step['step']); ?></span>
                            <h3><?php echo htmlspecialchars($step['title']); ?></h3>
                            <p><?php echo htmlspecialchars($step['text']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="section-wrap" id="about">
                <div class="split-panel row g-0 align-items-stretch">
                    <div class="col-lg-5 split-media">
                        <img src="./assets/images/home/home-slide-2.svg" alt="AIT university planning visual">
                    </div>
                    <div class="col-lg-7 p-4 p-lg-5">
                        <span class="section-badge">About AIT</span>
                        <div class="section-head mb-3">
                            <h2>Built to feel like a complete university website.</h2>
                            <p>AIT is presented as a modern admissions-focused university platform that can grow into a larger institutional website with public pages, student services, and policy sections.</p>
                        </div>
                        <ul class="check-list">
                            <li><i class="bi bi-check-circle-fill"></i> Responsive layout that stays usable on desktop, tablet, and mobile.</li>
                            <li><i class="bi bi-check-circle-fill"></i> Secure student and admin workflows with CSRF and secret-key gated access.</li>
                            <li><i class="bi bi-check-circle-fill"></i> Upload policy that rejects oversized files and compresses supported images automatically.</li>
                            <li><i class="bi bi-check-circle-fill"></i> Clear navigation for admissions, help, policy, and review actions.</li>
                        </ul>
                        <div class="mini-pill-row">
                            <span class="mini-pill"><i class="bi bi-shield-check"></i> Security First</span>
                            <span class="mini-pill"><i class="bi bi-phone"></i> Mobile Friendly</span>
                            <span class="mini-pill"><i class="bi bi-camera"></i> Media Ready</span>
                            <span class="mini-pill"><i class="bi bi-building"></i> University Branding</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section-wrap" id="programs">
                <div class="section-head">
                    <span class="eyebrow">Programs</span>
                    <h2>Program cards that can expand into full degree pages later.</h2>
                    <p>These entries are placeholders for the public university website structure and can be turned into dedicated program pages whenever you want.</p>
                </div>
                <div class="policy-grid">
                    <?php foreach (
                        [
                            ['icon' => 'bi-cpu-fill', 'title' => 'Computer Science', 'text' => 'Focus on programming, systems, AI, software engineering, and modern computing careers.'],
                            ['icon' => 'bi-gear-fill', 'title' => 'Engineering', 'text' => 'Civil, electrical, and mechanical engineering tracks with practical academic planning.'],
                            ['icon' => 'bi-heart-pulse-fill', 'title' => 'Health & Support', 'text' => 'Student wellbeing, help desk support, and campus assistance resources.'],
                        ] as $program
                    ): ?>
                        <article class="policy-card">
                            <div class="icon"><i class="bi <?php echo htmlspecialchars($program['icon']); ?>"></i></div>
                            <h3><?php echo htmlspecialchars($program['title']); ?></h3>
                            <p><?php echo htmlspecialchars($program['text']); ?></p>
                            <div class="meta"><i class="bi bi-arrow-right"></i> Expand to a full page</div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="section-wrap" id="faq">
                <div class="section-head">
                    <span class="eyebrow">FAQ</span>
                    <h2>Frequently asked questions for applicants.</h2>
                    <p>Quick answers make the admissions process feel more confident and less confusing for students and parents.</p>
                </div>
                <div class="accordion accordion-flush split-panel p-3" id="faqAccordion">
                    <?php foreach (
                        [
                            ['q' => 'Which documents are required for admission?', 'a' => 'The dashboard lists the required documents with a passport-size photo guide, identity documents, academic results, and optional certificates.'],
                            ['q' => 'How does the upload compression work?', 'a' => 'Supported images over 1 MB are compressed automatically when possible, and any file above 5 MB is rejected on the server.'],
                            ['q' => 'When can I download my slip?', 'a' => 'The slip becomes available only after the application is approved by the admin review process.'],
                        ] as $index => $faq
                    ): ?>
                        <div class="accordion-item faq-item mb-3">
                            <h2 class="accordion-header" id="faqHeading<?php echo $index; ?>">
                                <button class="accordion-button <?php echo $index === 0 ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse<?php echo $index; ?>" aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-controls="faqCollapse<?php echo $index; ?>">
                                    <?php echo htmlspecialchars($faq['q']); ?>
                                </button>
                            </h2>
                            <div id="faqCollapse<?php echo $index; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" aria-labelledby="faqHeading<?php echo $index; ?>" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <?php echo htmlspecialchars($faq['a']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="section-wrap" id="life">
                <div class="section-head">
                    <span class="eyebrow">Campus Life</span>
                    <h2>A softer, more welcoming presentation of the university brand.</h2>
                    <p>This area can later hold galleries, announcements, hostel information, transport details, and event highlights.</p>
                </div>
                <div class="support-grid">
                    <?php foreach (
                        [
                            ['icon' => 'bi-camera-fill', 'title' => 'Media Gallery', 'text' => 'Add campus photos, laboratory shots, event highlights, and achievement banners here.'],
                            ['icon' => 'bi-bus-front-fill', 'title' => 'Transport', 'text' => 'Explain routes, pickup points, and transit timing for students and visitors.'],
                            ['icon' => 'bi-house-heart-fill', 'title' => 'Hostel & Welfare', 'text' => 'Describe student living, welfare support, and family-friendly campus services.'],
                        ] as $lifeCard
                    ): ?>
                        <article class="support-card">
                            <div class="icon"><i class="bi <?php echo htmlspecialchars($lifeCard['icon']); ?>"></i></div>
                            <h3><?php echo htmlspecialchars($lifeCard['title']); ?></h3>
                            <p><?php echo htmlspecialchars($lifeCard['text']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="section-wrap" id="scholarships">
                <div class="split-panel p-4 p-lg-5">
                    <div class="row g-4 align-items-center">
                        <div class="col-lg-7">
                            <span class="section-badge">Scholarships & Support</span>
                            <div class="section-head mb-3">
                                <h2>Scholarships, fee guidance, and admission support in one place.</h2>
                                <p>Use this section for merit awards, need-based assistance, and help for students who need document verification support.</p>
                            </div>
                            <div class="mini-pill-row">
                                <span class="mini-pill"><i class="bi bi-award-fill"></i> Merit Awards</span>
                                <span class="mini-pill"><i class="bi bi-cash-coin"></i> Fee Guidance</span>
                                <span class="mini-pill"><i class="bi bi-life-preserver"></i> Help Desk</span>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <article class="policy-card mb-0">
                                <div class="icon"><i class="bi bi-cash-stack"></i></div>
                                <h3>Fee & Scholarship Card</h3>
                                <p>Place detailed fee structure, scholarship rules, deadlines, and contact details here for parents and students.</p>
                                <div class="meta"><i class="bi bi-arrow-right"></i> Expand into a full admissions page</div>
                            </article>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section-wrap" id="contact">
                <div class="section-head">
                    <span class="eyebrow">Contact</span>
                    <h2>Keep contact, legal, and help links easy to find.</h2>
                    <p>The footer below uses 14 links so the site feels like a complete university portal instead of a single landing page.</p>
                </div>
                <div class="support-grid">
                    <article class="support-card">
                        <div class="icon"><i class="bi bi-geo-alt-fill"></i></div>
                        <h3>Visit AIT</h3>
                        <p>Add your campus address, directions, and map embed here once you have the official details ready.</p>
                    </article>
                    <article class="support-card">
                        <div class="icon"><i class="bi bi-envelope-paper-fill"></i></div>
                        <h3>Email Support</h3>
                        <p>Use this slot for admissions email, registrar contact, or any central university inbox.</p>
                    </article>
                    <article class="support-card">
                        <div class="icon"><i class="bi bi-telephone-fill"></i></div>
                        <h3>Phone Support</h3>
                        <p>Share phone numbers for admission queries, document help, and general university communication.</p>
                    </article>
                </div>
            </section>
        </div>
    </main>

    <footer class="home-footer" id="legal">
        <div class="container py-5">
            <div class="footer-grid">
                <div>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="brand-badge">AIT</span>
                        <div>
                            <div class="fw-bold text-white fs-5" style="font-weight: 900;">Ahmer Institute of Technology</div>
                            <div class="text-white-50 small">University admissions, student support, and review workflow.</div>
                        </div>
                    </div>
                    <p class="mb-0">This homepage is built to feel like a real institutional website with a polished university identity and a responsive admissions focus.</p>
                </div>
                <div>
                    <h3 class="h6 text-white fw-bold mb-3">Explore</h3>
                    <ul class="footer-links">
                        <li><a href="#top">Home</a></li>
                        <li><a href="#about">About AIT</a></li>
                        <li><a href="#programs">Programs</a></li>
                        <li><a href="#admissions">Admissions</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="h6 text-white fw-bold mb-3">Student</h3>
                    <ul class="footer-links">
                        <li><a href="registration.php">Apply Online</a></li>
                        <li><a href="log-in.php">Student Login</a></li>
                        <li><a href="dashboard.php">Student Dashboard</a></li>
                        <li><a href="#faq">FAQ</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="h6 text-white fw-bold mb-3">Support & Legal</h3>
                    <ul class="footer-links">
                        <li><a href="#contact">Contact</a></li>
                        <li><a href="#scholarships">Scholarships</a></li>
                        <li><a href="#life">Campus Life</a></li>
                        <li><a href="#privacy">Privacy Policy</a></li>
                        <li><a href="#support">Help Desk</a></li>
                    </ul>
                </div>
            </div>
            <div class="row g-4 mt-1">
                <?php foreach ($policy_cards as $policy): ?>
                    <div class="col-md-4" id="<?php echo htmlspecialchars($policy['id']); ?>">
                        <article class="policy-card">
                            <div class="icon"><i class="bi bi-file-earmark-text-fill"></i></div>
                            <h3><?php echo htmlspecialchars($policy['title']); ?></h3>
                            <p><?php echo htmlspecialchars($policy['text']); ?></p>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="footer-note d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-2">
                <span>© <?php echo date('Y'); ?> Ahmer Institute of Technology (AIT). All rights reserved.</span>
                <span>Built for a complete university-style web presence with admissions, policies, support, and secure student workflows.</span>
            </div>
        </div>
    </footer>

    <button type="button" class="scroll-top" id="scrollTopBtn" aria-label="Scroll to top">
        <i class="bi bi-arrow-up"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script nonce="<?php echo htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        (function() {
            const scrollTopBtn = document.getElementById('scrollTopBtn');
            const toggleScrollTop = () => {
                if (window.scrollY > 420) {
                    scrollTopBtn.classList.add('show');
                } else {
                    scrollTopBtn.classList.remove('show');
                }
            };

            window.addEventListener('scroll', toggleScrollTop, {
                passive: true
            });
            toggleScrollTop();

            scrollTopBtn.addEventListener('click', () => {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        })();
    </script>
</body>

</html>