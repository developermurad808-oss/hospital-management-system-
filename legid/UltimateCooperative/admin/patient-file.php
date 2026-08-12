<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
Auth::requireLogin();

$canViewPatientFile = Auth::can('patients.manage') || Auth::can('emr.manage') || Auth::can('billing.manage') || Auth::can('lab.manage') || Auth::can('pharmacy.manage') || Auth::can('admissions.manage');
if (!$canViewPatientFile) {
    http_response_code(403);
    require dirname(__DIR__) . '/templates/403.php';
    exit;
}

ensure_patient_genotype_column();

$title = 'Patient File Records';
$search = trim($_GET['file'] ?? $_GET['q'] ?? '');
$patientId = isset($_GET['patient']) ? (int) $_GET['patient'] : 0;
$patient = null;
$matches = [];

if ($patientId > 0) {
    $patient = Database::query('SELECT * FROM patients WHERE id=? LIMIT 1', [$patientId])->fetch();
} elseif ($search !== '') {
    $patient = Database::query('SELECT * FROM patients WHERE patient_no=? LIMIT 1', [$search])->fetch();
    if (!$patient) {
        $matches = Database::query(
            'SELECT * FROM patients WHERE patient_no LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? ORDER BY id DESC LIMIT 20',
            ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"]
        )->fetchAll();
        if (count($matches) === 1) {
            $patient = $matches[0];
            $matches = [];
        }
    }
}

$visits = $consultations = $labResults = $prescriptions = $appointments = $admissions = $invoices = $payments = [];
if ($patient) {
    $patientId = (int) $patient['id'];
    $visits = Database::query(
        'SELECT v.*, u.name doctor_name FROM visits v LEFT JOIN users u ON u.id=v.doctor_id WHERE v.patient_id=? ORDER BY v.created_at DESC LIMIT 100',
        [$patientId]
    )->fetchAll();
    $consultations = Database::query(
        'SELECT er.*, v.visit_no, u.name doctor_name FROM emr_records er JOIN visits v ON v.id=er.visit_id JOIN users u ON u.id=er.doctor_id WHERE er.patient_id=? ORDER BY er.created_at DESC LIMIT 100',
        [$patientId]
    )->fetchAll();
    $labResults = Database::query(
        'SELECT lr.request_no, lr.status, lr.created_at, v.visit_no, u.name doctor_name, lt.name test_name, lt.sample_type, lt.normal_range, lri.result_value, lri.result_notes, lri.completed_at
         FROM lab_requests lr
         JOIN visits v ON v.id=lr.visit_id
         JOIN users u ON u.id=lr.doctor_id
         JOIN lab_request_items lri ON lri.lab_request_id=lr.id
         JOIN lab_tests lt ON lt.id=lri.lab_test_id
         WHERE lr.patient_id=?
         ORDER BY lr.created_at DESC, lri.id DESC LIMIT 120',
        [$patientId]
    )->fetchAll();
    $prescriptions = Database::query(
        'SELECT pr.id prescription_id, pr.status, pr.notes, pr.created_at, v.visit_no, u.name doctor_name, pi.medicine_name, pi.dosage, pi.frequency, pi.duration, pi.quantity, pi.dispensed_quantity
         FROM prescriptions pr
         JOIN visits v ON v.id=pr.visit_id
         JOIN users u ON u.id=pr.doctor_id
         JOIN prescription_items pi ON pi.prescription_id=pr.id
         WHERE pr.patient_id=?
         ORDER BY pr.created_at DESC, pi.id DESC LIMIT 120',
        [$patientId]
    )->fetchAll();
    $appointments = Database::query(
        'SELECT a.*, u.name doctor_name, d.name department_name FROM appointments a LEFT JOIN users u ON u.id=a.doctor_id LEFT JOIN departments d ON d.id=a.department_id WHERE a.patient_id=? ORDER BY a.appointment_at DESC LIMIT 100',
        [$patientId]
    )->fetchAll();
    $admissions = Database::query(
        'SELECT a.*, b.bed_no, w.name ward_name, w.type ward_type, u.name admitted_by_name FROM admissions a JOIN beds b ON b.id=a.bed_id JOIN wards w ON w.id=b.ward_id LEFT JOIN users u ON u.id=a.admitted_by WHERE a.patient_id=? ORDER BY a.admitted_at DESC LIMIT 80',
        [$patientId]
    )->fetchAll();
    $invoices = Database::query(
        'SELECT i.*, v.visit_no FROM invoices i LEFT JOIN visits v ON v.id=i.visit_id WHERE i.patient_id=? ORDER BY i.created_at DESC LIMIT 100',
        [$patientId]
    )->fetchAll();
    $payments = Database::query(
        'SELECT pay.*, i.invoice_no FROM payments pay JOIN invoices i ON i.id=pay.invoice_id WHERE i.patient_id=? ORDER BY pay.paid_at DESC LIMIT 100',
        [$patientId]
    )->fetchAll();
}

require dirname(__DIR__) . '/templates/header.php';
?>
<section class="panel">
  <h2>Find Patient File</h2>
  <form class="search-row global-search" method="get">
    <input name="file" value="<?= e($search ?: ($patient['patient_no'] ?? '')) ?>" placeholder="Enter patient file number, e.g. PAT-2026-00001" required>
    <button class="button primary">Open records</button>
  </form>
</section>

<?php if ($search !== '' && !$patient && !$matches): ?>
  <div class="alert danger">No patient file found for "<?= e($search) ?>".</div>
<?php endif; ?>

<?php if ($matches): ?>
<section class="panel">
  <div class="panel-head"><h2>Matching Files</h2><span class="pill"><?= count($matches) ?> found</span></div>
  <div class="table-wrap"><table><thead><tr><th>File No</th><th>Name</th><th>Phone</th><th>Blood</th><th></th></tr></thead><tbody>
    <?php foreach ($matches as $match): ?>
      <tr><td><?= e($match['patient_no']) ?></td><td><?= e($match['first_name'].' '.$match['last_name']) ?></td><td><?= e($match['phone']) ?></td><td><?= e(($match['blood_group'] ?: 'Unknown') . ' / ' . ($match['genotype'] ?: 'Unknown')) ?></td><td><a class="button small" href="<?= app_url('admin/patient-file.php?patient=' . (int) $match['id']) ?>">Open file</a></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
</section>
<?php endif; ?>

<?php if ($patient): ?>
<section class="stat-grid compact-stats">
  <article class="stat-card"><span>Visits</span><strong><?= count($visits) ?></strong></article>
  <article class="stat-card"><span>Consultations</span><strong><?= count($consultations) ?></strong></article>
  <article class="stat-card"><span>Lab Records</span><strong><?= count($labResults) ?></strong></article>
</section>

<section class="dashboard-grid">
  <article class="panel profile-panel">
    <h2>Patient Details</h2>
    <div class="settings-list">
      <strong>File number</strong><span><?= e($patient['patient_no']) ?></span>
      <strong>Name</strong><span><?= e($patient['first_name'].' '.$patient['last_name']) ?></span>
      <strong>Gender</strong><span><?= e(ucfirst((string) $patient['gender'])) ?></span>
      <strong>Date of birth</strong><span><?= e($patient['date_of_birth'] ?: 'Not recorded') ?></span>
      <strong>Blood / Genotype</strong><span><?= e(($patient['blood_group'] ?: 'Unknown') . ' / ' . ($patient['genotype'] ?: 'Unknown')) ?></span>
      <strong>Allergies</strong><span><?= e($patient['allergies'] ?: 'None recorded') ?></span>
      <strong>Phone</strong><span><?= e($patient['phone'] ?: 'Not recorded') ?></span>
      <strong>Address</strong><span><?= e($patient['address'] ?: 'Not recorded') ?></span>
    </div>
  </article>
  <article class="panel">
    <h2>Visit History</h2>
    <div class="table-wrap"><table><thead><tr><th>Visit No</th><th>Status</th><th>Priority</th><th>Doctor</th><th>Date</th></tr></thead><tbody>
      <?php foreach ($visits as $visit): ?><tr><td><?= e($visit['visit_no']) ?></td><td><span class="pill"><?= e($visit['status']) ?></span></td><td><?= e($visit['priority']) ?></td><td><?= e($visit['doctor_name'] ?? 'Unassigned') ?></td><td><?= e($visit['created_at']) ?></td></tr><?php endforeach; ?>
      <?php if (!$visits): ?><tr><td colspan="5">No visits recorded.</td></tr><?php endif; ?>
    </tbody></table></div>
  </article>
</section>

<section class="panel">
  <div class="panel-head"><h2>Consultation Records</h2><span class="pill"><?= count($consultations) ?> records</span></div>
  <div class="order-cards">
    <?php foreach ($consultations as $record): ?>
      <article class="order-card">
        <header><strong><?= e($record['visit_no']) ?></strong><span><?= e($record['created_at']) ?></span></header>
        <dl><dt>Doctor</dt><dd><?= e($record['doctor_name']) ?></dd><dt>Symptoms</dt><dd><?= e($record['symptoms'] ?: 'Not recorded') ?></dd><dt>Diagnosis</dt><dd><?= e($record['diagnosis'] ?: 'Not recorded') ?></dd><dt>Treatment</dt><dd><?= e($record['treatment_plan'] ?: 'Not recorded') ?></dd><dt>Notes</dt><dd><?= e($record['progress_notes'] ?: 'Not recorded') ?></dd></dl>
      </article>
    <?php endforeach; ?>
    <?php if (!$consultations): ?><p class="muted">No consultation records yet.</p><?php endif; ?>
  </div>
</section>

<section class="dashboard-grid">
  <article class="panel">
    <div class="panel-head"><h2>Laboratory Results</h2><span class="pill"><?= count($labResults) ?> items</span></div>
    <div class="table-wrap"><table><thead><tr><th>Request</th><th>Test</th><th>Status</th><th>Result</th><th>Completed</th></tr></thead><tbody>
      <?php foreach ($labResults as $lab): ?><tr><td><?= e($lab['request_no']) ?><br><span class="muted"><?= e($lab['visit_no']) ?></span></td><td><?= e($lab['test_name']) ?><br><span class="muted"><?= e($lab['sample_type']) ?></span></td><td><span class="pill"><?= e($lab['status']) ?></span></td><td><?= e($lab['result_value'] ?: 'Pending') ?><br><span class="muted"><?= e($lab['result_notes'] ?: ($lab['normal_range'] ? 'Normal: ' . $lab['normal_range'] : '')) ?></span></td><td><?= e($lab['completed_at'] ?: 'Pending') ?></td></tr><?php endforeach; ?>
      <?php if (!$labResults): ?><tr><td colspan="5">No laboratory records.</td></tr><?php endif; ?>
    </tbody></table></div>
  </article>
  <article class="panel">
    <div class="panel-head"><h2>Prescriptions</h2><span class="pill"><?= count($prescriptions) ?> items</span></div>
    <div class="table-wrap"><table><thead><tr><th>Visit</th><th>Medicine</th><th>Instruction</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($prescriptions as $rx): ?><tr><td><?= e($rx['visit_no']) ?><br><span class="muted"><?= e($rx['created_at']) ?></span></td><td><?= e($rx['medicine_name']) ?></td><td><?= e(trim($rx['dosage'].' '.$rx['frequency'].' '.$rx['duration'])) ?><br><span class="muted">Qty: <?= e((string) $rx['quantity']) ?>, dispensed: <?= e((string) $rx['dispensed_quantity']) ?></span></td><td><span class="pill"><?= e($rx['status']) ?></span></td></tr><?php endforeach; ?>
      <?php if (!$prescriptions): ?><tr><td colspan="4">No prescriptions recorded.</td></tr><?php endif; ?>
    </tbody></table></div>
  </article>
</section>

<section class="dashboard-grid">
  <article class="panel">
    <h2>Appointments</h2>
    <div class="table-wrap"><table><thead><tr><th>No</th><th>Date</th><th>Doctor</th><th>Status</th><th>Reason</th></tr></thead><tbody>
      <?php foreach ($appointments as $appointment): ?><tr><td><?= e($appointment['appointment_no']) ?></td><td><?= e($appointment['appointment_at']) ?></td><td><?= e($appointment['doctor_name'] ?? 'Unassigned') ?></td><td><span class="pill"><?= e($appointment['status']) ?></span></td><td><?= e($appointment['reason'] ?: '') ?></td></tr><?php endforeach; ?>
      <?php if (!$appointments): ?><tr><td colspan="5">No appointments recorded.</td></tr><?php endif; ?>
    </tbody></table></div>
  </article>
  <article class="panel">
    <h2>Admissions</h2>
    <div class="table-wrap"><table><thead><tr><th>No</th><th>Ward / Bed</th><th>Status</th><th>Admitted</th><th>Discharged</th></tr></thead><tbody>
      <?php foreach ($admissions as $admission): ?><tr><td><?= e($admission['admission_no']) ?></td><td><?= e($admission['ward_name'].' / '.$admission['bed_no']) ?></td><td><span class="pill"><?= e($admission['status']) ?></span></td><td><?= e($admission['admitted_at']) ?></td><td><?= e($admission['discharged_at'] ?: 'Active') ?></td></tr><?php endforeach; ?>
      <?php if (!$admissions): ?><tr><td colspan="5">No admissions recorded.</td></tr><?php endif; ?>
    </tbody></table></div>
  </article>
</section>

<section class="dashboard-grid">
  <article class="panel">
    <h2>Billing</h2>
    <div class="table-wrap"><table><thead><tr><th>Invoice</th><th>Visit</th><th>Total</th><th>Paid</th><th>Status</th></tr></thead><tbody>
      <?php foreach ($invoices as $invoice): ?><tr><td><?= e($invoice['invoice_no']) ?></td><td><?= e($invoice['visit_no'] ?? 'No visit') ?></td><td>NGN <?= money($invoice['total']) ?></td><td>NGN <?= money($invoice['paid']) ?></td><td><span class="pill"><?= e($invoice['status']) ?></span></td></tr><?php endforeach; ?>
      <?php if (!$invoices): ?><tr><td colspan="5">No invoices recorded.</td></tr><?php endif; ?>
    </tbody></table></div>
  </article>
  <article class="panel">
    <h2>Payments</h2>
    <div class="table-wrap"><table><thead><tr><th>Receipt</th><th>Invoice</th><th>Amount</th><th>Method</th><th>Date</th></tr></thead><tbody>
      <?php foreach ($payments as $payment): ?><tr><td><?= e($payment['receipt_no']) ?></td><td><?= e($payment['invoice_no']) ?></td><td>NGN <?= money($payment['amount']) ?></td><td><?= e($payment['method']) ?></td><td><?= e($payment['paid_at']) ?></td></tr><?php endforeach; ?>
      <?php if (!$payments): ?><tr><td colspan="5">No payments recorded.</td></tr><?php endif; ?>
    </tbody></table></div>
  </article>
</section>
<?php endif; ?>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
