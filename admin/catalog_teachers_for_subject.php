<?php
require_once __DIR__ . '/../backend/session.php';
ait_start_secure_session();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo '[]';
    exit;
}

require_once __DIR__ . '/../backend/pdo.php';
$pdo = ait_pdo();

$subjectId = (int) ($_GET['subject_id'] ?? 0);
if ($subjectId < 1) {
    echo '[]';
    exit;
}

$stmt = $pdo->prepare('
    SELECT t.id, t.name, t.teacher_code
    FROM teachers t
    INNER JOIN teacher_subjects ts ON ts.teacher_id = t.id
    WHERE ts.subject_id = :sid AND t.is_active = 1
    ORDER BY t.name
');
$stmt->execute(['sid' => $subjectId]);
echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_SLASHES);
