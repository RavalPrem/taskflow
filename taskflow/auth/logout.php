<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

// Log logout action if user is authenticated
if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $stmt = mysqli_prepare($conn, "INSERT INTO activity_log (user_id, action) VALUES (?, 'User logged out')");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $uid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// Clear all session variables
$_SESSION = [];

// Invalidate session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to public landing page
header('Location: ' . BASE_URL . '/index.php');
exit;
