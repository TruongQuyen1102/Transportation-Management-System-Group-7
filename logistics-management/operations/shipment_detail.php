<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('operations');

$shipments = get_shipments();
$shp = $shipments[0]; // SHP001 hardcoded

$tracking_logs = array_filter(get_tracking_logs(), fn($t) => $t['shipment_id'] === 'SHP001');
$tracking_logs = array_values($tracking_logs);

$orders = get_orders();
$order  = array_filter($orders, fn($o) => $o['id'] === $shp['order_id']);
$order  = array_values($order)[0] ?? null;

// Lifecycle steps
$steps = ['SCHEDULED', 'LOADING', 'IN_TRANSIT', 'DELIVERED'];
$step_labels = ['Scheduled', 'Loading', 'In Transit', 'Delivered'];
$current_step_idx = match($shp['status']) {
    'SCHEDULED'  => 0,
    'LOADING'    => 1,
    'IN_TRANSIT' => 2,
    'DELIVERED'  => 3,
    default      => 0
};

open_page('Shipment Detail — ' . $shp['id'], 'shipments', [
    ['label'=>'Operations'],
    ['label'=>'Shipments','url'=>'/operations/shipments.php'],
    ['label'=>$shp['id']]
]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Shipment <?= $shp['id'] ?></h1>
    <div style="display:flex;align-items:center;gap:10px;margin-top:6px;">
      <?= status_badge($shp['status']) ?>
      <span class="text-muted">Order <?= $shp['order_id'] ?></span>
      <span class="text-muted">|</span>
      <span class="text-muted"><?= htmlspecialchars($shp['route']) ?></span>
    </div>
  </div>
  <div class="page-actions">
    <button class="btn btn-outline btn-sm" data-modal-open="modalLogTracking">📍 Log Tracking</button>
    <button class="btn btn-outline btn-sm" data-modal-open="modalReportExc">⚠️ Report Exception</button>
    <button class="btn btn-primary btn-sm" data-modal-open="modalUpdateStatus">🔄 Update Status</button>
  </div>
</div>

<!-- Lifecycle Stepper -->
<div class="card mt-16">
  <div class="card-header">
    <h3 class="card-title">Shipment Lifecycle</h3>
  </div>
  <div class="card-body">
    <div class="stepper">
      <?php foreach ($steps as $i => $step): ?>
        <div class="step <?= $i <= $current_step_idx ? 'active' : '' ?> <?= $i < $current_step_idx ? 'completed' : '' ?>">
          <div class="step-dot"><?= $i < $current_step_idx ? '✓' : ($i+1) ?></div>
          <div class="step-label"><?= $step_labels[$i] ?></div>
          <?php if ($i === 0): ?><div class="step-sub"><?= $shp['planned_dep'] ?></div><?php endif; ?>
          <?php if ($i === 3 && $shp['actual_arr']): ?><div class="step-sub"><?= $shp['actual_arr'] ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="grid-2 mt-16" style="gap:20px;">

  <!-- Shipment Info -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Shipment Information</h3>
    </div>
    <div class="card-body" style="padding:0;">
      <div class="info-grid">
        <div class="info-row">
          <span class="info-label">Shipment ID</span>
          <span class="info-value"><strong><?= $shp['id'] ?></strong></span>
        </div>
        <div class="info-row">
          <span class="info-label">Route</span>
          <span class="info-value"><?= htmlspecialchars($shp['route']) ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Asset</span>
          <span class="info-value"><?= htmlspecialchars($shp['asset']) ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Carrier</span>
          <span class="info-value"><?= htmlspecialchars($shp['carrier']) ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Planned Departure</span>
          <span class="info-value"><?= $shp['planned_dep'] ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Actual Departure</span>
          <span class="info-value"><?= $shp['actual_dep'] ?? '<span class="td-muted">—</span>' ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Estimated Arrival</span>
          <span class="info-value"><?= $shp['est_arr'] ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Actual Arrival</span>
          <span class="info-value"><?= $shp['actual_arr'] ?? '<span class="td-muted">—</span>' ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Deadline</span>
          <span class="info-value"><?= $shp['deadline'] ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Status</span>
          <span class="info-value"><?= status_badge($shp['status']) ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Related Order Info -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Related Order — <?= $shp['order_id'] ?></h3>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if ($order): ?>
        <div class="info-grid">
          <div class="info-row">
            <span class="info-label">Order ID</span>
            <span class="info-value"><strong><?= $order['id'] ?></strong></span>
          </div>
          <div class="info-row">
            <span class="info-label">Customer</span>
            <span class="info-value"><?= htmlspecialchars($order['customer']) ?></span>
          </div>
          <div class="info-row">
            <span class="info-label">Pickup</span>
            <span class="info-value"><?= htmlspecialchars($order['pickup']) ?></span>
          </div>
          <div class="info-row">
            <span class="info-label">Delivery</span>
            <span class="info-value"><?= htmlspecialchars($order['delivery']) ?></span>
          </div>
          <div class="info-row">
            <span class="info-label">Order Date</span>
            <span class="info-value"><?= $order['order_date'] ?></span>
          </div>
          <div class="info-row">
            <span class="info-label">Expected Delivery</span>
            <span class="info-value"><?= $order['expected_delivery'] ?></span>
          </div>
          <div class="info-row">
            <span class="info-label">Weight</span>
            <span class="info-value"><?= fmt_num($order['weight']) ?> kg</span>
          </div>
          <div class="info-row">
            <span class="info-label">Volume</span>
            <span class="info-value"><?= $order['volume'] ?> m³</span>
          </div>
          <div class="info-row">
            <span class="info-label">SKU Count</span>
            <span class="info-value"><?= $order['sku_count'] ?></span>
          </div>
          <div class="info-row">
            <span class="info-label">Status</span>
            <span class="info-value"><?= status_badge($order['status']) ?></span>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- Tracking Timeline -->
<div class="card mt-16">
  <div class="card-header">
    <h3 class="card-title">📍 Tracking Log</h3>
    <button class="btn btn-outline btn-sm" data-modal-open="modalLogTracking">+ Add Log</button>
  </div>
  <div class="card-body">
    <?php if (empty($tracking_logs)): ?>
      <div class="alert alert-info">No tracking logs yet for this shipment.</div>
    <?php else: ?>
      <div class="timeline">
        <?php foreach ($tracking_logs as $log): ?>
          <div class="timeline-item">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
              <div>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
                  <span style="font-size:20px;"><?php
                    echo match($log['weather']) {
                      'Clear'      => '☀️',
                      'Sunny'      => '🌤️',
                      'Light Rain' => '🌧️',
                      'Cloudy'     => '☁️',
                      default      => '🌡️'
                    };
                  ?></span>
                  <strong><?= htmlspecialchars($log['location']) ?></strong>
                  <?php if ($log['delay'] > 0): ?>
                    <span class="badge badge-red">+<?= $log['delay'] ?> min delay</span>
                  <?php else: ?>
                    <span class="badge badge-green">On Time</span>
                  <?php endif; ?>
                </div>
                <div class="text-muted" style="font-size:13px;"><?= htmlspecialchars($log['note']) ?></div>
                <div class="text-muted" style="font-size:11px;margin-top:4px;">
                  👤 <?= htmlspecialchars($log['account']) ?> &nbsp;|&nbsp; ☁️ <?= $log['weather'] ?>
                </div>
              </div>
              <div class="text-muted" style="font-size:12px;white-space:nowrap;"><?= $log['timestamp'] ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal: Update Status -->
<div class="modal-overlay" id="modalUpdateStatus">
  <div class="modal" style="max-width:380px;">
    <div class="modal-header">
      <h3 class="modal-title">🔄 Update Status</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">New Status</label>
        <select class="form-control">
          <option>SCHEDULED</option>
          <option>LOADING</option>
          <option selected>IN_TRANSIT</option>
          <option>DELIVERED</option>
          <option>CANCELLED</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Actual Arrival (if delivered)</label>
        <input type="datetime-local" class="form-control">
      </div>
      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea class="form-control" rows="2"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline modal-close">Cancel</button>
      <button class="btn btn-primary">Update Status</button>
    </div>
  </div>
</div>

<!-- Modal: Log Tracking -->
<div class="modal-overlay" id="modalLogTracking">
  <div class="modal" style="max-width:460px;">
    <div class="modal-header">
      <h3 class="modal-title">📍 Log Tracking Update</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="modal-body">
      <form data-feedback="Tracking log added!">
        <div class="form-group">
          <label class="form-label">Current Location</label>
          <input type="text" class="form-control" placeholder="e.g. Binh Dinh Province Checkpoint">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Weather</label>
            <select class="form-control">
              <option>Clear</option>
              <option>Sunny</option>
              <option>Cloudy</option>
              <option>Light Rain</option>
              <option>Heavy Rain</option>
              <option>Storm</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Delay (minutes)</label>
            <input type="number" class="form-control" value="0" min="0">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea class="form-control" rows="2" placeholder="Log note..."></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline modal-close">Cancel</button>
      <button class="btn btn-primary">Save Log</button>
    </div>
  </div>
</div>

<!-- Modal: Report Exception -->
<div class="modal-overlay" id="modalReportExc">
  <div class="modal" style="max-width:460px;">
    <div class="modal-header">
      <h3 class="modal-title">⚠️ Report Exception</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="modal-body">
      <form data-feedback="Exception reported!">
        <div class="form-group">
          <label class="form-label">Issue Type</label>
          <select class="form-control">
            <option>Traffic Delay</option>
            <option>Damaged Goods</option>
            <option>Route Deviation</option>
            <option>Vehicle Breakdown</option>
            <option>Failed Delivery</option>
            <option>Weather Event</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea class="form-control" rows="3" placeholder="Describe the issue in detail..."></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline modal-close">Cancel</button>
      <button class="btn btn-danger">Report</button>
    </div>
  </div>
</div>

<?php close_page(); ?>
