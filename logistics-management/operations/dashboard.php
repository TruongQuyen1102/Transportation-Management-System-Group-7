<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/db.php'; // Replaced sample_data with real DB connection
auth_require('operations');

$db = get_db();
$current_user_account_id = $_SESSION['AccountID'] ?? 4; // Get current user ID (Ops)

// ── UTILITY FUNCTIONS ──────────────────────────────────────────────────────────
function db_rows($db, $sql) {
    $rows = [];
    $res = $db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) { $rows[] = $row; }
    }
    return $rows;
}

function ops_badge($status) {
    $s = strtolower(trim($status));
    $color = match($s) {
        'delivered', 'approved', 'shipped' => 'green',
        'in transit', 'processing' => 'olive',
        'scheduled', 'pending' => 'yellow',
        'cancelled', 'rejected', 'critical', 'high' => 'red',
        default => 'gray'
    };
    return '<span class="badge badge-'.$color.'">'.htmlspecialchars($status).'</span>';
}

// ── FORM SUBMIT HANDLING (CRUD - SAVE TO DATABASE) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'new_order') {
        $customer_id = (int)$_POST['customer_id'];
        $pickup = $db->real_escape_string($_POST['pickup_address']);
        $expected_date = $db->real_escape_string($_POST['expected_delivery']);
        $weight = (float)$_POST['weight'];
        
        $sql = "INSERT INTO order_info (AccountID, CustomerID, PickupAddress, OrderDate, ExpectedDeliveryDate, ShippingStatus) 
                VALUES ($current_user_account_id, $customer_id, '$pickup', NOW(), '$expected_date', 'Pending')";
        $db->query($sql);
        // Note: In a full implementation, you'd also INSERT into order_detailed for the weight.
    } 
    elseif ($action === 'new_shipment') {
        $order_id = (int)$_POST['order_id'];
        $route_id = (int)$_POST['route_id'];
        $asset_id = (int)$_POST['asset_id'];
        $planned_dep = $db->real_escape_string($_POST['planned_dep']);
        
        // Create new Shipment
        $sql = "INSERT INTO shipment (RouteID, AssetID, Status, PlannedDeparture) 
                VALUES ($route_id, $asset_id, 'Pending', '$planned_dep')";
        if ($db->query($sql)) {
            $new_shipment_id = $db->insert_id;
            // Link Shipment with Order
            $db->query("INSERT INTO shipment_order (ShipmentID, OrderID, LegSequence) VALUES ($new_shipment_id, $order_id, 1)");
            // Update Order status
            $db->query("UPDATE order_info SET ShippingStatus = 'Processing' WHERE OrderID = $order_id");
        }
    } 
    elseif ($action === 'report_exception') {
        $shipment_id = (int)$_POST['shipment_id'];
        $issue_type = $db->real_escape_string($_POST['issue_type']);
        $desc = $db->real_escape_string($_POST['description']);
        
        $sql = "INSERT INTO operational_exception (ShipmentID, AccountID, IssueType, Description, SeverityLevel, ApprovalStatus, CreatedAt) 
                VALUES ($shipment_id, $current_user_account_id, '$issue_type', '$desc', 'Medium', 'Pending', NOW())";
        $db->query($sql);
    }
    
    // Refresh page to prevent resubmission on F5
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ── QUERY DATA FOR DISPLAY (READ) ──────────────────────────────────────

// 1. Data for Stats Grid
$active_shipments_res = $db->query("SELECT COUNT(*) FROM shipment WHERE Status = 'In Transit'");
$active_shipments_count = $active_shipments_res ? $active_shipments_res->fetch_row()[0] : 0;

$orders_today_res = $db->query("SELECT COUNT(*) FROM order_info WHERE DATE(OrderDate) = CURDATE()");
$orders_today_count = $orders_today_res ? $orders_today_res->fetch_row()[0] : 0;

$open_exceptions_res = $db->query("SELECT COUNT(*) FROM operational_exception WHERE ApprovalStatus IN ('Pending', 'Under Investigation')");
$open_exceptions_count = $open_exceptions_res ? $open_exceptions_res->fetch_row()[0] : 0;

// Available assets = Total assets - Assets currently In Transit
$total_assets = $db->query("SELECT COUNT(*) FROM transport_asset")->fetch_row()[0];
$used_assets = $db->query("SELECT COUNT(DISTINCT AssetID) FROM shipment WHERE Status = 'In Transit' AND AssetID IS NOT NULL")->fetch_row()[0];
$avail_assets_count = $total_assets - $used_assets;

// 2. Data for Donut Chart
$status_counts = ['DELIVERED'=>0, 'IN_TRANSIT'=>0, 'PENDING'=>0, 'CANCELLED'=>0];
$total_shipments = 0;
$shipment_status_res = $db->query("SELECT Status, COUNT(*) as cnt FROM shipment GROUP BY Status");
if ($shipment_status_res) {
    while ($r = $shipment_status_res->fetch_assoc()) {
        $key = strtoupper(str_replace(' ', '_', $r['Status']));
        if (isset($status_counts[$key])) { $status_counts[$key] = $r['cnt']; }
        $total_shipments += $r['cnt'];
    }
}

// 3. Notification list
$notifications = db_rows($db, "SELECT * FROM system_notification WHERE AccountID = $current_user_account_id OR AccountID IS NULL ORDER BY CreatedAt DESC LIMIT 5");

// 4. Active Shipments list
$active_shipments_list = db_rows($db, "
    SELECT s.ShipmentID, s.Status, s.PlannedDeparture, s.EstimatedArrival, r.RouteName, ta.VehicleModel, so.OrderID 
    FROM shipment s 
    LEFT JOIN route r ON s.RouteID = r.RouteID 
    LEFT JOIN transport_asset ta ON s.AssetID = ta.AssetID 
    LEFT JOIN shipment_order so ON s.ShipmentID = so.ShipmentID 
    WHERE s.Status = 'In Transit' 
    ORDER BY s.PlannedDeparture DESC LIMIT 5
");

// 5. Recent Orders list
$recent_orders = db_rows($db, "
    SELECT oi.OrderID, bp.PartyName AS CustomerName, oi.PickupAddress, oi.ExpectedDeliveryDate, oi.ShippingStatus, COALESCE(SUM(od.Weight), 0) AS TotalWeight
    FROM order_info oi
    LEFT JOIN business_party bp ON oi.CustomerID = bp.PartyID
    LEFT JOIN order_detailed od ON oi.OrderID = od.OrderID
    GROUP BY oi.OrderID
    ORDER BY oi.OrderDate DESC LIMIT 5
");

// 6. Data for Dropdown Modals
$customers_list = db_rows($db, "SELECT PartyID, PartyName FROM business_party WHERE PartyType = 'Customer'");
$routes_list = db_rows($db, "SELECT RouteID, RouteName FROM route");
$assets_list = db_rows($db, "SELECT AssetID, VehicleModel FROM transport_asset");
$pending_orders = db_rows($db, "SELECT OrderID, CustomerID FROM order_info WHERE ShippingStatus IN ('Pending', 'Processing')");
$all_shipments = db_rows($db, "SELECT s.ShipmentID, r.RouteName FROM shipment s LEFT JOIN route r ON s.RouteID = r.RouteID ORDER BY s.ShipmentID DESC LIMIT 20");

open_page('Operations Dashboard', 'dashboard', [['label'=>'Operations'],['label'=>'Dashboard']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Operations Dashboard</h1>
    <p class="text-muted" style="margin-top:4px;">Welcome back, <?= htmlspecialchars($_SESSION['Username'] ?? 'Ops User') ?> — <?= date('l, d F Y') ?></p>
  </div>
  <div class="page-actions">
    <button class="btn btn-outline btn-sm" data-modal-open="modalNewOrder">📋 New Order</button>
    <button class="btn btn-accent btn-sm" data-modal-open="modalNewShipment">📦 New Shipment</button>
    <button class="btn btn-danger btn-sm" data-modal-open="modalReportException">⚠️ Report Exception</button>
  </div>
</div>

<div class="stats-grid" style="display:grid; grid-template-columns:repeat(4,1fr); gap:20px;">
  <div class="stat-card navy">
    <div class="stat-icon">📦</div>
    <div class="stat-body">
      <div class="stat-value"><?= $active_shipments_count ?></div>
      <div class="stat-label">Active Shipments</div>
      <div class="stat-trend neutral">In Transit right now</div>
    </div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon">📋</div>
    <div class="stat-body">
      <div class="stat-value"><?= $orders_today_count ?></div>
      <div class="stat-label">Orders Today</div>
      <div class="stat-trend neutral">Added today</div>
    </div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon">⚠️</div>
    <div class="stat-body">
      <div class="stat-value"><?= $open_exceptions_count ?></div>
      <div class="stat-label">Open Exceptions</div>
      <div class="stat-trend neutral">Requiring action</div>
    </div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">🚛</div>
    <div class="stat-body">
      <div class="stat-value"><?= $avail_assets_count ?></div>
      <div class="stat-label">Assets Available</div>
      <div class="stat-trend neutral">Ready to assign</div>
    </div>
  </div>
</div>

<div class="grid-2" style="margin-top:24px;gap:20px;">

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
            <span style="display:flex;align-items:center;gap:8px;"><span style="width:12px;height:12px;border-radius:50%;background:#3A5361;display:inline-block;"></span> Pending/Sched</span>
            <span class="font-bold"><?= $status_counts['PENDING'] ?></span>
          </div>
          <div class="flex flex-between">
            <span style="display:flex;align-items:center;gap:8px;"><span style="width:12px;height:12px;border-radius:50%;background:#aaa;display:inline-block;"></span> Cancelled</span>
            <span class="font-bold"><?= $status_counts['CANCELLED'] ?></span>
          </div>
          <div style="border-top:1px solid var(--border);padding-top:10px;" class="flex flex-between">
            <span class="font-bold">Total Shipments</span>
            <span class="font-bold"><?= $total_shipments ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

 <div class="card">
    <div class="card-header">
      <h3 class="card-title">🔔 Notifications</h3>
      <a href="../operations/notifications.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div class="card-body p-0">
      <?php if (empty($notifications)): ?>
        <p class="text-muted text-center py-24 font-sm" style="padding: 24px;">No notifications.</p>
      <?php else: ?>
        <div class="activity-feed">
          <?php foreach ($notifications as $n): ?>
            <div class="activity-item" style="padding: 12px 16px; <?= $n['IsRead'] == '0' ? 'border-left: 3px solid var(--c-yellow); padding-left: 13px; background: rgba(232, 184, 75, 0.03);' : '' ?>">
              <div class="activity-icon" style="font-size: 15px; margin-top: 1px;">🔔</div>
              <div class="activity-body" style="flex: 1; margin-left: 10px;">
                <div class="flex flex-between" style="align-items: center;">
                  <strong style="font-size: 13px; color: var(--c-navy-900);"><?= htmlspecialchars($n['Title']) ?></strong>
                  <?php if ($n['IsRead'] == '0'): ?>
                    <span class="badge badge-yellow" style="font-size: 10px; padding: 2px 8px; border-radius: 12px; letter-spacing: 0;">● Unread</span>
                  <?php endif; ?>
                </div>
                <div class="text-muted" style="font-size:12px; margin-top:3px; line-height: 1.4;"><?= htmlspecialchars($n['Message']) ?></div>
                <div class="text-muted" style="font-size:11px; margin-top:5px; display: flex; align-items: center; gap: 4px;">
                  🕒 <?= date('M d, H:i', strtotime($n['CreatedAt'])) ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<div class="card mt-16">
  <div class="card-header">
    <h3 class="card-title">🚛 Active Shipments (In Transit)</h3>
    <a href="../operations/shipments.php" class="btn btn-outline btn-sm">View All Shipments</a>
  </div>
  <div class="card-body">
    <?php if (empty($active_shipments_list)): ?>
      <div class="alert alert-info" style="margin:16px;">No shipments currently in transit.</div>
    <?php else: ?>
      <div class="timeline" style="padding:16px 24px;">
        <?php foreach ($active_shipments_list as $shp): ?>
          <div class="timeline-item">
            <div class="flex flex-between" style="margin-bottom:4px;">
              <div style="display:flex;align-items:center;gap:10px;">
                <strong>SHP<?= str_pad($shp['ShipmentID'], 4, '0', STR_PAD_LEFT) ?></strong>
                <?= ops_badge($shp['Status']) ?>
                <span class="text-muted">→ ORD<?= str_pad($shp['OrderID'] ?? 0, 4, '0', STR_PAD_LEFT) ?></span>
              </div>
              <a href="../operations/shipment_detail.php?id=<?= $shp['ShipmentID'] ?>" class="btn btn-ghost btn-sm">Track →</a>
            </div>
            <div style="font-size:13px;color:var(--text-muted);">
              🗺️ <?= htmlspecialchars($shp['RouteName'] ?? 'N/A') ?> &nbsp;|&nbsp;
              🚛 <?= htmlspecialchars($shp['VehicleModel'] ?? 'N/A') ?> &nbsp;|&nbsp;
              📅 Departed: <?= date('Y-m-d H:i', strtotime($shp['PlannedDeparture'])) ?> &nbsp;|&nbsp;
              🏁 ETA: <?= date('Y-m-d', strtotime($shp['EstimatedArrival'])) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card mt-16">
  <div class="card-header">
    <h3 class="card-title">📋 Recent Orders</h3>
    <a href="../operations/shipments.php" class="btn btn-outline btn-sm">View All</a>
  </div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Order ID</th>
          <th>Customer</th>
          <th>Pickup Location</th>
          <th>Expected Date</th>
          <th>Weight</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent_orders as $ord): ?>
          <tr>
            <td><strong>ORD<?= str_pad($ord['OrderID'], 4, '0', STR_PAD_LEFT) ?></strong></td>
            <td><?= htmlspecialchars($ord['CustomerName'] ?? 'Unknown') ?></td>
            <td class="td-muted truncate" style="max-width:200px;" title="<?= htmlspecialchars($ord['PickupAddress']) ?>"><?= htmlspecialchars($ord['PickupAddress']) ?></td>
            <td class="td-muted"><?= date('Y-m-d', strtotime($ord['ExpectedDeliveryDate'])) ?></td>
            <td><?= number_format($ord['TotalWeight'], 2) ?> kg</td>
            <td><?= ops_badge($ord['ShippingStatus']) ?></td>
            <td>
              <div class="action-menu">
                <button class="action-menu-btn btn btn-ghost btn-sm" data-dropdown-toggle="actOrd<?= $ord['OrderID'] ?>">⋯</button>
                <div class="action-dropdown" id="actOrd<?= $ord['OrderID'] ?>">
                  <a href="#" class="dropdown-item">👁 View</a>
                  <a href="#" class="dropdown-item" data-modal-open="modalNewShipment">📦 Create Shipment</a>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modalNewShipment">
  <div class="modal" style="max-width:520px;">
    <div class="modal-header">
      <h3 class="modal-title">📦 Create New Shipment</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="modal-body">
      <form id="formCreateShipment" method="POST" action="">
        <input type="hidden" name="action" value="new_shipment">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Order ID (Pending/Processing)</label>
            <select name="order_id" class="form-control" required>
              <option value="">-- Select Order --</option>
              <?php foreach ($pending_orders as $o): ?>
                <option value="<?= $o['OrderID'] ?>">ORD<?= str_pad($o['OrderID'], 4, '0', STR_PAD_LEFT) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Route</label>
            <select name="route_id" class="form-control" required>
              <option value="">-- Select Route --</option>
              <?php foreach ($routes_list as $r): ?>
                <option value="<?= $r['RouteID'] ?>"><?= htmlspecialchars($r['RouteName']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Transport Asset</label>
            <select name="asset_id" class="form-control" required>
              <option value="">-- Select Asset --</option>
              <?php foreach ($assets_list as $a): ?>
                <option value="<?= $a['AssetID'] ?>"><?= htmlspecialchars($a['VehicleModel']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Planned Departure</label>
            <input type="datetime-local" name="planned_dep" class="form-control" required>
          </div>
        </div>
      </form>
    </div>
    <div class="modal-footer" style="display:flex;justify-content:flex-end;align-items:center;gap:8px;padding:16px 20px;border-top:1px solid var(--border-color,#e5e7eb);">
      <button type="button" class="btn btn-outline modal-close">Cancel</button>
      <button type="submit" form="formCreateShipment" class="btn btn-primary">Create Shipment</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalNewOrder">
  <div class="modal" style="max-width:520px;">
    <div class="modal-header">
      <h3 class="modal-title">📋 Create New Order</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="modal-body">
      <form id="formNewOrder" method="POST" action="">
        <input type="hidden" name="action" value="new_order">
        <div class="form-group">
          <label class="form-label">Customer</label>
          <select name="customer_id" class="form-control" required>
            <option value="">-- Select Customer --</option>
            <?php foreach ($customers_list as $c): ?>
              <option value="<?= $c['PartyID'] ?>"><?= htmlspecialchars($c['PartyName']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Pickup Address</label>
          <input type="text" name="pickup_address" class="form-control" placeholder="Pickup location" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Approx. Total Weight (kg)</label>
            <input type="number" step="0.01" name="weight" class="form-control" placeholder="0.00" required>
          </div>
          <div class="form-group">
            <label class="form-label">Expected Delivery Date</label>
            <input type="date" name="expected_delivery" class="form-control" required>
          </div>
        </div>
      </form>
    </div>
    <div class="modal-footer" style="display:flex;justify-content:flex-end;align-items:center;gap:8px;padding:16px 20px;border-top:1px solid var(--border-color,#e5e7eb);">
      <button type="button" class="btn btn-outline modal-close">Cancel</button>
      <button type="submit" form="formNewOrder" class="btn btn-primary">Create Order</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalReportException">
  <div class="modal" style="max-width:500px;">
    <div class="modal-header">
      <h3 class="modal-title">⚠️ Report Exception</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="modal-body">
      <form id="formReportException" method="POST" action="">
        <input type="hidden" name="action" value="report_exception">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Shipment</label>
            <select name="shipment_id" class="form-control" required>
              <option value="">-- Select Shipment --</option>
              <?php foreach ($all_shipments as $s): ?>
                <option value="<?= $s['ShipmentID'] ?>">SHP<?= str_pad($s['ShipmentID'], 4, '0', STR_PAD_LEFT) ?> - <?= htmlspecialchars($s['RouteName']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Issue Type</label>
            <select name="issue_type" class="form-control" required>
              <option value="Traffic Delay">Traffic Delay</option>
              <option value="Damaged Goods">Damaged Goods</option>
              <option value="Route Deviation">Route Deviation</option>
              <option value="Vehicle Breakdown">Vehicle Breakdown</option>
              <option value="Failed Delivery">Failed Delivery</option>
              <option value="Weather Event">Weather Event</option>
              <option value="Customs Hold">Customs Hold</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="3" placeholder="Describe the issue in detail..." required></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer" style="display:flex;justify-content:flex-end;align-items:center;gap:8px;padding:16px 20px;border-top:1px solid var(--border-color,#e5e7eb);">
      <button type="button" class="btn btn-outline modal-close">Cancel</button>
      <button type="submit" form="formReportException" class="btn btn-danger">Report Exception</button>
    </div>
  </div>
</div>

<script>
window.addEventListener('load', function() {
    new Chart(document.getElementById('shipmentDonut'), {
      type: 'doughnut',
      data: {
        labels: ['Delivered', 'In Transit', 'Pending', 'Cancelled'],
        datasets: [{
          data: [<?= $status_counts['DELIVERED'] ?>, <?= $status_counts['IN_TRANSIT'] ?>, <?= $status_counts['PENDING'] ?>, <?= $status_counts['CANCELLED'] ?>],
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
});
</script>

<?php 
close_page(); 
$db->close();
?>