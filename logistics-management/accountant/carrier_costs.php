<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('accountant');

$costs = get_carrier_costs();
$totalPayable   = array_sum(array_map(fn($c) => $c['total_payable'], $costs));
$reconciled     = array_filter($costs, fn($c) => $c['reconciled']);
$pending        = array_filter($costs, fn($c) => !$c['reconciled']);
$reconciledAmt  = array_sum(array_map(fn($c) => $c['total_payable'], $reconciled));
$pendingAmt     = array_sum(array_map(fn($c) => $c['total_payable'], $pending));

$carriers = array_unique(array_column($costs, 'carrier'));

open_page('Carrier Cost Reconciliation', 'carrier_costs', [['label'=>'Finance'],['label'=>'Carrier Costs']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Carrier Cost Reconciliation</h1>
    <p class="page-subtitle">Reconcile transport costs payable to carriers based on completed trips</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-ghost btn-sm">📥 Export to CSV</button>
    <button class="btn btn-primary btn-sm" onclick="showToast('Reconciled','All pending items marked as reconciled.','success')">✅ Reconcile All Pending</button>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
  <div class="stat-card navy">
    <div class="stat-icon" style="background:rgba(12,40,64,.08)">💰</div>
    <div class="stat-body">
      <div class="stat-value" style="font-size:18px;"><?= fmt_currency($totalPayable) ?></div>
      <div class="stat-label">Total Payable</div>
      <div class="stat-trend neutral"><?= count($costs) ?> shipments</div>
    </div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon" style="background:var(--c-green-bg)">✅</div>
    <div class="stat-body">
      <div class="stat-value"><?= count($reconciled) ?></div>
      <div class="stat-label">Reconciled</div>
      <div class="stat-trend up"><?= fmt_currency($reconciledAmt) ?></div>
    </div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon" style="background:var(--c-yellow-bg)">⏳</div>
    <div class="stat-body">
      <div class="stat-value"><?= count($pending) ?></div>
      <div class="stat-label">Pending Reconciliation</div>
      <div class="stat-trend down"><?= fmt_currency($pendingAmt) ?></div>
    </div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon" style="background:rgba(58,83,97,.08)">🚛</div>
    <div class="stat-body">
      <div class="stat-value"><?= count($carriers) ?></div>
      <div class="stat-label">Active Carriers</div>
      <div class="stat-trend neutral">This period</div>
    </div>
  </div>
</div>

<!-- Progress -->
<div class="card mb-24">
  <div class="card-body" style="padding:16px 20px;">
    <div class="flex-between mb-8">
      <span style="font-size:13px;font-weight:600;color:var(--text-primary);">Reconciliation Progress</span>
      <span style="font-size:13px;font-weight:700;color:var(--c-green);"><?= count($reconciled) ?>/<?= count($costs) ?> completed</span>
    </div>
    <div class="progress-bar">
      <div class="progress-fill green" style="width:<?= round(count($reconciled)/count($costs)*100) ?>%"></div>
    </div>
    <div style="font-size:11px;color:var(--text-muted);margin-top:6px;">
      <?= round(count($reconciled)/count($costs)*100) ?>% of total payable amount reconciled this period
    </div>
  </div>
</div>

<!-- Filter + Table -->
<div class="card">
  <div class="card-header">
    <div class="card-title">🚛 Carrier Cost Details</div>
  </div>
  <div class="card-body" style="padding:16px 20px 0;">
    <div class="table-toolbar">
      <div class="table-toolbar-left">
        <div class="search-input-wrapper">
          <span class="search-icon">🔍</span>
          <input type="text" class="form-control" placeholder="Search shipment / carrier..." data-table-search="costsTable" style="min-width:220px;">
        </div>
        <select class="form-control" style="font-size:12px;" onchange="filterCosts(this.value,'carrier')">
          <option value="">All Carriers</option>
          <?php foreach ($carriers as $c): ?>
            <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
          <?php endforeach; ?>
        </select>
        <select class="form-control" style="font-size:12px;" onchange="filterCosts(this.value,'reconciled')">
          <option value="">All Status</option>
          <option value="yes">Reconciled</option>
          <option value="no">Pending</option>
        </select>
        <select class="form-control" style="font-size:12px;" onchange="filterCosts(this.value,'mode')">
          <option value="">All Modes</option>
          <option value="Road">Road</option>
          <option value="Air">Air</option>
          <option value="Waterway">Waterway</option>
        </select>
      </div>
      <div class="table-toolbar-right">
        <span class="table-count"><?= count($costs) ?> entries</span>
      </div>
    </div>
  </div>
  <div class="table-wrapper" style="border-radius:0;border:none;box-shadow:none;">
    <table id="costsTable">
      <thead>
        <tr>
          <th>Shipment ID</th>
          <th>Carrier</th>
          <th>Route</th>
          <th>Mode</th>
          <th>Shipment Status</th>
          <th>Base Cost</th>
          <th>Surcharges</th>
          <th>Total Payable</th>
          <th>Reconciled</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($costs as $cost): ?>
        <tr data-carrier="<?= htmlspecialchars($cost['carrier']) ?>"
            data-reconciled="<?= $cost['reconciled'] ? 'yes' : 'no' ?>"
            data-mode="<?= $cost['mode'] ?>">
          <td><strong><?= $cost['shipment_id'] ?></strong></td>
          <td><?= htmlspecialchars($cost['carrier']) ?></td>
          <td style="font-size:12px;"><?= htmlspecialchars($cost['route']) ?></td>
          <td><?= status_badge($cost['mode']) ?></td>
          <td><?= status_badge($cost['status']) ?></td>
          <td><?= fmt_currency($cost['base_cost']) ?></td>
          <td><?= fmt_currency($cost['surcharges']) ?></td>
          <td><strong><?= fmt_currency($cost['total_payable']) ?></strong></td>
          <td><?= status_badge($cost['reconciled'] ? 'DELIVERED' : 'PENDING') ?></td>
          <td>
            <div class="action-menu">
              <button class="action-menu-btn" data-dropdown-toggle="cost-menu-<?= $cost['shipment_id'] ?>">⋯</button>
              <div class="action-dropdown" id="cost-menu-<?= $cost['shipment_id'] ?>">
                <a href="/operations/shipment_detail.php">📋 View Shipment</a>
                <?php if (!$cost['reconciled']): ?>
                  <button onclick="showToast('Reconciled','<?= $cost['shipment_id'] ?> marked as reconciled.','success')">✅ Mark Reconciled</button>
                <?php else: ?>
                  <button onclick="showToast('Undone','<?= $cost['shipment_id'] ?> reconciliation reversed.','info')">↩️ Undo Reconciliation</button>
                <?php endif; ?>
                <button>📄 View Receipt</button>
              </div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer">
    <span class="font-sm text-muted">
      Reconciled: <strong class="text-success"><?= fmt_currency($reconciledAmt) ?></strong> &nbsp;|&nbsp;
      Pending: <strong style="color:var(--c-yellow);"><?= fmt_currency($pendingAmt) ?></strong> &nbsp;|&nbsp;
      Total: <strong><?= fmt_currency($totalPayable) ?></strong>
    </span>
    <button class="btn btn-success btn-sm" onclick="showToast('Processing','Generating carrier payment batch...','info')">💳 Generate Payment Batch</button>
  </div>
</div>

<!-- Carrier Breakdown Chart -->
<div class="card mt-24">
  <div class="card-header">
    <div class="card-title">📊 Payable by Carrier</div>
  </div>
  <div class="card-body">
    <div class="chart-container" style="height:220px;">
      <canvas id="carrierChart"></canvas>
    </div>
  </div>
</div>

<?php
$extraScripts = [<<<JS
new Chart(document.getElementById('carrierChart'), {
  type: 'bar',
  data: {
    labels: ['VietSpeed Logistics','MekongShip Transport','SwiftAir Cargo VN'],
    datasets: [
      { label: 'Reconciled', data: [9000000, 1860000, 0], backgroundColor: 'rgba(107,140,62,.8)', borderRadius: 4 },
      { label: 'Pending',    data: [13500000, 0, 16200000], backgroundColor: 'rgba(232,184,75,.8)', borderRadius: 4 }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false, indexAxis: 'y',
    plugins: { legend: { position: 'top' } },
    scales: {
      x: { stacked: true, ticks: { callback: v => (v/1000000).toFixed(0)+'M ₫' } },
      y: { stacked: true, grid: { display: false } }
    }
  }
});

function filterCosts(val, field) {
  document.querySelectorAll('#costsTable tbody tr').forEach(row => {
    if (!val) { row.style.display = ''; return; }
    const rv = row.dataset[field] || '';
    row.style.display = rv.toLowerCase().includes(val.toLowerCase()) ? '' : 'none';
  });
}
JS];
close_page();
?>
