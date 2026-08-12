<?php
declare(strict_types=1);

final class Jwt
{
    public static function encode(array $payload, ?string $secret = null): string
    {
        $header = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $body = self::base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', "{$header}.{$body}", $secret ?? config('app.jwt_secret'), true);
        return "{$header}.{$body}." . self::base64UrlEncode($signature);
    }

    public static function decode(string $token, ?string $secret = null): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $body, $signature] = $parts;
        $expected = self::base64UrlEncode(hash_hmac('sha256', "{$header}.{$body}", $secret ?? config('app.jwt_secret'), true));
        if (!hash_equals($expected, $signature)) {
            return null;
        }
        $payload = json_decode(self::base64UrlDecode($body), true);
        if (!$payload || (($payload['exp'] ?? PHP_INT_MAX) < time())) {
            return null;
        }
        return $payload;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

