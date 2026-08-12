<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

$path = trim($_GET['path'] ?? '', '/');
$method = $_SERVER['REQUEST_METHOD'];
$body = json_decode(file_get_contents('php://input') ?: '[]', true) ?: $_POST;

try {
    if ($path === 'auth/login' && $method === 'POST') {
        if (!Auth::attempt($body['email'] ?? '', $body['password'] ?? '')) {
            json_response(['error' => 'Invalid credentials'], 422);
        }
        $user = Auth::user();
        json_response(['token' => Jwt::encode(['sub' => $user['id'], 'role' => $user['role_slug'], 'exp' => time() + 86400]), 'user' => $user]);
    }

    if ($path === 'patients/lookup' && $method === 'GET') {
        ApiAuth::require('patients.manage');
        ensure_patient_genotype_column();
        $q = trim($_GET['q'] ?? '');
        $rows = Database::query('SELECT id, patient_no, first_name, last_name, blood_group, genotype, phone FROM patients WHERE patient_no LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? LIMIT 15', ["%{$q}%","%{$q}%","%{$q}%","%{$q}%"])->fetchAll();
        json_response(['data' => $rows]);
    }

    if ($path === 'patients' && $method === 'POST') {
        $user = ApiAuth::require('patients.manage');
        ensure_patient_genotype_column();
        $patientNo = next_number('PAT', 'patients', 'patient_no');
        Database::query('INSERT INTO patients (patient_no, first_name, last_name, gender, phone, email, blood_group, genotype, allergies, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$patientNo, $body['first_name'], $body['last_name'], $body['gender'], $body['phone'] ?? null, $body['email'] ?? null, $body['blood_group'] ?? null, $body['genotype'] ?? null, $body['allergies'] ?? null, $body['address'] ?? null]);
        $id = Database::connection()->lastInsertId();
        Auth::audit('api_patient_created', 'patients', (int)$id);
        json_response(['data' => ['id' => $id, 'patient_no' => $patientNo, 'created_by' => $user['id']]], 201);
    }

    if ($path === 'dashboard/stats' && $method === 'GET') {
        ApiAuth::require('dashboard.view');
        json_response(['data' => [
            'patients' => (int) Database::query('SELECT COUNT(*) total FROM patients')->fetch()['total'],
            'doctors' => (int) Database::query('SELECT COUNT(*) total FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug="doctor"')->fetch()['total'],
            'appointments_today' => (int) Database::query('SELECT COUNT(*) total FROM appointments WHERE DATE(appointment_at)=CURDATE()')->fetch()['total'],
            'revenue_today' => (float) Database::query('SELECT COALESCE(SUM(amount),0) total FROM payments WHERE DATE(paid_at)=CURDATE()')->fetch()['total'],
            'occupied_beds' => (int) Database::query('SELECT COUNT(*) total FROM beds WHERE status="occupied"')->fetch()['total'],
        ]]);
    }

    if ($path === 'reports/summary' && $method === 'GET') {
        Auth::requireLogin();
        Auth::requirePermission('reports.manage');
        json_response(['data' => [
            'patients' => Database::query('SELECT blood_group, COUNT(*) total FROM patients GROUP BY blood_group')->fetchAll(),
            'finance' => Database::query('SELECT DATE(paid_at) date, SUM(amount) revenue FROM payments GROUP BY DATE(paid_at) ORDER BY date DESC LIMIT 30')->fetchAll(),
            'lab' => Database::query('SELECT status, COUNT(*) total FROM lab_requests GROUP BY status')->fetchAll(),
            'pharmacy_low_stock' => Database::query('SELECT m.name, COALESCE(SUM(b.quantity),0) quantity FROM medicines m LEFT JOIN medicine_batches b ON b.medicine_id=m.id GROUP BY m.id HAVING quantity <= m.reorder_level')->fetchAll(),
        ]]);
    }

    json_response(['error' => 'Endpoint not found', 'path' => $path], 404);
} catch (Throwable $exception) {
    json_response(['error' => 'Server error', 'message' => $exception->getMessage()], 500);
}
