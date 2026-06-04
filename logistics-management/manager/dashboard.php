<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/db.php';
auth_require('manager');

$db = get_db();

function db_value(mysqli $db, string $sql, $default = 0) {
    $res = $db->query($sql);
    if (!$res) {
        return $default;
    }
    $row = $res->fetch_row();
    return $row ? ($row[0] ?? $default) : $default;
}

function db_rows(mysqli $db, string $sql): array {
    $rows = [];
    $res = $db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

function manager_badge(string $value): string {
    $key = strtolower(trim($value));
    $color = match ($key) {
        'delivered', 'approved', 'active', 'shipped' => 'green',
        'in transit', 'processing', 'under investigation' => 'olive',
        'pending' => 'yellow',
        'critical', 'high', 'cancelled', 'canceled', 'rejected' => 'red',
        default => 'gray',
    };
    return '<span class="badge badge-' . $color . '">' . htmlspecialchars($value) . '</span>';
}

function month_key(DateTime $date): string {
    return $date->format('Y-m');
}

// Get the latest timestamp from DB to avoid empty chart errors
$d1 = db_value($db, "SELECT MAX(IssueDate) FROM invoice", '2000-01-01');
$d2 = db_value($db, "SELECT MAX(PlannedDeparture) FROM shipment", '2000-01-01');

$maxDbDate = ($d1 > $d2) ? $d1 : $d2;
if ($maxDbDate === '2000-01-01') {
    $maxDbDate = date('Y-m-d');
}

$cursor = new DateTime($maxDbDate);
$cursor->modify('first day of this month');
$cursor->modify('-5 months');
$startDateStr = $cursor->format('Y-m-01'); 
$currentYm = substr($maxDbDate, 0, 7); 

$totalOrders = (int) db_value($db, "SELECT COUNT(*) FROM order_info");
$deliveredOrders = (int) db_value($db, "SELECT COUNT(*) FROM order_info WHERE ShippingStatus = 'Delivered'");
$activeOrders = (int) db_value($db, "SELECT COUNT(*) FROM order_info WHERE ShippingStatus IN ('In Transit', 'Shipped')");
$pendingOrders = (int) db_value($db, "SELECT COUNT(*) FROM order_info WHERE ShippingStatus IN ('Pending', 'Processing')");
$totalAssets = (int) db_value($db, "SELECT COUNT(*) FROM transport_asset");
$assetsInUse = (int) db_value($db, "SELECT COUNT(DISTINCT AssetID) FROM shipment WHERE AssetID IS NOT NULL AND Status = 'In Transit'");
$fleetUtilization = $totalAssets > 0 ? round(($assetsInUse / $totalAssets) * 100, 1) : 0;
$openExceptions = (int) db_value($db, "SELECT COUNT(*) FROM operational_exception WHERE ApprovalStatus IN ('Pending', 'Under Investigation')");

$monthRevenue = (float) db_value($db, "
    SELECT COALESCE(SUM(FinalAmount), 0) 
    FROM invoice 
    WHERE InvoiceType = 'AR_Receivable' 
      AND DATE_FORMAT(IssueDate, '%Y-%m') = '$currentYm'
", 0);

$onTimeStats = db_rows($db, "
    SELECT
      SUM(CASE WHEN ActualArrival <= DeliveryDeadline THEN 1 ELSE 0 END) AS on_time,
      COUNT(*) AS total_done
    FROM shipment
    WHERE Status = 'Delivered' AND ActualArrival IS NOT NULL AND DeliveryDeadline IS NOT NULL
");
$onTimeTotal = (int)($onTimeStats[0]['total_done'] ?? 0);
$onTimeRate = $onTimeTotal > 0 ? round(((int)$onTimeStats[0]['on_time'] / $onTimeTotal) * 100, 1) : 0;

$revenueByMonth = [];
foreach (db_rows($db, "
    SELECT DATE_FORMAT(IssueDate, '%Y-%m') AS ym, SUM(FinalAmount) AS revenue
    FROM invoice
    WHERE InvoiceType = 'AR_Receivable'
      AND IssueDate >= '$startDateStr'
    GROUP BY ym
    ORDER BY ym
") as $row) {
    $revenueByMonth[$row['ym']] = (float)$row['revenue'];
}

// ── Delivery performance ──────────────────────────────────────────────────────
// 3-layer strategy, priority from most accurate → estimated:
//
// Layer 1: Has ActualArrival + deadline (DeliveryDeadline or ExpectedDeliveryDate)
//          → compute on-time exactly, grouped by PlannedDeparture month
// Layer 2: (merged into Layer 1 CASE logic — missing deadline treated as on-time)
// Layer 3: No ActualArrival at all
//          → use ShippingStatus: Delivered/Shipped = on-time, others = delayed
//          → spread the overall ratio evenly across all 6 months

$perfByMonth     = [];
$perfHasRealData = false;

// --- Layer 1: ActualArrival + Deadline (no date range filter — collect all history) ---
$layer1 = db_rows($db, "
    SELECT
      DATE_FORMAT(s.PlannedDeparture, '%Y-%m') AS ym,
      COUNT(*)  AS total,
      SUM(CASE
            WHEN s.DeliveryDeadline IS NOT NULL AND s.ActualArrival <= s.DeliveryDeadline THEN 1
            WHEN s.DeliveryDeadline IS NULL
             AND oi.ExpectedDeliveryDate IS NOT NULL
             AND DATE(s.ActualArrival) <= oi.ExpectedDeliveryDate THEN 1
            WHEN s.DeliveryDeadline IS NULL AND oi.ExpectedDeliveryDate IS NULL THEN 1
            ELSE 0
          END) AS on_time
    FROM shipment s
    LEFT JOIN shipment_order so ON so.ShipmentID = s.ShipmentID
    LEFT JOIN order_info oi     ON oi.OrderID    = so.OrderID
    WHERE s.ActualArrival IS NOT NULL
      AND s.PlannedDeparture IS NOT NULL
    GROUP BY ym
    ORDER BY ym
");

if (!empty($layer1)) {
    $perfHasRealData = true;
    foreach ($layer1 as $row) {
        $t = (int)$row['total'];
        $o = (int)$row['on_time'];
        $perfByMonth[$row['ym']] = [
            'on_time' => $t > 0 ? round($o / $t * 100, 1) : 0,
            'delayed' => $t > 0 ? round(($t - $o) / $t * 100, 1) : 0,
            'total'   => $t,
        ];
    }
}

// --- Layer 3 fallback: use ShippingStatus from order_info ---
// Runs when no ActualArrival data exists at all
if (empty($perfByMonth)) {
    $statusTotals = db_rows($db, "
        SELECT ShippingStatus, COUNT(*) AS cnt
        FROM order_info
        GROUP BY ShippingStatus
    ");

    $totalAll     = 0;
    $deliveredAll = 0;
    foreach ($statusTotals as $st) {
        $totalAll += (int)$st['cnt'];
        if (in_array($st['ShippingStatus'], ['Delivered', 'Shipped'])) {
            $deliveredAll += (int)$st['cnt'];
        }
    }

    // No orders at all → use fixed 80/20 fallback ratio
    if ($totalAll === 0) {
        $onTimePct  = 80.0;
        $delayedPct = 20.0;
    } else {
        $onTimePct  = round($deliveredAll / $totalAll * 100, 1);
        $delayedPct = round(100 - $onTimePct, 1);
    }

    // Spread this ratio evenly across 6 months so the chart is never empty
    for ($mi = 0; $mi < 6; $mi++) {
        $tempDate = new DateTime($startDateStr);
        $tempDate->modify("+$mi months");
        $ym = $tempDate->format('Y-m');
        $perfByMonth[$ym] = [
            'on_time' => $onTimePct,
            'delayed' => $delayedPct,
            'total'   => $totalAll > 0 ? (int)round($totalAll / 6) : 0,
        ];
    }
    $perfHasRealData = false; // estimated, not from real arrival data
}

// ── Always build exactly 6 months starting from $startDateStr ────────────────
// This guarantees the chart always shows 6 bars regardless of how many months
// have real data in $perfByMonth.
$revenue = ['labels' => [], 'vnd' => []];
$perf    = ['labels' => [], 'on_time' => [], 'delayed' => [], 'totals' => []];

$loopCursor = new DateTime($startDateStr);
for ($i = 0; $i < 6; $i++) {
    $key = month_key($loopCursor);                   // e.g. "2026-01"
    $label = $loopCursor->format('M Y');             // e.g. "Jan 2026"

    $revenue['labels'][] = $label;
    $revenue['vnd'][]    = $revenueByMonth[$key] ?? 0;

    $perf['labels'][]    = $label;
    $perf['on_time'][]   = $perfByMonth[$key]['on_time'] ?? 0;
    $perf['delayed'][]   = $perfByMonth[$key]['delayed'] ?? 0;
    $perf['totals'][]    = $perfByMonth[$key]['total']   ?? 0;

    $loopCursor->modify('+1 month');
}

$orders = db_rows($db, "
    SELECT
      oi.OrderID,
      oi.PickupAddress,
      oi.OrderDate,
      oi.ExpectedDeliveryDate,
      oi.ShippingStatus,
      bp.PartyName AS CustomerName,
      r.RouteName,
      r.StartLocation,
      r.EndLocation,
      COALESCE(SUM(od.Weight), 0) AS Weight
    FROM order_info oi
    LEFT JOIN business_party bp ON bp.PartyID = oi.CustomerID
    LEFT JOIN shipment_order so ON so.OrderID = oi.OrderID
    LEFT JOIN shipment s ON s.ShipmentID = so.ShipmentID
    LEFT JOIN route r ON r.RouteID = s.RouteID
    LEFT JOIN order_detailed od ON od.OrderID = oi.OrderID
    GROUP BY oi.OrderID, oi.PickupAddress, oi.OrderDate, oi.ExpectedDeliveryDate, oi.ShippingStatus, bp.PartyName, r.RouteName, r.StartLocation, r.EndLocation
    ORDER BY oi.OrderDate DESC
    LIMIT 6
");

$exceptions = db_rows($db, "
    SELECT
      oe.ExceptionID,
      oe.IssueType,
      oe.Description,
      oe.SeverityLevel,
      oe.ApprovalStatus,
      oe.CreatedAt,
      oe.ShipmentID,
      bp.PartyName AS CarrierName
    FROM operational_exception oe
    LEFT JOIN business_party bp ON bp.PartyID = oe.CarrierID
    WHERE oe.ApprovalStatus IN ('Pending', 'Under Investigation')
    ORDER BY FIELD(oe.SeverityLevel, 'Critical', 'High', 'Medium', 'Low'), oe.CreatedAt DESC
    LIMIT 5
");

$shipmentMix = db_rows($db, "
    SELECT Status, COUNT(*) AS total
    FROM shipment
    GROUP BY Status
    ORDER BY total DESC
");
$totalShipmentsForMix = array_sum(array_map(fn($m) => (int)$m['total'], $shipmentMix));

open_page('KPI Dashboard', 'dashboard', [['label' => 'Manager'], ['label' => 'KPI Dashboard']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">KPI Dashboard</h1>
    <p class="page-subtitle">Live database overview for <?= date('F Y', strtotime($maxDbDate)) ?> &middot; Updated <?= date('H:i') ?></p>
  </div>
  <div class="page-actions">
    <a href="../manager/reports.php" class="btn btn-outline btn-sm">Full Report</a>
    <a href="../manager/cost_analysis.php" class="btn btn-primary btn-sm">Cost Analysis</a>
  </div>
</div>

<div class="stats-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
  <div class="stat-card navy">
    <div class="stat-icon">TO</div>
    <div class="stat-body">
      <div class="stat-value"><?= number_format($totalOrders) ?></div>
      <div class="stat-label">Total Orders</div>
      <div class="stat-trend neutral"><?= number_format($pendingOrders) ?> pending / processing</div>
    </div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">DL</div>
    <div class="stat-body">
      <div class="stat-value"><?= number_format($deliveredOrders) ?></div>
      <div class="stat-label">Delivered Orders</div>
      <div class="stat-trend neutral"><?= $totalOrders > 0 ? round(($deliveredOrders / $totalOrders) * 100, 1) : 0 ?>% of orders</div>
    </div>
  </div>
  <div class="stat-card olive">
    <div class="stat-icon">IT</div>
    <div class="stat-body">
      <div class="stat-value"><?= number_format($activeOrders) ?></div>
      <div class="stat-label">Active Orders</div>
      <div class="stat-trend neutral">In transit or shipped</div>
    </div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon">OT</div>
    <div class="stat-body">
      <div class="stat-value"><?= $onTimeRate ?>%</div>
      <div class="stat-label">On-Time Rate</div>
      <div class="stat-trend neutral"><?= number_format($onTimeTotal) ?> delivered shipments measured</div>
    </div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon">FU</div>
    <div class="stat-body">
      <div class="stat-value"><?= $fleetUtilization ?>%</div>
      <div class="stat-label">Fleet Utilization</div>
      <div class="stat-trend neutral"><?= number_format($assetsInUse) ?> / <?= number_format($totalAssets) ?> assets in use</div>
    </div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon">EX</div>
    <div class="stat-body">
      <div class="stat-value"><?= number_format($openExceptions) ?></div>
      <div class="stat-label">Open Exceptions</div>
      <div class="stat-trend neutral">Pending manager review</div>
    </div>
  </div>
</div>

<?php if (!empty($_GET['debug'])): ?>
<div class="alert alert-info mt-16" style="font-family:monospace;font-size:11px;white-space:pre-wrap;line-height:1.5;">
<strong>DEBUG perf $perf:</strong>
<?= json_encode($perf, JSON_PRETTY_PRINT) ?>

<strong>$perfByMonth keys:</strong> [<?= implode(', ', array_keys($perfByMonth)) ?>]  |  startDateStr: <?= $startDateStr ?>  |  perfHasReal: <?= $perfHasRealData ? 'YES' : 'NO' ?>

<strong>Shipments WITH ActualArrival (sample):</strong>
<?php $dbg = db_rows($db, "SELECT ShipmentID, DATE(PlannedDeparture) pd, DATE(ActualArrival) aa, DATE(DeliveryDeadline) dd, Status FROM shipment WHERE ActualArrival IS NOT NULL LIMIT 5"); echo json_encode($dbg); ?>

<strong>order_info ShippingStatus counts:</strong>
<?php $dbg2 = db_rows($db, "SELECT ShippingStatus, COUNT(*) n FROM order_info GROUP BY ShippingStatus"); echo json_encode($dbg2); ?>
</div>
<?php endif; ?>

<div class="grid-2 mt-24">
  <div class="card">
    <div class="card-header flex-between">
      <div>
        <h3 class="card-title">Monthly Revenue (VND)</h3>
        <p class="page-subtitle" style="margin-top:2px;font-size:11px;">Last 6 months — Total revenue</p>
      </div>
      <span class="badge badge-green"><?= number_format($monthRevenue * 25400, 0, ',', '.') ?> ₫ this month</span>
    </div>
    <div class="card-body" style="position: relative; height: 350px; width: 100%;">
      <canvas id="revenueChart"></canvas>
    </div>
  </div>

  <div class="card">
    <div class="card-header flex-between">
      <div>
        <h3 class="card-title">Delivery Performance</h3>
        <p class="page-subtitle" style="margin-top:2px;font-size:11px;">Last 6 months — On-time vs. Delayed (%)</p>
      </div>
      <?php if (!$perfHasRealData): ?>
        <span class="badge badge-yellow" title="No ActualArrival data — estimated from order status">⚠ Estimated</span>
      <?php endif; ?>
    </div>
    <div class="card-body" style="position: relative; height: 350px; width: 100%;">
      <canvas id="perfChart"></canvas>
    </div>
  </div>
</div>

<div class="grid-2 mt-24">
  <div class="card" style="display:flex; flex-direction:column; height: 100%;">
    <div class="card-header flex-between">
      <h3 class="card-title">Exceptions Needing Review</h3>
      <a href="../manager/exceptions.php" class="btn btn-ghost btn-sm">Manage</a>
    </div>
    <div class="card-body p-0" style="flex-grow: 1;">
      <?php if (empty($exceptions)): ?>
        <div class="empty-state" style="padding:28px 18px;">
          <div class="empty-title">No open exceptions</div>
          <div class="empty-msg">All operational exceptions are already approved or closed.</div>
        </div>
      <?php else: ?>
        <?php foreach ($exceptions as $exception): ?>
          <div class="activity-item" style="padding:12px 16px;">
            <div class="activity-body">
              <div class="flex-between">
                <div>
                  <strong>EXC<?= str_pad((string)$exception['ExceptionID'], 4, '0', STR_PAD_LEFT) ?></strong>
                  <span class="td-muted ml-8">&middot; SHP<?= str_pad((string)$exception['ShipmentID'], 4, '0', STR_PAD_LEFT) ?></span>
                </div>
                <?= manager_badge($exception['ApprovalStatus'] ?? 'Pending') ?>
              </div>
              
              <div class="flex-between mt-4" style="align-items: center;">
                <div class="td-muted font-xs" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; min-width: 0; padding-right: 12px;" title="<?= htmlspecialchars($exception['Description'] ?? '') ?>">
                  <strong style="color: #444;"><?= htmlspecialchars($exception['IssueType'] ?? 'Exception') ?>:</strong> 
                  <?= htmlspecialchars($exception['Description'] ?? '') ?>
                </div>
                <span class="td-muted font-xs" style="white-space: nowrap; flex-shrink: 0;">
                  <?= htmlspecialchars(date('M d, H:i', strtotime($exception['CreatedAt']))) ?>
                </span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="card" style="display:flex; flex-direction:column; height: 100%;">
    <div class="card-header">
      <h3 class="card-title">Shipment Status Mix</h3>
    </div>
    <div class="card-body" style="display: flex; justify-content: center; align-items: center; height: 320px; width: 100%;">
      <div style="position: relative; height: 100%; width: 100%; max-width: 450px;">
        <canvas id="mixPieChart"></canvas>
      </div>
    </div>
  </div>
</div> <div class="mt-24">
  <div class="card">
    <div class="card-header flex-between">
      <h3 class="card-title">Recent Orders</h3>
      <a href="../operations/shipments.php" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div class="table-wrapper" style="border:0;box-shadow:none;border-radius:0;">
      <table>
        <thead>
          <tr>
            <th>Order</th>
            <th>Customer</th>
            <th>Route</th>
            <th>Order Date</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
            <tr><td colspan="5" class="text-center text-muted">No orders found.</td></tr>
          <?php else: ?>
            <?php foreach ($orders as $order): ?>
              <tr>
                <td class="font-bold">ORD<?= str_pad((string)$order['OrderID'], 4, '0', STR_PAD_LEFT) ?></td>
                <td>
                  <div class="font-medium"><?= htmlspecialchars($order['CustomerName'] ?? 'Unknown customer') ?></div>
                  <div class="td-muted"><?= number_format((float)$order['Weight'], 2) ?> kg</div>
                </td>
                <td>
                  <div class="truncate" style="max-width:220px;"><?= htmlspecialchars($order['RouteName'] ?? 'Not assigned') ?></div>
                  <div class="td-muted truncate" style="max-width:220px;">
                    <?= htmlspecialchars($order['StartLocation'] ?? $order['PickupAddress'] ?? '') ?>
                    <?php if (!empty($order['EndLocation'])): ?>
                      &rarr; <?= htmlspecialchars($order['EndLocation']) ?>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="td-muted" style="white-space:nowrap;"><?= htmlspecialchars(date('Y-m-d', strtotime($order['OrderDate']))) ?></td>
                <td><?= manager_badge($order['ShippingStatus'] ?? 'Unknown') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
window.addEventListener('load', function() {
  const revenueData = <?= json_encode($revenue, JSON_NUMERIC_CHECK) ?>;
  const USD_TO_VND  = 25400;

  // Convert USD amounts stored in DB → VND for display
  const revenueVnd = revenueData.vnd.map(v => v * USD_TO_VND);

  new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
      labels: revenueData.labels,
      datasets: [{
        label: 'Revenue',
        data: revenueVnd,
        borderColor: '#0C2840',
        backgroundColor: 'rgba(12,40,64,0.08)',
        borderWidth: 2.5,
        pointBackgroundColor: '#0C2840',
        pointRadius: 4,
        fill: true,
        tension: 0.35
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => {
              const v = ctx.raw;
              if (v >= 1e9) return ' ' + (v / 1e9).toFixed(2) + 'B ₫';
              if (v >= 1e6) return ' ' + (v / 1e6).toFixed(1) + 'M ₫';
              return ' ' + Number(v).toLocaleString('en-US') + ' ₫';
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            callback: val => {
              if (val >= 1e9) return (val / 1e9).toFixed(1) + 'B ₫';
              if (val >= 1e6) return (val / 1e6).toFixed(0) + 'M ₫';
              return Number(val).toLocaleString('en-US');
            }
          },
          grid: { color: 'rgba(0,0,0,0.05)' }
        },
        x: { grid: { display: false } }
      }
    }
  });

  // ── Shipment mix pie (Updated to Doughnut chart) ────────────────────────────────────────────────────────
  const mixDataRaw = <?= json_encode($shipmentMix, JSON_NUMERIC_CHECK) ?>;
  const mixLabels  = mixDataRaw.map(d => d.Status);
  const mixValues  = mixDataRaw.map(d => d.total);

  new Chart(document.getElementById('mixPieChart'), {
    type: 'doughnut',
    data: {
      labels: mixLabels,
      datasets: [{ 
        data: mixValues, 
        backgroundColor: ['#6B8C3E','#E8B84B','#3A5361','#C0392B','#1a5fa8','#9BA8AD'], // Cùng bảng màu với báo cáo
        borderWidth: 2 
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'right' } // Hiển thị chú thích bên phải
      }
    }
  });

  // ── Delivery Performance — grouped bar, fixed 6 months ─────────────────────
  const perfData    = <?= json_encode($perf, JSON_NUMERIC_CHECK) ?>;
  const perfHasReal = <?= $perfHasRealData ? 'true' : 'false' ?>;

  // Always use all 6 months (PHP already guarantees exactly 6 entries)
  const perfLabelsRaw = perfData.labels;   // ["Jan 2026", "Feb 2026", ...]
  const perfOnTime    = perfData.on_time;
  const perfDelayed   = perfData.delayed;
  const perfTotals    = perfData.totals;

  const perfLabels = perfLabelsRaw;

  new Chart(document.getElementById('perfChart'), {
    type: 'bar',
    data: {
      labels: perfLabels,
      datasets: [
        {
          label: 'On-time',
          data: perfOnTime,
          backgroundColor: 'rgba(107,140,62,0.88)',
          borderRadius: 6,
          borderSkipped: false,
          barPercentage: 0.55,
          categoryPercentage: 0.72
        },
        {
          label: 'Delayed',
          data: perfDelayed,
          backgroundColor: 'rgba(192,57,43,0.82)',
          borderRadius: 6,
          borderSkipped: false,
          barPercentage: 0.55,
          categoryPercentage: 0.72
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: {
          position: 'top',
          labels: {
            usePointStyle: true,
            pointStyle: 'rectRounded',
            padding: 18,
            font: { size: 12, family: "'Montserrat', sans-serif" }
          }
        },
        tooltip: {
          callbacks: {
            title: ctx => perfLabelsRaw[ctx[0].dataIndex] ?? ctx[0].label,
            label: ctx => {
              const v     = ctx.raw;
              const total = perfTotals[ctx.dataIndex] ?? 0;
              const note  = !perfHasReal
                ? ' (estimated from order status)'
                : (total > 0 ? ` — ${total} shipment${total !== 1 ? 's' : ''}` : '');
              return `  ${ctx.dataset.label}: ${v}%${note}`;
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          max: 100,
          ticks: { callback: val => val + '%', font: { size: 11 }, stepSize: 20 },
          grid: { color: 'rgba(0,0,0,0.06)' }
        },
        x: {
          grid: { display: false }
        }
      }
    }
  });
});
</script>

<?php
close_page();
$db->close();
?>