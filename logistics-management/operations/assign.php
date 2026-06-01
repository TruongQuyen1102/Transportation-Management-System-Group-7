<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('operations');

$orders    = get_orders();
$assets    = get_transport_assets();
$routes    = get_routes();
$shipments = get_shipments();
$carriers  = get_carriers();

$pendingOrders = array_filter($orders, fn($o) => in_array($o['status'], ['PENDING','CONFIRMED']));
$availAssets   = array_filter($assets, fn($a) => $a['status'] === 'Available');

open_page('Assign Transport Assets', 'assign', [['label'=>'Operations'],['label'=>'Assign Assets']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Assign Transport Assets</h1>
    <p class="page-subtitle">Match pending orders with suitable carriers and transport assets</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-ghost btn-sm">📋 View All Assignments</button>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
  <div class="stat-card yellow">
    <div class="stat-icon" style="background:var(--c-yellow-bg)">📦</div>
    <div class="stat-body">
      <div class="stat-value"><?= count($pendingOrders) ?></div>
      <div class="stat-label">Orders Awaiting Assignment</div>
      <div class="stat-trend down">Needs attention</div>
    </div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon" style="background:var(--c-green-bg)">🚛</div>
    <div class="stat-body">
      <div class="stat-value"><?= count($availAssets) ?></div>
      <div class="stat-label">Assets Available</div>
      <div class="stat-trend up">Ready to assign</div>
    </div>
  </div>
  <div class="stat-card navy">
    <div class="stat-icon" style="background:rgba(12,40,64,.08)">🗺️</div>
    <div class="stat-body">
      <div class="stat-value"><?= count($routes) ?></div>
      <div class="stat-label">Available Routes</div>
      <div class="stat-trend neutral">Configured</div>
    </div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon" style="background:rgba(58,83,97,.08)">🏢</div>
    <div class="stat-body">
      <div class="stat-value"><?= count(array_filter($carriers, fn($c) => $c['status']==='active')) ?></div>
      <div class="stat-label">Active Carriers</div>
      <div class="stat-trend neutral">Ready to dispatch</div>
    </div>
  </div>
</div>

<div class="grid-2">
  <!-- Left: Pending Orders -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">📦 Orders Pending Assignment</div>
      <span class="badge badge-yellow"><?= count($pendingOrders) ?> pending</span>
    </div>
    <div style="overflow-y:auto;max-height:420px;">
      <?php foreach ($pendingOrders as $order): ?>
      <div class="activity-item" style="padding:14px 20px;border-bottom:1px solid var(--border-color);">
        <div class="activity-avatar" style="background:linear-gradient(135deg,var(--c-yellow),var(--c-olive-light));color:var(--c-navy-900);">📦</div>
        <div class="activity-body" style="flex:1;">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
            <strong><?= $order['id'] ?></strong>
            <?= status_badge($order['status']) ?>
          </div>
          <div style="font-size:12px;color:var(--text-secondary);margin-bottom:3px;">
            👤 <?= htmlspecialchars($order['customer']) ?>
          </div>
          <div style="font-size:11px;color:var(--text-muted);">
            📍 <?= htmlspecialchars(mb_strimwidth($order['delivery'],0,45,'…')) ?><br>
            ⚖️ <?= number_format($order['weight']) ?> kg &nbsp;|&nbsp;
            📅 Due: <?= $order['expected_delivery'] ?>
          </div>
        </div>
        <button class="btn btn-accent btn-sm" data-modal-open="assignModal" onclick="prefillOrder('<?= $order['id'] ?>','<?= htmlspecialchars($order['customer']) ?>','<?= $order['weight'] ?>','<?= $order['volume'] ?>')">
          🔗 Assign
        </button>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Right: Available Assets -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">🚛 Available Transport Assets</div>
      <span class="badge badge-green"><?= count($availAssets) ?> available</span>
    </div>
    <div style="overflow-y:auto;max-height:420px;">
      <?php foreach ($availAssets as $asset): ?>
      <div class="activity-item" style="padding:14px 20px;border-bottom:1px solid var(--border-color);">
        <div class="activity-avatar" style="background:var(--c-green-bg);color:var(--c-green);">🚛</div>
        <div class="activity-body" style="flex:1;">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
            <strong><?= $asset['id'] ?></strong>
            <span class="badge badge-navy" style="font-size:10px;"><?= $asset['mode'] ?></span>
          </div>
          <div style="font-size:12px;color:var(--text-secondary);margin-bottom:3px;">
            <?= htmlspecialchars($asset['type']) ?> — <span style="font-family:monospace;"><?= $asset['plate'] ?></span>
          </div>
          <div style="font-size:11px;color:var(--text-muted);">
            🏢 <?= htmlspecialchars($asset['carrier']) ?><br>
            ⚖️ Max <?= number_format($asset['max_weight']) ?> kg &nbsp;|&nbsp; 📦 <?= $asset['max_volume'] ?> m³
          </div>
        </div>
        <?= status_badge($asset['status']) ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Current Assignments -->
<div class="card mt-24">
  <div class="card-header">
    <div class="card-title">📋 Recent Assignments</div>
    <a href="/operations/shipments.php" class="btn btn-ghost btn-sm">View All Shipments →</a>
  </div>
  <div class="table-wrapper" style="border-radius:0;border:none;box-shadow:none;">
    <table>
      <thead>
        <tr>
          <th>Shipment ID</th>
          <th>Order ID</th>
          <th>Route</th>
          <th>Asset / Carrier</th>
          <th>Planned Departure</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (array_slice($shipments,0,5) as $s): ?>
        <tr>
          <td><strong><?= $s['id'] ?></strong></td>
          <td><?= $s['order_id'] ?></td>
          <td style="font-size:12px;"><?= htmlspecialchars($s['route']) ?></td>
          <td style="font-size:12px;"><?= htmlspecialchars($s['asset']) ?></td>
          <td class="td-muted"><?= $s['planned_dep'] ?></td>
          <td><?= status_badge($s['status']) ?></td>
          <td>
            <a href="/operations/shipment_detail.php" class="btn btn-ghost btn-sm">🔍 Detail</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Assign Modal -->
<div class="modal-overlay" id="assignModal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-title">🔗 <span>Assign Transport Asset</span></div>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body">
      <form data-feedback="Asset assigned successfully. Shipment created.">

        <!-- Smart Match Hint -->
        <div class="alert alert-info" id="smartHint" style="display:none;">
          <span class="alert-icon">💡</span>
          <div class="alert-body">
            <div class="alert-title">Smart Match Suggestion</div>
            <span id="hintText">Select an order to see recommendations.</span>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Order <span class="required">*</span></label>
            <select class="form-control" id="orderSelect" required onchange="updateHint()">
              <option value="">Select order...</option>
              <?php foreach ($pendingOrders as $o): ?>
                <option value="<?= $o['id'] ?>" data-weight="<?= $o['weight'] ?>" data-volume="<?= $o['volume'] ?>" data-customer="<?= htmlspecialchars($o['customer']) ?>">
                  <?= $o['id'] ?> — <?= htmlspecialchars($o['customer']) ?> (<?= number_format($o['weight']) ?>kg)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Carrier <span class="required">*</span></label>
            <select class="form-control" id="carrierSelect" required onchange="filterAssets()">
              <option value="">Select carrier...</option>
              <?php foreach ($carriers as $c): if ($c['status'] !== 'active') continue; ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (PFM: <?= $c['pfm_score'] ?>%)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Transport Asset <span class="required">*</span></label>
            <select class="form-control" id="assetSelect" required>
              <option value="">Select carrier first...</option>
              <?php foreach ($availAssets as $a): ?>
                <option value="<?= $a['id'] ?>" data-carrier="<?= $a['carrier_id'] ?>" data-weight="<?= $a['max_weight'] ?>" data-volume="<?= $a['max_volume'] ?>">
                  <?= $a['id'] ?> — <?= htmlspecialchars($a['type']) ?> (<?= $a['plate'] ?>, <?= number_format($a['max_weight']) ?>kg)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Route <span class="required">*</span></label>
            <select class="form-control" required>
              <option value="">Select route...</option>
              <?php foreach ($routes as $r): ?>
                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?> (<?= $r['distance'] ?>km)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Planned Departure <span class="required">*</span></label>
            <input type="datetime-local" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Delivery Deadline</label>
            <input type="date" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <input type="text" class="form-control" placeholder="Special instructions or remarks...">
        </div>
        <div class="modal-footer" style="padding:0;border:none;margin-top:8px;">
          <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
          <button type="submit" class="btn btn-primary">🔗 Create Assignment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
$extraScripts = [<<<JS
function prefillOrder(id, customer, weight, volume) {
  const sel = document.getElementById('orderSelect');
  if (sel) sel.value = id;
  setTimeout(updateHint, 50);
}

function updateHint() {
  const sel  = document.getElementById('orderSelect');
  const hint = document.getElementById('smartHint');
  const text = document.getElementById('hintText');
  if (!sel || !sel.value) { hint.style.display = 'none'; return; }
  const opt  = sel.options[sel.selectedIndex];
  const wt   = parseInt(opt.dataset.weight || 0);
  const vol  = parseFloat(opt.dataset.volume || 0);
  hint.style.display = 'flex';
  text.innerHTML = `Order requires <strong>${wt.toLocaleString()} kg</strong> / <strong>${vol} m³</strong>. 
    Only assets with sufficient capacity are recommended. Check "In Use" assets for conflicts.`;
}

function filterAssets() {
  const carrierId = document.getElementById('carrierSelect')?.value;
  const asel = document.getElementById('assetSelect');
  if (!asel) return;
  Array.from(asel.options).forEach(opt => {
    if (!opt.value) return;
    opt.hidden = carrierId && opt.dataset.carrier !== carrierId;
  });
  asel.value = '';
}
JS];
close_page();
?>
