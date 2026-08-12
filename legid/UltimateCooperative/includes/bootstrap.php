<?php
declare(strict_types=1);

$sessionPath = dirname(__DIR__) . '/storage/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0775, true);
}
if (is_writable($sessionPath)) {
    session_save_path($sessionPath);
}

session_start();

$config = require dirname(__DIR__) . '/config.php';
date_default_timezone_set($config['app']['timezone'] ?? 'UTC');

spl_autoload_register(function (string $class): void {
    $paths = [
        __DIR__ . '/' . $class . '.php',
        __DIR__ . '/Middleware/' . $class . '.php',
        dirname(__DIR__) . '/api/controllers/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (is_file($path)) {
            require_once $path;
            return;
        }
    }
});

function config(string $key, mixed $default = null): mixed
{
    global $config;
    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function app_url(string $path = ''): string
{
    $configured = rtrim((string) config('app.url', ''), '/');
    if (config('app.installed') && $configured !== '') {
        return $configured . '/' . ltrim($path, '/');
    }

    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $base = preg_replace('#/(install|admin|api)$#', '', rtrim($scriptDir, '/'));
    $base = $base === '/' ? '' : $base;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return "{$scheme}://{$host}{$base}/" . ltrim($path, '/');
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . app_url($path));
    exit;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_PRETTY_PRINT);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['_csrf'] ?? '', (string) $token)) {
        json_response(['error' => 'Invalid CSRF token'], 419);
    }
}
