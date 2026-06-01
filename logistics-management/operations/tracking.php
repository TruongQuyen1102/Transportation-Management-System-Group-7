<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('operations');

$all_logs  = get_tracking_logs();
$shipments = get_shipments();

// Filter by shipment if param provided
$filter_shp = $_GET['shipment'] ?? '';
$logs = $filter_shp
    ? array_values(array_filter($all_logs, fn($l) => $l['shipment_id'] === $filter_shp))
    : array_values($all_logs);

$weather_icons = [
    'Clear'      => '☀️',
    'Sunny'      => '🌤️',
    'Light Rain' => '🌧️',
    'Cloudy'     => '☁️',
    'Heavy Rain' => '⛈️',
    'Storm'      => '🌪️',
];

open_page('Tracking Logs', 'tracking', [['label'=>'Operations'],['label'=>'Tracking Logs']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Tracking Logs</h1>
    <p class="text-muted" style="margin-top:4px;"><?= count($all_logs) ?> total tracking events recorded</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-primary" data-modal-open="modalAddTracking">+ Add Tracking Log</button>
  </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar mt-8">
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <div class="search-input-wrapper">
      <span>📦</span>
      <select name="shipment" class="form-control" style="min-width:200px;" onchange="this.form.submit()">
        <option value="">All Shipments</option>
        <?php foreach ($shipments as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $filter_shp===$s['id'] ? 'selected' : '' ?>>
            <?= $s['id'] ?> — <?= htmlspecialchars($s['route']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($filter_shp): ?>
      <a href="/operations/tracking.php" class="btn btn-outline btn-sm">✕ Clear Filter</a>
    <?php endif; ?>
  </form>
  <div class="search-input-wrapper" style="margin-left:auto;">
    <span>🔍</span>
    <input type="text" class="form-control" placeholder="Search location, notes..." data-table-search="trackingTable">
  </div>
</div>

<?php if ($filter_shp): ?>
  <div class="alert alert-info mt-8">
    Showing tracking logs for shipment <strong><?= $filter_shp ?></strong>
    (<?= count($logs) ?> events)
  </div>
<?php endif; ?>

<div class="grid-2 mt-16" style="gap:20px;">

  <!-- Timeline View -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">📍 Live Timeline</h3>
    </div>
    <div class="card-body">
      <?php if (empty($logs)): ?>
        <div class="alert alert-info">No tracking logs found for the selected filter.</div>
      <?php else: ?>
        <div class="timeline">
          <?php foreach ($logs as $log): ?>
            <div class="timeline-item">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div style="flex:1;">
                  <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <span style="font-size:22px;"><?= $weather_icons[$log['weather']] ?? '🌡️' ?></span>
                    <div>
                      <strong style="font-size:14px;"><?= htmlspecialchars($log['location']) ?></strong>
                      <div class="td-muted" style="font-size:11px;">📦 <?= $log['shipment_id'] ?> &nbsp;·&nbsp; 👤 <?= htmlspecialchars($log['account']) ?></div>
                    </div>
                  </div>
                  <div class="text-muted" style="font-size:13px;margin-bottom:4px;"><?= htmlspecialchars($log['note']) ?></div>
                  <div style="display:flex;gap:8px;">
                    <span class="badge badge-gray">☁️ <?= $log['weather'] ?></span>
                    <?php if ($log['delay'] > 0): ?>
                      <span class="badge badge-red">+<?= $log['delay'] ?> min</span>
                    <?php else: ?>
                      <span class="badge badge-green">On Time</span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="text-muted" style="font-size:11px;white-space:nowrap;margin-left:12px;"><?= $log['timestamp'] ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Summary Table -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">📋 All Log Entries</h3>
    </div>
    <div class="table-wrapper">
      <table id="trackingTable" style="font-size:12px;">
        <thead>
          <tr>
            <th>Log ID</th>
            <th>Shipment</th>
            <th>Timestamp</th>
            <th>Location</th>
            <th>Weather</th>
            <th>Delay</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td><strong><?= $log['id'] ?></strong></td>
              <td>
                <a href="/operations/tracking.php?shipment=<?= $log['shipment_id'] ?>" style="color:var(--c-yellow);">
                  <?= $log['shipment_id'] ?>
                </a>
              </td>
              <td class="td-muted"><?= $log['timestamp'] ?></td>
              <td style="max-width:150px;" class="truncate"><?= htmlspecialchars($log['location']) ?></td>
              <td><?= $weather_icons[$log['weather']] ?? '' ?> <?= $log['weather'] ?></td>
              <td>
                <?php if ($log['delay'] > 0): ?>
                  <span class="badge badge-red">+<?= $log['delay'] ?>m</span>
                <?php else: ?>
                  <span class="badge badge-green">0</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Modal: Add Tracking Log -->
<div class="modal-overlay" id="modalAddTracking">
  <div class="modal" style="max-width:520px;">
    <div class="modal-header">
      <h3 class="modal-title">📍 Add Tracking Log</h3>
      <button class="modal-close">×</button>
    </div>
    <div class="modal-body">
      <form data-feedback="Tracking log added successfully!">
        <div class="form-group">
          <label class="form-label">Shipment *</label>
          <select class="form-control">
            <option value="">Select shipment...</option>
            <?php foreach ($shipments as $s): ?>
              <option value="<?= $s['id'] ?>" <?= $filter_shp===$s['id'] ? 'selected' : '' ?>>
                <?= $s['id'] ?> — <?= htmlspecialchars($s['route']) ?> (<?= $s['status'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Current Location *</label>
          <input type="text" class="form-control" placeholder="e.g. Da Nang Checkpoint, Km 760">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Weather Condition</label>
            <select class="form-control">
              <option>Clear</option>
              <option>Sunny</option>
              <option>Cloudy</option>
              <option>Light Rain</option>
              <option>Heavy Rain</option>
              <option>Storm</option>
              <option>Fog</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Delay (minutes)</label>
            <input type="number" class="form-control" value="0" min="0" placeholder="0">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Timestamp</label>
          <input type="datetime-local" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea class="form-control" rows="3" placeholder="Describe current status, issues, observations..."></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline modal-close">Cancel</button>
      <button class="btn btn-primary">Save Tracking Log</button>
    </div>
  </div>
</div>

<?php close_page(); ?>
