<?php
/**
 * header.php — Sidebar + Topbar shell
 * Usage: include at the top of every protected page after auth_require()
 *
 * Required variables before include:
 *   $pageTitle    string  — display title
 *   $activePage   string  — nav item key e.g. 'users', 'shipments'
 *   $breadcrumbs  array   — [['label'=>'...','url'=>'...'], ...]
 */

require_once __DIR__ . '/../config/db.php';

$user = current_user();
$role = current_role();

// ─── Count dynamic badges from DB ───────────────────────────────────────────────
function get_badge_count(string $query): int {
    try {
        $db = get_db();
        $result = $db->query($query);
        if ($result && $row = $result->fetch_row()) {
            return (int)$row[0];
        }
    } catch (Exception $e) { /* silent */ }
    return 0;
}

$badge_exceptions_ops = get_badge_count(
    "SELECT COUNT(*) FROM operational_exception WHERE ApprovalStatus IN ('Pending','Under Investigation')"
);
$badge_exceptions_mgr = $badge_exceptions_ops;
$badge_aging          = get_badge_count(
    "SELECT COUNT(*) FROM invoice WHERE InvoiceType = 'AR_Receivable' 
     AND IssueDate < DATE_SUB(NOW(), INTERVAL 30 DAY)"
);
$badge_notifications  = 0;
$current_uid = $_SESSION['user']['account_id'] ?? 0;
if ($current_uid) {
    $badge_notifications = get_badge_count(
        "SELECT COUNT(*) FROM system_notification WHERE AccountID = $current_uid AND IsRead = 0"
    );
}

// ─── Nav definitions per role ───────────────────────────────────────────────
$b = defined('BASE_URL') ? BASE_URL : '';
$navMenus = [

    // ── ADMIN ──────────────────────────────────────────────────────────────
    'admin' => [
        'main' => [
            ['key'=>'dashboard', 'icon'=>'🏠', 'label'=>'Dashboard',    'url'=>"$b/admin/dashboard.php"],
            ['key'=>'users',     'icon'=>'👥', 'label'=>'User Accounts','url'=>"$b/admin/users.php"],
            ['key'=>'roles',     'icon'=>'🔐', 'label'=>'Manage Roles', 'url'=>"$b/admin/roles.php"],
        ],
        'system' => [
            ['key'=>'audit',  'icon'=>'📋', 'label'=>'Audit Logs',  'url'=>"$b/admin/audit_logs.php"],
            ['key'=>'import', 'icon'=>'📥', 'label'=>'Import Users','url'=>"$b/admin/import_users.php"],
        ],
    ],

    // ── MANAGER ────────────────────────────────────────────────────────────
    'manager' => [
        'main' => [
            ['key'=>'dashboard', 'icon'=>'📊', 'label'=>'KPI Dashboard', 'url'=>"$b/manager/dashboard.php"],
            ['key'=>'cost',      'icon'=>'💰', 'label'=>'Cost Analysis', 'url'=>"$b/manager/cost_analysis.php"],
            ['key'=>'reports',   'icon'=>'📈', 'label'=>'Reports',       'url'=>"$b/manager/reports.php"],
        ],
        'operations' => [
            // requests.php has been merged into exceptions.php
            ['key'=>'exceptions', 'icon'=>'⚠️', 'label'=>'Exceptions & Requests',
             'url'=>"$b/manager/exceptions.php",
             'badge'=> $badge_exceptions_mgr ?: null],
        ],
    ],

    // ── ACCOUNTANT ─────────────────────────────────────────────────────────
    'accountant' => [
        'main' => [
            ['key'=>'dashboard', 'icon'=>'💹', 'label'=>'Financial Dashboard', 'url'=>"$b/accountant/dashboard.php"],
            ['key'=>'invoices',  'icon'=>'🧾', 'label'=>'Invoices',            'url'=>"$b/accountant/invoices.php"],
            ['key'=>'payments',  'icon'=>'💳', 'label'=>'Payments',            'url'=>"$b/accountant/payments.php"],
        ],
        'finance' => [
            ['key'=>'billing',       'icon'=>'⚙️', 'label'=>'Billing Structure', 'url'=>"$b/accountant/billing.php"],
            ['key'=>'reports',       'icon'=>'📊', 'label'=>'Financial Reports', 'url'=>"$b/accountant/reports.php"],
            ['key'=>'aging',         'icon'=>'🔔', 'label'=>'Aging Debt',        'url'=>"$b/accountant/aging_debt.php",
             'badge'=> $badge_aging ?: null],
            ['key'=>'carrier_costs', 'icon'=>'🚛', 'label'=>'Carrier Costs',     'url'=> BASE_URL . '/accountant/carrier_costs.php'],
        ],
    ],

    // ── OPERATIONS ─────────────────────────────────────────────────────────
    'operations' => [
        'main' => [
            ['key'=>'dashboard', 'icon'=>'🏠', 'label'=>'Dashboard',        'url'=>"$b/operations/dashboard.php"],
            ['key'=>'shipments', 'icon'=>'📦', 'label'=>'Shipment Orders',  'url'=>"$b/operations/shipments.php"],
            ['key'=>'assets',    'icon'=>'🚛', 'label'=>'Transport Assets', 'url'=>"$b/operations/assets.php"],
        ],
        'planning' => [
            ['key'=>'routes',    'icon'=>'🗺️', 'label'=>'Routes',       'url'=>"$b/operations/routes.php"],
            ['key'=>'schedules', 'icon'=>'📅', 'label'=>'Schedules',     'url'=>"$b/operations/schedules.php"],
            ['key'=>'assign',    'icon'=>'🔗', 'label'=>'Assign Assets', 'url'=>"$b/operations/assign.php"],
        ],
        'tracking' => [
            ['key'=>'inventory',     'icon'=>'🏭', 'label'=>'Inventory',         'url'=>"$b/operations/inventory.php"],
            ['key'=>'tracking',      'icon'=>'📍', 'label'=>'Tracking Logs',     'url'=>"$b/operations/tracking.php"],
            ['key'=>'exceptions',    'icon'=>'⚠️', 'label'=>'Exceptions',        'url'=>"$b/operations/exceptions.php",
             'badge'=> $badge_exceptions_ops ?: null],
            ['key'=>'pod',           'icon'=>'✅', 'label'=>'Proof of Delivery', 'url'=>"$b/operations/pod.php"],
            ['key'=>'notifications', 'icon'=>'🔔', 'label'=>'Notifications',     'url'=>"$b/operations/notifications.php",
             'badge'=> $badge_notifications ?: null],
        ],
    ],
];

$menu = $navMenus[$role] ?? [];

$roleLabels = [
    'admin'      => 'Administrator',
    'manager'    => 'Manager',
    'accountant' => 'Accountant',
    'operations' => 'Operation Staff',
];
$roleLabelDisplay = $roleLabels[$role] ?? ucfirst($role);
$breadcrumbs = $breadcrumbs ?? [];
$pageTitle   = $pageTitle   ?? 'Dashboard';
$activePage  = $activePage  ?? '';
?>
<div class="sidebar" id="mainSidebar">

  <!-- ── Logo ─────────────────────────────────────────────────────────── -->
  <a href="javascript:void(0)" class="sidebar-logo">
    <div class="sidebar-logo-icon">🚚</div>
    <div class="sidebar-logo-text">
      <span class="brand">ITL Logistics Group</span>
      <span class="tagline">Supply Chain Solutions</span>
    </div>
  </a>

  <!-- ── Navigation ────────────────────────────────────────────────────── -->
  <nav class="sidebar-nav">
    <?php foreach ($menu as $sectionKey => $items): ?>
      <div class="nav-section-label"><?= ucfirst($sectionKey) ?></div>
      <?php foreach ($items as $item): ?>
        <a href="<?= $item['url'] ?>"
           class="nav-item <?= ($activePage === $item['key']) ? 'active' : '' ?>"
           data-tooltip="<?= htmlspecialchars($item['label']) ?>">
          <span class="nav-icon"><?= $item['icon'] ?></span>
          <span class="nav-label"><?= htmlspecialchars($item['label']) ?></span>
          <?php if (!empty($item['badge'])): ?>
            <span class="nav-badge"><?= $item['badge'] ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <!-- ── Footer ────────────────────────────────────────────────────────── -->
  <div class="sidebar-footer">
    <a href="<?= $b ?>/logout.php"
       class="nav-item"
       data-tooltip="Logout"
       onclick="return confirm('Log out?')">
      <span class="nav-icon">🚪</span>
      <span class="nav-label">Logout</span>
    </a>
  </div>
</div><!-- /sidebar -->

<!-- ── Main Content Wrapper ──────────────────────────────────────────────── -->
<div class="main-content" id="mainContent">

  <!-- Top Bar -->
  <header class="topbar">
    <button class="topbar-toggle" id="sidebarToggle" title="Toggle sidebar">☰</button>

    <!-- Breadcrumb -->
    <div class="topbar-breadcrumb">
      <span class="crumb">ITL Logistics Group</span>
      <span class="sep">›</span>
      <span class="crumb"><?= htmlspecialchars($roleLabelDisplay) ?></span>
      <?php foreach ($breadcrumbs as $crumb): ?>
        <span class="sep">›</span>
        <?php if (isset($crumb['url'])): ?>
          <a href="<?= $crumb['url'] ?>" class="crumb"><?= htmlspecialchars($crumb['label']) ?></a>
        <?php else: ?>
          <span class="crumb current"><?= htmlspecialchars($crumb['label']) ?></span>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if (empty($breadcrumbs)): ?>
        <span class="sep">›</span>
        <span class="crumb current"><?= htmlspecialchars($pageTitle) ?></span>
      <?php endif; ?>
    </div>

    <!-- Actions -->
    <div class="topbar-actions">

      <!-- Notification bell -->
      <button class="topbar-icon-btn" title="Notifications"
              onclick="window.location.href='<?= $b ?>/<?= $role ?>/<?= $role === 'operations' ? 'notifications' : 'dashboard' ?>.php'">
        🔔
        <?php if ($badge_notifications > 0): ?>
          <span class="notif-dot"></span>
        <?php endif; ?>
      </button>

      <!-- Avatar Dropdown -->
      <div class="dropdown-wrapper">
        <button class="avatar-btn" id="avatarBtn">
          <div class="avatar">
            <?php
              $name  = $user['name'] ?? 'U';
              $parts = explode(' ', trim($name));
              $initials = strtoupper(
                  count($parts) >= 2
                      ? mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1)
                      : mb_substr($name, 0, 2)
              );
              echo htmlspecialchars($initials);
            ?>
          </div>
          <div class="avatar-info">
            <div class="avatar-name"><?= htmlspecialchars($user['name'] ?? 'User') ?></div>
            <div class="avatar-role"><?= htmlspecialchars($roleLabelDisplay) ?></div>
          </div>
          <span style="font-size:10px;color:var(--text-muted);margin-left:4px;">▼</span>
        </button>
        <div class="dropdown-menu" id="avatarMenu">
          <div class="dropdown-header">
            <div class="dn"><?= htmlspecialchars($user['name'] ?? 'User') ?></div>
            <div class="de"><?= htmlspecialchars($user['email'] ?? '') ?></div>
          </div>
          <a href="#" class="dropdown-item">👤 My Profile</a>
          <a href="#" class="dropdown-item">⚙️ Settings</a>
          <div class="dropdown-divider"></div>
          <a href="<?= $b ?>/logout.php" class="dropdown-item danger">🚪 Sign Out</a>
        </div>
      </div>
    </div><!-- /topbar-actions -->
  </header>

  <!-- Page Content Start -->
  <main class="page-content">