<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('admin');

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

<div class="grid-2 mt-8" style="gap:24px;align-items:start;">

  <!-- ── Left Column: Upload + Instructions ────────────────────────────────── -->
  <div style="display:flex;flex-direction:column;gap:20px;">

    <!-- Upload Zone -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">📥 Upload File</h3>
      </div>
      <div class="card-body" style="padding:24px;">
        <form id="importForm" data-feedback="File uploaded! Processing import…">
          <!-- Drop Zone -->
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
            <input type="file" id="fileInput" accept=".csv,.xlsx,.xls"
                   style="display:none;" onchange="handleFileSelect(event)">
            <button type="button" class="btn btn-primary"
                    onclick="document.getElementById('fileInput').click()">
              📁 Browse File
            </button>
            <div style="margin-top:16px;font-size:12px;color:var(--text-muted);">
              Supported formats: <strong>CSV</strong>, <strong>XLSX</strong>, <strong>XLS</strong><br>
              Maximum file size: <strong>5 MB</strong> · Maximum rows: <strong>500 users</strong>
            </div>
          </div>

          <!-- Selected File Info -->
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

          <!-- Import Options -->
          <div style="margin-top:20px;display:grid;gap:12px;">
            <div class="form-group">
              <label class="form-label">Duplicate Handling</label>
              <select class="form-control">
                <option value="skip">Skip duplicates (by username)</option>
                <option value="update">Update existing users</option>
                <option value="error">Fail on duplicate</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Default Role (if not specified in file)</label>
              <select class="form-control">
                <option value="operations">Operations</option>
                <option value="manager">Manager</option>
                <option value="accountant">Accountant</option>
                <option value="admin">Admin</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Default Status</label>
              <select class="form-control">
                <option value="active">Active</option>
                <option value="inactive">Inactive (require manual activation)</option>
              </select>
            </div>
            <label class="flex gap-8" style="align-items:center;cursor:pointer;">
              <input type="checkbox" checked style="width:16px;height:16px;accent-color:var(--c-navy-800);">
              <span style="font-size:13px;">Send welcome email to imported users</span>
            </label>
            <label class="flex gap-8" style="align-items:center;cursor:pointer;">
              <input type="checkbox" checked style="width:16px;height:16px;accent-color:var(--c-navy-800);">
              <span style="font-size:13px;">Validate all rows before import (recommended)</span>
            </label>
          </div>

          <div class="flex gap-8 mt-16">
            <button type="button" class="btn btn-outline" onclick="validateFile()" id="btnValidate" disabled>
              🔍 Validate File
            </button>
            <button type="submit" class="btn btn-primary" id="btnProcess" disabled>
              ⚙️ Process Import
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Validation Results (hidden initially) -->
    <div class="card" id="validationCard" style="display:none;">
      <div class="card-header">
        <h3 class="card-title">🔍 Validation Results</h3>
      </div>
      <div class="card-body" style="padding:16px 20px;">
        <div class="alert alert-success" id="valSuccess" style="display:none;">
          ✅ File validated successfully! <strong id="valRowCount">0</strong> rows ready to import.
        </div>
        <div class="alert alert-warning" id="valWarnings" style="display:none;">
          ⚠️ <strong id="valWarnCount">0</strong> warnings found — import can proceed but review recommended.
        </div>
        <div class="alert alert-danger" id="valErrors" style="display:none;">
          ❌ <strong id="valErrCount">0</strong> errors found — fix before importing.
        </div>
        <div id="valErrorList" style="margin-top:12px;"></div>
      </div>
    </div>

  </div>

  <!-- ── Right Column: Instructions + Preview ──────────────────────────────── -->
  <div style="display:flex;flex-direction:column;gap:20px;">

    <!-- Instructions Card -->
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
            ['col' => 'C', 'name' => 'email', 'req' => true,  'type' => 'Email',  'desc' => 'Valid email address', 'example' => 'nguyenan@logitrack.com'],
            ['col' => 'D', 'name' => 'role', 'req' => true,  'type' => 'Select', 'desc' => 'One of: admin, manager, accountant, operations', 'example' => 'operations'],
            ['col' => 'E', 'name' => 'password', 'req' => false, 'type' => 'Text',   'desc' => 'Initial password (min 8 chars). Leave blank to auto-generate.', 'example' => 'Pass@1234'],
            ['col' => 'F', 'name' => 'status', 'req' => false, 'type' => 'Select', 'desc' => 'active or inactive. Defaults to active.', 'example' => 'active'],
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

        <div class="alert alert-warning" style="margin-top:16px;">
          ⚠️ <strong>Important:</strong> Row 1 must be the header row exactly as shown. Do not modify column headers or add extra columns.
        </div>
      </div>
    </div>

    <!-- Preview Table -->
    <div class="card">
      <div class="card-header flex-between">
        <h3 class="card-title">👁 Sample Data Preview</h3>
        <span class="badge badge-gray">Expected Format</span>
      </div>
      <div class="table-wrapper" style="overflow-x:auto;">
        <table style="font-size:12px;min-width:600px;">
          <thead>
            <tr style="background:var(--c-navy-800);">
              <th style="background:var(--c-navy-800);color:#fff;font-size:11px;">name</th>
              <th style="background:var(--c-navy-800);color:#fff;font-size:11px;">username</th>
              <th style="background:var(--c-navy-800);color:#fff;font-size:11px;">email</th>
              <th style="background:var(--c-navy-800);color:#fff;font-size:11px;">role</th>
              <th style="background:var(--c-navy-800);color:#fff;font-size:11px;">password</th>
              <th style="background:var(--c-navy-800);color:#fff;font-size:11px;">status</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $sample_rows = [
              ['Nguyen Van An',    'nguyenan',  'nguyenan@logitrack.com',  'operations', 'Ops@2025!',  'active'],
              ['Tran Thi Binh',   'tranthib',  'tranthib@logitrack.com',  'operations', 'Ops@2025!',  'active'],
              ['Le Van Cuong',    'levancuong','levancuong@logitrack.com', 'manager',    'Mgr@2025!',  'active'],
              ['Pham Thi Dung',   'phamdung',  'phamdung@logitrack.com',  'accountant', '',           'active'],
              ['Hoang Van Duc',   'hoangduc',  'hoangduc@logitrack.com',  'operations', 'Ops@2025!',  'inactive'],
            ];
            $role_colors_map = ['admin'=>'navy','manager'=>'slate','accountant'=>'olive','operations'=>'green'];
            foreach ($sample_rows as $i => $row): ?>
              <tr style="<?= $i % 2 === 1 ? 'background:var(--bg-alt);' : '' ?>">
                <td style="font-weight:600;"><?= htmlspecialchars($row[0]) ?></td>
                <td><code style="font-size:11px;color:var(--c-navy-800);"><?= htmlspecialchars($row[1]) ?></code></td>
                <td class="td-muted"><?= htmlspecialchars($row[2]) ?></td>
                <td><span class="badge badge-<?= $role_colors_map[$row[3]] ?? 'gray' ?>" style="font-size:10px;"><?= $row[3] ?></span></td>
                <td class="td-muted"><?= $row[4] ? '<code style="font-size:11px;">••••••••</code>' : '<span style="color:var(--text-muted);font-style:italic;">auto-gen</span>' ?></td>
                <td><?= status_badge($row[5]) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer">
        <span style="font-size:12px;color:var(--text-muted);">This is a preview of expected file format. Download the template for a ready-to-fill spreadsheet.</span>
      </div>
    </div>

    <!-- Recent Import History -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">🕐 Recent Import History</h3>
      </div>
      <div class="table-wrapper">
        <table style="font-size:13px;">
          <thead>
            <tr>
              <th>Date</th>
              <th>File</th>
              <th>Rows</th>
              <th>Success</th>
              <th>Errors</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="td-muted">2025-05-20 09:10</td>
              <td><span style="font-family:monospace;font-size:11px;">users_batch_may20.csv</span></td>
              <td>3</td>
              <td style="color:var(--c-green);font-weight:700;">3</td>
              <td style="color:var(--c-red);font-weight:700;">0</td>
              <td><?= status_badge('COMPLETED') ?></td>
            </tr>
            <tr style="background:var(--bg-alt);">
              <td class="td-muted">2025-04-15 14:30</td>
              <td><span style="font-family:monospace;font-size:11px;">new_ops_staff_apr.xlsx</span></td>
              <td>5</td>
              <td style="color:var(--c-green);font-weight:700;">4</td>
              <td style="color:var(--c-red);font-weight:700;">1</td>
              <td><?= status_badge('COMPLETED') ?></td>
            </tr>
            <tr>
              <td class="td-muted">2025-03-02 10:00</td>
              <td><span style="font-family:monospace;font-size:11px;">initial_users_v1.csv</span></td>
              <td>6</td>
              <td style="color:var(--c-green);font-weight:700;">6</td>
              <td style="color:var(--c-red);font-weight:700;">0</td>
              <td><?= status_badge('COMPLETED') ?></td>
            </tr>
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
  if (file) setFile(file);
}

function handleFileSelect(e) {
  const file = e.target.files[0];
  if (file) setFile(file);
}

function setFile(file) {
  const allowed = ['.csv', '.xlsx', '.xls'];
  const ext = '.' + file.name.split('.').pop().toLowerCase();
  if (!allowed.includes(ext)) {
    alert('Invalid file type. Please upload a CSV or Excel file.');
    return;
  }
  if (file.size > 5 * 1024 * 1024) {
    alert('File exceeds the 5 MB limit.');
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
  document.getElementById('fileInput').value      = '';
  document.getElementById('fileInfo').style.display = 'none';
  document.getElementById('btnValidate').disabled = true;
  document.getElementById('btnProcess').disabled  = true;
  document.getElementById('validationCard').style.display = 'none';
  zone.style.borderColor = '';
}

// ── Simulated Validation ──────────────────────────────────────────────────────
function validateFile() {
  const card = document.getElementById('validationCard');
  card.style.display = 'block';
  card.scrollIntoView({ behavior: 'smooth', block: 'start' });

  // Show simulated success result
  document.getElementById('valSuccess').style.display  = 'block';
  document.getElementById('valWarnings').style.display = 'block';
  document.getElementById('valErrors').style.display   = 'none';
  document.getElementById('valRowCount').textContent   = '5';
  document.getElementById('valWarnCount').textContent  = '1';

  document.getElementById('valErrorList').innerHTML = `
    <div style="background:var(--bg-alt);border-radius:6px;padding:12px;font-size:12px;">
      <div style="font-weight:700;margin-bottom:6px;">⚠️ Warnings</div>
      <div style="color:var(--text-muted);">Row 5: <em>status</em> value "Inactive" should be lowercase "inactive" — will be auto-corrected.</div>
    </div>
  `;
}

// ── Template Download (decorative) ──────────────────────────────────────────
function downloadTemplate() {
  // In production this would serve the actual file
  alert('Template download would start here. File: import_users_template.xlsx');
}
</script>

<?php close_page(); ?>
