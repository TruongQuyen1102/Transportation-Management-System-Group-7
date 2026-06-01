<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('accountant');

$invoices = get_invoices();
$revenue  = get_monthly_revenue();
$recent   = array_slice($invoices, 0, 5);

// Stats
$total_invoiced  = 174650000;
$collected       = 58850000;
$outstanding     = 115800000;
$overdue_count   = 1;

open_page('Financial Dashboard', 'dashboard', [['label'=>'Financial Dashboard']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">💹 Financial Dashboard</h1>
    <p class="text-muted" style="margin-top:4px;">Overview of invoicing, collections, and outstanding balances</p>
  </div>
  <div class="page-actions">
    <a href="/accountant/invoices.php" class="btn btn-outline btn-sm">🧾 All Invoices</a>
    <button class="btn btn-accent" data-modal-open="modalCreateInvoice">＋ Create Invoice</button>
  </div>
</div>

<!-- Stat Cards -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
  <div class="stat-card navy">
    <div class="stat-icon">💰</div>
    <div class="stat-value"><?= number_format($total_invoiced) ?> ₫</div>
    <div class="stat-label">Total Invoiced (VND)</div>
    <div class="stat-trend">↑ 9 invoices issued</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">✅</div>
    <div class="stat-value"><?= number_format($collected) ?> ₫</div>
    <div class="stat-label">Collected</div>
    <div class="stat-trend">3 payments received</div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon">⏳</div>
    <div class="stat-value"><?= number_format($outstanding) ?> ₫</div>
    <div class="stat-label">Outstanding</div>
    <div class="stat-trend">5 invoices pending</div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon">🔔</div>
    <div class="stat-value"><?= $overdue_count ?></div>
    <div class="stat-label">Overdue Invoices</div>
    <div class="stat-trend" style="color:#ffcccc;">INV008 — 19 days late</div>
  </div>
</div>

<!-- Aging Debt Alert -->
<div class="alert alert-danger mt-16" style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
  <div>
    <strong>⚠️ Overdue Invoice Alert:</strong> INV008 — Saigon Textile Corp. has an outstanding balance of
    <strong>38,500,000 ₫</strong> that was due on <strong>2025-05-10</strong> (19 days overdue).
  </div>
  <a href="/accountant/aging_debt.php" class="btn btn-danger btn-sm" style="white-space:nowrap;">View Aging Report</a>
</div>

<!-- Charts Row -->
<div class="grid-2 mt-16">
  <!-- Doughnut: Invoice Status -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Invoice Status Breakdown</span>
    </div>
    <div class="card-body" style="display:flex;align-items:center;gap:32px;">
      <div style="position:relative;width:220px;height:220px;flex-shrink:0;">
        <canvas id="chartDonut"></canvas>
      </div>
      <div style="display:flex;flex-direction:column;gap:10px;">
        <div style="display:flex;align-items:center;gap:8px;"><span style="width:12px;height:12px;border-radius:50%;background:#6B8C3E;display:inline-block;"></span><span>Paid <strong>3</strong></span></div>
        <div style="display:flex;align-items:center;gap:8px;"><span style="width:12px;height:12px;border-radius:50%;background:#3A5361;display:inline-block;"></span><span>Issued <strong>4</strong></span></div>
        <div style="display:flex;align-items:center;gap:8px;"><span style="width:12px;height:12px;border-radius:50%;background:#9BA8AD;display:inline-block;"></span><span>Draft <strong>1</strong></span></div>
        <div style="display:flex;align-items:center;gap:8px;"><span style="width:12px;height:12px;border-radius:50%;background:#C0392B;display:inline-block;"></span><span>Overdue <strong>1</strong></span></div>
      </div>
    </div>
  </div>

  <!-- Bar: Monthly Revenue -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Monthly Revenue Collected (VND)</span>
    </div>
    <div class="card-body">
      <canvas id="chartRevenue" class="chart-container" style="height:220px;"></canvas>
    </div>
  </div>
</div>

<!-- Quick Actions + Recent Invoices -->
<div class="grid-2 mt-16" style="grid-template-columns:1fr 2fr;">
  <!-- Quick Actions -->
  <div class="card">
    <div class="card-header"><span class="card-title">Quick Actions</span></div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:12px;">
      <button class="btn btn-primary" style="width:100%;justify-content:flex-start;gap:10px;" data-modal-open="modalCreateInvoice">
        🧾 <span>Create New Invoice</span>
      </button>
      <button class="btn btn-secondary" style="width:100%;justify-content:flex-start;gap:10px;" data-modal-open="modalRecordPayment">
        💳 <span>Record Payment</span>
      </button>
      <a href="/accountant/reports.php" class="btn btn-outline" style="width:100%;justify-content:flex-start;gap:10px;">
        📊 <span>Generate Report</span>
      </a>
      <a href="/accountant/aging_debt.php" class="btn btn-outline" style="width:100%;justify-content:flex-start;gap:10px;">
        🔔 <span>Aging Debt Review</span>
      </a>
      <a href="/accountant/carrier_costs.php" class="btn btn-outline" style="width:100%;justify-content:flex-start;gap:10px;">
        🚛 <span>Carrier Reconciliation</span>
      </a>
    </div>
  </div>

  <!-- Recent Invoices -->
  <div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
      <span class="card-title">Recent Invoices</span>
      <a href="/accountant/invoices.php" class="btn btn-ghost btn-sm">View All →</a>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Invoice</th>
              <th>Customer</th>
              <th>Amount</th>
              <th>Due Date</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent as $inv): ?>
            <tr>
              <td><a href="/accountant/invoice_detail.php?id=<?= $inv['id'] ?>" class="font-bold" style="color:var(--c-navy-800);"><?= $inv['id'] ?></a></td>
              <td><?= htmlspecialchars($inv['customer']) ?></td>
              <td class="font-bold"><?= fmt_currency($inv['final'], $inv['currency']) ?></td>
              <td class="td-muted"><?= $inv['due_date'] ?></td>
              <td><?= status_badge($inv['status']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal: Create Invoice -->
<div class="modal-overlay" id="modalCreateInvoice">
  <div class="modal" style="max-width:560px;">
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
              <?php foreach (get_customers() as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Order ID *</label>
            <select class="form-control">
              <option value="">— Select Order —</option>
              <?php foreach (get_orders() as $o): ?>
              <option value="<?= $o['id'] ?>"><?= $o['id'] ?> – <?= htmlspecialchars($o['customer']) ?></option>
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
            <select class="form-control">
              <option>VND</option>
              <option>USD</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Tax Rate (%)</label>
            <input type="number" class="form-control" value="10" min="0" max="100">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea class="form-control" rows="2" placeholder="Optional billing notes…"></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost modal-close">Cancel</button>
      <button class="btn btn-accent">Save as Draft</button>
      <button class="btn btn-primary">Issue Invoice</button>
    </div>
  </div>
</div>

<!-- Modal: Record Payment -->
<div class="modal-overlay" id="modalRecordPayment">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <span class="modal-title">Record Payment</span>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body">
      <form data-feedback="Payment recorded successfully!">
        <div class="form-group">
          <label class="form-label">Invoice *</label>
          <select class="form-control">
            <option value="">— Select Invoice —</option>
            <?php foreach ($invoices as $inv): if ($inv['status'] === 'PAID') continue; ?>
            <option value="<?= $inv['id'] ?>"><?= $inv['id'] ?> — <?= htmlspecialchars($inv['customer']) ?> — <?= fmt_currency($inv['final'], $inv['currency']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Amount Received *</label>
            <input type="number" class="form-control" placeholder="0">
          </div>
          <div class="form-group">
            <label class="form-label">Currency</label>
            <select class="form-control"><option>VND</option><option>USD</option></select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Payment Date *</label>
            <input type="date" class="form-control" value="2025-05-29">
          </div>
          <div class="form-group">
            <label class="form-label">Method</label>
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
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost modal-close">Cancel</button>
      <button class="btn btn-success">Record Payment</button>
    </div>
  </div>
</div>

<script>
// Doughnut Chart
new Chart(document.getElementById('chartDonut'), {
  type: 'doughnut',
  data: {
    labels: ['Paid', 'Issued', 'Draft', 'Overdue'],
    datasets: [{
      data: [3, 4, 1, 1],
      backgroundColor: ['#6B8C3E', '#3A5361', '#9BA8AD', '#C0392B'],
      borderWidth: 3,
      borderColor: '#ffffff',
      hoverOffset: 6
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '65%',
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: (ctx) => ` ${ctx.label}: ${ctx.raw} invoice${ctx.raw > 1 ? 's' : ''}`
        }
      }
    }
  }
});

// Bar Chart: Monthly Revenue
const rev = <?= json_encode($revenue) ?>;
new Chart(document.getElementById('chartRevenue'), {
  type: 'bar',
  data: {
    labels: rev.labels,
    datasets: [{
      label: 'Revenue (VND)',
      data: rev.vnd,
      backgroundColor: '#0C2840',
      borderRadius: 5,
      borderSkipped: false
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: (ctx) => ` ${ctx.raw.toLocaleString()} ₫`
        }
      }
    },
    scales: {
      y: {
        ticks: {
          callback: (v) => (v / 1000000).toFixed(0) + 'M ₫'
        },
        grid: { color: 'rgba(0,0,0,0.05)' }
      },
      x: { grid: { display: false } }
    }
  }
});
</script>

<?php close_page(); ?>
