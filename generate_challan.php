<?php
require_once __DIR__ . '/backend/session.php';
ait_start_secure_session();
require_once 'backend/data.php';
require_once 'backend/security.php';

if (!isset($_SESSION['student_id'])) {
    die("Unauthorized access. Please log in.");
}

$student_id = $_SESSION['student_id'];
$csp_nonce = ait_bootstrap_security();

// Query Application and Challan data using MySQLi
$stmt = $conn->prepare("
    SELECT a.*, c.challan_no, c.amount, c.bank_name, c.due_date, c.status AS challan_status
    FROM applications a 
    JOIN challans c ON a.id = c.application_id 
    WHERE a.student_id = ? 
    ORDER BY a.id DESC LIMIT 1
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("No active application or challan found.");
}

$data = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (($data['status'] ?? '') !== 'approved') {
    http_response_code(403);
    die('Your fee challan becomes available after application approval.');
}

$copies = ['Bank Copy', 'University Copy', 'Candidate Copy'];
$safe = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AIT Fee Voucher_<?php echo $safe($data['challan_no']); ?></title>
    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        body {
            background: #e8eef4;
            padding: 24px;
            color: #102a43;
        }

        .no-print {
            text-align: center;
            margin-bottom: 20px;
        }

        .btn-print {
            background: #0f766e;
            color: #fff;
            padding: 10px 20px;
            border: none;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-print:hover {
            background: #115e59;
        }

        .page {
            width: min(210mm, 100%);
            background: #fff;
            margin: 0 auto;
            padding: 10mm;
            border: 1px solid #cbd5e1;
            box-shadow: 0 20px 55px rgba(15, 42, 67, .14);
        }

        .challan-copy {
            border: 1px solid #cbd5e1;
            border-left: 5px solid #0f766e;
            padding: 14px;
            margin-bottom: 12px;
            page-break-inside: avoid;
        }

        .header {
            border-bottom: 2px solid #0f766e;
            padding: 0 0 9px;
            margin-bottom: 10px;
            display: grid;
            grid-template-columns: 58px 1fr auto;
            align-items: center;
            gap: 12px;
            text-align: left;
        }

        .header h2 {
            font-size: 16px;
            margin-bottom: 2px;
        }

        .header h3 {
            font-size: 12px;
            font-weight: normal;
        }

        .header span {
            font-size: 12px;
            font-weight: bold;
            background: #dff7f2;
            color: #115e59;
            padding: 2px 8px;
            border-radius: 3px;
            display: inline-block;
            margin-top: 3px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 12px;
        }

        table td,
        table th {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
        }

        .amount-row {
            background: #effaf8;
            font-weight: bold;
            font-size: 13px;
        }

        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 25px;
            text-align: center;
            font-size: 11px;
        }

        .sig-line {
            border-top: 1px solid #64748b;
            width: 30%;
            padding-top: 3px;
        }

        .uni-logo {
            width: 58px;
            height: 58px;
            object-fit: contain;
        }

        .header-copy {
            min-width: 0;
        }

        .header-meta {
            text-align: right;
            font-size: 10px;
            color: #526579;
        }

        .header-meta strong {
            display: block;
            color: #0f766e;
            font-size: 12px;
        }

        .fee-note {
            margin-top: 8px;
            color: #526579;
            font-size: 10px;
        }

        @media print {
            body {
                background: none;
                padding: 0;
            }

            .no-print {
                display: none;
            }

            .page {
                border: none;
                padding: 0;
                width: 100%;
            }

            @page {
                size: A4;
                margin: 10mm;
            }
        }
    </style>
</head>

<body>

    <div class="no-print">
        <button onclick="window.print()" class="btn-print">Print / Save as PDF</button>
    </div>

    <div class="page">
        <?php foreach ($copies as $copy): ?>
            <div class="challan-copy">
                <div class="header">
                    <img class="uni-logo" src="assets/images/logo/ait_logo.png" alt="AIT logo">
                    <div class="header-copy">
                        <h2>Ahmer Institute of Technology</h2>
                        <h3>Admissions Fee Voucher · <?php echo $safe($data['bank_name']); ?></h3>
                        <span><?php echo $safe($copy); ?></span>
                    </div>
                    <div class="header-meta"><strong><?php echo $safe($data['challan_no']); ?></strong>ISSUED <?php echo date('d M Y'); ?></div>
                </div>

                <table>
                    <tr>
                        <th width="20%">Challan No:</th>
                        <td width="30%"><strong><?php echo htmlspecialchars($data['challan_no']); ?></strong></td>
                        <th width="20%">Due Date:</th>
                        <td width="30%"><?php echo $safe($data['due_date'] ?: 'Admission deadline'); ?></td>
                    </tr>
                    <tr>
                        <th>Candidate:</th>
                        <td><?php echo $safe($data['full_name']); ?></td>
                        <th>CNIC:</th>
                        <td><?php echo $safe($data['cnic']); ?></td>
                    </tr>
                    <tr>
                        <th>Department:</th>
                        <td colspan="3"><?php echo $safe($data['pref_1'] ?: $data['department']); ?></td>
                    </tr>
                    <tr class="amount-row">
                        <td colspan="2" style="text-align: right;">Total Amount Payable:</td>
                        <td colspan="2" style="color: #0f766e;">PKR <?php echo number_format($data['amount'], 2); ?></td>
                    </tr>
                </table>

                <div class="fee-note">Payable at <?php echo $safe($data['bank_name']); ?>. Keep the stamped candidate copy for your records.</div>

                <div class="signatures">
                    <div class="sig-line">Depositor Signature</div>
                    <div class="sig-line">Bank Cashier</div>
                    <div class="sig-line">Branch Manager</div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script nonce="<?php echo $safe($csp_nonce); ?>">
        // Automatically trigger print dialog when page loads
        window.onload = function() {
            window.print();
        };
    </script>

</body>

</html>