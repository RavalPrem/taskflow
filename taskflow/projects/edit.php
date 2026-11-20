<?php
$page_title = 'Edit Project';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/require_role.php';

// Only Admin and Project Manager can access edit
require_role(['admin', 'project_manager']);

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];

$project_id = (int)($_GET['id'] ?? 0);
if ($project_id <= 0) {
    $_SESSION['flash_error'] = "Invalid project ID specified.";
    header('Location: ' . BASE_URL . '/projects/index.php');
    exit;
}

// Fetch project (enforcing PM ownership check)
if ($user_role === 'admin') {
    $stmt = mysqli_prepare($conn, "SELECT * FROM projects WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $project_id);
} else {
    $stmt = mysqli_prepare($conn, "SELECT * FROM projects WHERE id = ? AND manager_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ii", $project_id, $user_id);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$project = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$project) {
    $_SESSION['flash_error'] = "Project not found or you do not have permission to edit it.";
    header('Location: ' . BASE_URL . '/projects/index.php');
    exit;
}

$error = '';
$name = $project['name'];
$description = $project['description'];
$status = $project['status'];
$manager_id = (int)$project['manager_id'];

// Handle Project Deletion (Admin or PM owner)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $del_stmt = mysqli_prepare($conn, "DELETE FROM projects WHERE id = ?");
    mysqli_stmt_bind_param($del_stmt, "i", $project_id);
    if (mysqli_stmt_execute($del_stmt)) {
        mysqli_stmt_close($del_stmt);

        // Record audit log
        $log_stmt = mysqli_prepare($conn, "INSERT INTO activity_log (user_id, action) VALUES (?, ?)");
        $act_msg = "Deleted project \"{$name}\"";
        mysqli_stmt_bind_param($log_stmt, "is", $user_id, $act_msg);
        mysqli_stmt_execute($log_stmt);
        mysqli_stmt_close($log_stmt);

        $_SESSION['flash_success'] = "Project \"{$name}\" was successfully deleted.";
        header('Location: ' . BASE_URL . '/projects/index.php');
        exit;
    } else {
        $error = "Failed to delete project: " . mysqli_error($conn);
        mysqli_stmt_close($del_stmt);
    }
}

// Fetch available managers (for Admin)
$managers = [];
if ($user_role === 'admin') {
    $mgr_res = mysqli_query($conn, "SELECT id, full_name, role FROM users WHERE role IN ('admin', 'project_manager') AND status = 'active' ORDER BY full_name ASC");
    while ($m = mysqli_fetch_assoc($mgr_res)) {
        $managers[] = $m;
    }
}

// Fetch assignable team members
$team_members = [];
$mem_res = mysqli_query($conn, "SELECT id, full_name, email FROM users WHERE role = 'team_member' AND status = 'active' ORDER BY full_name ASC");
while ($tm = mysqli_fetch_assoc($mem_res)) {
    $team_members[] = $tm;
}

// Fetch current assigned members
$current_members = [];
$cur_res = mysqli_query($conn, "SELECT user_id FROM project_members WHERE project_id = {$project_id}");
while ($row = mysqli_fetch_assoc($cur_res)) {
    $current_members[] = (int)$row['user_id'];
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'delete')) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    if ($user_role === 'admin') {
        $manager_id = (int)($_POST['manager_id'] ?? $project['manager_id']);
    } else {
        $manager_id = (int)$project['manager_id'];
    }
    $selected_members = isset($_POST['members']) && is_array($_POST['members']) ? array_map('intval', $_POST['members']) : [];

    if (empty($name)) {
        $error = "Project name is required.";
    } elseif (!in_array($status, ['active', 'on_hold', 'completed'], true)) {
        $error = "Invalid project status selected.";
    } else {
        // 1. Update Project details
        $stmt = mysqli_prepare($conn, "UPDATE projects SET name = ?, description = ?, manager_id = ?, status = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssisi", $name, $description, $manager_id, $status, $project_id);

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);

            // 2. Sync project members: delete removed, insert newly checked
            mysqli_query($conn, "DELETE FROM project_members WHERE project_id = {$project_id}");
            if (!empty($selected_members)) {
                $ins_stmt = mysqli_prepare($conn, "INSERT INTO project_members (project_id, user_id) VALUES (?, ?)");
                foreach ($selected_members as $m_uid) {
                    mysqli_stmt_bind_param($ins_stmt, "ii", $project_id, $m_uid);
                    mysqli_stmt_execute($ins_stmt);
                }
                mysqli_stmt_close($ins_stmt);
            }

            // 3. Log activity
            $log_stmt = mysqli_prepare($conn, "INSERT INTO activity_log (user_id, action) VALUES (?, ?)");
            $act_msg = "Updated project settings for \"{$name}\"";
            mysqli_stmt_bind_param($log_stmt, "is", $user_id, $act_msg);
            mysqli_stmt_execute($log_stmt);
            mysqli_stmt_close($log_stmt);

            $_SESSION['flash_success'] = "Project \"{$name}\" updated successfully.";
            header('Location: ' . BASE_URL . '/projects/view.php?id=' . $project_id);
            exit;
        } else {
            $error = "Failed to update project: " . mysqli_error($conn);
            mysqli_stmt_close($stmt);
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding-top: 32px; max-width: 800px;">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <span class="eyebrow">PORTFOLIO SETTINGS</span>
            <h1 class="headline-lg">Edit Project</h1>
            <p class="body-sm">Update scope, adjust sprint status, and manage assigned team members.</p>
        </div>
        <a href="<?= BASE_URL ?>/projects/view.php?id=<?= $project_id ?>" class="btn btn-secondary btn-sm flex items-center gap-xs">
            <?= tf_icon('arrow_back', 16) ?>
            <span>View Board</span>
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?= tf_icon('error', 18) ?>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="card mb-lg">
        <form method="POST" action="<?= BASE_URL ?>/projects/edit.php?id=<?= $project_id ?>">
            <!-- Project Name -->
            <div class="form-group">
                <label class="form-label" for="name">Project Name <span style="color: var(--danger-text);">*</span></label>
                <input type="text" id="name" name="name" class="form-control" 
                       value="<?= htmlspecialchars($name) ?>" required>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label class="form-label" for="description">Scope &amp; Description</label>
                <textarea id="description" name="description" class="form-control"><?= htmlspecialchars($description) ?></textarea>
            </div>

            <div class="grid grid-cols-2" style="gap: 16px;">
                <!-- Status -->
                <div class="form-group">
                    <label class="form-label" for="status">Project Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="on_hold" <?= $status === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>

                <!-- Manager Assignment (Admin only) -->
                <?php if ($user_role === 'admin'): ?>
                    <div class="form-group">
                        <label class="form-label" for="manager_id">Project Manager</label>
                        <select id="manager_id" name="manager_id" class="form-control">
                            <?php foreach ($managers as $mgr): ?>
                                <option value="<?= $mgr['id'] ?>" <?= $mgr['id'] == $manager_id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($mgr['full_name']) ?> (<?= htmlspecialchars($mgr['role']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label class="form-label">Project Manager</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($_SESSION['full_name']) ?>" disabled style="background: var(--surface-alt); color: var(--text-secondary);">
                    </div>
                <?php endif; ?>
            </div>

            <!-- Assign Team Members -->
            <div class="form-group" style="margin-top: 8px;">
                <label class="form-label">Assigned Team Members</label>
                <span class="form-hint" style="display: block; margin-top: -2px; margin-bottom: 12px;">
                    Check or uncheck engineers assigned to work on tasks within this project.
                </span>

                <?php if (!empty($team_members)): ?>
                    <div style="max-height: 200px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px; padding: 12px; background: var(--surface-alt);">
                        <?php foreach ($team_members as $tm): 
                            $checked = in_array((int)$tm['id'], $current_members, true);
                        ?>
                            <label style="display: flex; align-items: center; gap: 10px; padding: 6px 4px; cursor: pointer; border-bottom: 1px solid rgba(0,0,0,0.03);">
                                <input type="checkbox" name="members[]" value="<?= $tm['id'] ?>" <?= $checked ? 'checked' : '' ?> style="width: 16px; height: 16px; cursor: pointer;">
                                <div class="flex items-center gap-xs">
                                    <span class="user-avatar" style="width: 24px; height: 24px; font-size: 11px;">
                                        <?= strtoupper(substr($tm['full_name'], 0, 1)) ?>
                                    </span>
                                    <span style="font-size: 13px; font-weight: 500; color: var(--text-primary);"><?= htmlspecialchars($tm['full_name']) ?></span>
                                    <span style="font-size: 12px; color: var(--text-secondary);">&bull; <?= htmlspecialchars($tm['email']) ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="body-sm" style="color: var(--text-muted);">No active team members available.</p>
                <?php endif; ?>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-between" style="margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--border);">
                <a href="<?= BASE_URL ?>/projects/view.php?id=<?= $project_id ?>" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary flex items-center gap-xs">
                    <?= tf_icon('save', 16) ?>
                    <span>Save Changes</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Danger Zone: Delete Project -->
    <div class="card" style="border-color: var(--danger-border); background: #FFF5F5;">
        <div class="flex items-center justify-between" style="flex-wrap: wrap; gap: 16px;">
            <div>
                <h3 class="headline-sm" style="color: var(--danger-text); margin-bottom: 4px;">Delete Project</h3>
                <p class="body-sm" style="color: #7F1D1D; margin: 0;">
                    Permanently delete this project and all its associated tasks and comments. This action cannot be undone.
                </p>
            </div>
            <form method="POST" action="<?= BASE_URL ?>/projects/edit.php?id=<?= $project_id ?>" onsubmit="return confirm('Are you absolutely sure you want to permanently delete this project? All associated tasks will be removed.');">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn btn-destructive flex items-center gap-xs">
                    <?= tf_icon('delete', 16) ?>
                    <span>Delete Project</span>
                </button>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
