<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('operations');

$schedules = get_schedules();
$total      = count($schedules);
$completed  = count(array_filter($schedules, fn($s) => $s['status']==='COMPLETED'));
$in_prog    = count(array_filter($schedules, fn($s) => $s['status']==='IN_PROGRESS'));
$scheduled  = count(array_filter($schedules, fn($s) => $s['status']==='SCHEDULED'));

open_page('Delivery Schedules', 'schedules', [['label'=>'Operations'],['label'=>'Schedules']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Delivery Schedules</h1>
    <p class="text-muted" style="margin-top:4px;">Track planned vs. actual delivery times</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-outline btn-sm">📥 Export</button>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card navy">
    <div class="stat-icon">📅</div>
    <div class="stat-value"><?= $total ?></div>
    <div class="stat-label">Total Schedules</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">✅</div>
    <div class="stat-value"><?= $completed ?></div>
    <div class="stat-label">Completed</div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon">🔄</div>
    <div class="stat-value"><?= $in_prog ?></div>
    <div class="stat-label">In Progress</div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon">🕐</div>
    <div class="stat-value"><?= $scheduled ?></div>
    <div class="stat-label">Scheduled</div>
  </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar mt-16">
  <div class="search-input-wrapper">
    <span>🔍</span>
    <input type="text" class="form-control" placeholder="Search schedule, shipment, customer..." data-table-search="schedulesTable">
  </div>
  <select class="form-control" style="width:150px;">
    <option value="">All Statuses</option>
    <option>COMPLETED</option>
    <option>IN_PROGRESS</option>
    <option>SCHEDULED</option>
  </select>
  <input type="date" class="form-control" style="width:150px;">
  <input type="date" class="form-control" style="width:150px;">
</div>

<div class="card mt-12">
  <div class="table-wrapper">
    <table id="schedulesTable">
      <thead>
        <tr>
          <th>SCH ID</th>
          <th>Shipment</th>
          <th>Customer</th>
          <th>Route</th>
          <th>Planned Dep.</th>
          <th>Actual Dep.</th>
          <th>Planned Arr.</th>
          <th>Actual Arr.</th>
          <th>Variance</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($schedules as $sch): ?>
          <?php
            $variance = $sch['variance'];
            if ($variance === null) {
              $var_html = '<span class="td-muted">—</span>';
            } elseif ($variance === 0) {
              $var_html = '<span class="badge badge-green">On Time</span>';
            } elseif ($variance < 0) {
              $var_html = '<span class="badge badge-green">' . $variance . ' min</span>';
            } else {
              $var_html = '<span class="badge badge-red">+' . $variance . ' min</span>';
            }
          ?>
          <tr>
            <td><strong><?= $sch['id'] ?></strong></td>
            <td><a href="/operations/shipment_detail.php?id=<?= $sch['shipment_id'] ?>" style="color:var(--c-yellow);"><?= $sch['shipment_id'] ?></a></td>
            <td><?= htmlspecialchars($sch['customer']) ?></td>
            <td class="td-muted"><?= htmlspecialchars($sch['route']) ?></td>
            <td class="td-muted"><?= $sch['planned_dep'] ?></td>
            <td class="td-muted"><?= $sch['actual_dep'] ?? '—' ?></td>
            <td class="td-muted"><?= $sch['planned_arr'] ?></td>
            <td class="td-muted"><?= $sch['actual_arr'] ?? '—' ?></td>
            <td><?= $var_html ?></td>
            <td><?= status_badge($sch['status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Variance Summary -->
<div class="card mt-20">
  <div class="card-header">
    <h3 class="card-title">📊 Schedule Variance Summary</h3>
  </div>
  <div class="card-body">
    <canvas id="varianceChart" style="height:200px;"></canvas>
  </div>
</div>

<script>
const schLabels = <?= json_encode(array_column($schedules,'id')) ?>;
const schVariances = <?= json_encode(array_map(fn($s) => $s['variance'] ?? 0, $schedules)) ?>;
const varColors = schVariances.map(v => v > 0 ? '#C0392B' : (v < 0 ? '#6B8C3E' : '#E8B84B'));

new Chart(document.getElementById('varianceChart'), {
  type: 'bar',
  data: {
    labels: schLabels,
    datasets: [{
      label: 'Variance (minutes)',
      data: schVariances,
      backgroundColor: varColors,
      borderRadius: 4
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: ctx => (ctx.parsed.y > 0 ? '+' : '') + ctx.parsed.y + ' min' } }
    },
    scales: {
      y: { title: { display:true, text:'Variance (minutes)' } }
    }
  }
});
</script>

<?php close_page(); ?>
