<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/db.php';
auth_require('accountant');

$db = get_db();
$invoiceId = intval($_GET['id'] ?? 0);
if ($invoiceId <= 0) {
    header('Location: /accountant/invoices.php');
    exit;
}

$invResult = $db->query(
    "SELECT i.InvoiceID AS id,
            CONCAT('ORD', LPAD(i.OrderID, 3, '0')) AS order_id,
            bp.PartyName AS customer,
            bp.Address AS address,
            bp.ContactEmail AS email,
            bp.Phone AS phone,
            DATE_FORMAT(i.IssueDate, '%Y-%m-%d') AS issue_date,
            DATE_FORMAT(DATE_ADD(i.IssueDate, INTERVAL 30 DAY), '%Y-%m-%d') AS due_date,
            IFNULL(MIN(bs.currency), 'VND') AS currency,
            COALESCE(i.TaxRate, 0) AS tax,
            i.FinalAmount AS final,
            i.Note AS note,
            CASE WHEN EXISTS (SELECT 1 FROM payment_transaction pt WHERE pt.InvoiceID = i.InvoiceID) THEN 'PAID'
                 WHEN DATEDIFF(CURDATE(), i.IssueDate) > 30 THEN 'OVERDUE'
                 ELSE 'ISSUED' END AS status
     FROM invoice i
     LEFT JOIN business_party bp ON i.BilledPartyID = bp.PartyID
     LEFT JOIN invoice_line il ON il.InvoiceID = i.InvoiceID
     LEFT JOIN billing_structure bs ON il.BillingID = bs.BillingID
     WHERE i.InvoiceID = {$invoiceId}
     GROUP BY i.InvoiceID, i.OrderID, bp.PartyName, bp.Address, bp.ContactEmail, bp.Phone, i.IssueDate, i.TaxRate, i.FinalAmount, i.Note"
);

$inv = $invResult ? $invResult->fetch_assoc() : null;
if (!$inv) {
    header('Location: /accountant/invoices.php');
    exit;
}

$lines = [];
$lineResult = $db->query(
    "SELECT il.LineID,
            IFNULL(bs.ChargeType, il.ChargeMethod) AS billing,
            il.ChargeMethod AS method,
            il.Quantity AS qty,
            il.UnitPrice AS unit_price,
            il.LineTotal AS total
     FROM invoice_line il
     LEFT JOIN billing_structure bs ON il.BillingID = bs.BillingID
     WHERE il.InvoiceID = {$invoiceId}
     ORDER BY il.LineID"
);
if ($lineResult) {
    while ($row = $lineResult->fetch_assoc()) {
        $lines[] = $row;
    }
}

$payments = [];
$paymentResult = $db->query(
    "SELECT PaymentID, ReferenceCode AS ref,
            DATE_FORMAT(PaymentDate, '%Y-%m-%d') AS date,
            AmountPaid AS amount
     FROM payment_transaction
     WHERE InvoiceID = {$invoiceId}
     ORDER BY PaymentDate DESC"
);
if ($paymentResult) {
    while ($row = $paymentResult->fetch_assoc()) {
        $payments[] = $row;
    }
}

$paymentMeta = $payments[0] ?? null;

$subtotal = array_sum(array_column($lines, 'total'));
$tax_amt  = $subtotal * ($inv['tax'] / 100);
$final    = $subtotal + $tax_amt;

open_page('Invoice Detail — ' . $inv['id'], 'invoices', [
  ['label'=>'Invoices','url'=>'/accountant/invoices.php'],
  ['label'=>$inv['id']]
]);
?>

<div class="page-header">
  <div style="display:flex;align-items:center;gap:16px;">
    <a href="/accountant/invoices.php" class="btn btn-ghost btn-sm">← Back</a>
    <div>
      <div style="display:flex;align-items:center;gap:12px;">
        <h1 class="page-title" style="margin:0;">Invoice <?= $inv['id'] ?></h1>
        <?= status_badge($inv['status']) ?>
      </div>
      <p class="text-muted" style="margin-top:4px;">
        Issued: <strong><?= $inv['issue_date'] ?></strong> &nbsp;·&nbsp;
        Due: <strong><?= $inv['due_date'] ?></strong> &nbsp;·&nbsp;
        Order: <strong><?= $inv['order_id'] ?></strong>
      </p>
    </div>
  </div>
  <div class="page-actions">
    <button class="btn btn-outline btn-sm" onclick="window.print()">🖨 Print</button>
    <button class="btn btn-outline btn-sm">📤 Export PDF</button>
    <button class="btn btn-primary" data-modal-open="modalUpdateStatus">✏️ Update Status</button>
  </div>
</div>

<!-- Invoice Info Grid -->
<div class="grid-2 mt-16">
  <!-- Customer Details -->
  <div class="card">
    <div class="card-header"><span class="card-title">Customer Details</span></div>
    <div class="card-body">
      <div class="info-grid">
        <div class="info-row">
          <span class="info-label">Customer</span>
          <span class="info-value font-bold"><?= htmlspecialchars($inv['customer']) ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Tax Code</span>
          <span class="info-value">0301234567</span>
        </div>
        <div class="info-row">
          <span class="info-label">Address</span>
          <span class="info-value">12 Nguyen Hue, District 1, HCMC</span>
        </div>
        <div class="info-row">
          <span class="info-label">Email</span>
          <span class="info-value">logistics@sgtextile.vn</span>
        </div>
        <div class="info-row">
          <span class="info-label">Phone</span>
          <span class="info-value">+84 28 3823 0001</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Invoice Metadata -->
  <div class="card">
    <div class="card-header"><span class="card-title">Invoice Reference</span></div>
    <div class="card-body">
      <div class="info-grid">
        <div class="info-row">
          <span class="info-label">Invoice ID</span>
          <span class="info-value font-bold"><?= $inv['id'] ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Order Reference</span>
          <span class="info-value"><a href="/operations/shipments.php" style="color:var(--c-navy-800);"><?= $inv['order_id'] ?></a></span>
        </div>
        <div class="info-row">
          <span class="info-label">Currency</span>
          <span class="info-value"><?= $inv['currency'] ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Exchange Rate</span>
          <span class="info-value">1 USD = 24,850 VND</span>
        </div>
        <div class="info-row">
          <span class="info-label">Payment Ref</span>
          <span class="info-value font-bold" style="color:var(--c-green);"><?= $paymentMeta['ref'] ?? '—' ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Paid Date</span>
          <span class="info-value"><?= $paymentMeta['date'] ?? '—' ?></span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Invoice Lines -->
<div class="card mt-16">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
    <span class="card-title">Invoice Line Items</span>
    <span class="badge badge-navy"><?= count($lines) ?> line<?= count($lines) > 1 ? 's' : '' ?></span>
  </div>
  <div class="card-body" style="padding:0;">
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Charge Type</th>
            <th>Billing Method</th>
            <th class="text-right">Qty</th>
            <th class="text-right">Unit Price</th>
            <th class="text-right">Line Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lines as $i => $line): ?>
          <tr>
            <td class="td-muted"><?= $i + 1 ?></td>
            <td class="font-bold"><?= htmlspecialchars($line['billing']) ?></td>
            <td class="td-muted"><?= htmlspecialchars($line['method']) ?></td>
            <td class="text-right"><?= number_format($line['qty']) ?></td>
            <td class="text-right"><?= fmt_currency($line['unit_price'], $inv['currency']) ?></td>
            <td class="text-right font-bold"><?= fmt_currency($line['total'], $inv['currency']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <!-- Totals -->
  <div class="card-footer" style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
    <div style="display:grid;grid-template-columns:180px 160px;gap:4px 0;text-align:right;">
      <span class="text-muted">Subtotal:</span>
      <span class="font-bold"><?= fmt_currency($subtotal, $inv['currency']) ?></span>
      <span class="text-muted">Tax (<?= $inv['tax'] ?>%):</span>
      <span class="font-bold"><?= fmt_currency($tax_amt, $inv['currency']) ?></span>
      <span style="padding-top:8px;border-top:2px solid var(--c-navy-800);font-weight:700;color:var(--c-navy-800);font-size:15px;">Total Amount:</span>
      <span style="padding-top:8px;border-top:2px solid var(--c-navy-800);font-weight:700;color:var(--c-navy-800);font-size:15px;"><?= fmt_currency($final, $inv['currency']) ?></span>
    </div>
  </div>
</div>

<!-- Payment History -->
<div class="card mt-16">
  <div class="card-header"><span class="card-title">Payment History</span></div>
  <div class="card-body" style="padding:0;">
    <?php if (!empty($payments)): ?>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Reference</th>
            <th>Date</th>
            <th>Method</th>
            <th class="text-right">Amount</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $payment): ?>
          <tr>
            <td class="font-bold"><?= htmlspecialchars($payment['ref']) ?></td>
            <td class="td-muted"><?= htmlspecialchars($payment['date']) ?></td>
            <td>Bank Transfer</td>
            <td class="text-right font-bold text-success"><?= fmt_currency((float)$payment['amount'], $inv['currency']) ?></td>
            <td><?= status_badge('PAID') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div style="padding:32px;text-align:center;color:var(--text-muted);">
      <div style="font-size:36px;margin-bottom:8px;">💳</div>
      <p>No payment recorded yet for this invoice.</p>
      <button class="btn btn-primary mt-8" data-modal-open="modalUpdateStatus">Record Payment</button>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal: Update Status -->
<div class="modal-overlay" id="modalUpdateStatus">
  <div class="modal" style="max-width:440px;">
    <div class="modal-header">
      <span class="modal-title">Update Invoice Status</span>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body">
      <form data-feedback="Invoice status updated successfully!">
        <div class="alert alert-info" style="margin-bottom:16px;">
          Updating status for: <strong><?= $inv['id'] ?></strong> — <?= htmlspecialchars($inv['customer']) ?>
        </div>
        <div class="form-group">
          <label class="form-label">New Status</label>
          <select class="form-control">
            <option value="DRAFT" <?= $inv['status']==='DRAFT'?'selected':'' ?>>Draft</option>
            <option value="ISSUED" <?= $inv['status']==='ISSUED'?'selected':'' ?>>Issued</option>
            <option value="PAID" <?= $inv['status']==='PAID'?'selected':'' ?>>Paid</option>
            <option value="OVERDUE" <?= $inv['status']==='OVERDUE'?'selected':'' ?>>Overdue</option>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Payment Amount</label>
            <input type="number" class="form-control" value="<?= $inv['final'] ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Payment Date</label>
            <input type="date" class="form-control" value="2025-05-29">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Reference Code</label>
          <input type="text" class="form-control" value="<?= $inv['ref'] ?? '' ?>" placeholder="TXN-2025-XXXX-XXX">
        </div>
        <div class="form-group">
          <label class="form-label">Payment Method</label>
          <select class="form-control">
            <option>Bank Transfer</option>
            <option>Cash</option>
            <option>Cheque</option>
          </select>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost modal-close">Cancel</button>
      <button class="btn btn-primary">Save Changes</button>
    </div>
  </div>
</div>

<?php close_page(); ?>
