<?php
require_once __DIR__ . '/../backend/session.php';
ait_start_secure_session();
require_once __DIR__ . '/../backend/security.php';
require_once __DIR__ . '/../backend/pdo.php';

$csp_nonce = ait_bootstrap_security();
$pdo = ait_pdo();

$code = trim((string) ($_GET['code'] ?? ''));

if ($code === '' || !preg_match('/^T\d{5}$/', $code)) {
    http_response_code(404);
    require __DIR__ . '/../errors/404.php';
    exit;
}

$stmt = $pdo->prepare('SELECT t.*, d.name AS department_name FROM teachers t LEFT JOIN departments d ON d.code = t.department_code WHERE t.teacher_code = :code AND t.is_active = 1 LIMIT 1');
$stmt->execute(['code' => $code]);
$teacher = $stmt->fetch();

if (!$teacher) {
    http_response_code(404);
    require __DIR__ . '/../errors/404.php';
    exit;
}

$subjectsStmt = $pdo->prepare('SELECT s.code, s.name, s.semester, s.credit_hours FROM subjects s INNER JOIN teacher_subjects ts ON ts.subject_id = s.id WHERE ts.teacher_id = :id ORDER BY s.semester, s.name');
$subjectsStmt->execute(['id' => $teacher['id']]);
$subjects = $subjectsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($teacher['name'], ENT_QUOTES, 'UTF-8'); ?> | AIT Faculty</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon/ait.ico">
    <link rel="stylesheet" href="../assets/css/public.css">
    <style nonce="<?= htmlspecialchars($csp_nonce, ENT_QUOTES, 'UTF-8'); ?>">
        .profile-shell { max-width: 760px; margin: 60px auto; padding: 0 24px 80px; }
        .profile-card { background: var(--glass); border: 1px solid var(--glass-border); border-radius: 16px; padding: 40px; }
        .profile-card h1 { font: 600 32px "Space Grotesk", sans-serif; margin: 4px 0 6px; }
        .profile-meta { color: var(--muted); font-size: 14px; margin-bottom: 24px; }
        .profile-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin: 24px 0; }
        .profile-grid div { padding: 14px; border: 1px solid var(--line); border-radius: 8px; }
        .profile-grid span { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); margin-bottom: 4px; }
        .subject-list { list-style: none; padding: 0; margin: 12px 0 0; display: grid; gap: 8px; }
        .subject-list li { padding: 10px 14px; border: 1px solid var(--line); border-radius: 8px; }
    </style>
</head>

<body>
    <main class="profile-shell">
        <article class="profile-card">
            <p class="eyebrow">AIT Faculty</p>
            <h1><?= htmlspecialchars($teacher['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="profile-meta">
                <?= htmlspecialchars((string) ($teacher['designation'] ?? 'Faculty member'), ENT_QUOTES, 'UTF-8'); ?>
                &middot; <?= htmlspecialchars((string) ($teacher['department_name'] ?? 'Department not assigned'), ENT_QUOTES, 'UTF-8'); ?>
                &middot; Teacher ID <?= htmlspecialchars($teacher['teacher_code'], ENT_QUOTES, 'UTF-8'); ?>
            </p>

            <div class="profile-grid">
                <div><span>Department</span><?= htmlspecialchars((string) ($teacher['department_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div><span>Working time</span><?= htmlspecialchars((string) ($teacher['working_time'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div><span>Joined</span><?= $teacher['joining_date'] ? htmlspecialchars(date('F Y', strtotime((string) $teacher['joining_date'])), ENT_QUOTES, 'UTF-8') : '—'; ?></div>
            </div>

            <h2 style="font:600 18px 'Space Grotesk',sans-serif;">Subjects taught</h2>
            <?php if ($subjects === []): ?>
                <p style="color:var(--muted);">No subjects assigned yet.</p>
            <?php else: ?>
                <ul class="subject-list">
                    <?php foreach ($subjects as $subj): ?>
                        <li><strong><?= htmlspecialchars($subj['code'], ENT_QUOTES, 'UTF-8'); ?></strong> — <?= htmlspecialchars($subj['name'], ENT_QUOTES, 'UTF-8'); ?> (Semester <?= (int) $subj['semester']; ?>)</li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    </main>
</body>

</html>
