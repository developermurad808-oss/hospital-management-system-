<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
Auth::requireLogin(); Auth::requirePermission('emr.manage');
$user = Auth::user(); $message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $next = ClinicalWorkflow::saveConsultation($_POST, $user);
    $message = 'Consultation saved. Patient moved to ' . strtoupper($next) . ' queue and billing invoice was prepared.';
}
$visits = Database::query('SELECT v.*, p.patient_no, CONCAT(p.first_name," ",p.last_name) patient_name FROM visits v JOIN patients p ON p.id=v.patient_id WHERE v.status IN ("queue","consulting","lab","pharmacy","billing") ORDER BY v.created_at DESC LIMIT 20')->fetchAll();
$tests = Database::query('SELECT * FROM lab_tests ORDER BY name')->fetchAll();
$title = 'Electronic Medical Records';
require dirname(__DIR__) . '/templates/header.php';
?>
<?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
<section class="panel">
  <h2>Doctor Consultation</h2>
  <form method="post" class="form-grid wide">
    <select name="visit_id" required><option value="">Select patient in queue</option><?php foreach ($visits as $visit): ?><option value="<?= (int)$visit['id'] ?>"><?= e($visit['visit_no'].' - '.$visit['patient_no'].' - '.$visit['patient_name']) ?></option><?php endforeach; ?></select>
    <input name="consultation_fee" type="number" step="0.01" value="0" placeholder="Consultation fee">
    <textarea name="symptoms" placeholder="Symptoms"></textarea><textarea name="diagnosis" placeholder="Diagnosis"></textarea>
    <textarea name="treatment_plan" placeholder="Treatment / clinical plan"></textarea><textarea name="progress_notes" placeholder="Progress notes"></textarea>
    <label class="field-full">Add laboratory test for patient
      <select name="lab_test_id[]" multiple size="6">
        <?php foreach ($tests as $test): ?><option value="<?= (int)$test['id'] ?>"><?= e($test['name']) ?> - NGN <?= money($test['price']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <fieldset><legend>Prescription</legend>
      <div class="rx-grid"><input name="medicine_name[]" placeholder="Medicine"><input name="dosage[]" placeholder="Dosage"><input name="frequency[]" placeholder="Frequency"><input name="duration[]" placeholder="Duration"><input name="quantity[]" type="number" value="1" min="1"></div>
      <textarea name="rx_notes" placeholder="Prescription notes"></textarea>
    </fieldset>
    <button class="button primary">Save consultation and send orders</button>
  </form>
</section>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
