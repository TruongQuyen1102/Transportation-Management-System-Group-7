<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('admin');

$accounts   = get_demo_accounts();
$roles      = get_roles();
$audit_logs = get_audit_logs();

$total_users    = count($accounts);
$active_users   = count(array_filter($accounts, fn($a) => $a['status'] === 'active'));
$inactive_users = $total_users - $active_users;
$active_roles   = count($roles);
$total_actions  = count($audit_logs);
$pending_resets = 1; // from AUD008

$recent_logs = array_slice(array_reverse($audit_logs), 0, 5);

open_page('Admin Dashboard', 'dashboard', [['label' => 'Administration'], ['label' => 'Dashboard']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Administration Dashboard</h1>
    <p class="text-muted" style="margin-top:4px;">System health, user management, and audit overview</p>
  </div>
  <div class="page-actions">
    <a href="/admin/audit_logs.php" class="btn btn-outline btn-sm">📋 View Full Audit Log</a>
    <a href="/admin/users.php" class="btn btn-primary btn-sm">👥 Manage Users</a>
  </div>
</div>

<!-- ── Stat Cards ─────────────────────────────────────────────────────────── -->
<div class="stats-grid">
  <div class="stat-card navy">
    <div class="stat-icon">👥</div>
    <div class="stat-value"><?= $total_users ?></div>
    <div class="stat-label">Total Users</div>
    <div class="stat-trend">↑ <?= $active_users ?> active · <?= $inactive_users ?> inactive</div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon">🔐</div>
    <div class="stat-value"><?= $active_roles ?></div>
    <div class="stat-label">Active Roles</div>
    <div class="stat-trend">Admin · Manager · Accountant · Ops</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">📋</div>
    <div class="stat-value"><?= $total_actions ?></div>
    <div class="stat-label">Audit Actions Today</div>
    <div class="stat-trend">↑ 3 creates · 6 updates · 1 reset</div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon">🔑</div>
    <div class="stat-value"><?= $pending_resets ?></div>
    <div class="stat-label">Pending Password Resets</div>
    <div class="stat-trend">James Pham (ACC003)</div>
  </div>
</div>

<!-- ── Quick Links & Recent Audit ─────────────────────────────────────────── -->
<div class="grid-2 mt-24">

  <!-- Quick Links -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">⚡ Quick Access</h3>
    </div>
    <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:20px;">

      <a href="/admin/users.php" style="text-decoration:none;">
        <div style="background:var(--c-navy-800);color:#fff;border-radius:10px;padding:20px;text-align:center;transition:opacity .15s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
          <div style="font-size:32px;margin-bottom:8px;">👥</div>
          <div style="font-weight:700;font-size:14px;">User Accounts</div>
          <div style="font-size:12px;opacity:.75;margin-top:4px;"><?= $total_users ?> users registered</div>
        </div>
      </a>

      <a href="/admin/roles.php" style="text-decoration:none;">
        <div style="background:var(--c-slate-600);color:#fff;border-radius:10px;padding:20px;text-align:center;transition:opacity .15s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
          <div style="font-size:32px;margin-bottom:8px;">🔐</div>
          <div style="font-weight:700;font-size:14px;">Manage Roles</div>
          <div style="font-size:12px;opacity:.75;margin-top:4px;"><?= $active_roles ?> roles configured</div>
        </div>
      </a>

      <a href="/admin/audit_logs.php" style="text-decoration:none;">
        <div style="background:var(--c-green);color:#fff;border-radius:10px;padding:20px;text-align:center;transition:opacity .15s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
          <div style="font-size:32px;margin-bottom:8px;">📋</div>
          <div style="font-weight:700;font-size:14px;">Audit Logs</div>
          <div style="font-size:12px;opacity:.75;margin-top:4px;"><?= $total_actions ?> actions logged</div>
        </div>
      </a>

      <a href="/admin/import_users.php" style="text-decoration:none;">
        <div style="background:var(--c-yellow);color:var(--c-navy-800);border-radius:10px;padding:20px;text-align:center;transition:opacity .15s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
          <div style="font-size:32px;margin-bottom:8px;">📥</div>
          <div style="font-weight:700;font-size:14px;">Import Users</div>
          <div style="font-size:12px;opacity:.6;margin-top:4px;">CSV / XLSX upload</div>
        </div>
      </a>

    </div>
  </div>

  <!-- Activity Feed -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">🕐 Recent Activity</h3>
    </div>
    <div class="card-body" style="padding:16px 20px;">
      <div class="activity-feed">
        <?php foreach ($recent_logs as $log): ?>
          <?php
            $icon = match($log['action']) {
              'CREATE'         => '🟢',
              'UPDATE'         => '🔵',
              'DELETE'         => '🔴',
              'RESET_PASSWORD' => '🟡',
              default          => '⚪',
            };
          ?>
          <div class="activity-item">
            <span style="font-size:18px;flex-shrink:0;"><?= $icon ?></span>
            <div style="flex:1;min-width:0;">
              <div style="font-weight:600;font-size:13px;color:var(--text-primary);"><?= htmlspecialchars($log['desc']) ?></div>
              <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">
                <?= htmlspecialchars($log['account']) ?> · <?= htmlspecialchars($log['table']) ?> · <?= htmlspecialchars($log['time']) ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card-footer">
      <a href="/admin/audit_logs.php" class="btn btn-ghost btn-sm">View All Audit Logs →</a>
    </div>
  </div>

</div>

<!-- ── Recent Audit Log Table ─────────────────────────────────────────────── -->
<div class="card mt-24">
  <div class="card-header flex-between">
    <h3 class="card-title">📋 Recent Audit Log Entries</h3>
    <a href="/admin/audit_logs.php" class="btn btn-outline btn-sm">View All</a>
  </div>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Account</th>
          <th>Role</th>
          <th>Action</th>
          <th>Table</th>
          <th>Record</th>
          <th>Time</th>
          <th>Description</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recent_logs as $log): ?>
          <?php
            $action_color = match($log['action']) {
              'CREATE'         => 'green',
              'UPDATE'         => 'blue',
              'DELETE'         => 'red',
              'RESET_PASSWORD' => 'yellow',
              default          => 'gray',
            };
            $action_label = str_replace('_', ' ', $log['action']);
          ?>
          <tr>
            <td class="td-muted font-bold"><?= htmlspecialchars($log['id']) ?></td>
            <td style="font-weight:600;"><?= htmlspecialchars($log['account']) ?></td>
            <td><span class="badge badge-navy"><?= strtoupper(htmlspecialchars($log['role'])) ?></span></td>
            <td><span class="badge badge-<?= $action_color ?>"><?= htmlspecialchars($action_label) ?></span></td>
            <td class="td-muted"><?= htmlspecialchars($log['table']) ?></td>
            <td class="td-muted font-bold"><?= htmlspecialchars($log['record_id']) ?></td>
            <td class="td-muted" style="white-space:nowrap;"><?= htmlspecialchars($log['time']) ?></td>
            <td style="max-width:280px;" class="truncate"><?= htmlspecialchars($log['desc']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── System Overview Cards ──────────────────────────────────────────────── -->
<div class="grid-3 mt-24">

  <!-- User Breakdown -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">👥 User Overview</h3>
    </div>
    <div class="card-body" style="padding:16px 20px;">
      <?php
        $role_counts = [];
        foreach ($accounts as $acc) {
          $role_counts[$acc['role']] = ($role_counts[$acc['role']] ?? 0) + 1;
        }
        $role_colors = ['admin'=>'navy','manager'=>'slate','accountant'=>'olive','operations'=>'green'];
      ?>
      <?php foreach ($role_counts as $r => $cnt): ?>
        <div class="flex-between" style="padding:8px 0;border-bottom:1px solid var(--border-light);">
          <span class="badge badge-<?= $role_colors[$r] ?? 'gray' ?>"><?= ucfirst($r) ?></span>
          <span style="font-weight:700;color:var(--text-primary);"><?= $cnt ?> user<?= $cnt > 1 ? 's' : '' ?></span>
        </div>
      <?php endforeach; ?>
      <div class="flex-between mt-12" style="padding-top:8px;">
        <span style="font-size:13px;color:var(--text-muted);">Total Active</span>
        <span style="font-weight:700;color:var(--c-green);"><?= $active_users ?> / <?= $total_users ?></span>
      </div>
    </div>
  </div>

  <!-- Role Permissions Summary -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">🔐 Role Permissions</h3>
    </div>
    <div class="card-body" style="padding:16px 20px;">
      <?php foreach ($roles as $role_item): ?>
        <div style="padding:8px 0;border-bottom:1px solid var(--border-light);">
          <div style="font-weight:600;font-size:13px;margin-bottom:4px;"><?= htmlspecialchars($role_item['name']) ?></div>
          <div style="display:flex;gap:6px;flex-wrap:wrap;">
            <?php foreach ($role_item['permissions'] as $perm => $allowed): ?>
              <span class="badge badge-<?= $allowed ? 'green' : 'gray' ?>" style="font-size:10px;"><?= $allowed ? '✓' : '✗' ?> <?= ucfirst($perm) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- System Status -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">⚙️ System Status</h3>
    </div>
    <div class="card-body" style="padding:16px 20px;">
      <div class="info-grid">
        <div class="info-row">
          <span class="info-label">Application</span>
          <span class="info-value"><span class="badge badge-green">● Online</span></span>
        </div>
        <div class="info-row">
          <span class="info-label">Database</span>
          <span class="info-value"><span class="badge badge-green">● Connected</span></span>
        </div>
        <div class="info-row">
          <span class="info-label">Last Backup</span>
          <span class="info-value">2025-05-29 02:00</span>
        </div>
        <div class="info-row">
          <span class="info-label">PHP Version</span>
          <span class="info-value"><?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Session TTL</span>
          <span class="info-value">8 hours</span>
        </div>
        <div class="info-row">
          <span class="info-label">2FA Status</span>
          <span class="info-value"><span class="badge badge-yellow">Optional</span></span>
        </div>
        <div class="info-row">
          <span class="info-label">Audit Retention</span>
          <span class="info-value">90 days</span>
        </div>
      </div>
    </div>
  </div>

</div>

<?php close_page(); ?>
