<?php

/**
 * Role-based access control for the Admin / Sub-admin / Teacher / Staff portals.
 *
 * Super admins implicitly hold every permission. Sub-admins hold exactly the
 * permissions granted to them in `admin_permissions`. Nothing here trusts
 * client input for authorization decisions — every check re-reads the admin
 * row (or the permission table) from the database.
 */

if (!defined('AIT_PERMISSIONS')) {
    define('AIT_PERMISSIONS', [
        'manage_teachers'  => 'Create, edit, disable, and reset passwords for teacher accounts',
        'manage_students'  => 'Create, edit, disable, and reset passwords for student accounts',
        'manage_staff'     => 'Create, edit, disable, and reset passwords for staff accounts',
        'manage_subadmins' => 'Create and configure sub-admin accounts and their permissions',
        'manage_departments' => 'Create and edit departments, semesters, and subjects',
        'manage_timetable'   => 'Create and edit class timetable slots',
        'manage_admissions'  => 'Review and decide admission applications',
        'manage_content'     => 'Edit public site content and announcements',
        'view_reports'       => 'View dashboards, KPIs, and audit logs',
    ]);
}

if (!function_exists('ait_admin_permission_keys')) {
    function ait_admin_permission_keys(): array
    {
        return array_keys(AIT_PERMISSIONS);
    }
}

if (!function_exists('ait_admin_has_permission')) {
    function ait_admin_has_permission(PDO $pdo, array $admin, string $permission): bool
    {
        if (($admin['role'] ?? '') === 'super_admin') {
            return true;
        }

        if (!array_key_exists($permission, AIT_PERMISSIONS)) {
            return false;
        }

        $stmt = $pdo->prepare('SELECT 1 FROM admin_permissions WHERE admin_id = :admin_id AND permission_key = :permission LIMIT 1');
        $stmt->execute(['admin_id' => (int) $admin['id'], 'permission' => $permission]);

        return (bool) $stmt->fetchColumn();
    }
}

if (!function_exists('ait_require_admin_permission')) {
    function ait_require_admin_permission(PDO $pdo, array $admin, string $permission): void
    {
        if (!ait_admin_has_permission($pdo, $admin, $permission)) {
            http_response_code(403);
            die('You do not have permission to perform this action.');
        }
    }
}

if (!function_exists('ait_admin_permissions_for')) {
    function ait_admin_permissions_for(PDO $pdo, int $adminId): array
    {
        $stmt = $pdo->prepare('SELECT permission_key FROM admin_permissions WHERE admin_id = :admin_id');
        $stmt->execute(['admin_id' => $adminId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

if (!function_exists('ait_set_admin_permissions')) {
    function ait_set_admin_permissions(PDO $pdo, int $adminId, array $permissionKeys, int $grantedBy): void
    {
        $valid = array_values(array_intersect($permissionKeys, ait_admin_permission_keys()));

        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare('DELETE FROM admin_permissions WHERE admin_id = :admin_id');
            $del->execute(['admin_id' => $adminId]);

            if ($valid !== []) {
                $insert = $pdo->prepare('INSERT INTO admin_permissions (admin_id, permission_key, granted_by) VALUES (:admin_id, :permission, :granted_by)');
                foreach ($valid as $key) {
                    $insert->execute(['admin_id' => $adminId, 'permission' => $key, 'granted_by' => $grantedBy]);
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

if (!function_exists('ait_log_audit')) {
    function ait_log_audit(PDO $pdo, string $actorType, ?int $actorId, string $action, ?string $targetType = null, ?int $targetId = null, array $meta = [], ?string $actorLabel = null): void
    {
        $stmt = $pdo->prepare('INSERT INTO audit_log (actor_type, actor_id, actor_label, action, target_type, target_id, meta, ip_address) VALUES (:actor_type, :actor_id, :actor_label, :action, :target_type, :target_id, :meta, :ip)');
        $stmt->execute([
            'actor_type'  => $actorType,
            'actor_id'    => $actorId,
            'actor_label' => $actorLabel,
            'action'      => $action,
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'meta'        => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_SLASHES),
            'ip'          => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
    }
}

if (!function_exists('ait_generate_account_code')) {
    /**
     * Deterministic, collision-checked short code such as T00042 / S00017.
     */
    function ait_generate_account_code(PDO $pdo, string $table, string $column, string $prefix): string
    {
        $stmt = $pdo->query("SELECT MAX(id) FROM `{$table}`");
        $nextId = ((int) $stmt->fetchColumn()) + 1;

        do {
            $candidate = $prefix . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
            $check = $pdo->prepare("SELECT 1 FROM `{$table}` WHERE `{$column}` = :code LIMIT 1");
            $check->execute(['code' => $candidate]);
            $exists = (bool) $check->fetchColumn();
            $nextId++;
        } while ($exists);

        return $candidate;
    }
}

if (!function_exists('ait_generate_temp_password')) {
    function ait_generate_temp_password(): string
    {
        return 'Ait' . random_int(10000, 99999) . '!';
    }
}
