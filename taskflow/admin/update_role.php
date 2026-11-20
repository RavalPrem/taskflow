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
$new_role         = trim($_POST['role'] ?? '');
$valid_roles      = ['admin', 'project_manager', 'team_member'];
$redirect_url     = BASE_URL . '/admin/manage_users.php';

if ($user_id <= 0 || !in_array($new_role, $valid_roles, true)) {
    $_SESSION['flash_error'] = 'Invalid user ID or specified role.';
    header('Location: ' . $redirect_url);
    exit;
}

// Disallow altering self
if ($user_id === $current_admin_id) {
    $_SESSION['flash_error'] = 'You cannot modify your own administrator role.';
    header('Location: ' . $redirect_url);
    exit;
}

// Fetch current target user
$stmt = mysqli_prepare($conn, "SELECT id, full_name, email, role FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$target_user = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$target_user) {
    $_SESSION['flash_error'] = 'Target user not found.';
    header('Location: ' . $redirect_url);
    exit;
}

$old_role = $target_user['role'];

if ($old_role === $new_role) {
    $_SESSION['flash_success'] = 'Role unchanged (already set to ' . ucwords(str_replace('_', ' ', $new_role)) . ').';
    header('Location: ' . $redirect_url);
    exit;
}

// Update role
$update_stmt = mysqli_prepare($conn, "UPDATE users SET role = ? WHERE id = ?");
mysqli_stmt_bind_param($update_stmt, "si", $new_role, $user_id);

if (mysqli_stmt_execute($update_stmt)) {
    mysqli_stmt_close($update_stmt);

    // Record activity log
    $action_text = "Updated role of \"{$target_user['full_name']}\" from " . ucwords(str_replace('_', ' ', $old_role)) . " to " . ucwords(str_replace('_', ' ', $new_role));
    $log_stmt = mysqli_prepare($conn, "INSERT INTO activity_log (user_id, action) VALUES (?, ?)");
    mysqli_stmt_bind_param($log_stmt, "is", $current_admin_id, $action_text);
    mysqli_stmt_execute($log_stmt);
    mysqli_stmt_close($log_stmt);

    $_SESSION['flash_success'] = "Role for \"{$target_user['full_name']}\" successfully updated to " . ucwords(str_replace('_', ' ', $new_role)) . ".";
} else {
    mysqli_stmt_close($update_stmt);
    $_SESSION['flash_error'] = 'Database error: failed to update role.';
}

header('Location: ' . $redirect_url);
exit;
