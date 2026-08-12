<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
Auth::requireLogin();

$message = null;
$user = Auth::user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'profile') {
        $photo = upload_file($_FILES['profile_photo'] ?? [], 'staff');
        $params = [$_POST['name'], $_POST['phone'], $user['id']];
        $photoSql = '';
        if ($photo) {
            $photoSql = ', profile_photo=?';
            $params = [$_POST['name'], $_POST['phone'], $photo, $user['id']];
            $_SESSION['user']['profile_photo'] = $photo;
        }
        Database::query("UPDATE users SET name=?, phone=? {$photoSql} WHERE id=?", $params);
        $_SESSION['user']['name'] = $_POST['name'];
        $_SESSION['user']['phone'] = $_POST['phone'];
        $message = 'Your profile was updated.';
    }
    if ($action === 'password' && !empty($_POST['password'])) {
        Database::query('UPDATE users SET password_hash=? WHERE id=?', [password_hash($_POST['password'], PASSWORD_BCRYPT), $user['id']]);
        $message = 'Password updated.';
    }
    if ($action === 'hospital') {
        Auth::requirePermission('settings.manage');
        $logo = upload_file($_FILES['logo'] ?? [], 'logos');
        $hospital = Database::query('SELECT id FROM hospital_profiles ORDER BY id LIMIT 1')->fetch();
        if ($hospital) {
            $params = [$_POST['name'], $_POST['address'], $_POST['phone'], $_POST['email'], $hospital['id']];
            $logoSql = '';
            if ($logo) {
                $logoSql = ', logo_path=?';
                $params = [$_POST['name'], $_POST['address'], $_POST['phone'], $_POST['email'], $logo, $hospital['id']];
            }
            Database::query("UPDATE hospital_profiles SET name=?, address=?, phone=?, email=? {$logoSql} WHERE id=?", $params);
        } else {
            Database::query('INSERT INTO hospital_profiles (name, address, phone, email, logo_path) VALUES (?, ?, ?, ?, ?)', [$_POST['name'], $_POST['address'], $_POST['phone'], $_POST['email'], $logo]);
        }
        $message = 'Hospital profile updated.';
    }
}

$hospital = Database::query('SELECT * FROM hospital_profiles ORDER BY id LIMIT 1')->fetch();
$freshUser = Database::query('SELECT u.*, r.name role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?', [$user['id']])->fetch();
$title = 'Settings and Profiles';
require dirname(__DIR__) . '/templates/header.php';
?>
<?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
<section class="split">
  <article class="panel">
    <h2>My Profile</h2>
    <form method="post" enctype="multipart/form-data" class="form-grid">
      <input type="hidden" name="action" value="profile">
      <input name="name" value="<?= e($freshUser['name']) ?>" placeholder="Full name" required>
      <input name="email" value="<?= e($freshUser['email']) ?>" disabled>
      <input name="phone" value="<?= e($freshUser['phone']) ?>" placeholder="Phone">
      <label class="file">Profile picture <input name="profile_photo" type="file" accept="image/jpeg,image/png"></label>
      <button class="button primary">Save profile</button>
    </form>
  </article>
  <article class="panel">
    <h2>Change Password</h2>
    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="password">
      <input name="password" type="password" minlength="8" placeholder="New password" required>
      <button class="button primary">Update password</button>
    </form>
  </article>
</section>
<section class="panel">
  <h2>Hospital Profile</h2>
  <?php if (Auth::can('settings.manage')): ?>
  <form method="post" enctype="multipart/form-data" class="form-grid">
    <input type="hidden" name="action" value="hospital">
    <input name="name" value="<?= e($hospital['name'] ?? '') ?>" placeholder="Hospital name" required>
    <input name="phone" value="<?= e($hospital['phone'] ?? '') ?>" placeholder="Phone" required>
    <input name="email" type="email" value="<?= e($hospital['email'] ?? '') ?>" placeholder="Email" required>
    <textarea name="address" placeholder="Address" required><?= e($hospital['address'] ?? '') ?></textarea>
    <label class="file">Hospital logo <input name="logo" type="file" accept="image/jpeg,image/png"></label>
    <button class="button primary">Save hospital settings</button>
  </form>
  <?php else: ?>
    <dl class="settings-list"><dt>Name</dt><dd><?= e($hospital['name'] ?? '') ?></dd><dt>Address</dt><dd><?= e($hospital['address'] ?? '') ?></dd><dt>Phone</dt><dd><?= e($hospital['phone'] ?? '') ?></dd><dt>Email</dt><dd><?= e($hospital['email'] ?? '') ?></dd></dl>
  <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
