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
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$categories = Database::query('SELECT * FROM medicine_categories ORDER BY name')->fetchAll();
$medicines = Database::query('SELECT m.*, c.name category_name, COALESCE(SUM(b.quantity),0) qty, MIN(b.expiry_date) expiry FROM medicines m LEFT JOIN medicine_categories c ON c.id=m.category_id LEFT JOIN medicine_batches b ON b.medicine_id=m.id GROUP BY m.id ORDER BY m.name')->fetchAll();
$batches = Database::query('SELECT b.*, m.name medicine_name FROM medicine_batches b JOIN medicines m ON m.id=b.medicine_id ORDER BY b.expiry_date ASC, b.created_at DESC LIMIT 100')->fetchAll();
$title = 'Stock Management';
require dirname(__DIR__) . '/templates/header.php';
?>
<?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
<section class="dashboard-grid">
  <article class="panel">
    <h2>Add Stock Batch</h2>
    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="batch">
      <select name="medicine_id" required><option value="">Select medicine</option><?php foreach ($medicines as $medicine): ?><option value="<?= (int) $medicine['id'] ?>"><?= e($medicine['name'].' ('.$medicine['sku'].')') ?></option><?php endforeach; ?></select>
      <input name="batch_no" placeholder="Batch number" required>
      <input name="quantity" type="number" min="1" placeholder="Quantity" required>
      <input name="purchase_price" type="number" step="0.01" placeholder="Purchase price" required>
      <input name="expiry_date" type="date" required>
      <button class="button primary">Add stock</button>
    </form>
  </article>
  <article class="panel">
    <h2>Add Medicine</h2>
    <form method="post" class="form-grid">
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
<section class="panel">
  <h2>Add Category</h2>
  <form method="post" class="search-row">
    <input type="hidden" name="action" value="category">
    <input name="name" placeholder="Category name, e.g. Antibiotics" required>
    <button class="button">Save category</button>
  </form>
</section>
<section class="dashboard-grid">
  <article class="panel"><h2>Inventory</h2><div class="table-wrap"><table><thead><tr><th>Medicine</th><th>Type</th><th>Category</th><th>SKU</th><th>Unit</th><th>Qty</th><th>Price</th><th>Expiry</th></tr></thead><tbody>
    <?php foreach ($medicines as $medicine): ?><tr><td><?= e($medicine['name']) ?></td><td><?= e($medicine['stock_type'] ?? 'medicine') ?></td><td><?= e($medicine['category_name'] ?? '') ?></td><td><?= e($medicine['sku']) ?></td><td><?= e($medicine['unit']) ?></td><td><?= e((string) $medicine['qty']) ?></td><td>NGN <?= e(number_format((float) $medicine['selling_price'], 2)) ?></td><td><?= e($medicine['expiry']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div></article>
  <article class="panel"><h2>Stock Batches</h2><div class="table-wrap"><table><thead><tr><th>Medicine</th><th>Batch</th><th>Qty</th><th>Cost</th><th>Expiry</th></tr></thead><tbody>
    <?php foreach ($batches as $batch): ?><tr><td><?= e($batch['medicine_name']) ?></td><td><?= e($batch['batch_no']) ?></td><td><?= e((string) $batch['quantity']) ?></td><td>NGN <?= e(number_format((float) $batch['purchase_price'], 2)) ?></td><td><?= e($batch['expiry_date']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div></article>
</section>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
