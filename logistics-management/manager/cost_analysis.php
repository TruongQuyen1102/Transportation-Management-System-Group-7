<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('manager');

$costByRoute   = get_cost_by_route();
$carrierCosts  = get_carrier_costs();
$customers     = get_customers();
$routes        = get_routes();
$orders        = get_orders();

// Build customer cost summary
$custSummary = [];
foreach ($orders as $o) {
    $cid = $o['customer_id'];
    if (!isset($custSummary[$cid])) {
        $custSummary[$cid] = [
            'name'       => $o['customer'],
            'orders'     => 0,
            'total_wt'   => 0,
            'revenue'    => 0,
        ];
    }
    $custSummary[$cid]['orders']++;
    $custSummary[$cid]['total_wt'] += $o['weight'];
}

// Assign mock revenue per customer from invoices perspective
$custRevMap = [
    'CUST001' => 54500000,
    'CUST002' => 30800000,
    'CUST003' => 12100000,
    'CUST004' => 29700000,
    'CUST005' => 19800000,
];
foreach ($custSummary as $cid => &$row) {
    $row['revenue'] = $custRevMap[$cid] ?? 0;
    $row['avg_cost_per_kg'] = $row['total_wt'] > 0 ? round($row['revenue'] / $row['total_wt']) : 0;
}
unset($row);

// Aggregate stats
$totalRevenue = array_sum(array_column($custSummary, 'revenue'));
$totalCost    = array_sum(array_column($carrierCosts, 'total_payable'));
$margin       = $totalRevenue > 0 ? round(($totalRevenue - $totalCost) / $totalRevenue * 100, 1) : 0;

open_page('Cost Analysis', 'cost', [['label' => 'Manager'], ['label' => 'Cost Analysis']]);
?>

<!-- Page Header -->
<div class="page-header">
  <div>
    <h1 class="page-title">Cost Analysis</h1>
    <p class="text-muted" style="margin-top:4px;">Revenue, cost & margin breakdown · May 2025</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-outline btn-sm">📥 Export CSV</button>
    <button class="btn btn-primary btn-sm">📄 Export PDF</button>
  </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
  <div class="search-input-wrapper">
    <span>📅</span>
    <input type="date" class="form-control" value="2025-05-01" title="From date">
  </div>
  <div class="search-input-wrapper">
    <span>📅</span>
    <input type="date" class="form-control" value="2025-05-31" title="To date">
  </div>
  <select class="form-control">
    <option value="">All Customers</option>
    <?php foreach ($customers as $c): ?>
      <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select class="form-control">
    <option value="">All Routes</option>
    <?php foreach ($routes as $r): ?>
      <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-primary btn-sm">🔍 Apply Filter</button>
  <button class="btn btn-outline btn-sm">✕ Reset</button>
</div>

<!-- Stats Row -->
<div class="stats-grid mt-16">
  <div class="stat-card navy">
    <div class="stat-icon">💹</div>
    <div class="stat-value"><?= number_format($totalRevenue / 1000000, 1) ?>M ₫</div>
    <div class="stat-label">Total Revenue</div>
    <div class="stat-trend">↑ 5.9% vs last month</div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon">💸</div>
    <div class="stat-value"><?= number_format($totalCost / 1000000, 1) ?>M ₫</div>
    <div class="stat-label">Total Carrier Cost</div>
    <div class="stat-trend">6 shipments billed</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">📊</div>
    <div class="stat-value"><?= $margin ?>%</div>
    <div class="stat-label">Gross Margin</div>
    <div class="stat-trend">Target: 30%</div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon">📦</div>
    <div class="stat-value">12</div>
    <div class="stat-label">Total Orders</div>
    <div class="stat-trend">5 customers served</div>
  </div>
</div>

<!-- Charts Row -->
<div class="grid-2 mt-16">
  <!-- Cost by Route Bar Chart -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">🗺️ Cost by Route (VND)</span>
      <span class="badge badge-navy">May 2025</span>
    </div>
    <div class="card-body">
      <canvas id="routeCostChart" class="chart-container" style="height:280px;"></canvas>
    </div>
  </div>

  <!-- Revenue vs Cost Comparison -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">💰 Revenue vs Cost by Customer</span>
      <span class="badge badge-green">Margin view</span>
    </div>
    <div class="card-body">
      <canvas id="custRevChart" class="chart-container" style="height:280px;"></canvas>
    </div>
  </div>
</div>

<!-- Tables Row -->
<div class="grid-2 mt-16">

  <!-- Customer Cost Breakdown -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">👥 Cost Breakdown by Customer</span>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Customer</th>
              <th class="text-right">Orders</th>
              <th class="text-right">Total Weight</th>
              <th class="text-right">Revenue</th>
              <th class="text-right">Avg Cost/kg</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($custSummary as $cid => $row): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($row['name']) ?></strong>
                <div class="td-muted" style="font-size:11px;"><?= $cid ?></div>
              </td>
              <td class="text-right font-bold"><?= $row['orders'] ?></td>
              <td class="text-right"><?= number_format($row['total_wt']) ?> kg</td>
              <td class="text-right text-primary font-bold"><?= fmt_currency($row['revenue']) ?></td>
              <td class="text-right"><?= fmt_currency($row['avg_cost_per_kg']) ?>/kg</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:var(--bg-subtle); font-weight:700;">
              <td>Total</td>
              <td class="text-right">12</td>
              <td class="text-right"><?= number_format(array_sum(array_column($custSummary,'total_wt'))) ?> kg</td>
              <td class="text-right text-primary"><?= fmt_currency($totalRevenue) ?></td>
              <td class="text-right">—</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <!-- Carrier Cost Comparison -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">🚛 Carrier Cost Comparison</span>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="table-wrapper">
        <table id="carrierCostTable">
          <thead>
            <tr>
              <th>Shipment</th>
              <th>Carrier</th>
              <th>Mode</th>
              <th class="text-right">Base Cost</th>
              <th class="text-right">Surcharges</th>
              <th class="text-right">Total</th>
              <th>Reconciled</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($carrierCosts as $cc): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($cc['shipment_id']) ?></strong>
                <div class="td-muted" style="font-size:11px;"><?= htmlspecialchars(mb_substr($cc['route'], 0, 22)) ?>…</div>
              </td>
              <td style="font-size:13px;"><?= htmlspecialchars($cc['carrier']) ?></td>
              <td><span class="badge badge-<?= $cc['mode'] === 'Air' ? 'blue' : 'navy' ?>"><?= $cc['mode'] ?></span></td>
              <td class="text-right"><?= fmt_currency($cc['base_cost']) ?></td>
              <td class="text-right text-danger"><?= fmt_currency($cc['surcharges']) ?></td>
              <td class="text-right font-bold"><?= fmt_currency($cc['total_payable']) ?></td>
              <td><?= $cc['reconciled'] ? '<span class="badge badge-green">Yes</span>' : '<span class="badge badge-yellow">No</span>' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:var(--bg-subtle); font-weight:700;">
              <td colspan="3">Total</td>
              <td class="text-right"><?= fmt_currency(array_sum(array_column($carrierCosts,'base_cost'))) ?></td>
              <td class="text-right text-danger"><?= fmt_currency(array_sum(array_column($carrierCosts,'surcharges'))) ?></td>
              <td class="text-right text-primary"><?= fmt_currency($totalCost) ?></td>
              <td>—</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

</div>

<!-- Chart Scripts -->
<script>
(function() {
  const navy   = '#0C2840';
  const slate  = '#3A5361';
  const green  = '#6B8C3E';
  const yellow = '#E8B84B';
  const red    = '#C0392B';

  // Cost by Route Bar Chart
  const routeData = <?= json_encode($costByRoute) ?>;
  new Chart(document.getElementById('routeCostChart'), {
    type: 'bar',
    data: {
      labels: routeData.labels,
      datasets: [{
        label: 'Cost (VND)',
        data: routeData.costs,
        backgroundColor: [navy, slate, green, '#2980b9', '#8e44ad', yellow],
        borderRadius: 5,
        borderSkipped: false
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      indexAxis: 'y',
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => ' ' + Number(ctx.raw).toLocaleString('vi-VN') + ' ₫'
          }
        }
      },
      scales: {
        x: {
          beginAtZero: true,
          ticks: {
            callback: val => (val / 1000000).toFixed(0) + 'M',
            font: { size: 11 }
          },
          grid: { color: 'rgba(0,0,0,0.05)' }
        },
        y: { ticks: { font: { size: 11 } }, grid: { display: false } }
      }
    }
  });

  // Revenue vs Cost by Customer
  const custNames = <?= json_encode(array_values(array_map(fn($r) => mb_substr($r['name'],0,14), $custSummary))) ?>;
  const custRev   = <?= json_encode(array_values(array_column($custSummary,'revenue'))) ?>;
  const custRevMap = <?= json_encode($custRevMap) ?>;

  new Chart(document.getElementById('custRevChart'), {
    type: 'bar',
    data: {
      labels: custNames,
      datasets: [
        {
          label: 'Revenue (VND)',
          data: custRev,
          backgroundColor: navy,
          borderRadius: 4,
          borderSkipped: false
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'top', labels: { font: { size: 11 }, usePointStyle: true } },
        tooltip: {
          callbacks: {
            label: ctx => ' ' + Number(ctx.raw).toLocaleString('vi-VN') + ' ₫'
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: val => (val / 1000000).toFixed(0) + 'M',
            font: { size: 11 }
          },
          grid: { color: 'rgba(0,0,0,0.05)' }
        },
        x: { ticks: { font: { size: 11 } }, grid: { display: false } }
      }
    }
  });
})();
</script>

<?php close_page(); ?>
