<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/db.php';
auth_require('operations');

$db = get_db();
$message = '';
$message_type = '';
// Giả sử session account_id được set lúc login. Nếu không có thì fallback tạm về ops account ID 4
$current_account_id = $_SESSION['account_id'] ?? 4; 

// ══════════════════════════════════════════════════════════════════════════════
//  MIGRATION: Thêm cột Note vào bảng tracking_log nếu chưa có
// ══════════════════════════════════════════════════════════════════════════════
$db->query("ALTER TABLE tracking_log ADD COLUMN IF NOT EXISTS `Note` text DEFAULT NULL");

// ══════════════════════════════════════════════════════════════════════════════
//  POST HANDLING - Xử lý Form Thêm Tracking Log
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_log') {
        $shipment_id = (int)($_POST['shipment_id'] ?? 0);
        $location    = trim($_POST['location'] ?? '');
        $weather     = $_POST['weather'] ?? 'Clear';
        $delay       = (int)($_POST['delay'] ?? 0);
        $delay_unit  = $_POST['delay_unit'] ?? 'Minutes';
        $raw_time    = $_POST['timestamp'] ?? '';
        $note        = trim($_POST['note'] ?? '');
        
        // Chuẩn hoá thời gian để insert MySQL (Chuyển "T" thành khoảng trắng)
        $timestamp = $raw_time ? str_replace('T', ' ', $raw_time) : date('Y-m-d H:i:s');
        
        // Validate ENUM Delay Unit
        if (!in_array($delay_unit, ['Minutes','Hours','Days'])) {
            $delay_unit = 'Minutes';
        }

        if (!$shipment_id || !$location) {
            $message = "Shipment ID and Location are required.";
            $message_type = "danger";
        } else {
            $stmt = $db->prepare("INSERT INTO tracking_log (ShipmentID, AccountID, Timestamp, CheckpointLocation, WeatherCondition, TrafficDelayTime, TrafficDelayTimeUnit, Note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iisssiss', $shipment_id, $current_account_id, $timestamp, $location, $weather, $delay, $delay_unit, $note);
            
            if ($stmt->execute()) {
                $message = "Tracking log added successfully!";
                $message_type = "success";
            } else {
                $message = "Error adding tracking log: " . $db->error;
                $message_type = "danger";
            }
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  FETCH DATA FROM DB
// ══════════════════════════════════════════════════════════════════════════════

// 1. Fetch Danh sách Shipments cho Dropdown Filter
$shipments = [];
$res_shp = $db->query("
    SELECT s.ShipmentID, r.RouteName, s.Status 
    FROM shipment s 
    LEFT JOIN route r ON s.RouteID = r.RouteID 
    ORDER BY s.ShipmentID DESC
");
if ($res_shp) {
    while ($row = $res_shp->fetch_assoc()) {
        $shipments[] = $row;
    }
}

// 2. Fetch Tracking Logs dựa trên Filter
$filter_shp = (int)($_GET['shipment'] ?? 0);

$sql_logs = "
    SELECT t.*, a.Username 
    FROM tracking_log t 
    LEFT JOIN account a ON t.AccountID = a.AccountID
";
if ($filter_shp > 0) {
    $sql_logs .= " WHERE t.ShipmentID = $filter_shp";
}
$sql_logs .= " ORDER BY t.Timestamp DESC";

$logs = [];
$res_logs = $db->query($sql_logs);
if ($res_logs) {
    while ($row = $res_logs->fetch_assoc()) {
        $logs[] = $row;
    }
}

// Dictionary Map Icon thời tiết chuẩn theo ENUM trong DB
$weather_icons = [
    'Clear'      => '☀️',
    'Rain'       => '🌧️',
    'Heavy Rain' => '⛈️',
    'Snowstorm'  => '❄️',
    'Fog'        => '🌫️',
    'Typhoon'    => '🌪️',
    'Sandstorm'  => '🏜️',
];

open_page('Tracking Logs', 'tracking', [['label'=>'Operations'],['label'=>'Tracking Logs']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Tracking Logs</h1>
    <p class="text-muted" style="margin-top:4px;"><?= count($logs) ?> tracking events shown</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-primary" data-modal-open="modalAddTracking">+ Add Tracking Log</button>
  </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $message_type ?>" style="margin-top:16px;">
  <?= $message_type === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- Filter Bar -->
<div class="filter-bar mt-16">
  <div class="search-input-wrapper" style="flex:1;">
    <span class="search-icon">🔍</span>
    <input type="text" class="form-control" placeholder="Search location, notes..." data-table-search="trackingTable" style="width:99%;">
  </div>
  <form method="get" style="display:flex;gap:10px;align-items:center;margin:0;">
    <select name="shipment" class="form-control" style="min-width:200px;" onchange="this.form.submit()">
      <option value="">All Shipments</option>
      <?php foreach ($shipments as $s): ?>
        <option value="<?= $s['ShipmentID'] ?>" <?= $filter_shp === (int)$s['ShipmentID'] ? 'selected' : '' ?>>
          #<?= $s['ShipmentID'] ?> — <?= htmlspecialchars($s['RouteName'] ?? 'No Route') ?>
        </option>
      <?php endforeach; ?>
    </select>
    <?php if ($filter_shp > 0): ?>
      <a href="tracking.php" class="btn btn-outline btn-sm">✕ Clear Filter</a>
    <?php endif; ?>
  </form>
</div>

<?php if ($filter_shp > 0): ?>
  <div class="alert alert-info mt-8">
    Showing tracking logs for shipment <strong><?= $filter_shp ?></strong>
    (<?= count($logs) ?> events)
  </div>
<?php endif; ?>

<!-- Chuyển layout sang 2 cột, thiết lập align-items: start để không bị stretch dọc (không fixed) -->
<!-- Cho phép cuộn (scroll) trong nội dung bảng/timeline nếu dữ liệu quá dài -->
<div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 24px; align-items: start;" class="mt-16">

  <!-- Timeline View -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">📍 Live Timeline</h3>
    </div>
    <!-- Cố định chiều cao tối đa và cho phép cuộn để lướt thoải mái -->
    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
      <?php if (empty($logs)): ?>
        <div class="alert alert-info">No tracking logs found.</div>
      <?php else: ?>
        <div class="timeline">
          <?php foreach ($logs as $log): ?>
            <div class="timeline-item">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div style="flex:1;">
                  <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <span style="font-size:22px;"><?= $weather_icons[$log['WeatherCondition']] ?? '🌡️' ?></span>
                    <div>
                      <strong style="font-size:14px;"><?= htmlspecialchars($log['CheckpointLocation']) ?></strong>
                      <div class="td-muted" style="font-size:11px;">📦 <?= $log['ShipmentID'] ?> &nbsp;·&nbsp; 👤 <?= htmlspecialchars($log['Username'] ?? 'System') ?></div>
                    </div>
                  </div>
                  <?php if (!empty($log['Note'])): ?>
                    <div class="text-muted" style="font-size:13px;margin-bottom:4px;"><?= nl2br(htmlspecialchars($log['Note'])) ?></div>
                  <?php endif; ?>
                  <div style="display:flex;gap:8px;">
                    <span class="badge badge-gray">☁️ <?= htmlspecialchars($log['WeatherCondition']) ?></span>
                    <?php if ($log['TrafficDelayTime'] > 0): ?>
                      <span class="badge badge-red">+<?= $log['TrafficDelayTime'] ?> <?= htmlspecialchars($log['TrafficDelayTimeUnit']) ?></span>
                    <?php else: ?>
                      <span class="badge badge-green">On Time</span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="text-muted" style="font-size:11px;white-space:nowrap;margin-left:12px;">
                  <?= date('Y-m-d H:i', strtotime($log['Timestamp'])) ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Summary Table -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">📋 All Log Entries</h3>
    </div>
    <!-- Cố định chiều cao tối đa và cho phép cuộn để lướt thoải mái -->
    <div class="table-wrapper" style="max-height: 600px; overflow-y: auto; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px;">
      <table id="trackingTable" style="font-size:12px;">
        <thead style="position: sticky; top: 0; background: #f8fafc; z-index: 10;">
          <tr>
            <th>Log ID</th>
            <th>Shipment</th>
            <th>Timestamp</th>
            <th>Location</th>
            <th>Weather</th>
            <th>Delay</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td><strong>LOG-<?= $log['LogID'] ?></strong></td>
              <td>
                <a href="tracking.php?shipment=<?= $log['ShipmentID'] ?>" style="color:var(--c-yellow);">
                  #<?= $log['ShipmentID'] ?>
                </a>
              </td>
              <td class="td-muted"><?= date('Y-m-d H:i', strtotime($log['Timestamp'])) ?></td>
              <td style="max-width:150px;" class="truncate"><?= htmlspecialchars($log['CheckpointLocation']) ?></td>
              <td><?= $weather_icons[$log['WeatherCondition']] ?? '' ?> <?= htmlspecialchars($log['WeatherCondition']) ?></td>
              <td>
                <?php if ($log['TrafficDelayTime'] > 0): ?>
                  <span class="badge badge-red">+<?= $log['TrafficDelayTime'] ?> <?= substr($log['TrafficDelayTimeUnit'], 0, 1) ?></span>
                <?php else: ?>
                  <span class="badge badge-green">0</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Modal: Add Tracking Log -->
<div class="modal-overlay" id="modalAddTracking">
  <div class="modal" style="max-width:520px;">
    <div class="modal-header">
      <h3 class="modal-title">📍 Add Tracking Log</h3>
      <button class="modal-close">×</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="add_log">
      
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Shipment <span style="color:red">*</span></label>
          <select name="shipment_id" class="form-control" required>
            <option value="">Select shipment...</option>
            <?php foreach ($shipments as $s): ?>
              <option value="<?= $s['ShipmentID'] ?>" <?= $filter_shp === (int)$s['ShipmentID'] ? 'selected' : '' ?>>
                #<?= $s['ShipmentID'] ?> — <?= htmlspecialchars($s['RouteName'] ?? 'No Route') ?> (<?= $s['Status'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="form-group">
          <label class="form-label">Current Location <span style="color:red">*</span></label>
          <input type="text" name="location" class="form-control" placeholder="e.g. Da Nang Checkpoint, Km 760" required>
        </div>
        
        <div class="form-group">
          <label class="form-label">Weather Condition</label>
          <select name="weather" class="form-control">
            <option value="Clear">Clear</option>
            <option value="Rain">Rain</option>
            <option value="Heavy Rain">Heavy Rain</option>
            <option value="Snowstorm">Snowstorm</option>
            <option value="Fog">Fog</option>
            <option value="Typhoon">Typhoon</option>
            <option value="Sandstorm">Sandstorm</option>
          </select>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Traffic Delay Amount</label>
            <input type="number" name="delay" class="form-control" value="0" min="0" placeholder="0">
          </div>
          <div class="form-group">
            <label class="form-label">Delay Unit</label>
            <select name="delay_unit" class="form-control">
              <option value="Minutes">Minutes</option>
              <option value="Hours">Hours</option>
              <option value="Days">Days</option>
            </select>
          </div>
        </div>
        
        <div class="form-group">
          <label class="form-label">Timestamp <span style="color:red">*</span></label>
          <input type="datetime-local" name="timestamp" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
        </div>
        
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea name="note" class="form-control" rows="3" placeholder="Describe current status, issues, observations..."></textarea>
        </div>
      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Save Tracking Log</button>
      </div>
    </form>
  </div>
</div>

<?php 
$db->close();
close_page(); 
?>