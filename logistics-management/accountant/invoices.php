<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('accountant');

$invoices  = get_invoices();
$customers = get_customers();
$orders    = get_orders();

$total   = count($invoices);
$draft   = count(array_filter($invoices, fn($i) => $i['status'] === 'DRAFT'));
$issued  = count(array_filter($invoices, fn($i) => $i['status'] === 'ISSUED'));
$paid    = count(array_filter($invoices, fn($i) => $i['status'] === 'PAID'));
$overdue = count(array_filter($invoices, fn($i) => $i['status'] === 'OVERDUE'));

open_page('Invoices', 'invoices', [['label'=>'Invoices']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">🧾 Invoices</h1>
    <p class="text-muted" style="margin-top:4px;">Create and manage all customer invoices</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-accent" data-modal-open="modalCreateInvoice">＋ Create Invoice</button>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);">
  <div class="stat-card navy">
    <div class="stat-icon">🧾</div>
    <div class="stat-value"><?= $total ?></div>
    <div class="stat-label">Total Invoices</div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon">📝</div>
    <div class="stat-value"><?= $draft ?></div>
    <div class="stat-label">Draft</div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon">📤</div>
    <div class="stat-value"><?= $issued ?></div>
    <div class="stat-label">Issued</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">✅</div>
    <div class="stat-value"><?= $paid ?></div>
    <div class="stat-label">Paid</div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon">🔔</div>
    <div class="stat-value"><?= $overdue ?></div>
    <div class="stat-label">Overdue</div>
  </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar mt-16">
  <div class="search-input-wrapper">
    <input type="text" class="form-control" data-table-search="invoiceTable" placeholder="🔍  Search invoice, customer…" style="min-width:260px;">
  </div>
  <input type="date" class="form-control" placeholder="From date" style="max-width:160px;" title="Issue date from">
  <input type="date" class="form-control" placeholder="To date"   style="max-width:160px;" title="Issue date to">
  <select class="form-control" style="max-width:200px;" onchange="filterInvoices(this.value,'customer')">
    <option value="">All Customers</option>
    <?php foreach ($customers as $c): ?>
    <option value="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select class="form-control" style="max-width:160px;" onchange="filterInvoices(this.value,'status')">
    <option value="">All Status</option>
    <option value="DRAFT">Draft</option>
    <option value="ISSUED">Issued</option>
    <option value="PAID">Paid</option>
    <option value="OVERDUE">Overdue</option>
  </select>
  <select class="form-control" style="max-width:130px;" onchange="filterInvoices(this.value,'currency')">
    <option value="">All Currencies</option>
    <option value="VND">VND</option>
    <option value="USD">USD</option>
  </select>
</div>

<!-- Invoice Table -->
<div class="card mt-16">
  <div class="card-body" style="padding:0;">
    <div class="table-wrapper">
      <table id="invoiceTable">
        <thead>
          <tr>
            <th>Invoice ID</th>
            <th>Order ID</th>
            <th>Customer</th>
            <th>Issue Date</th>
            <th>Due Date</th>
            <th>Currency</th>
            <th class="text-right">Total (incl. Tax)</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($invoices as $inv): ?>
          <tr data-status="<?= $inv['status'] ?>" data-customer="<?= htmlspecialchars($inv['customer']) ?>" data-currency="<?= $inv['currency'] ?>"
              style="<?= $inv['status'] === 'OVERDUE' ? 'background:rgba(192,57,43,0.06);' : '' ?>">
            <td>
              <a href="/accountant/invoice_detail.php?id=<?= $inv['id'] ?>" class="font-bold" style="color:var(--c-navy-800);"><?= $inv['id'] ?></a>
            </td>
            <td class="td-muted"><?= $inv['order_id'] ?></td>
            <td class="font-bold"><?= htmlspecialchars($inv['customer']) ?></td>
            <td class="td-muted"><?= $inv['issue_date'] ?></td>
            <td class="td-muted <?= $inv['status'] === 'OVERDUE' ? 'text-danger font-bold' : '' ?>"><?= $inv['due_date'] ?></td>
            <td>
              <?= $inv['currency'] === 'USD'
                ? '<span class="badge badge-blue">USD</span>'
                : '<span class="badge badge-navy">VND</span>' ?>
            </td>
            <td class="text-right font-bold"><?= fmt_currency($inv['final'], $inv['currency']) ?></td>
            <td><?= status_badge($inv['status']) ?></td>
            <td>
              <div class="action-menu">
                <button class="btn btn-ghost btn-sm action-menu-btn" data-dropdown-toggle="imenu-<?= $inv['id'] ?>">⋮</button>
                <div class="action-dropdown" id="imenu-<?= $inv['id'] ?>">
                  <a href="/accountant/invoice_detail.php?id=<?= $inv['id'] ?>" class="dropdown-item">👁 View / Edit</a>
                  <?php if ($inv['status'] !== 'PAID'): ?>
                  <a href="#" class="dropdown-item" data-modal-open="modalUpdatePayment">💳 Update Payment</a>
                  <?php endif; ?>
                  <?php if ($inv['status'] !== 'PAID'): ?>
                  <a href="#" class="dropdown-item danger" data-confirm="Cancel invoice <?= $inv['id'] ?>?">🗑 Cancel Invoice</a>
                  <?php endif; ?>
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
    <span class="text-muted">Showing <?= $total ?> invoices</span>
  </div>
</div>

<!-- Modal: Create Invoice -->
<div class="modal-overlay" id="modalCreateInvoice">
  <div class="modal" style="max-width:620px;">
    <div class="modal-header">
      <span class="modal-title">Create New Invoice</span>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body">
      <form data-feedback="Invoice created successfully!">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Customer *</label>
            <select class="form-control">
              <option value="">— Select Customer —</option>
              <?php foreach ($customers as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Order ID *</label>
            <select class="form-control">
              <option value="">— Select Order —</option>
              <?php foreach ($orders as $o): ?>
              <option value="<?= $o['id'] ?>"><?= $o['id'] ?> — <?= htmlspecialchars($o['customer']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Issue Date *</label>
            <input type="date" class="form-control" value="2025-05-29">
          </div>
          <div class="form-group">
            <label class="form-label">Due Date *</label>
            <input type="date" class="form-control" value="2025-06-29">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Currency</label>
            <select class="form-control"><option>VND</option><option>USD</option></select>
          </div>
          <div class="form-group">
            <label class="form-label">Tax Rate (%)</label>
            <input type="number" class="form-control" value="10" min="0" max="100">
          </div>
        </div>
        <!-- Billing Lines -->
        <div class="form-group">
          <label class="form-label">Billing Lines</label>
          <table style="width:100%;font-size:13px;border-collapse:collapse;">
            <thead style="background:var(--c-navy-800);color:#fff;">
              <tr>
                <th style="padding:8px 10px;text-align:left;">Charge Type</th>
                <th style="padding:8px 10px;">Method</th>
                <th style="padding:8px 10px;">Qty</th>
                <th style="padding:8px 10px;">Unit Price</th>
                <th style="padding:8px 10px;">Total</th>
              </tr>
            </thead>
            <tbody id="billingLinesBody">
              <tr>
                <td style="padding:6px;"><select class="form-control" style="font-size:12px;">
                  <?php foreach (get_billing_structures() as $b): if (!$b['active']) continue; ?>
                  <option><?= htmlspecialchars($b['charge_type']) ?></option>
                  <?php endforeach; ?>
                </select></td>
                <td style="padding:6px;"><input type="text" class="form-control" style="font-size:12px;" value="Per KG"></td>
                <td style="padding:6px;"><input type="number" class="form-control" style="font-size:12px;" value="1"></td>
                <td style="padding:6px;"><input type="number" class="form-control" style="font-size:12px;" value="0"></td>
                <td style="padding:6px;text-align:center;" class="td-muted">0</td>
              </tr>
            </tbody>
          </table>
          <button type="button" class="btn btn-ghost btn-sm mt-8" onclick="addBillingLine()">＋ Add Line</button>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea class="form-control" rows="2" placeholder="Optional notes…"></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost modal-close">Cancel</button>
      <button class="btn btn-secondary">Save Draft</button>
      <button class="btn btn-primary">Issue Invoice</button>
    </div>
  </div>
</div>

<!-- Modal: Update Payment -->
<div class="modal-overlay" id="modalUpdatePayment">
  <div class="modal" style="max-width:440px;">
    <div class="modal-header">
      <span class="modal-title">Update Payment Status</span>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body">
      <form data-feedback="Payment status updated!">
        <div class="form-group">
          <label class="form-label">New Status</label>
          <select class="form-control">
            <option value="PAID">Mark as Paid</option>
            <option value="ISSUED">Mark as Issued</option>
            <option value="OVERDUE">Mark as Overdue</option>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Amount Received</label>
            <input type="number" class="form-control" placeholder="0">
          </div>
          <div class="form-group">
            <label class="form-label">Payment Date</label>
            <input type="date" class="form-control" value="2025-05-29">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Reference Code</label>
          <input type="text" class="form-control" placeholder="e.g. TXN-2025-0529-001">
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
      <button class="btn btn-success">Save Changes</button>
    </div>
  </div>
</div>

<script>
function filterInvoices(val, attr) {
  document.querySelectorAll('#invoiceTable tbody tr').forEach(row => {
    if (!val) { row.style.display = ''; return; }
    row.style.display = row.dataset[attr] === val ? '' : 'none';
  });
}

function addBillingLine() {
  const tbody = document.getElementById('billingLinesBody');
  const row = tbody.rows[0].cloneNode(true);
  row.querySelectorAll('input').forEach(i => i.value = '');
  tbody.appendChild(row);
}
</script>

<?php close_page(); ?>
