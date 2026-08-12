<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
Auth::requireLogin();
Auth::requirePermission('reports.manage');

$title = 'Reports and Analytics';
$financial = Database::query('SELECT DATE(paid_at) report_date, SUM(amount) revenue, COUNT(*) payments FROM payments GROUP BY DATE(paid_at) ORDER BY report_date DESC LIMIT 30')->fetchAll();
$patients = Database::query('SELECT blood_group, COUNT(*) total FROM patients GROUP BY blood_group ORDER BY total DESC')->fetchAll();
$lab = Database::query('SELECT status, COUNT(*) total FROM lab_requests GROUP BY status ORDER BY total DESC')->fetchAll();
$pharmacy = Database::query('SELECT m.name, COALESCE(SUM(b.quantity),0) quantity, m.reorder_level FROM medicines m LEFT JOIN medicine_batches b ON b.medicine_id=m.id GROUP BY m.id ORDER BY quantity ASC LIMIT 20')->fetchAll();
require dirname(__DIR__) . '/templates/header.php';
?>
<section class="panel">
  <div class="panel-head">
    <h2>Export Center</h2>
    <div class="quick-actions">
      <a class="button" href="<?= app_url('admin/export-report.php?format=excel') ?>">Export Excel</a>
      <a class="button" href="<?= app_url('admin/export-report.php?format=pdf') ?>">Export PDF</a>
      <button class="button" onclick="window.print()">Print</button>
    </div>
  </div>
  <p class="muted">Download finance, patient, laboratory, and pharmacy summaries.</p>
</section>
<section class="dashboard-grid">
  <article class="panel"><h2>Financial Summary</h2><div class="table-wrap"><table><thead><tr><th>Date</th><th>Payments</th><th>Revenue</th></tr></thead><tbody><?php foreach ($financial as $row): ?><tr><td><?= e($row['report_date']) ?></td><td><?= e((string) $row['payments']) ?></td><td>NGN <?= e(number_format((float) $row['revenue'], 2)) ?></td></tr><?php endforeach; ?></tbody></table></div></article>
  <article class="panel"><h2>Patient Blood Groups</h2><div class="table-wrap"><table><thead><tr><th>Blood Group</th><th>Total</th></tr></thead><tbody><?php foreach ($patients as $row): ?><tr><td><?= e($row['blood_group'] ?: 'Unknown') ?></td><td><?= e((string) $row['total']) ?></td></tr><?php endforeach; ?></tbody></table></div></article>
</section>
<section class="dashboard-grid">
  <article class="panel"><h2>Laboratory Requests</h2><div class="table-wrap"><table><thead><tr><th>Status</th><th>Total</th></tr></thead><tbody><?php foreach ($lab as $row): ?><tr><td><?= e($row['status']) ?></td><td><?= e((string) $row['total']) ?></td></tr><?php endforeach; ?></tbody></table></div></article>
  <article class="panel"><h2>Pharmacy Stock</h2><div class="table-wrap"><table><thead><tr><th>Medicine</th><th>Qty</th><th>Reorder</th></tr></thead><tbody><?php foreach ($pharmacy as $row): ?><tr><td><?= e($row['name']) ?></td><td><?= e((string) $row['quantity']) ?></td><td><?= e((string) $row['reorder_level']) ?></td></tr><?php endforeach; ?></tbody></table></div></article>
</section>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
