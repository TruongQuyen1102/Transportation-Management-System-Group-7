<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('admin');

$audit_logs = get_audit_logs();
$accounts   = get_demo_accounts();

// Compute stats
$total_actions   = count($audit_logs);
$creates  = count(array_filter($audit_logs, fn($l) => $l['action'] === 'CREATE'));
$updates  = count(array_filter($audit_logs, fn($l) => $l['action'] === 'UPDATE'));
$resets   = count(array_filter($audit_logs, fn($l) => $l['action'] === 'RESET_PASSWORD'));
$deletes  = count(array_filter($audit_logs, fn($l) => $l['action'] === 'DELETE'));

$account_names = array_unique(array_column($audit_logs, 'account'));

open_page('Audit Logs', 'audit', [['label' => 'Administration'], ['label' => 'Audit Logs']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">System Audit Logs</h1>
    <p class="text-muted" style="margin-top:4px;">Complete trail of all system actions performed by users</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-outline btn-sm">📥 Export CSV</button>
    <button class="btn btn-outline btn-sm">📄 Export PDF</button>
  </div>
</div>

<!-- ── Stats ──────────────────────────────────────────────────────────────── -->
<div class="stats-grid">
  <div class="stat-card navy">
    <div class="stat-icon">📋</div>
    <div class="stat-value"><?= $total_actions ?></div>
    <div class="stat-label">Total Actions</div>
    <div class="stat-trend">All time</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">➕</div>
    <div class="stat-value"><?= $creates ?></div>
    <div class="stat-label">Creates</div>
    <div class="stat-trend"><?= round($creates / $total_actions * 100) ?>% of total</div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon">✏️</div>
    <div class="stat-value"><?= $updates ?></div>
    <div class="stat-label">Updates</div>
    <div class="stat-trend"><?= round($updates / $total_actions * 100) ?>% of total</div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon">🔑</div>
    <div class="stat-value"><?= $resets ?></div>
    <div class="stat-label">Password Resets</div>
    <div class="stat-trend">Administrative actions</div>
  </div>
</div>

<!-- ── Filter Bar ─────────────────────────────────────────────────────────── -->
<div class="card mt-24">
  <div class="table-toolbar">
    <div class="search-input-wrapper">
      <span>🔍</span>
      <input type="text" placeholder="Search description, record ID, account…" data-table-search="auditTable" class="form-control" style="border:none;box-shadow:none;padding-left:4px;">
    </div>
    <div class="flex gap-8">
      <select class="form-control" id="filterAction" onchange="filterAudit()" style="min-width:160px;">
        <option value="">All Actions</option>
        <option value="CREATE">CREATE</option>
        <option value="UPDATE">UPDATE</option>
        <option value="DELETE">DELETE</option>
        <option value="RESET_PASSWORD">RESET PASSWORD</option>
      </select>
      <select class="form-control" id="filterAccount" onchange="filterAudit()" style="min-width:180px;">
        <option value="">All Accounts</option>
        <?php foreach ($account_names as $name): ?>
          <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="form-control" id="filterTable" onchange="filterAudit()" style="min-width:160px;">
        <option value="">All Tables</option>
        <?php
          $tables = array_unique(array_column($audit_logs, 'table'));
          foreach ($tables as $tbl): ?>
          <option value="<?= htmlspecialchars($tbl) ?>"><?= htmlspecialchars($tbl) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" class="form-control" id="filterDateFrom" onchange="filterAudit()" title="From date" style="min-width:140px;">
      <input type="date" class="form-control" id="filterDateTo"   onchange="filterAudit()" title="To date"   style="min-width:140px;">
      <button class="btn btn-ghost btn-sm" onclick="clearAuditFilters()">✕ Clear</button>
    </div>
  </div>

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
        <?php foreach (array_reverse($audit_logs) as $log): ?>
          <?php
            $action_map = [
              'CREATE'         => ['color' => 'green',  'icon' => '➕'],
              'UPDATE'         => ['color' => 'blue',   'icon' => '✏️'],
              'DELETE'         => ['color' => 'red',    'icon' => '🗑'],
              'RESET_PASSWORD' => ['color' => 'yellow', 'icon' => '🔑'],
            ];
            $am = $action_map[$log['action']] ?? ['color' => 'gray', 'icon' => '⚪'];
            $role_badge = match($log['role']) {
              'admin' => 'navy',
              'mgr'   => 'slate',
              'acc'   => 'olive',
              'ops'   => 'green',
              default => 'gray',
            };
            $role_label = match($log['role']) {
              'admin' => 'Admin',
              'mgr'   => 'Manager',
              'acc'   => 'Accountant',
              'ops'   => 'Operations',
              default => strtoupper($log['role']),
            };
          ?>
          <tr data-action="<?= $log['action'] ?>"
              data-account="<?= htmlspecialchars($log['account']) ?>"
              data-table-name="<?= htmlspecialchars($log['table']) ?>"
              data-time="<?= htmlspecialchars($log['time']) ?>">
            <td class="td-muted font-bold"><?= htmlspecialchars($log['id']) ?></td>
            <td>
              <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($log['account']) ?></div>
            </td>
            <td><span class="badge badge-<?= $role_badge ?>"><?= $role_label ?></span></td>
            <td>
              <span class="badge badge-<?= $am['color'] ?>" style="display:inline-flex;align-items:center;gap:4px;">
                <?= $am['icon'] ?> <?= str_replace('_', ' ', $log['action']) ?>
              </span>
            </td>
            <td>
              <span style="font-size:12px;background:var(--bg-alt);padding:2px 8px;border-radius:4px;font-family:monospace;font-weight:600;">
                <?= htmlspecialchars($log['table']) ?>
              </span>
            </td>
            <td class="td-muted font-bold"><?= htmlspecialchars($log['record_id']) ?></td>
            <td class="td-muted" style="white-space:nowrap;font-size:12px;"><?= htmlspecialchars($log['time']) ?></td>
            <td style="max-width:300px;font-size:13px;"><?= htmlspecialchars($log['desc']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card-footer flex-between">
    <span class="text-muted" style="font-size:13px;" id="auditCount">Showing <?= $total_actions ?> entries</span>
    <div class="pagination">
      <button class="btn btn-ghost btn-sm" disabled>‹ Prev</button>
      <button class="btn btn-primary btn-sm">1</button>
      <button class="btn btn-ghost btn-sm" disabled>Next ›</button>
    </div>
  </div>
</div>

<!-- ── Action Breakdown Chart ─────────────────────────────────────────────── -->
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
  const action  = document.getElementById('filterAction').value;
  const account = document.getElementById('filterAccount').value;
  const table   = document.getElementById('filterTable').value;
  const dateFrom = document.getElementById('filterDateFrom').value;
  const dateTo   = document.getElementById('filterDateTo').value;

  const rows = document.querySelectorAll('#auditTable tbody tr');
  let visible = 0;

  rows.forEach(row => {
    const rAction  = row.dataset.action  || '';
    const rAccount = row.dataset.account || '';
    const rTable   = row.dataset.tableName || '';
    const rTime    = row.dataset.time    || '';

    const matchAction  = !action  || rAction  === action;
    const matchAccount = !account || rAccount === account;
    const matchTable   = !table   || rTable   === table;

    let matchDate = true;
    if (dateFrom || dateTo) {
      const rowDate = rTime.substring(0, 10);
      if (dateFrom && rowDate < dateFrom) matchDate = false;
      if (dateTo   && rowDate > dateTo)   matchDate = false;
    }

    const show = matchAction && matchAccount && matchTable && matchDate;
    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });

  document.getElementById('auditCount').textContent = 'Showing ' + visible + ' entries';
}

function clearAuditFilters() {
  ['filterAction','filterAccount','filterTable','filterDateFrom','filterDateTo'].forEach(id => {
    document.getElementById(id).value = '';
  });
  filterAudit();
}

// ── Charts ───────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
  // Action Type Doughnut
  new Chart(document.getElementById('actionChart'), {
    type: 'doughnut',
    data: {
      labels: ['CREATE', 'UPDATE', 'DELETE', 'RESET PASSWORD'],
      datasets: [{
        data: [<?= $creates ?>, <?= $updates ?>, <?= $deletes ?>, <?= $resets ?>],
        backgroundColor: ['#6B8C3E', '#3A5361', '#C0392B', '#E8B84B'],
        borderWidth: 2,
        borderColor: '#fff',
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'right', labels: { font: { family: 'Montserrat', size: 12 } } }
      }
    }
  });

  // Activity by Account Bar
  <?php
    $account_counts = array_count_values(array_column($audit_logs, 'account'));
    arsort($account_counts);
    $ac_labels = json_encode(array_keys($account_counts));
    $ac_data   = json_encode(array_values($account_counts));
  ?>
  new Chart(document.getElementById('accountChart'), {
    type: 'bar',
    data: {
      labels: <?= $ac_labels ?>,
      datasets: [{
        label: 'Actions',
        data: <?= $ac_data ?>,
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
        y: { beginAtZero: true, ticks: { stepSize: 1, font: { family: 'Montserrat', size: 11 } } }
      }
    }
  });
});
</script>

<?php close_page(); ?>
