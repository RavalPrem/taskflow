<?php
$page_title = 'Task Board';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Filter parameters
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$valid_statuses = ['all', 'todo', 'in_progress', 'in_review', 'done'];
if (!in_array($status_filter, $valid_statuses, true)) {
    $status_filter = 'all';
}

$project_filter = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$search = trim($_GET['search'] ?? '');

// Fetch available projects for dropdown filter
$available_projects = [];
if ($user_role === 'admin') {
    $p_res = mysqli_query($conn, "SELECT id, name FROM projects ORDER BY name ASC");
} elseif ($user_role === 'project_manager') {
    $p_stmt = mysqli_prepare($conn, "SELECT id, name FROM projects WHERE manager_id = ? ORDER BY name ASC");
    mysqli_stmt_bind_param($p_stmt, "i", $user_id);
    mysqli_stmt_execute($p_stmt);
    $p_res = mysqli_stmt_get_result($p_stmt);
} else {
    $p_stmt = mysqli_prepare($conn, "
        SELECT DISTINCT p.id, p.name 
        FROM projects p
        LEFT JOIN project_members pm ON p.id = pm.project_id
        LEFT JOIN tasks t ON p.id = t.project_id
        WHERE pm.user_id = ? OR t.assigned_to = ?
        ORDER BY p.name ASC
    ");
    mysqli_stmt_bind_param($p_stmt, "ii", $user_id, $user_id);
    mysqli_stmt_execute($p_stmt);
    $p_res = mysqli_stmt_get_result($p_stmt);
}
if ($p_res) {
    while ($p = mysqli_fetch_assoc($p_res)) {
        $available_projects[] = $p;
    }
}

// Compute counts for filter tabs (scoped to user's permissions)
$count_where = [];
$count_params = [];
$count_types = "";

if ($user_role === 'project_manager') {
    $count_where[] = "p.manager_id = ?";
    $count_params[] = $user_id;
    $count_types .= "i";
} elseif ($user_role === 'team_member') {
    $count_where[] = "(t.assigned_to = ? OR pm.user_id = ?)";
    $count_params[] = $user_id;
    $count_params[] = $user_id;
    $count_types .= "ii";
}

if ($project_filter > 0) {
    $count_where[] = "t.project_id = ?";
    $count_params[] = $project_filter;
    $count_types .= "i";
}

$count_where_sql = !empty($count_where) ? "WHERE " . implode(" AND ", $count_where) : "";

$status_counts = [
    'all' => 0,
    'todo' => 0,
    'in_progress' => 0,
    'in_review' => 0,
    'done' => 0
];

$count_query = "
    SELECT t.status, COUNT(DISTINCT t.id) AS cnt
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    LEFT JOIN project_members pm ON p.id = pm.project_id
    {$count_where_sql}
    GROUP BY t.status
";
$cnt_stmt = mysqli_prepare($conn, $count_query);
if (!empty($count_params)) {
    mysqli_stmt_bind_param($cnt_stmt, $count_types, ...$count_params);
}
if ($cnt_stmt && mysqli_stmt_execute($cnt_stmt)) {
    $cnt_res = mysqli_stmt_get_result($cnt_stmt);
    while ($crow = mysqli_fetch_assoc($cnt_res)) {
        if (isset($status_counts[$crow['status']])) {
            $status_counts[$crow['status']] = (int)$crow['cnt'];
            $status_counts['all'] += (int)$crow['cnt'];
        }
    }
    mysqli_stmt_close($cnt_stmt);
}

// Build Main Task List Query
$where_clauses = [];
$params = [];
$types = "";

if ($user_role === 'project_manager') {
    $where_clauses[] = "p.manager_id = ?";
    $params[] = $user_id;
    $types .= "i";
} elseif ($user_role === 'team_member') {
    $where_clauses[] = "(t.assigned_to = ? OR pm.user_id = ?)";
    $params[] = $user_id;
    $params[] = $user_id;
    $types .= "ii";
}

if ($project_filter > 0) {
    $where_clauses[] = "t.project_id = ?";
    $params[] = $project_filter;
    $types .= "i";
}

if ($status_filter !== 'all') {
    $where_clauses[] = "t.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($search !== '') {
    $where_clauses[] = "(t.title LIKE ? OR t.description LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= "ss";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

$query = "
    SELECT DISTINCT t.id, t.title, t.description, t.priority, t.status, t.due_date, t.assigned_to, t.project_id,
           p.name AS project_name, p.status AS project_status, p.manager_id,
           u.full_name AS assigned_name,
           mgr.full_name AS manager_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    LEFT JOIN users mgr ON p.manager_id = mgr.id
    LEFT JOIN users u ON t.assigned_to = u.id
    LEFT JOIN project_members pm ON p.id = pm.project_id
    {$where_sql}
    ORDER BY p.name ASC, t.created_at DESC
";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

// Group tasks into project boxes
$projects_boxes = [];
$total_matching_tasks = 0;

while ($row = mysqli_fetch_assoc($res)) {
    $total_matching_tasks++;
    $pid = $row['project_id'];
    if (!isset($projects_boxes[$pid])) {
        $projects_boxes[$pid] = [
            'id' => $pid,
            'name' => $row['project_name'],
            'status' => $row['project_status'],
            'manager_id' => $row['manager_id'],
            'manager_name' => $row['manager_name'],
            'tasks' => []
        ];
    }
    $projects_boxes[$pid]['tasks'][] = $row;
}
mysqli_stmt_close($stmt);

// Helper formatting functions
function get_status_badge(string $s): string {
    switch ($s) {
        case 'todo':
            return '<span class="pill pill-info">To Do</span>';
        case 'in_progress':
            return '<span class="pill pill-warning">In Progress</span>';
        case 'in_review':
            return '<span class="pill pill-danger">In Review</span>';
        case 'done':
            return '<span class="pill pill-success">Done</span>';
        default:
            return '<span class="pill">' . htmlspecialchars(ucwords(str_replace('_', ' ', $s))) . '</span>';
    }
}

function get_priority_badge(string $p): string {
    switch ($p) {
        case 'low':
            return '<span class="pill pill-info">Low</span>';
        case 'medium':
            return '<span class="pill pill-warning">Medium</span>';
        case 'high':
            return '<span class="pill pill-danger">High</span>';
        case 'urgent':
            return '<span class="pill pill-danger" style="background-color: #FEE2E2; color: #991B1B; font-weight:700;">Urgent</span>';
        default:
            return '<span class="pill">' . htmlspecialchars(ucfirst($p)) . '</span>';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding-top: 32px; padding-bottom: 60px;">

    <!-- Flash Notifications -->
    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success" style="margin-bottom: 20px;">
            <div class="flex items-center gap-sm">
                <?= tf_icon('check', 18) ?>
                <span><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
            </div>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger" style="margin-bottom: 20px;">
            <div class="flex items-center gap-sm">
                <?= tf_icon('error', 18) ?>
                <span><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
            </div>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <!-- Page Header & Action Controls -->
    <div class="page-header" style="margin-bottom: 24px;">
        <div>
            <span class="eyebrow">WORKFLOW & EXECUTION</span>
            <h1 class="headline-lg">Task Board</h1>
            <p class="body-sm">Organized by project. Filter by sprint status, view details, and change progress with the confirm button.</p>
        </div>

        <div class="flex items-center gap-sm" style="flex-wrap: wrap;">
            <?php if ($user_role === 'admin' || $user_role === 'project_manager'): ?>
                <a href="<?= BASE_URL ?>/tasks/create.php<?= $project_filter > 0 ? '?project_id=' . $project_filter : '' ?>" class="btn btn-primary btn-sm flex items-center gap-xs">
                    <?= tf_icon('add', 16) ?>
                    <span>New Task</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filter Control Bar -->
    <div class="card" style="padding: 16px 20px; margin-bottom: 28px; background: #FFFFFF; border: 1px solid var(--border);">
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px;">
            
            <!-- Status Filter Tabs / Pills -->
            <div class="flex items-center gap-xs" style="flex-wrap: wrap;">
                <span class="body-sm" style="font-weight: 600; color: var(--text-secondary); margin-right: 4px;">Status:</span>
                
                <a href="<?= BASE_URL ?>/tasks/board.php?status=all&project_id=<?= $project_filter ?>&search=<?= urlencode($search) ?>" 
                   class="btn <?= $status_filter === 'all' ? 'btn-primary' : 'btn-secondary' ?> btn-sm" style="height: 32px; padding: 0 12px; font-size: 13px;">
                    All (<?= $status_counts['all'] ?>)
                </a>

                <a href="<?= BASE_URL ?>/tasks/board.php?status=todo&project_id=<?= $project_filter ?>&search=<?= urlencode($search) ?>" 
                   class="btn <?= $status_filter === 'todo' ? 'btn-primary' : 'btn-secondary' ?> btn-sm" style="height: 32px; padding: 0 12px; font-size: 13px;">
                    To Do (<?= $status_counts['todo'] ?>)
                </a>

                <a href="<?= BASE_URL ?>/tasks/board.php?status=in_progress&project_id=<?= $project_filter ?>&search=<?= urlencode($search) ?>" 
                   class="btn <?= $status_filter === 'in_progress' ? 'btn-primary' : 'btn-secondary' ?> btn-sm" style="height: 32px; padding: 0 12px; font-size: 13px;">
                    In Progress (<?= $status_counts['in_progress'] ?>)
                </a>

                <a href="<?= BASE_URL ?>/tasks/board.php?status=in_review&project_id=<?= $project_filter ?>&search=<?= urlencode($search) ?>" 
                   class="btn <?= $status_filter === 'in_review' ? 'btn-primary' : 'btn-secondary' ?> btn-sm" style="height: 32px; padding: 0 12px; font-size: 13px;">
                    In Review (<?= $status_counts['in_review'] ?>)
                </a>

                <a href="<?= BASE_URL ?>/tasks/board.php?status=done&project_id=<?= $project_filter ?>&search=<?= urlencode($search) ?>" 
                   class="btn <?= $status_filter === 'done' ? 'btn-primary' : 'btn-secondary' ?> btn-sm" style="height: 32px; padding: 0 12px; font-size: 13px;">
                    Done (<?= $status_counts['done'] ?>)
                </a>
            </div>

            <!-- Project Dropdown & Search Filter Form -->
            <form method="GET" action="<?= BASE_URL ?>/tasks/board.php" class="flex items-center gap-xs" style="flex-wrap: wrap;">
                <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                
                <!-- Project Selector -->
                <select name="project_id" class="form-control" onchange="this.form.submit()" style="height: 34px; font-size: 13px; min-width: 170px; padding: 0 10px;">
                    <option value="0">All Projects</option>
                    <?php foreach ($available_projects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $project_filter === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Keyword Search -->
                <input type="text" name="search" class="form-control" placeholder="Search tasks..." value="<?= htmlspecialchars($search) ?>" style="height: 34px; font-size: 13px; width: 150px; padding: 0 10px;">
                
                <button type="submit" class="btn btn-secondary btn-sm" style="height: 34px; padding: 0 10px;">
                    <?= tf_icon('search', 15) ?>
                </button>

                <?php if ($project_filter > 0 || $status_filter !== 'all' || $search !== ''): ?>
                    <a href="<?= BASE_URL ?>/tasks/board.php" class="btn btn-secondary btn-sm" title="Clear All Filters" style="height: 34px; padding: 0 10px; color: var(--danger-text);">
                        <?= tf_icon('close', 15) ?>
                    </a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Project-Divided List Boxes -->
    <?php if (empty($projects_boxes)): ?>
        <div class="card" style="text-align: center; padding: 56px 20px; background: #FFFFFF;">
            <div style="color: var(--text-muted); margin-bottom: 12px;">
                <?= tf_icon('tasks', 48) ?>
            </div>
            <h3 class="headline-sm" style="margin-bottom: 6px;">No Tasks Found</h3>
            <p class="body-sm" style="color: var(--text-secondary); max-width: 440px; margin: 0 auto 20px auto;">
                <?php if ($status_filter !== 'all' || $project_filter > 0 || $search !== ''): ?>
                    No tasks match the active filters. Try clearing filters or switching status views.
                <?php else: ?>
                    No tasks are currently assigned or active in your projects.
                <?php endif; ?>
            </p>
            <div class="flex items-center justify-center gap-sm">
                <?php if ($status_filter !== 'all' || $project_filter > 0 || $search !== ''): ?>
                    <a href="<?= BASE_URL ?>/tasks/board.php" class="btn btn-secondary btn-sm">Reset Filters</a>
                <?php endif; ?>
                <?php if ($user_role === 'admin' || $user_role === 'project_manager'): ?>
                    <a href="<?= BASE_URL ?>/tasks/create.php" class="btn btn-primary btn-sm">Create New Task</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>

        <?php foreach ($projects_boxes as $box): ?>
            <div class="card" style="margin-bottom: 28px; padding: 0; overflow: hidden; border: 1px solid var(--border); box-shadow: var(--shadow-sm); background: #FFFFFF; border-radius: 12px;">
                
                <!-- Project Box Header -->
                <div style="background-color: var(--surface-alt); padding: 14px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                    <div class="flex items-center gap-sm">
                        <div style="width: 32px; height: 32px; border-radius: 8px; background: #EEF2FF; color: var(--accent); display: flex; align-items: center; justify-content: center;">
                            <?= tf_icon('folder', 18) ?>
                        </div>
                        <div>
                            <div class="flex items-center gap-xs">
                                <a href="<?= BASE_URL ?>/projects/view.php?id=<?= $box['id'] ?>" style="font-weight: 700; font-size: 16px; color: var(--text-primary); text-decoration: none;">
                                    <?= htmlspecialchars($box['name']) ?>
                                </a>
                                <span class="pill pill-<?= $box['status'] === 'active' ? 'success' : ($box['status'] === 'completed' ? 'info' : 'warning') ?>" style="font-size: 11px; padding: 1px 8px;">
                                    <?= ucfirst($box['status']) ?>
                                </span>
                            </div>
                            <?php if (!empty($box['manager_name'])): ?>
                                <span class="body-xs" style="color: var(--text-secondary);">
                                    Manager: <?= htmlspecialchars($box['manager_name']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flex items-center gap-sm">
                        <span class="pill" style="font-size: 12px; font-weight: 600; background: #FFFFFF; border: 1px solid var(--border);">
                            <?= count($box['tasks']) ?> <?= count($box['tasks']) === 1 ? 'Task' : 'Tasks' ?>
                        </span>
                        <a href="<?= BASE_URL ?>/projects/view.php?id=<?= $box['id'] ?>" class="btn btn-secondary btn-sm" style="height: 30px; font-size: 12px; padding: 0 10px;">
                            View Project
                        </a>
                    </div>
                </div>

                <!-- Project Tasks List Table -->
                <div class="table-container" style="border: none; border-radius: 0; margin: 0;">
                    <table class="table" style="width: 100%; margin: 0;">
                        <thead>
                            <tr style="background: #FAFAFA;">
                                <th style="width: 28%; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); padding: 10px 16px;">Work Name</th>
                                <th style="width: 18%; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); padding: 10px 14px;">Project Name</th>
                                <th style="width: 13%; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); padding: 10px 14px;">Status</th>
                                <th style="width: 11%; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); padding: 10px 14px;">Priority</th>
                                <th style="width: 20%; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); padding: 10px 14px;">Change Status</th>
                                <th style="width: 10%; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-secondary); padding: 10px 16px; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($box['tasks'] as $t): ?>
                                <?php
                                $can_update = false;
                                if ($user_role === 'admin') {
                                    $can_update = true;
                                } elseif ($user_role === 'project_manager' && (int)$box['manager_id'] === $user_id) {
                                    $can_update = true;
                                } elseif ($user_role === 'team_member' && (int)$t['assigned_to'] === $user_id) {
                                    $can_update = true;
                                }
                                ?>
                                <tr style="border-bottom: 1px solid var(--border);">
                                    <!-- 1. Work Name -->
                                    <td style="padding: 12px 16px; vertical-align: middle;">
                                        <div style="display: flex; flex-direction: column;">
                                            <a href="<?= BASE_URL ?>/tasks/view.php?id=<?= $t['id'] ?>" style="font-weight: 600; font-size: 14px; color: var(--text-primary); text-decoration: none;">
                                                <?= htmlspecialchars($t['title']) ?>
                                            </a>
                                            <div class="flex items-center gap-xs" style="margin-top: 4px; font-size: 12px; color: var(--text-secondary);">
                                                <?php if (!empty($t['assigned_name'])): ?>
                                                    <span>Assignee: <strong><?= htmlspecialchars($t['assigned_name']) ?></strong></span>
                                                <?php else: ?>
                                                    <span style="font-style: italic; color: var(--text-muted);">Unassigned</span>
                                                <?php endif; ?>
                                                <?php if (!empty($t['due_date'])): ?>
                                                    <span>&bull; Due <?= date('M j', strtotime($t['due_date'])) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 2. Project Name -->
                                    <td style="padding: 12px 14px; vertical-align: middle;">
                                        <span style="font-weight: 500; font-size: 13px; color: var(--text-secondary);">
                                            <?= htmlspecialchars($box['name']) ?>
                                        </span>
                                    </td>

                                    <!-- 3. Status -->
                                    <td style="padding: 12px 14px; vertical-align: middle;">
                                        <?= get_status_badge($t['status']) ?>
                                    </td>

                                    <!-- 4. Priority -->
                                    <td style="padding: 12px 14px; vertical-align: middle;">
                                        <?= get_priority_badge($t['priority']) ?>
                                    </td>

                                    <!-- 5. Change Status Dropdown + Confirm Button -->
                                    <td style="padding: 12px 14px; vertical-align: middle;">
                                        <?php if ($can_update): ?>
                                            <form method="POST" action="<?= BASE_URL ?>/tasks/update_status.php" style="display: inline-flex; align-items: center; gap: 6px;">
                                                <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                                <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">

                                                <select name="status" class="kanban-status-select" style="height: 32px; font-size: 12px; border-radius: 6px; padding: 0 6px; border: 1px solid var(--border); background-color: #FFFFFF; min-width: 115px;">
                                                    <option value="todo" <?= $t['status'] === 'todo' ? 'selected' : '' ?>>To Do</option>
                                                    <option value="in_progress" <?= $t['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                                    <option value="in_review" <?= $t['status'] === 'in_review' ? 'selected' : '' ?>>In Review</option>
                                                    
                                                    <?php if ($user_role === 'admin' || $user_role === 'project_manager'): ?>
                                                        <option value="done" <?= $t['status'] === 'done' ? 'selected' : '' ?>>Done</option>
                                                    <?php else: ?>
                                                        <?php if ($t['status'] === 'done'): ?>
                                                            <option value="done" selected>Done</option>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </select>

                                                <button type="submit" class="btn-confirm" title="Apply status change">
                                                    Confirm
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="body-xs" style="color: var(--text-muted); font-style: italic;">
                                                Read only
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- 6. Action Link -->
                                    <td style="padding: 12px 16px; vertical-align: middle; text-align: right;">
                                        <a href="<?= BASE_URL ?>/tasks/view.php?id=<?= $t['id'] ?>" class="btn btn-secondary btn-sm" style="height: 28px; font-size: 12px; padding: 0 10px;">
                                            Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        <?php endforeach; ?>

    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
