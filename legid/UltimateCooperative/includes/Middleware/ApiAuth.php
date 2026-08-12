<?php
declare(strict_types=1);

final class ApiAuth
{
    public static function user(): ?array
    {
        if (Auth::check()) {
            return Auth::user();
        }
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }
        $payload = Jwt::decode(substr($header, 7));
        if (!$payload) {
            return null;
        }
        return Database::query(
            'SELECT u.id, u.name, u.email, u.role_id, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.status = "active"',
            [$payload['sub']]
        )->fetch() ?: null;
    }

    public static function require(string $permission): array
    {
        $user = self::user();
        if (!$user) {
            json_response(['error' => 'Unauthenticated'], 401);
        }
        if ($user['role_slug'] !== 'super_admin') {
            $allowed = Database::query(
                'SELECT COUNT(*) AS total FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ? AND p.slug = ?',
                [$user['role_id'], $permission]
            )->fetch()['total'] ?? 0;
            if ((int) $allowed === 0) {
                json_response(['error' => 'Forbidden'], 403);
            }
        }
        $_SESSION['user'] = $user;
        return $user;
    }
}
