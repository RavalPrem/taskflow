<?php
$page_title = 'System Administration';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/require_role.php';

require_role(['admin']);

// 1. Stats: Users Breakdown
$user_stats = ['total' => 0, 'admin' => 0, 'project_manager' => 0, 'team_member' => 0, 'active' => 0, 'disabled' => 0];
$ur = mysqli_query($conn, "SELECT role, status, COUNT(*) AS cnt FROM users GROUP BY role, status");
if ($ur) {
    while ($row = mysqli_fetch_assoc($ur)) {
        $user_stats['total'] += (int)$row['cnt'];
        if (isset($user_stats[$row['role']])) {
            $user_stats[$row['role']] += (int)$row['cnt'];
        }
        if (isset($user_stats[$row['status']])) {
            $user_stats[$row['status']] += (int)$row['cnt'];
        }
    }
}

// 2. Stats: Projects Breakdown
$proj_stats = ['total' => 0, 'active' => 0, 'completed' => 0, 'on_hold' => 0];
$pr = mysqli_query($conn, "SELECT status, COUNT(*) AS cnt FROM projects GROUP BY status");
if ($pr) {
    while ($row = mysqli_fetch_assoc($pr)) {
        $proj_stats['total'] += (int)$row['cnt'];
        if (isset($proj_stats[$row['status']])) {
            $proj_stats[$row['status']] += (int)$row['cnt'];
        }
    }
}

// 3. Stats: Tasks Breakdown
$task_stats = ['total' => 0, 'todo' => 0, 'in_progress' => 0, 'in_review' => 0, 'done' => 0];
$tr = mysqli_query($conn, "SELECT status, COUNT(*) AS cnt FROM tasks GROUP BY status");
if ($tr) {
    while ($row = mysqli_fetch_assoc($tr)) {
        $task_stats['total'] += (int)$row['cnt'];
        if (isset($task_stats[$row['status']])) {
            $task_stats[$row['status']] += (int)$row['cnt'];
        }
    }
}

// 4. Stats: Activity Log Count Today
$today_count = 0;
$today_res = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM activity_log WHERE DATE(created_at) = CURDATE()");
if ($today_res) {
    $row = mysqli_fetch_assoc($today_res);
    $today_count = (int)$row['cnt'];
}

// 5. Audit Log Filtering & Pagination
$search = trim($_GET['search'] ?? '');
$per_page = 20;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $per_page;

$where_clauses = [];
$params = [];
$types = "";

if ($search !== '') {
    $where_clauses[] = "(al.action LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

$where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Count total log rows
$count_query = "
    SELECT COUNT(*) 
    FROM activity_log al 
    LEFT JOIN users u ON al.user_id = u.id 
    {$where_sql}
";
$c_stmt = mysqli_prepare($conn, $count_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($c_stmt, $types, ...$params);
}
mysqli_stmt_execute($c_stmt);
mysqli_stmt_bind_result($c_stmt, $total_log_rows);
mysqli_stmt_fetch($c_stmt);
mysqli_stmt_close($c_stmt);
$total_pages = max(1, (int)ceil($total_log_rows / $per_page));

// Fetch audit log entries
$log_query = "
    SELECT al.id, al.action, al.created_at, al.user_id,
           u.full_name, u.email, u.role
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.id
    {$where_sql}
    ORDER BY al.created_at DESC
    LIMIT ? OFFSET ?
";
$l_stmt = mysqli_prepare($conn, $log_query);
$l_types = $types . "ii";
$l_params = array_merge($params, [$per_page, $offset]);
mysqli_stmt_bind_param($l_stmt, $l_types, ...$l_params);
mysqli_stmt_execute($l_stmt);
$logs_res = mysqli_stmt_get_result($l_stmt);

function log_paginate_link(array $extra = []): string {
    $params = array_merge($_GET, $extra);
    return '?' . http_build_query($params);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding-top: 32px; padding-bottom: 60px;">

    <!-- Page Header -->
    <div class="page-header" style="margin-bottom: 24px;">
        <div>
            <span class="eyebrow">SYSTEM GOVERNANCE</span>
            <h1 class="headline-lg">System Administration</h1>
            <p class="body-sm">Global system health metrics, entity distribution, and immutable event audit log.</p>
        </div>

        <div class="flex items-center gap-sm">
            <a href="<?= BASE_URL ?>/admin/manage_users.php" class="btn btn-primary btn-sm flex items-center gap-xs">
                <?= tf_icon('users', 16) ?>
                <span>Manage Users</span>
            </a>
        </div>
    </div>

    <!-- 4 High-Level System Metric Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 32px;">
        
        <!-- Total Users Card -->
        <div class="card" style="padding: 20px; background: #FFFFFF; border: 1px solid var(--border); border-radius: 12px;">
            <div class="flex items-center justify-between" style="margin-bottom: 10px;">
                <span class="body-xs" style="font-weight: 700; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em;">Users Registered</span>
                <span style="color: var(--accent);"><?= tf_icon('users', 20) ?></span>
            </div>
            <div style="font-size: 32px; font-weight: 800; color: var(--text-primary); line-height: 1.1; margin-bottom: 8px;">
                <?= $user_stats['total'] ?>
            </div>
            <div class="body-xs" style="color: var(--text-secondary);">
                <?= $user_stats['admin'] ?> Admin &bull; <?= $user_stats['project_manager'] ?> PM &bull; <?= $user_stats['team_member'] ?> Member
            </div>
        </div>

        <!-- Total Projects Card -->
        <div class="card" style="padding: 20px; background: #FFFFFF; border: 1px solid var(--border); border-radius: 12px;">
            <div class="flex items-center justify-between" style="margin-bottom: 10px;">
                <span class="body-xs" style="font-weight: 700; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em;">Active Projects</span>
                <span style="color: #2563EB;"><?= tf_icon('folder', 20) ?></span>
            </div>
            <div style="font-size: 32px; font-weight: 800; color: var(--text-primary); line-height: 1.1; margin-bottom: 8px;">
                <?= $proj_stats['total'] ?>
            </div>
            <div class="body-xs" style="color: var(--text-secondary);">
                <?= $proj_stats['active'] ?> Active &bull; <?= $proj_stats['completed'] ?> Done &bull; <?= $proj_stats['on_hold'] ?> On Hold
            </div>
        </div>

        <!-- Total Tasks Card -->
        <div class="card" style="padding: 20px; background: #FFFFFF; border: 1px solid var(--border); border-radius: 12px;">
            <div class="flex items-center justify-between" style="margin-bottom: 10px;">
                <span class="body-xs" style="font-weight: 700; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em;">Total Tasks</span>
                <span style="color: #059669;"><?= tf_icon('tasks', 20) ?></span>
            </div>
            <div style="font-size: 32px; font-weight: 800; color: var(--text-primary); line-height: 1.1; margin-bottom: 8px;">
                <?= $task_stats['total'] ?>
            </div>
            <div class="body-xs" style="color: var(--text-secondary);">
                <?= $task_stats['in_progress'] ?> In Progress &bull; <?= $task_stats['in_review'] ?> Review &bull; <?= $task_stats['done'] ?> Done
            </div>
        </div>

        <!-- Today Activity Card -->
        <div class="card" style="padding: 20px; background: #FFFFFF; border: 1px solid var(--border); border-radius: 12px;">
            <div class="flex items-center justify-between" style="margin-bottom: 10px;">
                <span class="body-xs" style="font-weight: 700; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em;">Events Today</span>
                <span style="color: #D97706;"><?= tf_icon('activity', 20) ?></span>
            </div>
            <div style="font-size: 32px; font-weight: 800; color: var(--text-primary); line-height: 1.1; margin-bottom: 8px;">
                <?= $today_count ?>
            </div>
            <div class="body-xs" style="color: var(--text-secondary);">
                <?= $total_log_rows ?> Lifetime audit entries
            </div>
        </div>

    </div>

    <!-- Audit Log Section Header & Filter Form -->
    <div class="card" style="padding: 16px 20px; margin-bottom: 20px; background: #FFFFFF; border: 1px solid var(--border);">
        <form method="GET" action="" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
            <div>
                <h3 class="headline-sm" style="font-size: 16px; margin: 0;">System Audit Log</h3>
                <span class="body-xs" style="color: var(--text-secondary);">Chronological trace of mutations and user sessions</span>
            </div>

            <div style="display: flex; gap: 10px; align-items: center;">
                <input type="text" name="search" class="form-control" placeholder="Search by action or user..." 
                       value="<?= htmlspecialchars($search) ?>" style="height: 36px; font-size: 13px; min-width: 240px; padding: 0 10px;">

                <button type="submit" class="btn btn-primary btn-sm flex items-center gap-xs" style="height: 36px; padding: 0 14px;">
                    <?= tf_icon('search', 15) ?>
                    <span>Search</span>
                </button>

                <?php if ($search !== ''): ?>
                    <a href="<?= BASE_URL ?>/admin/system_admin.php" class="btn btn-secondary btn-sm" style="height: 36px; padding: 0 12px; color: var(--danger-text);">
                        Clear
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Audit Log Table -->
    <div class="card" style="padding: 0; overflow: hidden; background: #FFFFFF; border: 1px solid var(--border); border-radius: 12px;">
        <div class="table-container" style="border: none; border-radius: 0; margin: 0;">
            <table class="table" style="width: 100%; margin: 0;">
                <thead>
                    <tr style="background: #FAFAFA;">
                        <th style="width: 8%; padding: 12px 16px;">Log ID</th>
                        <th style="width: 24%; padding: 12px 14px;">User</th>
                        <th style="width: 48%; padding: 12px 14px;">Action Performed</th>
                        <th style="width: 20%; padding: 12px 16px; text-align: right;">Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $has_log_rows = false;
                    while ($log = mysqli_fetch_assoc($logs_res)):
                        $has_log_rows = true;
                    ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 12px 16px; vertical-align: middle; color: var(--text-muted); font-size: 12px; font-family: monospace;">
                                #<?= $log['id'] ?>
                            </td>

                            <td style="padding: 12px 14px; vertical-align: middle;">
                                <?php if (!empty($log['full_name'])): ?>
                                    <div style="font-weight: 600; font-size: 13px; color: var(--text-primary);">
                                        <?= htmlspecialchars($log['full_name']) ?>
                                    </div>
                                    <div class="body-xs" style="color: var(--text-muted);">
                                        <?= htmlspecialchars($log['email']) ?>
                                    </div>
                                <?php else: ?>
                                    <span style="font-style: italic; color: var(--text-muted); font-size: 13px;">System</span>
                                <?php endif; ?>
                            </td>

                            <td style="padding: 12px 14px; vertical-align: middle; font-size: 13px; color: var(--text-primary);">
                                <?= htmlspecialchars($log['action']) ?>
                            </td>

                            <td style="padding: 12px 16px; vertical-align: middle; text-align: right; color: var(--text-secondary); font-size: 12px;">
                                <?= date('M j, Y — g:i A', strtotime($log['created_at'])) ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if (!$has_log_rows): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 40px 16px; color: var(--text-muted);">
                                No audit records found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div style="padding: 16px 20px; background: #FAFAFA; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div class="body-xs" style="color: var(--text-secondary);">
                    Showing page <?= $current_page ?> of <?= $total_pages ?> (<?= $total_log_rows ?> entries)
                </div>
                <div class="flex items-center gap-xs">
                    <?php if ($current_page > 1): ?>
                        <a href="<?= htmlspecialchars(log_paginate_link(['page' => $current_page - 1])) ?>" class="btn btn-secondary btn-sm" style="height: 30px; font-size: 12px;">&laquo; Prev</a>
                    <?php endif; ?>

                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a href="<?= htmlspecialchars(log_paginate_link(['page' => $p])) ?>" class="btn <?= $p === $current_page ? 'btn-primary' : 'btn-secondary' ?> btn-sm" style="height: 30px; font-size: 12px; min-width: 32px; padding: 0 8px;">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?= htmlspecialchars(log_paginate_link(['page' => $current_page + 1])) ?>" class="btn btn-secondary btn-sm" style="height: 30px; font-size: 12px;">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
