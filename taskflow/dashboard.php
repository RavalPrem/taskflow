<?php
$page_title = 'Dashboard';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/header.php';

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['full_name'];

// =========================================================================
// ROLE-SPECIFIC REAL DATABASE QUERIES
// =========================================================================

if ($user_role === 'admin') {
    // 1. Admin Stat Cards (System-wide COUNTs)
    $res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users");
    $total_users = (int)(mysqli_fetch_assoc($res)['cnt'] ?? 0);

    $res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM projects");
    $total_projects = (int)(mysqli_fetch_assoc($res)['cnt'] ?? 0);

    $res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM tasks");
    $total_tasks = (int)(mysqli_fetch_assoc($res)['cnt'] ?? 0);

    $res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM tasks WHERE status = 'in_review'");
    $tasks_in_review_count = (int)(mysqli_fetch_assoc($res)['cnt'] ?? 0);

    // 2. Admin Projects Progress (All projects with % complete)
    $proj_sql = "
        SELECT p.id, p.name, p.status, u.full_name AS manager_name,
               COUNT(t.id) AS total_tasks,
               SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END) AS done_tasks
        FROM projects p
        LEFT JOIN users u ON p.manager_id = u.id
        LEFT JOIN tasks t ON p.id = t.project_id
        GROUP BY p.id, p.name, p.status, u.full_name
        ORDER BY p.created_at DESC
    ";
    $projects_res = mysqli_query($conn, $proj_sql);

    // 3. Admin Tasks Awaiting Review (System-wide)
    $review_sql = "
        SELECT t.id, t.title, t.priority, t.due_date, p.name AS project_name, u.full_name AS assigned_name
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE t.status = 'in_review'
        ORDER BY t.created_at DESC
        LIMIT 6
    ";
    $reviews_res = mysqli_query($conn, $review_sql);

    // 4. Admin Recent Activity Logs
    $log_sql = "
        SELECT l.action, l.created_at, u.full_name
        FROM activity_log l
        LEFT JOIN users u ON l.user_id = u.id
        ORDER BY l.created_at DESC
        LIMIT 5
    ";
    $activity_res = mysqli_query($conn, $log_sql);

} elseif ($user_role === 'project_manager') {
    // 1. Project Manager Stat Cards (Scoped to projects managed by this PM)
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM projects WHERE manager_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $total_projects = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "SELECT COUNT(t.id) as cnt FROM tasks t JOIN projects p ON t.project_id = p.id WHERE p.manager_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $total_tasks = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "SELECT COUNT(t.id) as cnt FROM tasks t JOIN projects p ON t.project_id = p.id WHERE p.manager_id = ? AND t.status = 'in_review'");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $tasks_in_review_count = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "SELECT COUNT(t.id) as cnt FROM tasks t JOIN projects p ON t.project_id = p.id WHERE p.manager_id = ? AND t.status = 'done'");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $completed_tasks_count = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0);
    mysqli_stmt_close($stmt);

    // 2. PM Projects Progress (Their managed projects)
    $stmt = mysqli_prepare($conn, "
        SELECT p.id, p.name, p.status,
               COUNT(t.id) AS total_tasks,
               SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END) AS done_tasks
        FROM projects p
        LEFT JOIN tasks t ON p.id = t.project_id
        WHERE p.manager_id = ?
        GROUP BY p.id, p.name, p.status
        ORDER BY p.created_at DESC
    ");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $projects_res = mysqli_stmt_get_result($stmt);

    // 3. PM Tasks Awaiting Review (Tasks in their projects)
    $stmt = mysqli_prepare($conn, "
        SELECT t.id, t.title, t.priority, t.due_date, p.name AS project_name, u.full_name AS assigned_name
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        LEFT JOIN users u ON t.assigned_to = u.id
        WHERE p.manager_id = ? AND t.status = 'in_review'
        ORDER BY t.created_at DESC
        LIMIT 6
    ");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $reviews_res = mysqli_stmt_get_result($stmt);

} else {
    // Team Member Stat Cards (Scoped to tasks assigned to this user)
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM tasks WHERE assigned_to = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $my_total_tasks = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM tasks WHERE assigned_to = ? AND status = 'in_progress'");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $my_in_progress = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM tasks WHERE assigned_to = ? AND status = 'in_review'");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $my_in_review = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM tasks WHERE assigned_to = ? AND status = 'done'");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $my_completed = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0);
    mysqli_stmt_close($stmt);

    // Team Member's Tasks List
    $stmt = mysqli_prepare($conn, "
        SELECT t.id, t.title, t.status, t.priority, t.due_date, p.name AS project_name
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        WHERE t.assigned_to = ?
        ORDER BY FIELD(t.status, 'in_progress', 'todo', 'in_review', 'done'), t.due_date ASC
        LIMIT 10
    ");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $my_tasks_res = mysqli_stmt_get_result($stmt);

    // Team Member's Projects (Projects they belong to via project_members)
    $stmt = mysqli_prepare($conn, "
        SELECT p.id, p.name, p.status, u.full_name AS manager_name
        FROM project_members pm
        JOIN projects p ON pm.project_id = p.id
        LEFT JOIN users u ON p.manager_id = u.id
        WHERE pm.user_id = ?
        ORDER BY p.created_at DESC
    ");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $my_projects_res = mysqli_stmt_get_result($stmt);
}
?>

<div class="container" style="padding-top: 32px;">
    <!-- Top Header & Quick Actions -->
    <div class="page-header">
        <div>
            <span class="eyebrow">WORKSPACE OVERVIEW</span>
            <h1 class="headline-lg">Dashboard</h1>
            <p class="body-sm">
                Welcome back, <strong><?= htmlspecialchars($user_name) ?></strong> &bull; 
                <?php if ($user_role === 'admin'): ?>
                    Viewing organization-wide metrics and system status
                <?php elseif ($user_role === 'project_manager'): ?>
                    Viewing your managed project portfolio and team deliverables
                <?php else: ?>
                    Viewing your assigned tasks and active project memberships
                <?php endif; ?>
            </p>
        </div>

        <!-- Quick Actions based on Role -->
        <div class="flex items-center gap-sm">
            <?php if ($user_role === 'admin'): ?>
                <a href="<?= BASE_URL ?>/tasks/create.php" class="btn btn-primary btn-sm">
                    <?= tf_icon('add', '', 16) ?>
                    <span>New Task</span>
                </a>
                <a href="<?= BASE_URL ?>/projects/create.php" class="btn btn-secondary btn-sm">
                    <?= tf_icon('create_new_folder', '', 16) ?>
                    <span>New Project</span>
                </a>
                <a href="<?= BASE_URL ?>/admin/manage_users.php" class="btn btn-secondary btn-sm">
                    <?= tf_icon('manage_accounts', '', 16) ?>
                    <span>Manage Users</span>
                </a>
                <a href="<?= BASE_URL ?>/tasks/board.php" class="btn btn-ghost btn-sm">
                    <span>Task Board</span>
                </a>
            <?php elseif ($user_role === 'project_manager'): ?>
                <a href="<?= BASE_URL ?>/tasks/create.php" class="btn btn-primary btn-sm">
                    <?= tf_icon('add', '', 16) ?>
                    <span>New Task</span>
                </a>
                <a href="<?= BASE_URL ?>/projects/create.php" class="btn btn-secondary btn-sm">
                    <?= tf_icon('create_new_folder', '', 16) ?>
                    <span>New Project</span>
                </a>
                <a href="<?= BASE_URL ?>/tasks/board.php" class="btn btn-ghost btn-sm">
                    <span>Task Board</span>
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/tasks/my_tasks.php" class="btn btn-primary btn-sm">
                    <?= tf_icon('checklist', '', 16) ?>
                    <span>View All My Tasks</span>
                </a>
                <a href="<?= BASE_URL ?>/projects/index.php" class="btn btn-secondary btn-sm">
                    <?= tf_icon('folder', '', 16) ?>
                    <span>My Projects</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stat Cards Grid (Real MySQLi Counts) -->
    <div class="grid grid-cols-4 mb-xl" style="gap: 20px;">
        <?php if ($user_role === 'admin'): ?>
            <div class="stat-card">
                <span class="stat-label">Total Users</span>
                <div class="stat-value"><?= $total_users ?></div>
                <span class="stat-desc">Active in organization</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Total Projects</span>
                <div class="stat-value"><?= $total_projects ?></div>
                <span class="stat-desc">Across all departments</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Total Tasks</span>
                <div class="stat-value"><?= $total_tasks ?></div>
                <span class="stat-desc">Tracked work items</span>
            </div>
            <div class="stat-card" style="border-left: 3px solid var(--accent);">
                <span class="stat-label" style="color: var(--accent);">Tasks In Review</span>
                <div class="stat-value" style="color: var(--accent);"><?= $tasks_in_review_count ?></div>
                <span class="stat-desc">Awaiting approval</span>
            </div>

        <?php elseif ($user_role === 'project_manager'): ?>
            <div class="stat-card">
                <span class="stat-label">Managed Projects</span>
                <div class="stat-value"><?= $total_projects ?></div>
                <span class="stat-desc">Under your supervision</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Project Tasks</span>
                <div class="stat-value"><?= $total_tasks ?></div>
                <span class="stat-desc">Total across your projects</span>
            </div>
            <div class="stat-card" style="border-left: 3px solid var(--accent);">
                <span class="stat-label" style="color: var(--accent);">Awaiting Review</span>
                <div class="stat-value" style="color: var(--accent);"><?= $tasks_in_review_count ?></div>
                <span class="stat-desc">Require PM sign-off</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Tasks Completed</span>
                <div class="stat-value" style="color: var(--success-text);"><?= $completed_tasks_count ?></div>
                <span class="stat-desc">Marked as Done</span>
            </div>

        <?php else: ?>
            <div class="stat-card">
                <span class="stat-label">Assigned Tasks</span>
                <div class="stat-value"><?= $my_total_tasks ?></div>
                <span class="stat-desc">Your total workload</span>
            </div>
            <div class="stat-card" style="border-left: 3px solid var(--info-text);">
                <span class="stat-label" style="color: var(--info-text);">In Progress</span>
                <div class="stat-value" style="color: var(--info-text);"><?= $my_in_progress ?></div>
                <span class="stat-desc">Currently working on</span>
            </div>
            <div class="stat-card" style="border-left: 3px solid var(--accent);">
                <span class="stat-label" style="color: var(--accent);">In Review</span>
                <div class="stat-value" style="color: var(--accent);"><?= $my_in_review ?></div>
                <span class="stat-desc">Submitted for review</span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Completed Tasks</span>
                <div class="stat-value" style="color: var(--success-text);"><?= $my_completed ?></div>
                <span class="stat-desc">Successfully finished</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Main Content Layout (Role Conditional) -->
    <div class="grid" style="grid-template-columns: 2fr 1.2fr; gap: 24px; align-items: start;">
        
        <!-- LEFT COLUMN -->
        <div>
            <?php if ($user_role === 'admin' || $user_role === 'project_manager'): ?>
                <!-- Projects Progress Table -->
                <div class="table-card">
                    <div style="padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h2 class="headline-sm">Projects Progress</h2>
                            <p class="body-sm">
                                <?= $user_role === 'admin' ? 'All active and monitored organization projects' : 'Projects under your management' ?>
                            </p>
                        </div>
                        <a href="<?= BASE_URL ?>/projects/index.php" class="btn btn-ghost btn-sm">View All</a>
                    </div>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Status</th>
                                    <?php if ($user_role === 'admin'): ?>
                                        <th>Manager</th>
                                    <?php endif; ?>
                                    <th>Progress</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($projects_res && mysqli_num_rows($projects_res) > 0): ?>
                                    <?php while ($proj = mysqli_fetch_assoc($projects_res)): 
                                        $p_total = (int)$proj['total_tasks'];
                                        $p_done = (int)$proj['done_tasks'];
                                        $pct = $p_total > 0 ? round(($p_done / $p_total) * 100) : 0;
                                    ?>
                                        <tr>
                                            <td>
                                                <a href="<?= BASE_URL ?>/projects/view.php?id=<?= $proj['id'] ?>" style="font-weight: 600; color: var(--text-primary); text-decoration: none;">
                                                    <?= htmlspecialchars($proj['name']) ?>
                                                </a>
                                                <div class="body-sm" style="font-size: 12px;">
                                                    <?= $p_done ?> of <?= $p_total ?> tasks completed
                                                </div>
                                            </td>
                                            <td>
                                                <span class="pill <?= $proj['status'] === 'active' ? 'pill-success' : ($proj['status'] === 'completed' ? 'pill-neutral' : 'pill-warning') ?>">
                                                    <?= htmlspecialchars($proj['status']) ?>
                                                </span>
                                            </td>
                                            <?php if ($user_role === 'admin'): ?>
                                                <td>
                                                    <span class="body-sm"><?= htmlspecialchars($proj['manager_name'] ?? 'Unassigned') ?></span>
                                                </td>
                                            <?php endif; ?>
                                            <td style="width: 140px;">
                                                <div class="flex items-center gap-sm">
                                                    <div style="flex: 1; height: 6px; background: var(--border); border-radius: 9999px; overflow: hidden;">
                                                        <div style="height: 100%; width: <?= $pct ?>%; background: var(--primary);"></div>
                                                    </div>
                                                    <span style="font-size: 12px; font-weight: 600; min-width: 32px;"><?= $pct ?>%</span>
                                                </div>
                                            </td>
                                            <td style="text-align: right;">
                                                <a href="<?= BASE_URL ?>/projects/view.php?id=<?= $proj['id'] ?>" class="btn btn-ghost btn-sm">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                            No projects found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php else: ?>
                <!-- Team Member: My Assigned Tasks Table -->
                <div class="table-card">
                    <div style="padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h2 class="headline-sm">My Tasks</h2>
                            <p class="body-sm">Tasks assigned to you across all projects</p>
                        </div>
                        <a href="<?= BASE_URL ?>/tasks/my_tasks.php" class="btn btn-ghost btn-sm">View All</a>
                    </div>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Task Title</th>
                                    <th>Project</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($my_tasks_res && mysqli_num_rows($my_tasks_res) > 0): ?>
                                    <?php while ($task = mysqli_fetch_assoc($my_tasks_res)): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= BASE_URL ?>/tasks/view.php?id=<?= $task['id'] ?>" style="font-weight: 600; color: var(--text-primary); text-decoration: none;">
                                                    <?= htmlspecialchars($task['title']) ?>
                                                </a>
                                                <?php if (!empty($task['due_date'])): ?>
                                                    <div class="body-sm" style="font-size: 12px;">
                                                        Due: <?= date('M j, Y', strtotime($task['due_date'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="body-sm"><?= htmlspecialchars($task['project_name']) ?></span>
                                            </td>
                                            <td>
                                                <span class="pill <?= $task['priority'] === 'high' ? 'pill-danger' : ($task['priority'] === 'medium' ? 'pill-warning' : 'pill-neutral') ?>" style="font-size: 11px;">
                                                    <?= htmlspecialchars($task['priority']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="pill <?= $task['status'] === 'done' ? 'pill-success' : ($task['status'] === 'in_review' ? 'pill-accent' : ($task['status'] === 'in_progress' ? 'pill-info' : 'pill-neutral')) ?>" style="font-size: 11px;">
                                                    <?= htmlspecialchars(str_replace('_', ' ', $task['status'])) ?>
                                                </span>
                                            </td>
                                            <td style="text-align: right;">
                                                <a href="<?= BASE_URL ?>/tasks/view.php?id=<?= $task['id'] ?>" class="btn btn-ghost btn-sm">
                                                    Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                            You currently have no assigned tasks.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="flex flex-col gap-lg">
            <?php if ($user_role === 'admin' || $user_role === 'project_manager'): ?>
                <!-- Tasks Awaiting Review Card -->
                <div class="card">
                    <div class="flex items-center justify-between mb-md">
                        <div>
                            <h3 class="headline-sm">Tasks Awaiting Review</h3>
                            <span class="body-sm">Requires verification &amp; sign-off</span>
                        </div>
                        <span class="pill pill-accent"><?= $tasks_in_review_count ?></span>
                    </div>

                    <?php if ($reviews_res && mysqli_num_rows($reviews_res) > 0): ?>
                        <div class="flex flex-col gap-sm">
                            <?php while ($rev = mysqli_fetch_assoc($reviews_res)): ?>
                                <div style="background: var(--surface-alt); border: 1px solid var(--border); border-radius: 8px; padding: 14px;">
                                    <div class="flex items-center justify-between mb-xs">
                                        <span class="pill <?= $rev['priority'] === 'high' ? 'pill-danger' : 'pill-warning' ?>" style="font-size: 10px;">
                                            <?= htmlspecialchars($rev['priority']) ?>
                                        </span>
                                        <span class="body-sm" style="font-size: 11px;">
                                            <?= htmlspecialchars($rev['project_name']) ?>
                                        </span>
                                    </div>
                                    <strong style="font-size: 13px; display: block; margin-bottom: 4px;">
                                        <?= htmlspecialchars($rev['title']) ?>
                                    </strong>
                                    <div class="flex items-center justify-between" style="margin-top: 8px;">
                                        <span class="body-sm" style="font-size: 12px;">
                                            By: <?= htmlspecialchars($rev['assigned_name'] ?? 'Unassigned') ?>
                                        </span>
                                        <a href="<?= BASE_URL ?>/tasks/view.php?id=<?= $rev['id'] ?>" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 4px 8px;">
                                            Review
                                        </a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="body-sm" style="color: var(--text-muted); text-align: center; padding: 20px 0;">
                            No tasks currently awaiting review.
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Admin Only: System Activity Log -->
                <?php if ($user_role === 'admin' && isset($activity_res)): ?>
                    <div class="card">
                        <div class="flex items-center justify-between mb-md">
                            <h3 class="headline-sm">Recent Audit Log</h3>
                            <a href="<?= BASE_URL ?>/admin/system_admin.php" class="btn btn-ghost btn-sm">All Logs</a>
                        </div>
                        <div class="flex flex-col gap-sm">
                            <?php while ($act = mysqli_fetch_assoc($activity_res)): ?>
                                <div style="border-bottom: 1px solid var(--border); padding-bottom: 8px;">
                                    <p class="body-sm" style="color: var(--text-primary); margin: 0;">
                                        <strong><?= htmlspecialchars($act['full_name'] ?? 'System') ?></strong>: <?= htmlspecialchars($act['action']) ?>
                                    </p>
                                    <span class="body-sm" style="font-size: 11px; color: var(--text-muted);">
                                        <?= date('M j, g:i a', strtotime($act['created_at'])) ?>
                                    </span>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Team Member: My Contributing Projects -->
                <div class="card">
                    <div class="flex items-center justify-between mb-md">
                        <h3 class="headline-sm">My Projects</h3>
                        <span class="pill pill-neutral">Member</span>
                    </div>

                    <?php if ($my_projects_res && mysqli_num_rows($my_projects_res) > 0): ?>
                        <div class="flex flex-col gap-sm">
                            <?php while ($proj = mysqli_fetch_assoc($my_projects_res)): ?>
                                <div style="background: var(--surface-alt); border: 1px solid var(--border); border-radius: 8px; padding: 14px;">
                                    <div class="flex items-center justify-between mb-xs">
                                        <strong style="font-size: 14px;">
                                            <?= htmlspecialchars($proj['name']) ?>
                                        </strong>
                                        <span class="pill <?= $proj['status'] === 'active' ? 'pill-success' : 'pill-neutral' ?>" style="font-size: 11px;">
                                            <?= htmlspecialchars($proj['status']) ?>
                                        </span>
                                    </div>
                                    <p class="body-sm" style="font-size: 12px; margin: 4px 0 10px 0;">
                                        Lead: <?= htmlspecialchars($proj['manager_name'] ?? 'Sarah Chen') ?>
                                    </p>
                                    <a href="<?= BASE_URL ?>/projects/view.php?id=<?= $proj['id'] ?>" class="btn btn-secondary btn-sm" style="width: 100%; text-align: center;">
                                        View Project Tasks
                                    </a>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="body-sm" style="color: var(--text-muted); text-align: center; padding: 20px 0;">
                            You have not been assigned to any projects yet.
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
