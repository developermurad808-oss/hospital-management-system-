<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
Auth::requireLogin();
Auth::requirePermission('admissions.manage');

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'ward') {
        Database::query('INSERT INTO wards (name, type, floor) VALUES (?, ?, ?)', [$_POST['name'], $_POST['type'], $_POST['floor']]);
        $message = 'Ward created.';
    }
    if ($action === 'bed') {
        Database::query('INSERT INTO beds (ward_id, bed_no, status) VALUES (?, ?, ?)', [$_POST['ward_id'], $_POST['bed_no'], $_POST['status']]);
        $message = 'Bed added.';
    }
    if ($action === 'admit') {
        $visit = Database::query('SELECT * FROM visits WHERE id=?', [$_POST['visit_id']])->fetch();
        if ($visit) {
            Database::query('INSERT INTO admissions (admission_no, visit_id, patient_id, bed_id, admitted_by, admitted_at, status) VALUES (?, ?, ?, ?, ?, NOW(), "active")', [next_number('ADM', 'admissions', 'admission_no'), $visit['id'], $visit['patient_id'], $_POST['bed_id'], Auth::user()['id']]);
            Database::query('UPDATE beds SET status="occupied" WHERE id=?', [$_POST['bed_id']]);
            Database::query('UPDATE visits SET status="admitted" WHERE id=?', [$visit['id']]);
            $message = 'Patient admitted and bed marked occupied.';
        }
    }
    if ($action === 'discharge') {
        $admission = Database::query('SELECT * FROM admissions WHERE id=?', [$_POST['admission_id']])->fetch();
        if ($admission) {
            Database::query('UPDATE admissions SET status="discharged", discharged_at=NOW(), discharge_summary=? WHERE id=?', [$_POST['discharge_summary'], $_POST['admission_id']]);
            Database::query('UPDATE beds SET status="available" WHERE id=?', [$admission['bed_id']]);
            Database::query('UPDATE visits SET status="billing" WHERE id=?', [$admission['visit_id']]);
            $message = 'Patient discharged. Visit moved to billing.';
        }
    }
}

$wards = Database::query('SELECT * FROM wards ORDER BY name')->fetchAll();
$beds = Database::query('SELECT b.*, w.name ward, w.type FROM beds b JOIN wards w ON w.id=b.ward_id ORDER BY w.name,b.bed_no')->fetchAll();
$availableBeds = array_filter($beds, fn ($bed) => $bed['status'] === 'available');
$visits = Database::query('SELECT v.id, v.visit_no, p.patient_no, CONCAT(p.first_name," ",p.last_name) patient_name FROM visits v JOIN patients p ON p.id=v.patient_id WHERE v.status IN ("queue","consulting","billing","lab","pharmacy") ORDER BY v.created_at DESC LIMIT 100')->fetchAll();
$admissions = Database::query('SELECT a.*, p.patient_no, CONCAT(p.first_name," ",p.last_name) patient_name, b.bed_no, w.name ward FROM admissions a JOIN patients p ON p.id=a.patient_id JOIN beds b ON b.id=a.bed_id JOIN wards w ON w.id=b.ward_id ORDER BY a.id DESC LIMIT 60')->fetchAll();
$title = 'Bed & Ward Management';
require dirname(__DIR__) . '/templates/header.php';
?>
<?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
<section class="stat-grid">
  <article class="stat-card"><span>Total Beds</span><strong><?= count($beds) ?></strong></article>
  <article class="stat-card"><span>Available</span><strong><?= count($availableBeds) ?></strong></article>
  <article class="stat-card"><span>Occupied</span><strong><?= count(array_filter($beds, fn($b) => $b['status'] === 'occupied')) ?></strong></article>
  <article class="stat-card"><span>Admissions</span><strong><?= count($admissions) ?></strong></article>
</section>
<section class="split">
  <article class="panel">
    <h2>Available Beds and ICU</h2>
    <div class="bed-grid"><?php foreach ($beds as $bed): ?><div class="bed <?= e($bed['status']) ?>"><strong><?= e($bed['bed_no']) ?></strong><span><?= e($bed['ward']) ?></span><em><?= e($bed['type'].' / '.$bed['status']) ?></em></div><?php endforeach; ?></div>
  </article>
  <article class="panel">
    <h2>Admit Patient</h2>
    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="admit">
      <select name="visit_id" required><option value="">Select patient visit</option><?php foreach ($visits as $visit): ?><option value="<?= (int) $visit['id'] ?>"><?= e($visit['visit_no'].' - '.$visit['patient_no'].' - '.$visit['patient_name']) ?></option><?php endforeach; ?></select>
      <select name="bed_id" required><option value="">Available bed</option><?php foreach ($availableBeds as $bed): ?><option value="<?= (int) $bed['id'] ?>"><?= e($bed['ward'].' / '.$bed['bed_no']) ?></option><?php endforeach; ?></select>
      <button class="button primary">Admit patient</button>
    </form>
  </article>
</section>
<section class="split">
  <article class="panel">
    <h2>Add Ward</h2>
    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="ward">
      <input name="name" placeholder="Ward name" required>
      <select name="type"><option>general</option><option>private</option><option>maternity</option><option>pediatric</option><option>icu</option><option>emergency</option></select>
      <input name="floor" placeholder="Floor">
      <button class="button primary">Create ward</button>
    </form>
  </article>
  <article class="panel">
    <h2>Add Bed</h2>
    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="bed">
      <select name="ward_id" required><?php foreach ($wards as $ward): ?><option value="<?= (int) $ward['id'] ?>"><?= e($ward['name']) ?></option><?php endforeach; ?></select>
      <input name="bed_no" placeholder="Bed number" required>
      <select name="status"><option>available</option><option>reserved</option><option>maintenance</option><option>occupied</option></select>
      <button class="button primary">Add bed</button>
    </form>
  </article>
</section>
<section class="panel">
  <h2>Admissions</h2>
  <div class="table-wrap"><table><thead><tr><th>No</th><th>Patient</th><th>Ward / Bed</th><th>Status</th><th>Discharge</th></tr></thead><tbody>
  <?php foreach ($admissions as $row): ?><tr><td><?= e($row['admission_no']) ?></td><td><?= e($row['patient_no'].' '.$row['patient_name']) ?></td><td><?= e($row['ward'].' / '.$row['bed_no']) ?></td><td><span class="pill"><?= e($row['status']) ?></span></td><td><?php if ($row['status'] === 'active'): ?><form method="post" class="inline-form"><input type="hidden" name="action" value="discharge"><input type="hidden" name="admission_id" value="<?= (int) $row['id'] ?>"><input name="discharge_summary" placeholder="Discharge summary"><button class="button small">Discharge</button></form><?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
</section>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
