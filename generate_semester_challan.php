<?php
require_once __DIR__ . '/backend/session.php';
ait_start_secure_session();
require_once __DIR__ . '/backend/data.php';
require_once __DIR__ . '/backend/security.php';
ait_bootstrap_security();
if (!isset($_SESSION['student_id'])) {
    http_response_code(403);
    exit('Please log in.');
}
$student_id = (int) $_SESSION['student_id'];
$semester = max(1, min(8, (int) ($_GET['semester'] ?? 0)));
$type = (string) ($_GET['type'] ?? 'semester_fee');
if (!in_array($type, ['semester_fee', 'exam_fee'], true)) {
    http_response_code(400);
    exit('Invalid challan type.');
}
$stmt = $conn->prepare('SELECT c.*, s.name, s.student_code, s.department_code FROM semester_challans c JOIN students s ON s.id = c.student_id WHERE c.student_id = ? AND c.semester = ? AND c.challan_type = ? AND c.status <> \'disabled\' LIMIT 1');
$stmt->bind_param('iis', $student_id, $semester, $type);
$stmt->execute();
$challan = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$challan || ($type === 'exam_fee' && $challan['status'] === 'disabled')) {
    http_response_code(403);
    exit('This challan is not enabled by admin.');
}
$safe = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$label = $type === 'exam_fee' ? 'Examination Fee Challan' : 'Semester Fee Challan';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $safe($label); ?> - <?= $safe($challan['challan_no']); ?></title>
    <link rel="icon" type="image/x-icon" href="assets/images/favicon/ait.ico">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #e7ecef;
            padding: 24px;
            color: #102a43
        }

        .no-print {
            text-align: center;
            margin-bottom: 20px
        }

        .btn {
            background: #0f766e;
            color: #fff;
            padding: 10px 20px;
            border: 0;
            cursor: pointer
        }

        .page {
            max-width: 960px;
            margin: auto;
            background: #fff;
            padding: 28px;
            border: 1px solid #b8c6cf;
            box-shadow: 0 20px 55px #0f2a4324
        }

        .head {
            display: flex;
            gap: 16px;
            align-items: center;
            border-bottom: 3px solid #0f766e;
            padding-bottom: 16px;
            justify-content: space-between
        }

        .head .logo-mark {
            width: 58px;
            height: 58px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            border: 2px solid #0f766e;
            color: #0f766e;
            font: 800 16px Arial, sans-serif;
            flex-shrink: 0;
        }

        .head h1 {
            font-size: 21px;
            margin: 0 0 5px
        }

        .head p {
            margin: 0;
            color: #526579
        }

        .voucher-copy {
            color: #0f766e;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: .12em;
            text-transform: uppercase;
            text-align: right;
        }

        .voucher-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            border: 1px solid #b8c6cf;
            margin: 22px 0;
        }

        .voucher-grid div {
            min-height: 66px;
            padding: 12px;
            border-right: 1px solid #b8c6cf;
            border-bottom: 1px solid #b8c6cf;
        }

        .voucher-grid div:nth-child(3n) {
            border-right: 0;
        }

        .voucher-grid div:nth-last-child(-n+3) {
            border-bottom: 0;
        }

        .voucher-grid small {
            display: block;
            color: #607583;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: .1em;
            text-transform: uppercase;
            margin-bottom: 7px;
        }

        .voucher-grid strong {
            font-size: 13px;
        }

        .meta {
            margin: 26px 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px
        }

        .meta div {
            border: 1px solid #cbd5e1;
            padding: 14px
        }

        .meta small {
            display: block;
            color: #526579;
            text-transform: uppercase;
            font-size: 10px;
            margin-bottom: 5px
        }

        .amount {
            background: #effaf8;
            border: 2px solid #0f766e;
            padding: 18px;
            text-align: center;
            font-size: 22px;
            font-weight: bold
        }

        .instructions {
            padding: 16px;
            margin-top: 20px;
            background: #f3f8f7;
            border-left: 4px solid #0f766e;
            color: #526579;
            font-size: 12px;
            line-height: 1.6;
        }

        .signatures {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-top: 68px;
            text-align: center;
            font-size: 11px;
            color: #526579;
        }

        .signatures div {
            padding-top: 9px;
            border-top: 1px solid #526579;
        }

        @media print {
            body {
                background: none;
                padding: 0
            }

            .no-print {
                display: none
            }

            .page {
                box-shadow: none;
                border: 0
            }

            .voucher-grid div {
                break-inside: avoid;
            }
        }
    </style>
</head>

<body>
    <div class="no-print"><button class="btn" onclick="window.print()">Print / Save PDF</button></div>
    <main class="page">
        <header class="head"><span class="logo-mark">AIT</span>
            <div>
                <h1>Ahmer Institute of Technology</h1>
                <p><?= $safe($label); ?> · Semester <?= $semester; ?></p>
            </div>
            <div class="voucher-copy">Official fee voucher<br>Fall 2026</div>
        </header>
        <section class="voucher-grid">
            <div><small>Student name</small><strong><?= $safe($challan['name']); ?></strong></div>
            <div><small>Student ID / roll</small><strong><?= $safe($challan['student_code']); ?></strong></div>
            <div><small>Department</small><strong><?= $safe($challan['department_code']); ?></strong></div>
            <div><small>Semester</small><strong><?= $semester; ?> · Year <?= (int) ceil($semester / 2); ?></strong></div>
            <div><small>Challan number</small><strong><?= $safe($challan['challan_no']); ?></strong></div>
            <div><small>Due date</small><strong><?= $safe($challan['due_date'] ?: 'As advised'); ?></strong></div>
        </section>
        <div class="amount">Payable amount: PKR <?= number_format((float) $challan['amount'], 2); ?></div>
        <div class="instructions"><strong>Payment instructions:</strong> Pay through the approved bank channel, retain the stamped receipt, and upload a clear image or PDF from your dashboard. Admin verification is required before examination access. Do not share this voucher or your student credentials.</div>
        <section class="signatures">
            <div>Bank officer signature</div>
            <div>AIT accounts office</div>
            <div>Student signature</div>
        </section>
    </main>
</body>

</html>