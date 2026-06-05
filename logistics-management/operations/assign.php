<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/db.php';
auth_require('operations');

$db      = get_db();
$message = '';
$message_type = '';

// ── Auto-fix: đồng bộ những order vẫn còn status 'Pending' nhưng đã được link vào shipment ──
$db->query("
    UPDATE order_info oi
    JOIN shipment_order so ON so.OrderID = oi.OrderID
    SET oi.ShippingStatus = 'Processing'
    WHERE oi.ShippingStatus = 'Pending'
");

// ══════════════════════════════════════════════════════════════════════════════
//  POST HANDLING
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── CREATE ASSIGNMENT (new shipment + link order) ─────────────────────────
    if ($action === 'create_assignment') {
        $order_id    = (int)($_POST['order_id']    ?? 0);
        $asset_id    = (int)($_POST['asset_id']    ?? 0);
        $route_id    = (int)($_POST['route_id']    ?? 0);
        $planned_dep = trim($_POST['planned_dep']  ?? '');
        $deadline    = trim($_POST['deadline']     ?? '') ?: null;
        $est_arr     = trim($_POST['est_arr']      ?? '') ?: null;

        if (!$order_id || !$asset_id || !$route_id || !$planned_dep) {
            $message      = 'Please fill in Order, Asset, Route and Planned Departure.';
            $message_type = 'danger';
        } else {
            $status = 'Pending';
            $stmt = $db->prepare("
                INSERT INTO shipment (RouteID, AssetID, Status, PlannedDeparture, EstimatedArrival, DeliveryDeadline)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('iissss', $route_id, $asset_id, $status, $planned_dep, $est_arr, $deadline);

            if ($stmt->execute()) {
                $shipment_id = $db->insert_id;

                // Link with order
                $stmt2 = $db->prepare("INSERT INTO shipment_order (ShipmentID, OrderID, LegSequence) VALUES (?, ?, 1)");
                $stmt2->bind_param('ii', $shipment_id, $order_id);
                $stmt2->execute();

                // Update order status — chỉ update nếu còn là Pending (tránh ghi đè trạng thái xa hơn)
                $db->query("UPDATE order_info SET ShippingStatus = 'Processing' WHERE OrderID = $order_id AND ShippingStatus = 'Pending'");

                $message      = "Assignment created! Shipment #$shipment_id linked to Order #$order_id.";
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

// 1. Orders pending assignment: chỉ lấy status 'Pending' và chưa có shipment nào được link
$pending_orders = [];
$res = $db->query("
    SELECT oi.OrderID, bp.PartyName AS CustomerName,
           oi.PickupAddress, oi.ExpectedDeliveryDate, oi.ShippingStatus,
           COALESCE(SUM(od.Weight * od.Quantity), 0) AS TotalWeight
    FROM order_info oi
    JOIN business_party bp ON bp.PartyID = oi.CustomerID
    LEFT JOIN order_detailed od ON od.OrderID = oi.OrderID
    WHERE oi.ShippingStatus NOT IN ('Delivered', 'Cancelled')
      AND NOT EXISTS (
          SELECT 1 FROM shipment_order so WHERE so.OrderID = oi.OrderID
      )
    GROUP BY oi.OrderID
    ORDER BY oi.ExpectedDeliveryDate ASC
");
while ($row = $res->fetch_assoc()) { $pending_orders[] = $row; }

// 2. All transport assets with carrier name
$all_assets = [];
$res = $db->query("
    SELECT a.AssetID, a.AssetCategory, a.VehicleModel, a.MaxWeight, a.MaxVolume,
           a.CarrierID, bp.PartyName AS CarrierName
    FROM transport_asset a
    LEFT JOIN business_party bp ON bp.PartyID = a.CarrierID
    ORDER BY a.AssetID
");
while ($row = $res->fetch_assoc()) { $all_assets[] = $row; }

// 3. Carriers (Active)
$carriers = [];
$res = $db->query("
    SELECT c.PartyID, bp.PartyName, c.PFM_Score, c.Status, c.ServiceArea
    FROM carrier c
    JOIN business_party bp ON bp.PartyID = c.PartyID
    WHERE c.Status = 'Active'
    ORDER BY bp.PartyName
");
while ($row = $res->fetch_assoc()) { $carriers[] = $row; }

// 4. Routes
$routes = [];
$res = $db->query("SELECT RouteID, RouteName, StartLocation, EndLocation, EstimatedDistance FROM route ORDER BY RouteName");
while ($row = $res->fetch_assoc()) { $routes[] = $row; }

// 5. Recent assignments (last 8 shipments with order info)
$recent = [];
$res = $db->query("
    SELECT s.ShipmentID, s.Status, s.PlannedDeparture,
           r.RouteName, r.StartLocation, r.EndLocation,
           a.VehicleModel, a.AssetCategory,
           bp_c.PartyName AS CarrierName,
           so.OrderID,
           bp_cust.PartyName AS CustomerName
    FROM shipment s
    LEFT JOIN route r             ON r.RouteID   = s.RouteID
    LEFT JOIN transport_asset a   ON a.AssetID   = s.AssetID
    LEFT JOIN business_party bp_c ON bp_c.PartyID = a.CarrierID
    LEFT JOIN shipment_order so   ON so.ShipmentID = s.ShipmentID
    LEFT JOIN order_info oi       ON oi.OrderID    = so.OrderID
    LEFT JOIN business_party bp_cust ON bp_cust.PartyID = oi.CustomerID
    GROUP BY s.ShipmentID
    ORDER BY s.ShipmentID DESC
    LIMIT 8
");
while ($row = $res->fetch_assoc()) { $recent[] = $row; }

// Stats
$total_routes   = count($routes);
$total_carriers = count($carriers);

open_page('Assign Transport Assets', 'assign', [['label'=>'Operations'],['label'=>'Assign Assets']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Assign Transport Assets</h1>
    <p class="page-subtitle">Match pending orders with suitable carriers and transport assets</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-primary" data-modal-open="assignModal">🔗 New Assignment</button>
  </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $message_type ?>" style="margin-bottom:16px;">
  <?= $message_type === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
  <div class="stat-card yellow">
    <div class="stat-icon">📦</div>
    <div class="stat-value"><?= count($pending_orders) ?></div>
    <div class="stat-label">Unassigned Pending Orders</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">🚛</div>
    <div class="stat-value"><?= count($all_assets) ?></div>
    <div class="stat-label">Transport Assets</div>
  </div>
  <div class="stat-card navy">
    <div class="stat-icon">🗺️</div>
    <div class="stat-value"><?= $total_routes ?></div>
    <div class="stat-label">Available Routes</div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon">🏢</div>
    <div class="stat-value"><?= $total_carriers ?></div>
    <div class="stat-label">Active Carriers</div>
  </div>
</div>

<div class="grid-2">
  <!-- Left: Pending Orders -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">📦 Orders Pending Assignment</div>
      <span class="badge badge-yellow"><?= count($pending_orders) ?> unassigned</span>
    </div>
    <div style="overflow-y:auto;max-height:420px;">
      <?php if (empty($pending_orders)): ?>
        <div style="padding:32px;text-align:center;color:var(--text-muted);">
          <div style="font-size:32px;margin-bottom:8px;">✅</div>
          <div style="font-weight:600;margin-bottom:4px;">All orders assigned!</div>
          <div style="font-size:12px;">No pending orders waiting for transport assignment.</div>
        </div>
      <?php endif; ?>
      <?php foreach ($pending_orders as $o): ?>
      <div class="activity-item" style="padding:14px 20px;border-bottom:1px solid var(--border-color);">
        <div class="activity-avatar" style="background:linear-gradient(135deg,var(--c-yellow),var(--c-olive-light));color:var(--c-navy-900);">📦</div>
        <div class="activity-body" style="flex:1;">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
            <strong>ORD-<?= $o['OrderID'] ?></strong>
            <?php
              $due = $o['ExpectedDeliveryDate'] ? strtotime($o['ExpectedDeliveryDate']) : null;
              $daysLeft = $due ? ceil(($due - time()) / 86400) : null;
              if ($daysLeft !== null && $daysLeft <= 3):
            ?>
              <span class="badge badge-red" style="font-size:10px;">⚠️ Due in <?= $daysLeft <= 0 ? 'Overdue' : $daysLeft.'d' ?></span>
            <?php elseif ($daysLeft !== null && $daysLeft <= 7): ?>
              <span class="badge badge-yellow" style="font-size:10px;">📅 <?= $daysLeft ?>d left</span>
            <?php endif; ?>
          </div>
          <div style="font-size:12px;color:var(--text-secondary);margin-bottom:3px;">
            👤 <?= htmlspecialchars($o['CustomerName']) ?>
          </div>
          <div style="font-size:11px;color:var(--text-muted);">
            📍 <?= htmlspecialchars(mb_strimwidth($o['PickupAddress'] ?? '', 0, 45, '…')) ?><br>
            ⚖️ <?= $o['TotalWeight'] > 0 ? number_format($o['TotalWeight'], 0) . ' kg' : 'N/A' ?>
            &nbsp;·&nbsp;
            📅 <?= $o['ExpectedDeliveryDate'] ? date('d/m/Y', strtotime($o['ExpectedDeliveryDate'])) : 'No deadline' ?>
          </div>
        </div>
        <button class="btn btn-accent btn-sm" data-modal-open="assignModal"
                onclick="prefillOrder(<?= $o['OrderID'] ?>,'<?= htmlspecialchars(addslashes($o['CustomerName'])) ?>',<?= $o['TotalWeight'] ?>)">
          🔗 Assign
        </button>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Right: Transport Assets -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">🚛 Transport Assets</div>
      <span class="badge badge-green"><?= count($all_assets) ?> total</span>
    </div>
    <div style="overflow-y:auto;max-height:420px;">
      <?php foreach ($all_assets as $a): ?>
      <div class="activity-item" style="padding:14px 20px;border-bottom:1px solid var(--border-color);">
        <div class="activity-avatar" style="background:var(--c-green-bg);color:var(--c-green);">🚛</div>
        <div class="activity-body" style="flex:1;">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
            <strong>AST-<?= $a['AssetID'] ?></strong>
            <span class="badge badge-navy" style="font-size:10px;"><?= htmlspecialchars($a['AssetCategory']) ?></span>
          </div>
          <div style="font-size:12px;color:var(--text-secondary);margin-bottom:3px;">
            <?= htmlspecialchars($a['VehicleModel']) ?>
          </div>
          <div style="font-size:11px;color:var(--text-muted);">
            🏢 <?= htmlspecialchars($a['CarrierName'] ?? '—') ?><br>
            ⚖️ Max <?= number_format((float)$a['MaxWeight']) ?> kg
            &nbsp;|&nbsp;
            📦 <?= number_format((float)$a['MaxVolume']) ?> m³
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Recent Assignments -->
<div class="card mt-24">
  <div class="card-header">
    <div class="card-title">📋 Recent Assignments</div>
    <a href="../operations/shipments.php" class="btn btn-ghost btn-sm">View All Shipments →</a>
  </div>
  <div class="table-wrapper" style="border-radius:0;border:none;box-shadow:none;">
    <table>
      <thead>
        <tr>
          <th>Shipment</th>
          <th>Order</th>
          <th>Customer</th>
          <th>Route</th>
          <th>Asset / Carrier</th>
          <th>Planned Dep.</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $s): ?>
        <tr>
          <td><strong>#<?= $s['ShipmentID'] ?></strong></td>
          <td><?= $s['OrderID'] ? 'ORD-' . $s['OrderID'] : '—' ?></td>
          <td><?= htmlspecialchars($s['CustomerName'] ?? '—') ?></td>
          <td style="font-size:12px;">
            <?= $s['RouteName'] ? htmlspecialchars($s['RouteName']) : ($s['StartLocation'] ? htmlspecialchars($s['StartLocation'].' → '.$s['EndLocation']) : '—') ?>
          </td>
          <td style="font-size:12px;">
            <?= htmlspecialchars($s['VehicleModel'] ?? '—') ?>
            <?php if ($s['CarrierName']): ?>
              <br><small class="td-muted"><?= htmlspecialchars($s['CarrierName']) ?></small>
            <?php endif; ?>
          </td>
          <td class="td-muted" style="white-space:nowrap;">
            <?= $s['PlannedDeparture'] ? date('Y-m-d H:i', strtotime($s['PlannedDeparture'])) : '—' ?>
          </td>
          <td><?= status_badge(strtoupper(str_replace(' ','_',$s['Status']))) ?></td>
          <td>
            <a href="../operations/shipment_detail.php?id=<?= $s['ShipmentID'] ?>" class="btn btn-ghost btn-sm">🔍 Detail</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ═══ MODAL: Create Assignment ═════════════════════════════════════════════ -->
<div class="modal-overlay" id="assignModal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-title">🔗 Create Assignment</div>
      <button class="modal-close">✕</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="create_assignment">
      <div class="modal-body">

        <!-- Smart Hint -->
        <div class="alert alert-info" id="smartHint" style="display:none;margin-bottom:16px;">
          <span class="alert-icon">💡</span>
          <div class="alert-body">
            <div class="alert-title">Order Info</div>
            <span id="hintText"></span>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Order <span style="color:red">*</span></label>
            <select class="form-control" name="order_id" id="orderSelect" required onchange="updateHint()">
              <option value="">Select order...</option>
              <?php foreach ($pending_orders as $o): ?>
                <option value="<?= $o['OrderID'] ?>"
                        data-weight="<?= $o['TotalWeight'] ?>"
                        data-customer="<?= htmlspecialchars($o['CustomerName']) ?>">
                  ORD-<?= $o['OrderID'] ?> — <?= htmlspecialchars($o['CustomerName']) ?>
                  <?= $o['TotalWeight'] > 0 ? '(' . number_format($o['TotalWeight'], 0) . ' kg)' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Carrier <span style="color:red">*</span></label>
            <select class="form-control" id="carrierSelect" onchange="filterAssets()">
              <option value="">All carriers</option>
              <?php foreach ($carriers as $c): ?>
                <option value="<?= $c['PartyID'] ?>">
                  <?= htmlspecialchars($c['PartyName']) ?> (PFM: <?= $c['PFM_Score'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Transport Asset <span style="color:red">*</span></label>
            <select class="form-control" name="asset_id" id="assetSelect" required>
              <option value="">Select asset...</option>
              <?php foreach ($all_assets as $a): ?>
                <option value="<?= $a['AssetID'] ?>"
                        data-carrier="<?= $a['CarrierID'] ?>"
                        data-weight="<?= $a['MaxWeight'] ?>">
                  AST-<?= $a['AssetID'] ?> — <?= htmlspecialchars($a['VehicleModel']) ?>
                  (<?= htmlspecialchars($a['CarrierName'] ?? '') ?>, <?= number_format((float)$a['MaxWeight']) ?> kg)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Route <span style="color:red">*</span></label>
            <select class="form-control" name="route_id" required>
              <option value="">Select route...</option>
              <?php foreach ($routes as $r): ?>
                <option value="<?= $r['RouteID'] ?>">
                  <?= htmlspecialchars($r['RouteName']) ?>
                  (<?= htmlspecialchars($r['StartLocation']) ?> → <?= htmlspecialchars($r['EndLocation']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
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
          <label class="form-label">Delivery Deadline</label>
          <input type="datetime-local" name="deadline" class="form-control">
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">🔗 Create Assignment</button>
      </div>
    </form>
  </div>
</div>

<script>
function prefillOrder(id, customer, weight) {
  const sel = document.getElementById('orderSelect');
  if (sel) { sel.value = id; updateHint(); }
}

function updateHint() {
  const sel  = document.getElementById('orderSelect');
  const hint = document.getElementById('smartHint');
  const text = document.getElementById('hintText');
  if (!sel || !sel.value) { hint.style.display = 'none'; return; }
  const opt = sel.options[sel.selectedIndex];
  const wt  = parseFloat(opt.dataset.weight || 0);
  hint.style.display = 'flex';
  text.innerHTML = `Customer: <strong>${opt.dataset.customer}</strong>. `
    + (wt > 0 ? `Total weight: <strong>${wt.toLocaleString()} kg</strong>. Select an asset with sufficient capacity.` : 'No weight data available.');
}

function filterAssets() {
  const carrierId = document.getElementById('carrierSelect')?.value;
  const asel = document.getElementById('assetSelect');
  if (!asel) return;
  Array.from(asel.options).forEach(opt => {
    if (!opt.value) return;
    opt.hidden = carrierId ? opt.dataset.carrier !== carrierId : false;
  });
  asel.value = '';
}
</script>

<?php close_page(); $db->close(); ?>