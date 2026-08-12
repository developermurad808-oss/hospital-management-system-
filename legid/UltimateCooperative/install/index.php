<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

$step = max(1, min(9, (int) ($_GET['step'] ?? 1)));
$errors = [];

function install_db(array $db): PDO
{
    $dsn = "mysql:host={$db['host']};port={$db['port']};charset=utf8mb4";
    $pdo = new PDO($dsn, $db['user'], $db['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $db['name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . str_replace('`', '', $db['name']) . '`');
    return $pdo;
}

function run_sql_file(PDO $pdo, string $file): void
{
    $sql = file_get_contents($file);
    $statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
    foreach ($statements as $statement) {
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $install = $_SESSION['install'] ?? [];
    if ($step === 3) {
        $install['db'] = [
            'host' => trim($_POST['host'] ?? '127.0.0.1'),
            'port' => trim($_POST['port'] ?? '3306'),
            'name' => trim($_POST['name'] ?? ''),
            'user' => trim($_POST['user'] ?? ''),
            'password' => (string) ($_POST['password'] ?? ''),
        ];
        try {
            install_db($install['db']);
            $_SESSION['install'] = $install;
            redirect('install/index.php?step=4');
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }
    if ($step === 4) {
        try {
            $pdo = install_db($install['db']);
            run_sql_file($pdo, dirname(__DIR__) . '/database/schema.sql');
            run_sql_file($pdo, dirname(__DIR__) . '/database/seed.sql');
            redirect('install/index.php?step=5');
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
    }
    if ($step === 5) {
        $storage = dirname(__DIR__) . '/storage';
        if (!is_dir($storage)) {
            mkdir($storage, 0775, true);
        }
        $secret = bin2hex(random_bytes(32));
        $db = $install['db'];
        $appUrl = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
        $configPhp = "<?php\nreturn " . var_export([
            'app' => [
                'name' => 'UltimateCooperative HMS',
                'url' => ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . $_SERVER['HTTP_HOST'] . $appUrl,
                'timezone' => 'Africa/Lagos',
                'installed' => true,
                'jwt_secret' => $secret,
                'upload_path' => dirname(__DIR__) . '/uploads',
            ],
            'database' => [
                'driver' => 'mysql',
                'host' => $db['host'],
                'port' => $db['port'],
                'name' => $db['name'],
                'user' => $db['user'],
                'password' => $db['password'],
                'charset' => 'utf8mb4',
            ],
        ], true) . ";\n";
        file_put_contents($storage . '/config.php', $configPhp);
        redirect('install/index.php?step=6');
    }
    if ($step === 6) {
        $logo = upload_file($_FILES['logo'] ?? [], 'logos');
        Database::query(
            'INSERT INTO hospital_profiles (name, address, phone, email, logo_path) VALUES (?, ?, ?, ?, ?)',
            [$_POST['hospital_name'], $_POST['address'], $_POST['phone'], $_POST['email'], $logo]
        );
        redirect('install/index.php?step=7');
    }
    if ($step === 7) {
        $role = Database::query('SELECT id FROM roles WHERE slug = "super_admin"')->fetch();
        Database::query(
            'INSERT INTO users (role_id, name, email, phone, password_hash) VALUES (?, ?, ?, ?, ?)',
            [$role['id'], $_POST['name'], $_POST['email'], $_POST['phone'], password_hash($_POST['password'], PASSWORD_BCRYPT)]
        );
        redirect('install/index.php?step=8');
    }
}

$requirements = [
    'PHP 8.1+' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'PDO' => extension_loaded('pdo'),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'Fileinfo' => extension_loaded('fileinfo'),
    'Uploads writable' => is_writable(dirname(__DIR__) . '/uploads'),
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Hospital Installation</title>
  <link rel="stylesheet" href="../css/app.css">
</head>
<body class="install-body">
  <main class="install-card">
    <div class="brand-mark">HMS</div>
    <h1>Hospital Installation</h1>
    <p class="muted">Complete the setup steps to start using the hospital system.</p>
    <?php foreach ($errors as $error): ?><div class="alert danger"><?= e($error) ?></div><?php endforeach; ?>

    <?php if ($step === 1): ?>
      <h2>Start Setup</h2><p>Set up the database, hospital details, and administrator account.</p>
      <a class="button primary" href="?step=2">Start installation</a>
    <?php elseif ($step === 2): ?>
      <h2>Server Requirements</h2>
      <?php foreach ($requirements as $name => $ok): ?><div class="check"><span><?= $ok ? 'OK' : 'FAIL' ?></span><?= e($name) ?></div><?php endforeach; ?>
      <a class="button primary" href="?step=3">Continue</a>
    <?php elseif ($step === 3): ?>
      <h2>Database Connection</h2>
      <form method="post" class="form-grid">
        <input name="host" placeholder="Host" value="127.0.0.1" required><input name="port" placeholder="Port" value="3306" required>
        <input name="name" placeholder="Database name" required><input name="user" placeholder="Username" value="root" required>
        <input name="password" type="password" placeholder="Password"><button class="button primary">Test connection</button>
      </form>
    <?php elseif ($step === 4): ?>
      <h2>Create Tables</h2><p>Connection works. The next step creates normalized hospital tables and sample lookup data.</p>
      <form method="post"><button class="button primary">Create database tables</button></form>
    <?php elseif ($step === 5): ?>
      <h2>Generate Configuration</h2><p>The app will write <code>storage/config.php</code> with your database and JWT settings.</p>
      <form method="post"><button class="button primary">Generate config file</button></form>
    <?php elseif ($step === 6): ?>
      <h2>Hospital Profile</h2>
      <form method="post" enctype="multipart/form-data" class="form-grid">
        <input name="hospital_name" placeholder="Hospital name" required><input name="phone" placeholder="Phone" required>
        <input name="email" type="email" placeholder="Email" required><textarea name="address" placeholder="Address" required></textarea>
        <label class="file">Logo <input name="logo" type="file" accept="image/png,image/jpeg"></label><button class="button primary">Save profile</button>
      </form>
    <?php elseif ($step === 7): ?>
      <h2>Super Admin</h2>
      <form method="post" class="form-grid">
        <input name="name" placeholder="Full name" required><input name="email" type="email" placeholder="Email" required>
        <input name="phone" placeholder="Phone"><input name="password" type="password" placeholder="Password" minlength="8" required>
        <button class="button primary">Create Super Admin</button>
      </form>
    <?php elseif ($step === 8): ?>
      <h2>Installation Finished</h2><p>Your HMS is ready. Use your Super Admin account to log in.</p>
      <a class="button primary" href="../login.php">Go to login</a>
    <?php endif; ?>
  </main>
</body>
</html>
