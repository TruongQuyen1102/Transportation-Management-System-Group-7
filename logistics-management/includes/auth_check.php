<?php
// auth_check.php — include at the top of every protected page
// Usage: require_once __DIR__ . '/../includes/auth_check.php';
//        auth_require(ROLE_ADMIN);          // only admin
//        auth_require();                    // any logged-in role

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/constants.php';

function auth_require(string $role = '') {
    if (empty($_SESSION['user'])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
    if ($role && $_SESSION['user']['role'] !== $role) {
        // wrong role — redirect to their own home
        global $ROLE_HOME;
        $home = $ROLE_HOME[$_SESSION['user']['role']] ?? BASE_URL . '/index.php';
        header("Location: $home");
        exit;
    }
}

function current_user(): array {
    return $_SESSION['user'] ?? [];
}

function current_role(): string {
    return $_SESSION['user']['role'] ?? '';
}
