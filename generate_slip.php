<?php
require_once __DIR__ . '/backend/session.php';
ait_start_secure_session();
require_once 'backend/data.php';

if (!isset($_SESSION['student_id'])) {
    die("Unauthorized access. Please log in.");
}

$student_id = $_SESSION['student_id'];

// Fetch Application & Profile Picture Path
$stmt = $conn->prepare("
    SELECT a.*, d.file_url AS profile_pic 
    FROM applications a 
    LEFT JOIN documents d ON a.id = d.application_id AND d.doc_type = 'profile_picture'
    WHERE a.student_id = ? 
    ORDER BY a.id DESC LIMIT 1
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("No application record found.");
}

$data = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (($data['status'] ?? '') !== 'approved') {
    die("Your application is not approved yet. Test slips are available only after superadmin approval.");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>AdmitCard_<?php echo htmlspecialchars($data['cnic']); ?></title>
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
            background: #28a745;
            color: #fff;
            padding: 10px 20px;
            border: none;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-print:hover {
            background: #218838;
        }

        .card {
            width: 190mm;
            background: #fff;
            margin: 0 auto;
            padding: 20px;
            border: 2px solid #000;
            position: relative;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .header h1 {
            font-size: 18px;
            text-transform: uppercase;
        }

        .header h2 {
            font-size: 14px;
            color: #555;
        }

        .body-content {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .details-table {
            width: 75%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .details-table td {
            padding: 6px 4px;
        }

        .details-table td.label {
            font-weight: bold;
            width: 30%;
        }

        .photo-box {
            width: 120px;
            height: 140px;
            border: 1px solid #333;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 11px;
            background: #fafafa;
        }

        .photo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .venue-box {
            background: #e9ecef;
            border: 1px solid #ced4da;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .venue-box h3 {
            font-size: 14px;
            margin-bottom: 5px;
            color: #000;
        }

        .footer {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
        }

        .sig-line {
            border-top: 1px solid #000;
            width: 40%;
            padding-top: 4px;
        }

        @media print {
            body {
                background: none;
                padding: 0;
            }

            .no-print {
                display: none;
            }

            .card {
                border: 2px solid #000;
                padding: 15px;
                width: 100%;
            }

            @page {
                size: A4 portrait;
                margin: 10mm;
            }
        }
    </style>
</head>

<body>

    <div class="no-print">
        <button onclick="window.print()" class="btn-print">🖨️ Print / Save Admit Card</button>
    </div>

    <div class="card">
        <div class="header">
            <h1>Ahmer Institute of Technology (AIT)</h1>
            <h2>ENTRY TEST ADMIT CARD 2026</h2>
        </div>

        <div class="body-content">
            <table class="details-table">
                <tr>
                    <td class="label">Seat Number:</td>
                    <td><strong>AIT-2026-<?php echo str_pad($data['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                </tr>
                <tr>
                    <td class="label">Full Name:</td>
                    <td><?php echo htmlspecialchars($data['full_name']); ?></td>
                </tr>
                <tr>
                    <td class="label">Father Name:</td>
                    <td><?php echo htmlspecialchars($data['father_name']); ?></td>
                </tr>
                <tr>
                    <td class="label">CNIC / B-Form:</td>
                    <td><?php echo htmlspecialchars($data['cnic']); ?></td>
                </tr>
                <tr>
                    <td class="label">Department:</td>
                    <td><?php echo htmlspecialchars($data['department']); ?></td>
                </tr>
            </table>

            <div class="photo-box">
                <?php if (!empty($data['profile_pic']) && file_exists($data['profile_pic'])): ?>
                    <img src="<?php echo htmlspecialchars($data['profile_pic']); ?>" alt="Candidate Photo">
                <?php else: ?>
                    <span>Photo Unavailable</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="venue-box">
            <h3>Test Schedule & Venue</h3>
            <p><strong>Date:</strong> Sunday, 15th August 2026</p>
            <p><strong>Reporting Time:</strong> 08:00 AM Sharp</p>
            <p><strong>Test Center:</strong> Main Auditorium, AIT Jamshoro</p>
        </div>

        <div class="footer">
            <div class="sig-line">Candidate Signature</div>
            <div class="sig-line">Director Admissions</div>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>

</body>

</html>