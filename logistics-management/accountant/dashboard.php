<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/db.php';
auth_require('accountant');

$db = get_db();

define('USD_TO_VND', 25400);

function fmt_vnd(float $usd): string {
    $vnd = $usd * USD_TO_VND;
    if ($vnd >= 1_000_000_000) return number_format($vnd / 1_000_000_000, 1) . ' B ₫';
    if ($vnd >= 1_000_000)     return number_format($vnd / 1_000_000, 1) . ' M ₫';
    return number_format($vnd, 0) . ' ₫';
}
function fmt_vnd_full(float $usd): string {
    return number_format($usd * USD_TO_VND, 0) . ' ₫';
}

// ── 1. AR Invoice Statistics ───────────────────────────────────────────────
$count_ar_total  = 0;
$total_invoiced  = 0.0;
$count_ar_paid   = 0;
$total_collected = 0.0;

$res = $db->query("SELECT COUNT(*) as cnt, COALESCE(SUM(FinalAmount),0) as total
                   FROM invoice WHERE InvoiceType = 'AR_Receivable'");
if ($res && $row = $res->fetch_assoc()) {
    $count_ar_total = (int)$row['cnt'];
    $total_invoiced = (float)$row['total'];
}

$res = $db->query("
    SELECT COUNT(DISTINCT pt.InvoiceID) as paid_count,
           COALESCE(SUM(pt.AmountPaid), 0) as collected
    FROM payment_transaction pt
    JOIN invoice i ON pt.InvoiceID = i.InvoiceID
    WHERE i.InvoiceType = 'AR_Receivable'
");
if ($res && $row = $res->fetch_assoc()) {
    $count_ar_paid   = (int)$row['paid_count'];
    $total_collected = (float)$row['collected'];
}

$outstanding = $total_invoiced - $total_collected;

// ── 2. AP Payable ─────────────────────────────────────────────────────────
$ap_total = 0.0;
$count_ap = 0;
$res = $db->query("SELECT COUNT(*) as cnt, COALESCE(SUM(FinalAmount),0) as total
                   FROM invoice WHERE InvoiceType = 'AP_Payable'");
if ($res && $row = $res->fetch_assoc()) {
    $count_ap = (int)$row['cnt'];
    $ap_total = (float)$row['total'];
}

// ── 3. Payment Status Classification ─────────────────────────────────────
$status_paid    = 0;
$status_issued  = 0;
$status_overdue = 0;

$res = $db->query("
    SELECT i.InvoiceID, i.IssueDate,
           CASE WHEN pt.PaymentID IS NOT NULL THEN 'PAID'
                WHEN DATEDIFF(CURDATE(), i.IssueDate) > 30 THEN 'OVERDUE'
                ELSE 'ISSUED' END AS PayStatus
    FROM invoice i
    LEFT JOIN payment_transaction pt ON i.InvoiceID = pt.InvoiceID
    WHERE i.InvoiceType = 'AR_Receivable'
    GROUP BY i.InvoiceID, i.IssueDate
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        match($row['PayStatus']) {
            'PAID'    => $status_paid++,
            'OVERDUE' => $status_overdue++,
            default   => $status_issued++,
        };
    }
}
$ar_total_count  = $status_paid + $status_issued + $status_overdue;
$collection_rate = $ar_total_count > 0 ? round($status_paid / $ar_total_count * 100) : 0;

// ── 4. Overdue Invoices ───────────────────────────────────────────────────
$overdue_invoices = [];
$res = $db->query("
    SELECT i.InvoiceID, bp.PartyName AS CustomerName,
           i.IssueDate, i.FinalAmount,
           DATEDIFF(CURDATE(), i.IssueDate) AS DaysOld
    FROM invoice i
    JOIN business_party bp ON i.BilledPartyID = bp.PartyID
    LEFT JOIN payment_transaction pt ON i.InvoiceID = pt.InvoiceID
    WHERE i.InvoiceType = 'AR_Receivable'
      AND pt.PaymentID IS NULL
      AND DATEDIFF(CURDATE(), i.IssueDate) > 30
    GROUP BY i.InvoiceID, bp.PartyName, i.IssueDate, i.FinalAmount
    ORDER BY DaysOld DESC
    LIMIT 5
");
if ($res) {
    while ($row = $res->fetch_assoc()) $overdue_invoices[] = $row;
}
$overdue_count  = count($overdue_invoices);
$overdue_amount = array_sum(array_column($overdue_invoices, 'FinalAmount'));

// ── 5. Latest 5 Invoices ─────────────────────────────────────────────────
$recent_invoices = [];
$res = $db->query("
    SELECT i.InvoiceID, i.InvoiceType, i.IssueDate, i.FinalAmount,
           bp.PartyName,
           CASE WHEN pt.PaymentID IS NOT NULL THEN 'PAID'
                WHEN DATEDIFF(CURDATE(), i.IssueDate) > 30 THEN 'OVERDUE'
                ELSE 'ISSUED' END AS PayStatus
    FROM invoice i
    JOIN business_party bp ON i.BilledPartyID = bp.PartyID
    LEFT JOIN payment_transaction pt ON i.InvoiceID = pt.InvoiceID
    GROUP BY i.InvoiceID, i.InvoiceType, i.IssueDate, i.FinalAmount, bp.PartyName
    ORDER BY i.InvoiceID DESC
    LIMIT 5
");
if ($res) {
    while ($row = $res->fetch_assoc()) $recent_invoices[] = $row;
}

// ── 6. Chart: AR Revenue collected by month (payment_transaction.PaymentDate)
//         + AP Costs by month (invoice.IssueDate for AP_Payable)
// ─────────────────────────────────────────────────────────────────────────
// Step A – pull AR revenue by month (real payment dates)
$ar_by_month = [];
$res = $db->query("
    SELECT DATE_FORMAT(pt.PaymentDate, '%Y-%m') AS MonthKey,
           DATE_FORMAT(pt.PaymentDate, '%m/%Y')  AS MonthLabel,
           SUM(pt.AmountPaid) AS Revenue
    FROM payment_transaction pt
    JOIN invoice i ON pt.InvoiceID = i.InvoiceID
    WHERE i.InvoiceType = 'AR_Receivable'
    GROUP BY MonthKey, MonthLabel
    ORDER BY MonthKey ASC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $ar_by_month[$row['MonthKey']] = [
            'label'   => $row['MonthLabel'],
            'revenue' => round((float)$row['Revenue'] * USD_TO_VND / 1_000_000, 1),
        ];
    }
}

// Step B – pull AP costs by month (IssueDate on AP invoices — no payment record for AP)
$ap_by_month_raw = [];
$res = $db->query("
    SELECT DATE_FORMAT(i.IssueDate, '%Y-%m') AS MonthKey,
           SUM(i.FinalAmount) AS APCost
    FROM invoice i
    WHERE i.InvoiceType = 'AP_Payable'
    GROUP BY MonthKey
    ORDER BY MonthKey ASC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $ap_by_month_raw[$row['MonthKey']] = round((float)$row['APCost'] * USD_TO_VND / 1_000_000, 1);
    }
}

// Step C – luôn dùng 6 tháng gần nhất làm trục X cố định,
//           sau đó điền data thực tế vào (0 nếu không có)
$all_keys = [];
for ($i = 5; $i >= 0; $i--) {
    $all_keys[] = date('Y-m', strtotime("-$i months"));
}

$chart_labels   = [];
$chart_revenue  = [];
$chart_ap_costs = [];

$month_names = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
foreach ($all_keys as $key) {
    [$year, $month] = explode('-', $key);
    $chart_labels[]   = $month_names[(int)$month - 1] . ' ' . $year;
    $chart_revenue[]  = $ar_by_month[$key]['revenue'] ?? 0;
    $chart_ap_costs[] = $ap_by_month_raw[$key]        ?? 0;
}

// ── 7. Top 5 Customers ───────────────────────────────────────────────────
$top_customers = [];
$res = $db->query("
    SELECT bp.PartyName, SUM(pt.AmountPaid) AS TotalPaid,
           COUNT(DISTINCT pt.PaymentID) AS PayCount
    FROM payment_transaction pt
    JOIN invoice i  ON pt.InvoiceID  = i.InvoiceID
    JOIN business_party bp ON i.BilledPartyID = bp.PartyID
    WHERE i.InvoiceType = 'AR_Receivable'
    GROUP BY bp.PartyID, bp.PartyName
    ORDER BY TotalPaid DESC
    LIMIT 5
");
if ($res) {
    while ($row = $res->fetch_assoc()) $top_customers[] = $row;
}
$max_cust_rev = !empty($top_customers) ? (float)$top_customers[0]['TotalPaid'] : 1;

$net_pl = $total_collected - $ap_total;

open_page('Financial Dashboard', 'dashboard', [['label' => 'Financial Dashboard']]);
?>

<!-- ── Page Header ────────────────────────────────────────────────────────── -->
<div class="page-header">
  <div>
    <h1 class="page-title">💹 Financial Dashboard</h1>
    <p class="text-muted" style="margin-top:4px;">
      Overview of invoices, collections and payables &nbsp;·&nbsp;
      Exchange rate: <strong>1 USD = <?= number_format(USD_TO_VND) ?> ₫</strong>
    </p>
  </div>
  <div class="page-actions">
    <a href="../accountant/invoices.php"  class="btn btn-outline btn-sm">🧾 Invoices</a>
    <a href="../accountant/payments.php"  class="btn btn-outline btn-sm">💳 Payments</a>
    <a href="../accountant/reports.php"   class="btn btn-primary  btn-sm">📊 Reports</a>
  </div>
</div>

<?php if ($overdue_count > 0): ?>
<div class="alert alert-danger" style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
  <div>
    <strong>⚠️ <?= $overdue_count ?> Invoices overdue (>30 days):</strong>
    Total payables <strong><?= fmt_vnd($overdue_amount) ?></strong> not yet collected.
    Need to contact customer immediately.
  </div>
  <a href="../accountant/aging_debt.php" class="btn btn-danger btn-sm" style="white-space:nowrap;">View debt report →</a>
</div>
<?php endif; ?>

<!-- ── Quick Actions ──────────────────────────────────────────────────────── -->
<div style="margin-bottom:24px;background:var(--bg-card,#fff);border:1px solid var(--border-color);
            border-radius:14px;padding:18px 20px;
            box-shadow:0 1px 4px rgba(0,0,0,.06);">

  <!-- Section label -->
  <div style="margin-bottom:14px;">
    <span style="font-size:13px;font-weight:700;color:var(--text-primary);">⚡ Quick Actions</span>
  </div>

  <?php
  // Overdue: đỏ đậm nếu có, xanh lá nếu sạch
  $overdue_bg    = $overdue_count > 0 ? 'var(--c-red)'   : 'var(--c-green)';
  $overdue_color = '#fff';
  $overdue_icon  = $overdue_count > 0 ? '🚨'             : '🔔';
  $overdue_sub   = $overdue_count > 0
                     ? $overdue_count . ' overdue · ' . fmt_vnd($overdue_amount)
                     : 'No overdue · All on track';

  $qa = [
    // 1 — Invoices
    [
      'href'  => '../accountant/invoices.php',
      'icon'  => '🧾',
      'label' => 'Manage Invoices',
      'sub'   => $count_ar_total . ' total · ' . $status_paid . ' paid · ' . $status_issued . ' pending',
      'bg'    => 'var(--c-navy-800)',
      'color' => '#fff',
    ],
    // 2 — Payments
    [
      'href'  => '../accountant/payments.php',
      'icon'  => '💳',
      'label' => 'View Payments',
      'sub'   => $count_ar_paid . ' received · ' . fmt_vnd($total_collected) . ' collected',
      'bg'    => 'var(--c-slate-600)',
      'color' => '#fff',
    ],
    // 3 — Reports
    [
      'href'  => '../accountant/reports.php',
      'icon'  => '📊',
      'label' => 'Generate Reports',
      'sub'   => 'P&L · Revenue · Cost summary',
      'bg'    => '#4a6f1e',
      'color' => '#fff',
    ],
    // 4 — Aging / Overdue
    [
      'href'  => '../accountant/aging_debt.php',
      'icon'  => $overdue_icon,
      'label' => 'Overdue Debt',
      'sub'   => $overdue_sub,
      'bg'    => $overdue_count > 0 ? '#fdf1f0' : '#f0f7ee',
      'color' => $overdue_count > 0 ? 'var(--c-red)' : 'var(--c-green)',
      'border'=> $overdue_count > 0 ? '1.5px solid rgba(192,57,43,.25)' : '1.5px solid rgba(107,140,62,.25)',
    ],
    // 5 — Carrier Costs (AP)
    [
      'href'  => '../accountant/carrier_costs.php',
      'icon'  => '🚛',
      'label' => 'Carrier Costs',
      'sub'   => $count_ap . ' AP invoices · ' . fmt_vnd($ap_total) . ' payable',
      'bg'    => 'var(--c-yellow)',
      'color' => 'var(--c-navy-900)',
    ],
    // 6 — Config (ít dùng — xám, cuối cùng)
    [
      'href'  => '../accountant/billing.php',
      'icon'  => '⚙️',
      'label' => 'Service Fee Config',
      'sub'   => 'Charge types · Rates · Methods',
      'bg'    => 'var(--bg-alt,#f4f6f8)',
      'color' => 'var(--text-secondary)',
      'border'=> '1.5px solid var(--border-color)',
    ],
  ];
  ?>

  <!-- 2×3 grid: 3 primary actions (left col) + 3 secondary (right col) -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
    <?php foreach ($qa as $q):
      $border_style = isset($q['border']) ? 'border:' . $q['border'] . ';' : '';
    ?>
    <a href="<?= $q['href'] ?>" style="text-decoration:none;display:flex;">
      <div style="
            background:<?= $q['bg'] ?>;
            color:<?= $q['color'] ?>;
            <?= $border_style ?>
            border-radius:12px;
            padding:16px 18px;
            display:flex;align-items:center;gap:14px;
            width:100%;
            box-shadow:0 1px 4px rgba(0,0,0,.08);
            transition:filter .15s,box-shadow .15s;cursor:pointer;"
           onmouseover="this.style.filter='brightness(.93)';this.style.boxShadow='0 3px 10px rgba(0,0,0,.13)'"
           onmouseout="this.style.filter='brightness(1)';this.style.boxShadow='0 1px 4px rgba(0,0,0,.08)'">

        <!-- Icon -->
        <span style="font-size:28px;line-height:1;flex-shrink:0;"><?= $q['icon'] ?></span>

        <!-- Text -->
        <div style="min-width:0;">
          <div style="font-weight:700;font-size:13.5px;line-height:1.3;
                      white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            <?= $q['label'] ?>
          </div>
          <div style="font-size:11.5px;opacity:.72;margin-top:4px;line-height:1.4;
                      white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
            <?= $q['sub'] ?>
          </div>
        </div>

      </div>
    </a>
    <?php endforeach; ?>
  </div>

</div>

<!-- ── Stat Cards (7 cards, 1 row) ────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:10px;margin-bottom:24px;">

  <div class="stat-card navy" style="padding:14px 12px;">
    <div class="stat-icon" style="font-size:18px;">💰</div>
    <div class="stat-body">
      <div class="stat-value" style="font-size:14px;"><?= fmt_vnd($total_invoiced) ?></div>
      <div class="stat-label" style="font-size:10px;">AR Invoiced</div>
      <div class="stat-trend neutral" style="font-size:10px;"><?= $count_ar_total ?> invoices</div>
    </div>
  </div>

  <div class="stat-card green" style="padding:14px 12px;">
    <div class="stat-icon" style="font-size:18px;">✅</div>
    <div class="stat-body">
      <div class="stat-value" style="font-size:14px;"><?= fmt_vnd($total_collected) ?></div>
      <div class="stat-label" style="font-size:10px;">Collected</div>
      <div class="stat-trend up" style="font-size:10px;"><?= $count_ar_paid ?> payments</div>
    </div>
  </div>

  <div class="stat-card slate" style="padding:14px 12px;">
    <div class="stat-icon" style="font-size:18px;">⏳</div>
    <div class="stat-body">
      <div class="stat-value" style="font-size:14px;"><?= fmt_vnd($outstanding) ?></div>
      <div class="stat-label" style="font-size:10px;">Outstanding</div>
      <div class="stat-trend neutral" style="font-size:10px;"><?= $count_ar_total - $count_ar_paid ?> pending</div>
    </div>
  </div>

  <div class="stat-card red" style="padding:14px 12px;">
    <div class="stat-icon" style="font-size:18px;">🔔</div>
    <div class="stat-body">
      <div class="stat-value" style="font-size:20px;"><?= $overdue_count ?></div>
      <div class="stat-label" style="font-size:10px;">Overdue</div>
      <div class="stat-trend down" style="font-size:10px;">
        <?= $overdue_count > 0 ? fmt_vnd($overdue_amount) : 'All clear' ?>
      </div>
    </div>
  </div>

  <div class="stat-card yellow" style="padding:14px 12px;">
    <div class="stat-icon" style="font-size:18px;">📤</div>
    <div class="stat-body">
      <div class="stat-value" style="font-size:14px;"><?= fmt_vnd($ap_total) ?></div>
      <div class="stat-label" style="font-size:10px;">AP Payable</div>
      <div class="stat-trend neutral" style="font-size:10px;"><?= $count_ap ?> invoices</div>
    </div>
  </div>

  <div class="stat-card <?= $net_pl >= 0 ? 'green' : 'red' ?>" style="padding:14px 12px;">
    <div class="stat-icon" style="font-size:18px;"><?= $net_pl >= 0 ? '📈' : '📉' ?></div>
    <div class="stat-body">
      <div class="stat-value" style="font-size:14px;color:<?= $net_pl >= 0 ? 'var(--c-green)' : 'var(--c-red)' ?>">
        <?= fmt_vnd(abs($net_pl)) ?>
      </div>
      <div class="stat-label" style="font-size:10px;">Net Profit</div>
      <div class="stat-trend <?= $net_pl >= 0 ? 'up' : 'down' ?>" style="font-size:10px;">
        <?= $net_pl >= 0 ? '↑ Profit' : '↓ Loss' ?>
      </div>
    </div>
  </div>

  <div class="stat-card slate" style="padding:14px 12px;">
    <div class="stat-icon" style="font-size:18px;">🧾</div>
    <div class="stat-body">
      <div class="stat-value" style="font-size:20px;"><?= $count_ar_total + $count_ap ?></div>
      <div class="stat-label" style="font-size:10px;">Total Inv.</div>
      <div class="stat-trend neutral" style="font-size:10px;"><?= $collection_rate ?>% collected</div>
    </div>
  </div>

</div>
<!-- ── Charts Row ─────────────────────────────────────────────────────────── -->
<div class="grid-2 mt-16">

  <!-- Donut: AR Invoice Status -->
  <div class="card">
    <div class="card-header flex-between">
      <span class="card-title">AR Invoice Classification</span>
      <span class="badge badge-navy">AR only</span>
    </div>
    <div class="card-body" style="display:flex;align-items:center;gap:28px;">
      <div style="position:relative;width:200px;height:200px;flex-shrink:0;">
        <canvas id="chartDonut" width="200" height="200"></canvas>
        <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none;">
          <div style="font-size:22px;font-weight:800;color:var(--text-primary);line-height:1.1;"><?= $collection_rate ?>%</div>
          <div style="font-size:10px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.6px;">collected</div>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:14px;flex:1;">
        <div class="flex-between">
          <span style="display:flex;align-items:center;gap:8px;">
            <span style="width:12px;height:12px;border-radius:50%;background:#6B8C3E;display:inline-block;"></span>
            Paid
          </span>
          <strong><?= $status_paid ?></strong>
        </div>
        <div class="flex-between">
          <span style="display:flex;align-items:center;gap:8px;">
            <span style="width:12px;height:12px;border-radius:50%;background:#3A5361;display:inline-block;"></span>
            Issued, pending
          </span>
          <strong><?= $status_issued ?></strong>
        </div>
        <div class="flex-between">
          <span style="display:flex;align-items:center;gap:8px;">
            <span style="width:12px;height:12px;border-radius:50%;background:#C0392B;display:inline-block;"></span>
            Overdue
          </span>
          <strong><?= $status_overdue ?></strong>
        </div>
        <div style="border-top:1px solid var(--border-color);padding-top:10px;" class="flex-between">
          <span class="text-muted font-sm">Total AR invoices</span>
          <strong><?= $ar_total_count ?></strong>
        </div>
      </div>
    </div>
  </div>

  <!-- Bar Grouped: Revenue Collected vs AP Carrier Costs -->
  <div class="card">
    <div class="card-header flex-between">
      <span class="card-title">Revenue Collected vs. Carrier Costs (M ₫)</span>
      <div style="display:flex;gap:14px;align-items:center;">
        <span style="display:flex;align-items:center;gap:5px;font-size:11px;font-weight:600;color:var(--text-muted);">
          <span style="width:10px;height:10px;border-radius:2px;background:#6B8C3E;display:inline-block;"></span>AR Collected
        </span>
        <span style="display:flex;align-items:center;gap:5px;font-size:11px;font-weight:600;color:var(--text-muted);">
          <span style="width:10px;height:10px;border-radius:2px;background:#E8B84B;display:inline-block;"></span>AP Costs
        </span>
      </div>
    </div>
    <div class="card-body">
      <canvas id="chartRevenue" style="height:220px;"></canvas>
    </div>
  </div>

</div>


<!-- ── Recent Invoices (full width) ──────────────────────────────────────── -->
<div class="card mt-16">
  <div class="card-header flex-between">
    <span class="card-title">🧾 Recent Invoices</span>
    <a href="../accountant/invoices.php" class="btn btn-ghost btn-sm">View all →</a>
  </div>
  <div class="card-body" style="padding:0;">
    <div class="table-wrapper" style="border:none;box-shadow:none;border-radius:0;">
      <table>
        <thead>
          <tr>
            <th>Invoice</th>
            <th>Partner</th>
            <th>Type</th>
            <th>Issue Date</th>
            <th class="text-right">Amount (₫)</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recent_invoices)): ?>
            <tr><td colspan="6" class="text-center text-muted" style="padding:24px;">No invoices.</td></tr>
          <?php else: ?>
            <?php foreach ($recent_invoices as $inv):
              $t_badge = $inv['InvoiceType'] === 'AP_Payable'
                ? '<span class="badge badge-yellow">AP</span>'
                : '<span class="badge badge-navy">AR</span>';
            ?>
            <tr style="<?= $inv['PayStatus'] === 'OVERDUE' ? 'background:rgba(192,57,43,0.04);' : '' ?>">
              <td>
                <a href="../accountant/invoice_detail.php?id=<?= $inv['InvoiceID'] ?>"
                   class="font-bold" style="color:var(--c-navy-800);">
                  INV-<?= str_pad($inv['InvoiceID'], 4, '0', STR_PAD_LEFT) ?>
                </a>
              </td>
              <td class="font-bold"><?= htmlspecialchars($inv['PartyName']) ?></td>
              <td><?= $t_badge ?></td>
              <td class="td-muted"><?= $inv['IssueDate'] ?></td>
              <td class="text-right font-bold"><?= fmt_vnd($inv['FinalAmount']) ?></td>
              <td><?= status_badge($inv['PayStatus']) ?></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── Top Customers + Overdue Detail (50/50) ────────────────────────────── -->
<div class="grid-2 mt-16" style="align-items:stretch;">

  <!-- Top Customers -->
  <div class="card" style="display:flex;flex-direction:column;">
    <div class="card-header flex-between">
      <span class="card-title">🏆 Top Customers by Revenue</span>
      <span class="badge badge-navy">All time</span>
    </div>
    <div class="card-body">
      <?php if (empty($top_customers)): ?>
        <div class="text-muted text-center" style="padding:20px;">No data available.</div>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:16px;">
          <?php
          $rank_colors = ['#E8B84B','#9BA8AD','#CD7F32','#6B8C3E','#3A5361'];
          foreach ($top_customers as $i => $cust):
            $pct = round((float)$cust['TotalPaid'] / $max_cust_rev * 100);
          ?>
          <div>
            <div class="flex-between" style="margin-bottom:6px;">
              <div style="display:flex;align-items:center;gap:10px;">
                <span style="width:24px;height:24px;border-radius:50%;
                             background:<?= $rank_colors[$i] ?? '#9BA8AD' ?>;
                             color:<?= $i === 0 ? 'var(--c-navy-900)' : '#fff' ?>;
                             font-size:11px;font-weight:800;
                             display:flex;align-items:center;justify-content:center;
                             flex-shrink:0;"><?= $i+1 ?></span>
                <span class="font-bold" style="font-size:13px;"><?= htmlspecialchars($cust['PartyName']) ?></span>
              </div>
              <span class="font-bold" style="font-size:13px;color:var(--c-green);"><?= fmt_vnd((float)$cust['TotalPaid']) ?></span>
            </div>
            <div class="progress-bar" style="height:7px;border-radius:4px;">
              <div style="width:<?= $pct ?>%;height:100%;border-radius:4px;
                           background:linear-gradient(90deg,<?= $rank_colors[$i] ?? '#9BA8AD' ?>,<?= $rank_colors[$i] ?? '#9BA8AD' ?>99);
                           transition:width .4s;"></div>
            </div>
            <div class="text-muted" style="font-size:11px;margin-top:4px;">
              <?= $cust['PayCount'] ?> payment(s) · <?= $pct ?>% of top customer
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Overdue Detail -->
  <div class="card" style="display:flex;flex-direction:column;">
    <div class="card-header flex-between">
      <span class="card-title">🚨 Overdue Invoices Details</span>
      <a href="../accountant/aging_debt.php" class="btn btn-ghost btn-sm">Full report →</a>
    </div>

    <?php if (empty($overdue_invoices)): ?>
    <!-- Empty state compact -->
    <div class="card-body" style="display:flex;flex-direction:column;align-items:center;
                                  justify-content:space-between;padding:40px 24px;gap:12px;text-align:center;flex:1;">
      <div style="width:56px;height:56px;border-radius:14px;background:var(--c-green-bg);
                  display:flex;align-items:center;justify-content:center;font-size:26px;">✅</div>
      <div style="font-weight:700;font-size:15px;color:var(--text-primary);">No Overdue Invoices</div>
      <div style="font-size:13px;color:var(--text-muted);max-width:220px;line-height:1.5;">
        All payables are within payment terms.
      </div>
      <!-- Mini summary bên dưới -->
      <div style="margin-top:8px;width:100%;display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div style="background:var(--c-green-bg);border-radius:8px;padding:12px;text-align:center;">
          <div style="font-size:20px;font-weight:800;color:var(--c-green);"><?= $status_paid ?></div>
          <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">Invoices Paid</div>
        </div>
        <div style="background:var(--bg-alt,#f4f9f9);border-radius:8px;padding:12px;text-align:center;">
          <div style="font-size:20px;font-weight:800;color:var(--c-slate-600);"><?= $status_issued ?></div>
          <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">Pending</div>
        </div>
      </div>
      <a href="../accountant/aging_debt.php"
         class="btn btn-outline btn-sm" style="margin-top:4px;width:100%;justify-content:center;">
        View Aging Report →
      </a>
    </div>

    <?php else: ?>
    <div class="card-body" style="padding:0;">
      <div class="table-wrapper" style="border:none;box-shadow:none;border-radius:0;">
        <table>
          <thead>
            <tr>
              <th>Invoice</th>
              <th>Customer</th>
              <th>Issue Date</th>
              <th class="text-right">Amount</th>
              <th>Days Late</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($overdue_invoices as $ov): ?>
            <tr style="background:rgba(192,57,43,0.04);">
              <td>
                <a href="../accountant/invoice_detail.php?id=<?= $ov['InvoiceID'] ?>"
                   class="font-bold" style="color:var(--c-red);">
                  INV-<?= str_pad($ov['InvoiceID'], 4, '0', STR_PAD_LEFT) ?>
                </a>
              </td>
              <td class="font-bold truncate" style="max-width:120px;" title="<?= htmlspecialchars($ov['CustomerName']) ?>">
                <?= htmlspecialchars($ov['CustomerName']) ?>
              </td>
              <td class="td-muted"><?= $ov['IssueDate'] ?></td>
              <td class="text-right font-bold" style="color:var(--c-red);">
                <?= fmt_vnd((float)$ov['FinalAmount']) ?>
              </td>
              <td>
                <?php
                $d = (int)$ov['DaysOld'];
                $urgency = $d > 60 ? 'var(--c-red)' : ($d > 45 ? '#c0700b' : 'var(--c-yellow)');
                ?>
                <span style="color:<?= $urgency ?>;font-weight:700;font-size:12px;">
                  +<?= $d ?>d
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="padding:12px 20px;background:rgba(192,57,43,0.04);border-top:1px solid rgba(192,57,43,0.12);
                  display:flex;justify-content:space-between;align-items:center;">
        <span class="text-muted" style="font-size:12px;"><?= $overdue_count ?> overdue invoice(s)</span>
        <span class="font-bold" style="color:var(--c-red);">Total: <?= fmt_vnd($overdue_amount) ?></span>
      </div>
    </div>
    <?php endif; ?>

  </div>

</div>

<script>
window.addEventListener('load', function () {

    // ── Dữ liệu từ PHP ───────────────────────────────────────────────────────
    var donutData = <?= json_encode([$status_paid, $status_issued, $status_overdue]) ?>;
    var labels    = <?= json_encode(array_values($chart_labels)) ?>;
    var revenue   = <?= json_encode(array_values($chart_revenue)) ?>;
    var apCosts   = <?= json_encode(array_values($chart_ap_costs)) ?>;

    // ── Donut – AR Invoice Classification ────────────────────────────────────
    var ctxDonut = document.getElementById('chartDonut');
    if (ctxDonut) {
        new Chart(ctxDonut, {
            type: 'doughnut',
            data: {
                labels: ['Paid', 'Issued, pending', 'Overdue'],
                datasets: [{
                    data: donutData,
                    backgroundColor: ['#6B8C3E', '#3A5361', '#C0392B'],
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (c) {
                                return ' ' + c.label + ': ' + c.raw + ' invoices';
                            }
                        }
                    }
                }
            }
        });
    }

    // ── Grouped Bar – Revenue Collected vs AP Carrier Costs ──────────────────
    var ctxBar = document.getElementById('chartRevenue');
    if (ctxBar) {
        if (!labels || labels.length === 0) {
            var c2d = ctxBar.getContext('2d');
            ctxBar.width  = ctxBar.offsetWidth || 400;
            ctxBar.height = 220;
            c2d.fillStyle    = '#9BA8AD';
            c2d.font         = '13px Montserrat, sans-serif';
            c2d.textAlign    = 'center';
            c2d.textBaseline = 'middle';
            c2d.fillText('Chưa có dữ liệu — sẽ hiển thị khi có payment / invoice.', ctxBar.width / 2, 110);
        } else {
            new Chart(ctxBar, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'AR Collected (M ₫)',
                            data: revenue,
                            backgroundColor: 'rgba(107,140,62,0.85)',
                            borderRadius: 5,
                            borderSkipped: false,
                            barPercentage: 0.6,
                            categoryPercentage: 0.7
                        },
                        {
                            label: 'AP Carrier Costs (M ₫)',
                            data: apCosts,
                            backgroundColor: 'rgba(232,184,75,0.85)',
                            borderRadius: 5,
                            borderSkipped: false,
                            barPercentage: 0.6,
                            categoryPercentage: 0.7
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function (c) {
                                    return ' ' + c.dataset.label + ': ' + c.raw.toLocaleString('en-US') + ' M ₫';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (v) { return v + ' M'; },
                                font: { size: 11 }
                            },
                            grid: { color: 'rgba(0,0,0,0.05)' }
                        },
                        x: {
                            ticks: { font: { size: 12 } },
                            grid: { display: false }
                        }
                    }
                }
            });
        }
    }

});
</script>

<?php
close_page();
$db->close();
?>