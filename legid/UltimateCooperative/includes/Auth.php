<?php
declare(strict_types=1);

final class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $user = Database::query(
            'SELECT u.*, r.slug AS role_slug, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ? AND u.status = "active" LIMIT 1',
            [$email]
        )->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        unset($user['password_hash']);
        $_SESSION['user'] = $user;
        self::audit('login', 'users', (int) $user['id']);
        return true;
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('login.php');
        }
    }

    public static function can(string $permission): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }
        if ($user['role_slug'] === 'super_admin') {
            return true;
        }
        if ($user['role_slug'] === 'patient' && $permission === 'dashboard.view') {
            return true;
        }

        $count = Database::query(
            'SELECT COUNT(*) AS total FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ? AND p.slug = ?',
            [$user['role_id'], $permission]
        )->fetch()['total'] ?? 0;

        return (int) $count > 0;
    }

    public static function requirePermission(string $permission): void
    {
        if (!self::can($permission)) {
            http_response_code(403);
            require dirname(__DIR__) . '/templates/403.php';
            exit;
        }
    }

    public static function logout(): void
    {
        self::audit('logout', 'users', (int) (self::user()['id'] ?? 0));
        $_SESSION = [];
        session_destroy();
    }

    public static function audit(string $action, string $entity, ?int $entityId = null): void
    {
        try {
            if (!config('app.installed')) {
                return;
            }
            Database::query(
                'INSERT INTO audit_logs (user_id, action, entity, entity_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)',
                [self::user()['id'] ?? null, $action, $entity, $entityId, $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]
            );
        } catch (Throwable) {
            // Auditing must never break the clinical workflow.
        }
    }
}
