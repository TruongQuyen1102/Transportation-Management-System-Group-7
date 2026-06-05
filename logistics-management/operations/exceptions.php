<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/db.php';
auth_require('operations');

$db = get_db();
$message = '';
$message_type = '';
$current_account_id = $_SESSION['account_id'] ?? 4; // Fallback Ops account

// ══════════════════════════════════════════════════════════════════════════════
//  POST HANDLING - Xử lý thêm mới và cập nhật trạng thái Exception
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── 1. REPORT NEW EXCEPTION ───────────────────────────────────────────────
    if ($action === 'report_exception') {
        $shipment_id = (int)($_POST['shipment_id'] ?? 0);
        $issue_type  = $_POST['issue_type'] ?? '';
        $severity    = $_POST['severity'] ?? 'Low';
        $description = trim($_POST['description'] ?? '');
        $status      = 'Pending'; // Trạng thái mặc định khi tạo mới

        if (!$shipment_id || !$issue_type || !$description) {
            $message = "Please fill in all required fields.";
            $message_type = "danger";
        } else {
            // Lấy CarrierID dựa trên ShipmentID (Shipment -> Asset -> Carrier)
            $stmt_carrier = $db->prepare("
                SELECT ta.CarrierID 
                FROM shipment s 
                JOIN transport_asset ta ON s.AssetID = ta.AssetID 
                WHERE s.ShipmentID = ?
            ");
            $stmt_carrier->bind_param('i', $shipment_id);
            $stmt_carrier->execute();
            $res_carrier = $stmt_carrier->get_result()->fetch_assoc();
            $carrier_id = $res_carrier['CarrierID'] ?? null;

            $stmt = $db->prepare("INSERT INTO operational_exception (CarrierID, ShipmentID, AccountID, IssueType, Description, SeverityLevel, ApprovalStatus) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iiissss', $carrier_id, $shipment_id, $current_account_id, $issue_type, $description, $severity, $status);
            
            if ($stmt->execute()) {
                $message = "Exception reported successfully!";
                $message_type = "success";
            } else {
                $message = "Error reporting exception: " . $db->error;
                $message_type = "danger";
            }
        }
    }

    // ── 2. UPDATE EXCEPTION STATUS ────────────────────────────────────────────
    elseif ($action === 'update_status') {
        $exc_id = (int)($_POST['exception_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';
        $resolution_notes = trim($_POST['resolution_notes'] ?? '');

        if ($exc_id && $new_status) {
            if (!empty($resolution_notes)) {
                // Nối thêm note vào description cũ
                $append_note = "\n\n[Update - " . date('Y-m-d H:i') . "]: " . $resolution_notes;
                $stmt = $db->prepare("UPDATE operational_exception SET ApprovalStatus = ?, Description = CONCAT(Description, ?) WHERE ExceptionID = ?");
                $stmt->bind_param('ssi', $new_status, $append_note, $exc_id);
            } else {
                $stmt = $db->prepare("UPDATE operational_exception SET ApprovalStatus = ? WHERE ExceptionID = ?");
                $stmt->bind_param('si', $new_status, $exc_id);
            }
            
            if ($stmt->execute()) {
                $message = "Exception status updated to $new_status.";
                $message_type = "success";
            } else {
                $message = "Error updating exception: " . $db->error;
                $message_type = "danger";
            }
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  FETCH DATA FROM DB
// ══════════════════════════════════════════════════════════════════════════════

// 1. Fetch Exceptions
$exceptions = [];
$res_exc = $db->query("
    SELECT e.*, 
           bp.PartyName AS CarrierName, 
           a.Username 
    FROM operational_exception e
    LEFT JOIN business_party bp ON e.CarrierID = bp.PartyID
    LEFT JOIN account a ON e.AccountID = a.AccountID
    ORDER BY e.CreatedAt DESC
");
while ($row = $res_exc->fetch_assoc()) {
    $exceptions[] = $row;
}

// 2. Fetch Shipments for Dropdown
$shipments = [];
$res_shp = $db->query("
    SELECT s.ShipmentID, r.RouteName 
    FROM shipment s 
    LEFT JOIN route r ON s.RouteID = r.RouteID 
    ORDER BY s.ShipmentID DESC
");
while ($row = $res_shp->fetch_assoc()) {
    $shipments[] = $row;
}

// Phân loại đếm số lượng theo ApprovalStatus
$pending       = array_filter($exceptions, fn($e) => $e['ApprovalStatus'] === 'Pending');
$investigating = array_filter($exceptions, fn($e) => $e['ApprovalStatus'] === 'Under Investigation');
// DB đang dùng chữ 'Approved', ta gộp chung Approved/Resolved thành nhóm đã xử lý
$resolved      = array_filter($exceptions, fn($e) => in_array($e['ApprovalStatus'], ['Approved', 'Resolved']));

$typeColors = [
    'Traffic Delay'       => 'yellow',
    'Damaged Goods'       => 'red',
    'Route Deviation'     => 'olive',
    'Vehicle Breakdown'   => 'red',
    'Failed Delivery'     => 'red',
    'Weather Delay'       => 'blue',
    'Port Congestion'     => 'yellow',
    'Customs Hold'        => 'navy',
    'Documentation Error' => 'slate',
];

open_page('Delivery Exceptions', 'exceptions', [['label'=>'Operations'],['label'=>'Exceptions']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Delivery Exceptions</h1>
    <p class="page-subtitle">Log, track, and resolve operational anomalies and disruptions</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-ghost btn-sm">📥 Export</button>
    <button class="btn btn-danger" data-modal-open="reportModal">⚠️ Report Exception</button>
  </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $message_type ?>" style="margin-bottom:16px;">
  <?= $message_type === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<?php 
$needs_attention_count = count($pending) + count($investigating);
if ($needs_attention_count > 0): 
?>
<div class="alert alert-danger" style="margin-bottom:24px;">
  <span class="alert-icon">🚨</span>
  <div class="alert-body">
    <div class="alert-title"><?= $needs_attention_count ?> Exception<?= $needs_attention_count > 1 ? 's' : '' ?> Need Attention</div>
    There are <?= count($pending) ?> pending and <?= count($investigating) ?> investigating exceptions. Please review and update them to keep operations running smoothly.
  </div>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
  <div class="stat-card red">
    <div class="stat-icon" style="background:var(--c-red-bg)">🔴</div>
    <div class="stat-body">
      <div class="stat-value"><?= count($pending) ?></div>
      <div class="stat-label">Pending</div>
      <div class="stat-trend down">Requires action</div>
    </div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon" style="background:var(--c-yellow-bg)">🔵</div>
    <div class="stat-body">
      <div class="stat-value"><?= count($investigating) ?></div>
      <div class="stat-label">Investigating</div>
      <div class="stat-trend neutral">Under review</div>
    </div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon" style="background:var(--c-green-bg)">✅</div>
    <div class="stat-body">
      <div class="stat-value"><?= count($resolved) ?></div>
      <div class="stat-label">Resolved / Approved</div>
      <div class="stat-trend up">Handled issues</div>
    </div>
  </div>
  <div class="stat-card navy">
    <div class="stat-icon" style="background:rgba(12,40,64,.08)">📋</div>
    <div class="stat-body">
      <div class="stat-value"><?= count($exceptions) ?></div>
      <div class="stat-label">Total Logged</div>
      <div class="stat-trend neutral">All time</div>
    </div>
  </div>
</div>

<!-- Filter + Table -->
<div class="card">
  <div class="card-header">
    <div class="card-title">⚠️ Exception Log</div>
  </div>
  <div class="card-body" style="padding:16px 20px 0;">
    <div class="table-toolbar">
      <div class="table-toolbar-left" style="display:flex; gap:10px; flex-wrap:wrap; width:100%;">
        <div class="search-input-wrapper" style="flex:1;">
          <span class="search-icon">🔍</span>
          <input type="text" class="form-control" placeholder="Search exceptions, ID, shipments..." data-table-search="exceptTable" style="width:98%;">
        </div>
        <select class="form-control" style="font-size:12px; width:160px;" onchange="filterExcept(this.value,'status')">
          <option value="">All Statuses</option>
          <option value="Pending">Pending</option>
          <option value="Under Investigation">Under Investigation</option>
          <option value="Approved">Approved</option>
          <option value="Resolved">Resolved</option>
        </select>
      </div>
    </div>
  </div>
  <div class="table-wrapper" style="border-radius:0;border:none;box-shadow:none;margin-top:10px;">
    <table id="exceptTable">
      <thead>
        <tr>
          <th>Exception ID</th>
          <th>Shipment</th>
          <th>Carrier</th>
          <th>Issue Type</th>
          <th>Description</th>
          <th>Status</th>
          <th>Logged At</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($exceptions as $exc): ?>
        <?php $badge_color = match($exc['ApprovalStatus']) {
            'Approved', 'Resolved' => 'green',
            'Under Investigation' => 'yellow',
            'Pending' => 'red',
            default => 'gray'
        }; ?>
        <tr data-status="<?= htmlspecialchars($exc['ApprovalStatus']) ?>">
          <td><strong>EXC-<?= $exc['ExceptionID'] ?></strong></td>
          <td><a href="../operations/shipment_detail.php?id=<?= $exc['ShipmentID'] ?>" style="font-weight:600; color:var(--c-yellow);">#<?= $exc['ShipmentID'] ?></a></td>
          <td style="font-size:12px;"><?= htmlspecialchars($exc['CarrierName'] ?? 'N/A') ?></td>
          <td>
            <span class="badge badge-<?= $typeColors[$exc['IssueType']] ?? 'slate' ?>"><?= htmlspecialchars($exc['IssueType']) ?></span>
          </td>
          <td style="max-width:240px;font-size:12px;" class="truncate" title="<?= htmlspecialchars($exc['Description']) ?>">
            <?= htmlspecialchars(mb_strimwidth($exc['Description'], 0, 60, '…')) ?>
          </td>
          <td><span class="badge badge-<?= $badge_color ?>"><?= htmlspecialchars($exc['ApprovalStatus']) ?></span></td>
          <td class="td-muted"><?= date('Y-m-d H:i', strtotime($exc['CreatedAt'])) ?></td>
          <td>
            <div class="action-menu" style="position:relative;display:inline-block;">
              <button class="action-menu-btn btn btn-ghost btn-sm" onclick="toggleExcDropdown(event, 'excMenu<?= $exc['ExceptionID'] ?>')">⋯</button>
              <div class="action-dropdown" id="excMenu<?= $exc['ExceptionID'] ?>" style="display:none;position:absolute;right:0;top:100%;z-index:999;min-width:160px;background:#fff;border:1px solid #e2e8f0;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.12);padding:4px 0;">
                <button class="dropdown-item" data-modal-open="detailModal-<?= $exc['ExceptionID'] ?>">🔍 View / Update</button>
              </div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Detail & Update Modals -->
<?php foreach ($exceptions as $exc): ?>
<?php $badge_color = match($exc['ApprovalStatus']) {
    'Approved', 'Resolved' => 'green',
    'Under Investigation' => 'yellow',
    'Pending' => 'red',
    default => 'gray'
}; ?>
<div class="modal-overlay" id="detailModal-<?= $exc['ExceptionID'] ?>">
  <div class="modal modal-lg" style="max-width:600px;">
    <div class="modal-header">
      <div class="modal-title">⚠️ <span>EXC-<?= $exc['ExceptionID'] ?> — <?= htmlspecialchars($exc['IssueType']) ?></span></div>
      <button class="modal-close">✕</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="exception_id" value="<?= $exc['ExceptionID'] ?>">
      
      <div class="modal-body">
        <div class="info-grid">
          <div class="info-row"><div class="info-label">Shipment</div><div class="info-value">#<?= $exc['ShipmentID'] ?></div></div>
          <div class="info-row"><div class="info-label">Carrier</div><div class="info-value"><?= htmlspecialchars($exc['CarrierName'] ?? 'N/A') ?></div></div>
          <div class="info-row"><div class="info-label">Issue Type</div><div class="info-value"><span class="badge badge-<?= $typeColors[$exc['IssueType']] ?? 'slate' ?>"><?= htmlspecialchars($exc['IssueType']) ?></span></div></div>
          <div class="info-row"><div class="info-label">Current Status</div><div class="info-value"><span class="badge badge-<?= $badge_color ?>"><?= htmlspecialchars($exc['ApprovalStatus']) ?></span></div></div>
          <div class="info-row"><div class="info-label">Severity</div><div class="info-value"><strong><?= htmlspecialchars($exc['SeverityLevel']) ?></strong></div></div>
          <div class="info-row"><div class="info-label">Logged By</div><div class="info-value"><?= htmlspecialchars($exc['Username']) ?></div></div>
        </div>
        <div class="divider"></div>
        <div class="form-group">
          <label class="form-label">Full Description</label>
          <div style="background:var(--c-neutral-100);border-radius:8px;padding:14px;font-size:13px;line-height:1.6;color:var(--text-secondary); white-space: pre-wrap;"><?= htmlspecialchars($exc['Description']) ?></div>
        </div>
        
        <?php if (!in_array($exc['ApprovalStatus'], ['Approved', 'Resolved'])): ?>
        <div class="form-row mt-12">
            <div class="form-group" style="flex:1;">
              <label class="form-label">Change Status To</label>
              <select name="new_status" class="form-control">
                <option value="Pending" <?= $exc['ApprovalStatus'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                <option value="Under Investigation" <?= $exc['ApprovalStatus'] === 'Under Investigation' ? 'selected' : '' ?>>Under Investigation</option>
                <option value="Resolved">Resolved</option>
              </select>
            </div>
        </div>
        <div class="form-group">
          <label class="form-label">Resolution Notes (Optional)</label>
          <textarea class="form-control" name="resolution_notes" rows="2" placeholder="Add resolution notes or follow-up actions..."></textarea>
        </div>
        <?php endif; ?>
      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close>Close</button>
        <?php if (!in_array($exc['ApprovalStatus'], ['Approved', 'Resolved'])): ?>
          <button type="submit" class="btn btn-primary">Save Changes</button>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>

<!-- Report Exception Modal -->
<div class="modal-overlay" id="reportModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">⚠️ <span>Report New Exception</span></div>
      <button class="modal-close">✕</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="report_exception">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Shipment <span style="color:red;">*</span></label>
          <select name="shipment_id" class="form-control" required>
            <option value="">Select shipment...</option>
            <?php foreach ($shipments as $s): ?>
              <option value="<?= $s['ShipmentID'] ?>">#<?= $s['ShipmentID'] ?> — <?= htmlspecialchars($s['RouteName'] ?? 'No Route') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Issue Type <span style="color:red;">*</span></label>
            <select name="issue_type" class="form-control" required>
              <option value="Traffic Delay">Traffic Delay</option>
              <option value="Vehicle Breakdown">Vehicle Breakdown</option>
              <option value="Damaged Goods">Damaged Goods</option>
              <option value="Route Deviation">Route Deviation</option>
              <option value="Failed Delivery">Failed Delivery</option>
              <option value="Weather Delay">Weather Delay</option>
              <option value="Port Congestion">Port Congestion</option>
              <option value="Customs Hold">Customs Hold</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Severity Level</label>
            <select name="severity" class="form-control">
              <option value="Low">Low</option>
              <option value="Medium">Medium</option>
              <option value="High">High</option>
              <option value="Critical">Critical</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Description <span style="color:red;">*</span></label>
          <textarea name="description" class="form-control" rows="4" placeholder="Describe the issue in detail — location, impact, estimated delay..." required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-danger">⚠️ Submit Exception</button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleExcDropdown(e, id) {
    e.stopPropagation();
    const el = document.getElementById(id);
    const isOpen = el.style.display === 'block';
    document.querySelectorAll('.action-dropdown').forEach(d => d.style.display = 'none');
    el.style.display = isOpen ? 'none' : 'block';
}
document.addEventListener('click', () => {
    document.querySelectorAll('.action-dropdown').forEach(d => d.style.display = 'none');
});

function filterExcept(val, field) {
  document.querySelectorAll('#exceptTable tbody tr').forEach(row => {
    if (!val) { row.style.display = ''; return; }
    const rv = row.dataset[field] || '';
    row.style.display = rv === val ? '' : 'none';
  });
}
</script>

<?php close_page(); $db->close(); ?>