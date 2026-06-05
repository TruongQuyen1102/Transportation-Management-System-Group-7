<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/db.php';
auth_require('operations');

$db = get_db();
$message = '';
$message_type = '';
$current_account_id = $_SESSION['account_id'] ?? 1;

// ══════════════════════════════════════════════════════════════════════════════
//  POST HANDLING
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── 1. ADD NEW SKU ────────────────────────────────────────────────────────
    if ($action === 'add_sku') {
        $sku_name = trim($_POST['sku_name'] ?? '');
        $uom      = $_POST['uom'] ?? 'Unit';
        $qty      = (int)($_POST['qty'] ?? 0);

        if (!$sku_name) {
            $message = 'SKU Name is required.';
            $message_type = 'danger';
        } else {
            $stmt = $db->prepare("INSERT INTO sku (SKUName, UOM, Quantity) VALUES (?, ?, ?)");
            $stmt->bind_param('ssi', $sku_name, $uom, $qty);
            if ($stmt->execute()) {
                $new_sku_id = $db->insert_id;
                // Log the initial stock-in if qty > 0
                if ($qty > 0) {
                    $now = date('Y-m-d H:i:s');
                    $stmt_log = $db->prepare("INSERT INTO inventory_log (SKUID, AccountID, Quantity_Changed, Action, Movement_At) VALUES (?, ?, ?, 'IN', ?)");
                    $stmt_log->bind_param('iiis', $new_sku_id, $current_account_id, $qty, $now);
                    $stmt_log->execute();
                }
                $message = "SKU \"$sku_name\" added successfully!";
                $message_type = 'success';
            } else {
                $message = 'Error adding SKU: ' . $db->error;
                $message_type = 'danger';
            }
        }
    }

    // ── 2. ADJUST STOCK ───────────────────────────────────────────────────────
    elseif ($action === 'adjust_stock') {
        $sku_id    = (int)($_POST['sku_id'] ?? 0);
        $adj_type  = $_POST['adj_type'] ?? '';
        $adj_qty   = (int)($_POST['adj_qty'] ?? 0);
        $now       = date('Y-m-d H:i:s');

        if (!$sku_id || $adj_qty <= 0) {
            $message = 'Invalid SKU or quantity.';
            $message_type = 'danger';
        } else {
            // Determine new quantity and log action
            if ($adj_type === 'add') {
                $db->query("UPDATE sku SET Quantity = Quantity + $adj_qty WHERE SKUID = $sku_id");
                $log_action = 'IN';
                $log_qty    = $adj_qty;
            } elseif ($adj_type === 'remove') {
                // Prevent negative stock
                $cur = $db->query("SELECT Quantity FROM sku WHERE SKUID = $sku_id")->fetch_assoc();
                $actual_remove = min($adj_qty, (int)$cur['Quantity']);
                $db->query("UPDATE sku SET Quantity = Quantity - $actual_remove WHERE SKUID = $sku_id");
                $log_action = 'OUT';
                $log_qty    = $actual_remove;
            } elseif ($adj_type === 'set') {
                $db->query("UPDATE sku SET Quantity = $adj_qty WHERE SKUID = $sku_id");
                $log_action = 'IN';
                $log_qty    = $adj_qty;
            }

            // Write to inventory_log
            $stmt_log = $db->prepare("INSERT INTO inventory_log (SKUID, AccountID, Quantity_Changed, Action, Movement_At) VALUES (?, ?, ?, ?, ?)");
            $stmt_log->bind_param('iiiss', $sku_id, $current_account_id, $log_qty, $log_action, $now);
            $stmt_log->execute();

            $message = "Stock adjusted successfully!";
            $message_type = 'success';
        }
    }

    // ── 3. EDIT SKU ───────────────────────────────────────────────────────────
    elseif ($action === 'edit_sku') {
        $sku_id   = (int)($_POST['sku_id'] ?? 0);
        $sku_name = trim($_POST['sku_name'] ?? '');
        $uom      = $_POST['uom'] ?? 'Unit';

        if (!$sku_id || !$sku_name) {
            $message = 'Invalid data.';
            $message_type = 'danger';
        } else {
            $stmt = $db->prepare("UPDATE sku SET SKUName = ?, UOM = ? WHERE SKUID = ?");
            $stmt->bind_param('ssi', $sku_name, $uom, $sku_id);
            $stmt->execute();
            $message = "SKU updated successfully!";
            $message_type = 'success';
        }
    }

    // ── 4. REMOVE SKU ─────────────────────────────────────────────────────────
    elseif ($action === 'remove_sku') {
        $sku_id = (int)($_POST['sku_id'] ?? 0);
        if ($sku_id) {
            $db->query("DELETE FROM inventory_log WHERE SKUID = $sku_id");
            $db->query("DELETE FROM sku WHERE SKUID = $sku_id");
            $message = "SKU removed.";
            $message_type = 'success';
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  FETCH DATA FROM DB
// ══════════════════════════════════════════════════════════════════════════════

// 1. SKUs — join with warehouse_zone to get threshold for stock-level calculation
$skus = [];
$res_skus = $db->query("
    SELECT s.SKUID, s.SKUName, s.UOM, s.Quantity,
           MAX(wz.Threshold) AS Threshold
    FROM sku s
    LEFT JOIN warehouse_zone wz ON wz.ItemID = s.SKUID
    GROUP BY s.SKUID, s.SKUName, s.UOM, s.Quantity
    ORDER BY s.SKUID ASC
");
while ($row = $res_skus->fetch_assoc()) {
    // Stock status: Low if qty <= 20% of threshold, else Adequate
    $threshold = $row['Threshold'] ? (int)$row['Threshold'] : 500;
    $row['StockStatus'] = ($row['Quantity'] <= $threshold * 0.2) ? 'Low' : 'Adequate';
    $row['Threshold']   = $threshold;
    $skus[] = $row;
}

// 2. Warehouses
$warehouses = [];
$res_wh = $db->query("SELECT * FROM warehouse ORDER BY WarehouseID");
while ($row = $res_wh->fetch_assoc()) { $warehouses[] = $row; }

// 3. Warehouse zones count per warehouse
$zone_counts = [];
$res_zones = $db->query("SELECT WarehouseID, COUNT(*) AS ZoneCount FROM warehouse_zone GROUP BY WarehouseID");
while ($row = $res_zones->fetch_assoc()) {
    $zone_counts[$row['WarehouseID']] = $row['ZoneCount'];
}

// Stats
$total_skus  = count($skus);
$low_stock   = count(array_filter($skus, fn($s) => $s['StockStatus'] === 'Low'));
$total_units = array_sum(array_column($skus, 'Quantity'));
$wh_count    = count($warehouses);
$low_items   = array_filter($skus, fn($s) => $s['StockStatus'] === 'Low');

open_page('Inventory', 'inventory', [['label'=>'Operations'],['label'=>'Inventory']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Inventory Management</h1>
    <p class="text-muted" style="margin-top:4px;">SKU stock levels across all warehouses</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-primary" data-modal-open="modalAddSku">+ Add SKU</button>
  </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $message_type ?>" style="margin-bottom:16px;">
  <?= $message_type === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<!-- Low Stock Alert -->
<?php if ($low_stock > 0): ?>
  <div class="alert alert-warning mt-8" style="display:flex;align-items:center;gap:12px;">
    <span style="font-size:20px;">⚠️</span>
    <div>
      <strong><?= $low_stock ?> SKU(s) with Low Stock</strong> —
      <?= implode(', ', array_map(fn($s) => htmlspecialchars($s['SKUName']), $low_items)) ?>
      require immediate restocking attention.
    </div>
  </div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid mt-12">
  <div class="stat-card navy">
    <div class="stat-icon">📦</div>
    <div class="stat-info">
      <div class="stat-value"><?= $total_skus ?></div>
      <div class="stat-label">Total SKUs</div>
    </div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon">⚠️</div>
    <div class="stat-info">
      <div class="stat-value"><?= $low_stock ?></div>
      <div class="stat-label">Low Stock SKUs</div>
    </div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon">🏭</div>
    <div class="stat-info">
      <div class="stat-value"><?= $wh_count ?></div>
      <div class="stat-label">Warehouses</div>
    </div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">🔢</div>
    <div class="stat-info">
      <div class="stat-value"><?= number_format($total_units) ?></div>
      <div class="stat-label">Total Units</div>
    </div>
  </div>
</div>

<style>
  .stat-card { display: flex; flex-direction: row; align-items: center; gap: 16px; }
  .stat-info  { display: flex; flex-direction: column; }
</style>

<!-- Filter Bar -->
<div class="filter-bar mt-16">
  <div class="search-input-wrapper" style="flex:1;">
    <span class="search-icon">🔍</span>
    <input type="text" class="form-control" placeholder="Search SKU ID or name..."
           data-table-search="inventoryTable" style="width:99%;">
  </div>
  <select class="form-control" style="width:160px;" id="filterStock" onchange="filterInventory()">
    <option value="">All Stock Status</option>
    <option value="Adequate">Adequate</option>
    <option value="Low">Low</option>
  </select>
</div>

<!-- SKU Table -->
<div class="card mt-12">
  <div class="card-header">
    <h3 class="card-title">SKU Inventory</h3>
  </div>
  <div class="table-wrapper">
    <table id="inventoryTable">
      <thead>
        <tr>
          <th>SKU ID</th>
          <th>Name</th>
          <th>UOM</th>
          <th>Quantity</th>
          <th style="min-width:130px;">Stock Level</th>
          <th>Status</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($skus as $sku): ?>
          <?php
            $pct       = $sku['Threshold'] > 0 ? min(100, round($sku['Quantity'] / $sku['Threshold'] * 100)) : 0;
            $bar_color = $sku['StockStatus'] === 'Low' ? 'var(--c-red)' : 'var(--c-green)';
            $row_bg    = $sku['StockStatus'] === 'Low' ? 'background:rgba(192,57,43,0.04);' : '';
          ?>
          <tr style="<?= $row_bg ?>" data-stock="<?= $sku['StockStatus'] ?>">
            <td><strong>SKU-<?= str_pad($sku['SKUID'], 3, '0', STR_PAD_LEFT) ?></strong></td>
            <td>
              <?= htmlspecialchars($sku['SKUName']) ?>
              <?php if ($sku['StockStatus'] === 'Low'): ?>
                <span class="badge badge-red" style="margin-left:6px;">Low Stock</span>
              <?php endif; ?>
            </td>
            <td class="td-muted"><?= htmlspecialchars($sku['UOM']) ?></td>
            <td><strong><?= number_format($sku['Quantity']) ?></strong></td>
            <td>
              <div class="progress-bar">
                <div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $bar_color ?>;"></div>
              </div>
              <div style="font-size:10px;color:var(--c-slate-500);margin-top:2px;"><?= $pct ?>% of <?= number_format($sku['Threshold']) ?></div>
            </td>
            <td><?= status_badge($sku['StockStatus']) ?></td>
            <td style="text-align:right;">
              <div class="action-menu" style="position:relative;display:inline-block;">
                <button class="action-menu-btn btn btn-ghost btn-sm"
                        onclick="toggleSkuDropdown(event, 'actSku<?= $sku['SKUID'] ?>')">⋯</button>
                <div class="action-dropdown" id="actSku<?= $sku['SKUID'] ?>"
                     style="display:none;position:absolute;right:0;top:100%;z-index:999;min-width:160px;background:#fff;border:1px solid #e2e8f0;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.12);padding:4px 0;">
                  <a href="#" class="dropdown-item"
                     onclick='event.stopPropagation(); openAdjustStock(<?= (int)$sku['SKUID'] ?>, <?= htmlspecialchars(json_encode($sku['SKUName']), ENT_QUOTES, "UTF-8") ?>, <?= (float)($sku['Quantity'] ?? 0) ?>); return false;'>
                     📦 Adjust Stock
                  </a>
                  <a href="#" class="dropdown-item"
                     onclick='event.stopPropagation(); openEditSku(<?= (int)$sku['SKUID'] ?>, <?= htmlspecialchars(json_encode($sku['SKUName']), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode($sku['UOM']), ENT_QUOTES, "UTF-8") ?>); return false;'>
                     ✏️ Edit SKU
                  </a>
                  <a href="#" class="dropdown-item danger"
                     onclick='event.stopPropagation(); confirmRemove(<?= (int)$sku['SKUID'] ?>, <?= htmlspecialchars(json_encode($sku['SKUName']), ENT_QUOTES, "UTF-8") ?>); return false;'>
                     🗑️ Remove
                  </a>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Warehouse Summary Cards -->
<div style="margin-top:24px;">
  <h3 style="font-size:16px;font-weight:700;margin-bottom:14px;">🏭 Warehouse Summary</h3>
  <div class="grid-3">
    <?php foreach ($warehouses as $wh): ?>
      <?php
        $type_badge = match($wh['WarehouseType']) {
          'Bonded Warehouse'    => 'navy',
          'Distribution Center' => 'blue',
          'Cross-dock'          => 'olive',
          'Fulfillment Center'  => 'green',
          default               => 'slate'
        };
        $zones = $zone_counts[$wh['WarehouseID']] ?? 0;
      ?>
      <div class="card">
        <div class="card-header">
          <h3 class="card-title" style="font-size:14px;"><?= htmlspecialchars($wh['LocationName']) ?></h3>
          <span class="badge badge-<?= $type_badge ?>"><?= htmlspecialchars($wh['WarehouseType']) ?></span>
        </div>
        <div class="card-body">
          <div class="info-grid">
            <div class="info-row">
              <span class="info-label">ID</span>
              <span class="info-value">WH-<?= str_pad($wh['WarehouseID'], 2, '0', STR_PAD_LEFT) ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Address</span>
              <span class="info-value" style="font-size:12px;"><?= htmlspecialchars($wh['Address']) ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Zones</span>
              <span class="info-value"><?= $zones ?> zone<?= $zones !== 1 ? 's' : '' ?></span>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════
     MODALS
════════════════════════════════════════════════════════════════════════════ -->

<!-- Modal: Add SKU -->
<div class="modal-overlay" id="modalAddSku">
  <div class="modal" style="max-width:460px;">
    <div class="modal-header">
      <h3 class="modal-title">📦 Add New SKU</h3>
      <button class="modal-close">×</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="add_sku">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">SKU Name <span style="color:red">*</span></label>
          <input type="text" name="sku_name" class="form-control" placeholder="Product name" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Unit of Measure</label>
            <select class="form-control" name="uom">
              <option value="Piece">Piece</option>
              <option value="Unit">Unit</option>
              <option value="Box">Box</option>
              <option value="Carton">Carton</option>
              <option value="Pair">Pair</option>
              <option value="Bag">Bag</option>
              <option value="Roll">Roll</option>
              <option value="Crate">Crate</option>
              <option value="Drum">Drum</option>
              <option value="Pack">Pack</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Initial Quantity</label>
            <input type="number" name="qty" class="form-control" value="0" min="0">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Add SKU</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Adjust Stock -->
<div class="modal-overlay" id="modalAdjustStock">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header">
      <h3 class="modal-title">📦 Adjust Stock</h3>
      <button class="modal-close">×</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="adjust_stock">
      <input type="hidden" name="sku_id" id="adj_sku_id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">SKU</label>
          <input type="text" class="form-control" id="adj_sku_name" disabled>
        </div>
        <div class="form-group">
          <label class="form-label">Current Quantity</label>
          <input type="text" class="form-control" id="adj_sku_qty" disabled>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Adjustment Type</label>
            <select class="form-control" name="adj_type">
              <option value="add">Add Stock (+)</option>
              <option value="remove">Remove Stock (−)</option>
              <option value="set">Set Exact Quantity</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Quantity <span style="color:red">*</span></label>
            <input type="number" name="adj_qty" class="form-control" placeholder="0" min="1" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Confirm Adjustment</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Edit SKU -->
<div class="modal-overlay" id="modalEditSku">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header">
      <h3 class="modal-title">✏️ Edit SKU</h3>
      <button class="modal-close">×</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="edit_sku">
      <input type="hidden" name="sku_id" id="edit_sku_id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">SKU Name <span style="color:red">*</span></label>
          <input type="text" name="sku_name" id="edit_sku_name" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Unit of Measure</label>
          <select class="form-control" name="uom" id="edit_sku_uom">
            <option value="Piece">Piece</option>
            <option value="Unit">Unit</option>
            <option value="Box">Box</option>
            <option value="Carton">Carton</option>
            <option value="Pair">Pair</option>
            <option value="Bag">Bag</option>
            <option value="Roll">Roll</option>
            <option value="Crate">Crate</option>
            <option value="Drum">Drum</option>
            <option value="Pack">Pack</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Hidden form: Remove SKU -->
<form method="POST" action="" id="formRemoveSku" style="display:none;">
  <input type="hidden" name="action" value="remove_sku">
  <input type="hidden" name="sku_id" id="remove_sku_id">
</form>

<script>
// ── Dropdown toggle (bypass global handler)
function toggleSkuDropdown(e, id) {
    e.stopPropagation();
    const el = document.getElementById(id);
    const isOpen = el.style.display === 'block';
    closeAllDropdowns();
    el.style.display = isOpen ? 'none' : 'block';
}

// ── Close all open dropdowns
function closeAllDropdowns() {
    document.querySelectorAll('.action-dropdown').forEach(d => d.style.display = 'none');
}

document.addEventListener('click', closeAllDropdowns);

// ── Adjust Stock modal
function openAdjustStock(id, name, qty) {
    closeAllDropdowns();
    document.getElementById('adj_sku_id').value   = id;
    document.getElementById('adj_sku_name').value = name;
    document.getElementById('adj_sku_qty').value  = qty.toLocaleString();
    document.getElementById('modalAdjustStock').classList.add('open');
}

// ── Edit SKU modal
function openEditSku(id, name, uom) {
    closeAllDropdowns();
    document.getElementById('edit_sku_id').value   = id;
    document.getElementById('edit_sku_name').value = name;
    const sel = document.getElementById('edit_sku_uom');
    for (let i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === uom) { sel.selectedIndex = i; break; }
    }
    document.getElementById('modalEditSku').classList.add('open');
}

// ── Confirm remove
function confirmRemove(id, name) {
    closeAllDropdowns();
    if (confirm(`Remove SKU "${name}"?\n\nThis will also delete all inventory logs for this SKU.`)) {
        document.getElementById('remove_sku_id').value = id;
        document.getElementById('formRemoveSku').submit();
    }
}

// ── Client-side filter by stock status
function filterInventory() {
    const val = document.getElementById('filterStock').value.toLowerCase();
    document.querySelectorAll('#inventoryTable tbody tr').forEach(row => {
        const status = (row.dataset.stock || '').toLowerCase();
        row.style.display = (!val || status === val) ? '' : 'none';
    });
}
</script>

<?php close_page(); $db->close(); ?>