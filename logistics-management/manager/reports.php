<?php
ob_start();

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/db.php';
auth_require('manager');

$db = get_db();

// --------------------------------------------------------------------------
// 0. HỆ THỐNG GHI LOG LỖI
// --------------------------------------------------------------------------
global $sql_errors;
$sql_errors = [];

function db_value(mysqli $db, string $sql, $default = 0) {
    global $sql_errors;
    $res = $db->query($sql);
    if (!$res) {
        $sql_errors[] = "Lỗi SQL: " . $db->error . " | Truy vấn: " . $sql;
        return $default;
    }
    $row = $res->fetch_row();
    return $row ? ($row[0] ?? $default) : $default;
}

function db_rows(mysqli $db, string $sql): array {
    global $sql_errors;
    $rows = [];
    $res = $db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) $rows[] = $row;
    } else {
        $sql_errors[] = "Lỗi SQL: " . $db->error . " | Truy vấn: " . $sql;
    }
    return $rows;
}

// --------------------------------------------------------------------------
// HELPER: render badge màu theo status (hỗ trợ mixed-case từ DB)
// --------------------------------------------------------------------------
function render_status_badge(string $status): string {
    $map = [
        'delivered'  => ['green',  'Delivered'],
        'in transit' => ['yellow', 'In Transit'],
        'pending'    => ['gray',   'Pending'],
        'delayed'    => ['red',    'Delayed'],
        'cancelled'  => ['gray',   'Cancelled'],
        'scheduled'  => ['blue',   'Scheduled'],
        'paid'       => ['green',  'Paid'],
        'unpaid'     => ['red',    'Unpaid'],
    ];
    $key   = strtolower(trim($status));
    $color = $map[$key][0] ?? 'gray';
    $label = $map[$key][1] ?? htmlspecialchars($status);
    return '<span class="badge badge-' . $color . '">' . $label . '</span>';
}


// --------------------------------------------------------------------------
// SCHEMA THỰC TẾ (tms_g7)
// --------------------------------------------------------------------------
// shipment  : ShipmentID, RouteID, AssetID, Status (varchar: 'Delivered','In Transit','Delayed',...),
//             PlannedDeparture, DeliveryDeadline, EstimatedArrival, ActualDeparture, ActualArrival
//             *** KHÔNG có CreatedAt ***  => dùng PlannedDeparture làm date col
//
// invoice   : InvoiceID, OrderID, BilledPartyID, InvoiceType, UserID,
//             TotalPreAmount, TaxRate, FinalAmount, IssueDate, Note
//             *** KHÔNG có Status ***  => xác định PAID qua payment_transaction
//
// payment_transaction : PaymentID, InvoiceID, AmountPaid, ReferenceCode, PaymentDate

// --------------------------------------------------------------------------
// 1. NHẬN THAM SỐ TỪ FORM LỌC
// --------------------------------------------------------------------------
$reportType   = $_GET['report_type'] ?? 'operational';
$startDate    = $_GET['start_date'] ?? '';
$endDate      = $_GET['end_date'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$isExport     = isset($_GET['export']) && $_GET['export'] === 'csv';

// --------------------------------------------------------------------------
// 2. CỘT CỐ ĐỊNH CHO SHIPMENT
// --------------------------------------------------------------------------
$shipmentIdCol     = 'ShipmentID';
$dateCol           = 'PlannedDeparture';   // cột ngày duy nhất hợp lệ
$shipmentStatusCol = 'Status';

// --------------------------------------------------------------------------
// 3. CỘT CỐ ĐỊNH CHO INVOICE
// --------------------------------------------------------------------------
$finIdCol     = 'InvoiceID';
$finDateCol   = 'IssueDate';
$finAmountCol = 'FinalAmount';
// Không có cột Status => dùng sub-query qua payment_transaction
// derived_status: 'PAID' nếu có payment, 'UNPAID' nếu chưa

// --------------------------------------------------------------------------
// 4. XÂY DỰNG ĐIỀU KIỆN TRUY VẤN
// --------------------------------------------------------------------------
$whereOps = "1=1";
$whereFin = "1=1";

if (!empty($startDate)) {
    $s = $db->real_escape_string($startDate . ' 00:00:00');
    $whereOps .= " AND s.$dateCol >= '$s'";
    $whereFin .= " AND i.$finDateCol >= '$s'";
}
if (!empty($endDate)) {
    $e = $db->real_escape_string($endDate . ' 23:59:59');
    $whereOps .= " AND s.$dateCol <= '$e'";
    $whereFin .= " AND i.$finDateCol <= '$e'";
}
if (!empty($statusFilter)) {
    $sf = $db->real_escape_string($statusFilter);
    // Operational: Status lưu dạng 'Delivered', 'In Transit', so sánh case-insensitive
    $whereOps .= " AND LOWER(s.$shipmentStatusCol) = LOWER('$sf')";
    // Financial: filter theo derived_status
    if (strtoupper($sf) === 'PAID') {
        $whereFin .= " AND pt.PaymentID IS NOT NULL";
    } elseif (strtoupper($sf) === 'UNPAID') {
        $whereFin .= " AND pt.PaymentID IS NULL";
    }
}

// Tùy chỉnh nhóm thời gian
$dayDiff = 365;
if (!empty($startDate) && !empty($endDate)) {
    $dayDiff = (strtotime($endDate) - strtotime($startDate)) / 86400;
}
$groupSortFormat    = ($dayDiff <= 31) ? '%Y-%m-%d' : '%Y-%m';
$groupDisplayFormat = ($dayDiff <= 31) ? '%d/%m'    : '%m/%Y';

$tableData    = [];
$mixLabels    = [];
$mixValues    = [];
$trendLabels  = [];
$trendDataset1 = [];
$trendDataset2 = [];
$stat1 = $stat2 = $stat3 = 0;

// --------------------------------------------------------------------------
// 5. KÉO DỮ LIỆU
// --------------------------------------------------------------------------
if ($reportType === 'operational') {

    // ── Stat cards ──────────────────────────────────────────────────────────
    $stat1 = db_value($db,
        "SELECT COUNT(*) FROM shipment s WHERE $whereOps", 0);

    $stat2 = db_value($db,
        "SELECT COUNT(*) FROM shipment s
         WHERE LOWER(s.$shipmentStatusCol) = 'delivered' AND $whereOps", 0);

    $stat3 = db_value($db,
        "SELECT COUNT(*) FROM shipment s
         WHERE LOWER(s.$shipmentStatusCol) = 'delayed' AND $whereOps", 0);

    // ── Donut: Status Mix ────────────────────────────────────────────────────
    $mixResult = db_rows($db,
        "SELECT s.$shipmentStatusCol AS StatusKey, COUNT(*) AS cnt
         FROM shipment s WHERE $whereOps
         GROUP BY s.$shipmentStatusCol");
    foreach ($mixResult as $row) {
        $mixLabels[] = $row['StatusKey'] ?? 'Unknown';
        $mixValues[] = (int)$row['cnt'];
    }

    // ── Bar trend: Delivered vs Delayed ─────────────────────────────────────
    $perfQuery = "
        SELECT DATE_FORMAT(s.$dateCol, '$groupSortFormat')    AS sort_key,
               DATE_FORMAT(s.$dateCol, '$groupDisplayFormat') AS display_label,
               SUM(CASE WHEN LOWER(s.$shipmentStatusCol) = 'delivered' THEN 1 ELSE 0 END) AS on_time,
               SUM(CASE WHEN LOWER(s.$shipmentStatusCol) = 'delayed'   THEN 1 ELSE 0 END) AS delayed_count
        FROM shipment s
        WHERE $whereOps
        GROUP BY DATE_FORMAT(s.$dateCol, '$groupSortFormat'),
                 DATE_FORMAT(s.$dateCol, '$groupDisplayFormat')
        ORDER BY DATE_FORMAT(s.$dateCol, '$groupSortFormat') ASC
    ";
    $perfResult   = db_rows($db, $perfQuery);
    $trendLabels  = array_column($perfResult, 'display_label');
    $trendDataset1 = array_column($perfResult, 'on_time');
    $trendDataset2 = array_column($perfResult, 'delayed_count');

    // ── Table preview ────────────────────────────────────────────────────────
    $tableData = db_rows($db,
        "SELECT s.$shipmentIdCol          AS EntityID,
                s.$dateCol                AS DateVal,
                s.$shipmentStatusCol      AS StatusVal
         FROM shipment s
         WHERE $whereOps
         ORDER BY s.$dateCol DESC
         LIMIT 500");

} else {

    // ── Financial: dùng LEFT JOIN payment_transaction để suy ra PAID/UNPAID ──
    // derived_status: PAID nếu có ít nhất 1 payment record, UNPAID nếu không

    $paidJoin  = "LEFT JOIN payment_transaction pt ON pt.InvoiceID = i.$finIdCol";

    // Stat cards
    $stat1 = db_value($db,
        "SELECT COALESCE(SUM(i.$finAmountCol), 0)
         FROM invoice i $paidJoin
         WHERE $whereFin", 0);

    $stat2 = db_value($db,
        "SELECT COALESCE(SUM(i.$finAmountCol), 0)
         FROM invoice i $paidJoin
         WHERE pt.PaymentID IS NOT NULL AND $whereFin", 0);

    $stat3 = $stat1 - $stat2;

    // Donut: PAID vs UNPAID breakdown by amount
    $mixResult = db_rows($db,
        "SELECT
             CASE WHEN pt.PaymentID IS NOT NULL THEN 'Paid' ELSE 'Unpaid' END AS StatusKey,
             COALESCE(SUM(i.$finAmountCol), 0) AS val
         FROM invoice i $paidJoin
         WHERE $whereFin
         GROUP BY StatusKey");
    foreach ($mixResult as $row) {
        $mixLabels[] = $row['StatusKey'];
        $mixValues[] = (float)$row['val'];
    }

    // Bar trend: Paid vs Unpaid over time
    $revQuery = "
        SELECT DATE_FORMAT(i.$finDateCol, '$groupSortFormat')    AS sort_key,
               DATE_FORMAT(i.$finDateCol, '$groupDisplayFormat') AS display_label,
               SUM(CASE WHEN pt.PaymentID IS NOT NULL THEN i.$finAmountCol ELSE 0 END) AS paid,
               SUM(CASE WHEN pt.PaymentID IS NULL     THEN i.$finAmountCol ELSE 0 END) AS unpaid
        FROM invoice i $paidJoin
        WHERE $whereFin
        GROUP BY DATE_FORMAT(i.$finDateCol, '$groupSortFormat'),
                 DATE_FORMAT(i.$finDateCol, '$groupDisplayFormat')
        ORDER BY DATE_FORMAT(i.$finDateCol, '$groupSortFormat') ASC
    ";
    $revResult     = db_rows($db, $revQuery);
    $trendLabels   = array_column($revResult, 'display_label');
    $trendDataset1 = array_column($revResult, 'paid');
    $trendDataset2 = array_column($revResult, 'unpaid');

    // Table preview
    $tableData = db_rows($db,
        "SELECT i.$finIdCol       AS EntityID,
                i.BilledPartyID   AS DateVal,
                i.$finAmountCol   AS AmountVal,
                CASE WHEN pt.PaymentID IS NOT NULL THEN 'PAID' ELSE 'UNPAID' END AS StatusVal
         FROM invoice i $paidJoin
         WHERE $whereFin
         ORDER BY i.$finDateCol DESC
         LIMIT 500");
}

// --------------------------------------------------------------------------
// 6. XUẤT EXCEL (CSV)
// --------------------------------------------------------------------------
if ($isExport) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Report_' . ucfirst($reportType) . '_' . date('Ymd_Hi') . '.csv');

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM

    if ($reportType === 'operational') {
        fputcsv($output, ['Shipment ID', 'Planned Departure', 'Status']);
        foreach ($tableData as $row)
            fputcsv($output, [
                'SHP' . str_pad($row['EntityID'], 4, '0', STR_PAD_LEFT),
                $row['DateVal'],
                $row['StatusVal']
            ]);
    } else {
        fputcsv($output, ['Invoice ID', 'Billed Party ID', 'Amount (VND)', 'Status']);
        foreach ($tableData as $row)
            fputcsv($output, [
                'INV' . str_pad($row['EntityID'], 4, '0', STR_PAD_LEFT),
                $row['DateVal'],
                $row['AmountVal'],
                $row['StatusVal']
            ]);
    }

    fclose($output);
    exit;
}

// --------------------------------------------------------------------------
// 7. RENDER GIAO DIỆN
// --------------------------------------------------------------------------
open_page('Report Generator', 'reports', [['label' => 'Manager'], ['label' => 'Reports Generator']]);
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="page-header">
  <div>
    <h1 class="page-title">Management Report Generator</h1>
    <p class="page-subtitle">Filter, generate, and export dynamic analytics</p>
  </div>
</div>

<?php if (!empty($sql_errors)): ?>
  <div class="alert alert-danger mt-16" style="background:#fee;color:#c00;border:1px solid #c00;border-radius:8px;padding:16px;">
    <strong>Phát hiện lỗi Truy vấn SQL:</strong>
    <ul style="margin-top:8px;padding-left:20px;">
      <?php foreach ($sql_errors as $err): ?>
        <li><?= htmlspecialchars($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="GET" class="card mt-8" id="reportForm">
  <div class="card-body">
    <div class="grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">

      <div class="form-group">
        <label class="form-label text-sm">Report Type</label>
        <select name="report_type" class="form-control" onchange="document.getElementById('reportForm').submit();">
          <option value="operational" <?= $reportType === 'operational' ? 'selected' : '' ?>>📊 Operational Performance</option>
          <option value="financial"   <?= $reportType === 'financial'   ? 'selected' : '' ?>>💰 Financial Summary</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label text-sm">Date Range (Start)</label>
        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
      </div>

      <div class="form-group">
        <label class="form-label text-sm">Date Range (End)</label>
        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
      </div>

      <div class="form-group">
        <label class="form-label text-sm">Filter by Status</label>
        <select name="status" class="form-control">
          <option value="">-- All Statuses --</option>
          <?php if ($reportType === 'operational'): ?>
            <option value="Delivered"  <?= $statusFilter === 'Delivered'  ? 'selected' : '' ?>>Delivered</option>
            <option value="In Transit" <?= $statusFilter === 'In Transit' ? 'selected' : '' ?>>In Transit</option>
            <option value="Delayed"    <?= $statusFilter === 'Delayed'    ? 'selected' : '' ?>>Delayed</option>
          <?php else: ?>
            <option value="PAID"   <?= strtoupper($statusFilter) === 'PAID'   ? 'selected' : '' ?>>Paid</option>
            <option value="UNPAID" <?= strtoupper($statusFilter) === 'UNPAID' ? 'selected' : '' ?>>Unpaid</option>
          <?php endif; ?>
        </select>
      </div>

    </div>

    <div class="mt-16" style="display:flex;gap:12px;align-items:center;border-top:1px solid var(--border-color);padding-top:16px;">
      <button type="submit" class="btn btn-primary">Generate Report</button>
      <button type="submit" name="export" value="csv" class="btn btn-outline" style="display:flex;gap:8px;align-items:center;">
        📥 Export Excel (CSV)
      </button>
    </div>
  </div>
</form>

<!-- Stat cards -->
<div class="stats-grid mt-16" style="grid-template-columns:repeat(3,minmax(220px,1fr));gap:16px;">
  <?php if ($reportType === 'operational'): ?>
    <div class="stat-card navy"><div class="stat-body">
      <div class="stat-value"><?= number_format($stat1) ?></div>
      <div class="stat-label">Total Shipments</div>
    </div></div>
    <div class="stat-card green"><div class="stat-body">
      <div class="stat-value"><?= number_format($stat2) ?></div>
      <div class="stat-label">Successfully Delivered</div>
    </div></div>
    <div class="stat-card red"><div class="stat-body">
      <div class="stat-value"><?= number_format($stat3) ?></div>
      <div class="stat-label">Delayed Shipments</div>
    </div></div>
  <?php else: ?>
    <div class="stat-card navy"><div class="stat-body">
      <div class="stat-value"><?= number_format($stat1) ?> <span style="font-size:14px">VND</span></div>
      <div class="stat-label">Total Invoiced Volume</div>
    </div></div>
    <div class="stat-card green"><div class="stat-body">
      <div class="stat-value"><?= number_format($stat2) ?> <span style="font-size:14px">VND</span></div>
      <div class="stat-label">Collected Revenue (Paid)</div>
    </div></div>
    <div class="stat-card red"><div class="stat-body">
      <div class="stat-value"><?= number_format($stat3) ?> <span style="font-size:14px">VND</span></div>
      <div class="stat-label">Outstanding Balance</div>
    </div></div>
  <?php endif; ?>
</div>

<!-- Charts -->
<div class="grid-2 mt-16" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><?= $reportType === 'operational' ? 'Status Mix' : 'Financial Status Breakdown' ?></h3>
    </div>
    <div class="card-body" style="position:relative;height:320px;width:100%;">
      <canvas id="mixChart"></canvas>
    </div>
  </div>
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><?= $reportType === 'operational' ? 'Delivery Performance Trend' : 'Revenue Trend' ?></h3>
    </div>
    <div class="card-body" style="position:relative;height:320px;width:100%;">
      <canvas id="trendChart"></canvas>
    </div>
  </div>
</div>

<!-- Data table -->
<div class="card mt-16 mb-24">
  <div class="card-header">
    <h3 class="card-title">Generated Data Preview (Limit 500 rows)</h3>
  </div>
  <div class="card-body p-0">
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <?php if ($reportType === 'operational'): ?>
              <th>Shipment ID</th><th>Planned Departure</th><th>Status</th>
            <?php else: ?>
              <th>Invoice ID</th><th>Billed Party ID</th><th>Amount (VND)</th><th>Status</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($tableData)): ?>
            <tr><td colspan="4" class="text-center text-muted" style="padding:24px;">
              Không có dữ liệu trong khoảng thời gian này.
            </td></tr>
          <?php else: ?>
            <?php foreach ($tableData as $row): ?>
              <tr>
                <?php if ($reportType === 'operational'): ?>
                  <td><strong style="font-family:monospace;">SHP<?= str_pad($row['EntityID'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                  <td class="text-muted"><?= htmlspecialchars($row['DateVal']) ?></td>
                  <td><?= render_status_badge($row['StatusVal'] ?? '') ?></td>
                <?php else: ?>
                  <td><strong style="font-family:monospace;">INV<?= str_pad($row['EntityID'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                  <td class="text-muted"><?= htmlspecialchars($row['DateVal']) ?></td>
                  <td class="font-bold" style="color:var(--c-green)"><?= number_format((float)($row['AmountVal'] ?? 0)) ?></td>
                  <td><?= render_status_badge($row['StatusVal'] ?? 'UNPAID') ?></td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const reportType = "<?= $reportType ?>";

    new Chart(document.getElementById('mixChart'), {
        type: 'doughnut',
        data: {
            labels:   <?= json_encode(empty($mixLabels) ? ['No Data'] : $mixLabels) ?>,
            datasets: [{
                data:        <?= json_encode(empty($mixValues) ? [1] : $mixValues) ?>,
                borderWidth: 2,
                backgroundColor: ['#6B8C3E','#E8B84B','#3A5361','#C0392B','#1a5fa8','#9BA8AD']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'right' } }
        }
    });

    const label1 = reportType === 'operational' ? 'On-Time (Delivered)' : 'Paid (Revenue)';
    const label2 = reportType === 'operational' ? 'Delayed'             : 'Unpaid (Outstanding)';
    const color1 = reportType === 'operational' ? '#2ec4b6' : '#4361ee';
    const color2 = reportType === 'operational' ? '#e71d36' : '#f72585';

    new Chart(document.getElementById('trendChart'), {
        type: 'bar',
        data: {
            labels:   <?= json_encode(empty($trendLabels) ? ['No Data'] : $trendLabels) ?>,
            datasets: [
                { label: label1, data: <?= json_encode(empty($trendDataset1) ? [0] : $trendDataset1) ?>, backgroundColor: color1 },
                { label: label2, data: <?= json_encode(empty($trendDataset2) ? [0] : $trendDataset2) ?>, backgroundColor: color2 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { stacked: false },
                y: { stacked: false, beginAtZero: true }
            },
            plugins: { legend: { position: 'top' } }
        }
    });
});
</script>

<?php
close_page();
$db->close();
?>