<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
Auth::requireLogin();
Auth::requirePermission('lab.manage');

$message = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'create_request') {
            if (empty($_POST['visit_id']) || empty($_POST['doctor_id']) || empty($_POST['lab_test_id'])) {
                throw new RuntimeException('Select visit, doctor, and at least one test.');
            }
            $visit = Database::query('SELECT * FROM visits WHERE id=?', [$_POST['visit_id']])->fetch();
            if (!$visit) {
                throw new RuntimeException('Selected visit was not found.');
            }
            Database::query('INSERT INTO lab_requests (request_no, visit_id, patient_id, doctor_id, status) VALUES (?, ?, ?, ?, "requested")', [
                next_number('LAB', 'lab_requests', 'request_no'),
                $visit['id'],
                $visit['patient_id'],
                $_POST['doctor_id'],
            ]);
            $requestId = Database::connection()->lastInsertId();
            foreach ($_POST['lab_test_id'] as $testId) {
                Database::query('INSERT INTO lab_request_items (lab_request_id, lab_test_id) VALUES (?, ?)', [$requestId, $testId]);
            }
            Database::query('UPDATE visits SET status="lab" WHERE id=?', [$visit['id']]);
            $message = 'Laboratory request created.';
        } elseif ($action === 'lab_test') {
            if (trim($_POST['name'] ?? '') === '') {
                throw new RuntimeException('Test name is required.');
            }
            Database::query('INSERT INTO lab_tests (name, sample_type, price, normal_range) VALUES (?, ?, ?, ?)', [
                trim($_POST['name']),
                trim($_POST['sample_type']),
                (float) $_POST['price'],
                trim($_POST['normal_range'] ?? ''),
            ]);
            $message = 'Laboratory test added.';
        } elseif ($action === 'acknowledge') {
            $request = Database::query('SELECT lr.*, p.patient_no, CONCAT(p.first_name," ",p.last_name) patient_name FROM lab_requests lr JOIN patients p ON p.id=lr.patient_id WHERE lr.id=?', [$_POST['request_id']])->fetch();
            if ($request) {
                Database::query('UPDATE lab_requests SET status="processing" WHERE id=? AND status="requested"', [$_POST['request_id']]);
                Database::query('INSERT INTO notifications (user_id, channel, title, message, status) VALUES (?, "in_app", ?, ?, "sent")', [$request['doctor_id'], 'Laboratory acknowledged request', 'Lab has seen request ' . $request['request_no'] . ' for ' . $request['patient_no'] . ' ' . $request['patient_name'] . '.']);
                $message = 'Doctor has been notified that Laboratory saw this request.';
            }
        } else {
            Database::query('UPDATE lab_request_items SET result_value=?, result_notes=?, technician_id=?, completed_at=NOW() WHERE id=?', [$_POST['result_value'], $_POST['result_notes'], Auth::user()['id'], $_POST['item_id']]);
            Database::query('UPDATE lab_requests SET status="completed" WHERE id=? AND NOT EXISTS (SELECT 1 FROM lab_request_items WHERE lab_request_id=? AND completed_at IS NULL)', [$_POST['request_id'], $_POST['request_id']]);
            $request = Database::query('SELECT * FROM lab_requests WHERE id=?', [$_POST['request_id']])->fetch();
            if ($request) {
                Database::query('INSERT INTO notifications (user_id, channel, title, message, status) VALUES (?, "in_app", "Laboratory result completed", ?, "sent")', [$request['doctor_id'], 'Lab results are ready for request ' . $request['request_no'] . '.']);
            }
            $message = 'Lab result saved and doctor notified.';
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$tests = Database::query('SELECT * FROM lab_tests ORDER BY name')->fetchAll();
$testTemplates = [
    ['Full Blood Count', 'Blood', '2500', 'Varies by parameter'],
    ['Blood Group', 'Blood', '1500', 'A/B/AB/O Rh +/-'],
    ['Malaria Parasite', 'Blood', '1800', 'Negative'],
    ['Urinalysis', 'Urine', '1200', 'Normal'],
    ['Custom Test', '', '', ''],
];
$visits = Database::query('SELECT v.id, v.visit_no, p.patient_no, CONCAT(p.first_name," ",p.last_name) patient_name FROM visits v JOIN patients p ON p.id=v.patient_id WHERE v.status <> "closed" ORDER BY v.created_at DESC LIMIT 100')->fetchAll();
$doctors = Database::query('SELECT u.id, u.name FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug IN ("doctor","super_admin","hospital_admin") ORDER BY u.name')->fetchAll();
$requests = Database::query('SELECT lr.*, lri.id item_id, lri.result_value, lri.result_notes, lt.name test_name, lt.sample_type, lt.normal_range, p.patient_no, CONCAT(p.first_name," ",p.last_name) patient_name, u.name doctor_name FROM lab_requests lr JOIN lab_request_items lri ON lri.lab_request_id=lr.id JOIN lab_tests lt ON lt.id=lri.lab_test_id JOIN patients p ON p.id=lr.patient_id JOIN users u ON u.id=lr.doctor_id ORDER BY FIELD(lr.status,"requested","processing","sample_collected","completed","cancelled"), lr.created_at DESC LIMIT 80')->fetchAll();
$title = 'Laboratory Management';
require dirname(__DIR__) . '/templates/header.php';
?>
<?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
<section class="dashboard-grid">
  <article class="panel">
    <h2>Create Laboratory Request</h2>
    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="create_request">
      <select name="visit_id" required><option value="">Select patient visit</option><?php foreach ($visits as $visit): ?><option value="<?= (int) $visit['id'] ?>"><?= e($visit['visit_no'].' - '.$visit['patient_no'].' - '.$visit['patient_name']) ?></option><?php endforeach; ?></select>
      <select name="doctor_id" required><option value="">Referring doctor</option><?php foreach ($doctors as $doctor): ?><option value="<?= (int) $doctor['id'] ?>"><?= e($doctor['name']) ?></option><?php endforeach; ?></select>
      <label class="field-full">Choose laboratory test(s)
        <select name="lab_test_id[]" multiple size="6" required>
          <?php foreach ($tests as $test): ?><option value="<?= (int) $test['id'] ?>"><?= e($test['name']) ?> - NGN <?= money($test['price']) ?></option><?php endforeach; ?>
        </select>
      </label>
      <button class="button primary">Create request</button>
    </form>
  </article>
  <article class="panel">
    <h2>Add Laboratory Test</h2>
    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="lab_test">
      <select id="labTestTemplate">
        <option value="">Choose test template</option>
        <?php foreach ($testTemplates as $template): ?><option value="<?= e(implode('|', $template)) ?>"><?= e($template[0]) ?></option><?php endforeach; ?>
      </select>
      <input id="labTestName" name="name" placeholder="Test name" required>
      <input id="labSampleType" name="sample_type" placeholder="Sample type, e.g. Blood, Urine" required>
      <input id="labTestPrice" name="price" type="number" step="0.01" min="0" placeholder="Price" required>
      <input id="labNormalRange" name="normal_range" placeholder="Normal range">
      <button class="button primary">Add test</button>
    </form>
  </article>
</section>
<section class="panel">
  <div class="panel-head"><h2>Doctor Test Requests</h2><span class="pill"><?= count($requests) ?> requests</span></div>
  <div class="order-cards">
    <?php foreach ($requests as $row): ?>
      <article class="order-card">
        <header><strong><?= e($row['request_no']) ?></strong><span class="pill"><?= e($row['status']) ?></span></header>
        <p><?= e($row['patient_no'].' - '.$row['patient_name']) ?></p>
        <dl><dt>Doctor</dt><dd><?= e($row['doctor_name']) ?></dd><dt>Test</dt><dd><?= e($row['test_name']) ?></dd><dt>Sample</dt><dd><?= e($row['sample_type']) ?></dd><dt>Sent</dt><dd><?= e($row['created_at']) ?></dd></dl>
        <?php if ($row['normal_range']): ?><p class="muted">Normal range: <?= e($row['normal_range']) ?></p><?php endif; ?>
        <div class="order-actions">
          <form method="post"><input type="hidden" name="action" value="acknowledge"><input type="hidden" name="request_id" value="<?= (int) $row['id'] ?>"><button class="button small">Seen</button></form>
          <form method="post" class="inline-form compact-form"><input type="hidden" name="item_id" value="<?= (int) $row['item_id'] ?>"><input type="hidden" name="request_id" value="<?= (int) $row['id'] ?>"><input name="result_value" placeholder="Result" value="<?= e($row['result_value']) ?>"><input name="result_notes" placeholder="Notes" value="<?= e($row['result_notes']) ?>"><button class="button small primary">Save result</button></form>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$requests): ?><p class="muted">No laboratory requests yet.</p><?php endif; ?>
  </div>
</section>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
