<?php
$page_title = 'Project Details';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];

$project_id = (int)($_GET['id'] ?? 0);
if ($project_id <= 0) {
    $_SESSION['flash_error'] = "Invalid project ID.";
    header('Location: ' . BASE_URL . '/projects/index.php');
    exit;
}

// Fetch project details
$stmt = mysqli_prepare($conn, "
    SELECT p.id, p.name, p.description, p.status, p.created_at, p.manager_id,
           u.full_name AS manager_name, u.email AS manager_email
    FROM projects p
    LEFT JOIN users u ON p.manager_id = u.id
    WHERE p.id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, "i", $project_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$project = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$project) {
    $_SESSION['flash_error'] = "Project not found.";
    header('Location: ' . BASE_URL . '/projects/index.php');
    exit;
}

// Permission Check: Team Members can only view projects they are a member of
$is_member = false;
$mem_check = mysqli_prepare($conn, "SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1");
mysqli_stmt_bind_param($mem_check, "ii", $project_id, $user_id);
mysqli_stmt_execute($mem_check);
mysqli_stmt_store_result($mem_check);
if (mysqli_stmt_num_rows($mem_check) > 0) {
    $is_member = true;
}
mysqli_stmt_close($mem_check);

$is_manager = ((int)$project['manager_id'] === $user_id);
$can_manage = ($user_role === 'admin' || ($user_role === 'project_manager' && $is_manager));

if ($user_role === 'team_member' && !$is_member) {
    $_SESSION['flash_error'] = "You are not an assigned member of this project.";
    header('Location: ' . BASE_URL . '/projects/index.php');
    exit;
}

// Fetch assigned team members
$members = [];
$mem_sql = "
    SELECT u.id, u.full_name, u.email
    FROM project_members pm
    JOIN users u ON pm.user_id = u.id
    WHERE pm.project_id = ?
    ORDER BY u.full_name ASC
";
$stmt = mysqli_prepare($conn, $mem_sql);
mysqli_stmt_bind_param($stmt, "i", $project_id);
mysqli_stmt_execute($stmt);
$mem_res = mysqli_stmt_get_result($stmt);
while ($m = mysqli_fetch_assoc($mem_res)) {
    $members[] = $m;
}
mysqli_stmt_close($stmt);

// Fetch tasks for this project
$tasks_by_status = [
    'todo' => [],
    'in_progress' => [],
    'in_review' => [],
    'done' => []
];

$task_sql = "
    SELECT t.id, t.title, t.description, t.priority, t.status, t.due_date, t.assigned_to,
           u.full_name AS assigned_name,
           (SELECT COUNT(*) FROM task_comments tc WHERE tc.task_id = t.id) AS comment_count
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to = u.id
    WHERE t.project_id = ?
    ORDER BY t.created_at ASC
";
$stmt = mysqli_prepare($conn, $task_sql);
mysqli_stmt_bind_param($stmt, "i", $project_id);
mysqli_stmt_execute($stmt);
$task_res = mysqli_stmt_get_result($stmt);
$total_tasks = 0;
$done_tasks = 0;

while ($t = mysqli_fetch_assoc($task_res)) {
    $total_tasks++;
    if ($t['status'] === 'done') {
        $done_tasks++;
    }
    if (isset($tasks_by_status[$t['status']])) {
        $tasks_by_status[$t['status']][] = $t;
    }
}
mysqli_stmt_close($stmt);

$progress_pct = $total_tasks > 0 ? round(($done_tasks / $total_tasks) * 100) : 0;

$page_title = $project['name'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding-top: 32px;">
    <!-- Breadcrumb & Top Actions -->
    <div class="flex items-center justify-between mb-sm" style="flex-wrap: wrap; gap: 12px;">
        <div class="flex items-center gap-xs body-sm">
            <a href="<?= BASE_URL ?>/projects/index.php" style="color: var(--text-secondary); text-decoration: none;">Projects</a>
            <span style="color: var(--text-muted);">&rsaquo;</span>
            <span style="color: var(--text-primary); font-weight: 600;"><?= htmlspecialchars($project['name']) ?></span>
        </div>

        <div class="flex items-center gap-sm">
            <?php if ($can_manage): ?>
                <a href="<?= BASE_URL ?>/tasks/create.php?project_id=<?= $project_id ?>" class="btn btn-primary btn-sm flex items-center gap-xs">
                    <?= tf_icon('add', 15) ?>
                    <span>New Task</span>
                </a>
                <a href="<?= BASE_URL ?>/projects/edit.php?id=<?= $project_id ?>" class="btn btn-secondary btn-sm flex items-center gap-xs">
                    <?= tf_icon('settings', 15) ?>
                    <span>Edit Project</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Project Overview Hero Card -->
    <div class="card mb-xl" style="padding: 24px 28px;">
        <div class="flex items-start justify-between" style="flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">
            <div style="max-width: 720px;">
                <div class="flex items-center gap-sm mb-xs">
                    <h1 class="headline-md" style="margin: 0;"><?= htmlspecialchars($project['name']) ?></h1>
                    <span class="pill <?= $project['status'] === 'active' ? 'pill-success' : ($project['status'] === 'completed' ? 'pill-neutral' : 'pill-warning') ?>">
                        <?= htmlspecialchars($project['status']) ?>
                    </span>
                </div>
                <p class="body-md" style="color: var(--text-secondary); margin: 6px 0 0 0; line-height: 1.6;">
                    <?= nl2br(htmlspecialchars($project['description'] ?: 'No detailed scope provided.')) ?>
                </p>
            </div>

            <!-- Progress Meter -->
            <div style="min-width: 180px; background: var(--surface-alt); border: 1px solid var(--border); padding: 14px 18px; border-radius: 10px;">
                <div class="flex items-center justify-between mb-xs">
                    <span class="label-sm">Sprint Velocity</span>
                    <strong style="font-size: 14px; color: var(--text-primary);"><?= $progress_pct ?>%</strong>
                </div>
                <div style="height: 6px; width: 100%; background: var(--border); border-radius: 9999px; overflow: hidden; margin-bottom: 6px;">
                    <div style="height: 100%; width: <?= $progress_pct ?>%; background: var(--primary);"></div>
                </div>
                <span class="body-sm" style="font-size: 11px; color: var(--text-muted); display: block;">
                    <?= $done_tasks ?> of <?= $total_tasks ?> deliverables done
                </span>
            </div>
        </div>

        <!-- Manager & Member Badges Bar -->
        <div class="flex items-center justify-between pt-md" style="border-top: 1px solid var(--border); flex-wrap: wrap; gap: 16px;">
            <div class="flex items-center gap-md">
                <div class="flex items-center gap-xs">
                    <span style="color: var(--text-secondary); display: flex; align-items: center;"><?= tf_icon('verified_user', 18) ?></span>
                    <span class="body-sm" style="font-size: 13px;">
                        Project Lead: <strong><?= htmlspecialchars($project['manager_name'] ?? 'Unassigned') ?></strong>
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-sm">
                <span class="label-sm" style="font-size: 11px;">Team Members:</span>
                <div class="flex items-center" style="margin-left: 6px;">
                    <?php if (!empty($members)): ?>
                        <?php foreach ($members as $m): ?>
                            <div class="user-avatar" style="width: 28px; height: 28px; font-size: 11px; margin-left: -6px; border: 2px solid #FFFFFF;" title="<?= htmlspecialchars($m['full_name']) ?> (<?= htmlspecialchars($m['email']) ?>)">
                                <?= strtoupper(substr($m['full_name'], 0, 1)) ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="body-sm" style="font-size: 12px; color: var(--text-muted);">None assigned</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 4-Column Redesigned Interactive Kanban Board -->
    <div style="margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
        <div>
            <h2 class="headline-md" style="margin: 0;">Sprint Task Board</h2>
            <p class="body-sm" style="margin: 4px 0 0 0;">Move tasks across stages by selecting a status and clicking Confirm.</p>
        </div>
        <div class="flex items-center gap-xs">
            <span class="pill pill-neutral" style="font-weight: 600;"><?= $total_tasks ?> deliverables</span>
            <?php if ($can_manage): ?>
                <a href="<?= BASE_URL ?>/tasks/create.php?project_id=<?= $project_id ?>" class="btn btn-primary btn-sm flex items-center gap-xs">
                    <?= tf_icon('add', 15) ?>
                    <span>Add Task</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="kanban-board">
        <?php
        $columns = [
            'todo' => ['title' => 'To Do', 'pill' => 'pill-neutral', 'color' => 'var(--text-muted)'],
            'in_progress' => ['title' => 'In Progress', 'pill' => 'pill-info', 'color' => 'var(--info-text)'],
            'in_review' => ['title' => 'In Review', 'pill' => 'pill-accent', 'color' => 'var(--accent)'],
            'done' => ['title' => 'Done', 'pill' => 'pill-success', 'color' => 'var(--success-text)']
        ];

        foreach ($columns as $col_key => $col_meta):
            $col_tasks = $tasks_by_status[$col_key] ?? [];
        ?>
            <!-- Column -->
            <div class="kanban-column">
                <!-- Column Header -->
                <div class="kanban-column-header">
                    <div class="kanban-column-title">
                        <span class="kanban-status-dot" style="background: <?= $col_meta['color'] ?>;"></span>
                        <span><?= $col_meta['title'] ?></span>
                    </div>
                    <span class="pill <?= $col_meta['pill'] ?>" style="font-size: 11px;"><?= count($col_tasks) ?></span>
                </div>

                <!-- Cards List -->
                <div class="kanban-cards-list">
                    <?php if (!empty($col_tasks)): ?>
                        <?php foreach ($col_tasks as $task): 
                            $tid = (int)$task['id'];
                            $is_assigned_to_me = ($task['assigned_to'] && (int)$task['assigned_to'] === $user_id);
                            $can_change_status = $can_manage || ($user_role === 'team_member' && $is_assigned_to_me);
                        ?>
                            <div class="kanban-task-card">
                                <!-- Top Row: Priority & Due Date -->
                                <div class="flex items-center justify-between">
                                    <span class="pill <?= $task['priority'] === 'high' ? 'pill-danger' : ($task['priority'] === 'medium' ? 'pill-warning' : 'pill-neutral') ?>" style="font-size: 10px;">
                                        <?= htmlspecialchars(strtoupper($task['priority'])) ?>
                                    </span>
                                    <?php if (!empty($task['due_date'])): ?>
                                        <div class="flex items-center gap-xs body-sm" style="font-size: 11px; color: var(--text-secondary);">
                                            <span style="color: var(--text-muted); display: flex; align-items: center;"><?= tf_icon('calendar_today', 13) ?></span>
                                            <span><?= date('M j', strtotime($task['due_date'])) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Title Link -->
                                <a href="<?= BASE_URL ?>/tasks/view.php?id=<?= $tid ?>" class="kanban-card-title">
                                    <?= htmlspecialchars($task['title']) ?>
                                </a>

                                <?php if (!empty($task['description'])): ?>
                                    <p class="body-sm" style="font-size: 12px; margin: 0; color: var(--text-secondary); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4;">
                                        <?= htmlspecialchars($task['description']) ?>
                                    </p>
                                <?php endif; ?>

                                <!-- Card Meta: Assignee & Comments -->
                                <div class="kanban-card-meta">
                                    <div class="flex items-center gap-xs">
                                        <?php if (!empty($task['assigned_name'])): ?>
                                            <span class="user-avatar" style="width: 22px; height: 22px; font-size: 10px;">
                                                <?= strtoupper(substr($task['assigned_name'], 0, 1)) ?>
                                            </span>
                                            <span style="font-size: 12px; font-weight: 500; color: var(--text-primary);">
                                                <?= htmlspecialchars($task['assigned_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="font-size: 11px; color: var(--text-muted); font-style: italic;">Unassigned</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="flex items-center gap-sm">
                                        <?php if ((int)$task['comment_count'] > 0): ?>
                                            <span class="flex items-center gap-xs" style="font-size: 11px; color: var(--text-secondary);">
                                                <span style="display: flex; align-items: center;"><?= tf_icon('chat_bubble_outline', 13) ?></span>
                                                <span><?= (int)$task['comment_count'] ?></span>
                                            </span>
                                        <?php endif; ?>
                                        <a href="<?= BASE_URL ?>/tasks/view.php?id=<?= $tid ?>" class="btn btn-ghost btn-sm" style="padding: 2px 6px; font-size: 11px; display: inline-flex; align-items: center;" title="Task Details">
                                            <?= tf_icon('open_in_new', 13) ?>
                                        </a>
                                    </div>
                                </div>

                                <!-- Explicit Status Changer with Confirm Button -->
                                <?php if ($can_change_status): ?>
                                    <form method="POST" action="<?= BASE_URL ?>/tasks/update_status.php" class="kanban-status-form">
                                        <input type="hidden" name="task_id" value="<?= $tid ?>">
                                        <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                                        <select name="status" class="kanban-status-select" aria-label="Change status">
                                            <option value="todo" <?= $col_key === 'todo' ? 'selected' : '' ?>>To Do</option>
                                            <option value="in_progress" <?= $col_key === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                            <option value="in_review" <?= $col_key === 'in_review' ? 'selected' : '' ?>>In Review</option>
                                            <?php if ($can_manage): ?>
                                                <option value="done" <?= $col_key === 'done' ? 'selected' : '' ?>>Done (Approve)</option>
                                            <?php endif; ?>
                                        </select>
                                        <button type="submit" class="btn-confirm" title="Apply status change">
                                            <span>Confirm</span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="kanban-empty">
                            No tasks in <?= htmlspecialchars($col_meta['title']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
