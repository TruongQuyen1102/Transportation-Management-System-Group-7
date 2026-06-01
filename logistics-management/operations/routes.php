<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('operations');

$routes = get_routes();
$total  = count($routes);
$avg_dist = round(array_sum(array_column($routes,'distance')) / $total);
$avg_dur  = round(array_sum(array_column($routes,'duration')) / $total, 1);

open_page('Routes', 'routes', [['label'=>'Operations'],['label'=>'Routes']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Route Management</h1>
    <p class="text-muted" style="margin-top:4px;"><?= $total ?> configured routes across Vietnam</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-primary" data-modal-open="modalAddRoute">+ Add Route</button>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card navy">
    <div class="stat-icon">🗺️</div>
    <div class="stat-value"><?= $total ?></div>
    <div class="stat-label">Total Routes</div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon">📏</div>
    <div class="stat-value"><?= fmt_num($avg_dist) ?> km</div>
    <div class="stat-label">Avg. Distance</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">⏱️</div>
    <div class="stat-value"><?= $avg_dur ?>h</div>
    <div class="stat-label">Avg. Duration</div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon">💰</div>
    <div class="stat-value"><?= fmt_currency(array_sum(array_column($routes,'cost_base')),'VND') ?></div>
    <div class="stat-label">Total Base Costs</div>
  </div>
</div>

<div class="grid-2 mt-16" style="gap:20px;">

  <!-- Routes Table -->
  <div class="card col-span-2" style="grid-column:1/-1;">
    <div class="card-header">
      <h3 class="card-title">All Routes</h3>
      <div class="search-input-wrapper" style="width:280px;">
        <span>🔍</span>
        <input type="text" class="form-control" placeholder="Search routes..." data-table-search="routesTable">
      </div>
    </div>
    <div class="table-wrapper">
      <table id="routesTable">
        <thead>
          <tr>
            <th>Route ID</th>
            <th>Name</th>
            <th>Start → End</th>
            <th>Distance</th>
            <th>Duration</th>
            <th>Base Cost (VND)</th>
            <th style="text-align:right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($routes as $r): ?>
            <tr>
              <td><strong><?= $r['id'] ?></strong></td>
              <td><?= htmlspecialchars($r['name']) ?></td>
              <td>
                <span class="badge badge-navy"><?= htmlspecialchars($r['start']) ?></span>
                <span style="margin:0 6px;color:var(--text-muted);">→</span>
                <span class="badge badge-slate"><?= htmlspecialchars($r['end']) ?></span>
              </td>
              <td><?= fmt_num($r['distance']) ?> km</td>
              <td>
                <?php if ($r['duration'] < 24): ?>
                  <?= $r['duration'] ?>h
                <?php else: ?>
                  <?= round($r['duration']/24,1) ?> days
                <?php endif; ?>
              </td>
              <td><?= fmt_currency($r['cost_base'], 'VND') ?></td>
              <td style="text-align:right;">
                <div class="action-menu">
                  <button class="action-menu-btn btn btn-ghost btn-sm" data-dropdown-toggle="actRte<?= $r['id'] ?>">⋯</button>
                  <div class="action-dropdown" id="actRte<?= $r['id'] ?>">
                    <a href="#" class="dropdown-item" data-modal-open="modalEditRoute">✏️ Edit Route</a>
                    <a href="#" class="dropdown-item">📦 View Shipments</a>
                    <a href="#" class="dropdown-item danger" data-confirm="Deactivate this route?">🚫 Deactivate</a>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Distance Chart -->
<div class="card mt-20">
  <div class="card-header">
    <h3 class="card-title">📊 Distance Comparison by Route</h3>
  </div>
  <div class="card-body">
    <canvas id="routeDistChart" style="height:280px;"></canvas>
  </div>
</div>

<!-- Modal: Add Route -->
<div class="modal-overlay" id="modalAddRoute">
  <div class="modal" style="max-width:540px;">
    <div class="modal-header">
      <h3 class="modal-title">🗺️ Add New Route</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="modal-body">
      <form data-feedback="Route added successfully!">
        <div class="form-group">
          <label class="form-label">Route Name *</label>
          <input type="text" class="form-control" placeholder="e.g. HCMC → Hanoi Express">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Start Location *</label>
            <input type="text" class="form-control" placeholder="e.g. HCMC Central Hub">
          </div>
          <div class="form-group">
            <label class="form-label">End Location *</label>
            <input type="text" class="form-control" placeholder="e.g. Hanoi North Depot">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Distance (km)</label>
            <input type="number" class="form-control" placeholder="0">
          </div>
          <div class="form-group">
            <label class="form-label">Duration (hours)</label>
            <input type="number" class="form-control" step="0.5" placeholder="0">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Base Cost (VND)</label>
            <input type="number" class="form-control" placeholder="0">
          </div>
          <div class="form-group">
            <label class="form-label">Transport Mode</label>
            <select class="form-control">
              <option>Road</option>
              <option>Air</option>
              <option>Waterway</option>
              <option>Multi-modal</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Notes / Restrictions</label>
          <textarea class="form-control" rows="2" placeholder="Any special notes..."></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline modal-close">Cancel</button>
      <button class="btn btn-primary">Add Route</button>
    </div>
  </div>
</div>

<!-- Modal: Edit Route -->
<div class="modal-overlay" id="modalEditRoute">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <h3 class="modal-title">✏️ Edit Route</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Route Name</label>
        <input type="text" class="form-control" value="HCMC → Hanoi Express">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Distance (km)</label>
          <input type="number" class="form-control" value="1726">
        </div>
        <div class="form-group">
          <label class="form-label">Duration (hours)</label>
          <input type="number" class="form-control" value="36">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Base Cost (VND)</label>
        <input type="number" class="form-control" value="8500000">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline modal-close">Cancel</button>
      <button class="btn btn-primary">Save Changes</button>
    </div>
  </div>
</div>

<script>
const routeLabels = <?= json_encode(array_map(fn($r) => $r['id'] . ': ' . mb_substr($r['name'],0,18,'UTF-8').'…', $routes)) ?>;
const routeDistances = <?= json_encode(array_column($routes,'distance')) ?>;
const routeCosts = <?= json_encode(array_map(fn($r) => round($r['cost_base']/1000000,2), $routes)) ?>;

new Chart(document.getElementById('routeDistChart'), {
  type: 'bar',
  data: {
    labels: routeLabels,
    datasets: [
      {
        label: 'Distance (km)',
        data: routeDistances,
        backgroundColor: '#0C2840',
        borderRadius: 4,
        yAxisID: 'y'
      },
      {
        label: 'Base Cost (M VND)',
        data: routeCosts,
        backgroundColor: '#E8B84B',
        borderRadius: 4,
        yAxisID: 'y1'
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y',
    plugins: {
      legend: { position: 'top' }
    },
    scales: {
      y:  { position:'left',  title:{ display:true, text:'Distance (km)' } },
      y1: { position:'right', title:{ display:true, text:'Cost (M VND)' }, grid:{ drawOnChartArea:false } }
    }
  }
});
</script>

<?php close_page(); ?>
