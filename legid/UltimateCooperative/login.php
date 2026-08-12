<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';
if (!config('app.installed')) {
    redirect('install/index.php');
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ensure_patient_login_permission();
    if (Auth::attempt($_POST['email'] ?? '', $_POST['password'] ?? '')) {
        redirect('admin/dashboard.php');
    }
    $error = 'Invalid login details or inactive account.';
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Login - HMS</title><link rel="stylesheet" href="css/app.css"></head>
<body class="login-body">
  <form class="login-card" method="post">
    <div class="brand-mark">HMS</div>
    <h1>Hospital Login</h1>
    <p class="muted">Secure access for clinical, pharmacy, lab, billing, and admin teams.</p>
    <?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
    <input name="email" type="email" placeholder="Email" required autofocus>
    <input name="password" type="password" placeholder="Password" required>
    <button class="button primary">Sign in</button>
  </form>
</body>
</html>
