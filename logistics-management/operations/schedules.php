<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/db.php';
auth_require('operations');

$db = get_db();
$message      = '';
$message_type = '';

// ══════════════════════════════════════════════════════════════════════════════
//  POST HANDLING — Update actual departure / arrival times
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── 1. UPDATE SCHEDULE (actual departure + arrival) ───────────────────────
    if ($action === 'update_schedule') {
        $shipment_id    = (int)($_POST['shipment_id'] ?? 0);
        $actual_dep     = trim($_POST['actual_dep'] ?? '');
        $actual_arr     = trim($_POST['actual_arr'] ?? '');
        $planned_dep    = trim($_POST['planned_dep'] ?? '');
        $planned_arr    = trim($_POST['planned_arr'] ?? '');  // EstimatedArrival

        if (!$shipment_id) {
            $message      = 'Invalid shipment ID.';
            $message_type = 'danger';
        } else {
            // Determine new Status based on filled fields
            // Leave status management to shipments page — only update time fields here
            $actual_dep_val = $actual_dep !== '' ? $actual_dep : null;
            $actual_arr_val = $actual_arr !== '' ? $actual_arr : null;
            $planned_dep_val = $planned_dep !== '' ? $planned_dep : null;
            $planned_arr_val = $planned_arr !== '' ? $planned_arr : null;

            $stmt = $db->prepare("
                UPDATE shipment
                SET    PlannedDeparture  = ?,
                       EstimatedArrival  = ?,
                       ActualDeparture   = ?,
                       ActualArrival     = ?
                WHERE  ShipmentID = ?
            ");
            $stmt->bind_param('ssssi', $planned_dep_val, $planned_arr_val, $actual_dep_val, $actual_arr_val, $shipment_id);

            if ($stmt->execute()) {
                $message      = "Schedule for Shipment #$shipment_id updated successfully!";
                $message_type = 'success';
            } else {
                $message      = 'DB error: ' . $db->error;
                $message_type = 'danger';
            }
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  FETCH DATA
// ══════════════════════════════════════════════════════════════════════════════
$sql = "
    SELECT
        s.ShipmentID,
        s.Status,
        s.PlannedDeparture,
        s.ActualDeparture,
        s.EstimatedArrival,
        s.ActualArrival,
        s.DeliveryDeadline,
        r.RouteName,
        r.StartLocation,
        r.EndLocation,
        bp.PartyName AS CustomerName,
        (SELECT so.OrderID FROM shipment_order so WHERE so.ShipmentID = s.ShipmentID LIMIT 1) AS OrderID
    FROM shipment s
    LEFT JOIN route r         ON s.RouteID  = r.RouteID
    LEFT JOIN shipment_order so2 ON so2.ShipmentID = s.ShipmentID
    LEFT JOIN order_info oi   ON oi.OrderID  = so2.OrderID
    LEFT JOIN business_party bp ON bp.PartyID = oi.CustomerID
    GROUP BY s.ShipmentID
    ORDER BY s.ShipmentID DESC
";
$result    = $db->query($sql);
$schedules = [];
while ($row = $result->fetch_assoc()) {
    // Calculate variance in minutes: ActualArrival vs EstimatedArrival
    if ($row['ActualArrival'] && $row['EstimatedArrival']) {
        $row['variance'] = round(
            (strtotime($row['ActualArrival']) - strtotime($row['EstimatedArrival'])) / 3600, 1
        );
    } else {
        $row['variance'] = null;
    }
    $schedules[] = $row;
}

// Stats
$total     = count($schedules);
$completed = count(array_filter($schedules, fn($s) => strtoupper($s['Status']) === 'DELIVERED'));
$in_prog   = count(array_filter($schedules, fn($s) => in_array(strtoupper(str_replace(' ', '_', $s['Status'])), ['IN_TRANSIT', 'IN TRANSIT'])));
$scheduled = count(array_filter($schedules, fn($s) => in_array(strtoupper($s['Status']), ['SCHEDULED', 'PENDING'])));

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

<?php if ($message): ?>
<div class="alert alert-<?= $message_type ?>" style="margin-bottom:16px;">
  <?= $message_type === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

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
    <div class="stat-label">Delivered</div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon">🔄</div>
    <div class="stat-value"><?= $in_prog ?></div>
    <div class="stat-label">In Transit</div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon">🕐</div>
    <div class="stat-value"><?= $scheduled ?></div>
    <div class="stat-label">Scheduled / Pending</div>
  </div>
</div>

<!-- Filter Bar -->
<div style="display:flex;align-items:center;gap:12px;margin-top:16px;flex-wrap:nowrap;">
  <div class="search-input-wrapper" style="flex:1;min-width:0;display:flex;align-items:center;">
    <span class="search-icon">🔍</span>
    <input type="text" class="form-control" placeholder="Search shipment, customer, route..."
           data-table-search="schedulesTable" style="width:100%;min-width:0;">
  </div>
  <select class="form-control" style="width:150px;flex-shrink:0;" id="statusFilter">
    <option value="">All Statuses</option>
    <option value="DELIVERED">DELIVERED</option>
    <option value="IN_TRANSIT">IN TRANSIT</option>
    <option value="SCHEDULED">SCHEDULED</option>
    <option value="PENDING">PENDING</option>
  </select>
  <input type="date" class="form-control" style="width:140px;flex-shrink:0;" id="dateFrom">
  <input type="date" class="form-control" style="width:140px;flex-shrink:0;" id="dateTo">
</div>

<div class="card mt-12">
  <div class="table-wrapper">
    <table id="schedulesTable">
      <thead>
        <tr>
          <th style="width:80px;">Shipment</th>
          <th>Customer</th>
          <th>Route</th>
          <th style="min-width:130px;">Planned Dep.</th>
          <th style="min-width:130px;">Actual Dep.</th>
          <th style="min-width:130px;">Est. Arrival</th>
          <th style="min-width:130px;">Actual Arr.</th>
          <th>Variance</th>
          <th>Status</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($schedules as $sch):
            $variance = $sch['variance'];
            if ($variance === null) {
                $var_html = '<span class="td-muted">—</span>';
            } elseif ($variance === 0) {
                $var_html = '<span class="badge badge-green">On Time</span>';
            } elseif ($variance < 0) {
                $var_html = '<span class="badge badge-green">- ' . abs($variance) . ' hr</span>';
            } else {
                $var_html = '<span class="badge badge-red">+ ' . $variance . ' hr</span>';
            }

            $status_key = strtoupper(str_replace(' ', '_', $sch['Status']));
            $route_label = $sch['RouteName'] ?? ($sch['StartLocation'] && $sch['EndLocation']
                ? htmlspecialchars($sch['StartLocation'] . ' → ' . $sch['EndLocation'])
                : '—');
        ?>
          <tr
            data-status="<?= $status_key ?>"
            data-dep="<?= $sch['PlannedDeparture'] ? date('Y-m-d', strtotime($sch['PlannedDeparture'])) : '' ?>">
            <td style="white-space:nowrap;"><strong>#<?= $sch['ShipmentID'] ?></strong>
              <?php if ($sch['OrderID']): ?>
                <br><small class="td-muted">ORD-<?= $sch['OrderID'] ?></small>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($sch['CustomerName'] ?? '—') ?></td>
            <td class="td-muted"><?= $route_label ?></td>

            <td class="td-muted" style="white-space:nowrap;">
              <?= $sch['PlannedDeparture'] ? date('Y-m-d H:i', strtotime($sch['PlannedDeparture'])) : '—' ?>
            </td>
            <td class="td-muted" style="white-space:nowrap;">
              <?php if ($sch['ActualDeparture']): ?>
                <?= date('Y-m-d H:i', strtotime($sch['ActualDeparture'])) ?>
              <?php else: ?>
                <span style="color:var(--c-slate-400)">—</span>
              <?php endif; ?>
            </td>
            <td class="td-muted" style="white-space:nowrap;">
              <?= $sch['EstimatedArrival'] ? date('Y-m-d H:i', strtotime($sch['EstimatedArrival'])) : '—' ?>
            </td>
            <td class="td-muted" style="white-space:nowrap;">
              <?php if ($sch['ActualArrival']): ?>
                <?= date('Y-m-d H:i', strtotime($sch['ActualArrival'])) ?>
              <?php else: ?>
                <span style="color:var(--c-slate-400)">—</span>
              <?php endif; ?>
            </td>
            <td><?= $var_html ?></td>
            <td><?= status_badge($status_key) ?></td>
            <td style="text-align:right;">
              <div class="action-menu">
                <button class="action-menu-btn btn btn-ghost btn-sm"
                        data-dropdown-toggle="actSch<?= $sch['ShipmentID'] ?>">⋯</button>
                <div class="action-dropdown" id="actSch<?= $sch['ShipmentID'] ?>">
                  <a href="../operations/shipment_detail.php?id=<?= $sch['ShipmentID'] ?>"
                     class="dropdown-item">👁 View Shipment</a>
                  <a href="#" class="dropdown-item"
                     onclick="openEditSchedule(
                       <?= $sch['ShipmentID'] ?>,
                       '<?= $sch['PlannedDeparture']  ? date('Y-m-d\TH:i', strtotime($sch['PlannedDeparture']))  : '' ?>',
                       '<?= $sch['EstimatedArrival']  ? date('Y-m-d\TH:i', strtotime($sch['EstimatedArrival']))  : '' ?>',
                       '<?= $sch['ActualDeparture']   ? date('Y-m-d\TH:i', strtotime($sch['ActualDeparture']))   : '' ?>',
                       '<?= $sch['ActualArrival']     ? date('Y-m-d\TH:i', strtotime($sch['ActualArrival']))     : '' ?>'
                     )">✏️ Edit Schedule</a>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Variance Chart -->
<div class="card" style="margin-top:24px;">
  <div class="card-header">
    <h3 class="card-title">📊 Arrival Variance Summary (hours)</h3>
  </div>
  <div class="card-body">
    <canvas id="varianceChart" style="height:240px;"></canvas>
  </div>
</div>

<!-- ═══ MODAL: Edit Schedule ══════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalEditSchedule">
  <div class="modal" style="max-width:520px;">
    <div class="modal-header">
      <h3 class="modal-title">✏️ Edit Schedule — Shipment #<span id="es_title_id"></span></h3>
      <button class="modal-close">×</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action"      value="update_schedule">
      <input type="hidden" name="shipment_id" id="es_shipment_id">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Planned Departure</label>
            <input type="datetime-local" name="planned_dep" id="es_planned_dep" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Estimated Arrival</label>
            <input type="datetime-local" name="planned_arr" id="es_planned_arr" class="form-control">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Actual Departure</label>
            <input type="datetime-local" name="actual_dep" id="es_actual_dep" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Actual Arrival</label>
            <input type="datetime-local" name="actual_arr" id="es_actual_arr" class="form-control">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">💾 Save Schedule</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ── Open Edit Modal ───────────────────────────────────────────────────────────
function openEditSchedule(id, planned_dep, planned_arr, actual_dep, actual_arr) {
  document.getElementById('es_shipment_id').value  = id;
  document.getElementById('es_title_id').textContent = id;
  document.getElementById('es_planned_dep').value  = planned_dep;
  document.getElementById('es_planned_arr').value  = planned_arr;
  document.getElementById('es_actual_dep').value   = actual_dep;
  document.getElementById('es_actual_arr').value   = actual_arr;
  document.getElementById('modalEditSchedule').classList.add('active');
}

// ── Client-side filter: Status + Date range ──────────────────────────────────
document.getElementById('statusFilter').addEventListener('change', applyFilters);
document.getElementById('dateFrom').addEventListener('change', applyFilters);
document.getElementById('dateTo').addEventListener('change', applyFilters);

function applyFilters() {
  const status   = document.getElementById('statusFilter').value.toUpperCase();
  const dateFrom = document.getElementById('dateFrom').value;
  const dateTo   = document.getElementById('dateTo').value;
  const rows     = document.querySelectorAll('#schedulesTable tbody tr');

  rows.forEach(row => {
    const rowStatus = row.dataset.status || '';
    const rowDep    = row.dataset.dep    || '';
    let show = true;

    if (status && !rowStatus.includes(status.replace('_', ' ')) && rowStatus !== status) show = false;
    if (dateFrom && rowDep && rowDep < dateFrom) show = false;
    if (dateTo   && rowDep && rowDep > dateTo)   show = false;

    row.style.display = show ? '' : 'none';
  });
}

// ── Variance Chart ────────────────────────────────────────────────────────────
const schLabels    = <?= json_encode(array_map(fn($s) => '#' . $s['ShipmentID'], $schedules)) ?>;
const schVariances = <?= json_encode(array_map(fn($s) => $s['variance'] ?? 0, $schedules)) ?>;
const varColors    = schVariances.map(v => v > 0 ? '#C0392B' : (v < 0 ? '#6B8C3E' : '#E8B84B'));

new Chart(document.getElementById('varianceChart'), {
  type: 'line',
  data: {
    labels: schLabels,
    datasets: [{
      label: 'Variance (hours)',
      data: schVariances,
      borderColor: '#E8B84B',
      backgroundColor: 'rgba(232,184,75,0.12)',
      pointBackgroundColor: schVariances.map(v => v > 0 ? '#C0392B' : (v < 0 ? '#6B8C3E' : '#E8B84B')),
      pointBorderColor:     schVariances.map(v => v > 0 ? '#C0392B' : (v < 0 ? '#6B8C3E' : '#E8B84B')),
      pointRadius: 5,
      pointHoverRadius: 7,
      borderWidth: 2,
      tension: 0.3,
      fill: true
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: ctx => (ctx.parsed.y > 0 ? '+ ' : ctx.parsed.y < 0 ? '- ' : '') + Math.abs(ctx.parsed.y) + ' hr' } }
    },
    scales: {
      x: { grid: { color: 'rgba(0,0,0,0.05)' } },
      y: {
        title: { display: true, text: 'Variance (hours)' },
        grid: { color: 'rgba(0,0,0,0.05)' },
        ticks: { callback: v => (v > 0 ? '+' : '') + v + 'h' }
      }
    }
  }
});
</script>

<?php close_page(); $db->close(); ?>