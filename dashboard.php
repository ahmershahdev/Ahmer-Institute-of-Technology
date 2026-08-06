<?php
session_start();
if (!isset($_SESSION['student_id'])) {
    header("Location: log-in.php");
    exit();
}

require_once './backend/data.php';
require_once './backend/security.php';

$student_id = $_SESSION['student_id'];
$app_status = 'none';
$app_data = null;
$csp_nonce = ait_bootstrap_security();
$allowed_panes = ['dashboard', 'apply', 'view-application', 'download-slip', 'test-result'];
$requested_pane = $_GET['pane'] ?? 'dashboard';
$initial_pane = in_array($requested_pane, $allowed_panes, true) ? $requested_pane : 'dashboard';

$stmt = $conn->prepare("SELECT * FROM applications WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $app_status = $row['status'] ?? 'applied';
    $app_data = $row;
}

$has_application = $app_data !== null;
$can_download_slip = $app_status === 'approved';
$progress_map = [
    'none' => 0,
    'applied' => 35,
    'challan_uploaded' => 65,
    'approved' => 100,
    'rejected' => 35,
];
$application_progress = $progress_map[$app_status] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS | Ahmer Institute of Technology (AIT)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/style.css">
    <style nonce="<?php echo htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        :root {
            --nav-bg: #0f172a;
            --sidebar-bg: linear-gradient(180deg, #0f766e 0%, #0f172a 100%);
            --sidebar-solid: #0f766e;
            --content-bg: #eef4f7;
            --sidebar-width: 260px;
            --header-height: 74px;
            --surface: rgba(255, 255, 255, 0.92);
            --border-soft: rgba(15, 23, 42, 0.08);
            --brand: #0f766e;
            --brand-strong: #115e59;
            --accent: #f59e0b;
            --text-muted: #64748b;
        }

        body {
            background-color: var(--content-bg);
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
            color: #0f172a;
        }

        /* Navbar Styles */
        .custom-navbar {
            background: linear-gradient(135deg, #0f172a 0%, #0f766e 100%) !important;
            height: var(--header-height);
            z-index: 1030;
            backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .navbar-brand-box {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding-left: 1rem;
        }

        .navbar-brand-logo {
            height: 38px;
            width: 38px;
            object-fit: contain;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.08);
            padding: 4px;
        }

        .navbar-brand-text {
            color: #ffffff;
            font-weight: 700;
            font-size: 1rem;
            line-height: 1.15;
            letter-spacing: 0.2px;
        }

        .nav-toggle-btn {
            display: none;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            color: #fff;
            font-size: 1.35rem;
            cursor: pointer;
            transition: transform 0.2s ease, background-color 0.2s ease;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-toggle-btn:hover {
            transform: translateY(-1px);
            background: rgba(255, 255, 255, 0.18);
        }

        .user-shell {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-left: auto;
            color: #fff;
            padding-right: 1rem;
        }

        .user-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 999px;
            padding: 0.45rem 0.85rem;
            font-size: 0.92rem;
            white-space: nowrap;
        }

        .logout-pill {
            color: #fff;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: transform 0.2s ease, background-color 0.2s ease;
        }

        .logout-pill:hover {
            transform: translateY(-1px);
            background: rgba(255, 255, 255, 0.18);
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            height: calc(100vh - var(--header-height));
            position: fixed;
            top: var(--header-height);
            left: 0;
            background: var(--sidebar-bg);
            z-index: 1020;
            transition: transform 0.28s ease, box-shadow 0.28s ease;
            box-shadow: 10px 0 30px rgba(15, 23, 42, 0.12);
            overflow-y: auto;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-item {
            cursor: pointer;
            margin: 0.35rem 0.75rem;
        }

        .sidebar-item a {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.9rem 1rem;
            color: rgba(255, 255, 255, 0.82);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: 14px;
            transition: all 0.2s ease;
        }

        .sidebar-item a:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }

        .sidebar-item.active a {
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
        }

        .sidebar-item .bi {
            font-size: 1.05rem;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 1.5rem;
            margin-top: var(--header-height);
            min-height: calc(100vh - var(--header-height));
            transition: margin-left 0.28s ease;
        }

        .portal-card {
            background: var(--surface);
            border: 1px solid var(--border-soft);
            border-radius: 24px;
            box-shadow: 0 18px 55px rgba(15, 23, 42, 0.08);
            padding: 1.5rem;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }

        .dashboard-hero {
            background: linear-gradient(135deg, #0f172a 0%, #0f766e 100%);
            color: #fff;
            border-radius: 24px;
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 22px 60px rgba(15, 23, 42, 0.18);
        }

        .dashboard-hero .eyebrow {
            text-transform: uppercase;
            letter-spacing: 0.16em;
            font-size: 0.75rem;
            opacity: 0.8;
        }

        .dashboard-hero h3 {
            font-size: clamp(1.5rem, 2.5vw, 2.3rem);
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .dashboard-hero p {
            margin-bottom: 0;
            max-width: 65ch;
            color: rgba(255, 255, 255, 0.88);
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .status-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid var(--border-soft);
            padding: 1rem 1.1rem;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
        }

        .status-card .label {
            color: var(--text-muted);
            font-size: 0.82rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.35rem;
        }

        .status-card .value {
            font-size: 1.05rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0;
        }

        .site-footer {
            margin-left: var(--sidebar-width);
            min-height: 72px;
            background: rgba(255, 255, 255, 0.9);
            border-top: 1px solid var(--border-soft);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.5rem;
            font-size: 0.92rem;
            color: #64748b;
            transition: margin-left 0.28s ease;
        }

        .footer-links {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .footer-link {
            border: 0;
            border-radius: 999px;
            padding: 0.7rem 1rem;
            background: #0f172a;
            color: #fff;
            font-weight: 700;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.12);
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
        }

        .footer-link:hover,
        .footer-link.is-active {
            color: #fff;
            text-decoration: none;
        }

        .footer-link:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.16);
        }

        .footer-link.is-active {
            background: linear-gradient(135deg, #0f766e 0%, #0e7490 100%);
        }

        .sidebar-backdrop {
            display: none;
        }

        .sample-img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            margin-right: 10px;
        }

        .sample-guide-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .sample-guide-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 0.85rem;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
        }

        .sample-guide-card img {
            width: 100%;
            height: 138px;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            margin-bottom: 0.75rem;
            background: #ffffff;
        }

        .sample-guide-card h6 {
            font-size: 0.95rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 0.35rem;
        }

        .sample-guide-card p {
            margin-bottom: 0;
            color: #64748b;
            font-size: 0.84rem;
            line-height: 1.45;
        }

        .sample-photo-note {
            background: linear-gradient(135deg, #f8fafc 0%, #eef6ff 100%);
            border: 1px solid #dbeafe;
            border-radius: 16px;
            padding: 0.9rem 1rem;
            margin-top: 0.85rem;
            margin-bottom: 1rem;
            color: #0f172a;
        }

        .sample-photo-note strong {
            color: #0f766e;
        }

        .sample-photo-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.85rem;
        }

        .sample-photo-card {
            border-radius: 18px;
            overflow: hidden;
            border: 1px solid #dbe4ee;
            background: #fff;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.06);
        }

        .sample-photo-card img {
            width: 100%;
            height: 170px;
            object-fit: cover;
            display: block;
        }

        .sample-photo-card .sample-caption {
            padding: 0.75rem 0.8rem 0.9rem;
        }

        .sample-photo-card .sample-caption h6 {
            font-size: 0.92rem;
            font-weight: 800;
            margin-bottom: 0.3rem;
            color: #0f172a;
        }

        .sample-photo-card .sample-caption p {
            margin-bottom: 0;
            font-size: 0.82rem;
            line-height: 1.45;
            color: #64748b;
        }

        .sample-photo-card.accepted {
            border-color: rgba(22, 163, 74, 0.25);
        }

        .sample-photo-card.rejected {
            border-color: rgba(220, 38, 38, 0.25);
        }

        .sample-badge {
            position: absolute;
            top: 0.75rem;
            left: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.4rem 0.7rem;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            color: #fff;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.16);
        }

        .sample-photo-card .image-wrap {
            position: relative;
        }

        .sample-photo-card.accepted .sample-badge {
            background: linear-gradient(135deg, #16a34a 0%, #0f766e 100%);
        }

        .sample-photo-card.rejected .sample-badge {
            background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
        }

        .form-section-title {
            border-bottom: 2px solid var(--brand);
            padding-bottom: 10px;
            margin-bottom: 25px;
            color: var(--brand);
            font-weight: 700;
        }

        /* Form Card Layout */
        .form-block-card {
            border: 1px solid #e3e8ee;
            border-radius: 18px;
            margin-bottom: 25px;
            background-color: #ffffff;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.04);
            overflow: hidden;
        }

        .form-block-header {
            background-color: #f8fafc;
            border-bottom: 1px solid #e3e8ee;
            padding: 12px 20px;
            font-weight: 700;
            color: var(--brand);
            display: flex;
            align-items: center;
        }

        .form-block-header i {
            font-size: 1.1rem;
            margin-right: 10px;
        }

        .form-block-body {
            padding: 20px;
        }

        .action-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1rem;
            border-radius: 999px;
            background: linear-gradient(135deg, #0f766e 0%, #0e7490 100%);
            color: #ffffff;
            font-weight: 800;
            font-size: 0.9rem;
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 14px 28px rgba(15, 118, 110, 0.24);
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
        }

        .action-chip:hover {
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 18px 34px rgba(15, 118, 110, 0.3);
            filter: brightness(1.02);
        }

        .action-chip.disabled {
            background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
            color: rgba(255, 255, 255, 0.92);
            pointer-events: none;
            box-shadow: none;
        }

        .portal-card .btn,
        .dashboard-callout .btn {
            border-radius: 14px;
            font-weight: 700;
            padding-left: 1rem;
            padding-right: 1rem;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.08);
        }

        .portal-card .btn-primary {
            background: linear-gradient(135deg, #0f766e 0%, #0e7490 100%);
            border-color: transparent;
            color: #fff;
        }

        .portal-card .btn-success {
            background: linear-gradient(135deg, #15803d 0%, #16a34a 100%);
            border-color: transparent;
            color: #fff;
        }

        .portal-card .btn-danger {
            background: linear-gradient(135deg, #b91c1c 0%, #dc2626 100%);
            border-color: transparent;
            color: #fff;
        }

        .portal-card .btn-outline-secondary,
        .dashboard-callout .btn-outline-secondary {
            background: #e2e8f0;
            color: #0f172a;
            border-color: #cbd5e1;
        }

        .portal-card .btn-outline-primary,
        .dashboard-callout .btn-outline-primary {
            background: #dbeafe;
            color: #075985;
            border-color: #93c5fd;
        }

        .portal-card .btn:hover,
        .dashboard-callout .btn:hover {
            transform: translateY(-1px);
        }

        .section-muted {
            color: var(--text-muted);
        }

        .dashboard-callout {
            border-radius: 20px;
            border: 1px solid var(--border-soft);
            background: linear-gradient(180deg, #ffffff 0%, #f8fbfc 100%);
            padding: 1.1rem 1.2rem;
        }

        .responsive-stack {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(320px, 1fr);
            gap: 1rem;
        }

        .progress-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .progress-list li {
            display: flex;
            gap: 0.75rem;
            padding: 0.65rem 0;
            border-bottom: 1px dashed rgba(15, 23, 42, 0.08);
        }

        .progress-list li:last-child {
            border-bottom: 0;
        }

        .progress-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-top: 0.35rem;
            background: var(--accent);
            flex: 0 0 auto;
        }

        .sidebar-collapsed .sidebar {
            transform: translateX(-100%);
        }

        .sidebar-collapsed .main-content,
        .sidebar-collapsed .site-footer {
            margin-left: 0;
        }

        @media (max-width: 991.98px) {
            .nav-toggle-btn {
                display: inline-flex;
            }

            .user-shell {
                padding-right: 0.75rem;
            }

            .user-pill span:last-child {
                display: none;
            }

            .sidebar {
                transform: translateX(-100%);
                width: min(86vw, var(--sidebar-width));
            }

            .sidebar-open .sidebar {
                transform: translateX(0);
            }

            .main-content,
            .site-footer {
                margin-left: 0;
            }

            .sidebar-backdrop {
                display: block;
                position: fixed;
                inset: var(--header-height) 0 0 0;
                background: rgba(15, 23, 42, 0.45);
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.2s ease;
                z-index: 1015;
            }

            .sidebar-open .sidebar-backdrop {
                opacity: 1;
                pointer-events: auto;
            }

            .status-grid,
            .responsive-stack,
            .sample-guide-grid {
                grid-template-columns: 1fr;
            }

            .site-footer {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 575.98px) {
            .main-content {
                padding: 1rem;
            }

            .portal-card,
            .dashboard-hero,
            .status-card {
                border-radius: 18px;
            }

            .navbar-brand-text {
                font-size: 0.95rem;
            }

            .action-chip,
            .footer-link,
            .portal-card .btn,
            .dashboard-callout .btn {
                width: 100%;
                justify-content: center;
            }

            .footer-links {
                width: 100%;
            }

            .sample-guide-card img {
                height: 122px;
            }
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark custom-navbar fixed-top p-0 shadow-sm">
        <div class="container-fluid p-0 h-100 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center h-100">
                <div class="navbar-brand-box">
                    <img class="navbar-brand-logo" src="./assets/images/logo/ait_logo.png" alt="Ahmer Institute of Technology (AIT) Logo">
                    <span class="navbar-brand-text">Ahmer Institute of Technology (AIT) Portal</span>
                </div>
                <div class="nav-toggle-btn ms-3" id="sidebarToggle" aria-label="Toggle navigation">
                    <i class="bi bi-list"></i>
                </div>
            </div>
            <div class="user-shell">
                <div class="user-pill">
                    <i class="bi bi-person-circle"></i>
                    <span><?= htmlspecialchars($_SESSION['student_name'] ?? 'Candidate'); ?></span>
                </div>
                <a href="logout.php" class="logout-pill" aria-label="Log out">
                    <i class="bi bi-power"></i>
                </a>
            </div>
        </div>
    </nav>

    <!-- SIDEBAR NAV -->
    <div class="sidebar" id="sidebarMenu">
        <ul class="sidebar-menu pt-3" id="nav-tabs" role="tablist">
            <li class="sidebar-item active" data-bs-target="#dashboard" role="tab" data-bs-toggle="tab">
                <a><i class="bi bi-grid-fill"></i><span>Dashboard</span></a>
            </li>
            <li class="sidebar-item" data-bs-target="#apply" role="tab" data-bs-toggle="tab">
                <a><i class="bi bi-pencil-square"></i><span>Apply Online</span></a>
            </li>
            <li class="sidebar-item" data-bs-target="#view-application" role="tab" data-bs-toggle="tab">
                <a><i class="bi bi-file-earmark-text"></i><span>View Application</span></a>
            </li>
            <li class="sidebar-item" data-bs-target="#download-slip" role="tab" data-bs-toggle="tab">
                <a><i class="bi bi-download"></i><span>Download Test Slip</span></a>
            </li>
            <li class="sidebar-item" data-bs-target="#test-result" role="tab" data-bs-toggle="tab">
                <a><i class="bi bi-award"></i><span>Test Result</span></a>
            </li>
        </ul>
    </div>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- MAIN CONTENT / TAB PANES -->
    <main class="main-content">
        <div class="tab-content">

            <!-- 1. DASHBOARD PANE -->
            <div class="tab-pane fade show active" id="dashboard" role="tabpanel">
                <div class="dashboard-hero">
                    <div class="eyebrow mb-2">Admissions Workspace</div>
                    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">
                        <div>
                            <h3 class="mb-2">Welcome back, <?= htmlspecialchars($_SESSION['student_name'] ?? 'Candidate'); ?></h3>
                            <p>Track your application, challan, and test slip from a single responsive dashboard.</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <a class="action-chip" href="#apply" data-bs-toggle="tab" data-bs-target="#apply"><i class="bi bi-pencil-square"></i> Continue Application</a>
                            <a class="action-chip <?= $has_application ? '' : 'disabled'; ?>" href="#view-application" data-bs-toggle="tab" data-bs-target="#view-application"><i class="bi bi-file-earmark-text"></i> View Submission</a>
                        </div>
                    </div>
                </div>

                <div class="status-grid">
                    <div class="status-card">
                        <div class="label">Application</div>
                        <p class="value"><?= $has_application ? 'Submitted' : 'Not started'; ?></p>
                    </div>
                    <div class="status-card">
                        <div class="label">Challan</div>
                        <p class="value"><?= $app_status === 'challan_uploaded' ? 'Uploaded' : ($has_application ? 'Pending' : 'Locked'); ?></p>
                    </div>
                    <div class="status-card">
                        <div class="label">Test Slip</div>
                        <p class="value"><?= $can_download_slip ? 'Ready' : 'Waiting'; ?></p>
                    </div>
                    <div class="status-card">
                        <div class="label">Result</div>
                        <p class="value"><?= $app_status === 'approved' ? 'Published soon' : 'Pending'; ?></p>
                    </div>
                </div>

                <div class="dashboard-callout mb-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                        <strong>Application Progress</strong>
                        <span class="badge text-bg-dark"><?= $application_progress; ?>%</span>
                    </div>
                    <div class="progress" style="height: 12px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $application_progress; ?>%;" aria-valuenow="<?= $application_progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                <div class="responsive-stack">
                    <div class="dashboard-callout">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                            <h4 class="mb-0">Next Step</h4>
                            <span class="badge text-bg-dark text-uppercase"><?= htmlspecialchars(str_replace('_', ' ', $app_status)); ?></span>
                        </div>

                        <?php if (!$has_application): ?>
                            <p class="section-muted mb-3">Your account is ready, but the application form has not been submitted yet.</p>
                            <div class="alert alert-warning mb-0">
                                Open <strong>Apply Online</strong> to complete your profile and upload the required documents.
                            </div>
                        <?php elseif ($app_status === 'applied'): ?>
                            <p class="section-muted mb-3">Your application was received. The next step is to generate the exam challan.</p>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="generate_challan.php" target="_blank" class="action-chip"><i class="bi bi-printer"></i> Download Challan</a>
                            </div>
                            <div class="mt-4 p-3 rounded-4 border bg-white">
                                <h6 class="fw-bold mb-3">Upload Paid Challan</h6>
                                <form action="upload_challan.php" method="POST" enctype="multipart/form-data" class="row g-3 align-items-end">
                                    <?php echo ait_csrf_field(); ?>
                                    <div class="col-md-8">
                                        <label class="form-label">Upload Paid Challan Picture (images over 1MB auto-compress, max 5MB)</label>
                                        <input type="file" name="challan_pic" class="form-control file-validate" accept="image/*" required>
                                    </div>
                                    <div class="col-md-4 d-grid">
                                        <button type="submit" class="btn btn-success">Submit Paid Challan</button>
                                    </div>
                                </form>
                            </div>
                            <?php if (!empty($app_data['review_note'])): ?>
                                <div class="alert alert-warning mt-3 mb-0">
                                    <strong>Correction note:</strong> <?= htmlspecialchars($app_data['review_note']); ?>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($app_status === 'challan_uploaded'): ?>
                            <p class="section-muted mb-3">Your challan has been uploaded and is awaiting verification.</p>
                            <div class="alert alert-info mb-0">Once verified, the test slip will become available in the <strong>Download Test Slip</strong> tab.</div>
                        <?php elseif ($app_status === 'approved'): ?>
                            <p class="section-muted mb-3">Your application has been approved. Download your test slip and keep checking the result panel for updates.</p>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="#download-slip" class="action-chip" data-bs-toggle="tab" data-bs-target="#download-slip"><i class="bi bi-download"></i> Open Test Slip</a>
                                <a href="#test-result" class="action-chip" data-bs-toggle="tab" data-bs-target="#test-result"><i class="bi bi-graph-up"></i> Check Result</a>
                            </div>
                            <?php if (!empty($app_data['review_note'])): ?>
                                <div class="alert alert-success mt-3 mb-0">
                                    <strong>Admin note:</strong> <?= htmlspecialchars($app_data['review_note']); ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="section-muted mb-3">Your record is currently under review. Please revisit this dashboard for updates.</p>
                            <?php if (!empty($app_data['review_note'])): ?>
                                <div class="alert alert-warning mb-0">
                                    <strong>Admin note:</strong> <?= htmlspecialchars($app_data['review_note']); ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div class="dashboard-callout">
                        <h4 class="mb-3">Shortcuts</h4>
                        <ul class="progress-list">
                            <li><span class="progress-dot"></span>
                                <div><strong>Apply Online</strong>
                                    <div class="section-muted">Complete the admission form and upload documents.</div>
                                </div>
                            </li>
                            <li><span class="progress-dot"></span>
                                <div><strong>View Application</strong>
                                    <div class="section-muted">Review the submitted profile and uploaded files.</div>
                                </div>
                            </li>
                            <li><span class="progress-dot"></span>
                                <div><strong>Download Test Slip</strong>
                                    <div class="section-muted">Available after challan verification.</div>
                                </div>
                            </li>
                            <li><span class="progress-dot"></span>
                                <div><strong>Test Result</strong>
                                    <div class="section-muted">Results appear here after evaluation.</div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- 2. APPLY PANE -->
            <div class="tab-pane fade" id="apply" role="tabpanel">
                <div class="portal-card">
                    <h2 class="form-section-title"><i class="bi bi-journal-check me-2"></i>Admission Application Form</h2>

                    <?php if ($app_status !== 'none'): ?>
                        <div class="alert alert-success d-flex align-items-center" role="alert">
                            <i class="bi bi-check-circle-fill me-2 fs-4"></i>
                            <div>
                                You have already submitted your application! You can review your submitted details in the <strong>View Application</strong> tab.
                            </div>
                        </div>
                    <?php else: ?>
                        <form action="submit_application.php" method="POST" enctype="multipart/form-data">

                            <!-- SECTION 1: Personal Information -->
                            <div class="form-block-card">
                                <div class="form-block-header">
                                    <i class="bi bi-person-vcard"></i> 1. Personal Information
                                </div>
                                <div class="form-block-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Full Name (as per CNIC/B-Form) *</label>
                                            <input type="text" class="form-control" name="full_name" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Father's/Guardian's Name *</label>
                                            <input type="text" class="form-control" name="father_name" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">CNIC or B-Form Number *</label>
                                            <input type="text" class="form-control cnic-mask" name="cnic" placeholder="XXXXX-XXXXXXX-X" maxlength="15" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Father's/Guardian's CNIC *</label>
                                            <input type="text" class="form-control cnic-mask" name="father_cnic" placeholder="XXXXX-XXXXXXX-X" maxlength="15" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Date of Birth *</label>
                                            <input type="date" class="form-control" name="dob" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Gender *</label>
                                            <select class="form-select" name="gender" required>
                                                <option value="" selected disabled>Select Gender</option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Religion</label>
                                            <input type="text" class="form-control" name="religion" placeholder="e.g. Islam, Hinduism">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Nationality *</label>
                                            <input type="text" class="form-control" name="nationality" value="Pakistani" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Domicile / Province *</label>
                                            <select class="form-select" name="domicile_province" required>
                                                <option value="" selected disabled>Select Province</option>
                                                <option value="Sindh (Urban)">Sindh (Urban)</option>
                                                <option value="Sindh (Rural)">Sindh (Rural)</option>
                                                <option value="Punjab">Punjab</option>
                                                <option value="Balochistan">Balochistan</option>
                                                <option value="Khyber Pakhtunkhwa">Khyber Pakhtunkhwa</option>
                                                <option value="Gilgit Baltistan">Gilgit Baltistan</option>
                                                <option value="AJK">Azad Jammu & Kashmir</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">District & City *</label>
                                            <input type="text" class="form-control" name="district_city" placeholder="e.g. Hyderabad, Jamshoro" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 2: Contact Information -->
                            <div class="form-block-card">
                                <div class="form-block-header">
                                    <i class="bi bi-telephone"></i> 2. Contact Information
                                </div>
                                <div class="form-block-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Mobile Number *</label>
                                            <input type="tel" class="form-control" name="phone" placeholder="03XXXXXXXXX" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Alternate Mobile Number</label>
                                            <input type="tel" class="form-control" name="alt_phone" placeholder="03XXXXXXXXX">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Email Address *</label>
                                            <input type="email" class="form-control" name="email" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Permanent Address *</label>
                                            <textarea class="form-control" name="permanent_address" rows="2" required></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Postal / Current Address *</label>
                                            <textarea class="form-control" name="postal_address" rows="2" required></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 3: Academic Details -->
                            <div class="form-block-card">
                                <div class="form-block-header">
                                    <i class="bi bi-mortarboard"></i> 3. Academic Details
                                </div>
                                <div class="form-block-body">

                                    <!-- SSC / Matriculation -->
                                    <h6 class="text-primary fw-bold mb-3"><i class="bi bi-journal-bookmark me-1"></i> Matriculation / SSC / O-Levels</h6>
                                    <div class="row g-3 mb-4">
                                        <div class="col-md-4">
                                            <label class="form-label">Board / University *</label>
                                            <input type="text" class="form-control" name="matric_board" placeholder="e.g. BISE Hyderabad" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Roll No *</label>
                                            <input type="text" class="form-control" name="matric_roll" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Reg. Number</label>
                                            <input type="text" class="form-control" name="matric_reg">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Passing Year *</label>
                                            <input type="number" class="form-control" name="matric_year" placeholder="YYYY" min="1990" max="2026" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Group / Subjects *</label>
                                            <select class="form-select" name="matric_group" required>
                                                <option value="Science">Science</option>
                                                <option value="Arts">Arts</option>
                                                <option value="O-Levels">O-Levels</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Total Marks *</label>
                                            <input type="number" class="form-control" name="matric_total_marks" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Obtained Marks *</label>
                                            <input type="number" class="form-control" name="matric_obtained_marks" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Percentage (%) *</label>
                                            <input type="text" class="form-control" name="matric_percentage" placeholder="e.g. 85.5%" required>
                                        </div>
                                    </div>

                                    <hr class="my-4">

                                    <!-- HSSC / Intermediate -->
                                    <h6 class="text-primary fw-bold mb-3"><i class="bi bi-journal-bookmark-fill me-1"></i> Intermediate / HSSC / A-Levels / DAE</h6>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Board / University *</label>
                                            <input type="text" class="form-control" name="inter_board" placeholder="e.g. BISE Hyderabad" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Roll No *</label>
                                            <input type="text" class="form-control" name="inter_roll" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Reg. Number</label>
                                            <input type="text" class="form-control" name="inter_reg">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Passing Year *</label>
                                            <input type="number" class="form-control" name="inter_year" placeholder="YYYY" min="1990" max="2026" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Group / Subjects *</label>
                                            <select class="form-select" name="inter_group" required>
                                                <option value="Pre-Engineering">Pre-Engineering</option>
                                                <option value="Pre-Medical">Pre-Medical</option>
                                                <option value="ICS">ICS</option>
                                                <option value="Commerce">Commerce</option>
                                                <option value="A-Levels">A-Levels</option>
                                                <option value="DAE">DAE</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Total Marks *</label>
                                            <input type="number" class="form-control" name="inter_total_marks" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Obtained Marks *</label>
                                            <input type="number" class="form-control" name="inter_obtained_marks" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Percentage (%) *</label>
                                            <input type="text" class="form-control" name="inter_percentage" placeholder="e.g. 78.2%" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 4: Admission Program Details -->
                            <div class="form-block-card">
                                <div class="form-block-header">
                                    <i class="bi bi-building-check"></i> 4. Admission Program Details
                                </div>
                                <div class="form-block-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">University Campus *</label>
                                            <select class="form-select" name="campus" required>
                                                <option value="" selected disabled>Select Campus</option>
                                                <option value="Main Campus Jamshoro">Main Campus Jamshoro</option>
                                                <option value="Ahmer Institute of Technology (AIT) Shaheed Z.A Bhutto Campus Khairpur">Ahmer Institute of Technology (AIT) SZAB Campus Khairpur</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Faculty *</label>
                                            <select class="form-select" name="faculty" required>
                                                <option value="" selected disabled>Select Faculty</option>
                                                <option value="Electrical, Electronic & Computer Engineering">Electrical, Electronic & Computer Systems</option>
                                                <option value="Civil & Architecture">Civil & Architecture</option>
                                                <option value="Mechanical & Process Engineering">Mechanical & Process Engineering</option>
                                                <option value="Basic Sciences & Humanities">Basic Sciences & Humanities</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Degree Level *</label>
                                            <select class="form-select" name="degree_level" required>
                                                <option value="BS">Bachelor's (BS)</option>
                                                <option value="MS">Master's (MS)</option>
                                                <option value="MPhil">MPhil</option>
                                                <option value="PhD">PhD</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Shift Preference *</label>
                                            <select class="form-select" name="shift" required>
                                                <option value="Morning">Morning</option>
                                                <option value="Evening">Evening / Self-Finance</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">1st Choice Program / Major *</label>
                                            <select class="form-select" name="pref_1" required>
                                                <option value="" selected disabled>Choose Major...</option>
                                                <option value="Software Engineering">Software Engineering</option>
                                                <option value="Computer Systems Engineering">Computer Systems Engineering</option>
                                                <option value="Artificial Intelligence">Artificial Intelligence</option>
                                                <option value="Civil Engineering">Civil Engineering</option>
                                                <option value="Electrical Engineering">Electrical Engineering</option>
                                                <option value="Mechanical Engineering">Mechanical Engineering</option>
                                                <option value="Cyber Security">Cyber Security</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">2nd Choice Program</label>
                                            <select class="form-select" name="pref_2">
                                                <option value="" selected disabled>Choose Major...</option>
                                                <option value="Software Engineering">Software Engineering</option>
                                                <option value="Computer Systems Engineering">Computer Systems Engineering</option>
                                                <option value="Artificial Intelligence">Artificial Intelligence</option>
                                                <option value="Civil Engineering">Civil Engineering</option>
                                                <option value="Electrical Engineering">Electrical Engineering</option>
                                                <option value="Mechanical Engineering">Mechanical Engineering</option>
                                                <option value="Cyber Security">Cyber Security</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">3rd Choice Program</label>
                                            <select class="form-select" name="pref_3">
                                                <option value="" selected disabled>Choose Major...</option>
                                                <option value="Software Engineering">Software Engineering</option>
                                                <option value="Computer Systems Engineering">Computer Systems Engineering</option>
                                                <option value="Artificial Intelligence">Artificial Intelligence</option>
                                                <option value="Civil Engineering">Civil Engineering</option>
                                                <option value="Electrical Engineering">Electrical Engineering</option>
                                                <option value="Mechanical Engineering">Mechanical Engineering</option>
                                                <option value="Cyber Security">Cyber Security</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 5: Entry Test Information -->
                            <div class="form-block-card">
                                <div class="form-block-header">
                                    <i class="bi bi-card-checklist"></i> 5. Entry Test Information
                                </div>
                                <div class="form-block-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Pre-Entry Test Roll Number (if issued)</label>
                                            <input type="text" class="form-control" name="test_roll_no" placeholder="e.g. 10452">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Entry Test Date (if scheduled)</label>
                                            <input type="date" class="form-control" name="test_date">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 6: Required Documents -->
                            <div class="form-block-card">
                                <div class="form-block-header">
                                    <i class="bi bi-file-earmark-arrow-up"></i> 6. Required Documents (Images auto-compress over 1MB, max 5MB each)
                                </div>
                                <div class="form-block-body">
                                    <?php echo ait_csrf_field(); ?>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Passport-size Photograph *</label>
                                            <input type="file" class="form-control file-validate" name="profile_pic" accept=".jpg,.jpeg,.png" required>
                                            <small class="text-muted d-block mt-1">Blue or White background only</small>
                                        </div>
                                        <div class="col-12">
                                            <div class="sample-photo-note">
                                                <strong>Photo guide:</strong> Only <strong>Sample 1</strong> is acceptable. Samples 2, 3, and 4 show the kinds of photos that will be rejected.
                                            </div>
                                            <div class="sample-photo-grid">
                                                <div class="sample-photo-card accepted">
                                                    <div class="image-wrap">
                                                        <span class="sample-badge"><i class="bi bi-check-circle-fill"></i> ACCEPTED</span>
                                                        <img src="./assets/images/ait_sample_doc/ait-sample-doc-1.svg" alt="Accepted passport-size photograph sample">
                                                    </div>
                                                    <div class="sample-caption">
                                                        <h6>Sample 1</h6>
                                                        <p>Clear front-facing photo, plain background, proper lighting, and full face visible.</p>
                                                    </div>
                                                </div>
                                                <div class="sample-photo-card rejected">
                                                    <div class="image-wrap">
                                                        <span class="sample-badge"><i class="bi bi-x-circle-fill"></i> REJECTED</span>
                                                        <img src="./assets/images/ait_sample_doc/ait-sample-doc-2.svg" alt="Rejected passport-size photograph sample">
                                                    </div>
                                                    <div class="sample-caption">
                                                        <h6>Sample 2</h6>
                                                        <p>Not acceptable because the pose, crop, or document style does not match the required passport photo.</p>
                                                    </div>
                                                </div>
                                                <div class="sample-photo-card rejected">
                                                    <div class="image-wrap">
                                                        <span class="sample-badge"><i class="bi bi-x-circle-fill"></i> REJECTED</span>
                                                        <img src="./assets/images/ait_sample_doc/ait-sample-doc-3.svg" alt="Rejected passport-size photograph sample">
                                                    </div>
                                                    <div class="sample-caption">
                                                        <h6>Sample 3</h6>
                                                        <p>Not acceptable because this is a result card style image, not a passport-size photograph.</p>
                                                    </div>
                                                </div>
                                                <div class="sample-photo-card rejected">
                                                    <div class="image-wrap">
                                                        <span class="sample-badge"><i class="bi bi-x-circle-fill"></i> REJECTED</span>
                                                        <img src="./assets/images/ait_sample_doc/ait-sample-doc-4.svg" alt="Rejected passport-size photograph sample">
                                                    </div>
                                                    <div class="sample-caption">
                                                        <h6>Sample 4</h6>
                                                        <p>Not acceptable because certificates and scanned forms are not valid passport-size photos.</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Candidate's CNIC or B-Form *</label>
                                            <input type="file" class="form-control file-validate" name="doc_bform" accept=".jpg,.jpeg,.png,.pdf" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Father's/Guardian's CNIC *</label>
                                            <input type="file" class="form-control file-validate" name="doc_father_cnic" accept=".jpg,.jpeg,.png,.pdf" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Matric Certificate / Result Card *</label>
                                            <input type="file" class="form-control file-validate" name="doc_10th" accept=".jpg,.jpeg,.png,.pdf" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Intermediate Certificate / Result Card *</label>
                                            <input type="file" class="form-control file-validate" name="doc_12th" accept=".jpg,.jpeg,.png,.pdf" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Domicile Certificate *</label>
                                            <input type="file" class="form-control file-validate" name="doc_domicile" accept=".jpg,.jpeg,.png,.pdf" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">IBCC / HEC Equivalence Certificate (for O/A levels)</label>
                                            <input type="file" class="form-control file-validate" name="doc_equivalence" accept=".jpg,.jpeg,.png,.pdf">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Entry Test Result Card (where applicable)</label>
                                            <input type="file" class="form-control file-validate" name="doc_entry_test" accept=".jpg,.jpeg,.png,.pdf">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 7: Additional Information & Quotas -->
                            <div class="form-block-card">
                                <div class="form-block-header">
                                    <i class="bi bi-award-fill"></i> 7. Additional Information & Quotas
                                </div>
                                <div class="form-block-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Hafiz-e-Quran Certificate (if applicable)</label>
                                            <input type="file" class="form-control file-validate" name="doc_hafiz" accept=".jpg,.jpeg,.png,.pdf">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Disability Quota Certificate (if applicable)</label>
                                            <input type="file" class="form-control file-validate" name="doc_disability" accept=".jpg,.jpeg,.png,.pdf">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Sports Quota Documents (if applicable)</label>
                                            <input type="file" class="form-control file-validate" name="doc_sports" accept=".jpg,.jpeg,.png,.pdf">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Minority Quota Documents (if applicable)</label>
                                            <input type="file" class="form-control file-validate" name="doc_minority" accept=".jpg,.jpeg,.png,.pdf">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Work Experience Certificate (for Postgraduate)</label>
                                            <input type="file" class="form-control file-validate" name="doc_experience" accept=".jpg,.jpeg,.png,.pdf">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Publications / Research Papers (for MPhil/PhD)</label>
                                            <input type="file" class="form-control file-validate" name="doc_publications" accept=".jpg,.jpeg,.png,.pdf">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end my-4">
                                <button type="reset" class="btn btn-outline-secondary me-3 px-4">Reset Form</button>
                                <button type="submit" class="btn btn-primary btn-lg px-5"><i class="bi bi-send-check me-2"></i>Submit Application</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 3. VIEW APPLICATION PANE -->
            <div class="tab-pane fade" id="view-application" role="tabpanel">
                <div class="portal-card">
                    <h3 class="mb-4 text-primary"><i class="bi bi-file-earmark-person me-2"></i>Your Submitted Application</h3>

                    <?php if ($app_data): ?>
                        <div class="row mb-4">
                            <!-- Candidate Photo & Status Badge -->
                            <div class="col-md-3 text-center mb-3">
                                <?php if (!empty($app_data['profile_pic'])): ?>
                                    <img src="<?= htmlspecialchars($app_data['profile_pic']); ?>" alt="Profile Picture" class="img-thumbnail rounded shadow-sm" style="max-width: 180px; height: 180px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-light d-flex align-items-center justify-content-center border rounded mx-auto" style="width: 180px; height: 180px;">No Image</div>
                                <?php endif; ?>
                                <div class="mt-3">
                                    <span class="badge bg-<?= ($app_status === 'approved') ? 'success' : 'info'; ?> text-uppercase px-3 py-2 fs-6">
                                        Status: <?= htmlspecialchars($app_status); ?>
                                    </span>
                                </div>
                                <?php if (!empty($app_data['review_note'])): ?>
                                    <div class="alert alert-warning mt-3 text-start mb-0">
                                        <strong>Review note:</strong> <?= htmlspecialchars($app_data['review_note']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Personal Information Table -->
                            <div class="col-md-9">
                                <h5 class="text-secondary mb-3"><i class="bi bi-person me-2"></i>Personal Details Summary</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle">
                                        <tbody>
                                            <tr>
                                                <th class="bg-light" width="30%">Full Name</th>
                                                <td><?= htmlspecialchars($app_data['full_name'] ?? ''); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="bg-light">Father's Name</th>
                                                <td><?= htmlspecialchars($app_data['father_name'] ?? ''); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="bg-light">CNIC</th>
                                                <td><?= htmlspecialchars($app_data['cnic'] ?? ''); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="bg-light">Gender / Religion</th>
                                                <td><?= htmlspecialchars($app_data['gender'] ?? 'N/A') . ' | ' . htmlspecialchars($app_data['religion'] ?? 'N/A'); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Program & Contact Details -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <h5 class="text-secondary mb-3"><i class="bi bi-book me-2"></i>Program Choice</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle">
                                        <tbody>
                                            <tr>
                                                <th class="bg-light" width="40%">Selected Program</th>
                                                <td class="fw-bold text-primary"><?= htmlspecialchars($app_data['pref_1'] ?? $app_data['department'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="bg-light">Campus</th>
                                                <td><?= htmlspecialchars($app_data['campus'] ?? 'Main Campus Jamshoro'); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5 class="text-secondary mb-3"><i class="bi bi-geo-alt me-2"></i>Contact Details</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle">
                                        <tbody>
                                            <tr>
                                                <th class="bg-light" width="35%">Phone</th>
                                                <td><?= htmlspecialchars($app_data['phone'] ?? ''); ?></td>
                                            </tr>
                                            <tr>
                                                <th class="bg-light">Email</th>
                                                <td><?= htmlspecialchars($app_data['email'] ?? ''); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Uploaded Documents Links -->
                        <h5 class="mt-4 mb-3 text-secondary"><i class="bi bi-paperclip me-2"></i>Uploaded Documents</h5>
                        <div class="row g-3">
                            <?php
                            $docs = [
                                '10th Marksheet' => $app_data['doc_10th'] ?? '',
                                '12th Marksheet' => $app_data['doc_12th'] ?? '',
                                'B-Form / CNIC'  => $app_data['doc_bform'] ?? '',
                                'Domicile'       => $app_data['doc_domicile'] ?? ''
                            ];
                            foreach ($docs as $doc_label => $doc_path):
                            ?>
                                <div class="col-md-3 col-sm-6">
                                    <div class="card h-100 text-center p-3 border-0 bg-light shadow-sm">
                                        <i class="bi bi-file-earmark-check display-6 text-success mb-2"></i>
                                        <h6 class="card-title text-muted fs-6 mb-2"><?= $doc_label; ?></h6>
                                        <?php if (!empty($doc_path)): ?>
                                            <a href="<?= htmlspecialchars($doc_path); ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-auto">View File</a>
                                        <?php else: ?>
                                            <span class="text-danger small mt-auto">Not Uploaded</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    <?php else: ?>
                        <div class="alert alert-warning d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                            <div>
                                <strong>No Application Found!</strong> You have not submitted your application yet.
                                Please go to the <strong>Apply Online</strong> tab to complete your form.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 4. DOWNLOAD SLIP PANE -->
            <div class="tab-pane fade" id="download-slip" role="tabpanel">
                <div class="portal-card text-center py-5">
                    <i class="bi bi-file-earmark-pdf display-1 text-danger"></i>
                    <h3 class="mt-3">Entry Test Slip</h3>
                    <p class="text-muted mb-4">Your test slip will be enabled once your challan and application have been approved.</p>
                    <?php if ($can_download_slip): ?>
                        <a href="generate_slip.php" target="_blank" class="btn btn-danger btn-lg">Download PDF Slip</a>
                    <?php else: ?>
                        <a href="javascript:void(0)" class="btn btn-danger btn-lg disabled" aria-disabled="true">Download PDF Slip</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 5. TEST RESULT PANE -->
            <div class="tab-pane fade" id="test-result" role="tabpanel">
                <div class="portal-card text-center py-5">
                    <h2 class="text-secondary">Results Not Announced Yet</h2>
                    <p class="mb-0"><?= $app_status === 'approved' ? 'Your account is eligible for result updates. Check back after the entry test has been conducted.' : 'Check back after your application has been approved and the entry test has been conducted.'; ?></p>
                </div>
            </div>

        </div>
    </main>

    <footer class="site-footer">
        <div>
            <strong>Ahmer Institute of Technology (AIT)</strong><br>
            <span class="section-muted">Admissions portal for application, challan, slip, and result tracking.</span>
        </div>
        <div class="footer-links">
            <button type="button" class="footer-link" data-pane="dashboard">Dashboard</button>
            <button type="button" class="footer-link" data-pane="apply">Apply</button>
            <button type="button" class="footer-link" data-pane="view-application">Review</button>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script nonce="<?php echo htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        $(document).ready(function() {

            function showPane(paneId) {
                const tabTrigger = document.querySelector(`[data-bs-target="#${paneId}"]`);
                if (tabTrigger) {
                    bootstrap.Tab.getOrCreateInstance(tabTrigger).show();
                }
            }

            $('#sidebarToggle').on('click', function() {
                if ($(window).width() <= 991.98) {
                    $('body').toggleClass('sidebar-open');
                }
            });

            $('#sidebarBackdrop').on('click', function() {
                $('body').removeClass('sidebar-open');
            });

            $('.sidebar-item').on('click', function() {
                $('.sidebar-item').removeClass('active');
                const target = $(this).data('bsTarget') || $(this).attr('data-bs-target');
                if (target) {
                    $(`.sidebar-item[data-bs-target="${target}"]`).addClass('active');
                    showPane(target.replace('#', ''));
                }
                if ($(window).width() <= 991.98) {
                    $('body').removeClass('sidebar-open');
                }
            });

            $('.action-chip').on('click', function(e) {
                const target = $(this).data('bsTarget') || $(this).attr('data-bs-target');
                if (target) {
                    e.preventDefault();
                    showPane(target.replace('#', ''));
                }
            });

            $('.footer-link').on('click', function() {
                const paneId = $(this).data('pane');
                $('.footer-link').removeClass('is-active');
                $(this).addClass('is-active');
                showPane(paneId);
            });

            const initialPane = <?php echo json_encode($initial_pane); ?>;
            if (initialPane && initialPane !== 'dashboard') {
                const tabTrigger = document.querySelector(`[data-bs-target="#${initialPane}"]`);
                if (tabTrigger) {
                    bootstrap.Tab.getOrCreateInstance(tabTrigger).show();
                }
            }

            document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function(trigger) {
                trigger.addEventListener('shown.bs.tab', function(event) {
                    const paneTarget = event.target.getAttribute('data-bs-target');
                    $('.sidebar-item').removeClass('active');
                    $('.footer-link').removeClass('is-active');
                    if (paneTarget) {
                        $(`.sidebar-item[data-bs-target="${paneTarget}"]`).addClass('active');
                        $(`.footer-link[data-pane="${paneTarget.replace('#', '')}"]`).addClass('is-active');
                    }
                });
            });

            // CNIC Auto-Formatting
            $('.cnic-mask').on('input', function(e) {
                var x = e.target.value.replace(/\D/g, '').match(/(\d{0,5})(\d{0,7})(\d{0,1})/);
                e.target.value = !x[2] ? x[1] : x[1] + '-' + x[2] + (x[3] ? '-' + x[3] : '');
            });

            // File size validator
            $('.file-validate').on('change', function() {
                const maxAllowedSize = 5 * 1024 * 1024;
                if (this.files[0] && this.files[0].size > maxAllowedSize) {
                    alert("File size exceeds 5MB. Please upload a smaller file.");
                    this.value = '';
                }
            });

            const currentPane = <?php echo json_encode($initial_pane); ?>;
            if (currentPane) {
                const paneLinks = document.querySelectorAll('.sidebar-item');
                paneLinks.forEach((item) => {
                    if (item.getAttribute('data-bs-target') === '#' + currentPane) {
                        item.classList.add('active');
                    }
                });
            }
        });
    </script>
</body>

</html>