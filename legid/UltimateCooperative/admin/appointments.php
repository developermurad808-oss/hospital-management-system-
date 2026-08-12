<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
Auth::requireLogin(); Auth::requirePermission('appointments.manage');
if ($_SERVER['REQUEST_METHOD'] === 'POST') Database::query('INSERT INTO appointments (appointment_no, patient_id, doctor_id, department_id, appointment_at, reason, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)', [next_number('APT', 'appointments', 'appointment_no'), $_POST['patient_id'], $_POST['doctor_id'] ?: null, $_POST['department_id'] ?: null, $_POST['appointment_at'], $_POST['reason'], Auth::user()['id']]);
$patients = Database::query('SELECT id, patient_no, CONCAT(first_name," ",last_name) name FROM patients ORDER BY id DESC LIMIT 100')->fetchAll();
$doctors = Database::query('SELECT u.id, u.name FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug="doctor" ORDER BY u.name')->fetchAll();
$departments = Database::query('SELECT * FROM departments ORDER BY name')->fetchAll();
$appointments = Database::query('SELECT a.*, p.patient_no, CONCAT(p.first_name," ",p.last_name) patient_name, u.name doctor FROM appointments a JOIN patients p ON p.id=a.patient_id LEFT JOIN users u ON u.id=a.doctor_id ORDER BY a.appointment_at DESC LIMIT 60')->fetchAll();
$title = 'Appointments';
require dirname(__DIR__) . '/templates/header.php';
?>
<section class="split"><article class="panel"><h2>Book Appointment</h2><form method="post" class="form-grid"><select name="patient_id" required><option value="">Patient</option><?php foreach($patients as $p):?><option value="<?=$p['id']?>"><?=e($p['patient_no'].' '.$p['name'])?></option><?php endforeach;?></select><select name="doctor_id"><option value="">Doctor</option><?php foreach($doctors as $d):?><option value="<?=$d['id']?>"><?=e($d['name'])?></option><?php endforeach;?></select><select name="department_id"><option value="">Department</option><?php foreach($departments as $d):?><option value="<?=$d['id']?>"><?=e($d['name'])?></option><?php endforeach;?></select><input name="appointment_at" type="datetime-local" required><textarea name="reason" placeholder="Reason"></textarea><button class="button primary">Book</button></form></article><article class="panel"><h2>Calendar View</h2><div class="calendar-list"><?php foreach($appointments as $a):?><div><strong><?=e(date('M d, Y H:i', strtotime($a['appointment_at'])))?></strong><span><?=e($a['patient_no'].' '.$a['patient_name'])?></span><em><?=e($a['doctor'] ?? 'Unassigned')?> - <?=e($a['status'])?></em></div><?php endforeach;?></div></article></section>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
