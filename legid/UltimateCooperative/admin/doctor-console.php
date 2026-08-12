<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
Auth::requireLogin();
Auth::requirePermission('emr.manage');
ensure_patient_genotype_column();

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'consultation') {
    $next = ClinicalWorkflow::saveConsultation($_POST, Auth::user());
    $message = 'Consultation saved. Patient moved to ' . strtoupper($next) . ' queue and billing invoice was prepared.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'lab_test') {
    Database::query('INSERT INTO lab_tests (name, sample_type, price, normal_range) VALUES (?, ?, ?, ?)', [$_POST['name'], $_POST['sample_type'], $_POST['price'], $_POST['normal_range']]);
    $message = 'New lab test added.';
}

$doctorFilter = Auth::user()['role_slug'] === 'doctor' ? ' AND (v.doctor_id IS NULL OR v.doctor_id=' . (int) Auth::user()['id'] . ')' : '';
$visits = Database::query('SELECT v.*, p.patient_no, CONCAT(p.first_name," ",p.last_name) patient_name, p.blood_group, p.genotype, p.allergies FROM visits v JOIN patients p ON p.id=v.patient_id WHERE v.status IN ("queue","consulting","lab","pharmacy","billing")' . $doctorFilter . ' ORDER BY FIELD(v.priority,"emergency","urgent","normal"), v.created_at DESC LIMIT 30')->fetchAll();
$tests = Database::query('SELECT * FROM lab_tests ORDER BY name')->fetchAll();
$medicines = Database::query('SELECT name, selling_price FROM medicines ORDER BY name')->fetchAll();
$title = 'Doctor Console';
require dirname(__DIR__) . '/templates/header.php';
?>
<?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
<section class="doctor-layout">
  <article class="panel">
    <div class="panel-head"><h2>Patient Queue</h2><span class="pill"><?= count($visits) ?> active</span></div>
    <div class="queue-cards">
      <?php foreach ($visits as $visit): ?>
        <button class="queue-card" data-visit="<?= (int) $visit['id'] ?>">
          <strong><?= e($visit['patient_no']) ?></strong>
          <span><?= e($visit['patient_name']) ?></span>
          <em><?= e($visit['status'].' / '.$visit['priority']) ?></em>
          <small>Blood: <?= e($visit['blood_group'] ?: 'Unknown') ?> | Genotype: <?= e($visit['genotype'] ?: 'Unknown') ?> | Allergies: <?= e($visit['allergies'] ?: 'None') ?></small>
        </button>
      <?php endforeach; ?>
    </div>
  </article>
  <article class="panel">
    <h2>Consultation, Tests, Treatment</h2>
    <form method="post" class="form-grid wide" id="doctorForm">
      <input type="hidden" name="action" value="consultation">
      <select name="visit_id" id="visitSelect" required><option value="">Select patient from queue</option><?php foreach ($visits as $visit): ?><option value="<?= (int) $visit['id'] ?>"><?= e($visit['visit_no'].' - '.$visit['patient_no'].' - '.$visit['patient_name']) ?></option><?php endforeach; ?></select>
      <input name="consultation_fee" type="number" step="0.01" value="0" placeholder="Consultation fee">
      <textarea name="symptoms" placeholder="Symptoms"></textarea><textarea name="diagnosis" placeholder="Diagnosis"></textarea>
      <textarea name="treatment_plan" placeholder="Treatment plan"></textarea><textarea name="progress_notes" placeholder="Progress notes"></textarea>
      <fieldset><legend>Order Laboratory Tests</legend><?php foreach ($tests as $test): ?><label class="checkline"><input type="checkbox" name="lab_test_id[]" value="<?= (int) $test['id'] ?>"> <?= e($test['name']) ?> <small>₦<?= money($test['price']) ?></small></label><?php endforeach; ?></fieldset>
      <fieldset><legend>Prescription for Pharmacy</legend>
        <div class="rx-grid"><input name="medicine_name[]" list="medicineList" placeholder="Medicine"><input name="dosage[]" placeholder="Dosage"><input name="frequency[]" placeholder="Frequency"><input name="duration[]" placeholder="Duration"><input name="quantity[]" type="number" value="1" min="1"></div>
        <datalist id="medicineList"><?php foreach ($medicines as $m): ?><option value="<?= e($m['name']) ?>"></option><?php endforeach; ?></datalist>
        <textarea name="rx_notes" placeholder="Prescription notes"></textarea>
      </fieldset>
      <button class="button primary">Save and send to Lab / Pharmacy / Billing</button>
    </form>
  </article>
</section>
<section class="panel">
  <h2>Add Laboratory Test</h2>
  <form method="post" class="form-grid">
    <input type="hidden" name="action" value="lab_test">
    <input name="name" placeholder="Test name, e.g. Blood Group" required><input name="sample_type" placeholder="Sample type" required>
    <input name="price" type="number" step="0.01" placeholder="Price" required><input name="normal_range" placeholder="Normal range">
    <button class="button primary">Add test</button>
  </form>
</section>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
