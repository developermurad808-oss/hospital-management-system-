<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
Auth::requireLogin();
Auth::requirePermission('staff.manage');

$message = null;
$error = null;
$roleSql = Auth::user()['role_slug'] === 'super_admin'
    ? 'SELECT * FROM roles WHERE slug <> "patient" ORDER BY name'
    : 'SELECT * FROM roles WHERE slug IN ("doctor","nurse","receptionist","pharmacist","lab_technician","accountant") ORDER BY name';
$roles = Database::query($roleSql)->fetchAll();
$allowedRoleIds = array_map(fn ($role) => (int) $role['id'], $roles);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!in_array((int) $_POST['role_id'], $allowedRoleIds, true)) {
            throw new RuntimeException('You cannot create that staff role.');
        }
        if (strlen((string) $_POST['password']) < 8) {
            throw new RuntimeException('Temporary password must be at least 8 characters.');
        }
        $exists = Database::query('SELECT id FROM users WHERE email=? LIMIT 1', [trim($_POST['email'])])->fetch();
        if ($exists) {
            throw new RuntimeException('A staff account with this email already exists.');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        $photo = upload_file($_FILES['profile_photo'] ?? [], 'staff');
        Database::query('INSERT INTO users (role_id, name, email, phone, password_hash, profile_photo) VALUES (?, ?, ?, ?, ?, ?)', [
            (int) $_POST['role_id'],
            trim($_POST['name']),
            trim($_POST['email']),
            trim($_POST['phone'] ?? ''),
            password_hash($_POST['password'], PASSWORD_BCRYPT),
            $photo,
        ]);
        $userId = (int) $pdo->lastInsertId();
        Database::query('INSERT INTO staff_profiles (user_id, department_id, employee_no, specialization, qualification, hire_date, salary) VALUES (?, ?, ?, ?, ?, ?, ?)', [
            $userId,
            $_POST['department_id'] ?: null,
            next_number('EMP', 'staff_profiles', 'employee_no'),
            trim($_POST['specialization'] ?? ''),
            trim($_POST['qualification'] ?? ''),
            $_POST['hire_date'] ?: null,
            $_POST['salary'] !== '' ? (float) $_POST['salary'] : 0,
        ]);
        $pdo->commit();
        $message = 'Staff account created successfully.';
    } catch (Throwable $exception) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception->getMessage();
    }
}

$departments = Database::query('SELECT * FROM departments ORDER BY name')->fetchAll();
$staffWhere = Auth::user()['role_slug'] === 'super_admin' ? 'r.slug <> "patient"' : 'r.slug IN ("doctor","nurse","receptionist","pharmacist","lab_technician","accountant")';
$staff = Database::query('SELECT u.*, r.name role_name, d.name department FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN staff_profiles sp ON sp.user_id=u.id LEFT JOIN departments d ON d.id=sp.department_id WHERE ' . $staffWhere . ' ORDER BY u.id DESC LIMIT 50')->fetchAll();
$title = 'Staff and Doctor Management';
require dirname(__DIR__) . '/templates/header.php';
?>
<?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
<section class="split">
  <article class="panel">
    <h2>Add Staff</h2>
    <form method="post" enctype="multipart/form-data" class="form-grid">
      <input name="name" placeholder="Full name" required>
      <input name="email" type="email" placeholder="Email" required>
      <input name="phone" placeholder="Phone">
      <input name="password" type="password" minlength="8" placeholder="Temporary password" required>
      <select name="role_id" required><option value="">Role</option><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>"><?= e($role['name']) ?></option><?php endforeach; ?></select>
      <select name="department_id"><option value="">Department</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"><?= e($department['name']) ?></option><?php endforeach; ?></select>
      <input name="specialization" placeholder="Specialization">
      <input name="qualification" placeholder="Qualification">
      <input name="hire_date" type="date">
      <input name="salary" type="number" step="0.01" placeholder="Salary">
      <label class="file">Profile photo <input name="profile_photo" type="file" accept="image/jpeg,image/png"></label>
      <button class="button primary">Create account</button>
    </form>
  </article>
  <article class="panel">
    <h2>Team Directory</h2>
    <div class="staff-grid">
      <?php foreach ($staff as $member): ?>
        <div class="staff-card">
          <?php if ($member['profile_photo']): ?><img src="<?= app_url($member['profile_photo']) ?>" alt=""><?php else: ?><div class="avatar-fallback"><?= e(substr($member['name'], 0, 1)) ?></div><?php endif; ?>
          <strong><?= e($member['name']) ?></strong>
          <span><?= e($member['role_name']) ?></span>
          <em><?= e($member['department'] ?? 'No department') ?></em>
        </div>
      <?php endforeach; ?>
    </div>
  </article>
</section>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
