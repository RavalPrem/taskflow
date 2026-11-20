<?php
$page_title = 'Create Project';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/require_role.php';

// Only Admin and Project Manager can create projects
require_role(['admin', 'project_manager']);

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];

$error = '';
$name = '';
$description = '';
$status = 'active';
$manager_id = $user_id;
$selected_members = [];

// Fetch available managers (for Admin role to select)
$managers = [];
if ($user_role === 'admin') {
    $mgr_res = mysqli_query($conn, "SELECT id, full_name, role FROM users WHERE role IN ('admin', 'project_manager') AND status = 'active' ORDER BY full_name ASC");
    while ($m = mysqli_fetch_assoc($mgr_res)) {
        $managers[] = $m;
    }
}

// Fetch assignable team members (only users with role = team_member per spec)
$team_members = [];
$mem_res = mysqli_query($conn, "SELECT id, full_name, email FROM users WHERE role = 'team_member' AND status = 'active' ORDER BY full_name ASC");
while ($tm = mysqli_fetch_assoc($mem_res)) {
    $team_members[] = $tm;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    if ($user_role === 'admin') {
        $manager_id = (int)($_POST['manager_id'] ?? $user_id);
    } else {
        $manager_id = $user_id; // PM always manages their own created project
    }
    $selected_members = isset($_POST['members']) && is_array($_POST['members']) ? array_map('intval', $_POST['members']) : [];

    if (empty($name)) {
        $error = "Project name is required.";
    } elseif (!in_array($status, ['active', 'on_hold', 'completed'], true)) {
        $error = "Invalid project status selected.";
    } else {
        // 1. Insert Project using prepared statement
        $stmt = mysqli_prepare($conn, "INSERT INTO projects (name, description, manager_id, status) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssis", $name, $description, $manager_id, $status);

        if (mysqli_stmt_execute($stmt)) {
            $new_project_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            // 2. Insert assigned project members
            if (!empty($selected_members)) {
                $mem_stmt = mysqli_prepare($conn, "INSERT INTO project_members (project_id, user_id) VALUES (?, ?)");
                foreach ($selected_members as $m_uid) {
                    mysqli_stmt_bind_param($mem_stmt, "ii", $new_project_id, $m_uid);
                    mysqli_stmt_execute($mem_stmt);
                }
                mysqli_stmt_close($mem_stmt);
            }

            // 3. Record in activity log
            $log_stmt = mysqli_prepare($conn, "INSERT INTO activity_log (user_id, action) VALUES (?, ?)");
            $action_text = "Created project \"{$name}\"";
            mysqli_stmt_bind_param($log_stmt, "is", $user_id, $action_text);
            mysqli_stmt_execute($log_stmt);
            mysqli_stmt_close($log_stmt);

            $_SESSION['flash_success'] = "Project \"{$name}\" created successfully.";
            header('Location: ' . BASE_URL . '/projects/view.php?id=' . $new_project_id);
            exit;
        } else {
            $error = "Failed to create project: " . mysqli_error($conn);
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
            <span class="eyebrow">PORTFOLIO PLANNING</span>
            <h1 class="headline-lg">Create New Project</h1>
            <p class="body-sm">Define sprint milestones, project scope, and assign engineering members.</p>
        </div>
        <a href="<?= BASE_URL ?>/projects/index.php" class="btn btn-secondary btn-sm flex items-center gap-xs">
            <?= tf_icon('arrow_back', 16) ?>
            <span>Back to Projects</span>
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?= tf_icon('error', 18) ?>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="<?= BASE_URL ?>/projects/create.php">
            <!-- Project Name -->
            <div class="form-group">
                <label class="form-label" for="name">Project Name <span style="color: var(--danger-text);">*</span></label>
                <input type="text" id="name" name="name" class="form-control" 
                       value="<?= htmlspecialchars($name) ?>" 
                       placeholder="e.g. Website Redesign Q4" required autofocus>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label class="form-label" for="description">Scope &amp; Description</label>
                <textarea id="description" name="description" class="form-control" 
                          placeholder="Outline the core deliverables, technical goals, and target objectives for this project..."><?= htmlspecialchars($description) ?></textarea>
            </div>

            <div class="grid grid-cols-2" style="gap: 16px;">
                <!-- Status -->
                <div class="form-group">
                    <label class="form-label" for="status">Initial Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="on_hold" <?= $status === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>

                <!-- Manager Assignment (Admin only; PM is auto-assigned) -->
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
                        <span class="form-hint">You are assigned as the owner of this project.</span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Assign Team Members -->
            <div class="form-group" style="margin-top: 8px;">
                <label class="form-label">Assign Team Members (Workers)</label>
                <span class="form-hint" style="display: block; margin-top: -2px; margin-bottom: 12px;">
                    Only users with the <code>team_member</code> role can be assigned to work on tasks.
                </span>

                <?php if (!empty($team_members)): ?>
                    <div style="max-height: 200px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px; padding: 12px; background: var(--surface-alt);">
                        <?php foreach ($team_members as $tm): 
                            $checked = in_array((int)$tm['id'], $selected_members, true);
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
                    <p class="body-sm" style="color: var(--text-muted);">No active team members registered yet.</p>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-between" style="margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--border);">
                <a href="<?= BASE_URL ?>/projects/index.php" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary flex items-center gap-xs">
                    <?= tf_icon('save', 16) ?>
                    <span>Create Project</span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
