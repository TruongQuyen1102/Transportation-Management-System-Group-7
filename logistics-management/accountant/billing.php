<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('accountant');

$billing = get_billing_structures();
$active  = array_filter($billing, fn($b) => $b['active']);
$inactive = array_filter($billing, fn($b) => !$b['active']);

open_page('Billing Structure', 'billing', [['label'=>'Finance'],['label'=>'Billing Structure']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">⚙️ Billing Structure</h1>
    <p class="text-muted" style="margin-top:4px;">Manage charge types, rates, and billing methods applied to invoices</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-accent" data-modal-open="modalAddCharge">＋ Add Charge Type</button>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
  <div class="stat-card navy">
    <div class="stat-icon">📋</div>
    <div class="stat-value"><?= count($billing) ?></div>
    <div class="stat-label">Total Charge Types</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">✅</div>
    <div class="stat-value"><?= count($active) ?></div>
    <div class="stat-label">Active Rates</div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon">⏸</div>
    <div class="stat-value"><?= count($inactive) ?></div>
    <div class="stat-label">Inactive Rates</div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon">💱</div>
    <div class="stat-value">VND · USD</div>
    <div class="stat-label">Supported Currencies</div>
  </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar mt-16">
  <div class="search-input-wrapper">
    <input type="text" class="form-control" data-table-search="billingTable" placeholder="🔍  Search charge type or method…" style="min-width:280px;">
  </div>
  <select class="form-control" style="max-width:180px;" onchange="filterBillingTable(this.value,'currency')">
    <option value="">All Currencies</option>
    <option value="VND">VND</option>
    <option value="USD">USD</option>
  </select>
  <select class="form-control" style="max-width:160px;" onchange="filterBillingTable(this.value,'status')">
    <option value="">All Status</option>
    <option value="1">Active</option>
    <option value="0">Inactive</option>
  </select>
</div>

<!-- Table -->
<div class="card mt-16">
  <div class="card-body" style="padding:0;">
    <div class="table-wrapper">
      <table id="billingTable">
        <thead>
          <tr>
            <th>ID</th>
            <th>Charge Type</th>
            <th>Billing Method</th>
            <th class="text-right">Rate</th>
            <th>Currency</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($billing as $b): ?>
          <tr data-currency="<?= $b['currency'] ?>" data-status="<?= $b['active'] ? '1' : '0' ?>">
            <td class="td-muted font-bold"><?= $b['id'] ?></td>
            <td class="font-bold"><?= htmlspecialchars($b['charge_type']) ?></td>
            <td><?= htmlspecialchars($b['method']) ?></td>
            <td class="text-right font-bold">
              <?php if ($b['currency'] === 'USD'): ?>
                $<?= number_format($b['rate'], 2) ?>
              <?php elseif ($b['method'] === '% of Value'): ?>
                <?= $b['rate'] ?>%
              <?php else: ?>
                <?= number_format($b['rate'], 0) ?> ₫
              <?php endif; ?>
            </td>
            <td>
              <?php if ($b['currency'] === 'USD'): ?>
                <span class="badge badge-blue">USD</span>
              <?php else: ?>
                <span class="badge badge-navy">VND</span>
              <?php endif; ?>
            </td>
            <td>
              <label class="switch" title="<?= $b['active'] ? 'Deactivate' : 'Activate' ?>">
                <input type="checkbox" <?= $b['active'] ? 'checked' : '' ?> onchange="toggleBilling('<?= $b['id'] ?>', this.checked)">
                <span class="slider"></span>
              </label>
            </td>
            <td>
              <div class="action-menu">
                <button class="btn btn-ghost btn-sm action-menu-btn" data-dropdown-toggle="menu-<?= $b['id'] ?>">⋮</button>
                <div class="action-dropdown" id="menu-<?= $b['id'] ?>">
                  <a href="#" class="dropdown-item" data-modal-open="modalEditCharge" onclick="prefillEdit('<?= $b['id'] ?>', '<?= htmlspecialchars($b['charge_type'], ENT_QUOTES) ?>', '<?= $b['method'] ?>', '<?= $b['rate'] ?>', '<?= $b['currency'] ?>')">✏️ Edit Rate</a>
                  <a href="#" class="dropdown-item" data-confirm="<?= $b['active'] ? 'Deactivate' : 'Activate' ?> this charge type?"
                     onclick="toggleBilling('<?= $b['id'] ?>', <?= $b['active'] ? 'false' : 'true' ?>)">
                    <?= $b['active'] ? '⏸ Deactivate' : '▶ Activate' ?>
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
</div>

<!-- Modal: Add Charge Type -->
<div class="modal-overlay" id="modalAddCharge">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <span class="modal-title">Add Charge Type</span>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body">
      <form data-feedback="Charge type added successfully!">
        <div class="form-group">
          <label class="form-label">Charge Type Name *</label>
          <input type="text" class="form-control" placeholder="e.g. Weekend Surcharge">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Billing Method *</label>
            <select class="form-control">
              <option>Per KG</option>
              <option>Per CBM</option>
              <option>Per Pallet</option>
              <option>Flat</option>
              <option>% of Value</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Currency *</label>
            <select class="form-control">
              <option>VND</option>
              <option>USD</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Rate *</label>
          <input type="number" class="form-control" placeholder="0" min="0">
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select class="form-control">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost modal-close">Cancel</button>
      <button class="btn btn-primary">Save Charge Type</button>
    </div>
  </div>
</div>

<!-- Modal: Edit Charge -->
<div class="modal-overlay" id="modalEditCharge">
  <div class="modal" style="max-width:480px;">
    <div class="modal-header">
      <span class="modal-title">Edit Charge Type</span>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body">
      <form data-feedback="Charge type updated successfully!">
        <div class="form-group">
          <label class="form-label">Charge Type Name *</label>
          <input type="text" class="form-control" id="editChargeName">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Billing Method *</label>
            <select class="form-control" id="editChargeMethod">
              <option>Per KG</option>
              <option>Per CBM</option>
              <option>Per Pallet</option>
              <option>Flat</option>
              <option>% of Value</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Currency</label>
            <select class="form-control" id="editChargeCurrency">
              <option>VND</option>
              <option>USD</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Rate *</label>
          <input type="number" class="form-control" id="editChargeRate" min="0">
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost modal-close">Cancel</button>
      <button class="btn btn-primary">Update Rate</button>
    </div>
  </div>
</div>

<script>
function prefillEdit(id, name, method, rate, currency) {
  document.getElementById('editChargeName').value     = name;
  document.getElementById('editChargeRate').value     = rate;
  document.getElementById('editChargeCurrency').value = currency;
  const sel = document.getElementById('editChargeMethod');
  for (let o of sel.options) { if (o.value === method || o.text === method) o.selected = true; }
}

function toggleBilling(id, state) {
  // In production: AJAX call to update
  console.log('Toggle billing', id, state);
}

function filterBillingTable(val, attr) {
  document.querySelectorAll('#billingTable tbody tr').forEach(row => {
    if (!val) { row.style.display = ''; return; }
    row.style.display = row.dataset[attr] === val ? '' : 'none';
  });
}
</script>

<?php close_page(); ?>
