<?php
session_start();
require_once 'backend/data.php';

if (!isset($_SESSION['student_id'])) {
    die("Unauthorized access. Please log in.");
}

$student_id = $_SESSION['student_id'];

// Query Application and Challan data using MySQLi
$stmt = $conn->prepare("
    SELECT a.*, c.challan_no, c.amount 
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

$copies = ['Bank Copy', 'University Copy', 'Candidate Copy'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Challan_<?php echo htmlspecialchars($data['challan_no']); ?></title>
    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        body {
            background: #f4f4f9;
            padding: 20px;
        }

        .no-print {
            text-align: center;
            margin-bottom: 20px;
        }

        .btn-print {
            background: #0056b3;
            color: #fff;
            padding: 10px 20px;
            border: none;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-print:hover {
            background: #003d80;
        }

        .page {
            width: 210mm;
            background: #fff;
            margin: 0 auto;
            padding: 15mm;
            border: 1px solid #ccc;
        }

        .challan-copy {
            border: 2px dashed #333;
            padding: 12px;
            margin-bottom: 15px;
            page-break-inside: avoid;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }

        .header h2 {
            font-size: 16px;
            margin-bottom: 2px;
        }

        .header h3 {
            font-size: 13px;
            font-weight: normal;
        }

        .header span {
            font-size: 12px;
            font-weight: bold;
            background: #eee;
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
            border: 1px solid #888;
            padding: 5px 8px;
        }

        .amount-row {
            background: #f9f9f9;
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
            border-top: 1px solid #333;
            width: 30%;
            padding-top: 3px;
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
        <button onclick="window.print()" class="btn-print">🖨️ Print / Save as PDF</button>
    </div>

    <div class="page">
        <?php foreach ($copies as $copy): ?>
            <div class="challan-copy">
                <div class="header">
                    <h2>HABIB BANK LIMITED (HBL)</h2>
                    <h3>AIT Admission Fee Voucher 2026</h3>
                    <span><?php echo $copy; ?></span>
                </div>

                <table>
                    <tr>
                        <th width="20%">Challan No:</th>
                        <td width="30%"><strong><?php echo htmlspecialchars($data['challan_no']); ?></strong></td>
                        <th width="20%">Issue Date:</th>
                        <td width="30%"><?php echo date('d-M-Y'); ?></td>
                    </tr>
                    <tr>
                        <th>Candidate:</th>
                        <td><?php echo htmlspecialchars($data['full_name']); ?></td>
                        <th>CNIC:</th>
                        <td><?php echo htmlspecialchars($data['cnic']); ?></td>
                    </tr>
                    <tr>
                        <th>Department:</th>
                        <td colspan="3"><?php echo htmlspecialchars($data['department']); ?></td>
                    </tr>
                    <tr class="amount-row">
                        <td colspan="2" style="text-align: right;">Total Amount Payable:</td>
                        <td colspan="2" style="color: #0056b3;">PKR <?php echo number_format($data['amount'], 2); ?></td>
                    </tr>
                </table>

                <div class="signatures">
                    <div class="sig-line">Depositor Signature</div>
                    <div class="sig-line">Bank Cashier</div>
                    <div class="sig-line">Branch Manager</div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        // Automatically trigger print dialog when page loads
        window.onload = function() {
            window.print();
        };
    </script>

</body>

</html>