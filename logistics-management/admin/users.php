<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/db.php';
auth_require('admin');

$db = get_db();
$message = '';
$message_type = '';

// ══════════════════════════════════════════════════════════════════════════════
//  XỬ LÝ POST — Tất cả thao tác ghi DB đều qua đây
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── 1. THÊM USER MỚI ─────────────────────────────────────────────────────
    if ($action === 'add_user') {
        $full_name = trim($_POST['full_name'] ?? '');
        $username  = trim($_POST['username']  ?? '');
        $email     = trim($_POST['email']     ?? '');
        $password  = $_POST['password']       ?? '';
        $confirm   = $_POST['confirm_password'] ?? '';
        $role_id   = (int)($_POST['role_id']  ?? 0);
        $status    = $_POST['status']         ?? 'Active';

        if (!$full_name || !$username || !$email || !$password || !$role_id) {
            $message = 'Vui lòng điền đầy đủ các trường bắt buộc.';
            $message_type = 'danger';
        } elseif ($password !== $confirm) {
            $message = 'Mật khẩu xác nhận không khớp.';
            $message_type = 'danger';
        } elseif (strlen($password) < 8) {
            $message = 'Mật khẩu phải có ít nhất 8 ký tự.';
            $message_type = 'danger';
        } else {
            // Kiểm tra username trùng
            $chk = $db->prepare("SELECT AccountID FROM account WHERE Username = ?");
            $chk->bind_param('s', $username);
            $chk->execute();
            $chk->store_result();

            if ($chk->num_rows > 0) {
                $message = "Username \"$username\" đã tồn tại.";
                $message_type = 'danger';
            } else {
                // Tạo employee trước
                $stmt_emp = $db->prepare(
                    "INSERT INTO employee (RoleID, FullName, ContactEmail) VALUES (?, ?, ?)"
                );
                $stmt_emp->bind_param('iss', $role_id, $full_name, $email);
                $stmt_emp->execute();
                $emp_id = $db->insert_id;

                // Hash password và tạo account
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt_acc = $db->prepare(
                    "INSERT INTO account (EmployeeID, RoleID, Username, PasswordHash, Status)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt_acc->bind_param('iisss', $emp_id, $role_id, $username, $hash, $status);
                $stmt_acc->execute();

                $message = "Tạo tài khoản \"$username\" thành công!";
                $message_type = 'success';
            }
            $chk->close();
        }
    }

    // ── 2. SỬA USER ──────────────────────────────────────────────────────────
    elseif ($action === 'edit_user') {
        $account_id = (int)($_POST['account_id'] ?? 0);
        $full_name  = trim($_POST['full_name']   ?? '');
        $username   = trim($_POST['username']    ?? '');
        $email      = trim($_POST['email']       ?? '');
        $role_id    = (int)($_POST['role_id']    ?? 0);
        $status     = $_POST['status']           ?? 'Active';

        if (!$account_id || !$full_name || !$username || !$email || !$role_id) {
            $message = 'Dữ liệu không hợp lệ.';
            $message_type = 'danger';
        } else {
            // Lấy EmployeeID hiện tại
            $q = $db->prepare("SELECT EmployeeID FROM account WHERE AccountID = ?");
            $q->bind_param('i', $account_id);
            $q->execute();
            $q->bind_result($emp_id);
            $q->fetch();
            $q->close();

            // Cập nhật employee
            $stmt_e = $db->prepare(
                "UPDATE employee SET FullName = ?, ContactEmail = ?, RoleID = ? WHERE EmployeeID = ?"
            );
            $stmt_e->bind_param('ssii', $full_name, $email, $role_id, $emp_id);
            $stmt_e->execute();

            // Cập nhật account
            $stmt_a = $db->prepare(
                "UPDATE account SET Username = ?, RoleID = ?, Status = ? WHERE AccountID = ?"
            );
            $stmt_a->bind_param('sssi', $username, $role_id, $status, $account_id);
            $stmt_a->execute();

            $message = "Cập nhật tài khoản \"$username\" thành công!";
            $message_type = 'success';
        }
    }

    // ── 3. ĐỔI TRẠNG THÁI (Activate / Deactivate / Lock) ────────────────────
    elseif ($action === 'change_status') {
        $account_id = (int)($_POST['account_id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';
        $allowed    = ['Active', 'Inactive', 'Locked'];

        if (!$account_id || !in_array($new_status, $allowed)) {
            $message = 'Dữ liệu không hợp lệ.';
            $message_type = 'danger';
        } else {
            $stmt = $db->prepare("UPDATE account SET Status = ? WHERE AccountID = ?");
            $stmt->bind_param('si', $new_status, $account_id);
            $stmt->execute();

            $labels = ['Active' => 'Kích hoạt', 'Inactive' => 'Vô hiệu hóa', 'Locked' => 'Khóa'];
            $message = $labels[$new_status] . ' tài khoản thành công!';
            $message_type = 'success';
        }
    }

    // ── 4. ĐẶT LẠI MẬT KHẨU ─────────────────────────────────────────────────
    elseif ($action === 'reset_password') {
        $account_id  = (int)($_POST['account_id']  ?? 0);
        $new_password = $_POST['new_password'] ?? '';

        if (!$account_id || strlen($new_password) < 8) {
            $message = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
            $message_type = 'danger';
        } else {
            $hash = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE account SET PasswordHash = ? WHERE AccountID = ?");
            $stmt->bind_param('si', $hash, $account_id);
            $stmt->execute();

            $message = 'Đặt lại mật khẩu thành công!';
            $message_type = 'success';
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  LẤY DỮ LIỆU TỪ DB
// ══════════════════════════════════════════════════════════════════════════════
$sql = "
    SELECT
        a.AccountID,
        a.Username,
        a.Status,
        e.FullName,
        e.ContactEmail,
        r.RoleID,
        r.RoleName
    FROM account a
    JOIN employee e ON a.EmployeeID = e.EmployeeID
    JOIN role r     ON a.RoleID     = r.RoleID
    ORDER BY a.AccountID ASC
";
$result   = $db->query($sql);
$accounts = [];
while ($row = $result->fetch_assoc()) {
    $accounts[] = $row;
}

// Lấy danh sách roles cho dropdown
$roles_result = $db->query("SELECT RoleID, RoleName FROM role ORDER BY RoleID");
$role_options = [];
while ($r = $roles_result->fetch_assoc()) {
    $role_options[$r['RoleID']] = $r['RoleName'];
}

// Tính stats
$total_users    = count($accounts);
$active_users   = count(array_filter($accounts, fn($a) => $a['Status'] === 'Active'));
$inactive_users = count(array_filter($accounts, fn($a) => $a['Status'] === 'Inactive'));
$locked_users   = count(array_filter($accounts, fn($a) => $a['Status'] === 'Locked'));

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

<?php if ($message): ?>
<div class="alert alert-<?= $message_type ?>" style="margin-bottom:16px;">
  <?= $message_type === 'success' ? '✅' : '❌' ?> <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

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
    <div class="stat-value"><?= count($role_options) ?></div>
    <div class="stat-label">Roles Assigned</div>
  </div>
</div>

<!-- ── Filter Bar ─────────────────────────────────────────────────────────── -->
<div class="card mt-24" style="overflow:hidden;">
  <div class="table-toolbar" style="padding:16px 20px;border-bottom:1px solid var(--border-color);">
    <div class="search-input-wrapper" style="flex:1;">
      <span class="search-icon">🔍</span>
      <input type="text" placeholder="Search by name, username, email…"
             data-table-search="usersTable" class="form-control"
             style="padding-left:32px;width:100%;">
    </div>
    <div class="flex gap-8">
      <select class="form-control" id="filterRole" onchange="filterUsers()" style="min-width:160px;">
        <option value="">All Roles</option>
        <?php foreach ($role_options as $rid => $rname): ?>
          <option value="<?= $rid ?>"><?= htmlspecialchars($rname) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="form-control" id="filterStatus" onchange="filterUsers()" style="min-width:140px;">
        <option value="">All Statuses</option>
        <option value="Active">Active</option>
        <option value="Inactive">Inactive</option>
        <option value="Locked">Locked</option>
      </select>
    </div>
  </div>

  <div style="padding:0 20px 20px;">
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
          <th style="text-align:left;width:140px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($accounts as $acc):
          $role_badge_color = match(strtolower($acc['RoleName'])) {
              'admin'            => 'navy',
              'manager'          => 'slate',
              'accountant'       => 'olive',
              'operation staff'  => 'green',
              default            => 'gray',
          };
          $status_badge_color = match($acc['Status']) {
              'Active'   => 'green',
              'Inactive' => 'gray',
              'Locked'   => 'red',
              default    => 'gray',
          };
          // Avatar: 2 chữ đầu tên
          $name_parts = explode(' ', $acc['FullName']);
          $avatar = mb_strtoupper(mb_substr(end($name_parts), 0, 1) . mb_substr($name_parts[0], 0, 1));
        ?>
          <tr data-role="<?= $acc['RoleID'] ?>" data-status="<?= htmlspecialchars($acc['Status']) ?>">
            <td class="td-muted font-bold">ACC<?= str_pad($acc['AccountID'], 3, '0', STR_PAD_LEFT) ?></td>
            <td>
              <div class="flex gap-8" style="align-items:center;">
                <div style="width:32px;height:32px;border-radius:50%;background:var(--c-navy-800);color:#fff;
                            display:flex;align-items:center;justify-content:center;font-weight:700;
                            font-size:12px;flex-shrink:0;">
                  <?= htmlspecialchars($avatar) ?>
                </div>
                <span style="font-weight:600;"><?= htmlspecialchars($acc['FullName']) ?></span>
              </div>
            </td>
            <td class="td-muted">
              <code style="background:var(--bg-alt);padding:2px 6px;border-radius:4px;font-size:12px;">
                <?= htmlspecialchars($acc['Username']) ?>
              </code>
            </td>
            <td class="td-muted"><?= htmlspecialchars($acc['ContactEmail']) ?></td>
            <td><span class="badge badge-<?= $role_badge_color ?>"><?= htmlspecialchars($acc['RoleName']) ?></span></td>
            <td><span class="badge badge-<?= $status_badge_color ?>"><?= htmlspecialchars($acc['Status']) ?></span></td>
            <td style="text-align:left;">
              <div class="action-menu" style="display:inline-flex;justify-content:flex-start;">
                <button class="action-menu-btn btn btn-outline btn-sm"
                        data-dropdown-toggle="userMenu<?= $acc['AccountID'] ?>"
                        style="display:inline-flex;align-items:center;gap:6px;padding:5px 20px;font-size:13px;font-weight:500;border-radius:6px;white-space:nowrap;width:120px;justify-content:center;">
                  <span style="font-size:16px;line-height:1;">⋯</span> Actions <span style="font-size:10px;">▾</span>
                </button>
                <div class="action-dropdown dropdown-menu" id="userMenu<?= $acc['AccountID'] ?>">

                  <!-- Edit -->
                  <a href="#" class="dropdown-item" data-modal-open="modalEditUser"
                     onclick="prefillEditUser(
                       <?= $acc['AccountID'] ?>,
                       '<?= htmlspecialchars(addslashes($acc['FullName'])) ?>',
                       '<?= htmlspecialchars(addslashes($acc['Username'])) ?>',
                       '<?= htmlspecialchars(addslashes($acc['ContactEmail'])) ?>',
                       <?= $acc['RoleID'] ?>,
                       '<?= htmlspecialchars($acc['Status']) ?>'
                     )">✏️ Edit User</a>

                  <!-- Reset Password -->
                  <a href="#" class="dropdown-item" data-modal-open="modalResetPw"
                     onclick="prefillResetPw(
                       <?= $acc['AccountID'] ?>,
                       '<?= htmlspecialchars(addslashes($acc['FullName'])) ?>'
                     )">🔑 Reset Password</a>

                  <div class="dropdown-divider"></div>

                  <?php if ($acc['Status'] === 'Active'): ?>
                    <!-- Deactivate -->
                    <a href="#" class="dropdown-item danger"
                       onclick="confirmStatusChange(<?= $acc['AccountID'] ?>, 'Inactive',
                         '<?= htmlspecialchars(addslashes($acc['FullName'])) ?>')">
                      ⛔ Deactivate
                    </a>
                  <?php elseif ($acc['Status'] === 'Inactive'): ?>
                    <!-- Activate -->
                    <a href="#" class="dropdown-item"
                       onclick="confirmStatusChange(<?= $acc['AccountID'] ?>, 'Active',
                         '<?= htmlspecialchars(addslashes($acc['FullName'])) ?>')">
                      ✅ Activate
                    </a>
                  <?php endif; ?>

                  <?php if ($acc['Status'] !== 'Locked'): ?>
                    <!-- Lock -->
                    <a href="#" class="dropdown-item danger"
                       onclick="confirmStatusChange(<?= $acc['AccountID'] ?>, 'Locked',
                         '<?= htmlspecialchars(addslashes($acc['FullName'])) ?>')">
                      🔒 Lock Account
                    </a>
                  <?php else: ?>
                    <!-- Unlock -->
                    <a href="#" class="dropdown-item"
                       onclick="confirmStatusChange(<?= $acc['AccountID'] ?>, 'Active',
                         '<?= htmlspecialchars(addslashes($acc['FullName'])) ?>')">
                      🔓 Unlock Account
                    </a>
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

  <div class="card-footer flex-between">
    <span class="text-muted" style="font-size:13px;" id="userCount">
      Showing <?= $total_users ?> users
    </span>
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
    <form method="POST" action="">
      <input type="hidden" name="action" value="add_user">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Full Name <span style="color:var(--c-red);">*</span></label>
            <input type="text" name="full_name" class="form-control"
                   placeholder="e.g. Nguyen Van An" required>
          </div>
          <div class="form-group">
            <label class="form-label">Username <span style="color:var(--c-red);">*</span></label>
            <input type="text" name="username" class="form-control"
                   placeholder="e.g. nguyenan" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address <span style="color:var(--c-red);">*</span></label>
          <input type="email" name="email" class="form-control"
                 placeholder="user@itl.com" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Password <span style="color:var(--c-red);">*</span></label>
            <input type="password" name="password" class="form-control"
                   placeholder="Min. 8 characters" required>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm Password <span style="color:var(--c-red);">*</span></label>
            <input type="password" name="confirm_password" class="form-control"
                   placeholder="Re-enter password" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Role <span style="color:var(--c-red);">*</span></label>
            <select name="role_id" class="form-control" required>
              <option value="">— Select Role —</option>
              <?php foreach ($role_options as $rid => $rname): ?>
                <option value="<?= $rid ?>"><?= htmlspecialchars($rname) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="alert alert-info" style="margin-top:8px;">
          ℹ️ Tài khoản sẽ được tạo và lưu vào cơ sở dữ liệu ngay lập tức.
        </div>
      </div>
        <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Create User</button>
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
    <form method="POST" action="">
      <input type="hidden" name="action" value="edit_user">
      <div class="modal-body">
        <input type="hidden" name="account_id" id="editAccountId">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" name="full_name" class="form-control" id="editFullName" required>
          </div>
          <div class="form-group">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" id="editUsername" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control" id="editEmail" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Role</label>
            <select name="role_id" class="form-control" id="editRoleId">
              <?php foreach ($role_options as $rid => $rname): ?>
                <option value="<?= $rid ?>"><?= htmlspecialchars($rname) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control" id="editStatus">
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
              <option value="Locked">Locked</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
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
    <form method="POST" action="">
      <input type="hidden" name="action" value="reset_password">
      <div class="modal-body">
        <input type="hidden" name="account_id" id="resetPwAccountId">
        <div class="alert alert-warning">
          ⚠️ Đặt lại mật khẩu cho: <strong id="resetPwName">this user</strong>
        </div>
        <div class="form-group mt-16">
          <label class="form-label">Mật khẩu mới <span style="color:var(--c-red);">*</span></label>
          <input type="password" name="new_password" class="form-control"
                 placeholder="Tối thiểu 8 ký tự" required minlength="8">
        </div>
        <div class="form-group">
          <label class="form-label">Xác nhận mật khẩu mới <span style="color:var(--c-red);">*</span></label>
          <input type="password" name="confirm_new_password" class="form-control"
                 placeholder="Nhập lại mật khẩu" required minlength="8">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-danger" onclick="return validateResetPw()">
          🔑 Reset Password
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     FORM ẨN — Thay đổi trạng thái (Activate / Deactivate / Lock)
     Gửi POST để cập nhật DB thật, không dùng link GET
════════════════════════════════════════════════════════════════════════════ -->
<form method="POST" action="" id="formChangeStatus" style="display:none;">
  <input type="hidden" name="action"     value="change_status">
  <input type="hidden" name="account_id" id="statusAccountId">
  <input type="hidden" name="new_status" id="statusNewValue">
</form>


<!-- ── JavaScript ─────────────────────────────────────────────────────────── -->
<script>
// Lọc bảng theo Role và Status
function filterUsers() {
  const roleFilter   = document.getElementById('filterRole').value;
  const statusFilter = document.getElementById('filterStatus').value.toLowerCase();
  const rows = document.querySelectorAll('#usersTable tbody tr');
  let visible = 0;

  rows.forEach(row => {
    const role   = row.dataset.role   || '';
    const status = (row.dataset.status || '').toLowerCase();
    const matchRole   = !roleFilter   || role   === roleFilter;
    const matchStatus = !statusFilter || status === statusFilter;
    row.style.display = (matchRole && matchStatus) ? '' : 'none';
    if (matchRole && matchStatus) visible++;
  });

  document.getElementById('userCount').textContent = 'Showing ' + visible + ' users';
}

// Điền sẵn dữ liệu vào form Edit
function prefillEditUser(id, name, username, email, roleId, status) {
  document.getElementById('editAccountId').value = id;
  document.getElementById('editFullName').value  = name;
  document.getElementById('editUsername').value  = username;
  document.getElementById('editEmail').value     = email;
  document.getElementById('editRoleId').value    = roleId;
  document.getElementById('editStatus').value    = status;
}

// Điền sẵn dữ liệu vào form Reset Password
function prefillResetPw(id, name) {
  document.getElementById('resetPwAccountId').value = id;
  document.getElementById('resetPwName').textContent = name;
}

// Validate reset password (2 trường phải khớp)
function validateResetPw() {
  const pw1 = document.querySelector('#modalResetPw [name="new_password"]').value;
  const pw2 = document.querySelector('#modalResetPw [name="confirm_new_password"]').value;
  if (pw1 !== pw2) {
    alert('Mật khẩu xác nhận không khớp!');
    return false;
  }
  return true;
}

// Xác nhận và gửi thay đổi trạng thái qua POST
function confirmStatusChange(accountId, newStatus, userName) {
  const labels = {
    'Active':   'Kích hoạt',
    'Inactive': 'Vô hiệu hóa',
    'Locked':   'Khóa'
  };
  const label = labels[newStatus] || newStatus;
  const msg   = label + ' tài khoản của "' + userName + '"?\n\nThao tác này sẽ được lưu vào cơ sở dữ liệu ngay.';

  if (confirm(msg)) {
    document.getElementById('statusAccountId').value = accountId;
    document.getElementById('statusNewValue').value  = newStatus;
    document.getElementById('formChangeStatus').submit();
  }
}
</script>

<?php
close_page();
$db->close();
?>