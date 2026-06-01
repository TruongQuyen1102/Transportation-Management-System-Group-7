<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('accountant');

$payments  = get_payments();
$invoices  = get_invoices();

$total_received = array_sum(array_column(
  array_filter($payments, fn($p) => $p['currency'] === 'VND'),
  'amount'
));
$tx_count  = count($payments);
// All 3 are in May 2025
$this_month = $tx_count;

open_page('Payments', 'payments', [['label'=>'Payments']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">💳 Payments</h1>
    <p class="text-muted" style="margin-top:4px;">Track all payment transactions received from customers</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-accent" data-modal-open="modalRecordPayment">＋ Record Payment</button>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);">
  <div class="stat-card green">
    <div class="stat-icon">💰</div>
    <div class="stat-value"><?= number_format($total_received) ?> ₫</div>
    <div class="stat-label">Total Received (VND)</div>
    <div class="stat-trend">From 3 invoices paid</div>
  </div>
  <div class="stat-card navy">
    <div class="stat-icon">🔢</div>
    <div class="stat-value"><?= $tx_count ?></div>
    <div class="stat-label">Total Transactions</div>
    <div class="stat-trend">All currencies combined</div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon">📅</div>
    <div class="stat-value"><?= $this_month ?></div>
    <div class="stat-label">This Month</div>
    <div class="stat-trend">May 2025 receipts</div>
  </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar mt-16">
  <div class="search-input-wrapper">
    <input type="text" class="form-control" data-table-search="paymentTable" placeholder="🔍  Search payment ID, invoice, customer…" style="min-width:300px;">
  </div>
  <select class="form-control" style="max-width:160px;" onchange="filterPayments(this.value,'method')">
    <option value="">All Methods</option>
    <option value="Bank Transfer">Bank Transfer</option>
    <option value="Cash">Cash</option>
    <option value="Cheque">Cheque</option>
  </select>
  <input type="date" class="form-control" style="max-width:160px;" title="From date">
  <input type="date" class="form-control" style="max-width:160px;" title="To date">
</div>

<!-- Payment Table -->
<div class="card mt-16">
  <div class="card-body" style="padding:0;">
    <div class="table-wrapper">
      <table id="paymentTable">
        <thead>
          <tr>
            <th>Payment ID</th>
            <th>Invoice ID</th>
            <th>Customer</th>
            <th class="text-right">Amount</th>
            <th>Currency</th>
            <th>Date</th>
            <th>Reference Code</th>
            <th>Method</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $pay): ?>
          <tr data-method="<?= htmlspecialchars($pay['method']) ?>">
            <td class="font-bold td-muted"><?= $pay['id'] ?></td>
            <td>
              <a href="/accountant/invoice_detail.php?id=<?= $pay['invoice_id'] ?>" style="color:var(--c-navy-800);font-weight:600;">
                <?= $pay['invoice_id'] ?>
              </a>
            </td>
            <td class="font-bold"><?= htmlspecialchars($pay['invoice_customer']) ?></td>
            <td class="text-right font-bold text-success"><?= fmt_currency($pay['amount'], $pay['currency']) ?></td>
            <td>
              <?= $pay['currency'] === 'USD'
                ? '<span class="badge badge-blue">USD</span>'
                : '<span class="badge badge-navy">VND</span>' ?>
            </td>
            <td class="td-muted"><?= $pay['date'] ?></td>
            <td>
              <code style="background:var(--bg-secondary);padding:3px 7px;border-radius:4px;font-size:12px;color:var(--c-green);">
                <?= htmlspecialchars($pay['ref']) ?>
              </code>
            </td>
            <td>
              <?php
              $methodIcons = ['Bank Transfer'=>'🏦','Cash'=>'💵','Cheque'=>'📃'];
              echo ($methodIcons[$pay['method']] ?? '') . ' ' . htmlspecialchars($pay['method']);
              ?>
            </td>
            <td>
              <div class="action-menu">
                <button class="btn btn-ghost btn-sm action-menu-btn" data-dropdown-toggle="pmenu-<?= $pay['id'] ?>">⋮</button>
                <div class="action-dropdown" id="pmenu-<?= $pay['id'] ?>">
                  <a href="/accountant/invoice_detail.php?id=<?= $pay['invoice_id'] ?>" class="dropdown-item">👁 View Invoice</a>
                  <a href="#" class="dropdown-item">🖨 Print Receipt</a>
                  <a href="#" class="dropdown-item danger" data-confirm="Void payment <?= $pay['id'] ?>? This cannot be undone.">🗑 Void Payment</a>
                </div>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card-footer">
    <span class="text-muted"><?= $tx_count ?> payment transactions</span>
    <span class="font-bold" style="margin-left:auto;">Total VND: <span style="color:var(--c-green);"><?= number_format($total_received) ?> ₫</span></span>
  </div>
</div>

<!-- Modal: Record Payment -->
<div class="modal-overlay" id="modalRecordPayment">
  <div class="modal" style="max-width:500px;">
    <div class="modal-header">
      <span class="modal-title">Record New Payment</span>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body">
      <form data-feedback="Payment recorded successfully!">
        <div class="form-group">
          <label class="form-label">Invoice *</label>
          <select class="form-control">
            <option value="">— Select Invoice —</option>
            <?php foreach ($invoices as $inv): if ($inv['status'] === 'PAID') continue; ?>
            <option value="<?= $inv['id'] ?>">
              <?= $inv['id'] ?> — <?= htmlspecialchars($inv['customer']) ?> — <?= fmt_currency($inv['final'], $inv['currency']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Amount Received *</label>
            <input type="number" class="form-control" placeholder="0" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Currency</label>
            <select class="form-control">
              <option>VND</option>
              <option>USD</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Payment Date *</label>
            <input type="date" class="form-control" value="2025-05-29">
          </div>
          <div class="form-group">
            <label class="form-label">Payment Method</label>
            <select class="form-control">
              <option>Bank Transfer</option>
              <option>Cash</option>
              <option>Cheque</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Reference Code</label>
          <input type="text" class="form-control" placeholder="e.g. TXN-2025-0529-001">
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea class="form-control" rows="2" placeholder="Optional payment notes…"></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost modal-close">Cancel</button>
      <button class="btn btn-success">💳 Record Payment</button>
    </div>
  </div>
</div>

<script>
function filterPayments(val, attr) {
  document.querySelectorAll('#paymentTable tbody tr').forEach(row => {
    if (!val) { row.style.display = ''; return; }
    row.style.display = row.dataset[attr] === val ? '' : 'none';
  });
}
</script>

<?php close_page(); ?>
