<?php
session_start();
// Uncomment if admin login session check is required
// if (!isset($_SESSION['admin_id'])) { header("Location: admin_login.php"); exit(); }

require_once './backend/data.php';

$message = '';
$msg_type = '';

// Handle Approve / Disapprove Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $app_id = intval($_POST['app_id']);
    $new_status = ($_POST['action_type'] === 'approve') ? 'Approved' : 'Disapproved';

    $stmt = $conn->prepare("UPDATE applications SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $app_id);

    if ($stmt->execute()) {
        $message = "Application #{$app_id} successfully updated to {$new_status}.";
        $msg_type = "success";
    } else {
        $message = "Failed to update application status.";
        $msg_type = "danger";
    }
    $stmt->close();
}

// Fetch all applications
$query = "SELECT * FROM applications ORDER BY id DESC";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Applications Management | AIT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar-custom {
            background-color: #239cb3;
        }

        .card-custom {
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .badge-pending {
            background-color: #ffc107;
            color: #000;
        }

        .badge-approved {
            background-color: #198754;
            color: #fff;
        }

        .badge-disapproved {
            background-color: #dc3545;
            color: #fff;
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-dark navbar-custom px-4 py-2 shadow-sm mb-4">
        <span class="navbar-brand fw-bold"><i class="bi bi-shield-lock-fill me-2"></i>AIT Admin Portal</span>
        <span class="text-white">Admin Dashboard</span>
    </nav>

    <div class="container-fluid px-4">
        <?php if ($message): ?>
            <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card card-custom p-4">
            <h4 class="mb-4 text-secondary"><i class="bi bi-file-earmark-person me-2"></i>Submitted Student Applications</h4>

            <div class="table-responsive">
                <table class="table table-hover align-middle border">
                    <thead class="table-light">
                        <tr>
                            <th>#ID</th>
                            <th>Student Name</th>
                            <th>CNIC</th>
                            <th>Program / Campus</th>
                            <th>Challan Status</th>
                            <th>App Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()):
                                $challan_paid = !empty($row['challan_pic']);
                                $status_class = strtolower($row['status'] ?? 'pending');
                            ?>
                                <tr>
                                    <td><strong>#<?= $row['id'] ?></strong></td>
                                    <td><?= htmlspecialchars($row['full_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($row['cnic'] ?? 'N/A') ?></td>
                                    <td>
                                        <small class="d-block fw-bold"><?= htmlspecialchars($row['pref_1'] ?? 'N/A') ?></small>
                                        <small class="text-muted"><?= htmlspecialchars($row['campus'] ?? 'N/A') ?></small>
                                    </td>
                                    <td>
                                        <?php if ($challan_paid): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Paid & Uploaded</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><i class="bi bi-clock me-1"></i> Unpaid / Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $status_class ?>">
                                            <?= ucfirst(htmlspecialchars($row['status'] ?? 'Pending')) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-info text-white me-1" data-bs-toggle="modal" data-bs-target="#appModal<?= $row['id'] ?>">
                                            <i class="bi bi-eye"></i> View Details
                                        </button>

                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="app_id" value="<?= $row['id'] ?>">
                                            <button type="submit" name="action_type" value="approve" class="btn btn-sm btn-success me-1" onclick="return confirm('Approve this application?');">
                                                <i class="bi bi-check-lg"></i> Approve
                                            </button>
                                            <button type="submit" name="action_type" value="disapprove" class="btn btn-sm btn-danger" onclick="return confirm('Disapprove this application?');">
                                                <i class="bi bi-x-lg"></i> Disapprove
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- FULL STUDENT DETAILS MODAL -->
                                <div class="modal fade" id="appModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                                        <div class="modal-content">
                                            <div class="modal-header bg-primary text-white">
                                                <h5 class="modal-title">Application Details - #<?= $row['id'] ?> (<?= htmlspecialchars($row['full_name'] ?? '') ?>)</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">

                                                <!-- Personal Info -->
                                                <h6 class="border-bottom pb-2 text-primary fw-bold"><i class="bi bi-person me-2"></i>1. Personal Information</h6>
                                                <div class="row g-3 mb-4">
                                                    <div class="col-md-3"><strong>Full Name:</strong> <br><?= htmlspecialchars($row['full_name'] ?? 'N/A') ?></div>
                                                    <div class="col-md-3"><strong>Father Name:</strong> <br><?= htmlspecialchars($row['father_name'] ?? 'N/A') ?></div>
                                                    <div class="col-md-3"><strong>Student CNIC:</strong> <br><?= htmlspecialchars($row['cnic'] ?? 'N/A') ?></div>
                                                    <div class="col-md-3"><strong>Father CNIC:</strong> <br><?= htmlspecialchars($row['father_cnic'] ?? 'N/A') ?></div>
                                                    <div class="col-md-3"><strong>DOB:</strong> <br><?= htmlspecialchars($row['dob'] ?? 'N/A') ?></div>
                                                    <div class="col-md-3"><strong>Gender:</strong> <br><?= htmlspecialchars($row['gender'] ?? 'N/A') ?></div>
                                                    <div class="col-md-3"><strong>Religion:</strong> <br><?= htmlspecialchars($row['religion'] ?? 'N/A') ?></div>
                                                    <div class="col-md-3"><strong>Province/Domicile:</strong> <br><?= htmlspecialchars($row['domicile_province'] ?? 'N/A') ?></div>
                                                    <div class="col-md-3"><strong>District/City:</strong> <br><?= htmlspecialchars($row['district_city'] ?? 'N/A') ?></div>
                                                </div>

                                                <!-- Contact Info -->
                                                <h6 class="border-bottom pb-2 text-primary fw-bold"><i class="bi bi-telephone me-2"></i>2. Contact Details</h6>
                                                <div class="row g-3 mb-4">
                                                    <div class="col-md-3"><strong>Mobile:</strong> <br><?= htmlspecialchars($row['phone'] ?? 'N/A') ?></div>
                                                    <div class="col-md-3"><strong>Alt Mobile:</strong> <br><?= htmlspecialchars($row['alt_phone'] ?? 'N/A') ?></div>
                                                    <div class="col-md-3"><strong>Email:</strong> <br><?= htmlspecialchars($row['email'] ?? 'N/A') ?></div>
                                                    <div class="col-md-6"><strong>Permanent Address:</strong> <br><?= htmlspecialchars($row['permanent_address'] ?? 'N/A') ?></div>
                                                    <div class="col-md-6"><strong>Postal Address:</strong> <br><?= htmlspecialchars($row['postal_address'] ?? 'N/A') ?></div>
                                                </div>

                                                <!-- Academic Info -->
                                                <h6 class="border-bottom pb-2 text-primary fw-bold"><i class="bi bi-journal-bookmark me-2"></i>3. Academic Qualifications</h6>
                                                <div class="table-responsive mb-4">
                                                    <table class="table table-bordered text-center">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>Level</th>
                                                                <th>Board</th>
                                                                <th>Roll No</th>
                                                                <th>Year</th>
                                                                <th>Marks Obtained</th>
                                                                <th>Total Marks</th>
                                                                <th>Percentage</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td><strong>Matriculation</strong></td>
                                                                <td><?= htmlspecialchars($row['matric_board'] ?? 'N/A') ?></td>
                                                                <td><?= htmlspecialchars($row['matric_roll'] ?? 'N/A') ?></td>
                                                                <td><?= htmlspecialchars($row['matric_year'] ?? 'N/A') ?></td>
                                                                <td><?= htmlspecialchars($row['matric_obtained_marks'] ?? 'N/A') ?></td>
                                                                <td><?= htmlspecialchars($row['matric_total_marks'] ?? 'N/A') ?></td>
                                                                <td><?= htmlspecialchars($row['matric_percentage'] ?? 'N/A') ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td><strong>Intermediate</strong></td>
                                                                <td><?= htmlspecialchars($row['inter_board'] ?? 'N/A') ?></td>
                                                                <td><?= htmlspecialchars($row['inter_roll'] ?? 'N/A') ?></td>
                                                                <td><?= htmlspecialchars($row['inter_year'] ?? 'N/A') ?></td>
                                                                <td><?= htmlspecialchars($row['inter_obtained_marks'] ?? 'N/A') ?></td>
                                                                <td><?= htmlspecialchars($row['inter_total_marks'] ?? 'N/A') ?></td>
                                                                <td><?= htmlspecialchars($row['inter_percentage'] ?? 'N/A') ?></td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>

                                                <!-- Program Choice -->
                                                <h6 class="border-bottom pb-2 text-primary fw-bold"><i class="bi bi-building me-2"></i>4. Program Preferences</h6>
                                                <div class="row g-3 mb-4">
                                                    <div class="col-md-3"><strong>Campus:</strong> <br><?= htmlspecialchars($row['campus'] ?? 'N/A') ?></div>
                                                    <div class="col-md-3"><strong>Faculty:</strong> <br><?= htmlspecialchars($row['faculty'] ?? 'N/A') ?></div>
                                                    <div class="col-md-3"><strong>Shift:</strong> <br><?= htmlspecialchars($row['shift'] ?? 'N/A') ?></div>
                                                    <div class="col-md-3"><strong>1st Choice:</strong> <br><?= htmlspecialchars($row['pref_1'] ?? 'N/A') ?></div>
                                                </div>

                                                <!-- Documents & Challan Verification -->
                                                <h6 class="border-bottom pb-2 text-primary fw-bold"><i class="bi bi-paperclip me-2"></i>5. Documents & Challan Upload Verification</h6>
                                                <div class="row g-3">
                                                    <div class="col-md-6">
                                                        <div class="border rounded p-3 text-center">
                                                            <p class="fw-bold mb-2">Uploaded Paid Challan Slip</p>
                                                            <?php if (!empty($row['challan_pic'])): ?>
                                                                <a href="uploads/<?= htmlspecialchars($row['challan_pic']) ?>" target="_blank">
                                                                    <img src="uploads/<?= htmlspecialchars($row['challan_pic']) ?>" class="img-fluid rounded border" style="max-height: 200px;">
                                                                </a>
                                                            <?php else: ?>
                                                                <p class="text-danger my-3"><i class="bi bi-x-circle me-1"></i> No challan uploaded yet.</p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No applications found in the system.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>