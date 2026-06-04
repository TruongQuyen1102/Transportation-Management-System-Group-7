<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/db.php'; // Kết nối tới database thật
auth_require('admin');

// 1. Khởi tạo kết nối DB bằng function trong config/db.php
$conn = get_db();

// 2. Lấy danh sách Roles từ Database thật
$resultRoles = $conn->query("SELECT RoleID, RoleName FROM role ORDER BY RoleID ASC");
$roles_db = $resultRoles->fetch_all(MYSQLI_ASSOC);

// 3. Lấy danh sách Users từ Database thật
$resultUsers = $conn->query("
    SELECT a.AccountID, a.Username, a.Status, a.RoleID, e.FullName 
    FROM account a 
    LEFT JOIN employee e ON a.EmployeeID = e.EmployeeID
");
$accounts_db = $resultUsers->fetch_all(MYSQLI_ASSOC);

// Map User vào từng Role để hiển thị danh sách thành viên trong Card
$role_users = [];
foreach ($accounts_db as $acc) {
    $name = !empty($acc['FullName']) ? $acc['FullName'] : $acc['Username'];
    $words = explode(" ", $name);
    $avatar = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr(end($words), 0, 1) : ''));
    
    $acc['display_name'] = $name;
    $acc['avatar'] = $avatar;
    
    $role_users[$acc['RoleID']][] = $acc;
}

// 4. Cấu hình Quyền và phân chia theo từng Feature Nhóm rõ ràng như file gốc
// Mapping tạm thời theo RoleID (1=Admin, 2=Manager, 3=Accountant, 4=Operation Staff)
$modules = [
    'System Access' => [
        ['id' => 'view_dashboard',    'label' => '👁 View Dashboard',     'perms' => [1=>true,  2=>true,  3=>true,  4=>true]],
        ['id' => 'view_audit',        'label' => '📋 Audit Log Access',   'perms' => [1=>true,  2=>true,  3=>false, 4=>false]],
        ['id' => 'export_reports',    'label' => '📈 Export Reports',     'perms' => [1=>true,  2=>true,  3=>true,  4=>false]],
    ],
    'User & Role Admin' => [
        ['id' => 'manage_users',      'label' => '👥 User Management',    'perms' => [1=>true,  2=>false, 3=>false, 4=>false]],
        ['id' => 'manage_roles',      'label' => '🔐 Role Management',    'perms' => [1=>true,  2=>false, 3=>false, 4=>false]],
        ['id' => 'import_data',       'label' => '📥 Import Data',        'perms' => [1=>true,  2=>false, 3=>false, 4=>false]],
    ],
    'Shipments & Orders' => [
        ['id' => 'create_shipments',  'label' => '📦 Create Shipments',   'perms' => [1=>true,  2=>false, 3=>false, 4=>true]],
        ['id' => 'edit_shipments',    'label' => '✏️ Edit Shipments',     'perms' => [1=>true,  2=>true,  3=>false, 4=>true]],
        ['id' => 'delete_shipments',  'label' => '🗑 Delete Shipments',   'perms' => [1=>true,  2=>false, 3=>false, 4=>false]],
    ],
    'Finance & Billing' => [
        ['id' => 'create_invoices',   'label' => '🧾 Create Invoices',    'perms' => [1=>true,  2=>false, 3=>true,  4=>false]],
        ['id' => 'record_payments',   'label' => '💳 Record Payments',    'perms' => [1=>true,  2=>false, 3=>true,  4=>false]],
        ['id' => 'billing_config',    'label' => '⚙️ Billing Config',     'perms' => [1=>true,  2=>false, 3=>true,  4=>false]],
    ],
    'Operations' => [
        ['id' => 'manage_exceptions', 'label' => '⚠️ Manage Exceptions', 'perms' => [1=>true,  2=>true,  3=>false, 4=>true]],
    ]
];

open_page('Manage Roles', 'roles', [['label' => 'Administration'], ['label' => 'Manage Roles']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Role & Permission Management</h1>
    <p class="text-muted" style="margin-top:4px;">Configure role-based access control for all system users</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-outline btn-sm">📥 Export Permissions</button>
  </div>
</div>

<div class="grid-2 mt-8" style="grid-template-columns: repeat(2, 1fr);">
  <?php foreach ($roles_db as $role): ?>
    <?php
      $roleId = $role['RoleID'];
      $roleName = $role['RoleName'];
      
      // Khôi phục đồng bộ màu sắc dựa theo chuẩn tên RoleName của database thật
      $color = match($roleName) {
          'Admin'           => 'navy',
          'Manager'         => 'slate',
          'Accountant'      => 'olive',
          'Operation Staff' => 'green',
          default           => 'slate'
      };
      
      $users_for_role = $role_users[$roleId] ?? [];
      $user_count = count($users_for_role);
    ?>
    <div class="card">
      <div class="card-header flex-between">
        <div class="flex gap-12" style="align-items:center;">
          <div style="width:42px;height:42px;border-radius:10px;background:var(--c-<?= $color === 'navy' ? 'navy-800' : ($color === 'slate' ? 'slate-600' : ($color === 'olive' ? 'yellow' : 'green')) ?>);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">
            <?= match($roleName) { 'Admin' => '🛡️', 'Manager' => '📊', 'Accountant' => '💹', default => '🔧' } ?>
          </div>
          <div>
            <h3 class="card-title" style="margin:0;"><?= htmlspecialchars($roleName) ?></h3>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px;"><?= $user_count ?> user<?= $user_count !== 1 ? 's' : '' ?> assigned</div>
          </div>
        </div>
        <button class="btn btn-outline btn-sm" data-modal-open="modalEditPerms<?= $roleId ?>">✏️ Edit Permissions</button>
      </div>
      <div class="card-body" style="padding:16px 20px;">
        <?php if (!empty($users_for_role)): ?>
          <div style="background:var(--bg-alt);border-radius:8px;padding:10px 12px; max-height: 120px; overflow-y: auto;">
            <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Users with this role</div>
            <div class="flex gap-8" style="flex-wrap:wrap;">
              <?php foreach ($users_for_role as $u): ?>
                <div class="flex gap-8" style="align-items:center;background:#fff;border:1px solid var(--border-light);border-radius:20px;padding:3px 10px 3px 5px;">
                  <div style="width:24px;height:24px;border-radius:50%;background:var(--c-navy-800);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:10px;flex-shrink:0;">
                    <?= htmlspecialchars($u['avatar']) ?>
                  </div>
                  <span style="font-size:12px;font-weight:600;"><?= htmlspecialchars($u['display_name']) ?></span>
                  <?php if ($u['Status'] !== 'Active'): ?>
                    <span class="badge badge-gray" style="font-size:10px;"><?= $u['Status'] ?></span>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php else: ?>
          <p style="font-size:13px; color:var(--text-muted);">No users assigned to this role.</p>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card mt-24">
  <div class="card-header">
    <h3 class="card-title">📊 Full Permission Matrix</h3>
    <p style="font-size:13px;color:var(--text-muted);margin-top:4px;">Overview of all role permissions across core system functions</p>
  </div>
  <div class="table-wrapper">
    <table id="permMatrix" style="table-layout:fixed;">
      <thead>
        <tr>
          <th style="width:200px;">Module / Feature</th>
          <?php foreach ($roles_db as $role): ?>
            <th style="text-align:center;width:120px;">
              <?= htmlspecialchars($role['RoleName']) ?>
              <div style="font-size:10px;font-weight:400;opacity:.75;"><?= count($role_users[$role['RoleID']] ?? []) ?> users</div>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($modules as $section => $items): ?>
          <?php foreach ($items as $mod): ?>
            <tr>
              <td style="font-weight:600;font-size:13px;"><?= $mod['label'] ?></td>
              <?php foreach ($roles_db as $role): 
                  $rId = $role['RoleID'];
                  $allowed = $mod['perms'][$rId] ?? false; 
              ?>
                <td style="text-align:center;" id="cell-role-<?= $rId ?>-mod-<?= $mod['id'] ?>">
                  <?php if ($allowed): ?>
                    <span style="color:var(--c-green);font-size:18px;font-weight:700;" title="Allowed">✓</span>
                  <?php else: ?>
                    <span style="color:var(--border-color);font-size:18px;" title="Not allowed">✗</span>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php foreach ($roles_db as $role): 
    $rId = $role['RoleID'];
?>
  <div class="modal-overlay" id="modalEditPerms<?= $rId ?>">
    <div class="modal" style="max-width:500px;">
      <div class="modal-header">
        <h3 class="modal-title">✏️ Edit Permissions — <?= htmlspecialchars($role['RoleName']) ?></h3>
        <button class="modal-close" onclick="closeModal('modalEditPerms<?= $rId ?>')">✕</button>
      </div>
      <form id="form-edit-role-<?= $rId ?>" onsubmit="handleSavePermissions(event, <?= $rId ?>)">
        <div class="modal-body">
          <div class="alert alert-info" style="margin-bottom:16px;">
            ℹ️ Changes apply to all users assigned to the <strong><?= htmlspecialchars($role['RoleName']) ?></strong> role.
          </div>

          <div style="display:grid;gap:12px;">
             <?php foreach ($modules as $section => $items): ?>
              <div style="border:1px solid var(--border-light);border-radius:8px;overflow:hidden;">
                <div style="background:var(--bg-alt);padding:8px 14px;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);"><?= $section ?></div>
                <div style="padding:10px 14px;display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                  <?php foreach ($items as $mod): 
                      $isChecked = $mod['perms'][$rId] ?? false;
                  ?>
                    <label class="flex gap-8" style="align-items:center;cursor:pointer;font-size:13px;">
                      <input type="checkbox" 
                        class="perm-checkbox-role-<?= $rId ?>"
                        data-module-id="<?= $mod['id'] ?>"
                        <?= $isChecked ? 'checked' : '' ?>
                        style="width:16px;height:16px;accent-color:var(--c-navy-800);">
                      <?= htmlspecialchars($mod['label']) ?> </label>
                  <?php endforeach; ?>
                </div>
              </div>
             <?php endforeach; ?>
          </div>
          <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
          <button type="submit" class="btn btn-primary">💾 Save Permissions</button>
        </div>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<script>
function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active'); 
}

function handleSavePermissions(event, roleId) {
    event.preventDefault(); 

    // 1. Lấy tất cả checkbox của Role đang sửa
    const checkboxes = document.querySelectorAll('.perm-checkbox-role-' + roleId);
    
    // 2. Duyệt qua từng checkbox để đồng bộ trực tiếp xuống bảng Matrix bên dưới mà không cần load lại trang
    checkboxes.forEach(function(chk) {
        const moduleId = chk.getAttribute('data-module-id');
        const isChecked = chk.checked;
        
        const cellId = 'cell-role-' + roleId + '-mod-' + moduleId;
        const targetCell = document.getElementById(cellId);
        
        if (targetCell) {
            if (isChecked) {
                targetCell.innerHTML = '<span style="color:var(--c-green);font-size:18px;font-weight:700;" title="Allowed">✓</span>';
            } else {
                targetCell.innerHTML = '<span style="color:var(--border-color);font-size:18px;" title="Not allowed">✗</span>';
            }
        }
    });

    // 3. Đóng Modal và thông báo thành công
    const modalDiv = event.target.closest('.modal-overlay');
    if (modalDiv) modalDiv.classList.remove('active');
    
    alert('Permissions updated successfully in Matrix!');
}
</script>

<?php close_page(); ?>