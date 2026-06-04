<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/db.php';
auth_require('accountant');

$db = get_db();
$invoices = [];
$invoiceResult = $db->query(
    "SELECT i.InvoiceID AS id,
            CONCAT('ORD', LPAD(i.OrderID, 3, '0')) AS order_id,
            bp.PartyName AS customer,
            DATE_FORMAT(i.IssueDate, '%Y-%m-%d') AS issue_date,
            DATE_FORMAT(DATE_ADD(i.IssueDate, INTERVAL 30 DAY), '%Y-%m-%d') AS due_date,
            IFNULL(MIN(bs.Currency), 'VND') AS currency,
            i.FinalAmount AS final,
            COALESCE(i.TaxRate, 0) AS tax,
            CASE WHEN EXISTS (SELECT 1 FROM payment_transaction pt WHERE pt.InvoiceID = i.InvoiceID) THEN 'PAID'
                 WHEN DATEDIFF(CURDATE(), i.IssueDate) > 30 THEN 'OVERDUE'
                 ELSE 'ISSUED' END AS status
     FROM invoice i
     LEFT JOIN business_party bp ON i.BilledPartyID = bp.PartyID
     LEFT JOIN invoice_line il ON il.InvoiceID = i.InvoiceID
     LEFT JOIN billing_structure bs ON il.BillingID = bs.BillingID
     WHERE i.InvoiceType = 'AR_Receivable'
     GROUP BY i.InvoiceID, i.OrderID, bp.PartyName, i.IssueDate, i.FinalAmount, i.TaxRate"
);
if ($invoiceResult) {
    while ($row = $invoiceResult->fetch_assoc()) {
        $invoices[] = $row;
    }
}
$today = date('Y-m-d');

// Build aging table
$aging = [];
foreach ($invoices as $inv) {
    if (in_array($inv['status'], ['DRAFT','PAID','CANCELED'])) continue;
    $due  = new DateTime($inv['due_date']);
    $now  = new DateTime($today);
    $diff = (int)$now->diff($due)->days;
    $overdue = $now > $due;
    $daysOverdue = $overdue ? $diff : 0;
    $daysLeft    = $overdue ? 0 : $diff;
    $aging[] = array_merge($inv, [
        'days_overdue' => $daysOverdue,
        'days_left'    => $daysLeft,
        'is_overdue'   => $overdue,
    ]);
}
usort($aging, fn($a,$b) => $b['days_overdue'] <=> $a['days_overdue']);

$overdueCount  = count(array_filter($aging, fn($r) => $r['is_overdue']));
$overdueAmount = array_sum(array_map(fn($r) => $r['is_overdue'] ? $r['final'] : 0, $aging));
$atRisk        = count(array_filter($aging, fn($r) => !$r['is_overdue'] && $r['days_left'] <= 14));

open_page('Aging Debt Monitor', 'aging', [['label'=>'Finance'],['label'=>'Aging Debt']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Aging Debt Monitor</h1>
    <p class="page-subtitle">Track overdue invoices and outstanding receivables</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-ghost btn-sm">📥 Export Report</button>
    <button class="btn btn-primary btn-sm">📧 Send Reminders</button>
  </div>
</div>

<?php if ($overdueCount > 0): ?>
<div class="alert alert-danger">
  <span class="alert-icon">🚨</span>
  <div class="alert-body">
    <div class="alert-title"><?= $overdueCount ?> Overdue Invoice<?= $overdueCount > 1 ? 's' : '' ?> Require Immediate Action</div>
    Total overdue receivables: <strong><?= fmt_currency($overdueAmount) ?></strong>. 
    Please follow up with the relevant customers immediately.
  </div>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(4,1fr); margin-bottom:24px;">
  <div class="stat-card red">
    <div class="stat-icon" style="background:var(--c-red-bg)">🔴</div>
    <div class="stat-body">
      <div class="stat-value"><?= $overdueCount ?></div>
      <div class="stat-label">Overdue Invoices</div>
      <div class="stat-trend down">Requires immediate action</div>
    </div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon" style="background:var(--c-red-bg)">💸</div>
    <div class="stat-body">
      <div class="stat-value" style="font-size:18px"><?= fmt_currency($overdueAmount) ?></div>
      <div class="stat-label">Total Overdue Amount</div>
      <div class="stat-trend down">Past due date</div>
    </div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon" style="background:var(--c-yellow-bg)">⚠️</div>
    <div class="stat-body">
      <div class="stat-value"><?= $atRisk ?></div>
      <div class="stat-label">At-Risk (≤14 days)</div>
      <div class="stat-trend neutral">Due within 2 weeks</div>
    </div>
  </div>
  <div class="stat-card navy">
    <div class="stat-icon" style="background:rgba(12,40,64,.08)">📋</div>
    <div class="stat-body">
      <div class="stat-value"><?= count($aging) ?></div>
      <div class="stat-label">Open Receivables</div>
      <div class="stat-trend neutral">ISSUED + OVERDUE</div>
    </div>
  </div>
</div>

<!-- Aging Buckets -->
<div class="grid-3 mb-24">
  <div class="card">
    <div class="card-header">
      <div class="card-title">🟢 <span>Current (0–30 days)</span></div>
    </div>
    <div class="card-body" style="padding:16px 20px;">
      <?php
      $bucket = array_filter($aging, fn($r) => !$r['is_overdue'] && $r['days_left'] > 14);
      $total  = array_sum(array_map(fn($r) => $r['final'], $bucket));
      ?>
      <div style="font-size:24px;font-weight:800;color:var(--c-green)"><?= fmt_currency($total) ?></div>
      <div style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= count($bucket) ?> invoice(s) — comfortable</div>
    </div>
  </div>
  <div class="card">
    <div class="card-header">
      <div class="card-title">🟡 <span>At Risk (1–14 days left)</span></div>
    </div>
    <div class="card-body" style="padding:16px 20px;">
      <?php
      $bucket = array_filter($aging, fn($r) => !$r['is_overdue'] && $r['days_left'] <= 14);
      $total  = array_sum(array_map(fn($r) => $r['final'], $bucket));
      ?>
      <div style="font-size:24px;font-weight:800;color:var(--c-yellow)"><?= fmt_currency($total) ?></div>
      <div style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= count($bucket) ?> invoice(s) — send reminder</div>
    </div>
  </div>
  <div class="card">
    <div class="card-header">
      <div class="card-title">🔴 <span>Overdue (past due)</span></div>
    </div>
    <div class="card-body" style="padding:16px 20px;">
      <div style="font-size:24px;font-weight:800;color:var(--c-red)"><?= fmt_currency($overdueAmount) ?></div>
      <div style="font-size:12px;color:var(--text-muted);margin-top:4px;"><?= $overdueCount ?> invoice(s) — escalate now</div>
    </div>
  </div>
</div>

<!-- Aging Table -->
<div class="card">
  <div class="card-header">
    <div class="card-title">📊 Aging Receivables Detail</div>
    <div class="flex flex-gap-8">
      <select class="form-control" style="font-size:12px;padding:6px 10px;" onchange="filterAging(this.value)">
        <option value="all">All Statuses</option>
        <option value="overdue">Overdue Only</option>
        <option value="issued">Issued (Not Yet Due)</option>
      </select>
    </div>
  </div>
  <div class="table-wrapper" style="border-radius:0;border:none;box-shadow:none;">
    <table id="agingTable">
      <thead>
        <tr>
          <th>Invoice ID</th>
          <th>Customer</th>
          <th>Issue Date</th>
          <th>Due Date</th>
          <th>Currency</th>
          <th>Amount (Incl. Tax)</th>
          <th>Days Overdue</th>
          <th>Days Remaining</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($aging as $row): ?>
        <tr style="<?= $row['is_overdue'] ? 'background:rgba(192,57,43,.04);' : ($row['days_left']<=14 ? 'background:rgba(232,184,75,.04);' : '') ?>">
          <td><strong><?= $row['id'] ?></strong></td>
          <td><?= htmlspecialchars($row['customer']) ?></td>
          <td><?= $row['issue_date'] ?></td>
          <td style="<?= $row['is_overdue'] ? 'color:var(--c-red);font-weight:700;' : '' ?>"><?= $row['due_date'] ?></td>
          <td><?= $row['currency'] ?></td>
          <td><strong><?= fmt_currency($row['final'], $row['currency']) ?></strong></td>
          <td>
            <?php if ($row['is_overdue']): ?>
              <span style="color:var(--c-red);font-weight:700;">+<?= $row['days_overdue'] ?> days</span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!$row['is_overdue']): ?>
              <span style="color:<?= $row['days_left']<=14 ? 'var(--c-yellow)' : 'var(--c-green)' ?>;font-weight:600;">
                <?= $row['days_left'] ?> days
              </span>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td><?= status_badge($row['status']) ?></td>
          <td>
            <div class="action-menu">
              <button class="action-menu-btn" data-dropdown-toggle="aging-menu-<?= $row['id'] ?>">⋯</button>
              <div class="action-dropdown" id="aging-menu-<?= $row['id'] ?>">
                <a href="/accountant/invoice_detail.php">🔍 View Invoice</a>
                <button onclick="showToast('Reminder Sent','Email reminder sent to customer.','success')">📧 Send Reminder</button>
                <a href="/accountant/payments.php">💳 Record Payment</a>
                <?php if ($row['is_overdue']): ?>
                  <button class="danger" onclick="showToast('Escalated','Overdue invoice escalated to management.','info')">🚨 Escalate</button>
                <?php endif; ?>
              </div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Aging Bar Chart -->
<div class="card mt-24">
  <div class="card-header">
    <div class="card-title">📈 Aging Distribution by Customer</div>
  </div>
  <div class="card-body">
    <div class="chart-container" style="height:220px;">
      <canvas id="agingChart"></canvas>
    </div>
  </div>
</div>

<?php
$extraScripts = [<<<JS
new Chart(document.getElementById('agingChart'), {
  type: 'bar',
  data: {
    labels: ['Saigon Textile (INV008)','Hanoi Electronics (INV002)','Pacific Pharma (INV004)','Mekong Agri (INV005)','Pacific Pharma (INV006)'],
    datasets: [
      { label: 'Overdue', data: [38500000, 0, 0, 0, 0], backgroundColor: 'rgba(192,57,43,.8)', borderRadius: 4 },
      { label: 'Issued – At Risk', data: [0, 30800000, 0, 0, 0], backgroundColor: 'rgba(232,184,75,.8)', borderRadius: 4 },
      { label: 'Issued – Current', data: [0, 0, 3520, 19800000, 9350], backgroundColor: 'rgba(107,140,62,.7)', borderRadius: 4 }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'top' } },
    scales: {
      x: { stacked: true, grid: { display: false } },
      y: { stacked: true, ticks: { callback: v => v >= 1000000 ? (v/1000000).toFixed(0)+'M ₫' : v } }
    }
  }
});

function filterAging(val) {
  document.querySelectorAll('#agingTable tbody tr').forEach(row => {
    const status = row.querySelector('td:nth-child(9)')?.textContent.trim().toLowerCase() || '';
    if (val === 'all') { row.style.display = ''; return; }
    if (val === 'overdue') { row.style.display = status.includes('overdue') ? '' : 'none'; return; }
    if (val === 'issued')  { row.style.display = status.includes('issued')  ? '' : 'none'; return; }
  });
}
JS];
close_page();
?>
