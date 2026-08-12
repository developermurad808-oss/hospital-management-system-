<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
Auth::requireLogin();
Auth::requirePermission('dashboard.view');
ensure_patient_genotype_column();

$title = 'Command Dashboard';
$search = trim($_GET['search'] ?? '');
$canSearchPatients = Auth::can('patients.manage') || Auth::can('emr.manage') || Auth::can('billing.manage');
$canSeeOperationalAlerts = Auth::can('pharmacy.manage') || Auth::can('appointments.manage') || Auth::can('patients.manage') || Auth::can('emr.manage');

$revenueToday = Database::query('SELECT COALESCE(SUM(amount),0) total FROM payments WHERE DATE(paid_at)=CURDATE()')->fetch()['total'] ?? 0;
$revenueRows = Database::query('SELECT DATE(paid_at) label, COALESCE(SUM(amount),0) total FROM payments WHERE paid_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(paid_at) ORDER BY label')->fetchAll();
$revenueMap = [];
foreach ($revenueRows as $row) {
    $revenueMap[$row['label']] = (float) $row['total'];
}
$revenueChart = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $revenueChart[] = ['label' => date('M d', strtotime($date)), 'value' => $revenueMap[$date] ?? 0];
}
$weeklyRevenue = array_sum(array_column($revenueChart, 'value'));
$peakRevenuePoint = ['label' => 'No sales', 'value' => 0];
foreach ($revenueChart as $point) {
    if ($point['value'] >= $peakRevenuePoint['value']) {
        $peakRevenuePoint = $point;
    }
}

$stats = [
    'Patients' => Database::query('SELECT COUNT(*) total FROM patients')->fetch()['total'] ?? 0,
    'Doctors' => Database::query('SELECT COUNT(*) total FROM users u JOIN roles r ON r.id=u.role_id WHERE r.slug="doctor"')->fetch()['total'] ?? 0,
    'Appointments' => Database::query('SELECT COUNT(*) total FROM appointments WHERE DATE(appointment_at)=CURDATE()')->fetch()['total'] ?? 0,
    'Revenue' => $revenueToday,
    'Occupied Beds' => Database::query('SELECT COUNT(*) total FROM admissions WHERE status="active" AND discharged_at IS NULL')->fetch()['total'] ?? 0,
    'Low Stock' => Database::query('SELECT COUNT(*) total FROM medicines m WHERE (SELECT COALESCE(SUM(quantity),0) FROM medicine_batches b WHERE b.medicine_id=m.id) <= m.reorder_level')->fetch()['total'] ?? 0,
];
$stageLabels = [
    'queue' => 'Waiting',
    'consulting' => 'In consultation',
    'lab' => 'At laboratory',
    'pharmacy' => 'At pharmacy',
    'billing' => 'At billing',
    'admitted' => 'Admitted',
    'closed' => 'Discharged',
];
$queue = Database::query('SELECT v.*, p.patient_no, CONCAT(p.first_name," ",p.last_name) patient_name FROM visits v JOIN patients p ON p.id=v.patient_id WHERE v.status <> "closed" ORDER BY FIELD(v.priority,"emergency","urgent","normal"), v.created_at LIMIT 8')->fetchAll();
$currentUser = Database::query('SELECT u.*, r.name role_name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?', [Auth::user()['id']])->fetch();
$notifications = Database::query('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 6', [Auth::user()['id']])->fetchAll();
$patientResults = ($canSearchPatients && $search !== '') ? Database::query('SELECT id, patient_no, first_name, last_name, phone, blood_group, genotype FROM patients WHERE patient_no LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name," ",last_name) LIKE ? ORDER BY id DESC LIMIT 10', ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"])->fetchAll() : [];
$lowStockItems = $canSeeOperationalAlerts ? Database::query('SELECT m.name, m.reorder_level, COALESCE(SUM(b.quantity),0) qty FROM medicines m LEFT JOIN medicine_batches b ON b.medicine_id=m.id GROUP BY m.id HAVING qty <= m.reorder_level ORDER BY qty ASC LIMIT 8')->fetchAll() : [];
$urgentQueue = $canSeeOperationalAlerts ? Database::query('SELECT v.visit_no, v.priority, v.status, p.patient_no, CONCAT(p.first_name," ",p.last_name) patient_name FROM visits v JOIN patients p ON p.id=v.patient_id WHERE v.priority IN ("urgent","emergency") AND v.status <> "closed" ORDER BY FIELD(v.priority,"emergency","urgent"), v.created_at LIMIT 8')->fetchAll() : [];
$dueAppointments = $canSeeOperationalAlerts ? Database::query('SELECT a.appointment_no, a.appointment_at, p.patient_no, CONCAT(p.first_name," ",p.last_name) patient_name FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE DATE(a.appointment_at)=CURDATE() AND a.status IN ("booked","checked_in") ORDER BY a.appointment_at LIMIT 8')->fetchAll() : [];
require dirname(__DIR__) . '/templates/header.php';
?>
<?php if ($canSearchPatients): ?>
<section class="panel">
  <form method="get" class="search-row global-search">
    <input name="search" value="<?= e($search) ?>" placeholder="Search patient by file number or name">
    <button class="button primary">Search</button>
  </form>
  <?php if ($search !== ''): ?>
    <div class="lookup-result">
      <?php foreach ($patientResults as $patient): ?>
        <?php $target = 'admin/patient-file.php?patient=' . (int) $patient['id']; ?>
        <a href="<?= app_url($target) ?>"><strong><?= e($patient['patient_no']) ?></strong> <?= e($patient['first_name'].' '.$patient['last_name']) ?> <span><?= e(($patient['phone'] ?: 'No phone') . ' | Blood: ' . ($patient['blood_group'] ?: 'Unknown') . ' | Genotype: ' . ($patient['genotype'] ?: 'Unknown')) ?></span></a>
      <?php endforeach; ?>
      <?php if (!$patientResults): ?><p class="muted">No patient found for "<?= e($search) ?>".</p><?php endif; ?>
    </div>
  <?php endif; ?>
</section>
<?php endif; ?>
<section class="stat-grid">
  <?php foreach ($stats as $label => $value): ?><article class="stat-card"><span><?= e($label) ?></span><strong><?= $label === 'Revenue' ? 'NGN ' . money($value) : e((string) $value) ?></strong></article><?php endforeach; ?>
</section>
<section class="dashboard-grid">
  <article class="panel">
    <div class="panel-head"><h2>Live Queue Board</h2><a href="patients.php" class="button small">New visit</a></div>
    <div class="table-wrap"><table><thead><tr><th>File No</th><th>Patient</th><th>Status</th><th>Priority</th></tr></thead><tbody>
      <?php foreach ($queue as $row): ?><tr><td><?= e($row['patient_no']) ?></td><td><?= e($row['patient_name']) ?></td><td><span class="pill"><?= e($stageLabels[$row['status']] ?? ucfirst((string) $row['status'])) ?></span></td><td><?= e($row['priority']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </article>
  <article class="panel chart-panel revenue-chart-panel">
    <div class="panel-head">
      <div>
        <h2><span class="chart-title-icon" aria-hidden="true"></span> Revenue Overview</h2>
        <p class="muted">Last 7 days from payment records</p>
      </div>
    </div>
    <div class="chart-summary">
      <div><span>7-day revenue</span><strong>NGN <?= money($weeklyRevenue) ?></strong></div>
      <div><span>Peak day</span><strong><?= e($peakRevenuePoint['label']) ?></strong></div>
      <div><span>Peak value</span><strong>NGN <?= money($peakRevenuePoint['value']) ?></strong></div>
    </div>
    <div class="chart-stage">
      <canvas id="revenueChart" height="260" data-values='<?= e(json_encode($revenueChart)) ?>'></canvas>
      <div id="revenueTooltip" class="chart-tooltip"></div>
    </div>
  </article>
</section>
<section class="dashboard-grid">
  <article class="panel profile-panel">
    <h2>My Profile</h2>
    <div class="dashboard-profile">
      <?php if (!empty($currentUser['profile_photo'])): ?><img src="<?= app_url($currentUser['profile_photo']) ?>" alt="Profile picture"><?php else: ?><div class="avatar-fallback"><?= e(substr($currentUser['name'], 0, 1)) ?></div><?php endif; ?>
      <div><strong><?= e($currentUser['name']) ?></strong><span><?= e($currentUser['role_name']) ?></span><a class="button small" href="<?= app_url('admin/settings.php') ?>">Update profile</a></div>
    </div>
  </article>
  <article class="panel">
    <h2>Messages</h2>
    <div class="message-list">
      <?php foreach ($notifications as $note): ?><div><strong><?= e($note['title']) ?></strong><span><?= e($note['message']) ?></span><em><?= e($note['created_at']) ?></em></div><?php endforeach; ?>
      <?php if (!$notifications): ?><p class="muted">No messages yet.</p><?php endif; ?>
    </div>
  </article>
</section>
<?php if ($canSeeOperationalAlerts): ?>
<section class="dashboard-grid">
  <article class="panel">
    <h2>Operational Alerts</h2>
    <div class="alert-list">
      <h3>Low stock</h3>
      <?php foreach ($lowStockItems as $item): ?><div><strong><?= e($item['name']) ?></strong><span><?= e((string) $item['qty']) ?> left, reorder at <?= e((string) $item['reorder_level']) ?></span></div><?php endforeach; ?>
      <?php if (!$lowStockItems): ?><p class="muted">No low-stock medicines.</p><?php endif; ?>
      <h3>Urgent queue</h3>
      <?php foreach ($urgentQueue as $item): ?><div><strong><?= e($item['patient_no'].' '.$item['patient_name']) ?></strong><span><?= e($item['priority']) ?> - <?= e($stageLabels[$item['status']] ?? $item['status']) ?></span></div><?php endforeach; ?>
      <?php if (!$urgentQueue): ?><p class="muted">No urgent patients waiting.</p><?php endif; ?>
    </div>
  </article>
  <article class="panel">
    <h2>Appointments Due Today</h2>
    <div class="message-list">
      <?php foreach ($dueAppointments as $appointment): ?><div><strong><?= e($appointment['appointment_no']) ?></strong><span><?= e($appointment['patient_no'].' '.$appointment['patient_name']) ?></span><em><?= e(date('H:i', strtotime($appointment['appointment_at']))) ?></em></div><?php endforeach; ?>
      <?php if (!$dueAppointments): ?><p class="muted">No appointments due today.</p><?php endif; ?>
    </div>
  </article>
</section>
<?php endif; ?>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
