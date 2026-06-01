<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('operations');

$skus       = get_skus();
$warehouses = get_warehouses();

$total_skus   = count($skus);
$low_stock    = count(array_filter($skus, fn($s) => $s['stock']==='Low'));
$total_units  = array_sum(array_column($skus, 'qty'));
$wh_count     = count($warehouses);

$low_items = array_filter($skus, fn($s) => $s['stock']==='Low');

open_page('Inventory', 'inventory', [['label'=>'Operations'],['label'=>'Inventory']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Inventory Management</h1>
    <p class="text-muted" style="margin-top:4px;">SKU stock levels across all warehouses</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-outline btn-sm">📥 Export</button>
    <button class="btn btn-primary" data-modal-open="modalAddSku">+ Add SKU</button>
  </div>
</div>

<!-- Low Stock Alert -->
<?php if ($low_stock > 0): ?>
  <div class="alert alert-warning mt-8" style="display:flex;align-items:center;gap:12px;">
    <span style="font-size:20px;">⚠️</span>
    <div>
      <strong><?= $low_stock ?> SKU(s) with Low Stock</strong> —
      <?= implode(', ', array_map(fn($s) => htmlspecialchars($s['id']), $low_items)) ?>
      require immediate restocking attention.
    </div>
  </div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid mt-12">
  <div class="stat-card navy">
    <div class="stat-icon">📦</div>
    <div class="stat-value"><?= $total_skus ?></div>
    <div class="stat-label">Total SKUs</div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon">⚠️</div>
    <div class="stat-value"><?= $low_stock ?></div>
    <div class="stat-label">Low Stock SKUs</div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon">🏭</div>
    <div class="stat-value"><?= $wh_count ?></div>
    <div class="stat-label">Warehouses</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">🔢</div>
    <div class="stat-value"><?= fmt_num($total_units) ?></div>
    <div class="stat-label">Total Units</div>
  </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar mt-16">
  <div class="search-input-wrapper">
    <span>🔍</span>
    <input type="text" class="form-control" placeholder="Search SKU ID or name..." data-table-search="inventoryTable">
  </div>
  <select class="form-control" style="width:150px;">
    <option value="">All Stock Status</option>
    <option>Adequate</option>
    <option>Low</option>
  </select>
  <select class="form-control" style="width:180px;">
    <option value="">All Warehouses</option>
    <?php foreach ($warehouses as $w): ?>
      <option><?= htmlspecialchars($w['name']) ?></option>
    <?php endforeach; ?>
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
          <th>Stock Level</th>
          <th>Stock Status</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($skus as $sku): ?>
          <tr style="<?= $sku['stock']==='Low' ? 'background:rgba(192,57,43,0.04);' : '' ?>">
            <td><strong><?= $sku['id'] ?></strong></td>
            <td>
              <?= htmlspecialchars($sku['name']) ?>
              <?php if ($sku['stock']==='Low'): ?>
                <span class="badge badge-red" style="margin-left:6px;">Low Stock</span>
              <?php endif; ?>
            </td>
            <td class="td-muted"><?= $sku['uom'] ?></td>
            <td><strong><?= fmt_num($sku['qty']) ?></strong></td>
            <td style="min-width:120px;">
              <?php
                $pct = min(100, round($sku['qty'] / 250 * 100));
                $bar_color = $sku['stock']==='Low' ? 'var(--c-red)' : 'var(--c-green)';
              ?>
              <div class="progress-bar">
                <div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $bar_color ?>;"></div>
              </div>
            </td>
            <td><?= status_badge($sku['stock']) ?></td>
            <td style="text-align:right;">
              <div class="action-menu">
                <button class="action-menu-btn btn btn-ghost btn-sm" data-dropdown-toggle="actSku<?= $sku['id'] ?>">⋯</button>
                <div class="action-dropdown" id="actSku<?= $sku['id'] ?>">
                  <a href="#" class="dropdown-item" data-modal-open="modalAdjustStock">📦 Adjust Stock</a>
                  <a href="#" class="dropdown-item" data-modal-open="modalEditSku">✏️ Edit SKU</a>
                  <a href="#" class="dropdown-item danger" data-confirm="Remove this SKU?">🗑️ Remove</a>
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
      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><?= htmlspecialchars($wh['name']) ?></h3>
          <span class="badge badge-<?= $wh['type']==='Hub' ? 'navy' : ($wh['type']==='Cross-dock' ? 'olive' : 'blue') ?>">
            <?= $wh['type'] ?>
          </span>
        </div>
        <div class="card-body" style="padding:0;">
          <div class="info-grid">
            <div class="info-row">
              <span class="info-label">ID</span>
              <span class="info-value"><?= $wh['id'] ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Address</span>
              <span class="info-value" style="font-size:12px;"><?= htmlspecialchars($wh['address']) ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Type</span>
              <span class="info-value"><?= $wh['type'] ?></span>
            </div>
            <div class="info-row">
              <span class="info-label">Zones</span>
              <span class="info-value"><?= $wh['zones'] ?> zones</span>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Modal: Add SKU -->
<div class="modal-overlay" id="modalAddSku">
  <div class="modal" style="max-width:460px;">
    <div class="modal-header">
      <h3 class="modal-title">📦 Add New SKU</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="modal-body">
      <form data-feedback="SKU added!">
        <div class="form-group">
          <label class="form-label">SKU Name *</label>
          <input type="text" class="form-control" placeholder="Product name">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Unit of Measure</label>
            <select class="form-control">
              <option>Unit</option>
              <option>Box</option>
              <option>Carton</option>
              <option>Bag</option>
              <option>Roll</option>
              <option>Crate</option>
              <option>Drum</option>
              <option>Pack</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Initial Quantity</label>
            <input type="number" class="form-control" placeholder="0">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Warehouse</label>
          <select class="form-control">
            <?php foreach ($warehouses as $w): ?>
              <option><?= htmlspecialchars($w['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline modal-close">Cancel</button>
      <button class="btn btn-primary">Add SKU</button>
    </div>
  </div>
</div>

<!-- Modal: Adjust Stock -->
<div class="modal-overlay" id="modalAdjustStock">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header">
      <h3 class="modal-title">📦 Adjust Stock</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="modal-body">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Adjustment Type</label>
          <select class="form-control">
            <option>Add Stock</option>
            <option>Remove Stock</option>
            <option>Set Exact Quantity</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Quantity</label>
          <input type="number" class="form-control" placeholder="0">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Reason</label>
        <textarea class="form-control" rows="2" placeholder="Reason for adjustment..."></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline modal-close">Cancel</button>
      <button class="btn btn-primary">Confirm Adjustment</button>
    </div>
  </div>
</div>

<?php close_page(); ?>
