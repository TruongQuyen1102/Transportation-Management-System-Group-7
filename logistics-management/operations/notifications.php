<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/sample_data.php';
auth_require('operations');

$user = current_user();
$notifs = array_values(get_notifications($user['id']));
// Fallback for demo: use ACC004 if current user has no notifs
if (empty($notifs)) {
    $notifs = array_values(get_notifications('ACC004'));
}

$unread = array_filter($notifs, fn($n) => !$n['is_read']);
$read   = array_filter($notifs, fn($n) => $n['is_read']);

$notifIcons = [
    'New Shipment Assigned'       => '📦',
    'Delivery Exception Reported' => '⚠️',
    'Order'                       => '📋',
    'Delivery Delay'              => '🕐',
    'Exception Requires Approval' => '🔔',
    'Invoice'                     => '🧾',
    'Payment Received'            => '💳',
    'Shipment'                    => '🚛',
];

function getIcon(string $title): string {
    foreach (['Exception'=>'⚠️','Shipment'=>'🚛','Invoice'=>'🧾','Payment'=>'💳','Order'=>'📋','Delay'=>'🕐','Approval'=>'🔔'] as $k=>$v) {
        if (stripos($title, $k) !== false) return $v;
    }
    return '🔔';
}

open_page('Notifications', 'notifications', [['label'=>'Operations'],['label'=>'Notifications']]);
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Notifications</h1>
    <p class="page-subtitle">System-generated alerts for shipments, exceptions, and operational updates</p>
  </div>
  <div class="page-actions">
    <button class="btn btn-ghost btn-sm" onclick="markAllRead()">✓ Mark All Read</button>
    <button class="btn btn-ghost btn-sm" onclick="showToast('Cleared','All notifications cleared.','info')">🗑 Clear All</button>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px;">
  <div class="stat-card red">
    <div class="stat-icon" style="background:var(--c-red-bg)">🔔</div>
    <div class="stat-body">
      <div class="stat-value"><?= count($unread) ?></div>
      <div class="stat-label">Unread</div>
      <div class="stat-trend down">Require your attention</div>
    </div>
  </div>
  <div class="stat-card navy">
    <div class="stat-icon" style="background:rgba(12,40,64,.08)">📬</div>
    <div class="stat-body">
      <div class="stat-value"><?= count($notifs) ?></div>
      <div class="stat-label">Total</div>
      <div class="stat-trend neutral">All notifications</div>
    </div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon" style="background:var(--c-green-bg)">✅</div>
    <div class="stat-body">
      <div class="stat-value"><?= count($read) ?></div>
      <div class="stat-label">Read</div>
      <div class="stat-trend up">Acknowledged</div>
    </div>
  </div>
</div>

<!-- Filter Tabs -->
<div class="card">
  <div class="card-header" style="padding:0;">
    <div class="tabs" style="margin:0;border-bottom:none;padding:0 16px;" data-tab-group>
      <button class="tab-btn active" data-tab="tab-all">All (<?= count($notifs) ?>)</button>
      <button class="tab-btn" data-tab="tab-unread">Unread (<?= count($unread) ?>)</button>
      <button class="tab-btn" data-tab="tab-read">Read (<?= count($read) ?>)</button>
    </div>
  </div>

  <!-- All -->
  <div id="tab-all" class="tab-panel active">
    <?php if (empty($notifs)): ?>
      <div class="empty-state"><div class="empty-icon">📭</div><div class="empty-title">No Notifications</div></div>
    <?php else: ?>
      <div class="activity-feed" style="max-height:none;">
        <?php foreach ($notifs as $n): ?>
        <div class="activity-item notif-item <?= !$n['is_read'] ? 'notif-unread' : '' ?>"
             id="notif-<?= $n['id'] ?>"
             style="padding:16px 20px;<?= !$n['is_read'] ? 'background:rgba(232,184,75,.04);border-left:3px solid var(--c-yellow);' : '' ?>">
          <div class="activity-avatar" style="<?= !$n['is_read'] ? 'background:linear-gradient(135deg,var(--c-yellow),var(--c-olive-light));color:var(--c-navy-900);' : '' ?>">
            <?= getIcon($n['title']) ?>
          </div>
          <div class="activity-body" style="flex:1;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px;">
              <div class="activity-text" style="font-weight:<?= !$n['is_read'] ? '700' : '500' ?>;color:var(--text-primary);">
                <?= htmlspecialchars($n['title']) ?>
              </div>
              <?php if (!$n['is_read']): ?>
                <span class="badge badge-yellow" style="font-size:10px;padding:2px 6px;">NEW</span>
              <?php endif; ?>
            </div>
            <div class="activity-text" style="font-size:13px;"><?= htmlspecialchars($n['msg']) ?></div>
            <div class="activity-time">🕐 <?= $n['created_at'] ?></div>
          </div>
          <div style="display:flex;gap:6px;flex-shrink:0;align-items:flex-start;">
            <?php if (!$n['is_read']): ?>
              <button class="btn btn-ghost btn-sm" onclick="markRead('<?= $n['id'] ?>')">✓ Read</button>
            <?php endif; ?>
            <button class="btn btn-ghost btn-sm" onclick="document.getElementById('notif-<?= $n['id'] ?>').style.display='none'">✕</button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Unread -->
  <div id="tab-unread" class="tab-panel">
    <?php if (empty($unread)): ?>
      <div class="empty-state" style="padding:40px;"><div class="empty-icon">✅</div><div class="empty-title">All Caught Up!</div><div class="empty-msg">No unread notifications.</div></div>
    <?php else: ?>
      <div class="activity-feed" style="max-height:none;">
        <?php foreach ($unread as $n): ?>
        <div class="activity-item" style="padding:16px 20px;background:rgba(232,184,75,.04);border-left:3px solid var(--c-yellow);">
          <div class="activity-avatar" style="background:linear-gradient(135deg,var(--c-yellow),var(--c-olive-light));color:var(--c-navy-900);">
            <?= getIcon($n['title']) ?>
          </div>
          <div class="activity-body" style="flex:1;">
            <div style="font-weight:700;color:var(--text-primary);margin-bottom:3px;"><?= htmlspecialchars($n['title']) ?></div>
            <div class="activity-text" style="font-size:13px;"><?= htmlspecialchars($n['msg']) ?></div>
            <div class="activity-time">🕐 <?= $n['created_at'] ?></div>
          </div>
          <button class="btn btn-accent btn-sm" onclick="showToast('Marked','Notification marked as read.','success')">✓ Mark Read</button>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Read -->
  <div id="tab-read" class="tab-panel">
    <?php if (empty($read)): ?>
      <div class="empty-state" style="padding:40px;"><div class="empty-icon">📭</div><div class="empty-title">No Read Notifications</div></div>
    <?php else: ?>
      <div class="activity-feed" style="max-height:none;">
        <?php foreach ($read as $n): ?>
        <div class="activity-item" style="padding:16px 20px;opacity:.75;">
          <div class="activity-avatar"><?= getIcon($n['title']) ?></div>
          <div class="activity-body" style="flex:1;">
            <div style="font-weight:600;color:var(--text-primary);margin-bottom:3px;"><?= htmlspecialchars($n['title']) ?></div>
            <div class="activity-text" style="font-size:13px;"><?= htmlspecialchars($n['msg']) ?></div>
            <div class="activity-time">🕐 <?= $n['created_at'] ?></div>
          </div>
          <span class="badge badge-green">Read</span>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php
$extraScripts = [<<<JS
function markRead(id) {
  const el = document.getElementById('notif-' + id);
  if (!el) return;
  el.style.background = '';
  el.style.borderLeft = '';
  const badge = el.querySelector('.badge-yellow');
  if (badge) badge.remove();
  const btn = el.querySelector('button');
  if (btn && btn.textContent.includes('Read')) btn.remove();
  showToast('Marked as Read', 'Notification acknowledged.', 'success');
}

function markAllRead() {
  document.querySelectorAll('.notif-unread').forEach(el => {
    el.style.background = '';
    el.style.borderLeft = '';
    el.classList.remove('notif-unread');
    const badge = el.querySelector('.badge-yellow');
    if (badge) badge.remove();
  });
  showToast('All Read', 'All notifications marked as read.', 'success');
}
JS];
close_page();
?>
