<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

// Ensure user has an active session
if (!isset($_SESSION['user_id'])) {
    // Store requested URL to redirect back after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? (BASE_URL . '/dashboard.php');
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

// Re-check active status in database to prevent stale sessions for disabled users
$check_stmt = mysqli_prepare($conn, "SELECT id, full_name, email, role, status FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($check_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($check_stmt);
$user_res = mysqli_stmt_get_result($check_stmt);
$current_user = mysqli_fetch_assoc($user_res);
mysqli_stmt_close($check_stmt);

if (!$current_user || $current_user['status'] !== 'active') {
    // Clear session and redirect to login with disabled notice
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    session_start();
    $_SESSION['flash_error'] = "This account has been disabled or does not exist. Please contact an administrator.";
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

// Keep session values in sync with database (e.g. if admin changed role or name)
$_SESSION['role'] = $current_user['role'];
$_SESSION['full_name'] = $current_user['full_name'];
$_SESSION['email'] = $current_user['email'];
