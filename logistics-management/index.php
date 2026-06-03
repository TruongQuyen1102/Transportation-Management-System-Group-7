<?php
session_start();
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/sample_data.php';

// Redirect if already logged in
if (!empty($_SESSION['user'])) {
    global $ROLE_HOME;
    header('Location: ' . ($ROLE_HOME[$_SESSION['user']['role']] ?? BASE_URL . '/index.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = trim($_POST['role'] ?? '');

    foreach (get_demo_accounts() as $account) {
        if ($account['username'] === $username
            && $account['password'] === $password
            && $account['role'] === $role
            && $account['status'] === 'active') {

            $_SESSION['user'] = [
                'id'     => $account['id'],
                'name'   => $account['name'],
                'email'  => $account['email'],
                'role'   => $account['role'],
                'avatar' => $account['avatar'],
            ];
            header('Location: ' . $ROLE_HOME[$account['role']]);
            exit;
        }
    }
    $error = 'Invalid credentials or account is inactive. Please check your username, password, and role selection.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — ITL Logistics Group</title>
  <meta name="description" content="ITL Logistics Group Supply Chain Solutions — Secure sign-in portal.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/login.css">
</head>
<body>

<div class="login-page">

  <!-- ── Left Hero ──────────────────────────────────────────────────────── -->
  <section class="login-hero">
    <div class="hero-bg-pattern"></div>
    <div class="hero-gradient"></div>
    <!-- Truck silhouette via CSS/SVG -->
    <svg class="hero-truck-img" viewBox="0 0 900 400" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect x="0" y="220" width="560" height="140" rx="12" fill="white"/>
      <rect x="560" y="270" width="180" height="90" rx="8" fill="white"/>
      <rect x="570" y="248" width="160" height="55" rx="6" fill="white" opacity=".5"/>
      <circle cx="120" cy="372" r="38" fill="white"/>
      <circle cx="120" cy="372" r="20" fill="#1a4466"/>
      <circle cx="600" cy="372" r="38" fill="white"/>
      <circle cx="600" cy="372" r="20" fill="#1a4466"/>
      <circle cx="720" cy="372" r="38" fill="white"/>
      <circle cx="720" cy="372" r="20" fill="#1a4466"/>
      <rect x="0" y="180" width="560" height="48" rx="6" fill="white" opacity=".08"/>
    </svg>

    <div class="hero-content">
      <div class="hero-logo">
        <div class="hero-logo-icon">🚚</div>
        <div class="hero-logo-text">
          <div class="brand">ITL Logistics Group</div>
          <div class="tagline">Supply Chain Solutions</div>
        </div>
      </div>

      <div class="hero-headline">
        <h1>Smart Logistics,<br><span>Delivered.</span></h1>
        <p>End-to-end visibility into your supply chain — from warehouse to final mile delivery. Built for modern transport operations.</p>
      </div>

      <div class="hero-stats">
        <div class="hero-stat">
          <div class="val">12+</div>
          <div class="lbl">Active Orders</div>
        </div>
        <div class="hero-stat">
          <div class="val">95%</div>
          <div class="lbl">On-Time Rate</div>
        </div>
        <div class="hero-stat">
          <div class="val">7</div>
          <div class="lbl">Shipments Live</div>
        </div>
      </div>
    </div>
  </section>

  <!-- ── Right Form ─────────────────────────────────────────────────────── -->
  <section class="login-form-panel">
    <div class="login-box">
      <div class="login-box-header">
        <h2>Welcome Back</h2>
        <p>Select your role, then enter your credentials to continue.</p>
      </div>

      <!-- Error Banner -->
      <div class="login-error <?= $error ? 'show' : '' ?>" id="loginError">
        ⚠️ <?= htmlspecialchars($error) ?>
      </div>

      <form method="POST" action="<?= BASE_URL ?>/index.php" class="login-form" id="loginForm">
        <input type="hidden" name="role" id="selectedRole" value="admin">

        <!-- Role Selector -->
        <div class="role-selector">
          <button type="button" class="role-btn selected" data-role="admin" id="roleAdmin">
            <span class="role-icon">🛡️</span>
            <span class="role-label">Admin</span>
          </button>
          <button type="button" class="role-btn" data-role="manager" id="roleManager">
            <span class="role-icon">📊</span>
            <span class="role-label">Manager</span>
          </button>
          <button type="button" class="role-btn" data-role="accountant" id="roleAccountant">
            <span class="role-icon">💰</span>
            <span class="role-label">Accountant</span>
          </button>
          <button type="button" class="role-btn" data-role="operations" id="roleOps">
            <span class="role-icon">🚛</span>
            <span class="role-label">Operation Staff</span>
          </button>
        </div>

        <!-- Username -->
        <div class="form-group">
          <label class="form-label" for="username">Username</label>
          <div class="input-icon-wrap">
            <span class="input-icon">👤</span>
            <input
              type="text"
              id="username"
              name="username"
              class="form-control"
              placeholder="Enter your username"
              value="admin"
              required
              autocomplete="username">
          </div>
        </div>

        <!-- Password -->
        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="input-icon-wrap">
            <span class="input-icon">🔒</span>
            <input
              type="password"
              id="password"
              name="password"
              class="form-control"
              placeholder="Enter your password"
              value="admin123"
              required
              autocomplete="current-password">
            <button type="button" class="toggle-pw" title="Show/hide password">👁</button>
          </div>
        </div>

        <button type="submit" class="login-submit" id="loginBtn">
          Sign In to ITL Logistics Group
        </button>
      </form>

      <!-- Demo Hint -->
      <div class="login-hint">
        <strong>Demo Credentials:</strong><br>
        Admin: <kbd>admin</kbd> / <kbd>admin123</kbd> &nbsp;|&nbsp;
        Ops: <kbd>ops</kbd> / <kbd>ops123</kbd><br>
        Manager: <kbd>manager</kbd> / <kbd>manager123</kbd> &nbsp;|&nbsp;
        Accountant: <kbd>accountant</kbd> / <kbd>accountant123</kbd>
      </div>
    </div>
  </section>

</div>

<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<script>
  // Pre-select admin role on load
  document.querySelector('.role-btn[data-role="admin"]')?.click();
</script>
</body>
</html>
