<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/db.php';
auth_require('operations');

$db = get_db();

// ══════════════════════════════════════════════════════════════════════════════
//  ONE-TIME MIGRATION: add Status + TransportMode columns if missing
// ══════════════════════════════════════════════════════════════════════════════
$db->query("ALTER TABLE transport_asset
    ADD COLUMN IF NOT EXISTS `Status` ENUM('Available','In Use','Maintenance','Inactive') NOT NULL DEFAULT 'Available',
    ADD COLUMN IF NOT EXISTS `TransportMode` VARCHAR(20) DEFAULT NULL");

// Back-fill TransportMode from carrier capabilities if NULL
$db->query("UPDATE transport_asset a
    JOIN carrier c ON a.CarrierID = c.PartyID
    SET a.TransportMode = CASE
        WHEN c.Capabilities LIKE '%Ocean%' OR a.AssetCategory = 'Vessel'  THEN 'Waterway'
        WHEN c.Capabilities LIKE '%Air%'   OR a.AssetCategory = 'Aircraft' THEN 'Air'
        ELSE 'Road'
    END
    WHERE a.TransportMode IS NULL");

$message = '';
$message_type = '';

// ══════════════════════════════════════════════════════════════════════════════
//  POST HANDLING
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── 1. ADD ASSET ─────────────────────────────────────────────────────────
    if ($action === 'add_asset') {
        $carrier_id = (int)($_POST['carrier_id'] ?? 0);
        $mode       = $_POST['transport_mode'] ?? 'Road';
        $category   = trim($_POST['asset_category'] ?? '');
        $model      = trim($_POST['vehicle_model'] ?? '');
        $plate      = trim($_POST['plate'] ?? '');
        $max_weight = (float)($_POST['max_weight'] ?? 0);
        $max_volume = (float)($_POST['max_volume'] ?? 0);
        $status     = $_POST['status'] ?? 'Available';

        if (!$carrier_id || !$category || !$model) {
            $message = 'Please fill in Carrier, Category, and Vehicle Model.';
            $message_type = 'danger';
        } else {
            $stmt = $db->prepare("INSERT INTO transport_asset
                (CarrierID, AssetCategory, VehicleModel, MaxWeight, MaxVolume, Status, TransportMode)
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('issddss', $carrier_id, $category, $model, $max_weight, $max_volume, $status, $mode);
            if ($stmt->execute()) {
                $message = 'Asset #' . $db->insert_id . ' added successfully!';
                $message_type = 'success';
            } else {
                $message = 'Error: ' . $db->error;
                $message_type = 'danger';
            }
        }
    }

    // ── 2. EDIT ASSET ────────────────────────────────────────────────────────
    elseif ($action === 'edit_asset') {
        $asset_id   = (int)($_POST['asset_id'] ?? 0);
        $category   = trim($_POST['asset_category'] ?? '');
        $model      = trim($_POST['vehicle_model'] ?? '');
        $plate      = trim($_POST['plate'] ?? '');
        $max_weight = (float)($_POST['max_weight'] ?? 0);
        $max_volume = (float)($_POST['max_volume'] ?? 0);
        $mode       = $_POST['transport_mode'] ?? 'Road';

        if ($asset_id) {
            $stmt = $db->prepare("UPDATE transport_asset
                SET AssetCategory=?, VehicleModel=?, MaxWeight=?, MaxVolume=?, TransportMode=?
                WHERE AssetID=?");
            $stmt->bind_param('ssddsi', $category, $model, $max_weight, $max_volume, $mode, $asset_id);
            $stmt->execute();
            $message = "Asset #$asset_id updated.";
            $message_type = 'success';
        }
    }

    // ── 3. CHANGE STATUS ─────────────────────────────────────────────────────
    elseif ($action === 'change_status') {
        $asset_id  = (int)($_POST['asset_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';

        if ($asset_id && $new_status) {
            $stmt = $db->prepare("UPDATE transport_asset SET Status=? WHERE AssetID=?");
            $stmt->bind_param('si', $new_status, $asset_id);
            $stmt->execute();
            $message = "Asset #$asset_id status updated to $new_status.";
            $message_type = 'success';
        }
    }

    // ── 4. DEACTIVATE ASSET ──────────────────────────────────────────────────
    elseif ($action === 'deactivate_asset') {
        $asset_id = (int)($_POST['asset_id'] ?? 0);
        if ($asset_id) {
            $stmt = $db->prepare("UPDATE transport_asset SET Status='Inactive' WHERE AssetID=?");
            $stmt->bind_param('i', $asset_id);
            $stmt->execute();
            $message = "Asset #$asset_id deactivated.";
            $message_type = 'success';
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  FETCH DATA
// ══════════════════════════════════════════════════════════════════════════════

// Assets with carrier name
$sql_assets = "
    SELECT a.AssetID, a.AssetCategory, a.VehicleModel, a.MaxWeight, a.MaxVolume,
           a.Status, a.TransportMode,
           bp.PartyName AS CarrierName, bp.PartyID AS CarrierID
    FROM transport_asset a
    LEFT JOIN business_party bp ON a.CarrierID = bp.PartyID
    ORDER BY a.AssetID
";
$assets = [];
$res = $db->query($sql_assets);
while ($row = $res->fetch_assoc()) { $assets[] = $row; }

// Carriers with performance data
$sql_carriers = "
    SELECT bp.PartyID, bp.PartyName, bp.ContactEmail, bp.Phone,
           c.Capabilities, c.PFM_Score, c.Note, c.ServiceArea, c.Status
    FROM business_party bp
    JOIN carrier c ON bp.PartyID = c.PartyID
    WHERE bp.PartyType = 'Carrier'
    ORDER BY c.PFM_Score DESC
";
$carriers = [];
$res_c = $db->query($sql_carriers);
while ($row = $res_c->fetch_assoc()) { $carriers[] = $row; }

// Stats
$total     = count($assets);
$available = count(array_filter($assets, fn($a) => $a['Status'] === 'Available'));
$in_use    = count(array_filter($assets, fn($a) => $a['Status'] === 'In Use'));
$maint     = count(array_filter($assets, fn($a) => $a['Status'] === 'Maintenance'));

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

<?php if ($message): ?>
<div class="alert alert-<?= $message_type ?>" style="margin-bottom:16px;">
  <?= $message_type === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="stats-grid">
  <div class="stat-card navy">
    <div class="stat-icon">🚛</div>
    <div class="stat-info">
      <div class="stat-value"><?= $total ?></div>
      <div class="stat-label">Total Assets</div>
    </div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">✅</div>
    <div class="stat-info">
      <div class="stat-value"><?= $available ?></div>
      <div class="stat-label">Available</div>
    </div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon">🔄</div>
    <div class="stat-info">
      <div class="stat-value"><?= $in_use ?></div>
      <div class="stat-label">In Use</div>
    </div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon">🔧</div>
    <div class="stat-info">
      <div class="stat-value"><?= $maint ?></div>
      <div class="stat-label">Maintenance</div>
    </div>
  </div>
</div>

<div class="filter-bar mt-16">
  <div class="search-input-wrapper" style="flex:1;">
    <span class="search-icon">🔍</span>
    <input type="text" class="form-control" placeholder="Search model, carrier, category..."
           data-table-search="assetsTable" style="width:99%;">
  </div>
  <select class="form-control" style="width:160px;" id="filterCarrier" onchange="filterAssets()">
    <option value="">All Carriers</option>
    <?php foreach ($carriers as $c): ?>
      <option><?= htmlspecialchars($c['PartyName']) ?></option>
    <?php endforeach; ?>
  </select>
  <select class="form-control" style="width:130px;" id="filterMode" onchange="filterAssets()">
    <option value="">All Modes</option>
    <option>Road</option>
    <option>Air</option>
    <option>Waterway</option>
  </select>
  <select class="form-control" style="width:130px;" id="filterStatus" onchange="filterAssets()">
    <option value="">All Statuses</option>
    <option>Available</option>
    <option>In Use</option>
    <option>Maintenance</option>
    <option>Inactive</option>
  </select>
</div>

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
          <th>Category / Model</th>
          <th>Max Weight</th>
          <th>Max Volume</th>
          <th>Status</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($assets as $a):
          $mode_badge = match($a['TransportMode']) {
            'Road'     => '<span class="badge badge-navy">🚛 Road</span>',
            'Air'      => '<span class="badge badge-blue">✈️ Air</span>',
            'Waterway' => '<span class="badge badge-olive">🚢 Waterway</span>',
            default    => '<span class="badge badge-gray">' . htmlspecialchars($a['TransportMode'] ?? '—') . '</span>'
          };
          $asset_label = 'AST' . str_pad($a['AssetID'], 3, '0', STR_PAD_LEFT);
        ?>
          <tr>
            <td><strong><?= $asset_label ?></strong></td>
            <td><?= htmlspecialchars($a['CarrierName'] ?? 'Internal') ?></td>
            <td><?= $mode_badge ?></td>
            <td>
              <div><?= htmlspecialchars($a['VehicleModel']) ?></div>
              <div class="td-muted" style="font-size:11px;"><?= htmlspecialchars($a['AssetCategory']) ?></div>
            </td>
            <td><?= number_format($a['MaxWeight'], 0) ?> kg</td>
            <td><?= number_format($a['MaxVolume'], 1) ?> m³</td>
            <td><?= status_badge($a['Status']) ?></td>
            <td style="text-align:right;">
              <div class="action-menu">
                <button class="action-menu-btn btn btn-ghost btn-sm"
                        data-dropdown-toggle="actAst<?= $a['AssetID'] ?>">⋯</button>
                <div class="action-dropdown" id="actAst<?= $a['AssetID'] ?>">
                  <a href="#" class="dropdown-item"
                     onclick="openEditAsset(<?= $a['AssetID'] ?>, '<?= htmlspecialchars(addslashes($a['AssetCategory'])) ?>', '<?= htmlspecialchars(addslashes($a['VehicleModel'])) ?>', <?= $a['MaxWeight'] ?>, <?= $a['MaxVolume'] ?>, '<?= $a['TransportMode'] ?>')">
                     ✏️ Edit</a>
                  <a href="#" class="dropdown-item"
                     onclick="openChangeStatus(<?= $a['AssetID'] ?>, '<?= $a['Status'] ?>')">
                     🔄 Change Status</a>
                  <a href="#" class="dropdown-item danger"
                     onclick="deactivateAsset(<?= $a['AssetID'] ?>)">
                     🚫 Deactivate</a>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card mt-24">
  <div class="card-header">
    <h3 class="card-title">🤝 Carrier Partners</h3>
  </div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Capabilities</th>
          <th>Service Area</th>
          <th>Performance Score</th>
          <th>Contact</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($carriers as $c):
          $score = (float)$c['PFM_Score'];
          $score_pct = round($score * 20); // 0-5 scale → 0-100%
          $color = $score >= 4.5 ? 'green' : ($score >= 3.5 ? 'yellow' : 'red');
        ?>
          <tr>
            <td><strong><?= $c['PartyID'] ?></strong></td>
            <td>
              <div class="font-bold"><?= htmlspecialchars($c['PartyName']) ?></div>
              <div class="td-muted" style="font-size:11px;"><?= htmlspecialchars($c['Note']) ?></div>
            </td>
            <td>
              <?php foreach (explode(',', $c['Capabilities']) as $cap): ?>
                <span class="badge badge-navy" style="margin-right:3px;margin-bottom:2px;"><?= trim($cap) ?></span>
              <?php endforeach; ?>
            </td>
            <td class="td-muted"><?= htmlspecialchars($c['ServiceArea']) ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px;">
                <div class="progress-bar" style="width:80px;">
                  <div class="progress-fill" style="width:<?= $score_pct ?>%;background:var(--c-<?= $color ?>);"></div>
                </div>
                <span class="badge badge-<?= $color ?>"><?= number_format($score, 1) ?></span>
              </div>
            </td>
            <td class="td-muted" style="font-size:12px;">
              📧 <?= htmlspecialchars($c['ContactEmail']) ?><br>
              📞 <?= htmlspecialchars($c['Phone']) ?>
            </td>
            <td><?= status_badge($c['Status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<form id="formDeactivate" method="POST" action="" style="display:none;">
  <input type="hidden" name="action" value="deactivate_asset">
  <input type="hidden" name="asset_id" id="deact_asset_id">
</form>

<div class="modal-overlay" id="modalAddAsset">
  <div class="modal" style="max-width:540px;">
    <div class="modal-header">
      <h3 class="modal-title">🚛 Add Transport Asset</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="modal-body">
      <form id="formAddAsset" method="POST" action="">
        <input type="hidden" name="action" value="add_asset">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Carrier <span style="color:red">*</span></label>
            <select class="form-control" name="carrier_id" required>
              <option value="">Select carrier...</option>
              <?php foreach ($carriers as $c): if ($c['Status'] === 'Active'): ?>
                <option value="<?= $c['PartyID'] ?>"><?= htmlspecialchars($c['PartyName']) ?></option>
              <?php endif; endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Transport Mode <span style="color:red">*</span></label>
            <select class="form-control" name="transport_mode">
              <option>Road</option>
              <option>Air</option>
              <option>Waterway</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Asset Category <span style="color:red">*</span></label>
            <input type="text" class="form-control" name="asset_category"
                   placeholder="e.g. Truck, Vessel, Aircraft" required>
          </div>
          <div class="form-group">
            <label class="form-label">Vehicle Model <span style="color:red">*</span></label>
            <input type="text" class="form-control" name="vehicle_model"
                   placeholder="e.g. Hino 500 15-Ton" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Max Weight (kg)</label>
            <input type="number" class="form-control" name="max_weight" placeholder="0" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Max Volume (m³)</label>
            <input type="number" class="form-control" name="max_volume" step="0.1" placeholder="0.0" min="0">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Initial Status</label>
          <select class="form-control" name="status">
            <option>Available</option>
            <option>In Use</option>
            <option>Maintenance</option>
          </select>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
      <button type="submit" form="formAddAsset" class="btn btn-primary">Add Asset</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalEditAsset">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <h3 class="modal-title">✏️ Edit Asset</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="modal-body">
      <form id="formEditAsset" method="POST" action="">
        <input type="hidden" name="action" value="edit_asset">
        <input type="hidden" name="asset_id" id="edit_asset_id">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Asset Category</label>
            <input type="text" class="form-control" name="asset_category" id="edit_category">
          </div>
          <div class="form-group">
            <label class="form-label">Vehicle Model</label>
            <input type="text" class="form-control" name="vehicle_model" id="edit_model">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Max Weight (kg)</label>
            <input type="number" class="form-control" name="max_weight" id="edit_weight">
          </div>
          <div class="form-group">
            <label class="form-label">Max Volume (m³)</label>
            <input type="number" class="form-control" name="max_volume" id="edit_volume" step="0.1">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Transport Mode</label>
          <select class="form-control" name="transport_mode" id="edit_mode">
            <option value="Road">Road</option>
            <option value="Air">Air</option>
            <option value="Waterway">Waterway</option>
          </select>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
      <button type="submit" form="formEditAsset" class="btn btn-primary">Save Changes</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalChangeStatus">
  <div class="modal" style="max-width:360px;">
    <div class="modal-header">
      <h3 class="modal-title">🔄 Change Asset Status</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="modal-body">
      <form id="formChangeStatus" method="POST" action="">
        <input type="hidden" name="action" value="change_status">
        <input type="hidden" name="asset_id" id="cs_asset_id">
        <div class="form-group">
          <label class="form-label">New Status</label>
          <select class="form-control" name="new_status" id="cs_status">
            <option value="Available">Available</option>
            <option value="In Use">In Use</option>
            <option value="Maintenance">Maintenance</option>
            <option value="Inactive">Inactive</option>
          </select>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
      <button type="submit" form="formChangeStatus" class="btn btn-primary">Update Status</button>
    </div>
  </div>
</div>

<script>
function openEditAsset(id, category, model, weight, volume, mode) {
    document.getElementById('edit_asset_id').value = id;
    document.getElementById('edit_category').value  = category;
    document.getElementById('edit_model').value     = model;
    document.getElementById('edit_weight').value    = weight;
    document.getElementById('edit_volume').value    = volume;
    const sel = document.getElementById('edit_mode');
    for (let o of sel.options) { if (o.value === mode) { o.selected = true; break; } }
    document.getElementById('modalEditAsset').classList.add('open');
}

function openChangeStatus(id, currentStatus) {
    document.getElementById('cs_asset_id').value = id;
    const sel = document.getElementById('cs_status');
    for (let o of sel.options) { if (o.value === currentStatus) { o.selected = true; break; } }
    document.getElementById('modalChangeStatus').classList.add('open');
}

function deactivateAsset(id) {
    if (!confirm('Deactivate this asset?')) return;
    document.getElementById('deact_asset_id').value = id;
    document.getElementById('formDeactivate').submit();
}

// Client-side table filtering
function filterAssets() {
    const carrier = document.getElementById('filterCarrier').value.toLowerCase();
    const mode    = document.getElementById('filterMode').value.toLowerCase();
    const status  = document.getElementById('filterStatus').value.toLowerCase();
    document.querySelectorAll('#assetsTable tbody tr').forEach(row => {
        const cells = row.querySelectorAll('td');
        const rowCarrier = cells[1]?.textContent.toLowerCase() ?? '';
        const rowMode    = cells[2]?.textContent.toLowerCase() ?? '';
        const rowStatus  = cells[6]?.textContent.toLowerCase() ?? '';
        const show = (!carrier || rowCarrier.includes(carrier))
                  && (!mode    || rowMode.includes(mode))
                  && (!status  || rowStatus.includes(status));
        row.style.display = show ? '' : 'none';
    });
}
</script>

<?php close_page(); $db->close(); ?>