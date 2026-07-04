<?php
/**
 * User management helpers (Milestone 5).
 * Require after config/config.php and includes/session.php.
 */

function roles_list(): array
{
    return db()->query('SELECT id, name FROM roles ORDER BY name')->fetchAll();
}

/**
 * Number of active users with the admin role. Used to prevent system lockout.
 */
function active_admin_count(): int
{
    return (int) db()->query(
        "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id
         WHERE r.name = 'admin' AND u.is_active = 1"
    )->fetchColumn();
}

function get_user_row(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?'
    );
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function role_badge(string $role): string
{
    $class = $role === 'admin' ? 'bg-bb-red' : 'text-bg-secondary';
    return '<span class="badge ' . $class . ' text-uppercase">' . e($role) . '</span>';
}

function active_badge(int $active): string
{
    return $active
        ? '<span class="badge text-bg-success">Active</span>'
        : '<span class="badge text-bg-secondary">Inactive</span>';
}
