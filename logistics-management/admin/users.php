<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('admin');

$accounts     = get_demo_accounts();
$total_users  = count($accounts);
$active_users = count(array_filter($accounts, fn($a) => $a['status'] === 'active'));
$inactive_users = $total_users - $active_users;

$role_options = ['admin' => 'Admin', 'manager' => 'Manager', 'accountant' => 'Accountant', 'operations' => 'Operations'];

open_page('User Accounts', 'users', [['label' => 'Administration'], ['label' => 'User Accounts']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">User Accounts</h1>
    <p class="text-muted" style="margin-top:4px;">Manage system users, roles, and access credentials</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-primary" data-modal-open="modalAddUser">➕ Add User</button>
  </div>
</div>

<!-- ── Stats ──────────────────────────────────────────────────────────────── -->
<div class="stats-grid">
  <div class="stat-card navy">
    <div class="stat-icon">👥</div>
    <div class="stat-value"><?= $total_users ?></div>
    <div class="stat-label">Total Users</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon">✅</div>
    <div class="stat-value"><?= $active_users ?></div>
    <div class="stat-label">Active</div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon">⛔</div>
    <div class="stat-value"><?= $inactive_users ?></div>
    <div class="stat-label">Inactive</div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon">🔐</div>
    <div class="stat-value">4</div>
    <div class="stat-label">Roles Assigned</div>
  </div>
</div>

<!-- ── Filter Bar ─────────────────────────────────────────────────────────── -->
<div class="card mt-24">
  <div class="table-toolbar">
    <div class="search-input-wrapper">
      <span>🔍</span>
      <input type="text" placeholder="Search by name, username, email…" data-table-search="usersTable" class="form-control" style="border:none;box-shadow:none;padding-left:4px;">
    </div>
    <div class="flex gap-8">
      <select class="form-control" id="filterRole" onchange="filterUsers()" style="min-width:160px;">
        <option value="">All Roles</option>
        <?php foreach ($role_options as $val => $lbl): ?>
          <option value="<?= $val ?>"><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
      <select class="form-control" id="filterStatus" onchange="filterUsers()" style="min-width:140px;">
        <option value="">All Statuses</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
    </div>
  </div>

  <div class="table-wrapper">
    <table id="usersTable">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Username</th>
          <th>Email</th>
          <th>Role</th>
          <th>Status</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($accounts as $acc): ?>
          <?php
            $role_badge_color = match($acc['role']) {
              'admin'      => 'navy',
              'manager'    => 'slate',
              'accountant' => 'olive',
              'operations' => 'green',
              default      => 'gray',
            };
          ?>
          <tr data-role="<?= $acc['role'] ?>" data-status="<?= $acc['status'] ?>">
            <td class="td-muted font-bold"><?= htmlspecialchars($acc['id']) ?></td>
            <td>
              <div class="flex gap-8" style="align-items:center;">
                <div style="width:32px;height:32px;border-radius:50%;background:var(--c-navy-800);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0;">
                  <?= htmlspecialchars($acc['avatar']) ?>
                </div>
                <span style="font-weight:600;"><?= htmlspecialchars($acc['name']) ?></span>
              </div>
            </td>
            <td class="td-muted"><code style="background:var(--bg-alt);padding:2px 6px;border-radius:4px;font-size:12px;"><?= htmlspecialchars($acc['username']) ?></code></td>
            <td class="td-muted"><?= htmlspecialchars($acc['email']) ?></td>
            <td><span class="badge badge-<?= $role_badge_color ?>"><?= ucfirst($acc['role']) ?></span></td>
            <td><?= status_badge($acc['status']) ?></td>
            <td style="text-align:right;">
              <div class="action-menu">
                <button class="action-menu-btn btn btn-ghost btn-sm" data-dropdown-toggle="userMenu<?= $acc['id'] ?>">⋯ Actions ▾</button>
                <div class="action-dropdown dropdown-menu" id="userMenu<?= $acc['id'] ?>">
                  <a href="#" class="dropdown-item" data-modal-open="modalEditUser" onclick="prefillEditUser(<?= htmlspecialchars(json_encode($acc)) ?>)">✏️ Edit User</a>
                  <a href="#" class="dropdown-item" data-modal-open="modalResetPw" onclick="document.getElementById('resetPwName').textContent='<?= htmlspecialchars($acc['name']) ?>'">🔑 Reset Password</a>
                  <div class="dropdown-divider"></div>
                  <?php if ($acc['status'] === 'active'): ?>
                    <a href="#" class="dropdown-item danger" data-confirm="Deactivate account for <?= htmlspecialchars($acc['name']) ?>?">⛔ Deactivate</a>
                  <?php else: ?>
                    <a href="#" class="dropdown-item" data-confirm="Activate account for <?= htmlspecialchars($acc['name']) ?>?">✅ Activate</a>
                  <?php endif; ?>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card-footer flex-between">
    <span class="text-muted" style="font-size:13px;">Showing <?= $total_users ?> users</span>
    <div class="pagination">
      <button class="btn btn-ghost btn-sm" disabled>‹ Prev</button>
      <button class="btn btn-primary btn-sm">1</button>
      <button class="btn btn-ghost btn-sm" disabled>Next ›</button>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     MODAL — Add User
════════════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalAddUser">
  <div class="modal" style="max-width:560px;">
    <div class="modal-header">
      <h3 class="modal-title">➕ Add New User</h3>
      <button class="modal-close">✕</button>
    </div>
    <form data-feedback="User created successfully!">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Full Name <span style="color:var(--c-red);">*</span></label>
            <input type="text" class="form-control" placeholder="e.g. Nguyen Van An" required>
          </div>
          <div class="form-group">
            <label class="form-label">Username <span style="color:var(--c-red);">*</span></label>
            <input type="text" class="form-control" placeholder="e.g. nguyenan" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address <span style="color:var(--c-red);">*</span></label>
          <input type="email" class="form-control" placeholder="user@logitrack.com" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Password <span style="color:var(--c-red);">*</span></label>
            <input type="password" class="form-control" placeholder="Min. 8 characters" required>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm Password <span style="color:var(--c-red);">*</span></label>
            <input type="password" class="form-control" placeholder="Re-enter password" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Role <span style="color:var(--c-red);">*</span></label>
            <select class="form-control" required>
              <option value="">— Select Role —</option>
              <?php foreach ($role_options as $val => $lbl): ?>
                <option value="<?= $val ?>"><?= $lbl ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select class="form-control">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="alert alert-info" style="margin-top:8px;">
          ℹ️ User will receive a welcome email with login instructions upon creation.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline modal-close">Cancel</button>
        <button type="submit" class="btn btn-primary">Create User</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     MODAL — Edit User
════════════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalEditUser">
  <div class="modal" style="max-width:520px;">
    <div class="modal-header">
      <h3 class="modal-title">✏️ Edit User</h3>
      <button class="modal-close">✕</button>
    </div>
    <form data-feedback="User updated successfully!">
      <div class="modal-body">
        <input type="hidden" id="editUserId">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" class="form-control" id="editUserName">
          </div>
          <div class="form-group">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" id="editUserUsername">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" class="form-control" id="editUserEmail">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Role</label>
            <select class="form-control" id="editUserRole">
              <?php foreach ($role_options as $val => $lbl): ?>
                <option value="<?= $val ?>"><?= $lbl ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select class="form-control" id="editUserStatus">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline modal-close">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     MODAL — Reset Password
════════════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modalResetPw">
  <div class="modal" style="max-width:440px;">
    <div class="modal-header">
      <h3 class="modal-title">🔑 Reset Password</h3>
      <button class="modal-close">✕</button>
    </div>
    <form data-feedback="Password reset email sent!">
      <div class="modal-body">
        <div class="alert alert-warning">
          ⚠️ You are about to reset the password for <strong id="resetPwName">this user</strong>. A secure reset link will be emailed to them.
        </div>
        <div class="form-group mt-16">
          <label class="form-label">Or set a temporary password manually:</label>
          <input type="password" class="form-control" placeholder="Leave blank to send reset email">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline modal-close">Cancel</button>
        <button type="submit" class="btn btn-danger">🔑 Reset Password</button>
      </div>
    </form>
  </div>
</div>

<script>
function filterUsers() {
  const roleFilter   = document.getElementById('filterRole').value.toLowerCase();
  const statusFilter = document.getElementById('filterStatus').value.toLowerCase();
  const rows = document.querySelectorAll('#usersTable tbody tr');
  rows.forEach(row => {
    const role   = row.dataset.role   || '';
    const status = row.dataset.status || '';
    const matchRole   = !roleFilter   || role   === roleFilter;
    const matchStatus = !statusFilter || status === statusFilter;
    row.style.display = (matchRole && matchStatus) ? '' : 'none';
  });
}

function prefillEditUser(acc) {
  document.getElementById('editUserId').value       = acc.id;
  document.getElementById('editUserName').value     = acc.name;
  document.getElementById('editUserUsername').value = acc.username;
  document.getElementById('editUserEmail').value    = acc.email;
  document.getElementById('editUserRole').value     = acc.role;
  document.getElementById('editUserStatus').value   = acc.status;
}
</script>

<?php close_page(); ?>
