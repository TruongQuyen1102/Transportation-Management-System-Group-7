<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('manager');

$kpi        = get_kpi_summary();
$revenue    = get_monthly_revenue();
$perf       = get_delivery_performance();
$shipments  = get_shipments();
$orders     = get_orders();
$invoices   = get_invoices();
$carriers   = get_carriers();

// Status breakdown counts
$statusCount = [];
foreach ($shipments as $s) {
    $statusCount[$s['status']] = ($statusCount[$s['status']] ?? 0) + 1;
}

// Financial summary
$totalInvoiced = array_sum(array_map(fn($inv) => $inv['currency'] === 'VND' ? $inv['final'] : 0, $invoices));
$totalPaid     = array_sum(array_map(fn($inv) => ($inv['status'] === 'PAID' && $inv['currency'] === 'VND') ? $inv['final'] : 0, $invoices));
$totalOutstanding = $totalInvoiced - $totalPaid;

open_page('Reports', 'reports', [['label' => 'Manager'], ['label' => 'Reports']]);
?>

<!-- Page Header -->
<div class="page-header">
  <div>
    <h1 class="page-title">Reports</h1>
    <p class="text-muted" style="margin-top:4px;">Operational & financial performance summaries</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-success btn-sm" data-feedback="Report generation started…">⚡ Generate Report</button>
    <button class="btn btn-outline btn-sm">📥 Export CSV</button>
    <button class="btn btn-primary btn-sm">📄 Export PDF</button>
  </div>
</div>

<!-- Tabs -->
<div data-tab-group class="mt-8">
  <div class="tabs">
    <button class="tab-btn active" data-tab="operational-panel">📦 Operational Report</button>
    <button class="tab-btn" data-tab="financial-panel">💹 Financial Report</button>
  </div>

  <!-- ══ OPERATIONAL REPORT TAB ══ -->
  <div id="operational-panel" class="tab-panel">

    <!-- Date Filter -->
    <div class="filter-bar mt-16">
      <label class="form-label" style="margin:0; line-height:36px; white-space:nowrap;">Report Period:</label>
      <div class="search-input-wrapper">
        <span>📅</span>
        <input type="date" class="form-control" value="2025-05-01">
      </div>
      <span style="line-height:36px; color:var(--text-muted);">to</span>
      <div class="search-input-wrapper">
        <span>📅</span>
        <input type="date" class="form-control" value="2025-05-31">
      </div>
      <select class="form-control">
        <option>All Carriers</option>
        <?php foreach ($carriers as $c): ?>
          <?php if ($c['status'] === 'active'): ?>
          <option><?= htmlspecialchars($c['name']) ?></option>
          <?php endif; ?>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary btn-sm">🔍 Apply</button>
    </div>

    <!-- KPI Summary Cards -->
    <div class="stats-grid mt-16">
      <div class="stat-card navy">
        <div class="stat-icon">📦</div>
        <div class="stat-value"><?= $kpi['total_orders'] ?></div>
        <div class="stat-label">Total Orders</div>
        <div class="stat-trend">May 2025</div>
      </div>
      <div class="stat-card green">
        <div class="stat-icon">✅</div>
        <div class="stat-value"><?= $kpi['delivered'] ?></div>
        <div class="stat-label">Delivered</div>
        <div class="stat-trend"><?= round($kpi['delivered'] / $kpi['total_orders'] * 100) ?>% of total</div>
      </div>
      <div class="stat-card olive">
        <div class="stat-icon">🚛</div>
        <div class="stat-value"><?= $kpi['in_transit'] ?></div>
        <div class="stat-label">In Transit</div>
        <div class="stat-trend">Tracking active</div>
      </div>
      <div class="stat-card slate">
        <div class="stat-icon">🎯</div>
        <div class="stat-value"><?= $kpi['on_time_rate'] ?>%</div>
        <div class="stat-label">On-Time Rate</div>
        <div class="stat-trend">Target 85%</div>
      </div>
      <div class="stat-card yellow">
        <div class="stat-icon">⏱️</div>
        <div class="stat-value"><?= $kpi['avg_delivery_days'] ?> d</div>
        <div class="stat-label">Avg Delivery Time</div>
        <div class="stat-trend">Across all routes</div>
      </div>
      <div class="stat-card red">
        <div class="stat-icon">⚠️</div>
        <div class="stat-value"><?= $kpi['exceptions_open'] ?></div>
        <div class="stat-label">Open Exceptions</div>
        <div class="stat-trend">Requires action</div>
      </div>
    </div>

    <!-- Shipment Status Breakdown -->
    <div class="grid-2 mt-16">
      <div class="card">
        <div class="card-header">
          <span class="card-title">📊 Shipment Status Breakdown</span>
        </div>
        <div class="card-body" style="padding:0;">
          <div class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Status</th>
                  <th class="text-right">Count</th>
                  <th class="text-right">Share</th>
                  <th>Progress</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $total = count($shipments);
                $colors = ['DELIVERED'=>'green','IN_TRANSIT'=>'olive','SCHEDULED'=>'blue','DELAYED'=>'red','CANCELLED'=>'gray'];
                foreach ($statusCount as $st => $cnt):
                  $pct = round($cnt / $total * 100);
                  $bar = $colors[$st] ?? 'gray';
                ?>
                <tr>
                  <td><?= status_badge($st) ?></td>
                  <td class="text-right font-bold"><?= $cnt ?></td>
                  <td class="text-right td-muted"><?= $pct ?>%</td>
                  <td style="width:180px;">
                    <div class="progress-bar">
                      <div class="progress-fill" style="width:<?= $pct ?>%; background:var(--c-<?= $bar === 'olive' ? 'green' : ($bar === 'navy' ? 'navy-800' : $bar) ?>);"></div>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Orders by Customer Summary -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">👥 Orders by Customer</span>
        </div>
        <div class="card-body" style="padding:0;">
          <div class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>Customer</th>
                  <th class="text-right">Orders</th>
                  <th class="text-right">Total Weight</th>
                  <th>Last Status</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $custOrders = [];
                foreach ($orders as $o) {
                    $cid = $o['customer_id'];
                    if (!isset($custOrders[$cid])) {
                        $custOrders[$cid] = ['name' => $o['customer'], 'count' => 0, 'weight' => 0, 'last_status' => $o['status']];
                    }
                    $custOrders[$cid]['count']++;
                    $custOrders[$cid]['weight'] += $o['weight'];
                    $custOrders[$cid]['last_status'] = $o['status'];
                }
                foreach ($custOrders as $cid => $row):
                ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars($row['name']) ?></strong>
                    <div class="td-muted" style="font-size:11px;"><?= $cid ?></div>
                  </td>
                  <td class="text-right font-bold"><?= $row['count'] ?></td>
                  <td class="text-right"><?= number_format($row['weight']) ?> kg</td>
                  <td><?= status_badge($row['last_status']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Delivery Performance Chart -->
    <div class="card mt-16">
      <div class="card-header">
        <span class="card-title">📈 Delivery Performance Trend (6 months)</span>
        <span class="badge badge-blue">On-Time vs Delayed (%)</span>
      </div>
      <div class="card-body">
        <canvas id="opsPerfChart" class="chart-container" style="height:260px;"></canvas>
      </div>
    </div>

  </div><!-- /operational-panel -->

  <!-- ══ FINANCIAL REPORT TAB ══ -->
  <div id="financial-panel" class="tab-panel" style="display:none;">

    <!-- Date Filter -->
    <div class="filter-bar mt-16">
      <label class="form-label" style="margin:0; line-height:36px; white-space:nowrap;">Period:</label>
      <div class="search-input-wrapper">
        <span>📅</span>
        <input type="date" class="form-control" value="2025-05-01">
      </div>
      <span style="line-height:36px; color:var(--text-muted);">to</span>
      <div class="search-input-wrapper">
        <span>📅</span>
        <input type="date" class="form-control" value="2025-05-31">
      </div>
      <select class="form-control">
        <option>All Customers</option>
        <option>Saigon Textile Corp.</option>
        <option>Hanoi Electronics Ltd.</option>
        <option>FreshMart Distribution</option>
        <option>Pacific Pharma Group</option>
        <option>Mekong Agri Exports</option>
      </select>
      <button class="btn btn-primary btn-sm">🔍 Apply</button>
      <button class="btn btn-success btn-sm">📥 Export CSV</button>
      <button class="btn btn-outline btn-sm">📄 Export PDF</button>
    </div>

    <!-- Financial KPI Cards -->
    <div class="stats-grid mt-16">
      <div class="stat-card navy">
        <div class="stat-icon">💹</div>
        <div class="stat-value"><?= number_format($totalInvoiced / 1000000, 1) ?>M ₫</div>
        <div class="stat-label">Total Invoiced</div>
        <div class="stat-trend">VND invoices only</div>
      </div>
      <div class="stat-card green">
        <div class="stat-icon">✅</div>
        <div class="stat-value"><?= number_format($totalPaid / 1000000, 1) ?>M ₫</div>
        <div class="stat-label">Amount Paid</div>
        <div class="stat-trend">3 payments received</div>
      </div>
      <div class="stat-card red">
        <div class="stat-icon">⏳</div>
        <div class="stat-value"><?= number_format($totalOutstanding / 1000000, 1) ?>M ₫</div>
        <div class="stat-label">Outstanding</div>
        <div class="stat-trend">Pending collection</div>
      </div>
      <div class="stat-card yellow">
        <div class="stat-icon">📄</div>
        <div class="stat-value"><?= count($invoices) ?></div>
        <div class="stat-label">Total Invoices</div>
        <div class="stat-trend">VND + USD</div>
      </div>
    </div>

    <!-- Revenue Chart -->
    <div class="card mt-16">
      <div class="card-header">
        <span class="card-title">📈 Monthly Revenue Trend</span>
        <span class="badge badge-green">VND (millions)</span>
      </div>
      <div class="card-body">
        <canvas id="finRevChart" class="chart-container" style="height:260px;"></canvas>
      </div>
    </div>

    <!-- Invoice Status Breakdown -->
    <div class="card mt-16">
      <div class="card-header">
        <span class="card-title">🧾 Invoice Status Breakdown</span>
      </div>
      <div class="card-body" style="padding:0;">
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Invoice ID</th>
                <th>Customer</th>
                <th>Issue Date</th>
                <th>Due Date</th>
                <th>Currency</th>
                <th class="text-right">Total (incl. VAT)</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($invoices as $inv): ?>
              <tr>
                <td><strong><?= htmlspecialchars($inv['id']) ?></strong></td>
                <td><?= htmlspecialchars($inv['customer']) ?></td>
                <td class="td-muted"><?= $inv['issue_date'] ?></td>
                <td class="td-muted"><?= $inv['due_date'] ?></td>
                <td><span class="badge badge-<?= $inv['currency'] === 'USD' ? 'blue' : 'navy' ?>"><?= $inv['currency'] ?></span></td>
                <td class="text-right font-bold"><?= fmt_currency($inv['final'], $inv['currency']) ?></td>
                <td><?= status_badge($inv['status']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div><!-- /financial-panel -->

</div><!-- /tabs -->

<!-- Chart Scripts -->
<script>
(function() {
  const navy  = '#0C2840';
  const green = '#6B8C3E';
  const red   = '#C0392B';

  const perfData = <?= json_encode($perf) ?>;
  new Chart(document.getElementById('opsPerfChart'), {
    type: 'line',
    data: {
      labels: perfData.labels,
      datasets: [
        {
          label: 'On-Time (%)',
          data: perfData.on_time,
          borderColor: green,
          backgroundColor: 'rgba(107,140,62,0.1)',
          borderWidth: 2.5,
          pointBackgroundColor: green,
          pointRadius: 5,
          fill: true,
          tension: 0.4
        },
        {
          label: 'Delayed (%)',
          data: perfData.delayed,
          borderColor: red,
          backgroundColor: 'rgba(192,57,43,0.07)',
          borderWidth: 2,
          pointBackgroundColor: red,
          pointRadius: 4,
          fill: true,
          tension: 0.4
        }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { position: 'top', labels: { font: { size: 11 }, usePointStyle: true } },
        tooltip: { callbacks: { label: ctx => ' ' + ctx.raw + '%' } }
      },
      scales: {
        y: { beginAtZero: false, max: 100,
          ticks: { callback: v => v + '%', font: { size: 11 } },
          grid: { color: 'rgba(0,0,0,0.05)' }
        },
        x: { ticks: { font: { size: 11 } }, grid: { display: false } }
      }
    }
  });

  const revData = <?= json_encode($revenue) ?>;
  new Chart(document.getElementById('finRevChart'), {
    type: 'bar',
    data: {
      labels: revData.labels,
      datasets: [{
        label: 'Revenue (VND)',
        data: revData.vnd,
        backgroundColor: navy,
        borderRadius: 5,
        borderSkipped: false
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: ctx => ' ' + Number(ctx.raw).toLocaleString('vi-VN') + ' ₫' } }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: { callback: v => (v/1000000).toFixed(0)+'M', font: { size: 11 } },
          grid: { color: 'rgba(0,0,0,0.05)' }
        },
        x: { ticks: { font: { size: 11 } }, grid: { display: false } }
      }
    }
  });
})();
</script>

<?php close_page(); ?>
