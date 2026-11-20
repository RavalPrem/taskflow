<?php
$page_title = 'Manage Users';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/require_role.php';

require_role(['admin']);

// Flash messages
$flash_success = '';
$flash_error   = '';
if (!empty($_SESSION['flash_success'])) {
    $flash_success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $flash_error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Filters
$search = trim($_GET['search'] ?? '');
$role_filter = $_GET['role'] ?? '';
$valid_roles = ['admin', 'project_manager', 'team_member'];

// Pagination
$per_page     = 15;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($current_page - 1) * $per_page;

// Stats overview
$stats = ['total' => 0, 'admin' => 0, 'project_manager' => 0, 'team_member' => 0, 'active' => 0, 'disabled' => 0];
$stats_res = mysqli_query($conn, "SELECT role, status, COUNT(*) AS cnt FROM users GROUP BY role, status");
if ($stats_res) {
    while ($row = mysqli_fetch_assoc($stats_res)) {
        $stats['total']        += (int)$row['cnt'];
        if (isset($stats[$row['role']])) {
            $stats[$row['role']] += (int)$row['cnt'];
        }
        if (isset($stats[$row['status']])) {
            $stats[$row['status']] += (int)$row['cnt'];
        }
    }
}

// Build WHERE clause
$where_parts = [];
$bind_types  = '';
$bind_vals   = [];

if ($search !== '') {
    $where_parts[] = '(full_name LIKE ? OR email LIKE ?)';
    $like = '%' . $search . '%';
    $bind_types .= 'ss';
    $bind_vals[] = $like;
    $bind_vals[] = $like;
}
if (in_array($role_filter, $valid_roles, true)) {
    $where_parts[] = 'role = ?';
    $bind_types .= 's';
    $bind_vals[] = $role_filter;
}

$where_sql = $where_parts ? ('WHERE ' . implode(' AND ', $where_parts)) : '';

// Count for pagination
$count_sql  = "SELECT COUNT(*) FROM users $where_sql";
$count_stmt = mysqli_prepare($conn, $count_sql);
if ($bind_types) {
    mysqli_stmt_bind_param($count_stmt, $bind_types, ...$bind_vals);
}
mysqli_stmt_execute($count_stmt);
mysqli_stmt_bind_result($count_stmt, $total_rows);
mysqli_stmt_fetch($count_stmt);
mysqli_stmt_close($count_stmt);
$total_pages = max(1, (int)ceil($total_rows / $per_page));

// Fetch users
$users_sql  = "SELECT id, full_name, email, role, status, created_at FROM users $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
$users_stmt = mysqli_prepare($conn, $users_sql);
$page_bind_types = $bind_types . 'ii';
$page_bind_vals  = array_merge($bind_vals, [$per_page, $offset]);
mysqli_stmt_bind_param($users_stmt, $page_bind_types, ...$page_bind_vals);
mysqli_stmt_execute($users_stmt);
$users_result = mysqli_stmt_get_result($users_stmt);

function paginate_link(array $extra = []): string {
    $params = array_merge($_GET, $extra);
    return '?' . http_build_query($params);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding-top: 32px; padding-bottom: 60px;">

    <!-- Flash notifications -->
    <?php if ($flash_success): ?>
        <div class="alert alert-success" style="margin-bottom: 20px;">
            <div class="flex items-center gap-sm">
                <?= tf_icon('check', 18) ?>
                <span><?= htmlspecialchars($flash_success) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($flash_error): ?>
        <div class="alert alert-danger" style="margin-bottom: 20px;">
            <div class="flex items-center gap-sm">
                <?= tf_icon('error', 18) ?>
                <span><?= htmlspecialchars($flash_error) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="page-header" style="margin-bottom: 24px;">
        <div>
            <span class="eyebrow">ADMINISTRATION</span>
            <h1 class="headline-lg">Manage Users</h1>
            <p class="body-sm">Inspect team roster, assign RBAC access roles, and toggle user account statuses.</p>
        </div>

        <div class="flex items-center gap-sm">
            <a href="<?= BASE_URL ?>/admin/system_admin.php" class="btn btn-secondary btn-sm flex items-center gap-xs">
                <?= tf_icon('history', 15) ?>
                <span>System Audit Log</span>
            </a>
        </div>
    </div>

    <!-- Stats Bar -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 14px; margin-bottom: 24px;">
        <div class="card" style="padding: 14px; text-align: center; background: #FFFFFF;">
            <div style="font-size: 24px; font-weight: 800; color: var(--text-primary);"><?= $stats['total'] ?></div>
            <div style="font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Total Users</div>
        </div>
        <div class="card" style="padding: 14px; text-align: center; background: #FFFFFF;">
            <div style="font-size: 24px; font-weight: 800; color: var(--accent);"><?= $stats['admin'] ?></div>
            <div style="font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Admins</div>
        </div>
        <div class="card" style="padding: 14px; text-align: center; background: #FFFFFF;">
            <div style="font-size: 24px; font-weight: 800; color: #2563EB;"><?= $stats['project_manager'] ?></div>
            <div style="font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Project Managers</div>
        </div>
        <div class="card" style="padding: 14px; text-align: center; background: #FFFFFF;">
            <div style="font-size: 24px; font-weight: 800; color: #059669;"><?= $stats['team_member'] ?></div>
            <div style="font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Team Members</div>
        </div>
        <div class="card" style="padding: 14px; text-align: center; background: #FFFFFF;">
            <div style="font-size: 24px; font-weight: 800; color: #16A34A;"><?= $stats['active'] ?></div>
            <div style="font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Active</div>
        </div>
        <div class="card" style="padding: 14px; text-align: center; background: #FFFFFF;">
            <div style="font-size: 24px; font-weight: 800; color: #DC2626;"><?= $stats['disabled'] ?></div>
            <div style="font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Disabled</div>
        </div>
    </div>

    <!-- Search & Filter Card -->
    <div class="card" style="padding: 16px 20px; margin-bottom: 24px; background: #FFFFFF;">
        <form method="GET" action="" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; flex: 1;">
                <!-- Search input -->
                <input type="text" name="search" class="form-control" placeholder="Search by name or email..." 
                       value="<?= htmlspecialchars($search) ?>" style="height: 36px; font-size: 13px; min-width: 240px; padding: 0 10px;">
                
                <!-- Role selector -->
                <select name="role" class="form-control" style="height: 36px; font-size: 13px; min-width: 160px; padding: 0 10px;">
                    <option value="">All Roles</option>
                    <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="project_manager" <?= $role_filter === 'project_manager' ? 'selected' : '' ?>>Project Manager</option>
                    <option value="team_member" <?= $role_filter === 'team_member' ? 'selected' : '' ?>>Team Member</option>
                </select>

                <button type="submit" class="btn btn-primary btn-sm flex items-center gap-xs" style="height: 36px; padding: 0 14px;">
                    <?= tf_icon('search', 15) ?>
                    <span>Filter</span>
                </button>

                <?php if ($search !== '' || $role_filter !== ''): ?>
                    <a href="<?= BASE_URL ?>/admin/manage_users.php" class="btn btn-secondary btn-sm" style="height: 36px; padding: 0 12px; color: var(--danger-text);">
                        Clear
                    </a>
                <?php endif; ?>
            </div>
            
            <div class="body-xs" style="color: var(--text-secondary);">
                Found <strong><?= $total_rows ?></strong> <?= $total_rows === 1 ? 'user' : 'users' ?>
            </div>
        </form>
    </div>

    <!-- Users Table Card -->
    <div class="card" style="padding: 0; overflow: hidden; background: #FFFFFF; border: 1px solid var(--border); border-radius: 12px;">
        <div class="table-container" style="border: none; border-radius: 0; margin: 0;">
            <table class="table" style="width: 100%; margin: 0;">
                <thead>
                    <tr style="background: #FAFAFA;">
                        <th style="width: 5%; padding: 12px 16px;">#</th>
                        <th style="width: 22%; padding: 12px 14px;">Full Name</th>
                        <th style="width: 25%; padding: 12px 14px;">Email</th>
                        <th style="width: 22%; padding: 12px 14px;">Role</th>
                        <th style="width: 12%; padding: 12px 14px;">Status</th>
                        <th style="width: 14%; padding: 12px 16px; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $row_num = $offset + 1;
                    $has_rows = false;
                    while ($u = mysqli_fetch_assoc($users_result)):
                        $has_rows = true;
                        $is_self = ((int)$u['id'] === (int)$_SESSION['user_id']);
                        $is_active = ($u['status'] === 'active');
                        $role_pill = match($u['role']) {
                            'admin' => 'pill-danger',
                            'project_manager' => 'pill-warning',
                            'team_member' => 'pill-info',
                            default => ''
                        };
                    ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 12px 16px; vertical-align: middle; color: var(--text-muted); font-size: 13px;">
                                <?= $row_num++ ?>
                            </td>

                            <td style="padding: 12px 14px; vertical-align: middle;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="width: 30px; height: 30px; border-radius: 50%; background: #1C2333; color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700;">
                                        <?= strtoupper(substr($u['full_name'], 0, 2)) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; font-size: 14px; color: var(--text-primary);">
                                            <?= htmlspecialchars($u['full_name']) ?>
                                            <?php if ($is_self): ?>
                                                <span class="body-xs" style="color: var(--accent); font-weight: 700;">(You)</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="body-xs" style="color: var(--text-muted);">
                                            Joined <?= date('M j, Y', strtotime($u['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td style="padding: 12px 14px; vertical-align: middle; font-size: 13px; color: var(--text-secondary);">
                                <?= htmlspecialchars($u['email']) ?>
                            </td>

                            <!-- Role with form -->
                            <td style="padding: 12px 14px; vertical-align: middle;">
                                <?php if ($is_self): ?>
                                    <span class="pill <?= $role_pill ?>" style="font-size: 12px;">
                                        Admin (Cannot alter self)
                                    </span>
                                <?php else: ?>
                                    <form method="POST" action="<?= BASE_URL ?>/admin/update_role.php" style="display: inline-flex; align-items: center; gap: 6px;">
                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                        <select name="role" class="kanban-status-select" style="height: 30px; font-size: 12px; border-radius: 6px; padding: 0 6px; border: 1px solid var(--border);">
                                            <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                            <option value="project_manager" <?= $u['role'] === 'project_manager' ? 'selected' : '' ?>>Project Manager</option>
                                            <option value="team_member" <?= $u['role'] === 'team_member' ? 'selected' : '' ?>>Team Member</option>
                                        </select>
                                        <button type="submit" class="btn-confirm" title="Change Role" style="height: 30px; font-size: 11px; padding: 0 8px;">
                                            Update
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>

                            <!-- Status Pill -->
                            <td style="padding: 12px 14px; vertical-align: middle;">
                                <span class="pill <?= $is_active ? 'pill-success' : 'pill-danger' ?>">
                                    <?= $is_active ? 'Active' : 'Disabled' ?>
                                </span>
                            </td>

                            <!-- Action: Disable / Enable -->
                            <td style="padding: 12px 16px; vertical-align: middle; text-align: right;">
                                <?php if ($is_self): ?>
                                    <button class="btn btn-secondary btn-sm" disabled title="Cannot modify your own account" style="opacity: 0.5; height: 30px; font-size: 12px;">
                                        Protected
                                    </button>
                                <?php else: ?>
                                    <form method="POST" action="<?= BASE_URL ?>/admin/toggle_status.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to <?= $is_active ? 'disable' : 'enable' ?> this account?');">
                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                        <button type="submit" class="btn <?= $is_active ? 'btn-danger' : 'btn-primary' ?> btn-sm flex items-center gap-xs" style="height: 30px; font-size: 12px; padding: 0 10px;">
                                            <?= tf_icon($is_active ? 'user-x' : 'user-check', 14) ?>
                                            <span><?= $is_active ? 'Disable' : 'Enable' ?></span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                    <?php if (!$has_rows): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px 16px; color: var(--text-muted);">
                                No users match the given filter criteria.
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
                    Showing page <?= $current_page ?> of <?= $total_pages ?> (<?= $total_rows ?> total)
                </div>
                <div class="flex items-center gap-xs">
                    <?php if ($current_page > 1): ?>
                        <a href="<?= htmlspecialchars(paginate_link(['page' => $current_page - 1])) ?>" class="btn btn-secondary btn-sm" style="height: 30px; font-size: 12px;">&laquo; Prev</a>
                    <?php endif; ?>

                    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                        <a href="<?= htmlspecialchars(paginate_link(['page' => $p])) ?>" class="btn <?= $p === $current_page ? 'btn-primary' : 'btn-secondary' ?> btn-sm" style="height: 30px; font-size: 12px; min-width: 32px; padding: 0 8px;">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?= htmlspecialchars(paginate_link(['page' => $current_page + 1])) ?>" class="btn btn-secondary btn-sm" style="height: 30px; font-size: 12px;">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
