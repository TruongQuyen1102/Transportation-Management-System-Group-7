<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('operations');

$pods = get_pods();
$verified = array_filter($pods, fn($p) => $p['verified']);

open_page('Proof of Delivery', 'pod', [['label'=>'Operations'],['label'=>'Proof of Delivery']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Proof of Delivery</h1>
    <p class="page-subtitle">Verify and manage delivery confirmation documents, signatures, and images</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-ghost btn-sm">📥 Export Records</button>
    <button class="btn btn-primary" data-modal-open="uploadPodModal">📎 Upload POD</button>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
  <div class="stat-card navy">
    <div class="stat-icon" style="background:rgba(12,40,64,.08)">📋</div>
    <div class="stat-body">
      <div class="stat-value"><?= count($pods) ?></div>
      <div class="stat-label">Total POD Records</div>
      <div class="stat-trend neutral">All deliveries</div>
    </div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon" style="background:var(--c-green-bg)">✅</div>
    <div class="stat-body">
      <div class="stat-value"><?= count($verified) ?></div>
      <div class="stat-label">Verified</div>
      <div class="stat-trend up">100% verified rate</div>
    </div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon" style="background:var(--c-yellow-bg)">⏳</div>
    <div class="stat-body">
      <div class="stat-value"><?= count($pods) - count($verified) ?></div>
      <div class="stat-label">Pending Verification</div>
      <div class="stat-trend neutral">Awaiting review</div>
    </div>
  </div>
  <div class="stat-card slate">
    <div class="stat-icon" style="background:rgba(58,83,97,.08)">✍️</div>
    <div class="stat-body">
      <div class="stat-value"><?= count(array_filter($pods, fn($p) => $p['doc_type'] === 'Digital Signature')) ?></div>
      <div class="stat-label">Digital Signatures</div>
      <div class="stat-trend neutral">Most common type</div>
    </div>
  </div>
</div>

<!-- Info Cards for each POD -->
<div class="grid-auto mb-24">
  <?php foreach ($pods as $pod): ?>
  <div class="card">
    <div class="card-header">
      <div class="card-title">📦 <?= $pod['id'] ?> — <?= $pod['shipment_id'] ?></div>
      <?= status_badge($pod['verified'] ? 'DELIVERED' : 'PENDING') ?>
    </div>
    <div class="card-body">
      <div class="info-grid" style="grid-template-columns:1fr 1fr;">
        <div class="info-row">
          <div class="info-label">Order</div>
          <div class="info-value"><?= $pod['order_id'] ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Recipient</div>
          <div class="info-value"><?= htmlspecialchars($pod['recipient']) ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Signed At</div>
          <div class="info-value"><?= $pod['signed_at'] ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Location</div>
          <div class="info-value" style="font-size:12px;"><?= htmlspecialchars($pod['location']) ?></div>
        </div>
        <div class="info-row">
          <div class="info-label">Doc Type</div>
          <div class="info-value"><span class="badge badge-blue"><?= htmlspecialchars($pod['doc_type']) ?></span></div>
        </div>
        <div class="info-row">
          <div class="info-label">Verified</div>
          <div class="info-value"><?= $pod['verified'] ? '✅ Yes' : '⏳ Pending' ?></div>
        </div>
      </div>
      <?php if ($pod['notes']): ?>
      <div style="background:var(--c-neutral-100);border-radius:6px;padding:10px 12px;font-size:12px;color:var(--text-secondary);margin-top:12px;">
        📝 <?= htmlspecialchars($pod['notes']) ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="card-footer">
      <button class="btn btn-ghost btn-sm">🔍 View Documents</button>
      <button class="btn btn-outline btn-sm" onclick="showToast('Downloaded','POD record downloaded.','success')">⬇️ Download</button>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- Placeholder "No POD" for pending shipments -->
  <div class="card" style="border:2px dashed var(--border-color);box-shadow:none;">
    <div class="card-body">
      <div class="empty-state" style="padding:32px 16px;">
        <div class="empty-icon">📭</div>
        <div class="empty-title">SHP004 — Awaiting POD</div>
        <div class="empty-msg">This shipment is in transit. POD will be uploaded upon delivery confirmation.</div>
        <button class="btn btn-primary btn-sm" data-modal-open="uploadPodModal">📎 Upload when Ready</button>
      </div>
    </div>
  </div>
</div>

<!-- Full Table -->
<div class="card">
  <div class="card-header">
    <div class="card-title">📊 All POD Records</div>
    <div class="search-input-wrapper">
      <span class="search-icon">🔍</span>
      <input type="text" class="form-control" placeholder="Search..." data-table-search="podTable" style="min-width:200px;">
    </div>
  </div>
  <div class="table-wrapper" style="border-radius:0;border:none;box-shadow:none;">
    <table id="podTable">
      <thead>
        <tr>
          <th>POD ID</th>
          <th>Shipment</th>
          <th>Order</th>
          <th>Recipient</th>
          <th>Signed At</th>
          <th>Location</th>
          <th>Document Type</th>
          <th>Verified</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pods as $pod): ?>
        <tr>
          <td><strong><?= $pod['id'] ?></strong></td>
          <td><?= $pod['shipment_id'] ?></td>
          <td><?= $pod['order_id'] ?></td>
          <td><?= htmlspecialchars($pod['recipient']) ?></td>
          <td><?= $pod['signed_at'] ?></td>
          <td style="font-size:12px;"><?= htmlspecialchars($pod['location']) ?></td>
          <td><span class="badge badge-blue"><?= htmlspecialchars($pod['doc_type']) ?></span></td>
          <td><?= status_badge($pod['verified'] ? 'DELIVERED' : 'PENDING') ?></td>
          <td>
            <div class="action-menu">
              <button class="action-menu-btn" data-dropdown-toggle="pod-menu-<?= $pod['id'] ?>">⋯</button>
              <div class="action-dropdown" id="pod-menu-<?= $pod['id'] ?>">
                <button>🔍 View Documents</button>
                <button onclick="showToast('Downloaded','<?= $pod['id'] ?> downloaded.','success')">⬇️ Download PDF</button>
                <?php if (!$pod['verified']): ?>
                  <button onclick="showToast('Verified','POD marked as verified.','success')">✅ Verify</button>
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

<!-- Upload POD Modal -->
<div class="modal-overlay" id="uploadPodModal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <div class="modal-title">📎 <span>Upload Proof of Delivery</span></div>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body">
      <form data-feedback="POD uploaded and saved successfully.">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Shipment <span class="required">*</span></label>
            <select class="form-control" required>
              <option value="">Select shipment...</option>
              <?php foreach (get_shipments() as $s): ?>
                <option value="<?= $s['id'] ?>"><?= $s['id'] ?> — <?= htmlspecialchars(mb_strimwidth($s['route'],0,40,'…')) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Document Type <span class="required">*</span></label>
            <select class="form-control" required>
              <option>Digital Signature</option>
              <option>Photo + Signature</option>
              <option>Paper POD Scan</option>
              <option>Video Confirmation</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Recipient Name <span class="required">*</span></label>
            <input type="text" class="form-control" placeholder="Full name of recipient" required>
          </div>
          <div class="form-group">
            <label class="form-label">Delivery Location</label>
            <input type="text" class="form-control" placeholder="Warehouse / address">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Upload Documents</label>
          <div class="upload-zone">
            <div class="upload-icon">📎</div>
            <div class="upload-title">Drop files here or click to browse</div>
            <div class="upload-hint">Supports: JPG, PNG, PDF, MP4 — Max 20MB per file</div>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea class="form-control" rows="3" placeholder="Any additional delivery notes, partial returns, condition remarks..."></textarea>
        </div>
        <div class="modal-footer" style="padding:0;border:none;margin-top:8px;">
          <button type="button" class="btn btn-ghost" data-modal-close>Cancel</button>
          <button type="submit" class="btn btn-primary">📎 Upload & Save POD</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php close_page(); ?>
