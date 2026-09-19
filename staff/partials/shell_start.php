<?php
/**
 * Shared staff portal shell. Expects: $pageTitle, $activePage, $staff,
 * $roleLabel, $csp_nonce already defined by the including page.
 */
$navItems = [
    'dashboard' => ['dashboard.php', 'bi-speedometer2', 'Dashboard'],
];
if ($staff['role_type'] === 'hod') {
    $navItems['department'] = ['department.php', 'bi-diagram-3', 'Department Overview'];
}
$initials = strtoupper(substr((string) $staff['name'], 0, 1) . substr((string) strrchr($staff['name'], ' '), 1, 1));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> | AIT Staff Portal</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon/ait.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/public.css">
    <link rel="stylesheet" href="../assets/css/portal.css">
</head>

<body class="portal-body">
    <div class="portal-layout">
        <aside class="portal-sidebar" id="portalSidebar">
            <div class="portal-brand"><span class="dot"></span> AIT Staff</div>
            <ul class="portal-nav">
                <?php foreach ($navItems as $key => [$href, $icon, $label]): ?>
                    <li><a href="<?= $href; ?>" class="<?= $activePage === $key ? 'active' : ''; ?>"><i class="bi <?= $icon; ?>"></i><span><?= $label; ?></span></a></li>
                <?php endforeach; ?>
            </ul>
            <div class="nav-section-label">Account</div>
            <ul class="portal-nav">
                <li>
                    <form method="post" action="logout.php">
                        <?= ait_csrf_field(); ?>
                        <button type="submit" style="all:unset;display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;color:var(--muted);font-size:13.5px;font-weight:600;cursor:pointer;width:100%;"><i class="bi bi-box-arrow-right"></i><span>Sign out</span></button>
                    </form>
                </li>
            </ul>
        </aside>

        <div class="portal-main">
            <header class="portal-topbar">
                <div style="display:flex;align-items:center;gap:12px;">
                    <button class="nav-toggle-portal" id="portalNavToggle"><i class="bi bi-list"></i></button>
                    <div class="breadcrumb-trail"><a href="dashboard.php">Staff Portal</a> / <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <div class="portal-user">
                    <span><?= htmlspecialchars((string) $staff['name'], ENT_QUOTES, 'UTF-8'); ?> &middot; <?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    <div class="avatar"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </header>
            <main class="portal-content">
