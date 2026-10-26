<?php
/**
 * Creates or resets a super-admin account from the command line.
 *
 *   php bin/create-admin.php --email=you@example.com --name="Your Name"
 *
 * The password is read from AIT_ADMIN_PASSWORD, or prompted for interactively.
 * Nothing about the account is stored in source control.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../backend/pdo.php';
require_once __DIR__ . '/../backend/security.php';

$options = getopt('', ['email:', 'name::', 'role::']);
$email = strtolower(trim((string) ($options['email'] ?? '')));
$name = trim((string) ($options['name'] ?? 'AIT Administrator'));
$role = ($options['role'] ?? 'super_admin') === 'sub_admin' ? 'sub_admin' : 'super_admin';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php bin/create-admin.php --email=you@example.com [--name=\"Full Name\"] [--role=super_admin|sub_admin]\n");
    exit(2);
}

$password = (string) getenv('AIT_ADMIN_PASSWORD');
if ($password === '') {
    fwrite(STDOUT, 'Password: ');
    $password = trim((string) fgets(STDIN));
}
$error = ait_password_policy_error($password, [$name, strtok($email, '@')]);
if ($error !== null) {
    fwrite(STDERR, "Password rejected: {$error}\n");
    exit(1);
}

$stmt = ait_pdo()->prepare("INSERT INTO admins (name, email, password, role, role_title, is_active, failed_attempts, locked_until)
    VALUES (:name, :email, :password, :role, :title, 1, 0, NULL)
    ON DUPLICATE KEY UPDATE name = VALUES(name), password = VALUES(password), role = VALUES(role), is_active = 1, failed_attempts = 0, locked_until = NULL");
$stmt->execute([
    'name' => $name,
    'email' => $email,
    'password' => ait_password_hash($password),
    'role' => $role,
    'title' => $role === 'super_admin' ? 'Super Administrator' : 'Administrator',
]);
fwrite(STDOUT, "Saved {$role} account for {$email}.\n");
