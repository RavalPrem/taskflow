<?php
$page_title = 'My Tasks';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];

$status_filter = trim($_GET['status'] ?? '');
$allowed_statuses = ['todo', 'in_progress', 'in_review', 'done'];

$where_status = '';
if (in_array($status_filter, $allowed_statuses, true)) {
    $where_status = " AND t.status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
}

// Fetch all tasks assigned to this user
$query = "
    SELECT t.id, t.title, t.description, t.priority, t.status, t.due_date, t.created_at,
           p.id AS project_id, p.name AS project_name,
           (SELECT COUNT(*) FROM task_comments tc WHERE tc.task_id = t.id) AS comment_count
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    WHERE t.assigned_to = ? {$where_status}
    ORDER BY FIELD(t.status, 'in_progress', 'todo', 'in_review', 'done'), t.due_date ASC
";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

// Counts by status for quick summary pills
$cnt_stmt = mysqli_prepare($conn, "
    SELECT status, COUNT(*) as cnt 
    FROM tasks 
    WHERE assigned_to = ? 
    GROUP BY status
");
mysqli_stmt_bind_param($cnt_stmt, "i", $user_id);
mysqli_stmt_execute($cnt_stmt);
$cnt_res = mysqli_stmt_get_result($cnt_stmt);
$counts = ['todo' => 0, 'in_progress' => 0, 'in_review' => 0, 'done' => 0];
$total_assigned = 0;
while ($c_row = mysqli_fetch_assoc($cnt_res)) {
    $counts[$c_row['status']] = (int)$c_row['cnt'];
    $total_assigned += (int)$c_row['cnt'];
}
mysqli_stmt_close($cnt_stmt);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding-top: 32px;">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <span class="eyebrow">MY WORKSPACE</span>
            <h1 class="headline-lg">My Tasks</h1>
            <p class="body-sm">All sprint deliverables assigned to you across your active projects.</p>
        </div>

        <div class="flex items-center gap-xs">
            <a href="<?= BASE_URL ?>/tasks/board.php" class="btn btn-secondary btn-sm flex items-center gap-xs">
                <?= tf_icon('view_kanban', 16) ?>
                <span>Open Task Board</span>
            </a>
        </div>
    </div>

    <!-- Status Filter Pills -->
    <div class="flex items-center gap-xs mb-lg" style="flex-wrap: wrap;">
        <a href="<?= BASE_URL ?>/tasks/my_tasks.php" 
           class="pill" 
           style="text-decoration: none; padding: 6px 14px; font-weight: <?= empty($status_filter) ? '700' : '500' ?>; background: <?= empty($status_filter) ? 'var(--primary); color: #fff;' : 'var(--surface); border: 1px solid var(--border); color: var(--text-secondary);' ?>">
            All (<?= $total_assigned ?>)
        </a>
        <a href="<?= BASE_URL ?>/tasks/my_tasks.php?status=in_progress" 
           class="pill" 
           style="text-decoration: none; padding: 6px 14px; font-weight: <?= $status_filter === 'in_progress' ? '700' : '500' ?>; background: <?= $status_filter === 'in_progress' ? 'var(--primary); color: #fff;' : 'var(--surface); border: 1px solid var(--border); color: var(--text-secondary);' ?>">
            In Progress (<?= $counts['in_progress'] ?>)
        </a>
        <a href="<?= BASE_URL ?>/tasks/my_tasks.php?status=todo" 
           class="pill" 
           style="text-decoration: none; padding: 6px 14px; font-weight: <?= $status_filter === 'todo' ? '700' : '500' ?>; background: <?= $status_filter === 'todo' ? 'var(--primary); color: #fff;' : 'var(--surface); border: 1px solid var(--border); color: var(--text-secondary);' ?>">
            To Do (<?= $counts['todo'] ?>)
        </a>
        <a href="<?= BASE_URL ?>/tasks/my_tasks.php?status=in_review" 
           class="pill" 
           style="text-decoration: none; padding: 6px 14px; font-weight: <?= $status_filter === 'in_review' ? '700' : '500' ?>; background: <?= $status_filter === 'in_review' ? 'var(--primary); color: #fff;' : 'var(--surface); border: 1px solid var(--border); color: var(--text-secondary);' ?>">
            In Review (<?= $counts['in_review'] ?>)
        </a>
        <a href="<?= BASE_URL ?>/tasks/my_tasks.php?status=done" 
           class="pill" 
           style="text-decoration: none; padding: 6px 14px; font-weight: <?= $status_filter === 'done' ? '700' : '500' ?>; background: <?= $status_filter === 'done' ? 'var(--primary); color: #fff;' : 'var(--surface); border: 1px solid var(--border); color: var(--text-secondary);' ?>">
            Done (<?= $counts['done'] ?>)
        </a>
    </div>

    <!-- My Tasks Table -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Project</th>
                        <th>Priority</th>
                        <th>Due Date</th>
                        <th>Current Status</th>
                        <th style="min-width: 220px;">Update Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($res && mysqli_num_rows($res) > 0): ?>
                        <?php while ($t = mysqli_fetch_assoc($res)): 
                            $tid = (int)$t['id'];
                            $is_overdue = (!empty($t['due_date']) && strtotime($t['due_date']) < time() && $t['status'] !== 'done');
                        ?>
                            <tr>
                                <td>
                                    <a href="<?= BASE_URL ?>/tasks/view.php?id=<?= $tid ?>" style="font-weight: 600; color: var(--text-primary); text-decoration: none;">
                                        <?= htmlspecialchars($t['title']) ?>
                                    </a>
                                    <?php if ((int)$t['comment_count'] > 0): ?>
                                        <span class="body-sm" style="font-size: 11px; color: var(--text-muted); margin-left: 6px;">
                                            (<?= (int)$t['comment_count'] ?> comments)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/projects/view.php?id=<?= $t['project_id'] ?>" style="color: var(--text-secondary); text-decoration: none; font-size: 13px;">
                                        <?= htmlspecialchars($t['project_name']) ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="pill <?= $t['priority'] === 'high' ? 'pill-danger' : ($t['priority'] === 'medium' ? 'pill-warning' : 'pill-neutral') ?>" style="font-size: 10px;">
                                        <?= htmlspecialchars(strtoupper($t['priority'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($t['due_date'])): ?>
                                        <span style="font-size: 12px; color: <?= $is_overdue ? 'var(--danger-text); font-weight: 600;' : 'var(--text-secondary);' ?>">
                                            <?= date('M j, Y', strtotime($t['due_date'])) ?>
                                            <?= $is_overdue ? ' (Overdue)' : '' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="body-sm" style="color: var(--text-muted);">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="pill <?= $t['status'] === 'done' ? 'pill-success' : ($t['status'] === 'in_review' ? 'pill-accent' : ($t['status'] === 'in_progress' ? 'pill-info' : 'pill-neutral')) ?>" style="font-size: 11px;">
                                        <?= htmlspecialchars(str_replace('_', ' ', $t['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <!-- Explicit Status Changer with Confirm Button -->
                                    <form method="POST" action="<?= BASE_URL ?>/tasks/update_status.php" style="display: flex; align-items: center; gap: 6px;">
                                        <input type="hidden" name="task_id" value="<?= $tid ?>">
                                        <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                        <select name="status" class="form-control" style="height: 30px; font-size: 12px; padding: 0 6px;">
                                            <option value="todo" <?= $t['status'] === 'todo' ? 'selected' : '' ?>>To Do</option>
                                            <option value="in_progress" <?= $t['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                            <option value="in_review" <?= $t['status'] === 'in_review' ? 'selected' : '' ?>>In Review</option>
                                            <?php if ($user_role === 'admin' || $user_role === 'project_manager'): ?>
                                                <option value="done" <?= $t['status'] === 'done' ? 'selected' : '' ?>>Done (Approve)</option>
                                            <?php endif; ?>
                                        </select>
                                        <button type="submit" class="btn-confirm" title="Apply status change">
                                            <span>Confirm</span>
                                        </button>
                                    </form>
                                </td>
                                <td style="text-align: right;">
                                    <a href="<?= BASE_URL ?>/tasks/view.php?id=<?= $tid ?>" class="btn btn-ghost btn-sm">
                                        Details
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 48px 16px;">
                                No tasks found matching the selected filter.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
