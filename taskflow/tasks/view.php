<?php
$page_title = 'Task Details';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];

$task_id = (int)($_GET['id'] ?? 0);
if ($task_id <= 0) {
    $_SESSION['flash_error'] = "Invalid task ID specified.";
    header('Location: ' . BASE_URL . '/tasks/board.php');
    exit;
}

// Fetch task, project, and assignee details
$stmt = mysqli_prepare($conn, "
    SELECT t.*, p.name AS project_name, p.manager_id,
           u_assign.full_name AS assigned_name, u_assign.email AS assigned_email,
           u_create.full_name AS creator_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    LEFT JOIN users u_assign ON t.assigned_to = u_assign.id
    LEFT JOIN users u_create ON t.created_by = u_create.id
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
    header('Location: ' . BASE_URL . '/tasks/board.php');
    exit;
}

$is_assigned = ($task['assigned_to'] && (int)$task['assigned_to'] === $user_id);
$is_pm = ($user_role === 'project_manager' && (int)$task['manager_id'] === $user_id);
$can_manage = ($user_role === 'admin' || $is_pm);

// Permission check: Team member can view if assigned or if member of the project
if ($user_role === 'team_member') {
    $check_mem = mysqli_prepare($conn, "SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1");
    mysqli_stmt_bind_param($check_mem, "ii", $task['project_id'], $user_id);
    mysqli_stmt_execute($check_mem);
    mysqli_stmt_store_result($check_mem);
    $in_project = (mysqli_stmt_num_rows($check_mem) > 0);
    mysqli_stmt_close($check_mem);

    if (!$is_assigned && !$in_project) {
        $_SESSION['flash_error'] = "You do not have access to view this task.";
        header('Location: ' . BASE_URL . '/tasks/board.php');
        exit;
    }
}

$error = '';

// Handle Task Deletion (Admin or PM owner only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_task') {
    if (!$can_manage) {
        $_SESSION['flash_error'] = "You don't have permission to delete tasks.";
        header('Location: ' . BASE_URL . '/tasks/view.php?id=' . $task_id);
        exit;
    }

    $del_stmt = mysqli_prepare($conn, "DELETE FROM tasks WHERE id = ?");
    mysqli_stmt_bind_param($del_stmt, "i", $task_id);
    if (mysqli_stmt_execute($del_stmt)) {
        mysqli_stmt_close($del_stmt);

        // Record audit log
        $log_stmt = mysqli_prepare($conn, "INSERT INTO activity_log (user_id, action) VALUES (?, ?)");
        $act_text = "Deleted task \"{$task['title']}\" from project \"{$task['project_name']}\"";
        mysqli_stmt_bind_param($log_stmt, "is", $user_id, $act_text);
        mysqli_stmt_execute($log_stmt);
        mysqli_stmt_close($log_stmt);

        $_SESSION['flash_success'] = "Task \"{$task['title']}\" deleted successfully.";
        header('Location: ' . BASE_URL . '/projects/view.php?id=' . $task['project_id']);
        exit;
    } else {
        $error = "Failed to delete task: " . mysqli_error($conn);
        mysqli_stmt_close($del_stmt);
    }
}

// Handle Task Details Update (Admin & PM only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_task_details') {
    if (!$can_manage) {
        $_SESSION['flash_error'] = "Only Project Managers and Admins can modify task details.";
        header('Location: ' . BASE_URL . '/tasks/view.php?id=' . $task_id);
        exit;
    }

    $new_title = trim($_POST['title'] ?? '');
    $new_desc = trim($_POST['description'] ?? '');
    $new_priority = $_POST['priority'] ?? 'medium';
    $new_assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
    $new_due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

    if (empty($new_title)) {
        $error = "Task title cannot be empty.";
    } elseif (!in_array($new_priority, ['low', 'medium', 'high'], true)) {
        $error = "Invalid priority selected.";
    } else {
        $up_stmt = mysqli_prepare($conn, "
            UPDATE tasks 
            SET title = ?, description = ?, priority = ?, assigned_to = ?, due_date = ?
            WHERE id = ?
        ");
        mysqli_stmt_bind_param($up_stmt, "sssisi", $new_title, $new_desc, $new_priority, $new_assigned_to, $new_due_date, $task_id);

        if (mysqli_stmt_execute($up_stmt)) {
            mysqli_stmt_close($up_stmt);

            $log_stmt = mysqli_prepare($conn, "INSERT INTO activity_log (user_id, action) VALUES (?, ?)");
            $act_text = "Updated task details for \"{$new_title}\"";
            mysqli_stmt_bind_param($log_stmt, "is", $user_id, $act_text);
            mysqli_stmt_execute($log_stmt);
            mysqli_stmt_close($log_stmt);

            $_SESSION['flash_success'] = "Task details saved successfully.";
            header('Location: ' . BASE_URL . '/tasks/view.php?id=' . $task_id);
            exit;
        } else {
            $error = "Failed to update task: " . mysqli_error($conn);
            mysqli_stmt_close($up_stmt);
        }
    }
}

// Handle Posting a Comment (All roles who have access)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
    $comment_text = trim($_POST['comment'] ?? '');
    if (empty($comment_text)) {
        $error = "Comment cannot be empty.";
    } else {
        $com_stmt = mysqli_prepare($conn, "INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($com_stmt, "iis", $task_id, $user_id, $comment_text);
        if (mysqli_stmt_execute($com_stmt)) {
            mysqli_stmt_close($com_stmt);

            $_SESSION['flash_success'] = "Comment posted.";
            header('Location: ' . BASE_URL . '/tasks/view.php?id=' . $task_id . '#comments');
            exit;
        } else {
            $error = "Failed to post comment: " . mysqli_error($conn);
            mysqli_stmt_close($com_stmt);
        }
    }
}

// Fetch all comments for this task
$comments = [];
$c_stmt = mysqli_prepare($conn, "
    SELECT tc.*, u.full_name, u.role
    FROM task_comments tc
    JOIN users u ON tc.user_id = u.id
    WHERE tc.task_id = ?
    ORDER BY tc.created_at ASC
");
mysqli_stmt_bind_param($c_stmt, "i", $task_id);
mysqli_stmt_execute($c_stmt);
$c_res = mysqli_stmt_get_result($c_stmt);
while ($row = mysqli_fetch_assoc($c_res)) {
    $comments[] = $row;
}
mysqli_stmt_close($c_stmt);

// Fetch team members in this project (for assignee dropdown if editing)
$project_members = [];
$pm_res = mysqli_query($conn, "
    SELECT u.id, u.full_name 
    FROM project_members pm
    JOIN users u ON pm.user_id = u.id
    WHERE pm.project_id = {$task['project_id']}
    ORDER BY u.full_name ASC
");
while ($pm_row = mysqli_fetch_assoc($pm_res)) {
    $project_members[] = $pm_row;
}

$page_title = $task['title'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding-top: 32px; max-width: 960px;">
    <!-- Breadcrumb & Top Bar -->
    <div class="flex items-center justify-between mb-sm" style="flex-wrap: wrap; gap: 12px;">
        <div class="flex items-center gap-xs body-sm">
            <a href="<?= BASE_URL ?>/projects/view.php?id=<?= $task['project_id'] ?>" style="color: var(--text-secondary); text-decoration: none;">
                <?= htmlspecialchars($task['project_name']) ?>
            </a>
            <span style="color: var(--text-muted);">&rsaquo;</span>
            <span style="color: var(--text-primary); font-weight: 600;">Task #<?= $task_id ?></span>
        </div>

        <a href="<?= BASE_URL ?>/projects/view.php?id=<?= $task['project_id'] ?>" class="btn btn-secondary btn-sm flex items-center gap-xs">
            <?= tf_icon('arrow_back', 16) ?>
            <span>Back to Board</span>
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?= tf_icon('error', 18) ?>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Two-Column Layout -->
    <div class="grid" style="grid-template-columns: 2fr 1fr; gap: 24px; align-items: start;">
        <!-- Left: Task Details & Comments -->
        <div>
            <!-- Main Task Card -->
            <div class="card mb-lg">
                <!-- Status & Priority Banner -->
                <div class="flex items-center justify-between mb-md">
                    <span class="pill <?= $task['status'] === 'done' ? 'pill-success' : ($task['status'] === 'in_review' ? 'pill-accent' : ($task['status'] === 'in_progress' ? 'pill-info' : 'pill-neutral')) ?>" style="font-size: 12px; padding: 4px 12px;">
                        <?= htmlspecialchars(strtoupper(str_replace('_', ' ', $task['status']))) ?>
                    </span>
                    <span class="pill <?= $task['priority'] === 'high' ? 'pill-danger' : ($task['priority'] === 'medium' ? 'pill-warning' : 'pill-neutral') ?>">
                        Priority: <?= htmlspecialchars(ucfirst($task['priority'])) ?>
                    </span>
                </div>

                <h1 class="headline-md" style="margin-bottom: 12px;"><?= htmlspecialchars($task['title']) ?></h1>

                <div class="body-md" style="color: var(--text-secondary); line-height: 1.6; margin-bottom: 24px; white-space: pre-wrap;">
                    <?= nl2br(htmlspecialchars($task['description'] ?: 'No detailed description provided.')) ?>
                </div>

                <!-- Status Update Form with Confirm Button -->
                <?php 
                $can_change_status = $can_manage || ($user_role === 'team_member' && $is_assigned);
                if ($can_change_status): 
                ?>
                    <div style="background: var(--surface-alt); border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-top: 16px;">
                        <span class="label-sm" style="display: block; margin-bottom: 8px;">Update Task Status</span>
                        <form method="POST" action="<?= BASE_URL ?>/tasks/update_status.php" class="flex items-center gap-sm">
                            <input type="hidden" name="task_id" value="<?= $task_id ?>">
                            <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                            <select name="status" class="form-control" style="max-width: 200px; height: 36px; font-size: 13px;">
                                <option value="todo" <?= $task['status'] === 'todo' ? 'selected' : '' ?>>To Do</option>
                                <option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="in_review" <?= $task['status'] === 'in_review' ? 'selected' : '' ?>>In Review (Submit)</option>
                                <?php if ($can_manage): ?>
                                    <option value="done" <?= $task['status'] === 'done' ? 'selected' : '' ?>>Done (Approve)</option>
                                <?php endif; ?>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm flex items-center gap-xs" style="height: 36px;">
                                <?= tf_icon('check', 16) ?>
                                <span>Confirm Status</span>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Comments Thread -->
            <div class="card" id="comments">
                <h2 class="headline-sm" style="margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                    <?= tf_icon('chat', 18) ?>
                    <span>Discussion Thread (<?= count($comments) ?>)</span>
                </h2>

                <?php if (!empty($comments)): ?>
                    <div class="flex flex-col gap-md mb-lg">
                        <?php foreach ($comments as $c): ?>
                            <div style="background: var(--surface-alt); border: 1px solid var(--border); border-radius: 8px; padding: 14px;">
                                <div class="flex items-center justify-between mb-xs">
                                    <div class="flex items-center gap-xs">
                                        <span class="user-avatar" style="width: 24px; height: 24px; font-size: 11px;">
                                            <?= strtoupper(substr($c['full_name'], 0, 1)) ?>
                                        </span>
                                        <strong style="font-size: 13px; color: var(--text-primary);"><?= htmlspecialchars($c['full_name']) ?></strong>
                                        <span class="pill pill-neutral" style="font-size: 10px;"><?= htmlspecialchars($c['role']) ?></span>
                                    </div>
                                    <span class="body-sm" style="font-size: 11px; color: var(--text-muted);">
                                        <?= date('M j, Y &bull; g:i a', strtotime($c['created_at'])) ?>
                                    </span>
                                </div>
                                <p class="body-md" style="margin: 6px 0 0 0; color: var(--text-primary); line-height: 1.5; white-space: pre-wrap;">
                                    <?= nl2br(htmlspecialchars($c['comment'])) ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="body-sm" style="color: var(--text-muted); margin-bottom: 20px;">
                        No comments yet. Start the conversation below.
                    </p>
                <?php endif; ?>

                <!-- Post Comment Form -->
                <form method="POST" action="<?= BASE_URL ?>/tasks/view.php?id=<?= $task_id ?>">
                    <input type="hidden" name="action" value="add_comment">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label class="form-label" for="comment">Leave a Comment</label>
                        <textarea id="comment" name="comment" class="form-control" placeholder="Add feedback, update deliverables, or ask questions..." required style="min-height: 80px;"></textarea>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm flex items-center gap-xs">
                        <?= tf_icon('send', 15) ?>
                        <span>Post Comment</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Right: Task Metadata & Edit Controls -->
        <div class="flex flex-col gap-lg">
            <!-- Metadata Card -->
            <div class="card">
                <h3 class="headline-sm" style="margin-bottom: 16px;">Task Metadata</h3>

                <div class="flex flex-col gap-md">
                    <div>
                        <span class="label-sm">Project</span>
                        <p style="font-size: 14px; font-weight: 600; margin: 4px 0 0 0;">
                            <a href="<?= BASE_URL ?>/projects/view.php?id=<?= $task['project_id'] ?>" style="color: var(--text-primary); text-decoration: none;">
                                <?= htmlspecialchars($task['project_name']) ?>
                            </a>
                        </p>
                    </div>

                    <div>
                        <span class="label-sm">Assigned Engineer</span>
                        <div class="flex items-center gap-xs" style="margin-top: 4px;">
                            <?php if (!empty($task['assigned_name'])): ?>
                                <span class="user-avatar" style="width: 24px; height: 24px; font-size: 11px;">
                                    <?= strtoupper(substr($task['assigned_name'], 0, 1)) ?>
                                </span>
                                <div>
                                    <strong style="font-size: 13px; display: block; line-height: 1.2;"><?= htmlspecialchars($task['assigned_name']) ?></strong>
                                    <span class="body-sm" style="font-size: 11px;"><?= htmlspecialchars($task['assigned_email']) ?></span>
                                </div>
                            <?php else: ?>
                                <span class="body-sm" style="color: var(--text-muted); font-style: italic;">Unassigned</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <span class="label-sm">Due Date</span>
                        <p style="font-size: 14px; font-weight: 500; margin: 4px 0 0 0; color: <?= (!empty($task['due_date']) && strtotime($task['due_date']) < time() && $task['status'] !== 'done') ? 'var(--danger-text)' : 'var(--text-primary)' ?>;">
                            <?= !empty($task['due_date']) ? date('F j, Y', strtotime($task['due_date'])) : 'No deadline set' ?>
                        </p>
                    </div>

                    <div>
                        <span class="label-sm">Created By</span>
                        <p style="font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0;">
                            <?= htmlspecialchars($task['creator_name'] ?? 'System') ?> &bull; <?= date('M j, Y', strtotime($task['created_at'])) ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Edit Task Details (PM/Admin Only) -->
            <?php if ($can_manage): ?>
                <div class="card">
                    <h3 class="headline-sm" style="margin-bottom: 16px;">Edit Task Details</h3>

                    <form method="POST" action="<?= BASE_URL ?>/tasks/view.php?id=<?= $task_id ?>">
                        <input type="hidden" name="action" value="update_task_details">

                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label" for="edit_title">Title</label>
                            <input type="text" id="edit_title" name="title" class="form-control" value="<?= htmlspecialchars($task['title']) ?>" required>
                        </div>

                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label" for="edit_desc">Description</label>
                            <textarea id="edit_desc" name="description" class="form-control" style="min-height: 70px;"><?= htmlspecialchars($task['description']) ?></textarea>
                        </div>

                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label" for="edit_assignee">Assignee</label>
                            <select id="edit_assignee" name="assigned_to" class="form-control">
                                <option value="">-- Unassigned --</option>
                                <?php foreach ($project_members as $pm_u): ?>
                                    <option value="<?= $pm_u['id'] ?>" <?= $task['assigned_to'] == $pm_u['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($pm_u['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grid grid-cols-2" style="gap: 12px; margin-bottom: 12px;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" for="edit_priority">Priority</label>
                                <select id="edit_priority" name="priority" class="form-control">
                                    <option value="low" <?= $task['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
                                    <option value="medium" <?= $task['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                                    <option value="high" <?= $task['priority'] === 'high' ? 'selected' : '' ?>>High</option>
                                </select>
                            </div>

                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" for="edit_due">Due Date</label>
                                <input type="date" id="edit_due" name="due_date" class="form-control" value="<?= htmlspecialchars($task['due_date'] ?? '') ?>">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-secondary btn-sm flex items-center justify-center gap-xs" style="width: 100%; margin-top: 10px;">
                            <?= tf_icon('save', 15) ?>
                            <span>Save Changes</span>
                        </button>
                    </form>

                    <!-- Delete Task Form -->
                    <form method="POST" action="<?= BASE_URL ?>/tasks/view.php?id=<?= $task_id ?>" onsubmit="return confirm('Are you sure you want to permanently delete this task?');" style="margin-top: 16px; pt-md; border-top: 1px solid var(--border);">
                        <input type="hidden" name="action" value="delete_task">
                        <button type="submit" class="btn btn-destructive btn-sm flex items-center justify-center gap-xs" style="width: 100%;">
                            <?= tf_icon('delete', 15) ?>
                            <span>Delete Task</span>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
