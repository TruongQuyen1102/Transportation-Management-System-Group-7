<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('manager');

$exceptions = get_exceptions();

// Stats
$stats = ['OPEN' => 0, 'IN_REVIEW' => 0, 'RESOLVED' => 0];
foreach ($exceptions as $e) {
    if (isset($stats[$e['status']])) $stats[$e['status']]++;
}

// Unique types for filter
$types = array_unique(array_column($exceptions, 'type'));

open_page('Exceptions', 'exceptions', [['label' => 'Manager'], ['label' => 'Exceptions']]);
?>

<!-- Page Header -->
<div class="page-header">
  <div>
    <h1 class="page-title">Operational Exceptions</h1>
    <p class="text-muted" style="margin-top:4px;">Review and resolve shipment exceptions flagged by operations staff</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-outline btn-sm">📥 Export</button>
    <button class="btn btn-primary btn-sm" data-modal-open="addExcModal">+ Log Exception</button>
  </div>
</div>

<!-- Stats Cards -->
<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
  <div class="stat-card red">
    <div class="stat-icon">🔴</div>
    <div class="stat-value"><?= $stats['OPEN'] ?></div>
    <div class="stat-label">Open</div>
    <div class="stat-trend">Requires immediate action</div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon">🟡</div>
    <div class="stat-value"><?= $stats['IN_REVIEW'] ?></div>
    <div class="stat-label">In Review</div>
    <div class="stat-trend">Under manager review</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">🟢</div>
    <div class="stat-value"><?= $stats['RESOLVED'] ?></div>
    <div class="stat-label">Resolved</div>
    <div class="stat-trend">Closed this period</div>
  </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar mt-16">
  <div class="search-input-wrapper" style="flex:1; min-width:200px;">
    <span>🔍</span>
    <input type="text" placeholder="Search exceptions…" class="form-control" data-table-search="exceptionsTable">
  </div>
  <select class="form-control" id="statusFilter" onchange="filterExceptions()">
    <option value="">All Statuses</option>
    <option value="OPEN">Open</option>
    <option value="IN_REVIEW">In Review</option>
    <option value="RESOLVED">Resolved</option>
  </select>
  <select class="form-control" id="typeFilter" onchange="filterExceptions()">
    <option value="">All Types</option>
    <?php foreach ($types as $t): ?>
    <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-outline btn-sm" onclick="document.getElementById('statusFilter').value='';document.getElementById('typeFilter').value='';filterExceptions();">✕ Reset</button>
</div>

<!-- Exceptions Table -->
<div class="card mt-8">
  <div class="card-body" style="padding:0;">
    <div class="table-wrapper">
      <table id="exceptionsTable">
        <thead>
          <tr>
            <th>Exception</th>
            <th>Shipment</th>
            <th>Carrier</th>
            <th>Type</th>
            <th>Description</th>
            <th>Status</th>
            <th>Reported</th>
            <th style="width:50px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($exceptions as $exc): ?>
          <tr data-status="<?= $exc['status'] ?>" data-type="<?= htmlspecialchars($exc['type']) ?>">
            <td>
              <strong style="font-family:monospace;"><?= htmlspecialchars($exc['id']) ?></strong>
              <div class="td-muted" style="font-size:11px;">by <?= htmlspecialchars($exc['account']) ?></div>
            </td>
            <td>
              <strong><?= htmlspecialchars($exc['shipment_id']) ?></strong>
            </td>
            <td style="font-size:13px;"><?= htmlspecialchars($exc['carrier']) ?></td>
            <td>
              <?php
              $typeColors = [
                'Traffic Delay'    => 'yellow',
                'Damaged Goods'    => 'red',
                'Route Deviation'  => 'olive',
                'Vehicle Breakdown'=> 'red',
                'Failed Delivery'  => 'red',
              ];
              $tc = $typeColors[$exc['type']] ?? 'gray';
              ?>
              <span class="badge badge-<?= $tc ?>"><?= htmlspecialchars($exc['type']) ?></span>
            </td>
            <td style="max-width:260px;">
              <div style="font-size:13px; line-height:1.5; color:var(--text-primary);">
                <?= htmlspecialchars(mb_substr($exc['desc'], 0, 80)) ?><?= mb_strlen($exc['desc']) > 80 ? '…' : '' ?>
              </div>
            </td>
            <td><?= status_badge($exc['status']) ?></td>
            <td class="td-muted" style="font-size:12px; white-space:nowrap;"><?= $exc['created_at'] ?></td>
            <td>
              <div class="action-menu">
                <button class="action-menu-btn btn btn-ghost btn-sm" data-dropdown-toggle="exc-menu-<?= $exc['id'] ?>">⋮</button>
                <div class="action-dropdown" id="exc-menu-<?= $exc['id'] ?>">
                  <button class="dropdown-item" data-modal-open="excDetailModal"
                    onclick="openExcDetail(<?= htmlspecialchars(json_encode($exc)) ?>)">
                    🔍 View Details
                  </button>
                  <?php if ($exc['status'] !== 'RESOLVED'): ?>
                  <button class="dropdown-item text-success" data-confirm="Approve resolution for <?= $exc['id'] ?>?">
                    ✅ Approve Resolution
                  </button>
                  <button class="dropdown-item text-danger" data-confirm="Reject exception <?= $exc['id'] ?>?">
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

<!-- Exception Detail Modal -->
<div class="modal-overlay" id="excDetailModal">
  <div class="modal" style="max-width:600px;">
    <div class="modal-header">
      <span class="modal-title" id="excModalTitle">Exception Details</span>
      <button class="modal-close" data-modal-close="excDetailModal">✕</button>
    </div>
    <div class="modal-body">
      <div class="info-grid">
        <div class="info-row">
          <span class="info-label">Exception ID</span>
          <span class="info-value" id="excId">—</span>
        </div>
        <div class="info-row">
          <span class="info-label">Shipment</span>
          <span class="info-value" id="excShipment">—</span>
        </div>
        <div class="info-row">
          <span class="info-label">Carrier</span>
          <span class="info-value" id="excCarrier">—</span>
        </div>
        <div class="info-row">
          <span class="info-label">Reported By</span>
          <span class="info-value" id="excAccount">—</span>
        </div>
        <div class="info-row">
          <span class="info-label">Type</span>
          <span class="info-value" id="excType">—</span>
        </div>
        <div class="info-row">
          <span class="info-label">Status</span>
          <span class="info-value" id="excStatus">—</span>
        </div>
        <div class="info-row">
          <span class="info-label">Date Reported</span>
          <span class="info-value" id="excDate">—</span>
        </div>
      </div>
      <div class="form-group mt-16">
        <label class="form-label">Full Description</label>
        <div id="excDesc" style="background:var(--bg-subtle); border:1px solid var(--border); border-radius:8px; padding:12px 16px; font-size:13px; line-height:1.6; color:var(--text-primary);">—</div>
      </div>
      <div class="form-group mt-12">
        <label class="form-label">Manager Resolution Notes</label>
        <textarea class="form-control" rows="3" placeholder="Add your review notes or resolution instructions…"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline" data-modal-close="excDetailModal">Close</button>
      <button class="btn btn-danger btn-sm" data-confirm="Reject this exception?">❌ Reject</button>
      <button class="btn btn-success" data-confirm="Approve resolution?">✅ Approve Resolution</button>
    </div>
  </div>
</div>

<!-- Add Exception Modal (for logging) -->
<div class="modal-overlay" id="addExcModal">
  <div class="modal" style="max-width:520px;">
    <div class="modal-header">
      <span class="modal-title">Log New Exception</span>
      <button class="modal-close" data-modal-close="addExcModal">✕</button>
    </div>
    <div class="modal-body">
      <form data-feedback="Exception logged successfully.">
        <div class="form-group">
          <label class="form-label">Shipment ID</label>
          <select class="form-control">
            <option>SHP001</option><option>SHP002</option><option>SHP003</option>
            <option>SHP004</option><option>SHP005</option><option>SHP006</option>
          </select>
        </div>
        <div class="form-row mt-12">
          <div class="form-group">
            <label class="form-label">Exception Type</label>
            <select class="form-control">
              <option>Traffic Delay</option>
              <option>Damaged Goods</option>
              <option>Route Deviation</option>
              <option>Vehicle Breakdown</option>
              <option>Failed Delivery</option>
              <option>Other</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Initial Status</label>
            <select class="form-control">
              <option value="OPEN">Open</option>
              <option value="IN_REVIEW">In Review</option>
            </select>
          </div>
        </div>
        <div class="form-group mt-12">
          <label class="form-label">Description</label>
          <textarea class="form-control" rows="4" placeholder="Describe the exception in detail…" required></textarea>
        </div>
        <div class="modal-footer" style="padding-bottom:0;">
          <button type="button" class="btn btn-outline" data-modal-close="addExcModal">Cancel</button>
          <button type="submit" class="btn btn-primary">Log Exception</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openExcDetail(exc) {
  document.getElementById('excModalTitle').textContent = 'Exception Details — ' + exc.id;
  document.getElementById('excId').textContent        = exc.id;
  document.getElementById('excShipment').textContent  = exc.shipment_id;
  document.getElementById('excCarrier').textContent   = exc.carrier;
  document.getElementById('excAccount').textContent   = exc.account;
  document.getElementById('excType').textContent      = exc.type;
  document.getElementById('excStatus').textContent    = exc.status.replace('_',' ');
  document.getElementById('excDate').textContent      = exc.created_at;
  document.getElementById('excDesc').textContent      = exc.desc;
}

function filterExceptions() {
  const status = document.getElementById('statusFilter').value;
  const type   = document.getElementById('typeFilter').value;
  document.querySelectorAll('#exceptionsTable tbody tr').forEach(function(row) {
    const rs = row.dataset.status || '';
    const rt = row.dataset.type  || '';
    const statusOk = !status || rs === status;
    const typeOk   = !type   || rt === type;
    row.style.display = (statusOk && typeOk) ? '' : 'none';
  });
}
</script>

<?php close_page(); ?>
