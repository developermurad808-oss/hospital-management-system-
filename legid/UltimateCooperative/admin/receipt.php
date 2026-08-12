<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
Auth::requireLogin();
Auth::requirePermission('billing.manage');

$receipt = Database::query('SELECT pay.*, i.invoice_no, i.total, i.paid, p.patient_no, CONCAT(p.first_name," ",p.last_name) patient_name, hp.name hospital_name, hp.address, hp.phone, hp.email, hp.logo_path FROM payments pay JOIN invoices i ON i.id=pay.invoice_id JOIN patients p ON p.id=i.patient_id CROSS JOIN hospital_profiles hp WHERE pay.id=? LIMIT 1', [$_GET['id'] ?? 0])->fetch();
if (!$receipt) {
    http_response_code(404);
    exit('Receipt not found');
}
$items = Database::query('SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY id', [$receipt['invoice_id']])->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Receipt <?= e($receipt['receipt_no']) ?></title>
  <link rel="stylesheet" href="<?= app_url('css/app.css') ?>">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Inter:wght@400;600;800&display=swap');
    
    body.receipt-body {
      background: #e0e8e4;
      display: flex;
      justify-content: center;
      padding: 40px 20px;
      font-family: 'Inter', sans-serif;
    }
    
    .receipt-container {
      background: #fff;
      width: 100%;
      max-width: 500px;
      position: relative;
      border-radius: 12px;
      box-shadow: 0 20px 40px rgba(0,0,0,0.08);
      overflow: hidden;
    }
    
    .receipt-container::before, .receipt-container::after {
      content: '';
      position: absolute;
      width: 30px;
      height: 30px;
      background: #e0e8e4;
      border-radius: 50%;
      top: 140px;
    }
    .receipt-container::before { left: -15px; }
    .receipt-container::after { right: -15px; }
    
    .receipt-top {
      padding: 40px 40px 30px;
      text-align: center;
      border-bottom: 2px dashed #e2e8f0;
      position: relative;
    }
    
    .receipt-top img {
      width: 60px;
      height: 60px;
      object-fit: contain;
      margin-bottom: 12px;
    }
    
    .receipt-top h1 {
      font-size: 20px;
      font-weight: 800;
      color: #111827;
      margin: 0 0 6px;
    }
    
    .receipt-top p {
      font-size: 13px;
      color: #64748b;
      margin: 0;
      line-height: 1.5;
    }
    
    .receipt-title {
      font-family: 'Space Mono', monospace;
      text-transform: uppercase;
      letter-spacing: 2px;
      font-size: 14px;
      font-weight: 700;
      color: #2f9e68;
      margin-top: 24px;
    }
    
    .receipt-body-content {
      padding: 30px 40px;
    }
    
    .receipt-row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 16px;
      font-size: 14px;
    }
    
    .receipt-row .label {
      color: #64748b;
    }
    
    .receipt-row .value {
      font-weight: 600;
      color: #0f172a;
      text-align: right;
    }
    
    .receipt-total {
      margin-top: 24px;
      padding-top: 24px;
      border-top: 2px solid #f1f5f9;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .receipt-items {
      margin-top: 22px;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      overflow: hidden;
    }

    .receipt-item {
      display: grid;
      grid-template-columns: 1fr 70px 90px;
      gap: 10px;
      padding: 10px 12px;
      border-bottom: 1px solid #e2e8f0;
      font-size: 13px;
    }

    .receipt-item:last-child {
      border-bottom: 0;
    }

    .receipt-item.head {
      background: #f8fafc;
      color: #64748b;
      font-weight: 700;
      text-transform: uppercase;
      font-size: 11px;
    }
    
    .receipt-total .label {
      font-size: 16px;
      font-weight: 600;
      color: #0f172a;
    }
    
    .receipt-total .value {
      font-size: 24px;
      font-weight: 800;
      color: #2f9e68;
    }
    
    .paid-stamp {
      position: absolute;
      top: 30%;
      right: 15%;
      font-size: 40px;
      font-weight: 900;
      color: rgba(47, 158, 104, 0.1);
      text-transform: uppercase;
      transform: rotate(-25deg);
      pointer-events: none;
      letter-spacing: 4px;
      border: 4px solid rgba(47, 158, 104, 0.1);
      padding: 10px 20px;
      border-radius: 8px;
    }
    
    .receipt-actions {
      padding: 20px 40px 40px;
      display: flex;
      gap: 12px;
      justify-content: center;
      background: #f8fafc;
    }
    
    @media print {
      body.receipt-body {
        background: transparent;
        padding: 0;
      }
      .receipt-container {
        box-shadow: none;
        max-width: none;
      }
      .receipt-container::before, .receipt-container::after {
        display: none;
      }
      .receipt-actions {
        display: none;
      }
    }
  </style>
</head>
<body class="receipt-body">
<div class="receipt-container">
  <div class="paid-stamp">PAID</div>
  <div class="receipt-top">
    <?php if ($receipt['logo_path']): ?>
      <img src="<?= app_url($receipt['logo_path']) ?>" alt="Logo">
    <?php else: ?>
      <div style="width: 60px; height: 60px; background: #2f9e68; color: white; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold; margin-bottom: 12px;">H</div>
    <?php endif; ?>
    <h1><?= e($receipt['hospital_name']) ?></h1>
    <p><?= e($receipt['address']) ?><br><?= e($receipt['phone']) ?> &bull; <?= e($receipt['email']) ?></p>
    <div class="receipt-title">Payment Receipt</div>
  </div>
  
  <div class="receipt-body-content">
    <div class="receipt-row">
      <span class="label">Receipt No</span>
      <span class="value"><?= e($receipt['receipt_no']) ?></span>
    </div>
    <div class="receipt-row">
      <span class="label">Invoice No</span>
      <span class="value"><?= e($receipt['invoice_no']) ?></span>
    </div>
    <div class="receipt-row">
      <span class="label">Date</span>
      <span class="value"><?= date('M j, Y h:i A', strtotime($receipt['paid_at'])) ?></span>
    </div>
    <div class="receipt-row">
      <span class="label">Patient</span>
      <span class="value"><?= e($receipt['patient_name']) ?><br><small style="color: #64748b; font-weight: 400;"><?= e($receipt['patient_no']) ?></small></span>
    </div>
    <div class="receipt-row">
      <span class="label">Payment Method</span>
      <span class="value"><?= e($receipt['method']) ?></span>
    </div>

    <?php if ($items): ?>
      <div class="receipt-items">
        <div class="receipt-item head"><span>Description</span><span>Qty</span><span>Total</span></div>
        <?php foreach ($items as $item): ?>
          <div class="receipt-item">
            <span><?= e($item['description']) ?></span>
            <span><?= e((string) $item['quantity']) ?></span>
            <span>NGN <?= money($item['total']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    
    <div class="receipt-total">
      <span class="label">Amount Paid</span>
      <span class="value"><small style="font-size: 14px; vertical-align: top;">NGN</small> <?= money($receipt['amount']) ?></span>
    </div>
  </div>
  
  <div class="receipt-actions print-hide">
    <a class="button primary" href="<?= app_url('admin/receipt-pdf.php?id=' . (int) $receipt['id']) ?>" target="_blank">Open PDF Receipt</a>
    <button class="button" onclick="window.print()">Print Receipt</button>
    <a class="button ghost" href="billing.php">Back to Billing</a>
  </div>
</div>
<script>
if (new URLSearchParams(location.search).get('print') === '1') {
  window.addEventListener('load', () => setTimeout(() => window.print(), 300));
}
</script>
</body>
</html>
