<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('accountant');

$revenue        = get_monthly_revenue();
$carrier_costs  = get_carrier_costs();
$invoices       = get_invoices();
$customers      = get_customers();

// Revenue per customer
$rev_by_cust = [];
foreach ($invoices as $inv) {
    if ($inv['status'] === 'PAID') {
        $key = $inv['customer'];
        if (!isset($rev_by_cust[$key])) $rev_by_cust[$key] = ['vnd'=>0,'usd'=>0,'count'=>0];
        if ($inv['currency'] === 'VND') $rev_by_cust[$key]['vnd'] += $inv['final'];
        else $rev_by_cust[$key]['usd'] += $inv['final'];
        $rev_by_cust[$key]['count']++;
    }
}

// Carrier cost totals
$carrier_totals = [];
foreach ($carrier_costs as $cc) {
    $k = $cc['carrier'];
    if (!isset($carrier_totals[$k])) $carrier_totals[$k] = ['base'=>0,'surcharges'=>0,'total'=>0,'count'=>0];
    $carrier_totals[$k]['base']       += $cc['base_cost'];
    $carrier_totals[$k]['surcharges'] += $cc['surcharges'];
    $carrier_totals[$k]['total']      += $cc['total_payable'];
    $carrier_totals[$k]['count']++;
}

$total_revenue_vnd = array_sum(array_column(array_filter($invoices, fn($i) => $i['status']==='PAID' && $i['currency']==='VND'), 'final'));
$total_cost_vnd    = array_sum(array_column($carrier_costs, 'total_payable'));
$pl_vnd            = $total_revenue_vnd - $total_cost_vnd;

$revenueJson      = json_encode($revenue);
$carrierLabelsJson= json_encode(array_keys($carrier_totals));
$carrierTotalsJson= json_encode(array_values(array_column($carrier_totals,'total')));

$extraScripts = [<<<JS
const rev = $revenueJson;
const carrierLabels = $carrierLabelsJson;
const carrierTotals = $carrierTotalsJson;

let activeChart = 'VND';

const revenueChart = new Chart(document.getElementById('chartRevenue'), {
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
    plugins: { legend: { display: false },
      tooltip: { callbacks: { label: ctx => ` \${ctx.raw.toLocaleString()} ₫` } }
    },
    scales: {
      y: { ticks: { callback: v => (v/1000000).toFixed(0)+'M' }, grid: { color: 'rgba(0,0,0,0.05)' } },
      x: { grid: { display: false } }
    }
  }
});

function switchCurrency(cur) {
  const isVND = cur === 'VND';
  revenueChart.data.datasets[0].data  = isVND ? rev.vnd : rev.usd;
  revenueChart.data.datasets[0].label = isVND ? 'Revenue (VND)' : 'Revenue (USD)';
  revenueChart.options.scales.y.ticks.callback = isVND
    ? v => (v/1000000).toFixed(0)+'M ₫'
    : v => '$'+v.toLocaleString();
  revenueChart.update();
  document.getElementById('btnVND').className = isVND ? 'btn btn-ghost btn-sm' : 'btn btn-outline btn-sm';
  document.getElementById('btnUSD').className = isVND ? 'btn btn-outline btn-sm' : 'btn btn-ghost btn-sm';
}

new Chart(document.getElementById('chartCarrierCost'), {
  type: 'bar',
  data: {
    labels: carrierLabels,
    datasets: [{
      label: 'Total Payable (VND)',
      data: carrierTotals,
      backgroundColor: ['#0C2840','#3A5361','#E8B84B'],
      borderRadius: 5
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false },
      tooltip: { callbacks: { label: ctx => ` \${ctx.raw.toLocaleString()} ₫` } }
    },
    scales: {
      y: { ticks: { callback: v => (v/1000000).toFixed(0)+'M ₫' }, grid: { color: 'rgba(0,0,0,0.05)' } },
      x: { grid: { display: false } }
    }
  }
});

const costs = [38000000, 44000000, 41000000, 51000000, 54000000, 40560000];
new Chart(document.getElementById('chartPL'), {
  type: 'line',
  data: {
    labels: rev.labels,
    datasets: [
      {
        label: 'Revenue', data: rev.vnd,
        borderColor: '#6B8C3E', backgroundColor: 'rgba(107,140,62,0.1)',
        tension: 0.3, fill: true, pointRadius: 4
      },
      {
        label: 'Costs', data: costs,
        borderColor: '#C0392B', backgroundColor: 'rgba(192,57,43,0.07)',
        tension: 0.3, fill: true, pointRadius: 4
      }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'top' },
      tooltip: { callbacks: { label: ctx => ` \${ctx.dataset.label}: \${ctx.raw.toLocaleString()} ₫` } }
    },
    scales: {
      y: { ticks: { callback: v => (v/1000000).toFixed(0)+'M ₫' }, grid: { color: 'rgba(0,0,0,0.05)' } },
      x: { grid: { display: false } }
    }
  }
});
JS
];

open_page('Financial Reports', 'reports', [['label'=>'Finance'],['label'=>'Financial Reports']], $extraScripts);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">📊 Financial Reports</h1>
    <p class="text-muted" style="margin-top:4px;">Revenue, costs, and profit & loss summaries</p>
  </div>
  <div class="page-actions">
    <input type="date" class="form-control btn-sm" value="2025-05-01" title="From date" style="max-width:150px;">
    <input type="date" class="form-control btn-sm" value="2025-05-31" title="To date"   style="max-width:150px;">
    <button class="btn btn-outline btn-sm">📥 Export CSV</button>
    <button class="btn btn-outline btn-sm">📄 Export PDF</button>
  </div>
</div>

<!-- Tabs -->
<div data-tab-group class="mt-16">
  <div class="tabs">
    <button class="tab-btn active" data-tab="tabRevenue">📈 Revenue Report</button>
    <button class="tab-btn" data-tab="tabCost">🚛 Cost Report</button>
    <button class="tab-btn" data-tab="tabPL">💰 P&L Summary</button>
  </div>

  <!-- ═══ Revenue Report ═══ -->
  <div id="tabRevenue" class="tab-panel active">
    <!-- Monthly Revenue Chart -->
    <div class="card mt-16">
      <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <span class="card-title">Monthly Revenue — VND</span>
        <div style="display:flex;gap:8px;">
          <button class="btn btn-ghost btn-sm" id="btnVND" onclick="switchCurrency('VND')" style="font-weight:700;">VND</button>
          <button class="btn btn-outline btn-sm" id="btnUSD" onclick="switchCurrency('USD')">USD</button>
        </div>
      </div>
      <div class="card-body">
        <canvas id="chartRevenue" style="height:280px;"></canvas>
      </div>
    </div>

    <!-- Revenue by Customer -->
    <div class="card mt-16">
      <div class="card-header"><span class="card-title">Revenue by Customer (Paid Invoices)</span></div>
      <div class="card-body" style="padding:0;">
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Customer</th>
                <th class="text-right">Invoices Paid</th>
                <th class="text-right">Revenue (VND)</th>
                <th class="text-right">Revenue (USD)</th>
                <th class="text-right">% of Total</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $grand_vnd = array_sum(array_column($rev_by_cust, 'vnd'));
              foreach ($rev_by_cust as $cname => $data):
                $pct = $grand_vnd > 0 && $data['vnd'] > 0 ? round($data['vnd'] / $grand_vnd * 100, 1) : 0;
              ?>
              <tr>
                <td class="font-bold"><?= htmlspecialchars($cname) ?></td>
                <td class="text-right"><?= $data['count'] ?></td>
                <td class="text-right font-bold"><?= $data['vnd'] > 0 ? number_format($data['vnd']) . ' ₫' : '—' ?></td>
                <td class="text-right"><?= $data['usd'] > 0 ? '$' . number_format($data['usd'], 2) : '—' ?></td>
                <td class="text-right">
                  <?php if ($pct > 0): ?>
                  <div style="display:flex;align-items:center;gap:8px;justify-content:flex-end;">
                    <div class="progress-bar" style="width:80px;height:8px;background:var(--bg-secondary);border-radius:4px;">
                      <div class="progress-fill" style="width:<?= $pct ?>%;background:var(--c-navy-800);height:100%;border-radius:4px;"></div>
                    </div>
                    <span><?= $pct ?>%</span>
                  </div>
                  <?php else: ?>—<?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr style="background:var(--bg-secondary);font-weight:700;">
                <td>Total</td>
                <td class="text-right"><?= array_sum(array_column($rev_by_cust,'count')) ?></td>
                <td class="text-right"><?= number_format($total_revenue_vnd) ?> ₫</td>
                <td class="text-right">—</td>
                <td class="text-right">100%</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══ Cost Report ═══ -->
  <div id="tabCost" class="tab-panel">
    <div class="card mt-16">
      <div class="card-header"><span class="card-title">Carrier Cost Breakdown</span></div>
      <div class="card-body">
        <canvas id="chartCarrierCost" style="height:250px;"></canvas>
      </div>
    </div>
    <div class="card mt-16">
      <div class="card-header"><span class="card-title">Carrier Cost Summary</span></div>
      <div class="card-body" style="padding:0;">
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Carrier</th>
                <th class="text-right">Shipments</th>
                <th class="text-right">Base Cost (VND)</th>
                <th class="text-right">Surcharges (VND)</th>
                <th class="text-right">Total Payable (VND)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($carrier_totals as $carrier => $data): ?>
              <tr>
                <td class="font-bold"><?= htmlspecialchars($carrier) ?></td>
                <td class="text-right"><?= $data['count'] ?></td>
                <td class="text-right"><?= number_format($data['base']) ?> ₫</td>
                <td class="text-right"><?= number_format($data['surcharges']) ?> ₫</td>
                <td class="text-right font-bold"><?= number_format($data['total']) ?> ₫</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr style="background:var(--bg-secondary);font-weight:700;">
                <td>Grand Total</td>
                <td class="text-right"><?= count($carrier_costs) ?></td>
                <td class="text-right"><?= number_format(array_sum(array_column($carrier_totals,'base'))) ?> ₫</td>
                <td class="text-right"><?= number_format(array_sum(array_column($carrier_totals,'surcharges'))) ?> ₫</td>
                <td class="text-right"><?= number_format($total_cost_vnd) ?> ₫</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══ P&L Summary ═══ -->
  <div id="tabPL" class="tab-panel">
    <div class="grid-2 mt-16" style="grid-template-columns:1fr 1fr;">
      <div class="stat-card green">
        <div class="stat-icon">📈</div>
        <div class="stat-value"><?= number_format($total_revenue_vnd) ?> ₫</div>
        <div class="stat-label">Total Revenue Collected</div>
      </div>
      <div class="stat-card red">
        <div class="stat-icon">📉</div>
        <div class="stat-value"><?= number_format($total_cost_vnd) ?> ₫</div>
        <div class="stat-label">Total Carrier Costs</div>
      </div>
    </div>

    <div class="card mt-16">
      <div class="card-header"><span class="card-title">Profit & Loss Summary (VND)</span></div>
      <div class="card-body" style="padding:0;">
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Item</th>
                <th class="text-right">Amount (VND)</th>
                <th class="text-right">Notes</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td class="font-bold" style="color:var(--c-green);">📥 Revenue from Paid Invoices</td>
                <td class="text-right font-bold" style="color:var(--c-green);"><?= number_format($total_revenue_vnd) ?> ₫</td>
                <td class="text-right td-muted">3 invoices paid</td>
              </tr>
              <tr>
                <td class="font-bold" style="color:var(--c-red);">📤 Carrier Logistics Costs</td>
                <td class="text-right font-bold" style="color:var(--c-red);">-<?= number_format($total_cost_vnd) ?> ₫</td>
                <td class="text-right td-muted"><?= count($carrier_costs) ?> shipments</td>
              </tr>
              <tr>
                <td class="td-muted">📊 Outstanding (not yet collected)</td>
                <td class="text-right td-muted"><?= number_format(115800000) ?> ₫</td>
                <td class="text-right td-muted">5 invoices pending</td>
              </tr>
              <tr style="background:<?= $pl_vnd >= 0 ? 'rgba(107,140,62,0.08)' : 'rgba(192,57,43,0.08)' ?>;">
                <td class="font-bold" style="font-size:15px;">💰 Net P&L (Current)</td>
                <td class="text-right font-bold" style="font-size:15px;color:<?= $pl_vnd >= 0 ? 'var(--c-green)' : 'var(--c-red)' ?>;">
                  <?= $pl_vnd >= 0 ? '' : '-' ?><?= number_format(abs($pl_vnd)) ?> ₫
                </td>
                <td class="text-right td-muted">Collected vs. paid out</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Monthly P&L chart -->
    <div class="card mt-16">
      <div class="card-header"><span class="card-title">6-Month P&L Trend (VND)</span></div>
      <div class="card-body">
        <canvas id="chartPL" style="height:250px;"></canvas>
      </div>
    </div>
  </div>
</div>

<?php close_page(); ?>
