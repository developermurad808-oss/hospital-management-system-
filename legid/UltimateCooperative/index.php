<?php
require_once __DIR__ . '/includes/bootstrap.php';
if (!config('app.installed')) {
    redirect('install/index.php');
}
redirect(Auth::check() ? 'admin/dashboard.php' : 'login.php');
