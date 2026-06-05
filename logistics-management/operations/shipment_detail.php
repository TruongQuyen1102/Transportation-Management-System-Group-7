<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/db.php';
auth_require('operations');

$db = get_db();
$message = '';
$message_type = '';

$shipment_id = (int)($_GET['id'] ?? 1); // Default to ID 1 if not provided
$current_account_id = $_SESSION['account_id'] ?? 1; 

// ══════════════════════════════════════════════════════════════════════════════
//  POST HANDLING — Form submissions for Tracking / Status / Exceptions
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── UPDATE SHIPMENT STATUS
    if ($action === 'update_status') {
        $new_status = $_POST['new_status'] ?? '';
        $actual_arr = !empty($_POST['actual_arr']) ? $_POST['actual_arr'] : null;

        $stmt = $db->prepare("UPDATE shipment SET Status = ?, ActualArrival = ? WHERE ShipmentID = ?");
        $stmt->bind_param('ssi', $new_status, $actual_arr, $shipment_id);
        if ($stmt->execute()) {
            $message = "Status updated successfully!";
            $message_type = 'success';
        }
    }

    // ── LOG NEW TRACKING
    elseif ($action === 'log_tracking') {
        $location = $_POST['location'] ?? '';
        $weather  = $_POST['weather'] ?? 'Clear';
        $delay    = (int)($_POST['delay'] ?? 0);
        $note     = $_POST['note'] ?? '';
        $now      = date('Y-m-d H:i:s');

        // The tracking_log table uses TrafficDelayTime (int) and TrafficDelayTimeUnit (enum)
        $delay_unit = 'Minutes';
        
        $stmt = $db->prepare("INSERT INTO tracking_log (ShipmentID, AccountID, Timestamp, CheckpointLocation, WeatherCondition, TrafficDelayTime, TrafficDelayTimeUnit) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iisssis', $shipment_id, $current_account_id, $now, $location, $weather, $delay, $delay_unit);
        $stmt->execute();
        
        $message = "Tracking log added successfully!";
        $message_type = 'success';
    }

    // ── REPORT EXCEPTION
    elseif ($action === 'report_exception') {
        $issue_type  = $_POST['issue_type'] ?? '';
        $description = $_POST['description'] ?? '';

        $q = $db->query("SELECT a.CarrierID FROM shipment s JOIN transport_asset a ON s.AssetID = a.AssetID WHERE s.ShipmentID = $shipment_id");
        $carrier_id = ($q && $q->num_rows > 0) ? $q->fetch_assoc()['CarrierID'] : null;

        $stmt = $db->prepare("INSERT INTO operational_exception (CarrierID, ShipmentID, AccountID, IssueType, Description, SeverityLevel, ApprovalStatus) VALUES (?, ?, ?, ?, ?, 'High', 'Pending')");
        $stmt->bind_param('iiiss', $carrier_id, $shipment_id, $current_account_id, $issue_type, $description);
        $stmt->execute();

        $message = "Exception reported successfully!";
        $message_type = 'danger';
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  FETCH CURRENT SHIPMENT DATA FROM DB
// ══════════════════════════════════════════════════════════════════════════════

// 1. Shipment Data
$sql_shipment = "
    SELECT s.*, r.RouteName, a.VehicleModel as AssetModel, bp.PartyName as CarrierName
    FROM shipment s
    LEFT JOIN route r ON s.RouteID = r.RouteID
    LEFT JOIN transport_asset a ON s.AssetID = a.AssetID
    LEFT JOIN business_party bp ON a.CarrierID = bp.PartyID
    WHERE s.ShipmentID = $shipment_id
";
$shp = $db->query($sql_shipment)->fetch_assoc();

if (!$shp) {
    die("Shipment does not exist!");
}

$shp_formatted_id = 'SHP' . str_pad($shp['ShipmentID'], 3, '0', STR_PAD_LEFT);

// 2. Linked Order Data
$sql_order = "
    SELECT o.*, c.PartyName as CustomerName 
    FROM shipment_order so
    JOIN order_info o ON so.OrderID = o.OrderID
    JOIN business_party c ON o.CustomerID = c.PartyID
    WHERE so.ShipmentID = $shipment_id
    LIMIT 1
";
$order = $db->query($sql_order)->fetch_assoc();

// 3. Tracking Logs Data
$tracking_logs = [];
$res_logs = $db->query("
    SELECT t.*, a.Username 
    FROM tracking_log t
    LEFT JOIN account a ON t.AccountID = a.AccountID
    WHERE t.ShipmentID = $shipment_id
    ORDER BY t.Timestamp DESC
");
while ($l = $res_logs->fetch_assoc()) {
    $tracking_logs[] = $l;
}

// Calculate Lifecycle step
$steps = ['SCHEDULED', 'PENDING', 'IN_TRANSIT', 'DELIVERED'];
$step_labels = ['Scheduled', 'Pending', 'In Transit', 'Delivered'];
$current_status = strtoupper(str_replace(' ', '_', $shp['Status']));
$current_step_idx = match($current_status) {
    'SCHEDULED' => 0, 'PENDING' => 0,
    'PENDING'   => 1,
    'IN_TRANSIT'=> 2, 'IN TRANSIT' => 2,
    'DELIVERED' => 3,
    default     => 0
};

open_page('Shipment Detail — ' . $shp_formatted_id, 'shipments', [
    ['label'=>'Operations'],
    ['label'=>'Shipments','url'=>'/operations/shipments.php'],
    ['label'=>$shp_formatted_id]
]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Shipment <?= $shp_formatted_id ?></h1>
    <div style="display:flex;align-items:center;gap:10px;margin-top:6px;">
      <?= status_badge($current_status) ?>
      <?php if($order): ?>
        <span class="text-muted">Order ORD-<?= $order['OrderID'] ?></span>
        <span class="text-muted">|</span>
      <?php endif; ?>
      <span class="text-muted"><?= htmlspecialchars($shp['RouteName']) ?></span>
    </div>
  </div>
  <div class="page-actions">
    <button class="btn btn-outline btn-sm" data-modal-open="modalLogTracking">📍 Log Tracking</button>
    <button class="btn btn-outline btn-sm" data-modal-open="modalReportExc">⚠️ Report Exception</button>
    <button class="btn btn-primary btn-sm" data-modal-open="modalUpdateStatus">🔄 Update Status</button>
  </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $message_type ?> mt-16">
  <?= $message_type === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="card mt-16">
  <div class="card-header">
    <h3 class="card-title">Shipment Lifecycle</h3>
  </div>
  <div class="card-body">
    <style>
      .stepper {
        display: flex;
        align-items: flex-start;
        width: 100%;
        padding: 16px 8px;
      }
      .step {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
        position: relative;
      }
      /* Connector line: drawn from center of this dot to center of next dot */
      .step:not(:last-child)::after {
        content: '';
        position: absolute;
        top: 16px; /* half dot height */
        left: 50%;
        width: 100%;
        height: 2px;
        background-color: #e0e0e0;
        z-index: 0;
      }
      .step.active:not(:last-child)::after,
      .step.completed:not(:last-child)::after {
        background-color: #f5a623;
      }
      .step-dot {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background-color: #e0e0e0;
        color: #999;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        font-weight: 600;
        position: relative;
        z-index: 1;
        flex-shrink: 0;
      }
      .step.active .step-dot {
        background-color: #f5a623;
        color: #fff;
      }
      .step.completed .step-dot {
        background-color: #f5a623;
        color: #fff;
      }
      .step-label {
        margin-top: 8px;
        font-size: 13px;
        font-weight: 500;
        color: #999;
        text-align: center;
        white-space: nowrap;
      }
      .step.active .step-label,
      .step.completed .step-label {
        color: #333;
        font-weight: 700;
      }
      .step-sub {
        font-size: 11px;
        color: #777;
        margin-top: 2px;
        text-align: center;
        white-space: nowrap;
      }
    </style>
    <div class="stepper">
      <?php foreach ($steps as $i => $step): ?>
        <div class="step <?= $i <= $current_step_idx ? 'active' : '' ?> <?= $i < $current_step_idx ? 'completed' : '' ?>">
          <div class="step-dot"><?= $i < $current_step_idx ? '✓' : ($i+1) ?></div>
          <div class="step-label"><?= $step_labels[$i] ?></div>
          <?php if ($i === 0 && $shp['PlannedDeparture']): ?><div class="step-sub"><?= date('M d, H:i', strtotime($shp['PlannedDeparture'])) ?></div><?php endif; ?>
          <?php if ($i === 3 && $shp['ActualArrival']): ?><div class="step-sub"><?= date('M d, H:i', strtotime($shp['ActualArrival'])) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="grid-2 mt-16" style="gap:20px;">

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Shipment Information</h3>
    </div>
    <div class="card-body">
      <div class="info-grid">
        <div class="info-row">
          <span class="info-label">Shipment ID</span>
          <span class="info-value"><strong><?= $shp_formatted_id ?></strong></span>
        </div>
        <div class="info-row">
          <span class="info-label">Route</span>
          <span class="info-value"><?= htmlspecialchars($shp['RouteName']) ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Asset</span>
          <span class="info-value"><?= htmlspecialchars($shp['AssetModel'] ?? 'N/A') ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Carrier</span>
          <span class="info-value"><?= htmlspecialchars($shp['CarrierName'] ?? 'Internal') ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Planned Departure</span>
          <span class="info-value"><?= $shp['PlannedDeparture'] ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Actual Departure</span>
          <span class="info-value"><?= $shp['ActualDeparture'] ?? '<span class="td-muted">—</span>' ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Estimated Arrival</span>
          <span class="info-value"><?= $shp['EstimatedArrival'] ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Actual Arrival</span>
          <span class="info-value"><?= $shp['ActualArrival'] ?? '<span class="td-muted">—</span>' ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Deadline</span>
          <span class="info-value"><?= $shp['DeliveryDeadline'] ?></span>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Related Order</h3>
    </div>
    <div class="card-body">
      <?php if ($order): ?>
        <div class="info-grid">
          <div class="info-row">
            <span class="info-label">Order ID</span>
            <span class="info-value"><strong>ORD-<?= $order['OrderID'] ?></strong></span>
          </div>
          <div class="info-row">
            <span class="info-label">Customer</span>
            <span class="info-value"><?= htmlspecialchars($order['CustomerName']) ?></span>
          </div>
          <div class="info-row">
            <span class="info-label">Pickup Address</span>
            <span class="info-value"><?= htmlspecialchars($order['PickupAddress']) ?></span>
          </div>
          <div class="info-row">
            <span class="info-label">Order Date</span>
            <span class="info-value"><?= $order['OrderDate'] ?></span>
          </div>
          <div class="info-row">
            <span class="info-label">Expected Delivery</span>
            <span class="info-value"><?= $order['ExpectedDeliveryDate'] ?></span>
          </div>
          <div class="info-row">
            <span class="info-label">Order Status</span>
            <span class="info-value"><?= status_badge($order['ShippingStatus']) ?></span>
          </div>
        </div>
      <?php else: ?>
        <div class="alert alert-info m-16">No order assigned to this shipment yet.</div>
      <?php endif; ?>
    </div>
  </div>

</div>

<div class="card mt-16">
  <div class="card-header">
    <h3 class="card-title">📍 Tracking Log</h3>
  </div>
  <div class="card-body">
    <?php if (empty($tracking_logs)): ?>
      <div class="alert alert-info">No tracking logs yet for this shipment.</div>
    <?php else: ?>
      <div class="timeline">
        <?php foreach ($tracking_logs as $log): ?>
          <div class="timeline-item">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
              <div>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
                  <span style="font-size:20px;"><?php
                    echo match($log['WeatherCondition']) {
                      'Clear'      => '☀️',
                      'Rain'       => '🌧️',
                      'Heavy Rain' => '⛈️',
                      'Snowstorm'  => '🌨️',
                      'Fog'        => '🌫️',
                      'Typhoon'    => '🌀',
                      'Sandstorm'  => '🏜️',
                      default      => '🌡️'
                    };
                  ?></span>
                  <strong><?= htmlspecialchars($log['CheckpointLocation']) ?></strong>
                  
                  <?php if ($log['TrafficDelayTime'] > 0): ?>
                    <span class="badge badge-red">+<?= $log['TrafficDelayTime'] ?> <?= $log['TrafficDelayTimeUnit'] ?> delay</span>
                  <?php else: ?>
                    <span class="badge badge-green">On Time</span>
                  <?php endif; ?>
                </div>
                <div class="text-muted" style="font-size:11px;margin-top:4px;">
                  👤 <?= htmlspecialchars($log['Username'] ?? 'System') ?> &nbsp;|&nbsp; ☁️ <?= htmlspecialchars($log['WeatherCondition']) ?>
                </div>
              </div>
              <div class="text-muted" style="font-size:12px;white-space:nowrap;"><?= date('M d, H:i', strtotime($log['Timestamp'])) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="modal-overlay" id="modalUpdateStatus">
  <div class="modal" style="max-width:380px;">
    <div class="modal-header">
      <h3 class="modal-title">🔄 Update Status</h3>
      <button class="modal-close">×</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="update_status">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">New Status</label>
          <select class="form-control" name="new_status">
            <option value="SCHEDULED"  <?= $current_status=='SCHEDULED'?'selected':'' ?>>SCHEDULED</option>
            <option value="PENDING"    <?= $current_status=='PENDING'?'selected':'' ?>>PENDING</option>
            <option value="IN TRANSIT" <?= $current_status=='IN_TRANSIT'?'selected':'' ?>>IN TRANSIT</option>
            <option value="DELIVERED"  <?= $current_status=='DELIVERED'?'selected':'' ?>>DELIVERED</option>
            <option value="CANCELLED"  <?= $current_status=='CANCELLED'?'selected':'' ?>>CANCELLED</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Actual Arrival (if delivered)</label>
          <input type="datetime-local" name="actual_arr" class="form-control" value="<?= $shp['ActualArrival'] ? date('Y-m-d\TH:i', strtotime($shp['ActualArrival'])) : '' ?>">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Update Status</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="modalLogTracking">
  <div class="modal" style="max-width:460px;">
    <div class="modal-header">
      <h3 class="modal-title">📍 Log Tracking Update</h3>
      <button class="modal-close">×</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="log_tracking">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Current Location <span style="color:red">*</span></label>
          <input type="text" name="location" class="form-control" placeholder="e.g. Binh Dinh Province Checkpoint" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Weather</label>
            <select class="form-control" name="weather">
              <option value="Clear">Clear</option>
              <option value="Rain">Rain</option>
              <option value="Heavy Rain">Heavy Rain</option>
              <option value="Snowstorm">Snowstorm</option>
              <option value="Fog">Fog</option>
              <option value="Typhoon">Typhoon</option>
              <option value="Sandstorm">Sandstorm</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Delay (minutes)</label>
            <input type="number" name="delay" class="form-control" value="0" min="0">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Save Log</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="modalReportExc">
  <div class="modal" style="max-width:460px;">
    <div class="modal-header">
      <h3 class="modal-title">⚠️ Report Exception</h3>
      <button class="modal-close">×</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="report_exception">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Issue Type</label>
          <select class="form-control" name="issue_type">
            <option>Traffic Delay</option>
            <option>Damaged Goods</option>
            <option>Route Deviation</option>
            <option>Vehicle Breakdown</option>
            <option>Failed Delivery</option>
            <option>Weather Event</option>
            <option>Port Congestion</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea class="form-control" name="description" rows="3" placeholder="Describe the issue in detail..." required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-danger">Report</button>
      </div>
    </form>
  </div>
</div>

<?php close_page(); $db->close(); ?>