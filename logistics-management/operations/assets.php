<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('operations');

$assets   = get_transport_assets();
$carriers = get_carriers();

$total     = count($assets);
$available = count(array_filter($assets, fn($a) => $a['status']==='Available'));
$in_use    = count(array_filter($assets, fn($a) => $a['status']==='In Use'));
$maint     = count(array_filter($assets, fn($a) => $a['status']==='Maintenance'));

open_page('Transport Assets', 'assets', [['label'=>'Operations'],['label'=>'Transport Assets']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Transport Assets</h1>
    <p class="text-muted" style="margin-top:4px;"><?= $total ?> assets across <?= count($carriers) ?> carriers</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-primary" data-modal-open="modalAddAsset">+ Add Asset</button>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card navy">
    <div class="stat-icon">🚛</div>
    <div class="stat-value"><?= $total ?></div>
    <div class="stat-label">Total Assets</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">✅</div>
    <div class="stat-value"><?= $available ?></div>
    <div class="stat-label">Available</div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon">🔄</div>
    <div class="stat-value"><?= $in_use ?></div>
    <div class="stat-label">In Use</div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon">🔧</div>
    <div class="stat-value"><?= $maint ?></div>
    <div class="stat-label">Maintenance</div>
  </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar mt-16">
  <div class="search-input-wrapper">
    <span>🔍</span>
    <input type="text" class="form-control" placeholder="Search asset ID, carrier, plate..." data-table-search="assetsTable">
  </div>
  <select class="form-control" style="width:160px;">
    <option value="">All Carriers</option>
    <?php foreach ($carriers as $c): ?>
      <option><?= htmlspecialchars($c['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select class="form-control" style="width:130px;">
    <option value="">All Modes</option>
    <option>Road</option>
    <option>Air</option>
    <option>Waterway</option>
  </select>
  <select class="form-control" style="width:130px;">
    <option value="">All Statuses</option>
    <option>Available</option>
    <option>In Use</option>
    <option>Maintenance</option>
    <option>Inactive</option>
  </select>
</div>

<!-- Assets Table -->
<div class="card mt-12">
  <div class="card-header">
    <h3 class="card-title">Fleet Assets</h3>
  </div>
  <div class="table-wrapper">
    <table id="assetsTable">
      <thead>
        <tr>
          <th>Asset ID</th>
          <th>Carrier</th>
          <th>Mode</th>
          <th>Type</th>
          <th>Plate / ID</th>
          <th>Max Weight</th>
          <th>Max Volume</th>
          <th>Status</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($assets as $a): ?>
          <tr>
            <td><strong><?= $a['id'] ?></strong></td>
            <td><?= htmlspecialchars($a['carrier']) ?></td>
            <td>
              <?php
                $mode_badge = match($a['mode']) {
                  'Road'     => '<span class="badge badge-navy">🚛 Road</span>',
                  'Air'      => '<span class="badge badge-blue">✈️ Air</span>',
                  'Waterway' => '<span class="badge badge-olive">🚢 Waterway</span>',
                  default    => '<span class="badge badge-gray">' . $a['mode'] . '</span>'
                };
                echo $mode_badge;
              ?>
            </td>
            <td><?= htmlspecialchars($a['type']) ?></td>
            <td class="td-muted"><?= htmlspecialchars($a['plate']) ?></td>
            <td><?= fmt_num($a['max_weight']) ?> kg</td>
            <td><?= $a['max_volume'] ?> m³</td>
            <td><?= status_badge($a['status']) ?></td>
            <td style="text-align:right;">
              <div class="action-menu">
                <button class="action-menu-btn btn btn-ghost btn-sm" data-dropdown-toggle="actAst<?= $a['id'] ?>">⋯</button>
                <div class="action-dropdown" id="actAst<?= $a['id'] ?>">
                  <a href="#" class="dropdown-item" data-modal-open="modalEditAsset">✏️ Edit</a>
                  <a href="#" class="dropdown-item" data-modal-open="modalChangeStatus">🔄 Change Status</a>
                  <a href="#" class="dropdown-item danger" data-confirm="Deactivate this asset?">🚫 Deactivate</a>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Carriers Section -->
<div class="card mt-24">
  <div class="card-header">
    <h3 class="card-title">🤝 Carrier Partners</h3>
  </div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>Carrier ID</th>
          <th>Name</th>
          <th>Capabilities</th>
          <th>Service Area</th>
          <th>Performance Score</th>
          <th>Contact</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($carriers as $c): ?>
          <tr>
            <td><strong><?= $c['id'] ?></strong></td>
            <td>
              <div class="font-bold"><?= htmlspecialchars($c['name']) ?></div>
              <div class="td-muted" style="font-size:11px;"><?= htmlspecialchars($c['note']) ?></div>
            </td>
            <td>
              <?php foreach (explode(',', $c['capabilities']) as $cap): ?>
                <span class="badge badge-navy" style="margin-right:3px;"><?= trim($cap) ?></span>
              <?php endforeach; ?>
            </td>
            <td class="td-muted"><?= htmlspecialchars($c['service_area']) ?></td>
            <td>
              <?php
                $score = $c['pfm_score'];
                $color = $score >= 90 ? 'green' : ($score >= 75 ? 'yellow' : 'red');
              ?>
              <div style="display:flex;align-items:center;gap:8px;">
                <div class="progress-bar" style="width:80px;">
                  <div class="progress-fill" style="width:<?= $score ?>%;background:var(--c-<?= $color ?>);"></div>
                </div>
                <span class="badge badge-<?= $color ?>"><?= $score ?></span>
              </div>
            </td>
            <td class="td-muted" style="font-size:12px;">
              📧 <?= htmlspecialchars($c['email']) ?><br>
              📞 <?= htmlspecialchars($c['phone']) ?>
            </td>
            <td><?= status_badge($c['status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Add Asset -->
<div class="modal-overlay" id="modalAddAsset">
  <div class="modal" style="max-width:540px;">
    <div class="modal-header">
      <h3 class="modal-title">🚛 Add Transport Asset</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="modal-body">
      <form data-feedback="Asset added successfully!">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Carrier *</label>
            <select class="form-control">
              <?php foreach ($carriers as $c): if ($c['status']==='active'): ?>
                <option><?= htmlspecialchars($c['name']) ?></option>
              <?php endif; endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Transport Mode *</label>
            <select class="form-control">
              <option>Road</option>
              <option>Air</option>
              <option>Waterway</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Asset Type</label>
            <input type="text" class="form-control" placeholder="e.g. 40ft Container Truck">
          </div>
          <div class="form-group">
            <label class="form-label">Plate / Registration ID</label>
            <input type="text" class="form-control" placeholder="e.g. 51C-12345">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Max Weight (kg)</label>
            <input type="number" class="form-control" placeholder="0">
          </div>
          <div class="form-group">
            <label class="form-label">Max Volume (m³)</label>
            <input type="number" class="form-control" step="0.1" placeholder="0.0">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Initial Status</label>
          <select class="form-control">
            <option>Available</option>
            <option>In Use</option>
            <option>Maintenance</option>
          </select>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline modal-close">Cancel</button>
      <button class="btn btn-primary">Add Asset</button>
    </div>
  </div>
</div>

<!-- Modal: Edit Asset -->
<div class="modal-overlay" id="modalEditAsset">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <h3 class="modal-title">✏️ Edit Asset</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="modal-body">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Asset Type</label>
          <input type="text" class="form-control" value="40ft Container Truck">
        </div>
        <div class="form-group">
          <label class="form-label">Plate / ID</label>
          <input type="text" class="form-control" value="51C-12345">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Max Weight (kg)</label>
          <input type="number" class="form-control" value="20000">
        </div>
        <div class="form-group">
          <label class="form-label">Max Volume (m³)</label>
          <input type="number" class="form-control" value="67.0">
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline modal-close">Cancel</button>
      <button class="btn btn-primary">Save Changes</button>
    </div>
  </div>
</div>

<!-- Modal: Change Status -->
<div class="modal-overlay" id="modalChangeStatus">
  <div class="modal" style="max-width:360px;">
    <div class="modal-header">
      <h3 class="modal-title">🔄 Change Asset Status</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">New Status</label>
        <select class="form-control">
          <option>Available</option>
          <option>In Use</option>
          <option>Maintenance</option>
          <option>Inactive</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Reason / Notes</label>
        <textarea class="form-control" rows="2"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline modal-close">Cancel</button>
      <button class="btn btn-primary">Update Status</button>
    </div>
  </div>
</div>

<?php close_page(); ?>
