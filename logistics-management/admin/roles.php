<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('admin');

$roles    = get_roles();
$accounts = get_demo_accounts();

// Build role -> users map for display
$role_users = [];
foreach ($accounts as $acc) {
  $role_users[$acc['role']][] = $acc;
}

// All permission keys
$perm_keys = ['view', 'create', 'edit', 'delete'];
$perm_labels = ['view' => '👁 View', 'create' => '➕ Create', 'edit' => '✏️ Edit', 'delete' => '🗑 Delete'];

// Role colors for cards
$role_colors = [
  'Admin'           => 'navy',
  'Manager'         => 'slate',
  'Accountant'      => 'olive',
  'Operation Staff' => 'green',
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

<!-- ── Role Cards ─────────────────────────────────────────────────────────── -->
<div class="grid-2 mt-8" style="grid-template-columns: repeat(2, 1fr);">
  <?php foreach ($roles as $role): ?>
    <?php
      $color  = $role_colors[$role['name']] ?? 'slate';
      $r_key  = strtolower(str_replace(' ', '', $role['name']));
      $role_slug = match($role['name']) {
        'Admin'           => 'admin',
        'Manager'         => 'manager',
        'Accountant'      => 'accountant',
        'Operation Staff' => 'operations',
        default           => 'operations',
      };
      $users_for_role = $role_users[$role_slug] ?? [];
    ?>
    <div class="card">
      <div class="card-header flex-between">
        <div class="flex gap-12" style="align-items:center;">
          <div style="width:42px;height:42px;border-radius:10px;background:var(--c-<?= $color === 'navy' ? 'navy-800' : ($color === 'slate' ? 'slate-600' : ($color === 'olive' ? 'yellow' : 'green')) ?>);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">
            <?= match($role['name']) { 'Admin' => '🛡️', 'Manager' => '📊', 'Accountant' => '💹', default => '🔧' } ?>
          </div>
          <div>
            <h3 class="card-title" style="margin:0;"><?= htmlspecialchars($role['name']) ?></h3>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px;"><?= $role['user_count'] ?> user<?= $role['user_count'] !== 1 ? 's' : '' ?> assigned</div>
          </div>
        </div>
        <button class="btn btn-outline btn-sm" data-modal-open="modalEditPerms<?= $role['id'] ?>">✏️ Edit Permissions</button>
      </div>
      <div class="card-body" style="padding:16px 20px;">
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;"><?= htmlspecialchars($role['desc']) ?></p>

        <!-- Permission pills -->
        <div class="flex gap-8" style="flex-wrap:wrap;margin-bottom:16px;">
          <?php foreach ($perm_keys as $pk): ?>
            <span class="badge badge-<?= $role['permissions'][$pk] ? 'green' : 'gray' ?>" style="font-size:12px;padding:5px 10px;">
              <?= $role['permissions'][$pk] ? '✓' : '✗' ?> <?= ucfirst($pk) ?>
            </span>
          <?php endforeach; ?>
        </div>

        <!-- Users in this role -->
        <?php if (!empty($users_for_role)): ?>
          <div style="background:var(--bg-alt);border-radius:8px;padding:10px 12px;">
            <div style="font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Users with this role</div>
            <div class="flex gap-8" style="flex-wrap:wrap;">
              <?php foreach ($users_for_role as $u): ?>
                <div class="flex gap-8" style="align-items:center;background:#fff;border:1px solid var(--border-light);border-radius:20px;padding:3px 10px 3px 5px;">
                  <div style="width:24px;height:24px;border-radius:50%;background:var(--c-navy-800);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:10px;flex-shrink:0;">
                    <?= htmlspecialchars($u['avatar']) ?>
                  </div>
                  <span style="font-size:12px;font-weight:600;"><?= htmlspecialchars($u['name']) ?></span>
                  <?php if ($u['status'] === 'inactive'): ?>
                    <span class="badge badge-gray" style="font-size:10px;">Inactive</span>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- ── Permission Matrix Table ────────────────────────────────────────────── -->
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
          <?php foreach ($roles as $role): ?>
            <th style="text-align:center;width:120px;">
              <?= htmlspecialchars($role['name']) ?>
              <div style="font-size:10px;font-weight:400;opacity:.75;"><?= $role['user_count'] ?> user<?= $role['user_count'] !== 1 ? 's' : '' ?></div>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php
        $modules = [
          ['label' => '👁 View Dashboard',       'perms' => ['admin'=>true,  'manager'=>true,  'accountant'=>true,  'operations'=>true]],
          ['label' => '👥 User Management',       'perms' => ['admin'=>true,  'manager'=>false, 'accountant'=>false, 'operations'=>false]],
          ['label' => '🔐 Role Management',       'perms' => ['admin'=>true,  'manager'=>false, 'accountant'=>false, 'operations'=>false]],
          ['label' => '📋 Audit Log Access',      'perms' => ['admin'=>true,  'manager'=>true,  'accountant'=>false, 'operations'=>false]],
          ['label' => '📦 Create Shipments',      'perms' => ['admin'=>true,  'manager'=>false, 'accountant'=>false, 'operations'=>true]],
          ['label' => '✏️ Edit Shipments',        'perms' => ['admin'=>true,  'manager'=>true,  'accountant'=>false, 'operations'=>true]],
          ['label' => '🗑 Delete Shipments',      'perms' => ['admin'=>true,  'manager'=>false, 'accountant'=>false, 'operations'=>false]],
          ['label' => '🧾 Create Invoices',       'perms' => ['admin'=>true,  'manager'=>false, 'accountant'=>true,  'operations'=>false]],
          ['label' => '💳 Record Payments',       'perms' => ['admin'=>true,  'manager'=>false, 'accountant'=>true,  'operations'=>false]],
          ['label' => '⚙️ Billing Config',        'perms' => ['admin'=>true,  'manager'=>false, 'accountant'=>true,  'operations'=>false]],
          ['label' => '📈 Export Reports',        'perms' => ['admin'=>true,  'manager'=>true,  'accountant'=>true,  'operations'=>false]],
          ['label' => '⚠️ Manage Exceptions',    'perms' => ['admin'=>true,  'manager'=>true,  'accountant'=>false, 'operations'=>true]],
          ['label' => '📥 Import Data',           'perms' => ['admin'=>true,  'manager'=>false, 'accountant'=>false, 'operations'=>false]],
        ];
        $role_slugs = ['admin', 'manager', 'accountant', 'operations'];
        foreach ($modules as $mod):
        ?>
          <tr>
            <td style="font-weight:600;font-size:13px;"><?= $mod['label'] ?></td>
            <?php foreach ($role_slugs as $slug): ?>
              <?php $allowed = $mod['perms'][$slug] ?? false; ?>
              <td style="text-align:center;">
                <?php if ($allowed): ?>
                  <span style="color:var(--c-green);font-size:18px;font-weight:700;" title="Allowed">✓</span>
                <?php else: ?>
                  <span style="color:var(--border-color);font-size:18px;" title="Not allowed">✗</span>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer">
    <div class="flex gap-16" style="font-size:13px;">
      <span><span style="color:var(--c-green);font-weight:700;">✓</span> = Permission granted</span>
      <span><span style="color:var(--border-color);font-weight:700;">✗</span> = Permission denied</span>
      <span class="text-muted">Last updated: 2025-05-22 11:00 by Alex Administrator</span>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     MODALS — Edit Permissions per Role
════════════════════════════════════════════════════════════════════════════ -->
<?php foreach ($roles as $role): ?>
  <div class="modal-overlay" id="modalEditPerms<?= $role['id'] ?>">
    <div class="modal" style="max-width:500px;">
      <div class="modal-header">
        <h3 class="modal-title">✏️ Edit Permissions — <?= htmlspecialchars($role['name']) ?></h3>
        <button class="modal-close">✕</button>
      </div>
      <form data-feedback="Permissions updated successfully!">
        <div class="modal-body">
          <div class="alert alert-info" style="margin-bottom:16px;">
            ℹ️ Changes apply to all <strong><?= $role['user_count'] ?></strong> user<?= $role['user_count'] !== 1 ? 's' : '' ?> assigned to the <strong><?= htmlspecialchars($role['name']) ?></strong> role.
          </div>

          <div style="display:grid;gap:12px;">
            <?php
            $section_perms = [
              'System Access'       => ['View Dashboard','View Audit Log','Export Reports'],
              'User & Role Admin'   => ['Manage Users','Manage Roles','Import Data'],
              'Shipments & Orders'  => ['View Shipments','Create Shipments','Edit Shipments','Delete Shipments'],
              'Finance & Billing'   => ['View Invoices','Create/Edit Invoices','Record Payments','Configure Billing'],
              'Operations'          => ['Manage Routes','Manage Assets','Manage Exceptions','Tracking Logs'],
            ];
            foreach ($section_perms as $section => $perms):
            ?>
              <div style="border:1px solid var(--border-light);border-radius:8px;overflow:hidden;">
                <div style="background:var(--bg-alt);padding:8px 14px;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);"><?= $section ?></div>
                <div style="padding:10px 14px;display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                  <?php foreach ($perms as $perm): ?>
                    <label class="flex gap-8" style="align-items:center;cursor:pointer;font-size:13px;">
                      <input type="checkbox"
                        <?= (isset($role['permissions']['view']) && $role['permissions']['view']) ? 'checked' : '' ?>
                        style="width:16px;height:16px;accent-color:var(--c-navy-800);">
                      <?= htmlspecialchars($perm) ?>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline modal-close">Cancel</button>
          <button type="submit" class="btn btn-primary">💾 Save Permissions</button>
        </div>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<?php close_page(); ?>
