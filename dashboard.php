<?php
require_once __DIR__ . '/backend/session.php';
ait_start_secure_session();
if (!isset($_SESSION['student_id'])) {
    header("Location: login");
    exit();
}

require_once './backend/data.php';
require_once './backend/security.php';

$student_gate_stmt = $conn->prepare('SELECT student_code FROM students WHERE id = ? AND is_active = 1 LIMIT 1');
$student_gate_stmt->bind_param('i', $_SESSION['student_id']);
$student_gate_stmt->execute();
$student_gate = $student_gate_stmt->get_result()->fetch_assoc();
$student_gate_stmt->close();
if (!empty($student_gate['student_code'])) {
    header('Location: student-dashboard');
    exit;
}

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
    'none'             => 0,
    'applied'          => 35,
    'challan_uploaded' => 65,
    'approved'         => 100,
    'rejected'         => 35,
];
$application_progress = $progress_map[$app_status] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS | Ahmer Institute of Technology</title>
    <link rel="icon" type="image/x-icon" href="assets/images/favicon/ait.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/style.css">
    <style nonce="<?php echo htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        /* ================================================================
   DESIGN TOKENS
================================================================ */
        :root {
            --sw: 256px;
            /* sidebar width */
            --hh: 64px;
            /* header height */

            /* Teal accent */
            --teal: #14b8a6;
            --teal-dk: #0d9488;
            --teal-dim: rgba(20, 184, 166, 0.12);
            --teal-glow: rgba(20, 184, 166, 0.30);

            /* Glass layers */
            --g5: rgba(255, 255, 255, 0.05);
            --g8: rgba(255, 255, 255, 0.08);
            --g12: rgba(255, 255, 255, 0.12);
            --gb: rgba(255, 255, 255, 0.09);
            /* border */
            --gbs: rgba(255, 255, 255, 0.16);
            /* border strong */

            /* Text */
            --t1: #f1f5f9;
            --t2: rgba(241, 245, 249, 0.68);
            --t3: rgba(241, 245, 249, 0.42);
        }

        /* ================================================================
   BASE
================================================================ */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        * {
            scrollbar-width: thin;
            scrollbar-color: rgba(45, 212, 191, .65) rgba(255, 255, 255, .06);
        }

        *::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        *::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, .04);
        }

        *::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, rgba(45, 212, 191, .8), rgba(14, 116, 144, .8));
            border-radius: 99px;
            border: 1px solid rgba(255, 255, 255, .12);
        }

        html {
            height: 100%;
        }

        body {
            background: #04091a;
            font-family: 'Inter', sans-serif;
            color: var(--t1);
            overflow-x: hidden;
            min-height: 100%;
        }

        /* ================================================================
   BACKGROUND ORBS  (fixed, behind everything)
================================================================ */
        .bg-orbs {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(72px);
            opacity: 1;
        }

        .orb-1 {
            width: 720px;
            height: 720px;
            background: radial-gradient(circle, rgba(13, 148, 136, .32) 0%, transparent 65%);
            top: -260px;
            left: -160px;
        }

        .orb-2 {
            width: 580px;
            height: 580px;
            background: radial-gradient(circle, rgba(14, 116, 144, .28) 0%, transparent 65%);
            bottom: -120px;
            right: -80px;
        }

        .orb-3 {
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(79, 70, 229, .18) 0%, transparent 65%);
            top: 45%;
            left: 48%;
            transform: translate(-50%, -50%);
        }

        /* ================================================================
   NAVBAR
================================================================ */
        .custom-navbar {
            background: rgba(4, 10, 28, .88) !important;
            backdrop-filter: blur(24px) saturate(180%);
            -webkit-backdrop-filter: blur(24px) saturate(180%);
            border-bottom: 1px solid var(--gb);
            height: var(--hh);
            z-index: 1030;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
        }

        .navbar-inner {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1rem;
        }

        .brand-box {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .brand-logo {
            height: 34px;
            width: 34px;
            display: grid;
            place-items: center;
            border-radius: 9px;
            background: var(--g12);
            color: #fff;
            font: 700 13px "Space Grotesk", sans-serif;
        }

        .brand-text {
            color: #fff;
            font-weight: 700;
            font-size: 0.95rem;
            line-height: 1.2;
        }

        .brand-text small {
            display: block;
            font-size: 0.7rem;
            font-weight: 400;
            color: var(--t3);
            letter-spacing: 0.04em;
        }

        .nav-toggle {
            display: none;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            color: var(--t1);
            font-size: 1.2rem;
            cursor: pointer;
            border-radius: 9px;
            background: var(--g8);
            border: 1px solid var(--gb);
            transition: background .2s;
            margin-left: 0.5rem;
        }

        .nav-toggle:hover {
            background: var(--g12);
        }

        .user-area {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .user-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: var(--g8);
            border: 1px solid var(--gb);
            border-radius: 999px;
            padding: 0.38rem 0.75rem;
            font-size: 0.84rem;
            color: var(--t1);
            white-space: nowrap;
        }

        .logout-btn {
            color: var(--t2);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 9px;
            background: var(--g8);
            border: 1px solid var(--gb);
            transition: background .2s, color .2s, border-color .2s;
            font-size: 1rem;
        }

        .logout-btn:hover {
            background: rgba(239, 68, 68, .14);
            border-color: rgba(239, 68, 68, .3);
            color: #fca5a5;
        }

        /* ================================================================
   SIDEBAR
================================================================ */
        .sidebar {
            width: var(--sw);
            height: calc(100vh - var(--hh));
            position: fixed;
            top: var(--hh);
            left: 0;
            background: rgba(4, 10, 28, .84);
            backdrop-filter: blur(28px) saturate(200%);
            -webkit-backdrop-filter: blur(28px) saturate(200%);
            border-right: 1px solid var(--gb);
            display: flex;
            flex-direction: column;
            z-index: 1020;
            transition: transform .28s cubic-bezier(.4, 0, .2, 1);
            overflow: hidden;
        }

        .sidebar-top {
            padding: 1.25rem 1rem 0.5rem;
        }

        .sidebar-section-label {
            font-size: 0.66rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            color: var(--t3);
            padding: 0 0.6rem;
            display: block;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
        }

        .sidebar-nav {
            list-style: none;
            padding: 0 0.65rem;
            margin: 0;
            flex: 1;
            overflow-y: auto;
        }

        .sidebar-nav::-webkit-scrollbar {
            width: 3px;
        }

        .sidebar-nav::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar-nav::-webkit-scrollbar-thumb {
            background: var(--g12);
            border-radius: 99px;
        }

        .sidebar-item {
            margin: 2px 0;
        }

        .sidebar-item a {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            padding: 0.72rem 0.8rem;
            color: var(--t2);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 11px;
            border-left: 2px solid transparent;
            transition: all .18s ease;
            cursor: pointer;
        }

        .sidebar-item a:hover {
            background: var(--g8);
            color: var(--t1);
            border-left-color: rgba(20, 184, 166, .4);
        }

        .sidebar-item.active a {
            background: linear-gradient(90deg, rgba(20, 184, 166, .18) 0%, rgba(20, 184, 166, .05) 100%);
            border-left-color: var(--teal);
            color: #fff;
            font-weight: 600;
        }

        .sidebar-item.active .si-icon {
            color: var(--teal);
        }

        .si-icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
            background: var(--g5);
            transition: color .18s;
        }

        .sidebar-item.active .si-icon {
            background: var(--teal-dim);
        }

        .sidebar-divider {
            height: 1px;
            background: var(--gb);
            margin: 0.65rem 0.65rem;
        }

        .sidebar-footer {
            padding: 0.85rem 1rem;
            border-top: 1px solid var(--gb);
            display: flex;
            align-items: center;
            gap: 0.7rem;
            flex-shrink: 0;
        }

        .sf-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--teal-dk) 0%, #0e7490 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: #fff;
            flex-shrink: 0;
            box-shadow: 0 0 0 2px rgba(20, 184, 166, .25);
        }

        .sf-name {
            font-size: 0.83rem;
            font-weight: 600;
            color: var(--t1);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 155px;
        }

        .sf-role {
            font-size: 0.71rem;
            color: var(--t3);
        }

        /* ================================================================
   MAIN CONTENT
================================================================ */
        .main-content {
            margin-left: var(--sw);
            padding: 1.5rem;
            margin-top: var(--hh);
            min-height: calc(100vh - var(--hh));
            transition: margin-left .28s ease;
            position: relative;
            z-index: 1;
        }

        /* ================================================================
   GLASS CARD  (base surface)
================================================================ */
        .g-card {
            background: var(--g5);
            border: 1px solid var(--gb);
            border-radius: 20px;
            box-shadow: 0 24px 64px rgba(0, 0, 0, .38), inset 0 1px 0 rgba(255, 255, 255, .06);
            padding: 1.4rem;
            margin-bottom: 1.25rem;
            backdrop-filter: blur(20px) saturate(160%);
            -webkit-backdrop-filter: blur(20px) saturate(160%);
        }

        /* ================================================================
   DASHBOARD HERO
================================================================ */
        .dash-hero {
            background: linear-gradient(135deg, rgba(13, 148, 136, .28) 0%, rgba(14, 116, 144, .20) 100%);
            border: 1px solid rgba(20, 184, 166, .22);
            border-radius: 20px;
            padding: 1.5rem 1.6rem;
            margin-bottom: 1.25rem;
            backdrop-filter: blur(20px);
            position: relative;
            overflow: hidden;
        }

        .dash-hero::before {
            content: '';
            position: absolute;
            top: -80px;
            right: -80px;
            width: 240px;
            height: 240px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(20, 184, 166, .22) 0%, transparent 70%);
            pointer-events: none;
        }

        .dash-hero h3 {
            font-size: clamp(1.3rem, 2.5vw, 1.9rem);
            font-weight: 800;
            color: #fff;
            margin-bottom: 0.3rem;
        }

        .dash-hero p {
            color: rgba(255, 255, 255, .65);
            font-size: 0.875rem;
            margin-bottom: 0;
            max-width: 55ch;
        }

        /* ================================================================
   STATUS GRID
================================================================ */
        .status-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .sc {
            background: var(--g5);
            border: 1px solid var(--gb);
            border-radius: 16px;
            padding: 1rem 1.1rem 1rem;
            backdrop-filter: blur(16px);
            position: relative;
            overflow: hidden;
        }

        .sc::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            border-radius: 16px 16px 0 0;
        }

        .sc-teal::before {
            background: linear-gradient(90deg, #14b8a6, #0ea5e9);
        }

        .sc-amber::before {
            background: linear-gradient(90deg, #f59e0b, #d97706);
        }

        .sc-green::before {
            background: linear-gradient(90deg, #22c55e, #16a34a);
        }

        .sc-violet::before {
            background: linear-gradient(90deg, #818cf8, #6366f1);
        }

        .sc-icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            margin-bottom: 0.6rem;
        }

        .sc-teal .sc-icon {
            background: rgba(20, 184, 166, .13);
            color: #14b8a6;
        }

        .sc-amber .sc-icon {
            background: rgba(245, 158, 11, .13);
            color: #f59e0b;
        }

        .sc-green .sc-icon {
            background: rgba(34, 197, 94, .13);
            color: #22c55e;
        }

        .sc-violet.sc-icon {
            background: rgba(129, 140, 248, .13);
            color: #818cf8;
        }

        .sc-violet .sc-icon {
            background: rgba(129, 140, 248, .13);
            color: #818cf8;
        }

        .sc .sc-label {
            color: var(--t3);
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            margin-bottom: 0.18rem;
        }

        .sc .sc-value {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--t1);
            margin: 0;
        }

        /* ================================================================
   PROGRESS BAR
================================================================ */
        .prog-wrap {
            background: var(--g5);
            border: 1px solid var(--gb);
            border-radius: 16px;
            padding: 1rem 1.25rem;
            backdrop-filter: blur(16px);
            margin-bottom: 1.25rem;
        }

        .prog-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.65rem;
        }

        .prog-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--t2);
        }

        .prog-pct {
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--teal);
            background: var(--teal-dim);
            border: 1px solid rgba(20, 184, 166, .22);
            border-radius: 999px;
            padding: 0.18rem 0.65rem;
        }

        .prog-bar-rail {
            height: 7px;
            background: rgba(255, 255, 255, .07);
            border-radius: 999px;
            overflow: hidden;
        }

        .prog-bar-fill {
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #0d9488 0%, #14b8a6 50%, #0ea5e9 100%);
            box-shadow: 0 0 10px rgba(20, 184, 166, .55);
            transition: width .8s cubic-bezier(.4, 0, .2, 1);
        }

        /* ================================================================
   NEXT STEP CARD
================================================================ */
        .next-card {
            background: var(--g5);
            border: 1px solid var(--gb);
            border-radius: 18px;
            padding: 1.25rem;
            backdrop-filter: blur(16px);
            margin-bottom: 1.25rem;
        }

        .next-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }

        .next-card-head h5 {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--t1);
            margin: 0;
        }

        .status-badge {
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 0.28rem 0.7rem;
            border-radius: 999px;
            background: var(--g8);
            border: 1px solid var(--gbs);
            color: var(--t2);
        }

        .next-card p {
            color: var(--t2);
            font-size: 0.865rem;
            margin-bottom: 1rem;
            line-height: 1.55;
        }

        /* Glass alerts */
        .g-alert {
            border-radius: 12px;
            padding: 0.8rem 1rem;
            font-size: 0.855rem;
            border: 1px solid;
            line-height: 1.5;
        }

        .g-alert strong {
            font-weight: 700;
        }

        .g-warn {
            background: rgba(245, 158, 11, .10);
            border-color: rgba(245, 158, 11, .28);
            color: #fde68a;
        }

        .g-warn strong {
            color: #fcd34d;
        }

        .g-info {
            background: rgba(14, 165, 233, .10);
            border-color: rgba(14, 165, 233, .28);
            color: #bae6fd;
        }

        .g-info strong {
            color: #7dd3fc;
        }

        .g-ok {
            background: rgba(34, 197, 94, .10);
            border-color: rgba(34, 197, 94, .28);
            color: #bbf7d0;
        }

        .g-ok strong {
            color: #86efac;
        }

        /* Challan upload sub-card */
        .challan-box {
            background: var(--g8);
            border: 1px solid var(--gbs);
            border-radius: 14px;
            padding: 1.1rem;
            margin-top: 1rem;
        }

        .challan-box h6 {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--t1);
            margin-bottom: 0.75rem;
        }

        /* ================================================================
   ACTION CHIPS / BUTTONS
================================================================ */
        .action-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.58rem 1rem;
            border-radius: 10px;
            background: linear-gradient(135deg, #0d9488 0%, #0e7490 100%);
            color: #fff;
            font-weight: 600;
            font-size: 0.855rem;
            text-decoration: none;
            border: 1px solid rgba(20, 184, 166, .28);
            box-shadow: 0 8px 22px rgba(13, 148, 136, .28);
            transition: all .2s ease;
            cursor: pointer;
        }

        .action-chip:hover {
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 12px 28px rgba(13, 148, 136, .38);
            filter: brightness(1.05);
        }

        .action-chip.disabled {
            background: var(--g8);
            border-color: var(--gb);
            color: var(--t3);
            box-shadow: none;
            pointer-events: none;
        }

        /* Buttons inside cards */
        .btn {
            border-radius: 10px !important;
            font-weight: 600;
            font-size: 0.865rem;
            padding: 0.55rem 1.1rem;
            transition: all .2s ease;
            border: 1px solid transparent;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-lg {
            padding: 0.75rem 1.5rem !important;
            font-size: 0.9rem !important;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0d9488, #0e7490) !important;
            border-color: rgba(20, 184, 166, .3) !important;
            box-shadow: 0 8px 20px rgba(13, 148, 136, .28);
            color: #fff !important;
        }

        .btn-success {
            background: linear-gradient(135deg, #15803d, #16a34a) !important;
            border-color: rgba(34, 197, 94, .3) !important;
            box-shadow: 0 8px 20px rgba(22, 163, 74, .25);
            color: #fff !important;
        }

        .btn-danger {
            background: linear-gradient(135deg, #b91c1c, #dc2626) !important;
            border-color: rgba(239, 68, 68, .3) !important;
            box-shadow: 0 8px 20px rgba(220, 38, 38, .25);
            color: #fff !important;
        }

        .btn-outline-secondary {
            background: var(--g8) !important;
            color: var(--t2) !important;
            border-color: var(--gbs) !important;
        }

        .btn-outline-secondary:hover {
            background: var(--g12) !important;
            color: var(--t1) !important;
        }

        .btn-sm {
            padding: 0.38rem 0.75rem !important;
            font-size: 0.8rem !important;
        }

        /* ================================================================
   FORM SECTIONS (Apply pane)
================================================================ */
        .form-section-title {
            border-bottom: 1px solid rgba(20, 184, 166, .25);
            padding-bottom: 0.6rem;
            margin-bottom: 1.25rem;
            color: var(--teal);
            font-weight: 700;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-block-card {
            border: 1px solid var(--gb);
            border-radius: 16px;
            margin-bottom: 1.1rem;
            background: var(--g5);
            backdrop-filter: blur(16px);
            overflow: hidden;
        }

        .form-block-header {
            background: var(--g8);
            border-bottom: 1px solid var(--gb);
            padding: 0.8rem 1.1rem;
            font-weight: 700;
            font-size: 0.855rem;
            color: var(--teal);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-block-body {
            padding: 1.1rem 1.25rem;
        }

        /* Dark form controls */
        .form-control,
        .form-select {
            background: rgba(255, 255, 255, .07) !important;
            border: 1px solid var(--gbs) !important;
            color: var(--t1) !important;
            border-radius: 9px !important;
            font-size: 0.855rem;
            padding: 0.52rem 0.85rem;
            transition: border-color .2s, background .2s, box-shadow .2s;
        }

        .form-control:focus,
        .form-select:focus {
            background: rgba(255, 255, 255, .10) !important;
            border-color: rgba(20, 184, 166, .55) !important;
            box-shadow: 0 0 0 3px rgba(20, 184, 166, .12) !important;
            color: var(--t1) !important;
            outline: none;
        }

        .form-control::placeholder {
            color: var(--t3) !important;
        }

        .form-select option {
            background: #0d1b2e;
            color: var(--t1);
        }

        .form-label {
            color: var(--t2);
            font-size: 0.82rem;
            font-weight: 500;
            margin-bottom: 0.3rem;
        }

        .form-control.file-validate {
            padding: 0.42rem 0.85rem;
        }

        .file-validate {
            cursor: pointer;
            border-style: dashed !important;
        }

        .file-validate:hover {
            border-color: var(--teal) !important;
            background: rgba(20, 184, 166, .12) !important;
        }

        .file-validate::file-selector-button {
            margin: -.42rem .85rem -.42rem -.85rem;
            padding: .58rem 1rem;
            border: 0;
            border-right: 1px solid rgba(45, 212, 191, .24);
            background: rgba(20, 184, 166, .14);
            color: #99f6e4;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s ease, color .2s ease;
        }

        .file-validate:hover::file-selector-button {
            background: rgba(20, 184, 166, .28);
            color: #fff;
        }

        .file-control-row {
            display: flex;
            align-items: stretch;
            gap: .5rem;
        }

        .file-control-row .file-validate {
            min-width: 0;
            flex: 1;
        }

        .file-remove {
            flex: 0 0 auto;
            border: 1px solid rgba(248, 113, 113, .35);
            border-radius: 8px;
            background: rgba(248, 113, 113, .1);
            color: #fca5a5;
            padding: 0 .75rem;
        }

        .file-remove:hover {
            background: rgba(248, 113, 113, .22);
            color: #fff;
        }

        .upload-status {
            display: none;
            margin-top: .75rem;
            padding: .8rem 1rem;
            border: 1px solid rgba(20, 184, 166, .22);
            border-radius: 12px;
            background: rgba(20, 184, 166, .08);
        }

        .upload-status.is-visible {
            display: block;
        }

        .upload-status-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            color: var(--t2);
            font-size: .78rem;
            margin-bottom: .45rem;
        }

        .upload-progress {
            height: 5px;
            overflow: hidden;
            border-radius: 999px;
            background: rgba(255, 255, 255, .08);
        }

        .upload-progress-bar {
            width: 0;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #0d9488, #2dd4bf, #38bdf8);
            transition: width .18s ease;
        }

        .map-frame {
            width: 100%;
            height: 210px;
            border: 1px solid var(--gb);
            border-radius: 14px;
            margin-top: 1rem;
            filter: saturate(.8) contrast(1.05);
        }

        small,
        .small {
            font-size: 0.78rem;
        }

        .text-muted {
            color: var(--t3) !important;
        }

        /* Form subsection headers */
        .form-block-body h6.text-primary {
            color: #5eead4 !important;
        }

        .form-block-body .text-primary {
            color: #5eead4 !important;
        }

        hr {
            border-color: var(--gb) !important;
            opacity: 1;
        }

        /* ================================================================
   TABLES  (View application)
================================================================ */
        .table {
            color: var(--t1) !important;
            --bs-table-bg: transparent;
            --bs-table-border-color: var(--gb);
            font-size: 0.865rem;
            margin-bottom: 0;
        }

        .table th.bg-light,
        .table th {
            background: var(--g8) !important;
            color: var(--t3) !important;
            font-weight: 600;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            border-color: var(--gb) !important;
        }

        .table td {
            color: var(--t1) !important;
            border-color: var(--gb) !important;
            vertical-align: middle;
        }

        .table-bordered {
            border-color: var(--gb) !important;
        }

        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--gb);
        }

        /* Badges */
        .badge {
            border-radius: 8px;
            font-size: 0.76rem;
            padding: 0.3rem 0.65rem;
            font-weight: 700;
        }

        .bg-success {
            background: rgba(22, 163, 74, .22) !important;
            color: #86efac !important;
            border: 1px solid rgba(34, 197, 94, .3);
        }

        .bg-info {
            background: rgba(14, 165, 233, .18) !important;
            color: #7dd3fc !important;
            border: 1px solid rgba(14, 165, 233, .3);
        }

        /* ================================================================
   SAMPLE PHOTO SECTION
================================================================ */
        .sample-photo-note {
            background: rgba(20, 184, 166, .08);
            border: 1px solid rgba(20, 184, 166, .22);
            border-radius: 12px;
            padding: 0.7rem 1rem;
            margin: 0.75rem 0 0.85rem;
            color: var(--t2);
            font-size: 0.845rem;
        }

        .sample-photo-note strong {
            color: var(--teal);
        }

        .sample-photo-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.75rem;
        }

        .sample-photo-card {
            border-radius: 13px;
            overflow: hidden;
            border: 1px solid var(--gbs);
            background: var(--g8);
        }

        .sample-photo-card.accepted {
            border-color: rgba(22, 163, 74, .35);
        }

        .sample-photo-card.rejected {
            border-color: rgba(239, 68, 68, .3);
        }

        .sample-placeholder {
            width: 100%;
            height: 148px;
            display: grid;
            place-items: center;
            font-size: 2.2rem;
            color: var(--muted, #7c8b9a);
            background: var(--g10, rgba(148, 163, 184, .08));
        }

        .sample-photo-card .image-wrap {
            position: relative;
        }

        .sample-badge {
            position: absolute;
            top: 0.55rem;
            left: 0.55rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.28rem 0.55rem;
            border-radius: 999px;
            font-size: 0.66rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            color: #fff;
        }

        .sample-photo-card.accepted .sample-badge {
            background: linear-gradient(135deg, #16a34a, #0d9488);
        }

        .sample-photo-card.rejected .sample-badge {
            background: linear-gradient(135deg, #ef4444, #b91c1c);
        }

        .sample-photo-card .sample-caption {
            padding: 0.6rem 0.7rem 0.75rem;
        }

        .sample-photo-card .sample-caption h6 {
            font-size: 0.82rem;
            font-weight: 700;
            margin-bottom: 0.22rem;
            color: var(--t1);
        }

        .sample-photo-card .sample-caption p {
            margin: 0;
            font-size: 0.76rem;
            color: var(--t3);
            line-height: 1.4;
        }

        /* ================================================================
   VIEW APPLICATION — doc cards
================================================================ */
        .doc-card {
            background: var(--g8);
            border: 1px solid var(--gbs);
            border-radius: 13px;
            padding: 1rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        .doc-card .doc-icon {
            font-size: 2rem;
            color: var(--teal);
        }

        .doc-card .doc-label {
            font-size: 0.8rem;
            color: var(--t2);
            font-weight: 600;
        }

        /* Profile photo placeholder */
        .profile-placeholder {
            width: 160px;
            height: 160px;
            border-radius: 16px;
            background: var(--g8);
            border: 1px solid var(--gbs);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--t3);
            font-size: 0.82rem;
            margin: 0 auto;
        }

        .profile-img-wrap {
            width: 160px;
            margin: 0 auto;
        }

        .profile-img-wrap img {
            width: 100%;
            height: 160px;
            object-fit: cover;
            border-radius: 16px;
            border: 1px solid var(--gbs);
        }

        /* Text helpers */
        .t1 {
            color: var(--t1) !important;
        }

        .t2 {
            color: var(--t2) !important;
        }

        .t3 {
            color: var(--t3) !important;
        }

        .text-teal {
            color: var(--teal) !important;
        }

        h2,
        h3,
        h4,
        h5,
        h6,
        .h2,
        .h3,
        .h4,
        .h5,
        .h6 {
            color: var(--t1);
        }

        .text-primary {
            color: #5eead4 !important;
        }

        .text-secondary {
            color: var(--t2) !important;
        }

        /* ================================================================
   DOWNLOAD SLIP / TEST RESULT
================================================================ */
        .center-pane {
            min-height: 340px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            text-align: center;
        }

        .center-pane .icon-ring {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--g8);
            border: 1px solid var(--gbs);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 0.25rem;
        }

        .center-pane h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--t1);
            margin: 0;
        }

        .center-pane p {
            color: var(--t2);
            font-size: 0.875rem;
            margin: 0;
            max-width: 45ch;
        }

        /* ================================================================
   FOOTER
================================================================ */
        .site-footer {
            margin-left: var(--sw);
            background: rgba(4, 10, 28, .88);
            border-top: 1px solid var(--gb);
            backdrop-filter: blur(20px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.75rem 1.5rem;
            font-size: 0.8rem;
            color: var(--t3);
            transition: margin-left .28s ease;
            position: relative;
            z-index: 1;
        }

        .footer-links {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
        }

        .footer-link {
            border: 1px solid var(--gb);
            border-radius: 8px;
            padding: 0.38rem 0.75rem;
            background: var(--g5);
            color: var(--t2);
            font-weight: 600;
            font-size: 0.8rem;
            transition: all .18s;
            cursor: pointer;
        }

        .footer-link:hover {
            background: var(--g12);
            color: var(--t1);
            border-color: var(--gbs);
        }

        .footer-link.is-active {
            background: var(--teal-dim);
            color: var(--teal);
            border-color: rgba(20, 184, 166, .3);
        }

        /* ================================================================
   SIDEBAR BACKDROP
================================================================ */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: var(--hh) 0 0 0;
            background: rgba(4, 10, 28, .65);
            backdrop-filter: blur(4px);
            opacity: 0;
            pointer-events: none;
            transition: opacity .2s ease;
            z-index: 1015;
        }

        .sidebar-open .sidebar-backdrop {
            opacity: 1;
            pointer-events: auto;
        }

        /* Alert overrides */
        .alert-warning {
            background: rgba(245, 158, 11, .11) !important;
            border-color: rgba(245, 158, 11, .3) !important;
            color: #fde68a !important;
            border-radius: 12px !important;
        }

        .alert-warning strong {
            color: #fcd34d;
        }

        .alert-info {
            background: rgba(14, 165, 233, .11) !important;
            border-color: rgba(14, 165, 233, .3) !important;
            color: #bae6fd !important;
            border-radius: 12px !important;
        }

        .alert-info strong {
            color: #7dd3fc;
        }

        .alert-success {
            background: rgba(34, 197, 94, .11) !important;
            border-color: rgba(34, 197, 94, .3) !important;
            color: #bbf7d0 !important;
            border-radius: 12px !important;
        }

        .alert-success strong {
            color: #86efac;
        }

        /* CNIC mask */
        /* ================================================================
   RESPONSIVE
================================================================ */
        .sidebar-collapsed .sidebar {
            transform: translateX(-100%);
        }

        .sidebar-collapsed .main-content,
        .sidebar-collapsed .site-footer {
            margin-left: 0;
        }

        @media (max-width: 991.98px) {
            .nav-toggle {
                display: inline-flex;
            }

            .user-chip span:last-child {
                display: none;
            }

            .sidebar {
                transform: translateX(-100%);
                width: min(82vw, var(--sw));
            }

            .sidebar-open .sidebar {
                transform: translateX(0);
            }

            .sidebar-backdrop {
                display: block;
            }

            .main-content,
            .site-footer {
                margin-left: 0;
            }

            .status-grid {
                grid-template-columns: 1fr 1fr;
            }

            .sample-photo-grid {
                grid-template-columns: 1fr 1fr;
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

            .status-grid {
                grid-template-columns: 1fr 1fr;
            }

            .sample-photo-grid {
                grid-template-columns: 1fr 1fr;
            }

            .action-chip {
                justify-content: center;
            }

            .footer-links {
                width: 100%;
            }

            .brand-text small {
                display: none;
            }
        }
    </style>
    <script src="./assets/js/theme.js"></script>
</head>

<body>

    <!-- Background depth orbs -->
    <div class="bg-orbs" aria-hidden="true">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    <!-- ================================================================
     NAVBAR
================================================================ -->
    <nav class="custom-navbar" aria-label="Top navigation">
        <div class="navbar-inner">
            <div class="brand-box">
                <span class="brand-logo">AIT</span>
                <span class="brand-text">
                    Ahmer Institute of Technology
                    <small>Candidate Portal</small>
                </span>
                <div class="nav-toggle ms-2" id="sidebarToggle" aria-label="Toggle navigation" role="button" tabindex="0">
                    <i class="bi bi-list"></i>
                </div>
            </div>
            <div class="user-area">
                <div class="user-chip">
                    <i class="bi bi-person-circle"></i>
                    <span><?= htmlspecialchars($_SESSION['student_name'] ?? 'Candidate'); ?></span>
                </div>
            </div>
        </div>
    </nav>

    <!-- ================================================================
     SIDEBAR
================================================================ -->
    <div class="sidebar" id="sidebarMenu">
        <div class="sidebar-top">
            <span class="sidebar-section-label">Portal</span>
        </div>

        <ul class="sidebar-nav" id="nav-tabs" role="tablist">
            <li class="sidebar-item active" data-bs-target="#dashboard" role="tab">
                <a>
                    <span class="si-icon"><i class="bi bi-grid-fill"></i></span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-item" data-bs-target="#apply" role="tab">
                <a>
                    <span class="si-icon"><i class="bi bi-pencil-square"></i></span>
                    <span>Apply Online</span>
                </a>
            </li>
            <li class="sidebar-item" data-bs-target="#view-application" role="tab">
                <a>
                    <span class="si-icon"><i class="bi bi-file-earmark-text"></i></span>
                    <span>View Application</span>
                </a>
            </li>

            <div class="sidebar-divider"></div>

            <li class="sidebar-item" data-bs-target="#download-slip" role="tab">
                <a>
                    <span class="si-icon"><i class="bi bi-ticket-perforated"></i></span>
                    <span>Test Slip</span>
                </a>
            </li>
            <li class="sidebar-item" data-bs-target="#test-result" role="tab">
                <a>
                    <span class="si-icon"><i class="bi bi-bar-chart-line"></i></span>
                    <span>Test Result</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <div class="sf-avatar"><i class="bi bi-person-fill"></i></div>
            <div style="overflow:hidden;flex:1;">
                <div class="sf-name"><?= htmlspecialchars($_SESSION['student_name'] ?? 'Candidate'); ?></div>
                <div class="sf-role">Applicant</div>
            </div>
            <form method="post" action="logout.php" class="d-inline">
                <?php echo ait_csrf_field(); ?>
                <button type="submit" class="logout-btn" style="width:30px;height:30px;font-size:.85rem;" aria-label="Log out">
                    <i class="bi bi-power"></i>
                </button>
            </form>
        </div>
    </div>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- ================================================================
     MAIN CONTENT
================================================================ -->
    <main class="main-content">
        <div class="tab-content">

            <!-- 1. DASHBOARD -->
            <div class="tab-pane fade show active" id="dashboard" role="tabpanel">

                <!-- Hero -->
                <div class="dash-hero">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <h3>Welcome, <?= htmlspecialchars($_SESSION['student_name'] ?? 'Candidate'); ?></h3>
                            <p>Manage your admission — application, challan, test slip, and result in one place.</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <a class="action-chip" href="#apply" data-bs-target="#apply">
                                <i class="bi bi-pencil-square"></i> Apply Online
                            </a>
                            <a class="action-chip <?= $has_application ? '' : 'disabled'; ?>"
                                href="#view-application" data-bs-target="#view-application">
                                <i class="bi bi-file-earmark-text"></i> View Submission
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Status grid -->
                <div class="status-grid">
                    <div class="sc sc-teal">
                        <div class="sc-icon"><i class="bi bi-file-earmark-check"></i></div>
                        <div class="sc-label">Application</div>
                        <p class="sc-value"><?= $has_application ? 'Submitted' : 'Not started'; ?></p>
                    </div>
                    <div class="sc sc-amber">
                        <div class="sc-icon"><i class="bi bi-receipt"></i></div>
                        <div class="sc-label">Challan</div>
                        <p class="sc-value"><?= $app_status === 'challan_uploaded' ? 'Uploaded' : ($has_application ? 'Pending' : 'Locked'); ?></p>
                    </div>
                    <div class="sc sc-green">
                        <div class="sc-icon"><i class="bi bi-ticket-perforated"></i></div>
                        <div class="sc-label">Test Slip</div>
                        <p class="sc-value"><?= $can_download_slip ? 'Ready' : 'Awaiting'; ?></p>
                    </div>
                    <div class="sc sc-violet">
                        <div class="sc-icon"><i class="bi bi-bar-chart-line"></i></div>
                        <div class="sc-label">Result</div>
                        <p class="sc-value"><?= $app_status === 'approved' ? 'Coming soon' : 'Pending'; ?></p>
                    </div>
                </div>

                <!-- Progress -->
                <div class="prog-wrap">
                    <div class="prog-header">
                        <span class="prog-title">Application Progress</span>
                        <span class="prog-pct"><?= $application_progress; ?>%</span>
                    </div>
                    <div class="prog-bar-rail">
                        <div class="prog-bar-fill" style="width: <?= $application_progress; ?>%"
                            role="progressbar" aria-valuenow="<?= $application_progress; ?>"
                            aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>

                <!-- Next step -->
                <div class="next-card">
                    <div class="next-card-head">
                        <h5><i class="bi bi-arrow-right-circle-fill text-teal me-1"></i> Next Step</h5>
                        <span class="status-badge"><?= htmlspecialchars(str_replace('_', ' ', $app_status)); ?></span>
                    </div>

                    <?php if (!$has_application): ?>
                        <p>Your account is ready. Submit the application form to get started.</p>
                        <div class="g-alert g-warn">
                            Open <strong>Apply Online</strong> in the sidebar to complete your profile and upload required documents.
                        </div>

                    <?php elseif ($app_status === 'applied'): ?>
                        <p>Application received and waiting for super-admin approval. Your fee challan will appear here after approval.</p>
                        <div class="g-alert g-info">
                            Keep your application details ready. You will be notified in this dashboard when the challan is available.
                        </div>
                        <?php if (!empty($app_data['review_note'])): ?>
                            <div class="g-alert g-warn mt-3">
                                <strong>Correction note:</strong> <?= htmlspecialchars($app_data['review_note']); ?>
                            </div>
                        <?php endif; ?>

                    <?php elseif ($app_status === 'challan_uploaded'): ?>
                        <p>Challan uploaded — under verification. No action needed right now.</p>
                        <div class="g-alert g-info">
                            Once verified, the <strong>Test Slip</strong> tab will become available.
                        </div>

                    <?php elseif ($app_status === 'approved'): ?>
                        <p>Approved! Download your premium fee challan, pay it at the listed bank, and upload the stamped receipt below.</p>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="generate_challan.php" target="_blank" class="action-chip">
                                <i class="bi bi-receipt"></i> Download Fee Challan
                            </a>
                            <a href="#download-slip" class="action-chip" data-bs-target="#download-slip">
                                <i class="bi bi-download"></i> Get Test Slip
                            </a>
                            <a href="#test-result" class="action-chip" data-bs-target="#test-result">
                                <i class="bi bi-bar-chart-line"></i> Check Result
                            </a>
                        </div>
                        <div class="challan-box mt-3">
                            <h6><i class="bi bi-upload me-1"></i> Upload Paid Challan</h6>
                            <form action="upload_challan.php" method="POST" enctype="multipart/form-data">
                                <?php echo ait_csrf_field(); ?>
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-8">
                                        <label class="form-label">Stamped challan image (maximum 5 MB)</label>
                                        <input type="file" name="challan_pic" class="form-control file-validate" accept="image/*" required>
                                    </div>
                                    <div class="col-md-4 d-grid">
                                        <button type="submit" class="btn btn-success">Submit Paid Challan</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <?php if (!empty($app_data['review_note'])): ?>
                            <div class="g-alert g-ok mt-3">
                                <strong>Admin note:</strong> <?= htmlspecialchars($app_data['review_note']); ?>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <p>Your record is under review. Check back for updates.</p>
                        <?php if (!empty($app_data['review_note'])): ?>
                            <div class="g-alert g-warn">
                                <strong>Admin note:</strong> <?= htmlspecialchars($app_data['review_note']); ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

            </div><!-- /dashboard -->

            <!-- 2. APPLY -->
            <div class="tab-pane fade" id="apply" role="tabpanel">
                <div class="g-card">
                    <h2 class="form-section-title"><i class="bi bi-journal-check"></i> Admission Application</h2>

                    <?php if ($app_status !== 'none'): ?>
                        <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
                            <i class="bi bi-check-circle-fill fs-4"></i>
                            <div>Application submitted. Review it in the <strong>View Application</strong> tab.</div>
                        </div>
                    <?php else: ?>
                        <form action="submit_application.php" method="POST" enctype="multipart/form-data" id="applicationForm">

                            <!-- 1. Personal -->
                            <div class="form-block-card">
                                <div class="form-block-header"><i class="bi bi-person-vcard"></i> 1. Personal Information</div>
                                <div class="form-block-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Full Name (as per CNIC/B-Form) *</label>
                                            <input type="text" class="form-control" name="full_name" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Father's / Guardian's Name *</label>
                                            <input type="text" class="form-control" name="father_name" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">CNIC or B-Form Number *</label>
                                            <input type="text" class="form-control cnic-mask" name="cnic" placeholder="XXXXX-XXXXXXX-X" maxlength="15" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Father's / Guardian's CNIC *</label>
                                            <input type="text" class="form-control cnic-mask" name="father_cnic" placeholder="XXXXX-XXXXXXX-X" maxlength="15" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Date of Birth *</label>
                                            <input type="date" class="form-control" name="dob" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Gender *</label>
                                            <select class="form-select" name="gender" required>
                                                <option value="" selected disabled>Select</option>
                                                <option>Male</option>
                                                <option>Female</option>
                                                <option>Other</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Religion</label>
                                            <input type="text" class="form-control" name="religion" placeholder="e.g. Islam">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Nationality *</label>
                                            <input type="text" class="form-control" name="nationality" value="Pakistani" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Domicile / Province *</label>
                                            <select class="form-select" name="domicile_province" required>
                                                <option value="" selected disabled>Select Province</option>
                                                <option>Sindh (Urban)</option>
                                                <option>Sindh (Rural)</option>
                                                <option>Punjab</option>
                                                <option>Balochistan</option>
                                                <option>Khyber Pakhtunkhwa</option>
                                                <option>Gilgit Baltistan</option>
                                                <option value="AJK">Azad Jammu &amp; Kashmir</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">District &amp; City *</label>
                                            <input type="text" class="form-control" name="district_city" placeholder="e.g. Hyderabad" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Contact -->
                            <div class="form-block-card">
                                <div class="form-block-header"><i class="bi bi-telephone"></i> 2. Contact Information</div>
                                <div class="form-block-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Mobile Number *</label>
                                            <input type="tel" class="form-control" name="phone" placeholder="03XXXXXXXXX" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Alternate Number</label>
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
                                        <div class="col-12">
                                            <iframe class="map-frame" title="Ahmer Institute of Technology location map" loading="lazy" src="https://www.openstreetmap.org/export/embed.html?bbox=68.2%2C25.3%2C68.5%2C25.5&amp;layer=mapnik"></iframe>
                                            <small class="text-muted d-block mt-2"><i class="bi bi-geo-alt me-1"></i>Use the map to confirm your campus area, then enter your complete address above.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. Academic -->
                            <div class="form-block-card">
                                <div class="form-block-header"><i class="bi bi-mortarboard"></i> 3. Academic Details</div>
                                <div class="form-block-body">
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
                                            <label class="form-label">Reg. No</label>
                                            <input type="text" class="form-control" name="matric_reg">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Passing Year *</label>
                                            <input type="number" class="form-control" name="matric_year" placeholder="YYYY" min="1990" max="2026" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Group *</label>
                                            <select class="form-select" name="matric_group" required>
                                                <option>Science</option>
                                                <option>Arts</option>
                                                <option>O-Levels</option>
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

                                    <hr>

                                    <h6 class="text-primary fw-bold mb-3 mt-4"><i class="bi bi-journal-bookmark-fill me-1"></i> Intermediate / HSSC / A-Levels / DAE</h6>
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
                                            <label class="form-label">Reg. No</label>
                                            <input type="text" class="form-control" name="inter_reg">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Passing Year *</label>
                                            <input type="number" class="form-control" name="inter_year" placeholder="YYYY" min="1990" max="2026" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Group *</label>
                                            <select class="form-select" name="inter_group" required>
                                                <option>Pre-Engineering</option>
                                                <option>Pre-Medical</option>
                                                <option>ICS</option>
                                                <option>Commerce</option>
                                                <option>A-Levels</option>
                                                <option>DAE</option>
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

                            <!-- 4. Program -->
                            <div class="form-block-card">
                                <div class="form-block-header"><i class="bi bi-building-check"></i> 4. Admission Program</div>
                                <div class="form-block-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Campus *</label>
                                            <select class="form-select" name="campus" required>
                                                <option value="" selected disabled>Select Campus</option>
                                                <option value="Main Campus Jamshoro">Main Campus Jamshoro</option>
                                                <option value="SZAB Campus Khairpur">SZAB Campus Khairpur</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Faculty *</label>
                                            <select class="form-select" name="faculty" required>
                                                <option value="" selected disabled>Select Faculty</option>
                                                <option value="Electrical, Electronic & Computer Engineering">Electrical, Electronic &amp; Computer Systems</option>
                                                <option value="Civil & Architecture">Civil &amp; Architecture</option>
                                                <option value="Mechanical & Process Engineering">Mechanical &amp; Process Engineering</option>
                                                <option value="Basic Sciences & Humanities">Basic Sciences &amp; Humanities</option>
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
                                            <label class="form-label">Shift *</label>
                                            <select class="form-select" name="shift" required>
                                                <option>Morning</option>
                                                <option value="Evening">Evening / Self-Finance</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">1st Choice Program *</label>
                                            <select class="form-select" name="pref_1" required>
                                                <option value="" selected disabled>Choose Major…</option>
                                                <option>Software Engineering</option>
                                                <option>Computer Systems Engineering</option>
                                                <option>Artificial Intelligence</option>
                                                <option>Civil Engineering</option>
                                                <option>Electrical Engineering</option>
                                                <option>Mechanical Engineering</option>
                                                <option>Cyber Security</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">2nd Choice Program</label>
                                            <select class="form-select" name="pref_2">
                                                <option value="" selected disabled>Choose Major…</option>
                                                <option>Software Engineering</option>
                                                <option>Computer Systems Engineering</option>
                                                <option>Artificial Intelligence</option>
                                                <option>Civil Engineering</option>
                                                <option>Electrical Engineering</option>
                                                <option>Mechanical Engineering</option>
                                                <option>Cyber Security</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">3rd Choice Program</label>
                                            <select class="form-select" name="pref_3">
                                                <option value="" selected disabled>Choose Major…</option>
                                                <option>Software Engineering</option>
                                                <option>Computer Systems Engineering</option>
                                                <option>Artificial Intelligence</option>
                                                <option>Civil Engineering</option>
                                                <option>Electrical Engineering</option>
                                                <option>Mechanical Engineering</option>
                                                <option>Cyber Security</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 5. Entry Test -->
                            <div class="form-block-card">
                                <div class="form-block-header"><i class="bi bi-card-checklist"></i> 5. Entry Test</div>
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

                            <!-- 6. Documents -->
                            <div class="form-block-card">
                                <div class="form-block-header"><i class="bi bi-file-earmark-arrow-up"></i> 6. Required Documents <span style="font-weight:400;opacity:.7;font-size:.8rem;">(auto-compress &gt;1 MB · max 5 MB each)</span></div>
                                <div class="form-block-body">
                                    <?php echo ait_csrf_field(); ?>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Passport-size Photograph *</label>
                                            <input type="file" class="form-control file-validate" name="profile_pic" accept=".webp,.jpg,.jpeg,.png" data-label="Passport photograph" required>
                                            <div class="upload-status">
                                                <div class="upload-status-row"><span class="upload-status-text">Ready to process</span><strong class="upload-status-percent">0%</strong></div>
                                                <div class="upload-progress">
                                                    <div class="upload-progress-bar"></div>
                                                </div>
                                            </div>
                                            <small class="text-muted mt-1 d-block">Blue or white background only</small>
                                        </div>
                                        <div class="col-12">
                                            <div class="sample-photo-note">
                                                <strong>Photo guide:</strong> Only Sample 1 is accepted. Samples 2–4 show rejectable formats.
                                            </div>
                                            <div class="sample-photo-grid">
                                                <div class="sample-photo-card accepted">
                                                    <div class="image-wrap">
                                                        <span class="sample-badge"><i class="bi bi-check-circle-fill"></i> Accepted</span>
                                                        <div class="sample-placeholder"><i class="bi bi-person-badge"></i></div>
                                                    </div>
                                                    <div class="sample-caption">
                                                        <h6>Sample 1</h6>
                                                        <p>Clear front-facing, plain background, full face visible.</p>
                                                    </div>
                                                </div>
                                                <div class="sample-photo-card rejected">
                                                    <div class="image-wrap">
                                                        <span class="sample-badge"><i class="bi bi-x-circle-fill"></i> Rejected</span>
                                                        <div class="sample-placeholder"><i class="bi bi-crop"></i></div>
                                                    </div>
                                                    <div class="sample-caption">
                                                        <h6>Sample 2</h6>
                                                        <p>Wrong pose, crop, or document style.</p>
                                                    </div>
                                                </div>
                                                <div class="sample-photo-card rejected">
                                                    <div class="image-wrap">
                                                        <span class="sample-badge"><i class="bi bi-x-circle-fill"></i> Rejected</span>
                                                        <div class="sample-placeholder"><i class="bi bi-file-earmark-text"></i></div>
                                                    </div>
                                                    <div class="sample-caption">
                                                        <h6>Sample 3</h6>
                                                        <p>Result-card style image — not a passport photo.</p>
                                                    </div>
                                                </div>
                                                <div class="sample-photo-card rejected">
                                                    <div class="image-wrap">
                                                        <span class="sample-badge"><i class="bi bi-x-circle-fill"></i> Rejected</span>
                                                        <div class="sample-placeholder"><i class="bi bi-file-earmark-medical"></i></div>
                                                    </div>
                                                    <div class="sample-caption">
                                                        <h6>Sample 4</h6>
                                                        <p>Certificates or scanned forms are not passport photos.</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Candidate's CNIC / B-Form *</label>
                                            <input type="file" class="form-control file-validate" name="doc_bform" accept=".webp,.jpg,.jpeg,.png,.pdf" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Father's / Guardian's CNIC *</label>
                                            <input type="file" class="form-control file-validate" name="doc_father_cnic" accept=".webp,.jpg,.jpeg,.png,.pdf" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Matric Certificate / Result Card *</label>
                                            <input type="file" class="form-control file-validate" name="doc_10th" accept=".webp,.jpg,.jpeg,.png,.pdf" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Intermediate Certificate / Result Card *</label>
                                            <input type="file" class="form-control file-validate" name="doc_12th" accept=".webp,.jpg,.jpeg,.png,.pdf" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Domicile Certificate *</label>
                                            <input type="file" class="form-control file-validate" name="doc_domicile" accept=".webp,.jpg,.jpeg,.png,.pdf" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">IBCC / HEC Equivalence (O/A-Levels only)</label>
                                            <input type="file" class="form-control file-validate" name="doc_equivalence" accept=".jpg,.jpeg,.png,.pdf">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Entry Test Result Card (if applicable)</label>
                                            <input type="file" class="form-control file-validate" name="doc_entry_test" accept=".jpg,.jpeg,.png,.pdf">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 7. Quotas -->
                            <div class="form-block-card">
                                <div class="form-block-header"><i class="bi bi-award-fill"></i> 7. Quota Documents (if applicable)</div>
                                <div class="form-block-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Hafiz-e-Quran Certificate</label>
                                            <input type="file" class="form-control file-validate" name="doc_hafiz" accept=".jpg,.jpeg,.png,.pdf">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Disability Quota Certificate</label>
                                            <input type="file" class="form-control file-validate" name="doc_disability" accept=".jpg,.jpeg,.png,.pdf">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Sports Quota Documents</label>
                                            <input type="file" class="form-control file-validate" name="doc_sports" accept=".jpg,.jpeg,.png,.pdf">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Minority Quota Documents</label>
                                            <input type="file" class="form-control file-validate" name="doc_minority" accept=".jpg,.jpeg,.png,.pdf">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Work Experience Certificate (Postgraduate)</label>
                                            <input type="file" class="form-control file-validate" name="doc_experience" accept=".jpg,.jpeg,.png,.pdf">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Publications / Research Papers (MPhil/PhD)</label>
                                            <input type="file" class="form-control file-validate" name="doc_publications" accept=".jpg,.jpeg,.png,.pdf">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 my-4">
                                <button type="reset" class="btn btn-outline-secondary px-4" id="applicationReset">Reset</button>
                                <button type="submit" class="btn btn-primary btn-lg px-5">
                                    <i class="bi bi-send-check me-2"></i>Submit Application
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div><!-- /apply -->

            <!-- 3. VIEW APPLICATION -->
            <div class="tab-pane fade" id="view-application" role="tabpanel">
                <div class="g-card">
                    <h3 class="form-section-title"><i class="bi bi-file-earmark-person"></i> Submitted Application</h3>

                    <?php if ($app_data): ?>
                        <div class="row g-4 mb-4">
                            <div class="col-md-3 text-center">
                                <div class="profile-placeholder"><i class="bi bi-person" style="font-size:2.5rem;"></i></div>
                                <div class="mt-3">
                                    <span class="badge bg-<?= ($app_status === 'approved') ? 'success' : 'info'; ?> text-uppercase px-3 py-2">
                                        <?= htmlspecialchars($app_status); ?>
                                    </span>
                                </div>
                                <?php if (!empty($app_data['review_note'])): ?>
                                    <div class="g-alert g-warn mt-3 text-start">
                                        <strong>Review note:</strong> <?= htmlspecialchars($app_data['review_note']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-9">
                                <h5 class="t2 mb-3" style="font-size:.85rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;">
                                    <i class="bi bi-person me-1"></i>Personal Details
                                </h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <tbody>
                                            <tr>
                                                <th width="32%">Full Name</th>
                                                <td><?= htmlspecialchars($app_data['full_name'] ?? ''); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Father's Name</th>
                                                <td><?= htmlspecialchars($app_data['father_name'] ?? ''); ?></td>
                                            </tr>
                                            <tr>
                                                <th>CNIC</th>
                                                <td><?= htmlspecialchars($app_data['cnic'] ?? ''); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Gender / Religion</th>
                                                <td><?= htmlspecialchars($app_data['gender'] ?? 'N/A') . ' · ' . htmlspecialchars($app_data['religion'] ?? 'N/A'); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <h5 class="t2 mb-3" style="font-size:.85rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;"><i class="bi bi-book me-1"></i>Program</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <tbody>
                                            <tr>
                                                <th width="40%">Program</th>
                                                <td class="fw-bold text-teal"><?= htmlspecialchars($app_data['pref_1'] ?? $app_data['department'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Campus</th>
                                                <td><?= htmlspecialchars($app_data['campus'] ?? 'Main Campus Jamshoro'); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h5 class="t2 mb-3" style="font-size:.85rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;"><i class="bi bi-telephone me-1"></i>Contact</h5>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <tbody>
                                            <tr>
                                                <th width="35%">Phone</th>
                                                <td><?= htmlspecialchars($app_data['phone'] ?? ''); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Email</th>
                                                <td><?= htmlspecialchars($app_data['email'] ?? ''); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <h5 class="t2 mb-3" style="font-size:.85rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;"><i class="bi bi-paperclip me-1"></i>Uploaded Documents</h5>
                        <div class="row g-3">
                            <?php
                            $docs = [
                                '10th Marksheet' => $app_data['doc_10th']    ?? '',
                                '12th Marksheet' => $app_data['doc_12th']    ?? '',
                                'B-Form / CNIC'  => $app_data['doc_bform']   ?? '',
                                'Domicile'       => $app_data['doc_domicile'] ?? ''
                            ];
                            foreach ($docs as $label => $path): ?>
                                <div class="col-md-3 col-sm-6">
                                    <div class="doc-card h-100">
                                        <div class="doc-icon"><i class="bi bi-file-earmark-check"></i></div>
                                        <div class="doc-label"><?= $label; ?></div>
                                        <?php if (!empty($path)): ?>
                                            <a href="<?= htmlspecialchars($path); ?>" target="_blank" class="btn btn-sm" style="background:var(--teal-dim);color:var(--teal);border:1px solid rgba(20,184,166,.3);">View File</a>
                                        <?php else: ?>
                                            <span style="font-size:.78rem;color:#f87171;">Not uploaded</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    <?php else: ?>
                        <div class="g-alert g-warn d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                            <div><strong>No application found.</strong> Go to <strong>Apply Online</strong> to submit your form.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div><!-- /view-application -->

            <!-- 4. DOWNLOAD SLIP -->
            <div class="tab-pane fade" id="download-slip" role="tabpanel">
                <div class="g-card center-pane">
                    <div class="icon-ring" style="background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.28);">
                        <i class="bi bi-file-earmark-pdf" style="color:#f87171;"></i>
                    </div>
                    <h3>Entry Test Slip</h3>
                    <p>Available once your challan and application are approved.</p>
                    <?php if ($can_download_slip): ?>
                        <a href="generate_slip.php" target="_blank" class="btn btn-danger">
                            <i class="bi bi-download me-1"></i> Download PDF Slip
                        </a>
                    <?php else: ?>
                        <button class="btn btn-danger disabled" disabled>
                            <i class="bi bi-download me-1"></i> Download PDF Slip
                        </button>
                    <?php endif; ?>
                </div>
            </div><!-- /download-slip -->

            <!-- 5. TEST RESULT -->
            <div class="tab-pane fade" id="test-result" role="tabpanel">
                <div class="g-card center-pane">
                    <div class="icon-ring" style="background:rgba(99,102,241,.12);border-color:rgba(99,102,241,.28);">
                        <i class="bi bi-bar-chart-line" style="color:#818cf8;"></i>
                    </div>
                    <h3>Results Not Announced Yet</h3>
                    <p>
                        <?= $app_status === 'approved'
                            ? 'Your account is eligible. Check back after the entry test has been conducted.'
                            : 'Check back once your application is approved and the entry test has been conducted.'; ?>
                    </p>
                </div>
            </div><!-- /test-result -->

        </div><!-- /tab-content -->
    </main>

    <!-- ================================================================
     FOOTER
================================================================ -->
    <footer class="site-footer">
        <span>© <?= date('Y'); ?> Ahmer Institute of Technology</span>
        <div class="footer-links">
            <button type="button" class="footer-link" data-pane="dashboard">Dashboard</button>
            <button type="button" class="footer-link" data-pane="apply">Apply</button>
            <button type="button" class="footer-link" data-pane="view-application">Review</button>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script nonce="<?php echo htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        $(function() {

            function showPane(paneId) {
                const pane = document.getElementById(paneId);
                if (!pane) return;
                document.querySelectorAll('.tab-pane').forEach(function(item) {
                    item.classList.remove('show', 'active');
                });
                pane.classList.add('show', 'active');
                $('.sidebar-item').removeClass('active');
                $(`.sidebar-item[data-bs-target="#${paneId}"]`).addClass('active');
                $('.footer-link').removeClass('is-active');
                $(`.footer-link[data-pane="${paneId}"]`).addClass('is-active');
                if (window.history.replaceState) {
                    window.history.replaceState({}, '', `dashboard?pane=${encodeURIComponent(paneId)}`);
                }
            }

            // Toggle sidebar (mobile)
            $('#sidebarToggle').on('click', function() {
                if ($(window).width() <= 991.98) $('body').toggleClass('sidebar-open');
            });

            // Close sidebar on backdrop click
            $('#sidebarBackdrop').on('click', function() {
                $('body').removeClass('sidebar-open');
            });

            // Sidebar item click
            $('.sidebar-item').on('click', function() {
                const target = $(this).attr('data-bs-target');
                if (target) {
                    $('.sidebar-item').removeClass('active');
                    $(`.sidebar-item[data-bs-target="${target}"]`).addClass('active');
                    showPane(target.replace('#', ''));
                }
                if ($(window).width() <= 991.98) $('body').removeClass('sidebar-open');
            });

            // Action chip clicks
            $('.action-chip').on('click', function(e) {
                const target = $(this).attr('data-bs-target');
                if (target) {
                    e.preventDefault();
                    showPane(target.replace('#', ''));
                }
            });

            // Footer link clicks
            $('.footer-link').on('click', function() {
                const paneId = $(this).data('pane');
                $('.footer-link').removeClass('is-active');
                $(this).addClass('is-active');
                showPane(paneId);
            });

            // Initial pane from PHP
            const initialPane = <?php echo json_encode($initial_pane); ?>;
            showPane(initialPane || 'dashboard');

            // CNIC auto-format
            $('.cnic-mask').on('input', function(e) {
                const x = e.target.value.replace(/\D/g, '').match(/(\d{0,5})(\d{0,7})(\d{0,1})/);
                e.target.value = !x[2] ? x[1] : x[1] + '-' + x[2] + (x[3] ? '-' + x[3] : '');
            });

            const applicationForm = document.getElementById('applicationForm');
            const draftKey = 'ait-application-draft-v1';
            let applicationSubmitted = false;

            function setFileInputState(input, hasFile) {
                let row = input.closest('.file-control-row');
                if (!row) {
                    row = document.createElement('div');
                    row.className = 'file-control-row';
                    input.parentNode.insertBefore(row, input);
                    row.appendChild(input);
                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'file-remove';
                    remove.innerHTML = '<i class="bi bi-x-circle me-1"></i>Remove';
                    remove.addEventListener('click', function() {
                        input.value = '';
                        setFileInputState(input, false);
                        input.dispatchEvent(new Event('change', {
                            bubbles: true
                        }));
                    });
                    row.appendChild(remove);
                }
                row.querySelector('.file-remove').hidden = !hasFile;
            }

            // Client-side guard and visible processing feedback; the server remains authoritative.
            $('.file-validate').each(function() {
                setFileInputState(this, this.files.length > 0);
            }).on('change', function() {
                setFileInputState(this, this.files.length > 0);
                const file = this.files[0];
                if (!file) return;
                const isPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name);
                const limit = isPdf ? 20 * 1024 * 1024 : 5 * 1024 * 1024;
                const allowed = /\.(webp|png|jpe?g|pdf)$/i.test(file.name);
                if (!allowed || file.size > limit) {
                    alert(!allowed ? 'Only WEBP, PNG, JPG, or PDF files are allowed.' : (isPdf ? 'PDF files must be 20 MB or smaller.' : 'Images must be 5 MB or smaller.'));
                    this.value = '';
                    setFileInputState(this, false);
                    return;
                }
                let status = this.parentElement.querySelector('.upload-status');
                if (!status) {
                    status = document.createElement('div');
                    status.className = 'upload-status';
                    status.innerHTML = '<div class="upload-status-row"><span class="upload-status-text"></span><strong class="upload-status-percent">0%</strong></div><div class="upload-progress"><div class="upload-progress-bar"></div></div>';
                    this.parentElement.appendChild(status);
                }
                status.classList.add('is-visible');
                const text = status.querySelector('.upload-status-text');
                const percent = status.querySelector('.upload-status-percent');
                const bar = status.querySelector('.upload-progress-bar');
                let value = 0;
                text.textContent = isPdf ? 'Checking PDF integrity...' : 'Compressing and converting to WebP...';
                const timer = setInterval(function() {
                    value = Math.min(value + Math.ceil(Math.random() * 17), 100);
                    percent.textContent = value + '%';
                    bar.style.width = value + '%';
                    if (value === 100) {
                        clearInterval(timer);
                        text.textContent = isPdf ? 'PDF verified and ready.' : 'Successfully compressed to WebP.';
                    }
                }, 90);
            });

            $('#applicationReset').on('click', function(e) {
                if (!window.confirm('Reset every field and remove all selected files?')) {
                    e.preventDefault();
                    return;
                }
                window.localStorage.removeItem(draftKey);
                applicationSubmitted = true;
                window.setTimeout(function() {
                    document.querySelectorAll('.file-validate').forEach(function(input) {
                        setFileInputState(input, false);
                    });
                }, 0);
            });

            if (applicationForm) {
                const fields = applicationForm.querySelectorAll('input:not([type="file"]):not([type="hidden"]), select, textarea');
                const savedDraft = window.localStorage.getItem(draftKey);
                if (savedDraft) {
                    try {
                        const values = JSON.parse(savedDraft);
                        fields.forEach(function(field) {
                            if (Object.prototype.hasOwnProperty.call(values, field.name)) {
                                if (field.type === 'checkbox' || field.type === 'radio') field.checked = values[field.name];
                                else field.value = values[field.name];
                            }
                        });
                    } catch (error) {
                        window.localStorage.removeItem(draftKey);
                    }
                }

                function saveDraft() {
                    const values = {};
                    fields.forEach(function(field) {
                        if (!field.name || field.type === 'password') return;
                        values[field.name] = (field.type === 'checkbox' || field.type === 'radio') ? field.checked : field.value;
                    });
                    window.localStorage.setItem(draftKey, JSON.stringify(values));
                }

                applicationForm.addEventListener('input', saveDraft);
                applicationForm.addEventListener('change', saveDraft);
                applicationForm.addEventListener('submit', function() {
                    applicationSubmitted = true;
                    window.localStorage.removeItem(draftKey);
                });
                window.addEventListener('beforeunload', function(event) {
                    if (!applicationSubmitted && Array.from(fields).some(function(field) {
                            return field.value;
                        })) {
                        event.preventDefault();
                        event.returnValue = '';
                    }
                });
            }

        });
    </script>
</body>

</html>