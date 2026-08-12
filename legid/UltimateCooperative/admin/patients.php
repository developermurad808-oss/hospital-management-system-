<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
Auth::requireLogin(); Auth::requirePermission('patients.manage');
ensure_patient_genotype_column();
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientNo = next_number('PAT', 'patients', 'patient_no');
    Database::query('INSERT INTO patients (patient_no, first_name, last_name, gender, date_of_birth, blood_group, genotype, allergies, phone, email, address, emergency_name, emergency_phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
        $patientNo, $_POST['first_name'], $_POST['last_name'], $_POST['gender'], $_POST['date_of_birth'] ?: null, $_POST['blood_group'], $_POST['genotype'], $_POST['allergies'], $_POST['phone'], $_POST['email'], $_POST['address'], $_POST['emergency_name'], $_POST['emergency_phone']
    ]);
    $patientId = Database::connection()->lastInsertId();
    Database::query('INSERT INTO visits (visit_no, patient_id, status, priority) VALUES (?, ?, "queue", ?)', [next_number('VIS', 'visits', 'visit_no'), $patientId, $_POST['priority'] ?? 'normal']);
    Auth::audit('patient_registered', 'patients', (int) $patientId);
    $message = "Patient registered with file number {$patientNo}.";
}
$q = trim($_GET['q'] ?? '');
$patients = $q ? Database::query('SELECT * FROM patients WHERE patient_no LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? ORDER BY id DESC LIMIT 30', ["%{$q}%","%{$q}%","%{$q}%","%{$q}%"])->fetchAll() : Database::query('SELECT * FROM patients ORDER BY id DESC LIMIT 20')->fetchAll();
$title = 'Patient Management';
require dirname(__DIR__) . '/templates/header.php';
?>
<?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
<section class="split">
  <article class="panel">
    <h2>Find Patient by File Number</h2>
    <form class="search-row" method="get"><input name="q" value="<?= e($q) ?>" placeholder="Enter file number, name, or phone"><button class="button primary">Search</button></form>
    <div id="patientLookup" class="lookup-result"></div>
    <div class="table-wrap"><table><thead><tr><th>File No</th><th>Name</th><th>Blood</th><th>Genotype</th><th>Phone</th><th></th></tr></thead><tbody>
      <?php foreach ($patients as $patient): ?><tr><td><?= e($patient['patient_no']) ?></td><td><?= e($patient['first_name'].' '.$patient['last_name']) ?></td><td><?= e($patient['blood_group']) ?></td><td><?= e($patient['genotype'] ?? '') ?></td><td><?= e($patient['phone']) ?></td><td><a class="button small" href="patient-file.php?patient=<?= (int)$patient['id'] ?>">Open file</a></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </article>
  <article class="panel">
    <h2>Register New Patient</h2>
    <form method="post" class="form-grid">
      <input name="first_name" placeholder="First name" required><input name="last_name" placeholder="Last name" required>
      <select name="gender" required><option value="">Gender</option><option>male</option><option>female</option><option>other</option></select>
      <input name="date_of_birth" type="date"><select name="blood_group"><option value="">Blood group</option><option>A+</option><option>A-</option><option>B+</option><option>B-</option><option>AB+</option><option>AB-</option><option>O+</option><option>O-</option></select>
      <select name="genotype"><option value="">Genotype</option><option>AA</option><option>AS</option><option>SS</option><option>AC</option><option>SC</option></select>
      <input name="phone" placeholder="Phone"><input name="email" type="email" placeholder="Email">
      <textarea name="address" placeholder="Address"></textarea><textarea name="allergies" placeholder="Allergies"></textarea>
      <input name="emergency_name" placeholder="Emergency contact"><input name="emergency_phone" placeholder="Emergency phone">
      <select name="priority"><option>normal</option><option>urgent</option><option>emergency</option></select>
      <button class="button primary">Register and queue</button>
    </form>
  </article>
</section>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
