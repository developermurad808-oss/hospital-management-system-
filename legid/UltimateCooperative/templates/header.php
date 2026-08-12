<?php
$user = Auth::user();
if ($user && config('app.installed')) {
    try {
        $freshUser = Database::query(
            'SELECT u.*, r.slug AS role_slug, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1',
            [$user['id']]
        )->fetch();
        if ($freshUser) {
            unset($freshUser['password_hash']);
            $_SESSION['user'] = $freshUser;
            $user = $freshUser;
        }
    } catch (Throwable) {}
}

$hospital = null;
try {
    if (config('app.installed')) {
        $hospital = Database::query('SELECT * FROM hospital_profiles ORDER BY id LIMIT 1')->fetch();
    }
} catch (Throwable) {}
?>
<!doctype html>
<html lang="en" data-theme="<?= e($_COOKIE['theme'] ?? 'light') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'UltimateCooperative HMS') ?></title>
  <link rel="stylesheet" href="<?= app_url('css/app.css') ?>">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar" id="sidebarNav">
    <a class="logo-row" href="<?= app_url('admin/dashboard.php') ?>">
      <?php if (!empty($hospital['logo_path'])): ?><img src="<?= app_url($hospital['logo_path']) ?>" alt="Logo"><?php endif; ?>
      <span><?= e($hospital['name'] ?? 'Ultimate HMS') ?></span>
    </a>
    <nav>
      <?php
      $roleMenus = [
          'super_admin' => ['Dashboard','Doctor Console','Patients','Laboratory','Pharmacy','Medical Records','Wards','Staff','Billing','Stocks','Appointment','Settings'],
          'hospital_admin' => ['Dashboard','Doctor Console','Patients','Laboratory','Pharmacy','Medical Records','Wards','Staff','Billing','Stocks','Appointment','Settings'],
          'doctor' => ['Dashboard','Doctor Console','Patients','Laboratory','Medical Records','Appointment','Settings'],
          'nurse' => ['Dashboard','Patients','Wards','Appointment','Settings'],
          'receptionist' => ['Dashboard','Patients','Billing','Appointment','Settings'],
          'pharmacist' => ['Dashboard','Pharmacy','Stocks','Settings'],
          'lab_technician' => ['Dashboard','Laboratory','Settings'],
          'accountant' => ['Dashboard','Billing','Settings'],
          'patient' => ['Dashboard','Settings'],
      ];
      $allowedLabels = $roleMenus[$user['role_slug'] ?? 'patient'] ?? ['Dashboard','Settings'];
      $items = [
          ['dashboard.view','Dashboard','admin/dashboard.php'],
          ['emr.manage','Doctor Console','admin/doctor-console.php'],
          ['patients.manage','Patients','admin/patients.php'],
          ['lab.manage','Laboratory','admin/lab.php'],
          ['pharmacy.manage','Pharmacy','admin/pharmacy.php'],
          ['emr.manage','Medical Records','admin/patient-file.php'],
          ['admissions.manage','Wards','admin/wards.php'],
          ['staff.manage','Staff','admin/staff.php'],
          ['billing.manage','Billing','admin/billing.php'],
          ['pharmacy.manage','Stocks','admin/stocks.php'],
          ['appointments.manage','Appointment','admin/appointments.php'],
          ['dashboard.view','Settings','admin/settings.php'],
      ];
      foreach ($items as [$permission, $label, $href]):
          if (in_array($label, $allowedLabels, true) && Auth::can($permission)):
      ?>
        <a href="<?= app_url($href) ?>" title="<?= e($label) ?>"><span class="nav-icon"><?= e(substr($label, 0, 1)) ?></span><?= e($label) ?></a>
      <?php endif; endforeach; ?>
    </nav>
  </aside>
  <button class="sidebar-backdrop" id="sidebarBackdrop" type="button" aria-label="Close navigation"></button>
  <section class="main">
    <header class="topbar">
      <button class="icon-button menu-toggle" id="menuToggle" type="button" aria-label="Open navigation" aria-controls="sidebarNav" aria-expanded="false">
        <span></span>
      </button>
      <div>
        <p class="eyebrow"><?= e($user['role_name'] ?? $user['role_slug'] ?? '') ?></p>
        <h1><?= e($title ?? 'Dashboard') ?></h1>
      </div>
      <div class="top-actions">
        <button class="button ghost" id="themeToggle" title="Toggle theme">Theme</button>
        <?php if ($user): ?>
          <div class="profile-chip">
            <?php if (!empty($user['profile_photo'])): ?><img src="<?= app_url($user['profile_photo']) ?>" alt="Profile"><?php endif; ?>
            <span><?= e($user['name']) ?></span>
          </div>
          <a class="button ghost" href="<?= app_url('logout.php') ?>">Logout</a>
        <?php endif; ?>
      </div>
    </header>
    <main class="content">
