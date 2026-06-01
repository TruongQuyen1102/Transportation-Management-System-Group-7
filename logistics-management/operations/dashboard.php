<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('operations');

$shipments      = get_shipments();
$orders         = get_orders();
$exceptions     = get_exceptions();
$assets         = get_transport_assets();
$notifications  = array_values(get_notifications('ACC004'));

$active_shipments  = array_filter($shipments, fn($s) => $s['status'] === 'IN_TRANSIT');
$orders_today      = array_slice($orders, 0, 3);
$open_exceptions   = array_filter($exceptions, fn($e) => $e['status'] === 'OPEN');
$avail_assets      = array_filter($assets, fn($a) => $a['status'] === 'Available');
$unread_notifs     = array_filter($notifications, fn($n) => !$n['is_read']);

// Status counts for donut chart
$status_counts = ['IN_TRANSIT'=>0,'DELIVERED'=>0,'SCHEDULED'=>0,'CANCELLED'=>0];
foreach ($shipments as $s) {
    $key = $s['status'];
    if (isset($status_counts[$key])) $status_counts[$key]++;
}

open_page('Operations Dashboard', 'dashboard', [['label'=>'Operations'],['label'=>'Dashboard']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Operations Dashboard</h1>
    <p class="text-muted" style="margin-top:4px;">Welcome back, <?= htmlspecialchars(current_user()['name'] ?? 'Ops') ?> — <?= date('l, d F Y') ?></p>
  </div>
  <div class="page-actions">
    <button class="btn btn-outline btn-sm" data-modal-open="modalNewOrder">📋 New Order</button>
    <button class="btn btn-accent btn-sm" data-modal-open="modalNewShipment">📦 New Shipment</button>
    <button class="btn btn-danger btn-sm" data-modal-open="modalReportException">⚠️ Report Exception</button>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card navy">
    <div class="stat-icon">📦</div>
    <div class="stat-value"><?= count($active_shipments) ?></div>
    <div class="stat-label">Active Shipments</div>
    <div class="stat-trend">In Transit right now</div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon">📋</div>
    <div class="stat-value"><?= count($orders_today) ?></div>
    <div class="stat-label">Orders Today</div>
    <div class="stat-trend">Latest 3 orders</div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon">⚠️</div>
    <div class="stat-value"><?= count($open_exceptions) ?></div>
    <div class="stat-label">Open Exceptions</div>
    <div class="stat-trend">Requiring action</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">🚛</div>
    <div class="stat-value"><?= count($avail_assets) ?></div>
    <div class="stat-label">Assets Available</div>
    <div class="stat-trend">Ready to assign</div>
  </div>
</div>

<!-- Main Grid -->
<div class="grid-2" style="margin-top:24px;gap:20px;">

  <!-- Shipment Status Donut -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Shipment Status Overview</h3>
    </div>
    <div class="card-body" style="display:flex;align-items:center;gap:24px;">
      <div style="width:200px;height:200px;flex-shrink:0;">
        <canvas id="shipmentDonut"></canvas>
      </div>
      <div style="flex:1;">
        <div style="display:flex;flex-direction:column;gap:10px;">
          <div class="flex flex-between">
            <span style="display:flex;align-items:center;gap:8px;"><span style="width:12px;height:12px;border-radius:50%;background:#6B8C3E;display:inline-block;"></span> Delivered</span>
            <span class="font-bold"><?= $status_counts['DELIVERED'] ?></span>
          </div>
          <div class="flex flex-between">
            <span style="display:flex;align-items:center;gap:8px;"><span style="width:12px;height:12px;border-radius:50%;background:#E8B84B;display:inline-block;"></span> In Transit</span>
            <span class="font-bold"><?= $status_counts['IN_TRANSIT'] ?></span>
          </div>
          <div class="flex flex-between">
            <span style="display:flex;align-items:center;gap:8px;"><span style="width:12px;height:12px;border-radius:50%;background:#3A5361;display:inline-block;"></span> Scheduled</span>
            <span class="font-bold"><?= $status_counts['SCHEDULED'] ?></span>
          </div>
          <div class="flex flex-between">
            <span style="display:flex;align-items:center;gap:8px;"><span style="width:12px;height:12px;border-radius:50%;background:#aaa;display:inline-block;"></span> Cancelled</span>
            <span class="font-bold"><?= $status_counts['CANCELLED'] ?></span>
          </div>
          <div style="border-top:1px solid var(--border);padding-top:10px;" class="flex flex-between">
            <span class="font-bold">Total Shipments</span>
            <span class="font-bold"><?= count($shipments) ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Unread Notifications -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">🔔 Notifications</h3>
      <a href="/operations/notifications.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($notifications)): ?>
        <p class="text-muted" style="padding:20px;">No notifications.</p>
      <?php else: ?>
        <div class="activity-feed">
          <?php foreach (array_slice($notifications, 0, 4) as $n): ?>
            <div class="activity-item" style="<?= !$n['is_read'] ? 'background:rgba(232,184,75,0.06);border-left:3px solid var(--c-yellow);' : '' ?>">
              <div class="activity-icon"><?= $n['is_read'] ? '📭' : '🔔' ?></div>
              <div class="activity-body">
                <div class="flex flex-between">
                  <strong><?= htmlspecialchars($n['title']) ?></strong>
                  <?php if (!$n['is_read']): ?><span class="badge badge-yellow">Unread</span><?php endif; ?>
                </div>
                <div class="text-muted" style="font-size:12px;margin-top:2px;"><?= htmlspecialchars($n['msg']) ?></div>
                <div class="text-muted" style="font-size:11px;margin-top:4px;">🕐 <?= $n['created_at'] ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- Active Shipments Timeline -->
<div class="card mt-16">
  <div class="card-header">
    <h3 class="card-title">🚛 Active Shipments (In Transit)</h3>
    <a href="/operations/shipments.php" class="btn btn-outline btn-sm">View All Shipments</a>
  </div>
  <div class="card-body" style="padding:0;">
    <?php if (empty($active_shipments)): ?>
      <div class="alert alert-info" style="margin:16px;">No shipments currently in transit.</div>
    <?php else: ?>
      <div class="timeline" style="padding:16px 24px;">
        <?php foreach ($active_shipments as $shp): ?>
          <div class="timeline-item">
            <div class="flex flex-between" style="margin-bottom:4px;">
              <div style="display:flex;align-items:center;gap:10px;">
                <strong><?= htmlspecialchars($shp['id']) ?></strong>
                <?= status_badge($shp['status']) ?>
                <span class="text-muted">→ <?= htmlspecialchars($shp['order_id']) ?></span>
              </div>
              <a href="/operations/shipment_detail.php?id=<?= $shp['id'] ?>" class="btn btn-ghost btn-sm">Track →</a>
            </div>
            <div style="font-size:13px;color:var(--text-muted);">
              🗺️ <?= htmlspecialchars($shp['route']) ?> &nbsp;|&nbsp;
              🚛 <?= htmlspecialchars($shp['asset']) ?> &nbsp;|&nbsp;
              📅 Departed: <?= $shp['actual_dep'] ?? $shp['planned_dep'] ?> &nbsp;|&nbsp;
              🏁 ETA: <?= $shp['est_arr'] ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Recent Orders Table -->
<div class="card mt-16">
  <div class="card-header">
    <h3 class="card-title">📋 Recent Orders</h3>
    <a href="/operations/shipments.php" class="btn btn-outline btn-sm">View All</a>
  </div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Order ID</th>
          <th>Customer</th>
          <th>Pickup</th>
          <th>Delivery</th>
          <th>Expected</th>
          <th>Weight</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (array_slice($orders, 0, 5) as $ord): ?>
          <tr>
            <td><strong><?= $ord['id'] ?></strong></td>
            <td><?= htmlspecialchars($ord['customer']) ?></td>
            <td class="td-muted truncate" style="max-width:140px;"><?= htmlspecialchars($ord['pickup']) ?></td>
            <td class="td-muted truncate" style="max-width:140px;"><?= htmlspecialchars($ord['delivery']) ?></td>
            <td class="td-muted"><?= $ord['expected_delivery'] ?></td>
            <td><?= fmt_num($ord['weight']) ?> kg</td>
            <td><?= status_badge($ord['status']) ?></td>
            <td>
              <div class="action-menu">
                <button class="action-menu-btn btn btn-ghost btn-sm" data-dropdown-toggle="actOrd<?= $ord['id'] ?>">⋯</button>
                <div class="action-dropdown" id="actOrd<?= $ord['id'] ?>">
                  <a href="#" class="dropdown-item">👁 View</a>
                  <a href="/operations/shipments.php" class="dropdown-item">📦 Create Shipment</a>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: New Shipment -->
<div class="modal-overlay" id="modalNewShipment">
  <div class="modal" style="max-width:520px;">
    <div class="modal-header">
      <h3 class="modal-title">📦 Create New Shipment</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="modal-body">
      <form data-feedback="Shipment created successfully!">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Order ID</label>
            <select class="form-control">
              <?php foreach ($orders as $o): ?>
                <?php if (in_array($o['status'],['PENDING','CONFIRMED'])): ?>
                  <option><?= $o['id'] ?> — <?= htmlspecialchars($o['customer']) ?></option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Route</label>
            <select class="form-control">
              <?php foreach (get_routes() as $r): ?>
                <option><?= htmlspecialchars($r['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Transport Asset</label>
            <select class="form-control">
              <?php foreach ($avail_assets as $a): ?>
                <option><?= $a['id'] ?> — <?= htmlspecialchars($a['type']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Planned Departure</label>
            <input type="datetime-local" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea class="form-control" rows="2" placeholder="Any special instructions..."></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline modal-close">Cancel</button>
      <button class="btn btn-primary">Create Shipment</button>
    </div>
  </div>
</div>

<!-- Modal: New Order -->
<div class="modal-overlay" id="modalNewOrder">
  <div class="modal" style="max-width:520px;">
    <div class="modal-header">
      <h3 class="modal-title">📋 Create New Order</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="modal-body">
      <form data-feedback="Order created successfully!">
        <div class="form-group">
          <label class="form-label">Customer</label>
          <select class="form-control">
            <?php foreach (get_customers() as $c): ?>
              <option><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Pickup Address</label>
            <input type="text" class="form-control" placeholder="Pickup location">
          </div>
          <div class="form-group">
            <label class="form-label">Delivery Address</label>
            <input type="text" class="form-control" placeholder="Delivery destination">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Weight (kg)</label>
            <input type="number" class="form-control" placeholder="0">
          </div>
          <div class="form-group">
            <label class="form-label">Expected Delivery</label>
            <input type="date" class="form-control">
          </div>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline modal-close">Cancel</button>
      <button class="btn btn-primary">Create Order</button>
    </div>
  </div>
</div>

<!-- Modal: Report Exception -->
<div class="modal-overlay" id="modalReportException">
  <div class="modal" style="max-width:500px;">
    <div class="modal-header">
      <h3 class="modal-title">⚠️ Report Exception</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="modal-body">
      <form data-feedback="Exception reported!">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Shipment</label>
            <select class="form-control">
              <?php foreach ($shipments as $s): ?>
                <option><?= $s['id'] ?> — <?= $s['route'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Issue Type</label>
            <select class="form-control">
              <option>Traffic Delay</option>
              <option>Damaged Goods</option>
              <option>Route Deviation</option>
              <option>Vehicle Breakdown</option>
              <option>Failed Delivery</option>
              <option>Weather Event</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea class="form-control" rows="3" placeholder="Describe the issue in detail..."></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline modal-close">Cancel</button>
      <button class="btn btn-danger">Report Exception</button>
    </div>
  </div>
</div>

<script>
new Chart(document.getElementById('shipmentDonut'), {
  type: 'doughnut',
  data: {
    labels: ['Delivered', 'In Transit', 'Scheduled', 'Cancelled'],
    datasets: [{
      data: [<?= $status_counts['DELIVERED'] ?>, <?= $status_counts['IN_TRANSIT'] ?>, <?= $status_counts['SCHEDULED'] ?>, <?= $status_counts['CANCELLED'] ?>],
      backgroundColor: ['#6B8C3E','#E8B84B','#3A5361','#aaa'],
      borderWidth: 0,
      hoverOffset: 6
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: true,
    cutout: '65%',
    plugins: { legend: { display: false } }
  }
});
</script>

<?php close_page(); ?>
