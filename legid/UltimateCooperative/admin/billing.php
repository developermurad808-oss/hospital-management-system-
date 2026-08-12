<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
Auth::requireLogin();
Auth::requirePermission('billing.manage');

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'invoice') {
            if (empty($_POST['patient_id'])) {
                throw new RuntimeException('Select a patient before creating an invoice.');
            }

            $items = [];
            foreach (($_POST['description'] ?? []) as $i => $description) {
                $description = trim((string) $description);
                if ($description === '') {
                    continue;
                }
                $quantity = max(0.01, (float) ($_POST['quantity'][$i] ?? 1));
                $unitPrice = max(0, (float) ($_POST['unit_price'][$i] ?? 0));
                $items[] = [
                    'type' => $_POST['item_type'][$i] ?? 'other',
                    'description' => $description,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => $quantity * $unitPrice,
                ];
            }
            if (!$items) {
                throw new RuntimeException('Add at least one invoice item.');
            }

            $pdo = Database::connection();
            $pdo->beginTransaction();
            $invoiceNo = next_number('INV', 'invoices', 'invoice_no');
            Database::query('INSERT INTO invoices (invoice_no, patient_id, visit_id, created_by, status) VALUES (?, ?, ?, ?, "unpaid")', [
                $invoiceNo,
                (int) $_POST['patient_id'],
                $_POST['visit_id'] ?: null,
                Auth::user()['id'],
            ]);
            $invoiceId = (int) $pdo->lastInsertId();
            foreach ($items as $item) {
                Database::query('INSERT INTO invoice_items (invoice_id, item_type, description, quantity, unit_price, total) VALUES (?, ?, ?, ?, ?, ?)', [
                    $invoiceId,
                    $item['type'],
                    $item['description'],
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total'],
                ]);
            }
            $subtotal = array_sum(array_column($items, 'total'));
            Database::query('UPDATE invoices SET subtotal=?, total=? WHERE id=?', [$subtotal, $subtotal, $invoiceId]);
            $pdo->commit();
            $message = "Invoice {$invoiceNo} generated successfully.";
        }

        if ($action === 'payment') {
            if (empty($_POST['invoice_id']) || (float) ($_POST['amount'] ?? 0) <= 0) {
                throw new RuntimeException('Select an invoice and enter a valid payment amount.');
            }
            $pdo = Database::connection();
            $pdo->beginTransaction();
            Database::query('INSERT INTO payments (receipt_no, invoice_id, amount, method, reference, paid_by, received_by, paid_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())', [
                next_number('RCT', 'payments', 'receipt_no'),
                (int) $_POST['invoice_id'],
                (float) $_POST['amount'],
                $_POST['method'],
                trim($_POST['reference'] ?? ''),
                trim($_POST['paid_by'] ?? ''),
                Auth::user()['id'],
            ]);
            $receiptId = $pdo->lastInsertId();
            $invoiceId = (int) $_POST['invoice_id'];
            $paidTotal = Database::query('SELECT COALESCE(SUM(amount),0) total FROM payments WHERE invoice_id=?', [$invoiceId])->fetch()['total'] ?? 0;
            $invoiceTotal = Database::query('SELECT total FROM invoices WHERE id=?', [$invoiceId])->fetch()['total'] ?? 0;
            $status = (float) $paidTotal >= (float) $invoiceTotal ? 'paid' : 'partial';
            Database::query('UPDATE invoices SET paid=?, status=? WHERE id=?', [$paidTotal, $status, $invoiceId]);
            $pdo->commit();
            redirect('admin/receipt-pdf.php?id=' . $receiptId);
        }
    } catch (Throwable $exception) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception->getMessage();
    }
}

$patients = Database::query('SELECT id, patient_no, CONCAT(first_name," ",last_name) name FROM patients ORDER BY id DESC LIMIT 100')->fetchAll();
$visits = Database::query('SELECT id, visit_no FROM visits WHERE status <> "closed" ORDER BY id DESC LIMIT 100')->fetchAll();
$invoices = Database::query('SELECT i.*, p.patient_no, CONCAT(p.first_name," ",p.last_name) patient_name FROM invoices i JOIN patients p ON p.id=i.patient_id ORDER BY i.id DESC LIMIT 40')->fetchAll();
$billingStats = [
    'Invoices Today' => Database::query('SELECT COUNT(*) total FROM invoices WHERE DATE(created_at)=CURDATE()')->fetch()['total'] ?? 0,
    'Revenue Today' => Database::query('SELECT COALESCE(SUM(amount),0) total FROM payments WHERE DATE(paid_at)=CURDATE()')->fetch()['total'] ?? 0,
    'Unpaid Invoices' => Database::query('SELECT COUNT(*) total FROM invoices WHERE status IN ("unpaid","partial")')->fetch()['total'] ?? 0,
];
$title = 'Billing and Accounting';
require dirname(__DIR__) . '/templates/header.php';
?>
<?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
<section class="stat-grid compact-stats">
  <?php foreach ($billingStats as $label => $value): ?><article class="stat-card"><span><?= e($label) ?></span><strong><?= str_contains($label, 'Revenue') ? 'NGN ' . money($value) : e((string) $value) ?></strong></article><?php endforeach; ?>
</section>
<section class="split">
  <article class="panel">
    <h2>Generate Invoice</h2>
    <form method="post" class="form-grid">
      <input type="hidden" name="action" value="invoice">
      <select name="patient_id" required><option value="">Patient</option><?php foreach ($patients as $patient): ?><option value="<?= (int) $patient['id'] ?>"><?= e($patient['patient_no'].' '.$patient['name']) ?></option><?php endforeach; ?></select>
      <select name="visit_id"><option value="">No visit</option><?php foreach ($visits as $visit): ?><option value="<?= (int) $visit['id'] ?>"><?= e($visit['visit_no']) ?></option><?php endforeach; ?></select>
      <div class="rx-grid">
        <select name="item_type[]"><option>consultation</option><option>lab</option><option>medicine</option><option>admission</option><option>procedure</option><option>other</option></select>
        <input name="description[]" placeholder="Description" required>
        <input name="quantity[]" type="number" step="0.01" min="0.01" value="1">
        <input name="unit_price[]" type="number" step="0.01" min="0" placeholder="Amount" required>
      </div>
      <button class="button primary">Create invoice</button>
    </form>
  </article>
  <article class="panel">
    <h2>Invoices and Payments</h2>
    <div class="table-wrap"><table><thead><tr><th>Invoice</th><th>Patient</th><th>Total</th><th>Paid</th><th>Status</th><th>Payment</th></tr></thead><tbody>
      <?php foreach ($invoices as $invoice): ?><tr><td><?= e($invoice['invoice_no']) ?></td><td><?= e($invoice['patient_no'].' '.$invoice['patient_name']) ?></td><td>NGN <?= money($invoice['total']) ?></td><td>NGN <?= money($invoice['paid']) ?></td><td><span class="pill"><?= e($invoice['status']) ?></span></td><td><form method="post" class="inline-form"><input type="hidden" name="action" value="payment"><input type="hidden" name="invoice_id" value="<?= (int) $invoice['id'] ?>"><input name="amount" type="number" step="0.01" min="0.01" placeholder="Amount"><select name="method"><option>cash</option><option>card</option><option>bank_transfer</option><option>insurance</option><option>mobile_money</option></select><input name="paid_by" placeholder="Paid by"><input name="reference" placeholder="Ref"><button class="button small primary">Pay & PDF Receipt</button></form></td></tr><?php endforeach; ?>
    </tbody></table></div>
  </article>
</section>
<section class="panel">
  <div class="panel-head">
    <h2>Billing Reports</h2>
    <div class="quick-actions">
      <a class="button" href="<?= app_url('admin/export-report.php?format=excel') ?>">Export Excel</a>
      <a class="button" href="<?= app_url('admin/export-report.php?format=pdf') ?>">Export PDF</a>
      <button class="button" onclick="window.print()">Print Billing Report</button>
    </div>
  </div>
  <p class="muted">Use this section to generate financial reports after creating invoices and receiving payments.</p>
</section>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
