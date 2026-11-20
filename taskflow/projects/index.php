<?php
$page_title = 'Projects';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/header.php';

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Filter by status if provided in query string
$status_filter = trim($_GET['status'] ?? '');
$allowed_statuses = ['active', 'completed', 'on_hold'];
$where_status = '';
if (in_array($status_filter, $allowed_statuses, true)) {
    $where_status = " AND p.status = '" . mysqli_real_escape_string($conn, $status_filter) . "'";
}

// Scoped query per role
if ($user_role === 'admin') {
    $query = "
        SELECT p.id, p.name, p.description, p.status, p.created_at, p.manager_id,
               u.full_name AS manager_name,
               COUNT(t.id) AS total_tasks,
               SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END) AS done_tasks
        FROM projects p
        LEFT JOIN users u ON p.manager_id = u.id
        LEFT JOIN tasks t ON p.id = t.project_id
        WHERE 1=1 {$where_status}
        GROUP BY p.id, p.name, p.description, p.status, p.created_at, p.manager_id, u.full_name
        ORDER BY p.created_at DESC
    ";
    $result = mysqli_query($conn, $query);

} elseif ($user_role === 'project_manager') {
    $query = "
        SELECT p.id, p.name, p.description, p.status, p.created_at, p.manager_id,
               u.full_name AS manager_name,
               COUNT(t.id) AS total_tasks,
               SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END) AS done_tasks
        FROM projects p
        LEFT JOIN users u ON p.manager_id = u.id
        LEFT JOIN tasks t ON p.id = t.project_id
        WHERE p.manager_id = ? {$where_status}
        GROUP BY p.id, p.name, p.description, p.status, p.created_at, p.manager_id, u.full_name
        ORDER BY p.created_at DESC
    ";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

} else {
    // Team member sees projects they belong to
    $query = "
        SELECT p.id, p.name, p.description, p.status, p.created_at, p.manager_id,
               u.full_name AS manager_name,
               COUNT(t.id) AS total_tasks,
               SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END) AS done_tasks
        FROM project_members pm
        JOIN projects p ON pm.project_id = p.id
        LEFT JOIN users u ON p.manager_id = u.id
        LEFT JOIN tasks t ON p.id = t.project_id
        WHERE pm.user_id = ? {$where_status}
        GROUP BY p.id, p.name, p.description, p.status, p.created_at, p.manager_id, u.full_name
        ORDER BY p.created_at DESC
    ";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}

// Fetch all project members for avatar display
$members_map = [];
$mem_sql = "
    SELECT pm.project_id, u.id, u.full_name
    FROM project_members pm
    JOIN users u ON pm.user_id = u.id
    ORDER BY u.full_name ASC
";
$mem_res = mysqli_query($conn, $mem_sql);
if ($mem_res) {
    while ($m = mysqli_fetch_assoc($mem_res)) {
        $members_map[$m['project_id']][] = $m;
    }
}
?>

<div class="container" style="padding-top: 32px;">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <span class="eyebrow">PORTFOLIO</span>
            <h1 class="headline-lg">
                <?= $user_role === 'team_member' ? 'My Projects' : 'Projects' ?>
            </h1>
            <p class="body-sm">
                <?php if ($user_role === 'admin'): ?>
                    All organization projects, sprint deliverables, and team assignments.
                <?php elseif ($user_role === 'project_manager'): ?>
                    Projects managed by you. Track deliverables and review submitted tasks.
                <?php else: ?>
                    Projects where you are actively assigned as a contributing team member.
                <?php endif; ?>
            </p>
        </div>

        <div class="flex items-center gap-sm">
            <?php if ($user_role === 'admin' || $user_role === 'project_manager'): ?>
                <a href="<?= BASE_URL ?>/projects/create.php" class="btn btn-primary btn-sm">
                    <?= tf_icon('add', '', 16) ?>
                    <span>New Project</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status Filter Pills -->
    <div class="flex items-center gap-xs mb-lg" style="flex-wrap: wrap;">
        <a href="<?= BASE_URL ?>/projects/index.php" 
           class="pill <?= empty($status_filter) ? 'pill-neutral' : '' ?>" 
           style="text-decoration: none; padding: 6px 14px; font-weight: <?= empty($status_filter) ? '700' : '500' ?>; background: <?= empty($status_filter) ? 'var(--primary); color: #fff;' : 'var(--surface); border: 1px solid var(--border); color: var(--text-secondary);' ?>">
            All Projects
        </a>
        <a href="<?= BASE_URL ?>/projects/index.php?status=active" 
           class="pill" 
           style="text-decoration: none; padding: 6px 14px; font-weight: <?= $status_filter === 'active' ? '700' : '500' ?>; background: <?= $status_filter === 'active' ? 'var(--primary); color: #fff;' : 'var(--surface); border: 1px solid var(--border); color: var(--text-secondary);' ?>">
            Active
        </a>
        <a href="<?= BASE_URL ?>/projects/index.php?status=on_hold" 
           class="pill" 
           style="text-decoration: none; padding: 6px 14px; font-weight: <?= $status_filter === 'on_hold' ? '700' : '500' ?>; background: <?= $status_filter === 'on_hold' ? 'var(--primary); color: #fff;' : 'var(--surface); border: 1px solid var(--border); color: var(--text-secondary);' ?>">
            On Hold
        </a>
        <a href="<?= BASE_URL ?>/projects/index.php?status=completed" 
           class="pill" 
           style="text-decoration: none; padding: 6px 14px; font-weight: <?= $status_filter === 'completed' ? '700' : '500' ?>; background: <?= $status_filter === 'completed' ? 'var(--primary); color: #fff;' : 'var(--surface); border: 1px solid var(--border); color: var(--text-secondary);' ?>">
            Completed
        </a>
    </div>

    <!-- Projects Grid -->
    <?php if ($result && mysqli_num_rows($result) > 0): ?>
        <div class="grid grid-cols-3" style="gap: 24px;">
            <?php while ($proj = mysqli_fetch_assoc($result)): 
                $pid = (int)$proj['id'];
                $total_t = (int)$proj['total_tasks'];
                $done_t = (int)$proj['done_tasks'];
                $pct = $total_t > 0 ? round(($done_t / $total_t) * 100) : 0;
                $can_edit = ($user_role === 'admin' || ($user_role === 'project_manager' && (int)$proj['manager_id'] === $user_id));
                $proj_members = $members_map[$pid] ?? [];
            ?>
                <div class="card card-hover flex flex-col justify-between" style="padding: 24px;">
                    <div>
                        <!-- Header with Status Pill -->
                        <div class="flex items-center justify-between mb-sm">
                            <span class="pill <?= $proj['status'] === 'active' ? 'pill-success' : ($proj['status'] === 'completed' ? 'pill-neutral' : 'pill-warning') ?>">
                                <?= htmlspecialchars($proj['status']) ?>
                            </span>
                            <span class="body-sm" style="font-size: 11px;">
                                Created <?= date('M Y', strtotime($proj['created_at'])) ?>
                            </span>
                        </div>

                        <!-- Project Title & Description -->
                        <h2 class="headline-sm" style="margin-bottom: 8px;">
                            <a href="<?= BASE_URL ?>/projects/view.php?id=<?= $pid ?>" style="color: var(--text-primary); text-decoration: none;">
                                <?= htmlspecialchars($proj['name']) ?>
                            </a>
                        </h2>
                        <p class="body-sm" style="line-height: 1.5; color: var(--text-secondary); margin-bottom: 20px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                            <?= htmlspecialchars($proj['description'] ?: 'No description provided.') ?>
                        </p>

                        <!-- Project Manager Info -->
                        <div class="flex items-center gap-xs mb-md">
                            <?= tf_icon('person', '', 14) ?>
                            <span class="body-sm" style="font-size: 12px; color: var(--text-secondary);">
                                Manager: <strong><?= htmlspecialchars($proj['manager_name'] ?? 'Unassigned') ?></strong>
                            </span>
                        </div>
                    </div>

                    <div>
                        <!-- Progress Bar -->
                        <div style="margin-bottom: 16px;">
                            <div class="flex items-center justify-between mb-xs">
                                <span class="label-sm">Progress</span>
                                <span style="font-size: 12px; font-weight: 700; color: var(--text-primary);"><?= $pct ?>%</span>
                            </div>
                            <div style="height: 6px; width: 100%; background: var(--surface-muted); border-radius: 9999px; overflow: hidden;">
                                <div style="height: 100%; width: <?= $pct ?>%; background: var(--primary);"></div>
                            </div>
                            <div class="body-sm" style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                                <?= $done_t ?> of <?= $total_t ?> tasks marked done
                            </div>
                        </div>

                        <!-- Assigned Team Members Avatars -->
                        <div class="flex items-center justify-between pt-sm" style="border-top: 1px solid var(--border);">
                            <div class="flex items-center" style="margin-left: 6px;">
                                <?php if (!empty($proj_members)): ?>
                                    <?php foreach (array_slice($proj_members, 0, 4) as $idx => $mem): ?>
                                        <div class="user-avatar" style="width: 26px; height: 26px; font-size: 11px; margin-left: -6px; border: 2px solid #FFFFFF; background: <?= $idx % 2 === 0 ? 'var(--primary)' : 'var(--accent)' ?>;" title="<?= htmlspecialchars($mem['full_name']) ?>">
                                            <?= strtoupper(substr($mem['full_name'], 0, 1)) ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($proj_members) > 4): ?>
                                        <div class="user-avatar" style="width: 26px; height: 26px; font-size: 10px; margin-left: -6px; border: 2px solid #FFFFFF; background: var(--border-strong); color: var(--text-primary);">
                                            +<?= count($proj_members) - 4 ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="body-sm" style="font-size: 11px; color: var(--text-muted);">No assigned members</span>
                                <?php endif; ?>
                            </div>

                            <!-- Card Action Buttons -->
                            <div class="flex items-center gap-xs">
                                <a href="<?= BASE_URL ?>/projects/view.php?id=<?= $pid ?>" class="btn btn-secondary btn-sm" title="Open Task Board">
                                    <span>Board</span>
                                    <?= tf_icon('arrow_forward', '', 14) ?>
                                </a>
                                <?php if ($can_edit): ?>
                                    <a href="<?= BASE_URL ?>/projects/edit.php?id=<?= $pid ?>" class="btn btn-ghost btn-sm" title="Edit Project Settings">
                                        <?= tf_icon('settings', '', 16) ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="card" style="text-align: center; padding: 60px 24px;">
            <div style="margin-bottom: 12px; color: var(--text-muted);"><?= tf_icon('folder_off', '', 40) ?></div>
            <h2 class="headline-sm" style="margin-bottom: 6px;">No Projects Found</h2>
            <p class="body-sm" style="max-width: 440px; margin: 0 auto 20px auto;">
                <?= !empty($status_filter) ? 'There are no projects matching the selected status filter.' : 'There are currently no projects available in your workspace.' ?>
            </p>
            <?php if ($user_role === 'admin' || $user_role === 'project_manager'): ?>
                <a href="<?= BASE_URL ?>/projects/create.php" class="btn btn-primary btn-sm">Create New Project</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
