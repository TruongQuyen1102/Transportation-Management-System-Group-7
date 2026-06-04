<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/db.php';
auth_require('admin');

$db = get_db();

// ══════════════════════════════════════════════════════════════════════════════
//  LẤY DỮ LIỆU TỪ BẢNG system_audit_log
//  JOIN thêm account, employee, role để hiển thị đầy đủ thông tin người thao tác.
// ══════════════════════════════════════════════════════════════════════════════
$sql = "
    SELECT
        sal.LogID,
        CONCAT('AUD', LPAD(sal.LogID, 3, '0'))  AS AuditID,
        e.FullName                              AS AccountName,
        r.RoleName                              AS RoleName,
        r.RoleID,
        sal.ActionType                          AS Action,
        sal.TableName                           AS TableName,
        sal.RecordID                            AS RecordID,
        sal.ActionTime                          AS EventTime,
        sal.Description                         AS Description
    FROM system_audit_log sal
    JOIN account  a  ON sal.AccountID = a.AccountID
    JOIN employee e  ON a.EmployeeID = e.EmployeeID
    JOIN role     r  ON a.RoleID     = r.RoleID
    ORDER BY sal.ActionTime DESC
";
$result    = $db->query($sql);
$audit_logs = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $audit_logs[] = $row;
    }
}

// ── Tính stats ───────────────────────────────────────────────────────────────
$total_actions = count($audit_logs);
$create_count  = count(array_filter($audit_logs, fn($l) => strtoupper($l['Action']) === 'CREATE'));
$update_count  = count(array_filter($audit_logs, fn($l) => strtoupper($l['Action']) === 'UPDATE'));
$delete_count  = count(array_filter($audit_logs, fn($l) => strtoupper($l['Action']) === 'DELETE'));

// Danh sách tài khoản duy nhất cho filter
$account_names = array_unique(array_column($audit_logs, 'AccountName'));
sort($account_names);

// Đếm theo tài khoản (cho chart)
$account_counts = [];
foreach ($audit_logs as $log) {
    $k = $log['AccountName'];
    $account_counts[$k] = ($account_counts[$k] ?? 0) + 1;
}
arsort($account_counts);

open_page('Audit Logs', 'audit', [['label' => 'Administration'], ['label' => 'Audit Logs']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">System Audit Logs</h1>
    <p class="text-muted" style="margin-top:4px;">
      Complete trail of all system actions performed by users
    </p>
  </div>
  <div class="page-actions">
    <button class="btn btn-outline btn-sm">📥 Export CSV</button>
    <button class="btn btn-outline btn-sm">📄 Export PDF</button>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card navy">
    <div class="stat-icon">📋</div>
    <div class="stat-value"><?= $total_actions ?></div>
    <div class="stat-label">Total Actions</div>
    <div class="stat-trend">All time records</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">➕</div>
    <div class="stat-value"><?= $create_count ?></div>
    <div class="stat-label">CREATE (Thêm mới)</div>
    <div class="stat-trend">
      <?= $total_actions > 0 ? round($create_count / $total_actions * 100) : 0 ?>% of total
    </div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon">✏️</div>
    <div class="stat-value"><?= $update_count ?></div>
    <div class="stat-label">UPDATE (Cập nhật)</div>
    <div class="stat-trend">
      <?= $total_actions > 0 ? round($update_count / $total_actions * 100) : 0 ?>% of total
    </div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon">🗑️</div>
    <div class="stat-value"><?= $delete_count ?></div>
    <div class="stat-label">DELETE (Xóa)</div>
    <div class="stat-trend">
      <?= $total_actions > 0 ? round($delete_count / $total_actions * 100) : 0 ?>% of total
    </div>
  </div>
</div>

<div class="card mt-24" style="overflow:hidden;">

  <div class="table-toolbar" style="padding:16px 20px; border-bottom:1px solid var(--border-color);">
    <div class="search-input-wrapper" style="flex:1;">
      <span class="search-icon">🔍</span>
      <input type="text" placeholder="Search description, record ID, account…"
             data-table-search="auditTable" class="form-control"
             style="padding-left:32px; width:100%;">
    </div>
    <div class="flex gap-8" style="flex-shrink:0;">
      <select class="form-control" id="filterAction" onchange="filterAudit()" style="min-width:160px;">
        <option value="">All Actions</option>
        <option value="CREATE">CREATE</option>
        <option value="UPDATE">UPDATE</option>
        <option value="DELETE">DELETE</option>
      </select>
      <select class="form-control" id="filterAccount" onchange="filterAudit()" style="min-width:180px;">
        <option value="">All Accounts</option>
        <?php foreach ($account_names as $name): ?>
          <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" class="form-control" id="filterDateFrom"
             onchange="filterAudit()" title="From date" style="min-width:140px;">
      <input type="date" class="form-control" id="filterDateTo"
             onchange="filterAudit()" title="To date"   style="min-width:140px;">
      <button class="btn btn-ghost btn-sm" onclick="clearAuditFilters()">✕ Clear</button>
    </div>
  </div>

  <div style="padding:0 20px;">
  <div class="table-wrapper">
    <table id="auditTable">
      <thead>
        <tr>
          <th>ID</th>
          <th>Account</th>
          <th>Role</th>
          <th>Action</th>
          <th>Table</th>
          <th>Record ID</th>
          <th>Timestamp</th>
          <th>Description</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($audit_logs as $log):
          $actionUpper = strtoupper($log['Action']);
          $action_map = [
              'CREATE' => ['color' => 'green',  'icon' => '➕'],
              'UPDATE' => ['color' => 'yellow', 'icon' => '✏️'],
              'DELETE' => ['color' => 'red',    'icon' => '🗑️'],
          ];
          $am = $action_map[$actionUpper] ?? ['color' => 'gray', 'icon' => '⚪'];

          $role_badge = match(strtolower($log['RoleName'])) {
              'admin'           => 'navy',
              'manager'         => 'slate',
              'accountant'      => 'olive',
              'operation staff' => 'green',
              default           => 'gray',
          };

          $time_display = $log['EventTime'] ?? '';
          $date_only    = substr($time_display, 0, 10);
        ?>
          <tr data-action="<?= htmlspecialchars($actionUpper) ?>"
              data-account="<?= htmlspecialchars($log['AccountName']) ?>"
              data-time="<?= htmlspecialchars($time_display) ?>">
            <td class="td-muted font-bold"><?= htmlspecialchars($log['AuditID']) ?></td>
            <td>
              <div style="font-weight:600;font-size:13px;">
                <?= htmlspecialchars($log['AccountName']) ?>
              </div>
            </td>
            <td>
              <span class="badge badge-<?= $role_badge ?>">
                <?= htmlspecialchars($log['RoleName']) ?>
              </span>
            </td>
            <td>
              <span class="badge badge-<?= $am['color'] ?>"
                    style="display:inline-flex;align-items:center;gap:4px;">
                <?= $am['icon'] ?> <?= htmlspecialchars($actionUpper) ?>
              </span>
            </td>
            <td>
              <span style="font-size:12px;background:var(--bg-alt);padding:2px 8px;
                           border-radius:4px;font-family:monospace;font-weight:600;">
                <?= htmlspecialchars($log['TableName']) ?>
              </span>
            </td>
            <td class="td-muted font-bold">#<?= htmlspecialchars($log['RecordID']) ?></td>
            <td class="td-muted" style="white-space:nowrap;font-size:12px;">
              <?= htmlspecialchars($time_display) ?>
            </td>
            <td style="max-width:300px;font-size:13px;">
              <?= htmlspecialchars($log['Description']) ?>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($audit_logs)): ?>
          <tr>
            <td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted);">
              📭 Chưa có dữ liệu audit log nào.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  </div>

  <div class="card-footer flex-between">
    <span class="text-muted" style="font-size:13px;" id="auditCount">
      Showing <?= $total_actions ?> entries
    </span>
    <div class="pagination">
      <button class="btn btn-ghost btn-sm" disabled>‹ Prev</button>
      <button class="btn btn-primary btn-sm">1</button>
      <button class="btn btn-ghost btn-sm" disabled>Next ›</button>
    </div>
  </div>

</div>

<div class="grid-2 mt-24">

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">📊 Action Type Breakdown</h3>
    </div>
    <div class="card-body" style="padding:20px;">
      <canvas id="actionChart" class="chart-container" style="max-height:220px;"></canvas>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">👤 Activity by Account</h3>
    </div>
    <div class="card-body" style="padding:20px;">
      <canvas id="accountChart" class="chart-container" style="max-height:220px;"></canvas>
    </div>
  </div>

</div>

<script>
// ── Filter Logic ─────────────────────────────────────────────────────────────
function filterAudit() {
  const action   = document.getElementById('filterAction').value;
  const account  = document.getElementById('filterAccount').value;
  const dateFrom = document.getElementById('filterDateFrom').value;
  const dateTo   = document.getElementById('filterDateTo').value;

  const rows = document.querySelectorAll('#auditTable tbody tr');
  let visible = 0;

  rows.forEach(row => {
    const rAction  = row.dataset.action  || '';
    const rAccount = row.dataset.account || '';
    const rTime    = row.dataset.time    || '';

    const matchAction  = !action  || rAction  === action;
    const matchAccount = !account || rAccount === account;

    let matchDate = true;
    if (dateFrom || dateTo) {
      const rowDate = rTime.substring(0, 10);
      if (dateFrom && rowDate < dateFrom) matchDate = false;
      if (dateTo   && rowDate > dateTo)   matchDate = false;
    }

    const show = matchAction && matchAccount && matchDate;
    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });

  document.getElementById('auditCount').textContent = 'Showing ' + visible + ' entries';
}

function clearAuditFilters() {
  ['filterAction', 'filterAccount', 'filterDateFrom', 'filterDateTo'].forEach(id => {
    document.getElementById(id).value = '';
  });
  filterAudit();
}

// ── Charts ───────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {

  // Doughnut: Action type
  new Chart(document.getElementById('actionChart'), {
    type: 'doughnut',
    data: {
      labels: ['CREATE (Thêm mới)', 'UPDATE (Cập nhật)', 'DELETE (Xóa)'],
      datasets: [{
        data: [<?= $create_count ?>, <?= $update_count ?>, <?= $delete_count ?>],
        backgroundColor: ['#6B8C3E', '#E8B84B', '#C0392B'],
        borderWidth: 2,
        borderColor: '#fff',
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'right',
          labels: { font: { family: 'Montserrat', size: 12 } }
        }
      }
    }
  });

  // Bar: Activity by account
  const acLabels = <?= json_encode(array_keys($account_counts)) ?>;
  const acData   = <?= json_encode(array_values($account_counts)) ?>;
  const maxVal   = Math.max(...acData, 1);

  new Chart(document.getElementById('accountChart'), {
    type: 'bar',
    data: {
      labels: acLabels,
      datasets: [{
        label: 'Actions',
        data: acData,
        backgroundColor: '#0C2840',
        borderRadius: 6,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { ticks: { font: { family: 'Montserrat', size: 10 } } },
        y: {
          beginAtZero: true,
          max: maxVal + 1,
          ticks: { stepSize: 1, font: { family: 'Montserrat', size: 11 } }
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