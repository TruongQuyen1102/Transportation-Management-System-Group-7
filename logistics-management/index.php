<?php
session_start();
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/db.php';

// Redirect if user already logged in
if (!empty($_SESSION['user'])) {
    global $ROLE_HOME;
    header('Location: ' . ($ROLE_HOME[$_SESSION['user']['role']] ?? BASE_URL . '/index.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role     = trim($_POST['role'] ?? ''); // Receive from form: 'admin', 'manager', 'accountant', 'operations'

    // Initialize MySQLi connection as in users.php
    $db = get_db(); 

    // Map from UI slug to standard RoleName in Database
    $role_mapping = [
        'admin'      => 'Admin',
        'manager'    => 'Manager',
        'accountant' => 'Accountant',
        'operations' => 'Operation Staff'
    ];

    $db_role_name = $role_mapping[$role] ?? '';

    // Query account information with employee data and role name
    $stmt = $db->prepare("
        SELECT 
            a.AccountID, 
            a.PasswordHash, 
            a.Status, 
            e.FullName, 
            e.ContactEmail
        FROM account a
        JOIN employee e ON a.EmployeeID = e.EmployeeID
        JOIN role r     ON a.RoleID     = r.RoleID
        WHERE a.Username = ? AND r.RoleName = ?
    ");

    if ($stmt) {
        $stmt->bind_param('ss', $username, $db_role_name);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($account = $result->fetch_assoc()) {
            // 1. Kiểm tra trạng thái kích hoạt của tài khoản
            if (strtolower($account['Status']) !== 'active') {
                $error = 'Tài khoản của bạn đã bị Khóa hoặc Vô hiệu hóa liên hệ Admin.';
            } 
            // 2. Xác thực mật khẩu thông qua hàm Verify Hash Bcrypt chuẩn
            elseif (password_verify($password, $account['PasswordHash'])) {
                
                // Khởi tạo thông tin Session chuẩn để đồng bộ toàn bộ hệ thống Layout Shell
                $_SESSION['user'] = [
                    'id'     => $account['AccountID'],
                    'name'   => $account['FullName'],
                    'email'  => $account['ContactEmail'],
                    'role'   => $role, // Giữ nguyên slug thường ('admin', 'operations') để biến hệ thống $ROLE_HOME chuyển hướng chuẩn
                    'avatar' => mb_strtoupper(mb_substr($account['FullName'], 0, 1)) // Lấy chữ cái đầu làm Avatar mặc định
                ];

                global $ROLE_HOME;
                header('Location: ' . ($ROLE_HOME[$role] ?? BASE_URL . '/index.php'));
                exit;
            } else {
                $error = 'Mật khẩu nhập vào không chính xác. Vui lòng thử lại.';
            }
        } else {
            $error = 'Không tìm thấy tài khoản tương ứng với vai trò đã chọn.';
        }
        $stmt->close();
    } else {
        $error = 'Hệ thống đang gặp sự cố kết nối dữ liệu.';
    }
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

  <section class="login-hero">
    <div class="hero-bg-pattern"></div>
    <div class="hero-gradient"></div>
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

  <section class="login-form-panel">
    <div class="login-box">
      <div class="login-box-header">
        <h2>Welcome Back</h2>
        <p>Select your role, then enter your credentials to continue.</p>
      </div>

      <div class="login-error <?= $error ? 'show' : '' ?>" id="loginError">
        ⚠️ <?= htmlspecialchars($error) ?>
      </div>

      <form method="POST" action="<?= BASE_URL ?>/index.php" class="login-form" id="loginForm">
        <input type="hidden" name="role" id="selectedRole" value="admin">

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
              value=""
              required
              autocomplete="off">
          </div>
        </div>

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
              value=""
              required
              autocomplete="new-password">
            <button type="button" class="toggle-pw" title="Show/hide password">👁</button>
          </div>
        </div>

        <button type="submit" class="login-submit" id="loginBtn">
          Sign In to ITL Logistics Group
        </button>
      </form>

      <div class="login-hint">
        <div class="login-hint">
        <strong>Demo Account:</strong><br>
        admin - Password: <kbd>admin123456</kbd> | 
        manager - password: <kbd>mng123456</kbd><br>
        accountant - password: <kbd>act123456</kbd> | ops - password: <kbd>ops123456</kbd>
      </div>
    </div>
  </section>

</div>

<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<script>
  // Các thẻ DOM cần tương tác
  const roleButtons = document.querySelectorAll('.role-btn');
  const hiddenRoleInput = document.getElementById('selectedRole');
  const usernameInput = document.getElementById('username');
  const passwordInput = document.getElementById('password');

  // Mảng dữ liệu Demo Account
  const demoAccounts = {
    'admin': { user: 'admin', pass: 'admin123456' },
    'manager': { user: 'manager', pass: 'mng123456' },
    'accountant': { user: 'accountant', pass: 'act123456' },
    'operations': { user: 'ops', pass: 'ops123456' }
  };

  // 1. Xử lý sự kiện khi click vào các nút Role
  roleButtons.forEach(button => {
    button.addEventListener('click', function() {
      // Xóa class 'selected' ở tất cả các nút
      roleButtons.forEach(btn => btn.classList.remove('selected'));
      
      // Thêm class 'selected' cho nút vừa click
      this.classList.add('selected');
      
      // Lấy role được chọn (ví dụ: 'manager')
      const selectedRole = this.getAttribute('data-role');
      
      // Cập nhật giá trị data-role vào thẻ input ẩn để submit form
      hiddenRoleInput.value = selectedRole;
      
      // Tự động điền Username và Password theo role đã chọn
      if (demoAccounts[selectedRole]) {
          setTimeout(() => {
              usernameInput.value = demoAccounts[selectedRole].user;
              passwordInput.value = demoAccounts[selectedRole].pass;
          }, 30); // Delay nhẹ để đè lại autofill của trình duyệt
      }
    });
  });

</script>
</body>
</html>