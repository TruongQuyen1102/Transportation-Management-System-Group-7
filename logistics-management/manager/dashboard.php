<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('manager');

$kpi        = get_kpi_summary();
$revenue    = get_monthly_revenue();
$perf       = get_delivery_performance();
$orders     = array_slice(array_reverse(get_orders()), 0, 5);
$exceptions = array_filter(get_exceptions(), fn($e) => $e['status'] === 'OPEN');
$requests   = array_filter(get_manager_requests(), fn($r) => $r['status'] === 'PENDING');

open_page('KPI Dashboard', 'dashboard', [['label' => 'Manager'], ['label' => 'KPI Dashboard']]);
?>

<!-- Page Header -->
<div class="page-header">
  <div>
    <h1 class="page-title">KPI Dashboard</h1>
    <p class="text-muted" style="margin-top:4px;">Overview for <?= date('F Y') ?> · Last updated <?= date('H:i') ?></p>
  </div>
  <div class="page-actions">
    <a href="/manager/reports.php" class="btn btn-outline btn-sm">📈 Full Report</a>
    <a href="/manager/cost_analysis.php" class="btn btn-primary btn-sm">💰 Cost Analysis</a>
  </div>
</div>

<!-- Stats Row -->
<div class="stats-grid">
  <div class="stat-card navy">
    <div class="stat-icon">📦</div>
    <div class="stat-value"><?= $kpi['total_orders'] ?></div>
    <div class="stat-label">Total Orders</div>
    <div class="stat-trend">↑ This month</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">✅</div>
    <div class="stat-value"><?= $kpi['delivered'] ?></div>
    <div class="stat-label">Delivered</div>
    <div class="stat-trend">On-time & completed</div>
  </div>
  <div class="stat-card olive">
    <div class="stat-icon">🚛</div>
    <div class="stat-value"><?= $kpi['in_transit'] ?></div>
    <div class="stat-label">In Transit</div>
    <div class="stat-trend">Active shipments</div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon">🎯</div>
    <div class="stat-value"><?= $kpi['on_time_rate'] ?>%</div>
    <div class="stat-label">On-Time Rate</div>
    <div class="stat-trend">Target: 85%</div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon">⚙️</div>
    <div class="stat-value"><?= $kpi['fleet_utilization'] ?>%</div>
    <div class="stat-label">Fleet Utilization</div>
    <div class="stat-trend">7 assets tracked</div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon">⚠️</div>
    <div class="stat-value"><?= $kpi['exceptions_open'] ?></div>
    <div class="stat-label">Open Exceptions</div>
    <div class="stat-trend">Requires attention</div>
  </div>
</div>

<!-- Charts Row -->
<div class="grid-2 mt-16">
  <!-- Revenue Chart -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">📈 Monthly Revenue (VND)</span>
      <span class="badge badge-green">Live</span>
    </div>
    <div class="card-body">
      <canvas id="revenueChart" class="chart-container" style="height:260px;"></canvas>
    </div>
  </div>

  <!-- Delivery Performance Chart -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">🎯 Delivery Performance (%)</span>
      <span class="badge badge-blue">6-month trend</span>
    </div>
    <div class="card-body">
      <canvas id="perfChart" class="chart-container" style="height:260px;"></canvas>
    </div>
  </div>
</div>

<!-- Recent Orders + Side Cards -->
<div class="grid-2 mt-16" style="grid-template-columns: 1fr 420px;">
  <!-- Recent Orders Table -->
  <div class="card">
    <div class="card-header flex-between">
      <span class="card-title">🧾 Recent Orders</span>
      <a href="/operations/shipments.php" class="btn btn-ghost btn-sm">View All →</a>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Order ID</th>
              <th>Customer</th>
              <th>Route</th>
              <th>Date</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $o): ?>
            <tr>
              <td><strong><?= htmlspecialchars($o['id']) ?></strong></td>
              <td>
                <div><?= htmlspecialchars($o['customer']) ?></div>
                <div class="td-muted"><?= number_format($o['weight']) ?> kg</div>
              </td>
              <td>
                <div class="truncate" style="max-width:180px;" title="<?= htmlspecialchars($o['pickup']) ?> → <?= htmlspecialchars($o['delivery']) ?>">
                  <?= htmlspecialchars($o['pickup']) ?>
                </div>
                <div class="td-muted truncate" style="max-width:180px;">→ <?= htmlspecialchars($o['delivery']) ?></div>
              </td>
              <td class="td-muted"><?= $o['order_date'] ?></td>
              <td><?= status_badge($o['status']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Right column: Exceptions + Requests -->
  <div class="flex" style="flex-direction:column; gap:16px;">

    <!-- Active Exceptions -->
    <div class="card">
      <div class="card-header flex-between">
        <span class="card-title">⚠️ Open Exceptions</span>
        <a href="/manager/exceptions.php" class="btn btn-ghost btn-sm">Manage →</a>
      </div>
      <div class="card-body" style="padding:0;">
        <?php foreach ($exceptions as $exc): ?>
        <div class="activity-item" style="padding:12px 16px; border-bottom:1px solid var(--border);">
          <div class="flex-between mb-4">
            <strong style="font-size:13px;"><?= htmlspecialchars($exc['id']) ?></strong>
            <?= status_badge($exc['status']) ?>
          </div>
          <div style="font-size:13px; color:var(--text-primary); margin-bottom:4px;">
            <span class="badge badge-red" style="font-size:11px;"><?= htmlspecialchars($exc['type']) ?></span>
            &nbsp;<?= htmlspecialchars($exc['shipment_id']) ?> · <?= htmlspecialchars($exc['carrier']) ?>
          </div>
          <div class="td-muted" style="font-size:12px; line-height:1.5;"><?= htmlspecialchars(mb_substr($exc['desc'], 0, 90)) ?>…</div>
          <div class="td-muted mt-4" style="font-size:11px;">Reported: <?= $exc['created_at'] ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($exceptions)): ?>
          <div style="padding:24px; text-align:center; color:var(--text-muted); font-size:13px;">✅ No open exceptions</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Pending Requests -->
    <div class="card">
      <div class="card-header flex-between">
        <span class="card-title">📩 Pending Requests</span>
        <a href="/manager/requests.php" class="btn btn-ghost btn-sm">Manage →</a>
      </div>
      <div class="card-body" style="padding:0;">
        <?php foreach ($requests as $req): ?>
        <div class="activity-item" style="padding:12px 16px; border-bottom:1px solid var(--border);">
          <div class="flex-between mb-4">
            <strong style="font-size:13px;"><?= htmlspecialchars($req['id']) ?></strong>
            <span class="badge badge-<?= $req['priority'] === 'High' ? 'red' : ($req['priority'] === 'Medium' ? 'yellow' : 'gray') ?>"><?= $req['priority'] ?></span>
          </div>
          <div style="font-size:13px; color:var(--text-primary); margin-bottom:4px;">
            <strong><?= htmlspecialchars($req['submitted_by']) ?></strong>
            <span class="td-muted"> · <?= htmlspecialchars($req['type']) ?></span>
          </div>
          <div class="td-muted" style="font-size:12px; line-height:1.5;"><?= htmlspecialchars(mb_substr($req['desc'], 0, 85)) ?>…</div>
          <div class="flex gap-8 mt-8">
            <button class="btn btn-success btn-sm" data-confirm="Approve request <?= $req['id'] ?>?">✓ Approve</button>
            <button class="btn btn-danger btn-sm" data-confirm="Reject request <?= $req['id'] ?>?">✗ Reject</button>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($requests)): ?>
          <div style="padding:24px; text-align:center; color:var(--text-muted); font-size:13px;">✅ No pending requests</div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<!-- Chart Scripts -->
<script>
(function() {
  const navy  = '#0C2840';
  const green = '#6B8C3E';
  const red   = '#C0392B';
  const yellow= '#E8B84B';

  // Revenue Line Chart
  const revData = <?= json_encode($revenue) ?>;
  new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
      labels: revData.labels,
      datasets: [{
        label: 'Revenue (VND)',
        data: revData.vnd,
        borderColor: navy,
        backgroundColor: 'rgba(12,40,64,0.08)',
        borderWidth: 2.5,
        pointBackgroundColor: navy,
        pointRadius: 5,
        pointHoverRadius: 7,
        fill: true,
        tension: 0.4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => ' ' + Number(ctx.raw).toLocaleString('vi-VN') + ' ₫'
          }
        }
      },
      scales: {
        y: {
          beginAtZero: false,
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

  // Delivery Performance Bar Chart
  const perfData = <?= json_encode($perf) ?>;
  new Chart(document.getElementById('perfChart'), {
    type: 'bar',
    data: {
      labels: perfData.labels,
      datasets: [
        {
          label: 'On-Time (%)',
          data: perfData.on_time,
          backgroundColor: green,
          borderRadius: 4,
          borderSkipped: false
        },
        {
          label: 'Delayed (%)',
          data: perfData.delayed,
          backgroundColor: red,
          borderRadius: 4,
          borderSkipped: false
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'top',
          labels: { font: { size: 11 }, usePointStyle: true }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
          ticks: { callback: val => val + '%', font: { size: 11 } },
          grid: { color: 'rgba(0,0,0,0.05)' }
        },
        x: { ticks: { font: { size: 11 } }, grid: { display: false } }
      }
    }
  });
})();
</script>

<?php close_page(); ?>
