<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/db.php';
auth_require('manager');

$db   = get_db();
$user = current_user();
$accountId = (int) preg_replace('/\D+/', '', (string)($user['id'] ?? '2'));
$accountId = $accountId > 0 ? $accountId : 2;

// Determine which tab is active: 'exceptions' (all) or 'requests' (not processed)
$activeTab = $_GET['tab'] ?? 'exceptions';
$activeTab = in_array($activeTab, ['exceptions', 'requests'], true) ? $activeTab : 'exceptions';

// --------------------------------------------------------------------------
// Helpers — used for both tabs
// --------------------------------------------------------------------------
function exc_redirect(string $tab = 'exceptions'): void {
    header('Location: ' . BASE_URL . '/manager/exceptions.php?tab=' . $tab);
    exit;
}

function exc_flash(string $type, string $msg): void {
    $_SESSION['exc_flash'] = ['type' => $type, 'message' => $msg];
}

function exc_rows(mysqli $db, string $sql): array {
    $rows = [];
    $res  = $db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) $rows[] = $row;
    }
    return $rows;
}

function exc_badge(string $value): string {
    $map = [
        'approved'             => 'green',
        'resolved'             => 'green',
        'low'                  => 'gray',
        'pending'              => 'yellow',
        'medium'               => 'yellow',
        'under investigation'  => 'olive',
        'rejected'             => 'red',
        'high'                 => 'red',
        'critical'             => 'red',
    ];
    $color = $map[strtolower(trim($value))] ?? 'gray';
    return '<span class="badge badge-' . $color . '">' . htmlspecialchars($value) . '</span>';
}

function exc_audit(mysqli $db, int $accountId, string $action, int $recordId, string $desc): void {
    $stmt = $db->prepare("
        INSERT INTO system_audit_log (AccountID, TableName, ActionType, RecordID, ActionTime, Description)
        VALUES (?, 'Operational_Exception', ?, ?, NOW(), ?)
    ");
    if ($stmt) {
        $stmt->bind_param('isis', $accountId, $action, $recordId, $desc);
        $stmt->execute();
        $stmt->close();
    }
}

// --------------------------------------------------------------------------
// POST Handler
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']  ?? '';
    $tab     = $_POST['tab']     ?? 'exceptions';

    // ── Create new exception ──────────────────────────────────────────────────
    if ($action === 'create') {
        $shipmentId  = (int)($_POST['shipment_id']    ?? 0);
        $issueType   = trim($_POST['issue_type']      ?? '');
        $severity    = trim($_POST['severity']        ?? 'Medium');
        $status      = trim($_POST['approval_status'] ?? 'Pending');
        $description = trim($_POST['description']     ?? '');

        if ($shipmentId <= 0 || $issueType === '' || $description === '') {
            exc_flash('danger', 'Please fill in shipment, issue type, and description.');
            exc_redirect($tab);
        }

        $severity = in_array($severity, ['Low','Medium','High','Critical'], true) ? $severity : 'Medium';
        $status   = in_array($status,   ['Pending','Under Investigation'],   true) ? $status   : 'Pending';

        // Get CarrierID from shipment → transport_asset
        $carrierId = null;
        $cs = $db->prepare("SELECT ta.CarrierID FROM shipment s LEFT JOIN transport_asset ta ON ta.AssetID = s.AssetID WHERE s.ShipmentID = ? LIMIT 1");
        if ($cs) {
            $cs->bind_param('i', $shipmentId);
            $cs->execute();
            $cs->bind_result($carrierId);
            $cs->fetch();
            $cs->close();
        }

        $stmt = $db->prepare("
            INSERT INTO operational_exception (CarrierID, ShipmentID, AccountID, IssueType, Description, SeverityLevel, ApprovalStatus, CreatedAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        if (!$stmt) { exc_flash('danger', 'DB prepare error.'); exc_redirect($tab); }

        $stmt->bind_param('iiissss', $carrierId, $shipmentId, $accountId, $issueType, $description, $severity, $status);
        if ($stmt->execute()) {
            exc_audit($db, $accountId, 'CREATE', $stmt->insert_id, "Logged exception for shipment #$shipmentId");
            exc_flash('success', 'Exception logged successfully.');
        } else {
            exc_flash('danger', 'Could not save: ' . $stmt->error);
        }
        $stmt->close();
        exc_redirect($tab);
    }

    // ── Approve / Reject / Delete ──────────────────────────────────────────
    if (in_array($action, ['approve', 'reject', 'delete'], true)) {
        $exceptionId = (int)($_POST['exception_id'] ?? 0);
        if ($exceptionId <= 0) { exc_flash('danger', 'Invalid exception.'); exc_redirect($tab); }

        if ($action === 'delete') {
            $stmt = $db->prepare("DELETE FROM operational_exception WHERE ExceptionID = ?");
            $stmt->bind_param('i', $exceptionId);
            if ($stmt->execute()) {
                exc_audit($db, $accountId, 'DELETE', $exceptionId, "Deleted exception #$exceptionId");
                exc_flash('success', 'Exception deleted.');
            } else {
                exc_flash('danger', 'Could not delete: ' . $stmt->error);
            }
            $stmt->close();
            exc_redirect($tab);
        }

        $newStatus = $action === 'approve' ? 'Approved' : 'Rejected';
        $stmt = $db->prepare("UPDATE operational_exception SET ApprovalStatus = ? WHERE ExceptionID = ?");
        $stmt->bind_param('si', $newStatus, $exceptionId);
        if ($stmt->execute()) {
            exc_audit($db, $accountId, 'UPDATE', $exceptionId, "Set exception #$exceptionId → $newStatus");
            exc_flash('success', "Exception marked as $newStatus.");
        } else {
            exc_flash('danger', 'Could not update: ' . $stmt->error);
        }
        $stmt->close();
        exc_redirect($tab);
    }
}

// --------------------------------------------------------------------------
// FETCH DATA
// --------------------------------------------------------------------------
$baseQuery = "
    SELECT
        oe.ExceptionID, oe.CarrierID, oe.ShipmentID, oe.AccountID,
        oe.IssueType, oe.Description, oe.SeverityLevel, oe.ApprovalStatus, oe.CreatedAt,
        bp.PartyName  AS CarrierName,
        acc.Username,
        e.FullName
    FROM operational_exception oe
    LEFT JOIN business_party  bp  ON bp.PartyID    = oe.CarrierID
    LEFT JOIN account         acc ON acc.AccountID  = oe.AccountID
    LEFT JOIN employee        e   ON e.EmployeeID   = acc.EmployeeID
";

// Tab Exceptions: tất cả, sort mới nhất trước
$exceptions = exc_rows($db, $baseQuery . " ORDER BY oe.CreatedAt DESC, oe.ExceptionID DESC");

// Tab Requests: chỉ Pending + Under Investigation, sort theo priority
$requests = exc_rows($db, $baseQuery . "
    WHERE oe.ApprovalStatus NOT IN ('Approved', 'Rejected')
    ORDER BY FIELD(oe.SeverityLevel,'Critical','High','Medium','Low'),
             oe.CreatedAt DESC
");

// Stats tính từ tất cả exceptions
$stats = ['Pending' => 0, 'Under Investigation' => 0, 'Approved' => 0, 'Rejected' => 0];
foreach ($exceptions as $e) {
    $s = $e['ApprovalStatus'] ?? '';
    if (isset($stats[$s])) $stats[$s]++;
}

// Danh sách shipments cho form tạo mới
$shipments = exc_rows($db, "
    SELECT s.ShipmentID, s.Status, r.RouteName, bp.PartyName AS CarrierName
    FROM shipment s
    LEFT JOIN route          r  ON r.RouteID   = s.RouteID
    LEFT JOIN transport_asset ta ON ta.AssetID  = s.AssetID
    LEFT JOIN business_party  bp ON bp.PartyID  = ta.CarrierID
    ORDER BY s.ShipmentID ASC
");

$excTypes = array_values(array_unique(array_filter(array_column($exceptions, 'IssueType'))));
$flash    = $_SESSION['exc_flash'] ?? null;
unset($_SESSION['exc_flash']);

open_page('Exceptions & Requests', 'exceptions', [['label' => 'Manager'], ['label' => 'Exceptions']]);
?>

<!-- PAGE HEADER -->
<div class="page-header">
  <div>
    <h1 class="page-title">Exceptions &amp; Requests</h1>
    <p class="page-subtitle">Manage and review all operational exceptions from the database</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-primary btn-sm" data-modal-open="addExcModal">+ Log Exception</button>
  </div>
</div>

<?php if ($flash): ?>
  <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> mt-12">
    <div class="alert-body"><div class="alert-title"><?= htmlspecialchars($flash['message']) ?></div></div>
  </div>
<?php endif; ?>

<!-- STAT CARDS -->
<div class="stats-grid" style="grid-template-columns:repeat(4,minmax(160px,1fr));">
  <div class="stat-card yellow">
    <div class="stat-icon">⏳</div>
    <div class="stat-body">
      <div class="stat-value"><?= $stats['Pending'] ?></div>
      <div class="stat-label">Pending</div>
      <div class="stat-trend neutral">Awaiting decision</div>
    </div>
  </div>
  <div class="stat-card olive">
    <div class="stat-icon">🔍</div>
    <div class="stat-body">
      <div class="stat-value"><?= $stats['Under Investigation'] ?></div>
      <div class="stat-label">Under Investigation</div>
      <div class="stat-trend neutral">In manager review</div>
    </div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">✅</div>
    <div class="stat-body">
      <div class="stat-value"><?= $stats['Approved'] ?></div>
      <div class="stat-label">Approved</div>
      <div class="stat-trend neutral">Closed as approved</div>
    </div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon">❌</div>
    <div class="stat-body">
      <div class="stat-value"><?= $stats['Rejected'] ?></div>
      <div class="stat-label">Rejected</div>
      <div class="stat-trend neutral">Closed as rejected</div>
    </div>
  </div>
</div>

<?php if (($stats['Pending'] + $stats['Under Investigation']) > 0): ?>
  <div class="alert alert-warning mt-16">
    <div class="alert-body">
      <div class="alert-title">
        <?= $stats['Pending'] + $stats['Under Investigation'] ?> exception(s) require manager action.
        <?php if ($activeTab === 'exceptions'): ?>
          <a href="?tab=requests" style="font-weight:700;color:var(--c-navy-800);margin-left:8px;">View pending requests →</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- TABS -->
<div data-tab-group class="mt-16">
  <div class="tabs">
    <a href="?tab=exceptions" class="tab-btn <?= $activeTab === 'exceptions' ? 'active' : '' ?>">
      📋 All Exceptions (<?= count($exceptions) ?>)
    </a>
    <a href="?tab=requests" class="tab-btn <?= $activeTab === 'requests' ? 'active' : '' ?>">
      📩 Pending Requests (<?= count($requests) ?>)
    </a>
  </div>

  <!-- ══ TAB: ALL EXCEPTIONS ══════════════════════════════════════════════ -->
  <div id="tab-exceptions" class="tab-panel <?= $activeTab === 'exceptions' ? 'active' : '' ?>">

    <div class="filter-bar mt-16">
      <div class="search-input-wrapper" style="flex:1;min-width:220px;">
        <span class="search-icon">🔍</span>
        <input type="text" placeholder="Search exceptions..." class="form-control" data-table-search="exceptionsTable" style="padding-left:32px;width:100%;">
      </div>
      <select class="form-control" id="excStatusFilter" onchange="filterTable('exceptionsTable','excStatusFilter','excSeverityFilter','excTypeFilter')">
        <option value="">All Statuses</option>
        <option value="Pending">Pending</option>
        <option value="Under Investigation">Under Investigation</option>
        <option value="Approved">Approved</option>
        <option value="Rejected">Rejected</option>
      </select>
      <select class="form-control" id="excSeverityFilter" onchange="filterTable('exceptionsTable','excStatusFilter','excSeverityFilter','excTypeFilter')">
        <option value="">All Severities</option>
        <option value="Critical">Critical</option>
        <option value="High">High</option>
        <option value="Medium">Medium</option>
        <option value="Low">Low</option>
      </select>
      <select class="form-control" id="excTypeFilter" onchange="filterTable('exceptionsTable','excStatusFilter','excSeverityFilter','excTypeFilter')">
        <option value="">All Types</option>
        <?php foreach ($excTypes as $t): ?>
          <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-outline btn-sm" onclick="resetFilters('excStatusFilter','excSeverityFilter','excTypeFilter')">Reset</button>
    </div>

    <div class="card mt-8">
      <div class="card-body p-0">
        <div class="table-wrapper">
          <table id="exceptionsTable">
            <thead>
              <tr>
                <th>Exception</th>
                <th>Shipment</th>
                <th>Carrier</th>
                <th>Issue</th>
                <th>Severity</th>
                <th>Status</th>
                <th>Reported</th>
                <th style="width:50px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($exceptions)): ?>
                <tr><td colspan="8" class="text-center text-muted" style="padding:24px;">No exceptions found.</td></tr>
              <?php else: ?>
                <?php foreach ($exceptions as $exc):
                  $row = [
                      'id'         => 'EXC' . str_pad((string)$exc['ExceptionID'], 4, '0', STR_PAD_LEFT),
                      'raw_id'     => (int)$exc['ExceptionID'],
                      'shipment_id'=> 'SHP' . str_pad((string)$exc['ShipmentID'],  4, '0', STR_PAD_LEFT),
                      'carrier'    => $exc['CarrierName'] ?? 'Unknown',
                      'account'    => $exc['FullName'] ?: ($exc['Username'] ?? 'System'),
                      'type'       => $exc['IssueType']     ?? '',
                      'severity'   => $exc['SeverityLevel'] ?? '',
                      'status'     => $exc['ApprovalStatus']?? '',
                      'desc'       => $exc['Description']   ?? '',
                      'created_at' => $exc['CreatedAt']     ?? '',
                  ];
                ?>
                <tr data-status="<?= htmlspecialchars($row['status']) ?>"
                    data-severity="<?= htmlspecialchars($row['severity']) ?>"
                    data-type="<?= htmlspecialchars($row['type']) ?>">
                  <td>
                    <strong style="font-family:monospace;"><?= htmlspecialchars($row['id']) ?></strong>
                    <div class="td-muted font-xs">by <?= htmlspecialchars($row['account']) ?></div>
                  </td>
                  <td class="font-bold"><?= htmlspecialchars($row['shipment_id']) ?></td>
                  <td><?= htmlspecialchars($row['carrier']) ?></td>
                  <td>
                    <div class="font-medium"><?= htmlspecialchars($row['type']) ?></div>
                    <div class="td-muted truncate" style="max-width:260px;"><?= htmlspecialchars($row['desc']) ?></div>
                  </td>
                  <td><?= exc_badge($row['severity']) ?></td>
                  <td><?= exc_badge($row['status']) ?></td>
                  <td class="td-muted" style="white-space:nowrap;"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($row['created_at']))) ?></td>
                  <td>
                    <div class="action-menu">
                      <button class="action-menu-btn btn btn-ghost btn-sm" data-dropdown-toggle="exc-menu-<?= $row['raw_id'] ?>">...</button>
                      <div class="action-dropdown" id="exc-menu-<?= $row['raw_id'] ?>">
                        <button class="dropdown-item" data-modal-open="excDetailModal"
                          onclick="openExcDetail(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)">View Details</button>
                        <?php if (!in_array($row['status'], ['Approved','Rejected'], true)): ?>
                          <div class="dropdown-divider"></div>
                          <form method="post" onsubmit="return confirm('Approve this exception?');">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="exception_id" value="<?= $row['raw_id'] ?>">
                            <input type="hidden" name="tab" value="exceptions">
                            <button type="submit" class="dropdown-item text-success">✅ Approve</button>
                          </form>
                          <form method="post" onsubmit="return confirm('Reject this exception?');">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="exception_id" value="<?= $row['raw_id'] ?>">
                            <input type="hidden" name="tab" value="exceptions">
                            <button type="submit" class="dropdown-item text-danger">❌ Reject</button>
                          </form>
                        <?php endif; ?>
                        <div class="dropdown-divider"></div>
                        <form method="post" onsubmit="return confirm('Delete this exception?');">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="exception_id" value="<?= $row['raw_id'] ?>">
                          <input type="hidden" name="tab" value="exceptions">
                          <button type="submit" class="dropdown-item text-danger">🗑 Delete</button>
                        </form>
                      </div>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div><!-- /tab-exceptions -->

  <!-- ══ TAB: PENDING REQUESTS ════════════════════════════════════════════ -->
  <div id="tab-requests" class="tab-panel <?= $activeTab === 'requests' ? 'active' : '' ?>">

    <div class="filter-bar mt-16">
      <div class="search-input-wrapper" style="flex:1;min-width:220px;">
        <span class="search-icon">🔍</span>
        <input type="text" placeholder="Search requests..." class="form-control" data-table-search="requestsTable" style="padding-left:32px;width:100%;">
      </div>
      <select class="form-control" id="reqStatusFilter" onchange="filterTable('requestsTable','reqStatusFilter','reqPriorityFilter','reqTypeFilter')">
        <option value="">All Statuses</option>
        <option value="Pending">Pending</option>
        <option value="Under Investigation">Under Investigation</option>
      </select>
      <select class="form-control" id="reqPriorityFilter" onchange="filterTable('requestsTable','reqStatusFilter','reqPriorityFilter','reqTypeFilter')">
        <option value="">All Priorities</option>
        <option value="Critical">Critical</option>
        <option value="High">High</option>
        <option value="Medium">Medium</option>
        <option value="Low">Low</option>
      </select>
      <select class="form-control" id="reqTypeFilter" onchange="filterTable('requestsTable','reqStatusFilter','reqPriorityFilter','reqTypeFilter')">
        <option value="">All Types</option>
        <?php foreach ($excTypes as $t): ?>
          <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-outline btn-sm" onclick="resetFilters('reqStatusFilter','reqPriorityFilter','reqTypeFilter')">Reset</button>
    </div>

    <div class="card mt-8">
      <div class="card-body p-0">
        <div class="table-wrapper">
          <table id="requestsTable">
            <thead>
              <tr>
                <th>Request</th>
                <th>Submitted By</th>
                <th>Shipment</th>
                <th>Carrier</th>
                <th>Type</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Date</th>
                <th style="width:50px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($requests)): ?>
                <tr><td colspan="9" class="text-center text-muted" style="padding:24px;">No pending requests — all exceptions have been reviewed. ✅</td></tr>
              <?php else: ?>
                <?php foreach ($requests as $req):
                  $id = (int)$req['ExceptionID'];
                  $payload = [
                      'id'          => 'REQ' . str_pad((string)$id, 4, '0', STR_PAD_LEFT),
                      'submitted_by'=> $req['FullName'] ?: ($req['Username'] ?? 'System'),
                      'shipment'    => 'SHP' . str_pad((string)$req['ShipmentID'], 4, '0', STR_PAD_LEFT),
                      'carrier'     => $req['CarrierName'] ?? 'Unknown',
                      'type'        => $req['IssueType']      ?? '',
                      'priority'    => $req['SeverityLevel']  ?? '',
                      'status'      => $req['ApprovalStatus'] ?? '',
                      'date'        => $req['CreatedAt']      ?? '',
                      'desc'        => $req['Description']    ?? '',
                  ];
                ?>
                <tr data-status="<?= htmlspecialchars($payload['status']) ?>"
                    data-severity="<?= htmlspecialchars($payload['priority']) ?>"
                    data-type="<?= htmlspecialchars($payload['type']) ?>">
                  <td><strong style="font-family:monospace;"><?= htmlspecialchars($payload['id']) ?></strong></td>
                  <td><?= htmlspecialchars($payload['submitted_by']) ?></td>
                  <td class="font-bold"><?= htmlspecialchars($payload['shipment']) ?></td>
                  <td><?= htmlspecialchars($payload['carrier']) ?></td>
                  <td>
                    <div class="font-medium"><?= htmlspecialchars($payload['type']) ?></div>
                    <div class="td-muted truncate" style="max-width:240px;"><?= htmlspecialchars($payload['desc']) ?></div>
                  </td>
                  <td><?= exc_badge($payload['priority']) ?></td>
                  <td><?= exc_badge($payload['status']) ?></td>
                  <td class="td-muted" style="white-space:nowrap;"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($payload['date']))) ?></td>
                  <td>
                    <div class="action-menu">
                      <button class="action-menu-btn btn btn-ghost btn-sm" data-dropdown-toggle="req-menu-<?= $id ?>">...</button>
                      <div class="action-dropdown" id="req-menu-<?= $id ?>">
                        <button class="dropdown-item" data-modal-open="excDetailModal"
                          onclick="openExcDetail(<?= htmlspecialchars(json_encode([
                              'id'         => $payload['id'],
                              'shipment_id'=> $payload['shipment'],
                              'carrier'    => $payload['carrier'],
                              'account'    => $payload['submitted_by'],
                              'type'       => $payload['type'],
                              'severity'   => $payload['priority'],
                              'status'     => $payload['status'],
                              'created_at' => $payload['date'],
                              'desc'       => $payload['desc'],
                          ]), ENT_QUOTES, 'UTF-8') ?>)">View Details</button>
                        <div class="dropdown-divider"></div>
                        <form method="post" onsubmit="return confirm('Approve this request?');">
                          <input type="hidden" name="action" value="approve">
                          <input type="hidden" name="exception_id" value="<?= $id ?>">
                          <input type="hidden" name="tab" value="requests">
                          <button type="submit" class="dropdown-item text-success">✅ Approve</button>
                        </form>
                        <form method="post" onsubmit="return confirm('Reject this request?');">
                          <input type="hidden" name="action" value="reject">
                          <input type="hidden" name="exception_id" value="<?= $id ?>">
                          <input type="hidden" name="tab" value="requests">
                          <button type="submit" class="dropdown-item text-danger">❌ Reject</button>
                        </form>
                      </div>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div><!-- /tab-requests -->
</div><!-- /tab-group -->

<!-- MODAL: Exception Detail (dùng chung cho cả 2 tab) -->
<div class="modal-overlay" id="excDetailModal">
  <div class="modal" style="max-width:620px;">
    <div class="modal-header">
      <span class="modal-title" id="excModalTitle">Exception Details</span>
      <button class="modal-close" data-modal-close="excDetailModal">✕</button>
    </div>
    <div class="modal-body">
      <div class="info-grid">
        <div class="info-row"><span class="info-label">ID</span><span class="info-value" id="excId">-</span></div>
        <div class="info-row"><span class="info-label">Shipment</span><span class="info-value" id="excShipment">-</span></div>
        <div class="info-row"><span class="info-label">Carrier</span><span class="info-value" id="excCarrier">-</span></div>
        <div class="info-row"><span class="info-label">Reported By</span><span class="info-value" id="excAccount">-</span></div>
        <div class="info-row"><span class="info-label">Issue Type</span><span class="info-value" id="excType">-</span></div>
        <div class="info-row"><span class="info-label">Severity</span><span class="info-value" id="excSeverity">-</span></div>
        <div class="info-row"><span class="info-label">Status</span><span class="info-value" id="excStatus">-</span></div>
        <div class="info-row"><span class="info-label">Reported At</span><span class="info-value" id="excDate">-</span></div>
      </div>
      <div class="form-group mt-16">
        <label class="form-label">Full Description</label>
        <div id="excDesc" style="background:var(--c-neutral-100);border:1px solid var(--border-color);border-radius:8px;padding:12px 16px;font-size:13px;line-height:1.6;color:var(--text-primary);">-</div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" data-modal-close="excDetailModal">Close</button>
    </div>
  </div>
</div>

<!-- MODAL: Log New Exception -->
<div class="modal-overlay" id="addExcModal">
  <div class="modal" style="max-width:560px;">
    <div class="modal-header">
      <span class="modal-title">Log New Exception</span>
      <button class="modal-close" data-modal-close="addExcModal">✕</button>
    </div>
    <div class="modal-body">
      <form method="post">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
        <div class="form-group">
          <label class="form-label">Shipment *</label>
          <select class="form-control" name="shipment_id" required>
            <option value="">Select shipment</option>
            <?php foreach ($shipments as $s): ?>
              <option value="<?= (int)$s['ShipmentID'] ?>">
                SHP<?= str_pad((string)$s['ShipmentID'], 4, '0', STR_PAD_LEFT) ?>
                — <?= htmlspecialchars($s['RouteName']   ?? 'No route') ?>
                — <?= htmlspecialchars($s['CarrierName'] ?? 'No carrier') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row mt-12">
          <div class="form-group">
            <label class="form-label">Issue Type *</label>
            <select class="form-control" name="issue_type" required>
              <option>Weather Delay</option>
              <option>Customs Hold</option>
              <option>Vehicle Breakdown</option>
              <option>Port Congestion</option>
              <option>Documentation Error</option>
              <option>Damaged Goods</option>
              <option>Lost in Transit</option>
              <option>Temperature Deviation</option>
              <option>Other</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Severity *</label>
            <select class="form-control" name="severity" required>
              <option>Medium</option>
              <option>Low</option>
              <option>High</option>
              <option>Critical</option>
            </select>
          </div>
        </div>
        <div class="form-group mt-12">
          <label class="form-label">Initial Status</label>
          <select class="form-control" name="approval_status">
            <option>Pending</option>
            <option>Under Investigation</option>
          </select>
        </div>
        <div class="form-group mt-12">
          <label class="form-label">Description *</label>
          <textarea class="form-control" name="description" rows="4" placeholder="Describe the exception in detail..." required></textarea>
        </div>
        <div class="modal-footer" style="padding-bottom:0;">
          <button type="button" class="btn btn-outline" data-modal-close="addExcModal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save to Database</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Mở modal detail — dùng chung cho cả 2 tab
function openExcDetail(exc) {
    document.getElementById('excModalTitle').textContent = 'Exception Details — ' + exc.id;
    document.getElementById('excId').textContent       = exc.id;
    document.getElementById('excShipment').textContent = exc.shipment_id || exc.shipment || '-';
    document.getElementById('excCarrier').textContent  = exc.carrier;
    document.getElementById('excAccount').textContent  = exc.account;
    document.getElementById('excType').textContent     = exc.type;
    document.getElementById('excSeverity').textContent = exc.severity || exc.priority || '-';
    document.getElementById('excStatus').textContent   = exc.status;
    document.getElementById('excDate').textContent     = exc.created_at || exc.date || '-';
    document.getElementById('excDesc').textContent     = exc.desc;
}

// Filter table bằng 3 select (status/severity/type)
function filterTable(tableId, s1, s2, s3) {
    const v1 = document.getElementById(s1)?.value || '';
    const v2 = document.getElementById(s2)?.value || '';
    const v3 = document.getElementById(s3)?.value || '';
    document.querySelectorAll('#' + tableId + ' tbody tr').forEach(function(row) {
        const ok = (!v1 || row.dataset.status   === v1)
                && (!v2 || row.dataset.severity === v2)
                && (!v3 || row.dataset.type     === v3);
        row.style.display = ok ? '' : 'none';
    });
}

// Reset tất cả filter của 1 tab
function resetFilters(s1, s2, s3) {
    [s1, s2, s3].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    // Re-trigger filter
    const tableId = s1.startsWith('exc') ? 'exceptionsTable' : 'requestsTable';
    filterTable(tableId, s1, s2, s3);
}
</script>

<?php
close_page();
$db->close();
?>