<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';
if (!config('app.installed')) {
    redirect('install/index.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        ensure_patient_login_permission();
        ensure_patient_genotype_column();
        $exists = Database::query('SELECT id FROM users WHERE email=? LIMIT 1', [$_POST['email']])->fetch();
        if ($exists) {
            throw new RuntimeException('This email is already registered.');
        }
        $role = Database::query('SELECT id FROM roles WHERE slug="patient" LIMIT 1')->fetch();
        Database::query(
            'INSERT INTO users (role_id, name, email, phone, password_hash) VALUES (?, ?, ?, ?, ?)',
            [$role['id'], trim($_POST['first_name'] . ' ' . $_POST['last_name']), $_POST['email'], $_POST['phone'], password_hash($_POST['password'], PASSWORD_BCRYPT)]
        );
        $userId = Database::connection()->lastInsertId();
        $patientNo = next_number('PAT', 'patients', 'patient_no');
        Database::query(
            'INSERT INTO patients (patient_no, first_name, last_name, gender, date_of_birth, blood_group, genotype, phone, email, address, emergency_name, emergency_phone, portal_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$patientNo, $_POST['first_name'], $_POST['last_name'], $_POST['gender'], $_POST['date_of_birth'] ?: null, $_POST['blood_group'], $_POST['genotype'], $_POST['phone'], $_POST['email'], $_POST['address'], $_POST['emergency_name'], $_POST['emergency_phone'], $userId]
        );
        Auth::attempt($_POST['email'], $_POST['password']);
        redirect('admin/dashboard.php');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Register - HMS</title><link rel="stylesheet" href="css/app.css"></head>
<body class="login-body">
  <form class="login-card register-card" method="post">
    <div class="brand-mark">HMS</div>
    <h1>Create Patient Account</h1>
    <p class="muted">Register as a patient and get your hospital file number.</p>
    <?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
    <div class="form-grid">
      <input name="first_name" placeholder="First name" required><input name="last_name" placeholder="Last name" required>
      <select name="gender" required><option value="">Gender</option><option>male</option><option>female</option><option>other</option></select>
      <input name="date_of_birth" type="date">
      <select name="blood_group"><option value="">Blood group</option><option>A+</option><option>A-</option><option>B+</option><option>B-</option><option>AB+</option><option>AB-</option><option>O+</option><option>O-</option></select>
      <select name="genotype"><option value="">Genotype</option><option>AA</option><option>AS</option><option>SS</option><option>AC</option><option>SC</option></select>
      <input name="phone" placeholder="Phone" required>
      <input name="email" type="email" placeholder="Email" required>
      <input name="password" type="password" minlength="8" placeholder="Password" required>
      <textarea name="address" placeholder="Address"></textarea>
      <input name="emergency_name" placeholder="Emergency contact">
      <input name="emergency_phone" placeholder="Emergency phone">
    </div>
    <button class="button primary">Register</button>
    <a class="button ghost" href="login.php">Already have an account? Login</a>
  </form>
</body>
</html>
