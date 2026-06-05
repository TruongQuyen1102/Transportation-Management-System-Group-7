<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/db.php';
auth_require('operations');

$db = get_db();

// ── MIGRATION: add BaseCost + Notes columns if missing ────────────────────────
$db->query("ALTER TABLE route
    ADD COLUMN IF NOT EXISTS `BaseCost` decimal(15,2) DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `Notes` text DEFAULT NULL");

$message = '';
$message_type = '';

// ══════════════════════════════════════════════════════════════════════════════
//  POST HANDLING
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── 1. ADD ROUTE ──────────────────────────────────────────────────────────
    if ($action === 'add_route') {
        $name      = trim($_POST['route_name'] ?? '');
        $start     = trim($_POST['start_location'] ?? '');
        $end       = trim($_POST['end_location'] ?? '');
        $distance  = (float)($_POST['distance'] ?? 0);
        $duration  = (float)($_POST['duration'] ?? 0);
        $dur_unit  = $_POST['duration_unit'] ?? 'Hours';
        $mode      = $_POST['transport_mode'] ?? 'Road';
        $cost      = (float)($_POST['base_cost'] ?? 0);
        $notes     = trim($_POST['notes'] ?? '');

        if (!$name || !$start || !$end) {
            $message = 'Please fill in Route Name, Start and End Location.';
            $message_type = 'danger';
        } else {
            $stmt = $db->prepare("INSERT INTO route
                (RouteName, StartLocation, EndLocation, TransportMode,
                 EstimatedDistance, EstimatedDuration, DurationUnit, BaseCost, Notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssddsds', $name, $start, $end, $mode,
                              $distance, $duration, $dur_unit, $cost, $notes);
            if ($stmt->execute()) {
                $message = 'Route #' . $db->insert_id . ' added successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error: ' . $db->error;
                $message_type = 'danger';
            }
        }
    }

    // ── 2. EDIT ROUTE ─────────────────────────────────────────────────────────
    elseif ($action === 'edit_route') {
        $route_id  = (int)($_POST['route_id'] ?? 0);
        $name      = trim($_POST['route_name'] ?? '');
        $start     = trim($_POST['start_location'] ?? '');
        $end       = trim($_POST['end_location'] ?? '');
        $distance  = (float)($_POST['distance'] ?? 0);
        $duration  = (float)($_POST['duration'] ?? 0);
        $dur_unit  = $_POST['duration_unit'] ?? 'Hours';
        $mode      = $_POST['transport_mode'] ?? 'Road';
        $cost      = (float)($_POST['base_cost'] ?? 0);
        $notes     = trim($_POST['notes'] ?? '');

        if ($route_id) {
            $stmt = $db->prepare("UPDATE route SET
                RouteName=?, StartLocation=?, EndLocation=?, TransportMode=?,
                EstimatedDistance=?, EstimatedDuration=?, DurationUnit=?,
                BaseCost=?, Notes=?
                WHERE RouteID=?");
            $stmt->bind_param('ssssddsdsi', $name, $start, $end, $mode,
                              $distance, $duration, $dur_unit, $cost, $notes, $route_id);
            $stmt->execute();
            $message = "Route #$route_id updated successfully.";
            $message_type = 'success';
        }
    }

    // ── 3. DEACTIVATE (DELETE) ROUTE ─────────────────────────────────────────
    elseif ($action === 'deactivate_route') {
        $route_id = (int)($_POST['route_id'] ?? 0);
        if ($route_id) {
            $stmt = $db->prepare("DELETE FROM route WHERE RouteID=?");
            $stmt->bind_param('i', $route_id);
            $stmt->execute();
            $message = "Route #$route_id removed.";
            $message_type = 'success';
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  FETCH DATA
// ══════════════════════════════════════════════════════════════════════════════
$routes = [];
$res = $db->query("SELECT * FROM route ORDER BY RouteID");
while ($row = $res->fetch_assoc()) { $routes[] = $row; }

$total    = count($routes);
$avg_dist = $total ? round(array_sum(array_column($routes, 'EstimatedDistance')) / $total) : 0;
$avg_dur  = $total ? round(array_sum(array_column($routes, 'EstimatedDuration')) / $total, 1) : 0;
$total_cost = array_sum(array_column($routes, 'BaseCost'));

// Duration label helper
function dur_label(float $val, string $unit): string {
    return match($unit) {
        'Days'   => $val . ' days',
        'Months' => $val . ' mo',
        default  => $val . 'h',
    };
}

open_page('Routes', 'routes', [['label'=>'Planning'],['label'=>'Routes']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Route Management</h1>
    <p class="text-muted" style="margin-top:4px;"><?= $total ?> configured routes</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-primary" data-modal-open="modalAddRoute">+ Add Route</button>
  </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $message_type ?>" style="margin-bottom:16px;">
  <?= $message_type === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card navy">
    <div class="stat-icon">🗺️</div>
    <div class="stat-info">
      <div class="stat-value"><?= $total ?></div>
      <div class="stat-label">Total Routes</div>
    </div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon">📏</div>
    <div class="stat-info">
      <div class="stat-value"><?= number_format($avg_dist) ?> km</div>
      <div class="stat-label">Avg. Distance</div>
    </div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">⏱️</div>
    <div class="stat-info">
      <div class="stat-value"><?= $avg_dur ?></div>
      <div class="stat-label">Avg. Duration</div>
    </div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon">💰</div>
    <div class="stat-info">
      <div class="stat-value"><?= number_format($total_cost/1000000, 1) ?>M</div>
      <div class="stat-label">Total Base Cost (VND)</div>
    </div>
  </div>
</div>

<!-- Routes Table -->
<div class="search-input-wrapper" style="flex:1;">
  <span class="search-icon">🔍</span>
  <input type="text" class="form-control" placeholder="Search routes..."
          data-table-search="routesTable" style="width:100%;">
</div>
<div class="card mt-8">
  <div class="card-header">
    <h3 class="card-title">All Routes</h3>
  </div>
  <div class="table-wrapper">
    <table id="routesTable">
      <thead>
        <tr>
          <th style="width:90px;">Route ID</th>
          <th style="width:200px;">Name</th>
          <th>Start → End</th>
          <th style="width:90px;">Mode</th>
          <th style="width:90px;">Distance</th>
          <th style="width:90px;">Duration</th>
          <th style="width:130px;">Base Cost (VND)</th>
          <th style="width:80px;text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($routes as $r):
          $mode_badge = match($r['TransportMode']) {
            'Air'   => '<span class="badge badge-blue">✈️ Air</span>',
            'Ocean' => '<span class="badge badge-olive">🚢 Ocean</span>',
            'Rail'  => '<span class="badge badge-slate">🚂 Rail</span>',
            default => '<span class="badge badge-navy">🚛 Road</span>',
          };
          $route_label = 'RTE' . str_pad($r['RouteID'], 3, '0', STR_PAD_LEFT);
        ?>
          <tr>
            <td><strong><?= $route_label ?></strong></td>
            <td><?= htmlspecialchars($r['RouteName']) ?></td>
            <td style="white-space:nowrap;">
              <span class="badge badge-navy"><?= htmlspecialchars($r['StartLocation']) ?></span>
              <span style="margin:0 4px;color:var(--text-muted);">→</span>
              <span class="badge badge-slate"><?= htmlspecialchars($r['EndLocation']) ?></span>
            </td>
            <td><?= $mode_badge ?></td>
            <td><?= number_format($r['EstimatedDistance']) ?> km</td>
            <td><?= dur_label((float)$r['EstimatedDuration'], $r['DurationUnit']) ?></td>
            <td><?= $r['BaseCost'] ? number_format($r['BaseCost']) : '—' ?></td>
            <td style="text-align:right;">
              <div class="action-menu">
                <button class="action-menu-btn btn btn-ghost btn-sm"
                        data-dropdown-toggle="actRte<?= $r['RouteID'] ?>">⋯</button>
                <div class="action-dropdown" id="actRte<?= $r['RouteID'] ?>">
                  <a href="#" class="dropdown-item"
                     onclick="openEditRoute(
                       <?= $r['RouteID'] ?>,
                       '<?= addslashes($r['RouteName']) ?>',
                       '<?= addslashes($r['StartLocation']) ?>',
                       '<?= addslashes($r['EndLocation']) ?>',
                       <?= $r['EstimatedDistance'] ?>,
                       <?= $r['EstimatedDuration'] ?>,
                       '<?= $r['DurationUnit'] ?>',
                       '<?= $r['TransportMode'] ?>',
                       <?= $r['BaseCost'] ?? 0 ?>,
                       '<?= addslashes($r['Notes'] ?? '') ?>'
                     ); return false;">✏️ Edit Route</a>
                  <a href="#" class="dropdown-item" style="color:#ef4444;"
                     onclick="deleteRoute(<?= $r['RouteID'] ?>); return false;">🗑️ Delete Route</a>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Charts Row -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:32px;">

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">📏 Distance by Route (km)</h3>
    </div>
    <div class="card-body" style="padding:16px 20px;">
      <div style="position:relative;width:100%;height:300px;">
        <canvas id="chartDistance"></canvas>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">⏱️ Duration by Route (hours)</h3>
    </div>
    <div class="card-body" style="padding:16px 20px;">
      <div style="position:relative;width:100%;height:300px;">
        <canvas id="chartDuration"></canvas>
      </div>
    </div>
  </div>

</div>

<!-- Hidden: Delete Route -->
<form id="formDeleteRoute" method="POST" action="" style="display:none;">
  <input type="hidden" name="action" value="deactivate_route">
  <input type="hidden" name="route_id" id="delete_route_id">
</form>

<!-- Modal: Add Route -->
<div class="modal-overlay" id="modalAddRoute">
  <div class="modal" style="max-width:540px;">
    <div class="modal-header">
      <h3 class="modal-title">🗺️ Add New Route</h3>
      <button class="modal-close">×</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="add_route">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Route Name <span style="color:red">*</span></label>
          <input type="text" class="form-control" name="route_name"
                 placeholder="e.g. HCMC → Hanoi Express" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Start Location <span style="color:red">*</span></label>
            <input type="text" class="form-control" name="start_location"
                   placeholder="e.g. Hanoi (VN)" required>
          </div>
          <div class="form-group">
            <label class="form-label">End Location <span style="color:red">*</span></label>
            <input type="text" class="form-control" name="end_location"
                   placeholder="e.g. HCMC (VN)" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Distance (km)</label>
            <input type="number" class="form-control" name="distance" placeholder="0" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Transport Mode</label>
            <select class="form-control" name="transport_mode">
              <option value="Road">Road</option>
              <option value="Air">Air</option>
              <option value="Ocean">Ocean</option>
              <option value="Rail">Rail</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Duration</label>
            <input type="number" class="form-control" name="duration" step="0.5" placeholder="0" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Duration Unit</label>
            <select class="form-control" name="duration_unit">
              <option value="Hours">Hours</option>
              <option value="Days">Days</option>
              <option value="Months">Months</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Base Cost (VND)</label>
          <input type="number" class="form-control" name="base_cost" placeholder="0" min="0">
        </div>
        <div class="form-group">
          <label class="form-label">Notes / Restrictions</label>
          <textarea class="form-control" name="notes" rows="2"
                    placeholder="Any special notes..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Add Route</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Edit Route -->
<div class="modal-overlay" id="modalEditRoute">
  <div class="modal" style="max-width:540px;">
    <div class="modal-header">
      <h3 class="modal-title">✏️ Edit Route</h3>
      <button class="modal-close">×</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="edit_route">
      <input type="hidden" name="route_id" id="edit_route_id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Route Name</label>
          <input type="text" class="form-control" name="route_name" id="edit_name">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Start Location</label>
            <input type="text" class="form-control" name="start_location" id="edit_start">
          </div>
          <div class="form-group">
            <label class="form-label">End Location</label>
            <input type="text" class="form-control" name="end_location" id="edit_end">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Distance (km)</label>
            <input type="number" class="form-control" name="distance" id="edit_distance">
          </div>
          <div class="form-group">
            <label class="form-label">Transport Mode</label>
            <select class="form-control" name="transport_mode" id="edit_mode">
              <option value="Road">Road</option>
              <option value="Air">Air</option>
              <option value="Ocean">Ocean</option>
              <option value="Rail">Rail</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Duration</label>
            <input type="number" class="form-control" name="duration" id="edit_duration" step="0.5">
          </div>
          <div class="form-group">
            <label class="form-label">Duration Unit</label>
            <select class="form-control" name="duration_unit" id="edit_dur_unit">
              <option value="Hours">Hours</option>
              <option value="Days">Days</option>
              <option value="Months">Months</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Base Cost (VND)</label>
          <input type="number" class="form-control" name="base_cost" id="edit_cost">
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea class="form-control" name="notes" id="edit_notes" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditRoute(id, name, start, end, dist, dur, durUnit, mode, cost, notes) {
    document.getElementById('edit_route_id').value = id;
    document.getElementById('edit_name').value     = name;
    document.getElementById('edit_start').value    = start;
    document.getElementById('edit_end').value      = end;
    document.getElementById('edit_distance').value = dist;
    document.getElementById('edit_duration').value = dur;
    document.getElementById('edit_cost').value     = cost;
    document.getElementById('edit_notes').value    = notes;
    ['edit_mode','edit_dur_unit'].forEach(selId => {
        const map = { edit_mode: mode, edit_dur_unit: durUnit };
        const sel = document.getElementById(selId);
        for (let o of sel.options) {
            if (o.value === map[selId]) { o.selected = true; break; }
        }
    });

    const overlay = document.getElementById('modalEditRoute');
    overlay.classList.add('open');
}

function deleteRoute(id) {
    if (!confirm('Delete this route? This cannot be undone.')) return;
    document.getElementById('delete_route_id').value = id;
    document.getElementById('formDeleteRoute').submit();
}

// Charts
document.addEventListener('DOMContentLoaded', function () {
  const routeLabels    = <?= json_encode(array_map(fn($r) => 'RTE'.str_pad($r['RouteID'],3,'0',STR_PAD_LEFT).': '.mb_substr($r['RouteName'],0,14,'UTF-8').'…', $routes)) ?>;
  const routeDistances = <?= json_encode(array_map(fn($r) => (float)$r['EstimatedDistance'], $routes)) ?>;
  // Convert everything to hours: Days × 24, Months × 720, Hours × 1
  const routeDurations = <?= json_encode(array_map(function($r) {
      $val = (float)$r['EstimatedDuration'];
      return match($r['DurationUnit'] ?? 'Hours') {
          'Days'   => $val * 24,
          'Months' => $val * 720,
          default  => $val,
      };
  }, $routes)) ?>;

  const sharedOptions = {
    responsive: true,
    maintainAspectRatio: false,
    indexAxis: 'y',
    plugins: { legend: { display: false } },
    scales: {
      x: { grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 11 } } },
      y: { ticks: { font: { size: 11 } } }
    }
  };

  // Distance chart
  const c1 = document.getElementById('chartDistance');
  if (c1) new Chart(c1, {
    type: 'bar',
    data: {
      labels: routeLabels,
      datasets: [{
        label: 'Distance (km)',
        data: routeDistances,
        backgroundColor: '#0C2840',
        borderRadius: 4,
      }]
    },
    options: {
      ...sharedOptions,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => ' ' + ctx.parsed.x.toLocaleString() + ' km'
          }
        }
      }
    }
  });

  // Duration chart (all values in hours)
  const c2 = document.getElementById('chartDuration');
  if (c2) new Chart(c2, {
    type: 'bar',
    data: {
      labels: routeLabels,
      datasets: [{
        label: 'Duration (hours)',
        data: routeDurations,
        backgroundColor: '#E8B84B',
        borderRadius: 4,
      }]
    },
    options: {
      ...sharedOptions,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => ' ' + ctx.parsed.x.toLocaleString() + ' hours'
          }
        }
      },
      scales: {
        x: {
          grid: { color: 'rgba(0,0,0,0.05)' },
          ticks: {
            font: { size: 11 },
            callback: val => val.toLocaleString() + 'h'
          }
        },
        y: { ticks: { font: { size: 11 } } }
      }
    }
  });
});
</script>

<?php close_page(); $db->close(); ?>