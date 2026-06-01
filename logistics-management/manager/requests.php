<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('manager');

$requests = get_manager_requests();

// Stats
$stats = ['PENDING' => 0, 'APPROVED' => 0, 'REJECTED' => 0];
foreach ($requests as $r) {
    if (isset($stats[$r['status']])) $stats[$r['status']]++;
}

// Unique types and priorities for filters
$types      = array_unique(array_column($requests, 'type'));
$priorities = ['High', 'Medium', 'Low'];

open_page('Requests', 'requests', [['label' => 'Manager'], ['label' => 'Requests']]);
?>

<!-- Page Header -->
<div class="page-header">
  <div>
    <h1 class="page-title">Operations Requests</h1>
    <p class="text-muted" style="margin-top:4px;">Review and approve/reject requests submitted by operations staff</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-outline btn-sm">📥 Export</button>
  </div>
</div>

<!-- Stats Cards -->
<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
  <div class="stat-card yellow">
    <div class="stat-icon">📩</div>
    <div class="stat-value"><?= $stats['PENDING'] ?></div>
    <div class="stat-label">Pending</div>
    <div class="stat-trend">Awaiting decision</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">✅</div>
    <div class="stat-value"><?= $stats['APPROVED'] ?></div>
    <div class="stat-label">Approved</div>
    <div class="stat-trend">This period</div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon">❌</div>
    <div class="stat-value"><?= $stats['REJECTED'] ?></div>
    <div class="stat-label">Rejected</div>
    <div class="stat-trend">This period</div>
  </div>
</div>

<!-- Pending Banner -->
<?php if ($stats['PENDING'] > 0): ?>
<div class="alert alert-warning mt-16" style="display:flex; align-items:center; gap:12px;">
  <span style="font-size:20px;">⚡</span>
  <span>You have <strong><?= $stats['PENDING'] ?> pending request<?= $stats['PENDING'] > 1 ? 's' : '' ?></strong> requiring your attention. Please review and take action.</span>
</div>
<?php endif; ?>

<!-- Filter Bar -->
<div class="filter-bar mt-8">
  <div class="search-input-wrapper" style="flex:1; min-width:220px;">
    <span>🔍</span>
    <input type="text" placeholder="Search requests…" class="form-control" data-table-search="requestsTable">
  </div>
  <select class="form-control" id="reqStatusFilter" onchange="filterRequests()">
    <option value="">All Statuses</option>
    <option value="PENDING">Pending</option>
    <option value="APPROVED">Approved</option>
    <option value="REJECTED">Rejected</option>
  </select>
  <select class="form-control" id="reqTypeFilter" onchange="filterRequests()">
    <option value="">All Types</option>
    <?php foreach ($types as $t): ?>
    <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
    <?php endforeach; ?>
  </select>
  <select class="form-control" id="reqPriorityFilter" onchange="filterRequests()">
    <option value="">All Priorities</option>
    <?php foreach ($priorities as $p): ?>
    <option value="<?= $p ?>"><?= $p ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-outline btn-sm" onclick="document.getElementById('reqStatusFilter').value='';document.getElementById('reqTypeFilter').value='';document.getElementById('reqPriorityFilter').value='';filterRequests();">✕ Reset</button>
</div>

<!-- Requests Table -->
<div class="card mt-8">
  <div class="card-body" style="padding:0;">
    <div class="table-wrapper">
      <table id="requestsTable">
        <thead>
          <tr>
            <th>Request</th>
            <th>Submitted By</th>
            <th>Type</th>
            <th>Priority</th>
            <th>Description</th>
            <th>Status</th>
            <th>Date</th>
            <th style="width:50px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($requests as $req): ?>
          <?php
          $priColors = ['High' => 'red', 'Medium' => 'yellow', 'Low' => 'gray'];
          $priColor  = $priColors[$req['priority']] ?? 'gray';
          $typeColors = [
            'Schedule Adjustment' => 'blue',
            'Route Deviation'     => 'olive',
            'Special Delivery'    => 'navy',
            'Asset Replacement'   => 'yellow',
          ];
          $typeColor = $typeColors[$req['type']] ?? 'gray';
          ?>
          <tr data-status="<?= $req['status'] ?>" data-type="<?= htmlspecialchars($req['type']) ?>" data-priority="<?= $req['priority'] ?>">
            <td>
              <strong style="font-family:monospace;"><?= htmlspecialchars($req['id']) ?></strong>
            </td>
            <td>
              <strong><?= htmlspecialchars($req['submitted_by']) ?></strong>
              <div class="td-muted" style="font-size:11px; text-transform:uppercase;"><?= $req['role'] ?></div>
            </td>
            <td>
              <span class="badge badge-<?= $typeColor ?>"><?= htmlspecialchars($req['type']) ?></span>
            </td>
            <td>
              <span class="badge badge-<?= $priColor ?>"><?= $req['priority'] ?></span>
            </td>
            <td style="max-width:280px;">
              <div style="font-size:13px; line-height:1.5; color:var(--text-primary);">
                <?= htmlspecialchars(mb_substr($req['desc'], 0, 90)) ?><?= mb_strlen($req['desc']) > 90 ? '…' : '' ?>
              </div>
            </td>
            <td><?= status_badge($req['status']) ?></td>
            <td class="td-muted" style="font-size:12px; white-space:nowrap;"><?= $req['created_at'] ?></td>
            <td>
              <div class="action-menu">
                <button class="action-menu-btn btn btn-ghost btn-sm" data-dropdown-toggle="req-menu-<?= $req['id'] ?>">⋮</button>
                <div class="action-dropdown" id="req-menu-<?= $req['id'] ?>">
                  <button class="dropdown-item"
                    data-modal-open="reqDetailModal"
                    onclick="openReqDetail(<?= htmlspecialchars(json_encode($req)) ?>)">
                    🔍 View Details
                  </button>
                  <?php if ($req['status'] === 'PENDING'): ?>
                  <div class="dropdown-divider"></div>
                  <button class="dropdown-item text-success" data-confirm="Approve request <?= htmlspecialchars($req['id']) ?>?">
                    ✅ Approve
                  </button>
                  <button class="dropdown-item text-danger" data-confirm="Reject request <?= htmlspecialchars($req['id']) ?>?">
                    ❌ Reject
                  </button>
                  <?php endif; ?>
                </div>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Pagination -->
<div class="pagination mt-12">
  <button class="btn btn-ghost btn-sm" disabled>← Prev</button>
  <button class="btn btn-primary btn-sm">1</button>
  <button class="btn btn-ghost btn-sm" disabled>Next →</button>
</div>

<!-- Request Detail Modal -->
<div class="modal-overlay" id="reqDetailModal">
  <div class="modal" style="max-width:600px;">
    <div class="modal-header">
      <span class="modal-title" id="reqModalTitle">Request Details</span>
      <button class="modal-close" data-modal-close="reqDetailModal">✕</button>
    </div>
    <div class="modal-body">
      <div class="info-grid">
        <div class="info-row">
          <span class="info-label">Request ID</span>
          <span class="info-value" id="reqId">—</span>
        </div>
        <div class="info-row">
          <span class="info-label">Submitted By</span>
          <span class="info-value" id="reqSubmittedBy">—</span>
        </div>
        <div class="info-row">
          <span class="info-label">Role</span>
          <span class="info-value" id="reqRole">—</span>
        </div>
        <div class="info-row">
          <span class="info-label">Type</span>
          <span class="info-value" id="reqType">—</span>
        </div>
        <div class="info-row">
          <span class="info-label">Priority</span>
          <span class="info-value" id="reqPriority">—</span>
        </div>
        <div class="info-row">
          <span class="info-label">Status</span>
          <span class="info-value" id="reqStatus">—</span>
        </div>
        <div class="info-row">
          <span class="info-label">Submitted At</span>
          <span class="info-value" id="reqDate">—</span>
        </div>
      </div>

      <div class="form-group mt-16">
        <label class="form-label">Full Description</label>
        <div id="reqDesc" style="background:var(--bg-subtle); border:1px solid var(--border); border-radius:8px; padding:12px 16px; font-size:13px; line-height:1.6; color:var(--text-primary);">—</div>
      </div>

      <div id="reqDecisionArea" class="form-group mt-12">
        <label class="form-label">Manager Decision Notes</label>
        <textarea class="form-control" rows="3" placeholder="Optional notes to accompany your decision…"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" data-modal-close="reqDetailModal">Close</button>
      <span id="reqModalActions">
        <button class="btn btn-danger btn-sm" data-confirm="Reject this request?">❌ Reject</button>
        <button class="btn btn-success" data-confirm="Approve this request?">✅ Approve</button>
      </span>
    </div>
  </div>
</div>

<script>
function openReqDetail(req) {
  document.getElementById('reqModalTitle').textContent  = 'Request Details — ' + req.id;
  document.getElementById('reqId').textContent          = req.id;
  document.getElementById('reqSubmittedBy').textContent = req.submitted_by;
  document.getElementById('reqRole').textContent        = req.role.toUpperCase();
  document.getElementById('reqType').textContent        = req.type;
  document.getElementById('reqPriority').textContent    = req.priority;
  document.getElementById('reqStatus').textContent      = req.status.replace('_', ' ');
  document.getElementById('reqDate').textContent        = req.created_at;
  document.getElementById('reqDesc').textContent        = req.desc;

  // Show/hide action buttons for non-pending
  const actions = document.getElementById('reqModalActions');
  const decArea  = document.getElementById('reqDecisionArea');
  if (req.status !== 'PENDING') {
    actions.style.display  = 'none';
    decArea.style.display  = 'none';
  } else {
    actions.style.display  = '';
    decArea.style.display  = '';
  }
}

function filterRequests() {
  const status   = document.getElementById('reqStatusFilter').value;
  const type     = document.getElementById('reqTypeFilter').value;
  const priority = document.getElementById('reqPriorityFilter').value;

  document.querySelectorAll('#requestsTable tbody tr').forEach(function(row) {
    const rs = row.dataset.status   || '';
    const rt = row.dataset.type     || '';
    const rp = row.dataset.priority || '';
    const statusOk   = !status   || rs === status;
    const typeOk     = !type     || rt === type;
    const priorityOk = !priority || rp === priority;
    row.style.display = (statusOk && typeOk && priorityOk) ? '' : 'none';
  });
}
</script>

<?php close_page(); ?>
