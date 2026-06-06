<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/db.php'; // Thay thế sang file cấu hình kết nối DB thực tế
auth_require('admin');

// Khởi tạo đối tượng kết nối DB MySQLi giống như trang users.php của bạn
$db = get_db();

// ── TRUY VẤN DỮ LIỆU THỰC TẾ TỪ DATABASE ───────────────────────────────────

// 1. Thống kê thông tin User & Trạng thái tài khoản
$total_users    = 0;
$active_users   = 0;
$inactive_users = 0;

$user_stmt = $db->query("SELECT Status, COUNT(*) as cnt FROM account GROUP BY Status");
if ($user_stmt) {
    while ($row = $user_stmt->fetch_assoc()) {
        $status = strtolower($row['Status']);
        if ($status === 'active') {
            $active_users = (int)$row['cnt'];
        } else {
            $inactive_users += (int)$row['cnt']; // Bao gồm cả nhóm Inactive và Locked
        }
        $total_users += (int)$row['cnt'];
    }
}

// 2. Lấy số lượng vai trò (Roles) có trong hệ thống
$role_count_res = $db->query("SELECT COUNT(*) FROM role");
$active_roles = $role_count_res ? (int)$role_count_res->fetch_row()[0] : 0;

// 3. Lấy tổng số lượng hành động nhật ký hệ thống phát sinh trong ngày hôm nay
$audit_today_res = $db->query("SELECT COUNT(*) FROM system_audit_log WHERE DATE(ActionTime) = CURDATE()");
$total_actions = $audit_today_res ? (int)$audit_today_res->fetch_row()[0] : 0;

// Thống kê chi tiết các loại hành động phục vụ cho Trend Label của thẻ card Green
$action_counts = ['CREATE' => 0, 'UPDATE' => 0, 'DELETE' => 0];
$action_stmt = $db->query("SELECT ActionType, COUNT(*) as cnt FROM system_audit_log WHERE DATE(ActionTime) = CURDATE() GROUP BY ActionType");
if ($action_stmt) {
    while ($row = $action_stmt->fetch_assoc()) {
        $act = strtoupper($row['ActionType']);
        if (isset($action_counts[$act])) {
            $action_counts[$act] = (int)$row['cnt'];
        }
    }
}

// 4. Lấy số lượng tài khoản bị Khóa hoặc có yêu cầu Reset mật khẩu
$pending_resets_res = $db->query("SELECT COUNT(*) FROM system_audit_log WHERE ActionType = 'RESET_PASSWORD' OR Description LIKE '%Locked%'");
$pending_resets = $pending_resets_res ? (int)$pending_resets_res->fetch_row()[0] : 0;

// Tìm tên tài khoản có biến động nhật ký bảo mật gần đây nhất
$last_reset_res = $db->query("SELECT acc.Username FROM system_audit_log sal JOIN account acc ON sal.AccountID = acc.AccountID WHERE sal.ActionType = 'RESET_PASSWORD' OR sal.Description LIKE '%Locked%' ORDER BY sal.LogID DESC LIMIT 1");
$last_reset_user = ($last_reset_res && $last_reset_res->num_rows > 0) ? $last_reset_res->fetch_row()[0] : 'None';

// 5. Lấy danh sách 5 dòng Audit Log mới nhất để đẩy vào giao diện hiển thị nhanh
$recent_logs = [];
$logs_query = "SELECT sal.*, acc.Username, r.RoleName 
               FROM system_audit_log sal 
               LEFT JOIN account acc ON sal.AccountID = acc.AccountID 
               LEFT JOIN role r ON acc.RoleID = r.RoleID 
               ORDER BY sal.LogID DESC LIMIT 5";
$logs_stmt = $db->query($logs_query);
if ($logs_stmt) {
    while ($row = $logs_stmt->fetch_assoc()) {
        $recent_logs[] = [
            'id'        => $row['LogID'],
            'action'    => strtoupper($row['ActionType']),
            'desc'      => $row['Description'],
            'account'   => $row['Username'] ?? 'System',
            'table'     => $row['TableName'] ?? 'N/A',
            'record_id' => $row['RecordID'] ?? 0,
            'time'      => $row['ActionTime'],
            'role'      => $row['RoleName'] ?? 'Admin'
        ];
    }
}

open_page('Admin Dashboard', 'dashboard', [['label' => 'Administration'], ['label' => 'Dashboard']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Administration Dashboard</h1>
    <p class="text-muted mt-4">System health, user management, and audit overview</p>
  </div>
  <div class="page-actions">
    <a href="../admin/audit_logs.php" class="btn btn-outline btn-sm">📋 View Full Audit Log</a>
    <a href="../admin/users.php" class="btn btn-primary btn-sm">👥 Manage Users</a>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card navy">
    <div class="stat-icon">👥</div>
    <div class="stat-body">
      <div class="stat-value"><?= $total_users ?></div>
      <div class="stat-label">TOTAL USERS</div>
      <div class="stat-trend neutral"><?= $active_users ?> active &middot; <?= $inactive_users ?> inactive</div>
    </div>
  </div>
  
  <div class="stat-card slate">
    <div class="stat-icon">🔐</div>
    <div class="stat-body">
      <div class="stat-value"><?= $active_roles ?></div>
      <div class="stat-label">ACTIVE ROLES</div>
      <div class="stat-trend neutral">Admin &middot; Manager &middot; Accountant &middot; Ops</div>
    </div>
  </div>
  
  <div class="stat-card green">
    <div class="stat-icon">📋</div>
    <div class="stat-body">
      <div class="stat-value"><?= $total_actions ?></div>
      <div class="stat-label">AUDIT ACTIONS TODAY</div>
      <div class="stat-trend neutral"><?= $action_counts['CREATE'] ?> creates &middot; <?= $action_counts['UPDATE'] ?> updates &middot; <?= $action_counts['DELETE'] ?> deletes</div>
    </div>
  </div>
  
  <div class="stat-card yellow">
    <div class="stat-icon">🔑</div>
    <div class="stat-body">
      <div class="stat-value"><?= $pending_resets ?></div>
      <div class="stat-label">PENDING RESETS</div>
      <div class="stat-trend neutral">Last: <?= htmlspecialchars($last_reset_user) ?></div>
    </div>
  </div>
</div>

<div class="grid-2 mt-24" style="grid-template-columns: 1fr 2fr;">

  <div class="card" style="height: fit-content;">
    <div class="card-header">
      <h3 class="card-title">⚡ Quick Access</h3>
    </div>
    <div class="card-body" style="display:grid;grid-template-columns:1fr;gap:12px;">

      <a href="../admin/users.php" style="text-decoration:none;">
        <div style="background:var(--c-navy-800);color:#fff;border-radius:10px;padding:16px;display:flex;align-items:center;gap:12px;transition:opacity .15s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
          <div style="font-size:26px;line-height:1;flex-shrink:0;">👥</div>
          <div>
            <div class="font-bold font-sm">User Accounts</div>
            <div class="font-xs mt-4" style="opacity:.7;"><?= $total_users ?> users registered</div>
          </div>
        </div>
      </a>

      <a href="../admin/roles.php" style="text-decoration:none;">
        <div style="background:var(--c-slate-600);color:#fff;border-radius:10px;padding:16px;display:flex;align-items:center;gap:12px;transition:opacity .15s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
          <div style="font-size:26px;line-height:1;flex-shrink:0;">🔐</div>
          <div>
            <div class="font-bold font-sm">Manage Roles</div>
            <div class="font-xs mt-4" style="opacity:.7;"><?= $active_roles ?> roles configured</div>
          </div>
        </div>
      </a>

      <a href="../admin/audit_logs.php" style="text-decoration:none;">
        <div style="background:var(--c-green);color:#fff;border-radius:10px;padding:16px;display:flex;align-items:center;gap:12px;transition:opacity .15s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
          <div style="font-size:26px;line-height:1;flex-shrink:0;">📋</div>
          <div>
            <div class="font-bold font-sm">Audit Logs</div>
            <div class="font-xs mt-4" style="opacity:.75;"><?= count($recent_logs) ?> recent logs loaded</div>
          </div>
        </div>
      </a>

      <a href="../admin/import_users.php" style="text-decoration:none;">
        <div style="background:var(--c-yellow);color:var(--c-navy-800);border-radius:10px;padding:16px;display:flex;align-items:center;gap:12px;transition:opacity .15s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
          <div style="font-size:26px;line-height:1;flex-shrink:0;">📥</div>
          <div>
            <div class="font-bold font-sm">Import Users</div>
            <div class="font-xs mt-4" style="opacity:.6;">CSV / XLSX upload</div>
          </div>
        </div>
      </a>

    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">🕐 Recent Activity</h3>
    </div>
    <div class="card-body p-0">
      <div class="activity-feed" style="padding: 0 20px;">
        <?php if (empty($recent_logs)): ?>
          <div class="text-muted text-center py-24 font-sm">No activity logged today.</div>
        <?php else: ?>
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
              <div class="activity-avatar" style="background: transparent; font-size: 18px;"><?= $icon ?></div>
              <div class="activity-body">
                <div class="activity-text"><strong><?= htmlspecialchars($log['desc']) ?></strong></div>
                <div class="activity-time">
                  <?= htmlspecialchars($log['account']) ?> &middot; <?= htmlspecialchars($log['table']) ?> &middot; <?= htmlspecialchars($log['time']) ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-footer">
      <a href="../admin/audit_logs.php" class="btn btn-ghost btn-sm w-100 flex-center">View All Audit Logs →</a>
    </div>
  </div>

</div>

<div class="card mt-24">
  <div class="card-header flex-between">
    <h3 class="card-title">📋 Recent Audit Log Entries</h3>
    <a href="../admin/audit_logs.php" class="btn btn-outline btn-sm">View All</a>
  </div>
  <div class="table-wrapper" style="border: none; box-shadow: none; border-radius: 0;">
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
        <?php if (empty($recent_logs)): ?>
          <tr><td colspan="8" class="text-center text-muted">No audit entries found.</td></tr>
        <?php else: ?>
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
              <td class="font-bold text-primary"><?= htmlspecialchars($log['account']) ?></td>
              <td><span class="badge badge-navy"><?= strtoupper(htmlspecialchars($log['role'])) ?></span></td>
              <td><span class="badge badge-<?= $action_color ?>"><?= htmlspecialchars($action_label) ?></span></td>
              <td class="td-muted"><?= htmlspecialchars($log['table']) ?></td>
              <td class="td-muted font-bold"><?= htmlspecialchars($log['record_id']) ?></td>
              <td class="td-muted" style="white-space:nowrap;"><?= htmlspecialchars($log['time']) ?></td>
              <td style="max-width:280px;" class="truncate"><?= htmlspecialchars($log['desc']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="grid-3 mt-24">

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">👥 User Overview</h3>
    </div>
    <div class="card-body">
      <?php
        $role_colors = ['admin' => 'navy', 'manager' => 'slate', 'accountant' => 'olive', 'operation staff' => 'green'];
        $breakdown_stmt = $db->query("SELECT r.RoleName, COUNT(a.AccountID) as cnt FROM account a LEFT JOIN role r ON a.RoleID = r.RoleID GROUP BY r.RoleID, r.RoleName");
        if ($breakdown_stmt):
          while ($row = $breakdown_stmt->fetch_assoc()):
            $r_name = $row['RoleName'] ?? 'Unassigned';
            $r_key = strtolower($r_name);
      ?>
        <div class="flex-between py-2" style="padding: 10px 0; border-bottom: 1px solid var(--border-color);">
          <span class="badge badge-<?= $role_colors[$r_key] ?? 'gray' ?>"><?= ucfirst($r_name) ?></span>
          <span class="font-bold text-primary"><?= $row['cnt'] ?> user<?= $row['cnt'] > 1 ? 's' : '' ?></span>
        </div>
      <?php 
          endwhile;
        endif; 
      ?>
      <div class="flex-between mt-12 pt-2">
        <span class="font-sm text-muted">Total Active</span>
        <span class="font-bold text-success"><?= $active_users ?> / <?= $total_users ?></span>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">🔐 Role Permissions</h3>
    </div>
    <div class="card-body">
      <?php 
        $roles_list_stmt = $db->query("SELECT * FROM role");
        if ($roles_list_stmt):
          while ($role_item = $roles_list_stmt->fetch_assoc()):
            $is_admin = (strtolower($role_item['RoleName']) === 'admin');
            $is_manager = (strtolower($role_item['RoleName']) === 'manager');
            $perms = [
              'read'   => true,
              'create' => $is_admin || $is_manager,
              'update' => $is_admin || $is_manager,
              'delete' => $is_admin
            ];
      ?>
        <div style="padding: 10px 0; border-bottom: 1px solid var(--border-color);">
          <div class="font-bold font-sm mb-4"><?= htmlspecialchars($role_item['RoleName']) ?></div>
          <div class="flex flex-wrap gap-8">
            <?php foreach ($perms as $perm => $allowed): ?>
              <span class="badge badge-<?= $allowed ? 'green' : 'gray' ?>" style="font-size: 10px;">
                <?= $allowed ? '✓' : '✗' ?> <?= ucfirst($perm) ?>
              </span>
            <?php endforeach; ?>
          </div>
        </div>
      <?php 
          endwhile;
        endif; 
      ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">⚙️ System Status</h3>
    </div>
    <div class="card-body">
      <div class="info-grid" style="grid-template-columns: 1fr;">
        <div class="info-row flex-row flex-between" style="flex-direction: row; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
          <span class="info-label">Application</span>
          <span class="info-value"><span class="badge badge-green">● Online</span></span>
        </div>
        <div class="info-row flex-row flex-between" style="flex-direction: row; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
          <span class="info-label">Database</span>
          <span class="info-value"><span class="badge badge-green">● Connected</span></span>
        </div>
        <div class="info-row flex-row flex-between" style="flex-direction: row; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
          <span class="info-label">Last Backup</span>
          <span class="info-value font-sm">2026-06-02 02:18</span>
        </div>
        <div class="info-row flex-row flex-between" style="flex-direction: row; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
          <span class="info-label">PHP Version</span>
          <span class="info-value font-sm"><?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ?></span>
        </div>
        <div class="info-row flex-row flex-between" style="flex-direction: row; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
          <span class="info-label">Session TTL</span>
          <span class="info-value font-sm">8 hours</span>
        </div>
        <div class="info-row flex-row flex-between" style="flex-direction: row; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
          <span class="info-label">2FA Status</span>
          <span class="info-value"><span class="badge badge-yellow">Optional</span></span>
        </div>
        <div class="info-row flex-row flex-between" style="flex-direction: row;">
          <span class="info-label">Audit Retention</span>
          <span class="info-value font-sm">90 days</span>
        </div>
      </div>
    </div>
  </div>

</div>

<?php 
close_page(); 
$db->close();
?>