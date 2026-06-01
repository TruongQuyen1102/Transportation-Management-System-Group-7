<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('operations');

$exceptions = get_exceptions();
$open     = array_filter($exceptions, fn($e) => $e['status'] === 'OPEN');
$review   = array_filter($exceptions, fn($e) => $e['status'] === 'IN_REVIEW');
$resolved = array_filter($exceptions, fn($e) => $e['status'] === 'RESOLVED');

$typeColors = [
    'Traffic Delay'     => 'yellow',
    'Damaged Goods'     => 'red',
    'Route Deviation'   => 'olive',
    'Vehicle Breakdown' => 'red',
    'Failed Delivery'   => 'red',
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

<?php if (count($open) > 0): ?>
<div class="alert alert-danger">
  <span class="alert-icon">🚨</span>
  <div class="alert-body">
    <div class="alert-title"><?= count($open) ?> Open Exception<?= count($open) > 1 ? 's' : '' ?> Need Attention</div>
    Active delivery disruptions require immediate action. Review and update all open exceptions.
  </div>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
  <div class="stat-card red">
    <div class="stat-icon" style="background:var(--c-red-bg)">🔴</div>
    <div class="stat-body">
      <div class="stat-value"><?= count($open) ?></div>
      <div class="stat-label">Open</div>
      <div class="stat-trend down">Requires action</div>
    </div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon" style="background:var(--c-yellow-bg)">🔵</div>
    <div class="stat-body">
      <div class="stat-value"><?= count($review) ?></div>
      <div class="stat-label">In Review</div>
      <div class="stat-trend neutral">Awaiting decision</div>
    </div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon" style="background:var(--c-green-bg)">✅</div>
    <div class="stat-body">
      <div class="stat-value"><?= count($resolved) ?></div>
      <div class="stat-label">Resolved</div>
      <div class="stat-trend up">This period</div>
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
      <div class="table-toolbar-left">
        <div class="search-input-wrapper">
          <span class="search-icon">🔍</span>
          <input type="text" class="form-control" placeholder="Search exceptions..." data-table-search="exceptTable" style="min-width:220px;">
        </div>
        <select class="form-control" style="font-size:12px;" onchange="filterExcept(this.value,'status')">
          <option value="">All Statuses</option>
          <option value="OPEN">Open</option>
          <option value="IN_REVIEW">In Review</option>
          <option value="RESOLVED">Resolved</option>
        </select>
        <select class="form-control" style="font-size:12px;" onchange="filterExcept(this.value,'type')">
          <option value="">All Types</option>
          <option value="Traffic Delay">Traffic Delay</option>
          <option value="Damaged Goods">Damaged Goods</option>
          <option value="Route Deviation">Route Deviation</option>
          <option value="Vehicle Breakdown">Vehicle Breakdown</option>
          <option value="Failed Delivery">Failed Delivery</option>
        </select>
      </div>
    </div>
  </div>
  <div class="table-wrapper" style="border-radius:0;border:none;box-shadow:none;">
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
        <tr data-status="<?= $exc['status'] ?>" data-type="<?= htmlspecialchars($exc['type']) ?>">
          <td><strong><?= $exc['id'] ?></strong></td>
          <td><a href="/operations/shipment_detail.php" style="font-weight:600;"><?= $exc['shipment_id'] ?></a></td>
          <td style="font-size:12px;"><?= htmlspecialchars($exc['carrier']) ?></td>
          <td>
            <span class="badge badge-<?= $typeColors[$exc['type']] ?? 'gray' ?>"><?= htmlspecialchars($exc['type']) ?></span>
          </td>
          <td style="max-width:240px;font-size:12px;" class="truncate" title="<?= htmlspecialchars($exc['desc']) ?>">
            <?= htmlspecialchars(mb_strimwidth($exc['desc'], 0, 70, '…')) ?>
          </td>
          <td><?= status_badge($exc['status']) ?></td>
          <td class="td-muted"><?= $exc['created_at'] ?></td>
          <td>
            <div class="action-menu">
              <button class="action-menu-btn" data-dropdown-toggle="exc-menu-<?= $exc['id'] ?>">⋯</button>
              <div class="action-dropdown" id="exc-menu-<?= $exc['id'] ?>">
                <button data-modal-open="detailModal-<?= $exc['id'] ?>">🔍 View Details</button>
                <?php if ($exc['status'] !== 'RESOLVED'): ?>
                  <button onclick="showToast('Updated','Exception status updated to IN REVIEW.','info')">🔵 Set In Review</button>
                  <button onclick="showToast('Resolved','Exception <?= $exc['id'] ?> marked as resolved.','success')">✅ Mark Resolved</button>
                <?php endif; ?>
                <button class="danger" data-confirm="Are you sure you want to escalate this exception?">🚨 Escalate to Manager</button>
              </div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Detail Modals -->
<?php foreach ($exceptions as $exc): ?>
<div class="modal-overlay" id="detailModal-<?= $exc['id'] ?>">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-title">⚠️ <span><?= $exc['id'] ?> — <?= htmlspecialchars($exc['type']) ?></span></div>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body">
      <div class="info-grid">
        <div class="info-row"><div class="info-label">Shipment</div><div class="info-value"><?= $exc['shipment_id'] ?></div></div>
        <div class="info-row"><div class="info-label">Carrier</div><div class="info-value"><?= htmlspecialchars($exc['carrier']) ?></div></div>
        <div class="info-row"><div class="info-label">Issue Type</div><div class="info-value"><span class="badge badge-<?= $typeColors[$exc['type']] ?? 'gray' ?>"><?= htmlspecialchars($exc['type']) ?></span></div></div>
        <div class="info-row"><div class="info-label">Status</div><div class="info-value"><?= status_badge($exc['status']) ?></div></div>
        <div class="info-row"><div class="info-label">Logged By</div><div class="info-value"><?= htmlspecialchars($exc['account']) ?></div></div>
        <div class="info-row"><div class="info-label">Created At</div><div class="info-value"><?= $exc['created_at'] ?></div></div>
      </div>
      <div class="divider"></div>
      <div class="form-group">
        <label class="form-label">Full Description</label>
        <div style="background:var(--c-neutral-100);border-radius:8px;padding:14px;font-size:13px;line-height:1.6;color:var(--text-secondary);">
          <?= htmlspecialchars($exc['desc']) ?>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Resolution Notes</label>
        <textarea class="form-control" rows="3" placeholder="Add resolution notes or follow-up actions..."></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" data-modal-close>Close</button>
      <?php if ($exc['status'] !== 'RESOLVED'): ?>
        <button class="btn btn-success" onclick="showToast('Resolved','Exception marked as resolved.','success')">✅ Mark Resolved</button>
      <?php endif; ?>
    </div>
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
    <div class="modal-body">
      <form data-feedback="Exception reported successfully. Manager has been notified.">
        <div class="form-group">
          <label class="form-label">Shipment <span class="required">*</span></label>
          <select class="form-control" required>
            <option value="">Select shipment...</option>
            <?php foreach (get_shipments() as $s): ?>
              <option value="<?= $s['id'] ?>"><?= $s['id'] ?> — <?= htmlspecialchars($s['route']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Issue Type <span class="required">*</span></label>
            <select class="form-control" required>
              <option>Traffic Delay</option>
              <option>Vehicle Breakdown</option>
              <option>Damaged Goods</option>
              <option>Route Deviation</option>
              <option>Failed Delivery</option>
              <option>Other</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Severity</label>
            <select class="form-control">
              <option>High</option>
              <option>Medium</option>
              <option>Low</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Description <span class="required">*</span></label>
          <textarea class="form-control" rows="4" placeholder="Describe the issue in detail — location, impact, estimated delay..." required></textarea>
        </div>
        <div class="modal-footer" style="padding:0;border:none;margin-top:8px;">
          <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
          <button type="submit" class="btn btn-danger">⚠️ Submit Exception</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$extraScripts = [<<<JS
function filterExcept(val, field) {
  document.querySelectorAll('#exceptTable tbody tr').forEach(row => {
    if (!val) { row.style.display = ''; return; }
    const rv = row.dataset[field] || '';
    row.style.display = rv === val ? '' : 'none';
  });
}
JS];
close_page();
?>
