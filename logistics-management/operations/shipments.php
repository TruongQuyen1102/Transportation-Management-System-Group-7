<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/db.php';
auth_require('operations');

$db = get_db();
$message = '';
$message_type = '';

// Get the AccountID of the logged-in user (assuming it's stored in the session)
$current_account_id = $_SESSION['account_id'] ?? 1; 

// ══════════════════════════════════════════════════════════════════════════════
//  POST HANDLING — Data creation and modification operations
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── 1. CREATE NEW SHIPMENT ────────────────────────────────────────────────
    if ($action === 'create_shipment') {
        $order_id    = (int)($_POST['order_id'] ?? 0);
        $route_id    = (int)($_POST['route_id'] ?? 0);
        $asset_id    = (int)($_POST['asset_id'] ?? 0);
        $planned_dep = $_POST['planned_dep'] ?? null;
        $est_arr     = $_POST['est_arr'] ?? null;
        $deadline    = $_POST['deadline'] ?? null;

        if (!$order_id || !$route_id || !$planned_dep) {
            $message = 'Please fill in the Order, Route, and Planned Departure fields.';
            $message_type = 'danger';
        } else {
            // Insert into the shipment table
            $status = 'Scheduled';
            $stmt = $db->prepare("INSERT INTO shipment (RouteID, AssetID, Status, PlannedDeparture, EstimatedArrival, DeliveryDeadline) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iissss', $route_id, $asset_id, $status, $planned_dep, $est_arr, $deadline);
            
            if ($stmt->execute()) {
                $shipment_id = $db->insert_id;
                // Link with the Order
                $stmt_link = $db->prepare("INSERT INTO shipment_order (ShipmentID, OrderID, LegSequence) VALUES (?, ?, 1)");
                $stmt_link->bind_param('ii', $shipment_id, $order_id);
                $stmt_link->execute();

                // Update order status
                $db->query("UPDATE order_info SET ShippingStatus = 'Processing' WHERE OrderID = $order_id");

                $message = "Shipment #$shipment_id created successfully!";
                $message_type = 'success';
            } else {
                $message = 'Error creating shipment: ' . $db->error;
                $message_type = 'danger';
            }
        }
    }

    // ── 2. UPDATE STATUS ──────────────────────────────────────────────────────
    elseif ($action === 'update_status') {
        $shipment_id = (int)($_POST['shipment_id'] ?? 0);
        $new_status  = $_POST['new_status'] ?? '';

        if ($shipment_id && $new_status) {
            $stmt = $db->prepare("UPDATE shipment SET Status = ? WHERE ShipmentID = ?");
            $stmt->bind_param('si', $new_status, $shipment_id);
            $stmt->execute();

            $message = "Status of Shipment #$shipment_id updated to $new_status!";
            $message_type = 'success';
        }
    }

    // ── 3. LOG EXCEPTION ──────────────────────────────────────────────────────
    elseif ($action === 'log_exception') {
        $shipment_id = (int)($_POST['shipment_id'] ?? 0);
        $issue_type  = $_POST['issue_type'] ?? '';
        $description = trim($_POST['description'] ?? '');

        if ($shipment_id && $issue_type) {
            // Get CarrierID via the Shipment's Asset
            $q = $db->query("SELECT a.CarrierID FROM shipment s JOIN transport_asset a ON s.AssetID = a.AssetID WHERE s.ShipmentID = $shipment_id");
            $carrier_id = ($q && $q->num_rows > 0) ? $q->fetch_assoc()['CarrierID'] : null;

            $severity = 'Medium';
            $approval = 'Pending';
            
            $stmt = $db->prepare("INSERT INTO operational_exception (CarrierID, ShipmentID, AccountID, IssueType, Description, SeverityLevel, ApprovalStatus) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iiissss', $carrier_id, $shipment_id, $current_account_id, $issue_type, $description, $severity, $approval);
            $stmt->execute();

            $message = "Exception reported for Shipment #$shipment_id!";
            $message_type = 'success';
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  FETCH DATA FROM DB FOR UI
// ══════════════════════════════════════════════════════════════════════════════

// 1. Shipments List
$sql_shipments = "
    SELECT s.*, r.RouteName, a.AssetCategory, a.VehicleModel, bp.PartyName AS CarrierName,
           (SELECT OrderID FROM shipment_order so WHERE so.ShipmentID = s.ShipmentID LIMIT 1) AS OrderID
    FROM shipment s
    LEFT JOIN route r ON s.RouteID = r.RouteID
    LEFT JOIN transport_asset a ON s.AssetID = a.AssetID
    LEFT JOIN business_party bp ON a.CarrierID = bp.PartyID
    ORDER BY s.ShipmentID DESC
";
$shipments_result = $db->query($sql_shipments);
$shipments = [];
while ($row = $shipments_result->fetch_assoc()) {
    $shipments[] = $row;
}

// 2. Routes List
$routes = [];
$res_routes = $db->query("SELECT RouteID, RouteName FROM route ORDER BY RouteName");
while ($r = $res_routes->fetch_assoc()) { $routes[] = $r; }

// 3. Carriers List
$carriers = [];
$res_carriers = $db->query("SELECT PartyID, PartyName FROM business_party WHERE PartyType = 'Carrier'");
while ($c = $res_carriers->fetch_assoc()) { $carriers[] = $c; }

// 4. Available Assets List (Simulated condition)
$avail_assets = [];
$res_assets = $db->query("SELECT a.AssetID, a.AssetCategory, a.VehicleModel, c.PartyName FROM transport_asset a LEFT JOIN business_party c ON a.CarrierID = c.PartyID");
while ($a = $res_assets->fetch_assoc()) { $avail_assets[] = $a; }

// 5. Orders without Shipment
$orders_unassigned = [];
$res_orders = $db->query("
    SELECT o.OrderID, c.PartyName AS CustomerName 
    FROM order_info o 
    JOIN business_party c ON o.CustomerID = c.PartyID 
    WHERE o.ShippingStatus IN ('Pending', 'Processing')
");
while ($o = $res_orders->fetch_assoc()) { $orders_unassigned[] = $o; }

// Calculate Stats
$total     = count($shipments);
$in_transit= count(array_filter($shipments, fn($s) => strtolower($s['Status']) === 'in transit'));
$delivered = count(array_filter($shipments, fn($s) => strtolower($s['Status']) === 'delivered'));
$scheduled = count(array_filter($shipments, fn($s) => strtolower($s['Status']) === 'scheduled'));
$pending   = count(array_filter($shipments, fn($s) => strtolower($s['Status']) === 'pending'));

open_page('Shipment Orders', 'shipments', [['label'=>'Operations'],['label'=>'Shipment Orders']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Shipment Orders</h1>
    <p class="text-muted" style="margin-top:4px;"><?= $total ?> total shipments in system</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-primary" data-modal-open="modalCreateShipment">+ Create Shipment</button>
  </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $message_type ?>" style="margin-bottom:16px;">
  <?= $message_type === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);">
  <div class="stat-card navy">
    <div class="stat-icon">📦</div>
    <div class="stat-info">
      <div class="stat-value"><?= $total ?></div>
      <div class="stat-label">Total Shipments</div>
    </div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon">🗓️</div>
    <div class="stat-info">
      <div class="stat-value"><?= $scheduled ?></div>
      <div class="stat-label">Scheduled</div>
    </div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon">⏳</div>
    <div class="stat-info">
      <div class="stat-value"><?= $pending ?></div>
      <div class="stat-label">Pending</div>
    </div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">🚛</div>
    <div class="stat-info">
      <div class="stat-value"><?= $in_transit ?></div>
      <div class="stat-label">In Transit</div>
    </div>
  </div>
  <div class="stat-card navy">
    <div class="stat-icon">✅</div>
    <div class="stat-info">
      <div class="stat-value"><?= $delivered ?></div>
      <div class="stat-label">Delivered</div>
    </div>
  </div>
</div>

<style>
  .stat-card { display: flex; flex-direction: row; align-items: center; gap: 16px; }
  .stat-info { display: flex; flex-direction: column; }

  #shipmentsTable td {
    white-space: normal !important;
    word-break: break-word !important;
  }
  .wrap-text-column {
    max-width: 220px;
    min-width: 150px;
  }
  .datetime-column {
    min-width: 100px;
  }
</style>

<div class="card mt-16">
  <div class="table-wrapper">
    <table id="shipmentsTable">
      <thead>
        <tr>
          <th>SHP ID</th>
          <th>Order ID</th>
          <th>Route</th>
          <th>Asset / Carrier</th>
          <th>Planned Dep.</th>
          <th>Est. Arrival</th>
          <th>Status</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($shipments as $s): 
          $shp_formatted_id = 'SHP' . str_pad($s['ShipmentID'], 3, '0', STR_PAD_LEFT);
        ?>
          <tr>
            <td><strong><?= $shp_formatted_id ?></strong></td>
            <td><a href="#" style="color:var(--c-yellow);">ORD-<?= $s['OrderID'] ?></a></td>
            
            <td class="td-muted wrap-text-column"><?= htmlspecialchars($s['RouteName']) ?></td>
            
            <td class="wrap-text-column">
              <div><?= htmlspecialchars($s['VehicleModel'] ?? 'N/A') ?></div>
              <div class="td-muted" style="font-size:11px;"><?= htmlspecialchars($s['CarrierName'] ?? 'Internal') ?></div>
            </td>
            
            <td class="td-muted datetime-column">
                <?php if($s['PlannedDeparture']): ?>
                    <?= date('Y-m-d', strtotime($s['PlannedDeparture'])) ?><br>
                    <small style="color:var(--c-slate-500)"><?= date('H:i', strtotime($s['PlannedDeparture'])) ?></small>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
            
            <td class="td-muted datetime-column">
                <?php if($s['EstimatedArrival']): ?>
                    <?= date('Y-m-d', strtotime($s['EstimatedArrival'])) ?><br>
                    <small style="color:var(--c-slate-500)"><?= date('H:i', strtotime($s['EstimatedArrival'])) ?></small>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
            
            <td><?= status_badge(strtoupper(str_replace(' ', '_', $s['Status']))) ?></td>
            <td style="text-align:right;">
              <div class="action-menu">
                <button class="action-menu-btn btn btn-ghost btn-sm" data-dropdown-toggle="actShp<?= $s['ShipmentID'] ?>">⋯</button>
                <div class="action-dropdown" id="actShp<?= $s['ShipmentID'] ?>">
                  <a href="../operations/shipment_detail.php?id=<?= $s['ShipmentID'] ?>" class="dropdown-item">👁 View Details</a>
                  <a href="#" class="dropdown-item" onclick="openUpdateStatus(<?= $s['ShipmentID'] ?>, '<?= htmlspecialchars($s['Status']) ?>')">🔄 Update Status</a>
                  <a href="#" class="dropdown-item" onclick="openLogException(<?= $s['ShipmentID'] ?>)">⚠️ Log Exception</a>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="modalCreateShipment">
  <div class="modal" style="max-width:580px;">
    <div class="modal-header">
      <h3 class="modal-title">📦 Create New Shipment</h3>
      <button class="modal-close">×</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="create_shipment">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Order <span style="color:red">*</span></label>
            <select class="form-control" name="order_id" required>
              <option value="">Select order...</option>
              <?php foreach ($orders_unassigned as $o): ?>
                <option value="<?= $o['OrderID'] ?>">ORD-<?= $o['OrderID'] ?> — <?= htmlspecialchars($o['CustomerName']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Route <span style="color:red">*</span></label>
            <select class="form-control" name="route_id" required>
              <option value="">Select route...</option>
              <?php foreach ($routes as $r): ?>
                <option value="<?= $r['RouteID'] ?>"><?= htmlspecialchars($r['RouteName']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Transport Asset <span style="color:red">*</span></label>
          <select class="form-control" name="asset_id" required>
            <option value="">Select asset...</option>
            <?php foreach ($avail_assets as $a): ?>
              <option value="<?= $a['AssetID'] ?>"><?= htmlspecialchars($a['VehicleModel']) ?> (<?= $a['PartyName'] ?? 'Internal' ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Planned Departure <span style="color:red">*</span></label>
            <input type="datetime-local" name="planned_dep" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Estimated Arrival</label>
            <input type="datetime-local" name="est_arr" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Deadline</label>
          <input type="datetime-local" name="deadline" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Create Shipment</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="modalUpdateStatus">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header">
      <h3 class="modal-title">🔄 Update Shipment Status</h3>
      <button class="modal-close">×</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="shipment_id" id="us_shipment_id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">New Status</label>
          <select class="form-control" name="new_status" id="us_status">
            <option value="Scheduled">Scheduled</option>
            <option value="Pending">Pending</option>
            <option value="In Transit">In Transit</option>
            <option value="Delivered">Delivered</option>
            <option value="Cancelled">Cancelled</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="modalLogException">
  <div class="modal" style="max-width:460px;">
    <div class="modal-header">
      <h3 class="modal-title">⚠️ Log Exception</h3>
      <button class="modal-close">×</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="log_exception">
      <input type="hidden" name="shipment_id" id="exc_shipment_id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Issue Type</label>
          <select class="form-control" name="issue_type">
            <option>Traffic Delay</option>
            <option>Damaged Goods</option>
            <option>Route Deviation</option>
            <option>Vehicle Breakdown</option>
            <option>Failed Delivery</option>
            <option>Weather Delay</option>
            <option>Port Congestion</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea class="form-control" name="description" rows="3" placeholder="Describe the exception..." required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-danger">Report Exception</button>
      </div>
    </form>
  </div>
</div>

<script>
function openUpdateStatus(id, currentStatus) {
    document.getElementById('us_shipment_id').value = id;

    let select = document.getElementById('us_status');
    for (let i = 0; i < select.options.length; i++) {
        if (select.options[i].value.toLowerCase() === currentStatus.toLowerCase()) {
            select.selectedIndex = i;
            break;
        }
    }
    document.getElementById('modalUpdateStatus').classList.add('active');
}

function openLogException(id) {
    document.getElementById('exc_shipment_id').value = id;
    document.getElementById('modalLogException').classList.add('active');
}
</script>

<?php close_page(); $db->close(); ?>