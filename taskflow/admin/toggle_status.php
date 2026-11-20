<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/require_role.php';

require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/manage_users.php');
    exit;
}

$current_admin_id = (int)$_SESSION['user_id'];
$user_id          = (int)($_POST['user_id'] ?? 0);
$redirect_url     = BASE_URL . '/admin/manage_users.php';

if ($user_id <= 0) {
    $_SESSION['flash_error'] = 'Invalid user ID.';
    header('Location: ' . $redirect_url);
    exit;
}

// Disallow altering self
if ($user_id === $current_admin_id) {
    $_SESSION['flash_error'] = 'You cannot disable your own administrator account.';
    header('Location: ' . $redirect_url);
    exit;
}

// Fetch current target user
$stmt = mysqli_prepare($conn, "SELECT id, full_name, email, status FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$target_user = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$target_user) {
    $_SESSION['flash_error'] = 'User not found.';
    header('Location: ' . $redirect_url);
    exit;
}

$new_status = ($target_user['status'] === 'active') ? 'disabled' : 'active';

// Update status
$update_stmt = mysqli_prepare($conn, "UPDATE users SET status = ? WHERE id = ?");
mysqli_stmt_bind_param($update_stmt, "si", $new_status, $user_id);

if (mysqli_stmt_execute($update_stmt)) {
    mysqli_stmt_close($update_stmt);

    // Record activity log
    $action_verb = ($new_status === 'disabled') ? 'Disabled' : 'Enabled';
    $action_text = "{$action_verb} user account for \"{$target_user['full_name']}\" ({$target_user['email']})";
    
    $log_stmt = mysqli_prepare($conn, "INSERT INTO activity_log (user_id, action) VALUES (?, ?)");
    mysqli_stmt_bind_param($log_stmt, "is", $current_admin_id, $action_text);
    mysqli_stmt_execute($log_stmt);
    mysqli_stmt_close($log_stmt);

    $_SESSION['flash_success'] = "Account for \"{$target_user['full_name']}\" is now " . ucfirst($new_status) . ".";
} else {
    mysqli_stmt_close($update_stmt);
    $_SESSION['flash_error'] = 'Database error: failed to update status.';
}

header('Location: ' . $redirect_url);
exit;
