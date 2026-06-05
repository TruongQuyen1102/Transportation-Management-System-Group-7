<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page_shell.php';
require_once __DIR__ . '/../config/db.php';
auth_require('operations');

$db = get_db();
$message = '';
$message_type = '';

$user = current_user();
$current_account_id = $user['id'] ?? 4; // Lấy đúng ID từ session

// ══════════════════════════════════════════════════════════════════════════════
//  POST HANDLING
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Tự động detect cột ID (NotifID hoặc NotificationID) để tránh lỗi lệch schema
    $pk_col = 'NotifID';
    $check = $db->query("SHOW COLUMNS FROM system_notification LIKE 'NotificationID'");
    if ($check && $check->num_rows > 0) {
        $pk_col = 'NotificationID';
    }

    if ($action === 'mark_read') {
        $notif_id = (int)($_POST['notif_id'] ?? 0);
        if ($notif_id > 0) {
            $stmt = $db->prepare("UPDATE system_notification SET IsRead = '1' WHERE $pk_col = ?");
            if ($stmt) {
                $stmt->bind_param('i', $notif_id);
                if ($stmt->execute()) {
                    $message = "Notification marked as read.";
                    $message_type = "success";
                }
            }
        }
    } 
    elseif ($action === 'mark_all_read') {
        $stmt = $db->prepare("UPDATE system_notification SET IsRead = '1' WHERE (AccountID = ? OR AccountID IS NULL) AND IsRead = '0'");
        if ($stmt) {
            $stmt->bind_param('i', $current_account_id);
            if ($stmt->execute()) {
                $message = "All notifications marked as read.";
                $message_type = "success";
            }
        }
    }
    elseif ($action === 'clear_all') {
        $stmt = $db->prepare("DELETE FROM system_notification WHERE AccountID = ?");
        if ($stmt) {
            $stmt->bind_param('i', $current_account_id);
            if ($stmt->execute()) {
                $message = "All notifications cleared.";
                $message_type = "info";
            }
        }
    }
    elseif ($action === 'clear_one') {
        $notif_id = (int)($_POST['notif_id'] ?? 0);
        if ($notif_id > 0) {
            $stmt = $db->prepare("DELETE FROM system_notification WHERE $pk_col = ?");
            if ($stmt) {
                $stmt->bind_param('i', $notif_id);
                if ($stmt->execute()) {
                    $message = "Notification removed.";
                    $message_type = "info";
                }
            }
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
//  FETCH DATA FROM DB
// ══════════════════════════════════════════════════════════════════════════════

$notifs = [];
$res_notifs = $db->query("
    SELECT * FROM system_notification 
    WHERE AccountID = $current_account_id 
       OR AccountID IS NULL 
    ORDER BY CreatedAt DESC
");

if ($res_notifs) {
    while ($row = $res_notifs->fetch_assoc()) {
        // Gắn key ảo '_pk' để HTML form luôn nhận đúng ID dù cột tên gì
        $row['_pk'] = $row['NotifID'] ?? $row['NotificationID'] ?? 0;
        $notifs[] = $row;
    }
}

// Lọc an toàn cho cả '0'/0 và '1'/1
$unread = array_filter($notifs, fn($n) => $n['IsRead'] == 0);
$read   = array_filter($notifs, fn($n) => $n['IsRead'] == 1);

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
  <div class="page-actions" style="display:flex; gap:8px;">
    <form method="POST" action="" style="margin:0;">
        <input type="hidden" name="action" value="mark_all_read">
        <button type="submit" class="btn btn-ghost btn-sm">✓ Mark All Read</button>
    </form>
    <form method="POST" action="" style="margin:0;">
        <input type="hidden" name="action" value="clear_all">
        <button type="submit" class="btn btn-ghost btn-sm" onclick="return confirm('Are you sure you want to clear all notifications?');">🗑 Clear All</button>
    </form>
  </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $message_type ?>" style="margin-bottom:16px;">
  <?= $message_type === 'success' ? '✅' : 'ℹ️' ?> <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

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
      <div class="empty-state" style="padding:40px;"><div class="empty-icon">📭</div><div class="empty-title">No Notifications</div></div>
    <?php else: ?>
      <div class="activity-feed" style="max-height:none;">
        <?php foreach ($notifs as $n): ?>
        <div class="activity-item notif-item <?= $n['IsRead'] == 0 ? 'notif-unread' : '' ?>"
             id="notif-<?= $n['_pk'] ?>"
             style="padding:16px 20px;<?= $n['IsRead'] == 0 ? 'background:rgba(232,184,75,.04);border-left:3px solid var(--c-yellow);' : '' ?>">
          <div class="activity-avatar" style="<?= $n['IsRead'] == 0 ? 'background:linear-gradient(135deg,var(--c-yellow),var(--c-olive-light));color:var(--c-navy-900);' : '' ?>">
            <?= getIcon($n['Title']) ?>
          </div>
          <div class="activity-body" style="flex:1;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px;">
              <div class="activity-text" style="font-weight:<?= $n['IsRead'] == 0 ? '700' : '500' ?>;color:var(--text-primary);">
                <?= htmlspecialchars($n['Title']) ?>
              </div>
              <?php if ($n['IsRead'] == 0): ?>
                <span class="badge badge-yellow" style="font-size:10px;padding:2px 6px;">NEW</span>
              <?php endif; ?>
            </div>
            <div class="activity-text" style="font-size:13px;"><?= htmlspecialchars($n['Message']) ?></div>
            <div class="activity-time">🕐 <?= date('M d, Y H:i', strtotime($n['CreatedAt'])) ?></div>
          </div>
          <div style="display:flex;gap:6px;flex-shrink:0;align-items:flex-start;">
            <?php if ($n['IsRead'] == 0): ?>
              <form method="POST" action="" style="margin:0;">
                  <input type="hidden" name="action" value="mark_read">
                  <input type="hidden" name="notif_id" value="<?= $n['_pk'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm">✓ Read</button>
              </form>
            <?php endif; ?>
            <form method="POST" action="" style="margin:0;">
                <input type="hidden" name="action" value="clear_one">
                <input type="hidden" name="notif_id" value="<?= $n['_pk'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--c-red);">✕</button>
            </form>
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
            <?= getIcon($n['Title']) ?>
          </div>
          <div class="activity-body" style="flex:1;">
            <div style="font-weight:700;color:var(--text-primary);margin-bottom:3px;"><?= htmlspecialchars($n['Title']) ?></div>
            <div class="activity-text" style="font-size:13px;"><?= htmlspecialchars($n['Message']) ?></div>
            <div class="activity-time">🕐 <?= date('M d, Y H:i', strtotime($n['CreatedAt'])) ?></div>
          </div>
          <form method="POST" action="" style="margin:0;">
              <input type="hidden" name="action" value="mark_read">
              <input type="hidden" name="notif_id" value="<?= $n['_pk'] ?>">
              <button type="submit" class="btn btn-accent btn-sm">✓ Mark Read</button>
          </form>
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
        <div class="activity-item" style="padding:16px 20px;opacity:.85;">
          <div class="activity-avatar"><?= getIcon($n['Title']) ?></div>
          <div class="activity-body" style="flex:1;">
            <div style="font-weight:600;color:var(--text-primary);margin-bottom:3px;"><?= htmlspecialchars($n['Title']) ?></div>
            <div class="activity-text" style="font-size:13px;"><?= htmlspecialchars($n['Message']) ?></div>
            <div class="activity-time">🕐 <?= date('M d, Y H:i', strtotime($n['CreatedAt'])) ?></div>
          </div>
          <span class="badge badge-green" style="margin-right:10px;">Read</span>
          <form method="POST" action="" style="margin:0;">
              <input type="hidden" name="action" value="clear_one">
              <input type="hidden" name="notif_id" value="<?= $n['_pk'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--c-red);">✕</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php
close_page();
$db->close();
?>