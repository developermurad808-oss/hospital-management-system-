<?php
declare(strict_types=1);

function next_number(string $prefix, string $table, string $column): string
{
    $year = date('Y');
    $row = Database::query("SELECT {$column} FROM {$table} WHERE {$column} LIKE ? ORDER BY id DESC LIMIT 1", ["{$prefix}-{$year}-%"])->fetch();
    $next = 1;
    if ($row && preg_match('/-(\d+)$/', $row[$column], $match)) {
        $next = ((int) $match[1]) + 1;
    }
    return sprintf('%s-%s-%05d', $prefix, $year, $next);
}

function upload_file(array $file, string $folder): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed.');
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'];
    $mime = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, and PDF files are allowed.');
    }
    $dir = dirname(__DIR__) . '/uploads/' . trim($folder, '/');
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    move_uploaded_file($file['tmp_name'], $dir . '/' . $name);
    return 'uploads/' . trim($folder, '/') . '/' . $name;
}

function money(float|int|string $amount): string
{
    return number_format((float) $amount, 2);
}

function ensure_patient_genotype_column(): void
{
    static $checked = false;
    if ($checked || !config('app.installed')) {
        return;
    }
    $checked = true;
    try {
        $column = Database::query('SHOW COLUMNS FROM patients LIKE "genotype"')->fetch();
        if (!$column) {
            Database::query('ALTER TABLE patients ADD genotype VARCHAR(8) NULL AFTER blood_group');
        }
    } catch (Throwable) {
        // The installer creates this column for new systems; this keeps older installs compatible.
    }
}

function ensure_medicine_stock_type_column(): void
{
    static $checked = false;
    if ($checked || !config('app.installed')) {
        return;
    }
    $checked = true;
    try {
        $column = Database::query('SHOW COLUMNS FROM medicines LIKE "stock_type"')->fetch();
        if (!$column) {
            Database::query('ALTER TABLE medicines ADD stock_type VARCHAR(30) NULL AFTER unit');
        }
    } catch (Throwable) {
        // New installations include this column; this protects older installed databases.
    }
}

function ensure_patient_login_permission(): void
{
    if (!config('app.installed')) {
        return;
    }
    try {
        Database::query('INSERT IGNORE INTO roles (name, slug) VALUES ("Patient", "patient")');
        Database::query('INSERT IGNORE INTO permissions (name, slug) VALUES ("View Dashboard", "dashboard.view")');
        Database::query(
            'INSERT IGNORE INTO role_permissions (role_id, permission_id)
             SELECT r.id, p.id FROM roles r JOIN permissions p
             WHERE r.slug = "patient" AND p.slug = "dashboard.view"'
        );
    } catch (Throwable) {
        // Auth::can also allows patient dashboard access as a code-level fallback.
    }
}
