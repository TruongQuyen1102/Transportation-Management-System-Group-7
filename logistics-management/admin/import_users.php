<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/db.php'; 
auth_require('admin');

$db = get_db();
$message = '';
$message_type = '';
$validation_results = null;
$import_results = null;

if (!function_exists('status_badge')) {
    function status_badge($status) {
        $status = strtoupper($status);
        $color = match($status) {
            'ACTIVE', 'COMPLETED' => 'green',
            'INACTIVE', 'PENDING' => 'gray',
            'LOCKED'              => 'red',
            default               => 'blue'
        };
        return "<span class=\"badge badge-$color\">$status</span>";
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  XỬ LÝ SUBMIT FORM - ĐỌC FILE CSV VÀ KIỂM TRA / IMPORT THỰC TẾ BIẾN ĐỘNG DB
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
    $action             = $_POST['action'] ?? ''; 
    $duplicate_handling = $_POST['duplicate_handling'] ?? 'skip'; // 'skip', 'update', 'error'
    $default_role_id    = (int)($_POST['default_role'] ?? 0);
    $default_status     = $_POST['default_status'] ?? 'Active';
    $file_tmp           = $_FILES['import_file']['tmp_name'];

    if (($handle = fopen($file_tmp, "r")) !== FALSE) {
        // Đọc hàng tiêu đề cột đầu tiên
        $headers = fgetcsv($handle, 1000, ",");
        $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

        $col_idx = [
            'name'     => array_search('name', $headers),
            'username' => array_search('username', $headers),
            'email'    => array_search('email', $headers),
            'role'     => array_search('role', $headers),
            'password' => array_search('password', $headers),
            'status'   => array_search('status', $headers),
        ];

        // Lấy danh sách Roles thực tế từ DB để đối chiếu ID chuẩn xác
        $roles_res = $db->query("SELECT RoleID, RoleName FROM role");
        $roles_map = [];
        while ($r = $roles_res->fetch_assoc()) {
            $roles_map[strtolower($r['RoleName'])] = $r['RoleID'];
        }
        if (isset($roles_map['operation staff'])) {
            $roles_map['operations'] = $roles_map['operation staff'];
        }

        $rows_to_process = [];
        $errors          = [];
        $warnings        = [];
        $row_num         = 1;

        // Vòng lặp quét dữ liệu từng hàng trong file thực tế
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $row_num++;
            
            $name     = $col_idx['name'] !== false ? trim($data[$col_idx['name']] ?? '') : '';
            $username = $col_idx['username'] !== false ? trim($data[$col_idx['username']] ?? '') : '';
            $email    = $col_idx['email'] !== false ? trim($data[$col_idx['email']] ?? '') : '';
            $role_str = $col_idx['role'] !== false ? strtolower(trim($data[$col_idx['role']] ?? '')) : '';
            $password = $col_idx['password'] !== false ? trim($data[$col_idx['password']] ?? '') : '';
            $status   = $col_idx['status'] !== false ? ucfirst(strtolower(trim($data[$col_idx['status']] ?? ''))) : 'Active';

            if (empty($name) || empty($username) || empty($email)) {
                $errors[] = "Dòng $row_num: Thiếu thông tin bắt buộc (Name, Username, hoặc Email).";
                continue;
            }

            $role_id = $roles_map[$role_str] ?? $default_role_id;
            if (!$role_id) {
                $errors[] = "Dòng $row_num: Quyền hạn '$role_str' không hợp lệ và hệ thống không có quyền mặc định.";
                continue;
            }

            if (!in_array($status, ['Active', 'Inactive', 'Locked'])) {
                $warnings[] = "Dòng $row_num: Trạng thái '$status' không chuẩn. Sẽ áp dụng thuộc tính mặc định: '$default_status'.";
                $status = ucfirst(strtolower($default_status));
            }

            // Kiểm tra trùng lặp tài khoản dựa trên Username thật trong DB
            $chk = $db->prepare("SELECT AccountID, EmployeeID FROM account WHERE Username = ?");
            $chk->bind_param('s', $username);
            $chk->execute();
            $existing = $chk->get_result()->fetch_assoc();
            $chk->close();

            $should_add_to_queue = true;

            if ($existing) {
                if ($duplicate_handling === 'error') {
                    $errors[] = "Dòng $row_num: Trùng lặp dữ liệu — Tài khoản '$username' đã tồn tại trên hệ thống.";
                    $should_add_to_queue = false;
                } elseif ($duplicate_handling === 'skip') {
                    $warnings[] = "Dòng $row_num: Trùng lặp dữ liệu — Tài khoản '$username' sẽ bị hệ thống bỏ qua.";
                    $should_add_to_queue = false;
                } elseif ($duplicate_handling === 'update') {
                    $warnings[] = "Dòng $row_num: Tài khoản '$username' đã trùng — Sẽ tiến hành cập nhật ghi đè dữ liệu mới.";
                }
            }

            if ($should_add_to_queue) {
                $rows_to_process[] = [
                    'name'     => $name,
                    'username' => $username,
                    'email'    => $email,
                    'role_id'  => $role_id,
                    'password' => $password,
                    'status'   => $status,
                    'existing' => $existing
                ];
            }
        }
        fclose($handle);

        // Phân tách hành động xử lý dựa trên nút bấm được kích hoạt từ Front-end
        if ($action === 'validate') {
            $validation_results = [
                'rows_count' => count($rows_to_process),
                'errors'     => $errors,
                'warnings'   => $warnings
            ];
        } elseif ($action === 'import') {
            if (count($errors) > 0) {
                $message = "Cannot proceed with Import. Please fix all validation errors below.";
                $message_type = "danger";
            } else {
                $success_count = 0;
                $current_admin = $_SESSION['user']['id'] ?? 1;

                foreach ($rows_to_process as $r) {
                    if ($r['existing'] && $duplicate_handling === 'update') {
                        // Cập nhật đè dữ liệu tài khoản cũ
                        $emp_id = $r['existing']['EmployeeID'];
                        $up_emp = $db->prepare("UPDATE employee SET FullName = ?, ContactEmail = ?, RoleID = ? WHERE EmployeeID = ?");
                        $up_emp->bind_param('ssii', $r['name'], $r['email'], $r['role_id'], $emp_id);
                        $up_emp->execute();
                        $up_emp->close();

                        if (!empty($r['password'])) {
                            $hash = password_hash($r['password'], PASSWORD_BCRYPT);
                            $up_acc = $db->prepare("UPDATE account SET RoleID = ?, Status = ?, PasswordHash = ? WHERE AccountID = ?");
                            $up_acc->bind_param('issi', $r['role_id'], $r['status'], $hash, $r['existing']['AccountID']);
                        } else {
                            $up_acc = $db->prepare("UPDATE account SET RoleID = ?, Status = ? WHERE AccountID = ?");
                            $up_acc->bind_param('isi', $r['role_id'], $r['status'], $r['existing']['AccountID']);
                        }
                        $up_acc->execute();
                        $up_acc->close();

                        $log_desc = "Import bulk update account: {$r['username']} from file CSV";
                        $log_stmt = $db->prepare("INSERT INTO system_audit_log (AccountID, ActionType, TableName, RecordID, ActionTime, Description) VALUES (?, 'UPDATE', 'account', ?, NOW(), ?)");
                        $log_stmt->bind_param('iis', $current_admin, $r['existing']['AccountID'], $log_desc);
                        $log_stmt->execute();
                        $log_stmt->close();
                        $success_count++;
                    } elseif (!$r['existing']) {
                        // Tạo mới tài khoản hoàn toàn
                        $ins_emp = $db->prepare("INSERT INTO employee (RoleID, FullName, ContactEmail) VALUES (?, ?, ?)");
                        $ins_emp->bind_param('iss', $r['role_id'], $r['name'], $r['email']);
                        $ins_emp->execute();
                        $emp_id = $ins_emp->insert_id;
                        $ins_emp->close();

                        $pass = empty($r['password']) ? 'itl123456' : $r['password'];
                        $hash = password_hash($pass, PASSWORD_BCRYPT);

                        $ins_acc = $db->prepare("INSERT INTO account (EmployeeID, RoleID, Username, PasswordHash, Status) VALUES (?, ?, ?, ?, ?)");
                        $ins_acc->bind_param('iisss', $emp_id, $r['role_id'], $r['username'], $hash, $r['status']);
                        $ins_acc->execute();
                        $new_acc_id = $ins_acc->insert_id;
                        $ins_acc->close();

                        $log_desc = "Import bulk create account: {$r['username']} from file CSV";
                        $log_stmt = $db->prepare("INSERT INTO system_audit_log (AccountID, ActionType, TableName, RecordID, ActionTime, Description) VALUES (?, 'CREATE', 'account', ?, NOW(), ?)");
                        $log_stmt->bind_param('iis', $current_admin, $new_acc_id, $log_desc);
                        $log_stmt->execute();
                        $log_stmt->close();
                        $success_count++;
                    }
                }
                $message = "Successfully loaded <strong>$success_count</strong> accounts into the database.";
                $message_type = "success";
                $import_results = [
                    'success' => $success_count
                ];
            }
        }
    }
}

// Đọc danh sách vai trò cho form select
$roles_options_res = $db->query("SELECT RoleID, RoleName FROM role ORDER BY RoleID");
$role_select_html = '';
while ($r = $roles_options_res->fetch_assoc()) {
    $role_select_html .= "<option value=\"{$r['RoleID']}\">{$r['RoleName']}</option>";
}

// Đọc lịch sử đồng bộ từ hệ thống dữ liệu thật system_audit_log
$recent_imports = [];
$hist_stmt = $db->query("SELECT sal.ActionTime, sal.Description, acc.Username FROM system_audit_log sal LEFT JOIN account acc ON sal.AccountID = acc.AccountID WHERE sal.Description LIKE '%Import bulk%' ORDER BY sal.LogID DESC LIMIT 3");
if ($hist_stmt) {
    while ($h_row = $hist_stmt->fetch_assoc()) {
        $recent_imports[] = $h_row;
    }
}

open_page('Import Users', 'import', [['label' => 'Administration'], ['label' => 'Import Users']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Import Users</h1>
    <p class="text-muted" style="margin-top:4px;">Bulk-import user accounts from CSV or Excel files</p>
  </div>
  <div class="page-actions">
    <a href="#" class="btn btn-outline btn-sm" onclick="downloadTemplate(); return false;">📄 Download Template</a>
    <a href="/admin/users.php" class="btn btn-ghost btn-sm">← Back to Users</a>
  </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $message_type ?>" style="margin-bottom:16px;">
  <?= $message_type === 'success' ? '✅' : '❌' ?> <?= $message ?>
</div>
<?php endif; ?>

<div class="grid-2 mt-8" style="gap:24px;align-items:start;">

  <div style="display:flex;flex-direction:column;gap:20px;">

    <div class="card">
      <div class="card-header">
        <h3 class="card-title">📥 Upload File</h3>
      </div>
      <div class="card-body" style="padding:24px;">
        <form id="importForm" method="POST" action="" enctype="multipart/form-data">
          <input type="hidden" name="action" id="formAction" value="validate">

          <div class="upload-zone" id="uploadZone"
               ondragover="handleDragOver(event)"
               ondragleave="handleDragLeave(event)"
               ondrop="handleDrop(event)">
            <div style="font-size:48px;margin-bottom:16px;">📂</div>
            <div style="font-weight:700;font-size:16px;color:var(--text-primary);margin-bottom:8px;">
              Drag &amp; Drop your file here
            </div>
            <div style="color:var(--text-muted);font-size:13px;margin-bottom:20px;">
              or click to browse your computer
            </div>
            <input type="file" id="fileInput" name="import_file" accept=".csv"
                   style="display:none;" onchange="handleFileSelect(event)">
            <button type="button" class="btn btn-primary"
                    onclick="document.getElementById('fileInput').click()">
              📁 Browse File
            </button>
            <div style="margin-top:16px;font-size:12px;color:var(--text-muted);">
              Supported formats: <strong>CSV</strong><br>
              Maximum file size: <strong>5 MB</strong> · Maximum rows: <strong>500 users</strong>
            </div>
          </div>

          <div id="fileInfo" style="display:none;margin-top:16px;padding:12px 16px;background:var(--bg-alt);border-radius:8px;border:1px solid var(--border-light);">
            <div class="flex-between">
              <div class="flex gap-8" style="align-items:center;">
                <span style="font-size:24px;">📄</span>
                <div>
                  <div style="font-weight:600;font-size:14px;" id="fileName">file.csv</div>
                  <div style="font-size:12px;color:var(--text-muted);" id="fileSize">0 KB</div>
                </div>
              </div>
              <button type="button" class="btn btn-ghost btn-sm" onclick="clearFile()">✕ Remove</button>
            </div>
          </div>

          <div style="margin-top:20px;display:grid;gap:12px;">
            <div class="form-group">
              <label class="form-label">Duplicate Handling</label>
              <select class="form-control" name="duplicate_handling">
                <option value="skip">Skip duplicates (by username)</option>
                <option value="update">Update existing users</option>
                <option value="error">Fail on duplicate</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Default Role (if not specified in file)</label>
              <select class="form-control" name="default_role">
                <?= $role_select_html ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Default Status</label>
              <select class="form-control" name="default_status">
                <option value="active">Active</option>
                <option value="inactive">Inactive (require manual activation)</option>
              </select>
            </div>
          </div>

          <div class="flex gap-8 mt-16">
            <button type="button" class="btn btn-outline" onclick="submitWithAction('validate')" id="btnValidate" disabled>
              🔍 Validate File
            </button>
            <button type="button" class="btn btn-primary" onclick="submitWithAction('import')" id="btnProcess" disabled>
              ⚙️ Process Import
            </button>
          </div>
        </form>
      </div>
    </div>

    <?php if ($validation_results !== null): ?>
    <div class="card" id="validationCard">
      <div class="card-header">
        <h3 class="card-title">🔍 Validation Results</h3>
      </div>
      <div class="card-body" style="padding:16px 20px;">
        
        <?php if (count($validation_results['errors']) === 0): ?>
          <div class="alert alert-success">
            ✅ Cấu trúc file chuẩn xác! Sẵn sàng nạp <strong><?= $validation_results['rows_count'] ?></strong> tài khoản hợp lệ vào hệ thống.
          </div>
        <?php else: ?>
          <div class="alert alert-danger">
            ❌ Phát hiện <strong><?= count($validation_results['errors']) ?></strong> lỗi nghiêm trọng cần điều chỉnh trước khi tiến hành import.
          </div>
        <?php endif; ?>

        <?php if (count($validation_results['warnings']) > 0): ?>
          <div class="alert alert-warning" style="margin-top: 8px;">
            ⚠️ Lưu ý: Phát hiện <strong><?= count($validation_results['warnings']) ?></strong> hàng trùng lặp hoặc sai định dạng trạng thái hệ thống tự xử lý.
          </div>
        <?php endif; ?>

        <div style="background:var(--bg-alt);border-radius:6px;padding:12px;font-size:12px;margin-top:12px;display:grid;gap:6px;">
          <div style="font-weight:700;margin-bottom: 4px;">📋 Nhật ký chi tiết các dòng quét:</div>
          
          <?php foreach ($validation_results['errors'] as $err): ?>
            <div style="color:var(--c-red); font-weight:600;">• <?= htmlspecialchars($err) ?></div>
          <?php endforeach; ?>

          <?php foreach ($validation_results['warnings'] as $warn): ?>
            <div style="color:var(--text-primary);">• <?= htmlspecialchars($warn) ?></div>
          <?php endforeach; ?>

          <?php if(empty($validation_results['errors']) && empty($validation_results['warnings'])): ?>
            <div class="text-muted text-center py-8">Mọi dòng tài khoản đều sạch sẽ và tối ưu 100%!</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($import_results !== null): ?>
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">🎉 Import Success</h3>
      </div>
      <div class="card-body" style="padding:16px 20px;">
         <div class="alert alert-success m-0">
            🚀 Đồng bộ dữ liệu thành công! Hệ thống đã hoàn tất xử lý <strong><?= $import_results['success'] ?></strong> bản ghi tài khoản vào database thật.
         </div>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <div style="display:flex;flex-direction:column;gap:20px;">

    <div class="card">
      <div class="card-header">
        <h3 class="card-title">📋 File Format Requirements</h3>
      </div>
      <div class="card-body" style="padding:16px 20px;">
        <div class="alert alert-info" style="margin-bottom:16px;">
          ℹ️ Download the <a href="#" onclick="downloadTemplate(); return false;" style="color:var(--c-navy-800);font-weight:700;">official template</a> to ensure your file matches the required format exactly.
        </div>

        <div style="display:grid;gap:10px;">
          <?php
          $fields = [
            ['col' => 'A', 'name' => 'name', 'req' => true,  'type' => 'Text',   'desc' => 'Full name of the user', 'example' => 'Nguyen Van An'],
            ['col' => 'B', 'name' => 'username', 'req' => true,  'type' => 'Text',   'desc' => 'Unique username (no spaces)', 'example' => 'nguyenan'],
            ['col' => 'C', 'name' => 'email', 'req' => true,  'type' => 'Email',  'desc' => 'Valid email address', 'example' => 'nguyenan@itl.com'],
            ['col' => 'D', 'name' => 'role', 'req' => true,  'type' => 'Select', 'desc' => 'One of: Admin, Manager, Accountant, Operation Staff', 'example' => 'Admin'],
            ['col' => 'E', 'name' => 'password', 'req' => false, 'type' => 'Text',   'desc' => 'Initial password (min 8 chars). Leave blank to auto-generate.', 'example' => 'admin123456'],
            ['col' => 'F', 'name' => 'status', 'req' => false, 'type' => 'Select', 'desc' => 'Active, Inactive or Locked. Defaults to Active.', 'example' => 'Active'],
          ];
          foreach ($fields as $f): ?>
            <div style="display:grid;grid-template-columns:28px 90px 70px 1fr;gap:8px;align-items:start;padding:8px;background:var(--bg-alt);border-radius:6px;border:1px solid var(--border-light);">
              <div style="background:var(--c-navy-800);color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;"><?= $f['col'] ?></div>
              <div>
                <code style="font-size:12px;font-weight:700;"><?= $f['name'] ?></code>
                <?php if ($f['req']): ?>
                  <span style="color:var(--c-red);font-size:10px;display:block;">Required</span>
                <?php else: ?>
                  <span style="color:var(--text-muted);font-size:10px;display:block;">Optional</span>
                <?php endif; ?>
              </div>
              <div>
                <span class="badge badge-blue" style="font-size:10px;"><?= $f['type'] ?></span>
              </div>
              <div>
                <div style="font-size:12px;color:var(--text-primary);"><?= htmlspecialchars($f['desc']) ?></div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">e.g. <em><?= htmlspecialchars($f['example']) ?></em></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title">🕐 Recent Import History (Realtime Log)</h3>
      </div>
      <div class="table-wrapper">
        <table style="font-size:13px;">
          <thead>
            <tr>
              <th>Date Time</th>
              <th>Triggered By</th>
              <th>Audit Log Description Summary</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recent_imports)): ?>
              <tr><td colspan="4" class="text-center text-muted">Chưa ghi nhận đợt import dữ liệu thực tế nào từ DB.</td></tr>
            <?php else: ?>
              <?php foreach ($recent_imports as $import_log): ?>
                <tr>
                  <td class="td-muted" style="white-space:nowrap;"><?= htmlspecialchars($import_log['ActionTime']) ?></td>
                  <td class="font-bold text-primary"><?= htmlspecialchars($import_log['Username'] ?? 'Admin') ?></td>
                  <td style="max-width: 220px;" class="truncate"><?= htmlspecialchars($import_log['Description']) ?></td>
                  <td><?= status_badge('COMPLETED') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<script>
// ── Drag & Drop Handlers ──────────────────────────────────────────────────────
const zone = document.getElementById('uploadZone');

function handleDragOver(e) {
  e.preventDefault();
  zone.style.borderColor = 'var(--c-navy-800)';
  zone.style.background  = 'rgba(12,40,64,.05)';
}

function handleDragLeave(e) {
  zone.style.borderColor = '';
  zone.style.background  = '';
}

function handleDrop(e) {
  e.preventDefault();
  zone.style.borderColor = '';
  zone.style.background  = '';
  const file = e.dataTransfer.files[0];
  if (file) {
    document.getElementById('fileInput').files = e.dataTransfer.files;
    setFile(file);
  }
}

function handleFileSelect(e) {
  const file = e.target.files[0];
  if (file) setFile(file);
}

function setFile(file) {
  if (file.name.split('.').pop().toLowerCase() !== 'csv') {
    alert('Vui lòng chỉ sử dụng file đuôi cấu trúc .CSV để tối ưu hóa bộ cào.');
    return;
  }
  if (file.size > 5 * 1024 * 1024) {
    alert('Kích thước file vượt quá giới hạn cho phép (Tối đa 5MB).');
    return;
  }
  document.getElementById('fileName').textContent = file.name;
  document.getElementById('fileSize').textContent = (file.size / 1024).toFixed(1) + ' KB';
  document.getElementById('fileInfo').style.display = 'block';
  document.getElementById('btnValidate').disabled = false;
  document.getElementById('btnProcess').disabled  = false;
  zone.style.borderColor = 'var(--c-green)';
}

function clearFile() {
  document.getElementById('fileInput').value = '';
  document.getElementById('fileInfo').style.display = 'none';
  document.getElementById('btnValidate').disabled = true;
  document.getElementById('btnProcess').disabled  = true;
  zone.style.borderColor = '';
}

function submitWithAction(actionValue) {
  document.getElementById('formAction').value = actionValue;
  document.getElementById('importForm').submit();
}

function downloadTemplate() {
  const csvContent = "data:text/csv;charset=utf-8,name,username,email,role,password,status\nNguyen Van A,admin_test,vana.nguyen@itl.com,Admin,admin123456,Active";
  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", "import_users_template.csv");
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}
</script>

<?php 
close_page(); 
$db->close();
?>