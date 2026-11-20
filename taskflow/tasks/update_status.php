<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];

$task_id = (int)($_POST['task_id'] ?? 0);
$new_status = trim($_POST['status'] ?? '');
$redirect_to = $_POST['redirect_to'] ?? (BASE_URL . '/dashboard.php');

$allowed_statuses = ['todo', 'in_progress', 'in_review', 'done'];

if ($task_id <= 0 || !in_array($new_status, $allowed_statuses, true)) {
    $_SESSION['flash_error'] = "Invalid task status or ID provided.";
    header('Location: ' . $redirect_to);
    exit;
}

// Fetch task and project ownership
$stmt = mysqli_prepare($conn, "
    SELECT t.id, t.title, t.status, t.assigned_to, t.project_id, p.manager_id, p.name AS project_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    WHERE t.id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, "i", $task_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$task = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$task) {
    $_SESSION['flash_error'] = "Task not found.";
    header('Location: ' . $redirect_to);
    exit;
}

$current_status = $task['status'];
$is_assigned = ($task['assigned_to'] && (int)$task['assigned_to'] === $user_id);
$is_pm = ($user_role === 'project_manager' && (int)$task['manager_id'] === $user_id);
$is_admin = ($user_role === 'admin');

// Permission enforcement per 03_ROLES_AND_PERMISSIONS.md
if ($user_role === 'team_member') {
    if (!$is_assigned) {
        $_SESSION['flash_error'] = "You can only update status on tasks assigned to you.";
        header('Location: ' . $redirect_to);
        exit;
    }
    // Team member cannot mark task as Done (approval required from PM/Admin)
    if ($new_status === 'done') {
        $_SESSION['flash_error'] = "Only Project Managers and Admins can approve tasks to Done. Please submit for In Review.";
        header('Location: ' . $redirect_to);
        exit;
    }
} elseif ($user_role === 'project_manager') {
    if (!$is_pm && !$is_admin) {
        $_SESSION['flash_error'] = "You can only update tasks within projects you manage.";
        header('Location: ' . $redirect_to);
        exit;
    }
}

// Perform update
$update_stmt = mysqli_prepare($conn, "UPDATE tasks SET status = ? WHERE id = ?");
mysqli_stmt_bind_param($update_stmt, "si", $new_status, $task_id);

if (mysqli_stmt_execute($update_stmt)) {
    mysqli_stmt_close($update_stmt);

    // Record activity log
    $action_text = "Updated task \"{$task['title']}\" from " . str_replace('_', ' ', $current_status) . " to " . str_replace('_', ' ', $new_status);
    $log_stmt = mysqli_prepare($conn, "INSERT INTO activity_log (user_id, action) VALUES (?, ?)");
    mysqli_stmt_bind_param($log_stmt, "is", $user_id, $action_text);
    mysqli_stmt_execute($log_stmt);
    mysqli_stmt_close($log_stmt);

    $_SESSION['flash_success'] = "Task \"{$task['title']}\" moved to " . ucwords(str_replace('_', ' ', $new_status)) . ".";
} else {
    $_SESSION['flash_error'] = "Failed to update task status: " . mysqli_error($conn);
    mysqli_stmt_close($update_stmt);
}

header('Location: ' . $redirect_to);
exit;
