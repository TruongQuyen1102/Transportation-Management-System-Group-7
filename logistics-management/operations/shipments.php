<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('operations');

$shipments = get_shipments();
$routes    = get_routes();
$carriers  = get_carriers();

$total     = count($shipments);
$in_transit= count(array_filter($shipments, fn($s) => $s['status']==='IN_TRANSIT'));
$delivered = count(array_filter($shipments, fn($s) => $s['status']==='DELIVERED'));
$scheduled = count(array_filter($shipments, fn($s) => $s['status']==='SCHEDULED'));

$avail_assets = array_filter(get_transport_assets(), fn($a) => $a['status']==='Available');
$orders_unassigned = array_filter(get_orders(), fn($o) => in_array($o['status'],['PENDING','CONFIRMED']));

open_page('Shipment Orders', 'shipments', [['label'=>'Operations'],['label'=>'Shipment Orders']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Shipment Orders</h1>
    <p class="text-muted" style="margin-top:4px;"><?= $total ?> total shipments in system</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-primary" data-modal-open="modalCreateShipment">+ Create Shipment</button>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card navy">
    <div class="stat-icon">📦</div>
    <div class="stat-value"><?= $total ?></div>
    <div class="stat-label">Total Shipments</div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon">🚛</div>
    <div class="stat-value"><?= $in_transit ?></div>
    <div class="stat-label">In Transit</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">✅</div>
    <div class="stat-value"><?= $delivered ?></div>
    <div class="stat-label">Delivered</div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon">📅</div>
    <div class="stat-value"><?= $scheduled ?></div>
    <div class="stat-label">Scheduled</div>
  </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar mt-16">
  <div class="search-input-wrapper">
    <span>🔍</span>
    <input type="text" class="form-control" placeholder="Search shipment ID, order, route..." data-table-search="shipmentsTable">
  </div>
  <select class="form-control" style="width:140px;">
    <option value="">All Statuses</option>
    <option>IN_TRANSIT</option>
    <option>DELIVERED</option>
    <option>SCHEDULED</option>
  </select>
  <select class="form-control" style="width:160px;">
    <option value="">All Carriers</option>
    <?php foreach ($carriers as $c): ?>
      <option><?= htmlspecialchars($c['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select class="form-control" style="width:180px;">
    <option value="">All Routes</option>
    <?php foreach ($routes as $r): ?>
      <option><?= htmlspecialchars($r['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="date" class="form-control" style="width:150px;" placeholder="From date">
  <input type="date" class="form-control" style="width:150px;" placeholder="To date">
</div>

<div class="card mt-12">
  <div class="table-wrapper">
    <table id="shipmentsTable">
      <thead>
        <tr>
          <th>SHP ID</th>
          <th>Order ID</th>
          <th>Route</th>
          <th>Asset / Carrier</th>
          <th>Planned Dep.</th>
          <th>Est. Arrival</th>
          <th>Status</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($shipments as $s): ?>
          <tr>
            <td><strong><?= $s['id'] ?></strong></td>
            <td><a href="#" style="color:var(--c-yellow);"><?= $s['order_id'] ?></a></td>
            <td class="td-muted"><?= htmlspecialchars($s['route']) ?></td>
            <td>
              <div><?= htmlspecialchars($s['asset']) ?></div>
              <div class="td-muted" style="font-size:11px;"><?= htmlspecialchars($s['carrier']) ?></div>
            </td>
            <td class="td-muted"><?= $s['planned_dep'] ?></td>
            <td class="td-muted"><?= $s['est_arr'] ?></td>
            <td><?= status_badge($s['status']) ?></td>
            <td style="text-align:right;">
              <div class="action-menu">
                <button class="action-menu-btn btn btn-ghost btn-sm" data-dropdown-toggle="actShp<?= $s['id'] ?>">⋯</button>
                <div class="action-dropdown" id="actShp<?= $s['id'] ?>">
                  <a href="/operations/shipment_detail.php?id=<?= $s['id'] ?>" class="dropdown-item">👁 View Details</a>
                  <a href="#" class="dropdown-item" data-modal-open="modalUpdateStatus">🔄 Update Status</a>
                  <a href="/operations/tracking.php?shipment=<?= $s['id'] ?>" class="dropdown-item">📍 Track</a>
                  <a href="#" class="dropdown-item" data-modal-open="modalLogException">⚠️ Log Exception</a>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal: Create Shipment -->
<div class="modal-overlay" id="modalCreateShipment">
  <div class="modal" style="max-width:580px;">
    <div class="modal-header">
      <h3 class="modal-title">📦 Create New Shipment</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="modal-body">
      <form data-feedback="Shipment created successfully!">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Order *</label>
            <select class="form-control">
              <option value="">Select order...</option>
              <?php foreach ($orders_unassigned as $o): ?>
                <option value="<?= $o['id'] ?>"><?= $o['id'] ?> — <?= htmlspecialchars($o['customer']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Route *</label>
            <select class="form-control">
              <option value="">Select route...</option>
              <?php foreach ($routes as $r): ?>
                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Carrier</label>
            <select class="form-control">
              <option value="">Select carrier...</option>
              <?php foreach ($carriers as $c): if ($c['status']==='active'): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endif; endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Transport Asset</label>
            <select class="form-control">
              <option value="">Select asset...</option>
              <?php foreach ($avail_assets as $a): ?>
                <option value="<?= $a['id'] ?>"><?= $a['id'] ?> — <?= htmlspecialchars($a['type']) ?> (<?= $a['plate'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Planned Departure *</label>
            <input type="datetime-local" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Estimated Arrival</label>
            <input type="datetime-local" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Deadline</label>
          <input type="date" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea class="form-control" rows="2" placeholder="Special instructions or notes..."></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline modal-close">Cancel</button>
      <button class="btn btn-primary">✓ Create Shipment</button>
    </div>
  </div>
</div>

<!-- Modal: Update Status -->
<div class="modal-overlay" id="modalUpdateStatus">
  <div class="modal" style="max-width:400px;">
    <div class="modal-header">
      <h3 class="modal-title">🔄 Update Shipment Status</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">New Status</label>
        <select class="form-control">
          <option>SCHEDULED</option>
          <option>LOADING</option>
          <option>IN_TRANSIT</option>
          <option>DELIVERED</option>
          <option>CANCELLED</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea class="form-control" rows="2"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline modal-close">Cancel</button>
      <button class="btn btn-primary">Update</button>
    </div>
  </div>
</div>

<!-- Modal: Log Exception -->
<div class="modal-overlay" id="modalLogException">
  <div class="modal" style="max-width:460px;">
    <div class="modal-header">
      <h3 class="modal-title">⚠️ Log Exception</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="modal-body">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Issue Type</label>
          <select class="form-control">
            <option>Traffic Delay</option>
            <option>Damaged Goods</option>
            <option>Route Deviation</option>
            <option>Vehicle Breakdown</option>
            <option>Failed Delivery</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-control" rows="3" placeholder="Describe the exception..."></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline modal-close">Cancel</button>
      <button class="btn btn-danger">Report Exception</button>
    </div>
  </div>
</div>

<?php close_page(); ?>
