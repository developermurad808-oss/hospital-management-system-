<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
Auth::requireLogin();
Auth::requirePermission('pharmacy.manage');
ensure_medicine_stock_type_column();

$message = null;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
    $action = $_POST['action'] ?? '';
    if ($action === 'category') {
        if (trim($_POST['name'] ?? '') === '') {
            throw new RuntimeException('Category name is required.');
        }
        Database::query('INSERT IGNORE INTO medicine_categories (name) VALUES (?)', [trim($_POST['name'])]);
        $message = 'Medicine category saved.';
    } elseif ($action === 'medicine') {
        if (trim($_POST['name'] ?? '') === '' || trim($_POST['sku'] ?? '') === '') {
            throw new RuntimeException('Medicine name and SKU are required.');
        }
            Database::query('INSERT INTO medicines (category_id, name, sku, unit, stock_type, reorder_level, selling_price) VALUES (?, ?, ?, ?, ?, ?, ?)', [
                $_POST['category_id'] ?: null,
                trim($_POST['name']),
                trim($_POST['sku']),
                trim($_POST['unit']),
                $_POST['stock_type'],
                (int) $_POST['reorder_level'],
                (float) $_POST['selling_price'],
            ]);
        $message = 'Medicine added to inventory.';
    } elseif ($action === 'batch') {
        if (empty($_POST['medicine_id']) || trim($_POST['batch_no'] ?? '') === '') {
            throw new RuntimeException('Select a medicine and enter a batch number.');
        }
        Database::query('INSERT INTO medicine_batches (medicine_id, batch_no, quantity, purchase_price, expiry_date) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), purchase_price = VALUES(purchase_price), expiry_date = VALUES(expiry_date)', [
            $_POST['medicine_id'],
            trim($_POST['batch_no']),
            (int) $_POST['quantity'],
            (float) $_POST['purchase_price'],
            $_POST['expiry_date'],
        ]);
        $message = 'Stock batch added.';
    } else {
        $rx = Database::query('SELECT pr.*, p.patient_no, CONCAT(p.first_name," ",p.last_name) patient_name FROM prescriptions pr JOIN patients p ON p.id=pr.patient_id WHERE pr.id=?', [$_POST['prescription_id']])->fetch();
        if ($action === 'acknowledge') {
        if ($rx) {
            Database::query('INSERT INTO notifications (user_id, channel, title, message, status) VALUES (?, "in_app", "Pharmacy acknowledged prescription", ?, "sent")', [$rx['doctor_id'], 'Pharmacy has seen prescription for ' . $rx['patient_no'] . ' ' . $rx['patient_name'] . '.']);
            $message = 'Doctor has been notified that Pharmacy saw this prescription.';
        }
        } else {
        foreach ($_POST['item_id'] as $i => $itemId) {
            Database::query('UPDATE prescription_items SET dispensed_quantity=? WHERE id=?', [(int) $_POST['dispensed'][$i], (int) $itemId]);
        }
        Database::query('UPDATE prescriptions SET status="dispensed" WHERE id=?', [$_POST['prescription_id']]);
        if ($rx) {
            Database::query('INSERT INTO notifications (user_id, channel, title, message, status) VALUES (?, "in_app", "Prescription dispensed", ?, "sent")', [$rx['doctor_id'], 'Pharmacy dispensed prescription for ' . $rx['patient_no'] . ' ' . $rx['patient_name'] . '.']);
        }
        $message = 'Prescription dispensed and doctor notified.';
        }
    }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$rxs = Database::query('SELECT pr.id rx_id, pr.status, pr.notes, pr.created_at, u.name doctor_name, p.patient_no, CONCAT(p.first_name," ",p.last_name) patient_name, pi.id item_id, pi.medicine_name, pi.dosage, pi.frequency, pi.duration, pi.quantity, pi.dispensed_quantity FROM prescriptions pr JOIN patients p ON p.id=pr.patient_id JOIN users u ON u.id=pr.doctor_id JOIN prescription_items pi ON pi.prescription_id=pr.id ORDER BY FIELD(pr.status,"pending","partially_dispensed","dispensed","cancelled"), pr.created_at DESC LIMIT 80')->fetchAll();
$categories = Database::query('SELECT * FROM medicine_categories ORDER BY name')->fetchAll();
$medicines = Database::query('SELECT m.*, c.name category_name, COALESCE(SUM(b.quantity),0) qty, MIN(b.expiry_date) expiry FROM medicines m LEFT JOIN medicine_categories c ON c.id=m.category_id LEFT JOIN medicine_batches b ON b.medicine_id=m.id GROUP BY m.id ORDER BY m.name')->fetchAll();
$stocks = Database::query('SELECT m.name, m.reorder_level, COALESCE(SUM(b.quantity),0) qty, MIN(b.expiry_date) expiry FROM medicines m LEFT JOIN medicine_batches b ON b.medicine_id=m.id GROUP BY m.id ORDER BY qty ASC')->fetchAll();
$batches = Database::query('SELECT b.*, m.name medicine_name FROM medicine_batches b JOIN medicines m ON m.id=b.medicine_id ORDER BY b.expiry_date ASC, b.created_at DESC LIMIT 80')->fetchAll();
$title = 'Pharmacy Management';
require dirname(__DIR__) . '/templates/header.php';
?>
<?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
<section class="dashboard-grid">
  <article class="panel">
    <h2>Add Medicine Stock</h2>
    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="batch">
      <select name="medicine_id" required><option value="">Select medicine</option><?php foreach ($medicines as $medicine): ?><option value="<?= (int) $medicine['id'] ?>"><?= e($medicine['name'].' ('.$medicine['sku'].')') ?></option><?php endforeach; ?></select>
      <input name="batch_no" placeholder="Batch number" required>
      <input name="quantity" type="number" min="1" placeholder="Quantity" required>
      <input name="purchase_price" type="number" step="0.01" placeholder="Purchase price" required>
      <input name="expiry_date" type="date" required>
      <button class="button primary">Add stock batch</button>
    </form>
  </article>
  <article class="panel">
    <h2>Add Medicine / Category</h2>
    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="category">
      <input name="name" placeholder="New category name">
      <button class="button">Save category</button>
    </form>
    <form method="post" class="form-grid stacked-form">
      <input type="hidden" name="action" value="medicine">
      <select name="category_id"><option value="">No category</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>"><?= e($category['name']) ?></option><?php endforeach; ?></select>
      <input name="name" placeholder="Medicine name" required>
      <input name="sku" placeholder="SKU / Code" required>
      <input name="unit" placeholder="Unit, e.g. tablet, bottle" required>
      <select name="stock_type" required><option value="medicine">Medicine</option><option value="injection">Injection</option><option value="drip">Drip</option><option value="supply">Supply</option></select>
      <input name="reorder_level" type="number" min="0" value="10" placeholder="Reorder level">
      <input name="selling_price" type="number" step="0.01" placeholder="Selling price" required>
      <button class="button primary">Add medicine</button>
    </form>
  </article>
</section>
<section class="dashboard-grid">
  <article class="panel">
    <div class="panel-head"><h2>Doctor Prescriptions</h2><span class="pill"><?= count($rxs) ?> items</span></div>
    <div class="order-cards">
      <?php foreach ($rxs as $rx): ?>
        <article class="order-card">
          <header><strong><?= e($rx['patient_no'].' - '.$rx['patient_name']) ?></strong><span class="pill"><?= e($rx['status']) ?></span></header>
          <dl><dt>Doctor</dt><dd><?= e($rx['doctor_name']) ?></dd><dt>Medicine</dt><dd><?= e($rx['medicine_name']) ?></dd><dt>Instruction</dt><dd><?= e($rx['dosage'].' '.$rx['frequency'].' '.$rx['duration']) ?></dd><dt>Sent</dt><dd><?= e($rx['created_at']) ?></dd></dl>
          <?php if ($rx['notes']): ?><p class="muted">Doctor note: <?= e($rx['notes']) ?></p><?php endif; ?>
          <div class="order-actions">
            <form method="post"><input type="hidden" name="action" value="acknowledge"><input type="hidden" name="prescription_id" value="<?= (int) $rx['rx_id'] ?>"><button class="button small">Seen</button></form>
            <form method="post" class="inline-form compact-form"><input type="hidden" name="prescription_id" value="<?= (int) $rx['rx_id'] ?>"><input type="hidden" name="item_id[]" value="<?= (int) $rx['item_id'] ?>"><input name="dispensed[]" type="number" min="0" value="<?= (int) $rx['quantity'] ?>"><button class="button small primary">Dispense</button></form>
          </div>
        </article>
      <?php endforeach; ?>
      <?php if (!$rxs): ?><p class="muted">No prescriptions sent yet.</p><?php endif; ?>
    </div>
  </article>
  <article class="panel"><h2>Stock Alerts</h2><div class="table-wrap"><table><thead><tr><th>Medicine</th><th>Qty</th><th>Reorder</th><th>Expiry</th></tr></thead><tbody>
    <?php foreach ($stocks as $stock): ?><tr><td><?= e($stock['name']) ?></td><td><?= e((string)$stock['qty']) ?></td><td><?= e((string)$stock['reorder_level']) ?></td><td><?= e($stock['expiry']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div></article>
</section>
<section class="dashboard-grid">
  <article class="panel"><h2>Inventory</h2><div class="table-wrap"><table><thead><tr><th>Medicine</th><th>Type</th><th>Category</th><th>SKU</th><th>Unit</th><th>Qty</th><th>Price</th></tr></thead><tbody>
    <?php foreach ($medicines as $medicine): ?><tr><td><?= e($medicine['name']) ?></td><td><?= e($medicine['stock_type'] ?? 'medicine') ?></td><td><?= e($medicine['category_name'] ?? '') ?></td><td><?= e($medicine['sku']) ?></td><td><?= e($medicine['unit']) ?></td><td><?= e((string)$medicine['qty']) ?></td><td>NGN <?= e(number_format((float)$medicine['selling_price'], 2)) ?></td></tr><?php endforeach; ?>
  </tbody></table></div></article>
  <article class="panel"><h2>Stock Batches</h2><div class="table-wrap"><table><thead><tr><th>Medicine</th><th>Batch</th><th>Qty</th><th>Cost</th><th>Expiry</th></tr></thead><tbody>
    <?php foreach ($batches as $batch): ?><tr><td><?= e($batch['medicine_name']) ?></td><td><?= e($batch['batch_no']) ?></td><td><?= e((string)$batch['quantity']) ?></td><td>NGN <?= e(number_format((float)$batch['purchase_price'], 2)) ?></td><td><?= e($batch['expiry_date']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div></article>
</section>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
