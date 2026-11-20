<?php
$page_title = 'Create Task';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/require_role.php';

// Only Admin and PM can create tasks
require_role(['admin', 'project_manager']);

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'];

$preselected_project_id = isset($_GET['project_id']) && is_numeric($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

$error = '';
$title = '';
$description = '';
$project_id = $preselected_project_id;
$assigned_to = null;
$priority = 'medium';
$due_date = date('Y-m-d', strtotime('+7 days'));

// Fetch available projects for task assignment
$projects = [];
if ($user_role === 'admin') {
    $p_res = mysqli_query($conn, "SELECT id, name FROM projects WHERE status != 'completed' ORDER BY name ASC");
} else {
    $p_stmt = mysqli_prepare($conn, "SELECT id, name FROM projects WHERE manager_id = ? AND status != 'completed' ORDER BY name ASC");
    mysqli_stmt_bind_param($p_stmt, "i", $user_id);
    mysqli_stmt_execute($p_stmt);
    $p_res = mysqli_stmt_get_result($p_stmt);
}
while ($p = mysqli_fetch_assoc($p_res)) {
    $projects[] = $p;
}

// Fetch team members across projects
$team_members = [];
$tm_res = mysqli_query($conn, "SELECT id, full_name, email FROM users WHERE role = 'team_member' AND status = 'active' ORDER BY full_name ASC");
while ($tm = mysqli_fetch_assoc($tm_res)) {
    $team_members[] = $tm;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $project_id = (int)($_POST['project_id'] ?? 0);
    $assigned_to = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
    $priority = $_POST['priority'] ?? 'medium';
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

    if (empty($title)) {
        $error = "Task title is required.";
    } elseif ($project_id <= 0) {
        $error = "Please select a valid project.";
    } elseif (!in_array($priority, ['low', 'medium', 'high'], true)) {
        $error = "Invalid priority selected.";
    } else {
        // Verify project ownership if PM
        if ($user_role === 'project_manager') {
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM projects WHERE id = ? AND manager_id = ? LIMIT 1");
            mysqli_stmt_bind_param($check_stmt, "ii", $project_id, $user_id);
            mysqli_stmt_execute($check_stmt);
            $check_res = mysqli_stmt_get_result($check_stmt);
            $allowed_proj = mysqli_fetch_assoc($check_res);
            mysqli_stmt_close($check_stmt);

            if (!$allowed_proj) {
                $error = "You can only create tasks within projects you manage.";
            }
        }

        if (empty($error)) {
            $stmt = mysqli_prepare($conn, "
                INSERT INTO tasks (project_id, title, description, assigned_to, created_by, priority, status, due_date)
                VALUES (?, ?, ?, ?, ?, ?, 'todo', ?)
            ");
            mysqli_stmt_bind_param($stmt, "ississs", $project_id, $title, $description, $assigned_to, $user_id, $priority, $due_date);

            if (mysqli_stmt_execute($stmt)) {
                $new_task_id = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);

                // If assigned member isn't already in project_members, automatically add them
                if ($assigned_to) {
                    $mem_check = mysqli_prepare($conn, "INSERT IGNORE INTO project_members (project_id, user_id) VALUES (?, ?)");
                    mysqli_stmt_bind_param($mem_check, "ii", $project_id, $assigned_to);
                    mysqli_stmt_execute($mem_check);
                    mysqli_stmt_close($mem_check);
                }

                // Audit log
                $log_stmt = mysqli_prepare($conn, "INSERT INTO activity_log (user_id, action) VALUES (?, ?)");
                $act_text = "Created task \"{$title}\"";
                mysqli_stmt_bind_param($log_stmt, "is", $user_id, $act_text);
                mysqli_stmt_execute($log_stmt);
                mysqli_stmt_close($log_stmt);

                $_SESSION['flash_success'] = "Task \"{$title}\" created successfully.";
                header('Location: ' . BASE_URL . '/tasks/view.php?id=' . $new_task_id);
                exit;
            } else {
                $error = "Failed to create task: " . mysqli_error($conn);
                mysqli_stmt_close($stmt);
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding-top: 32px; max-width: 760px;">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <span class="eyebrow">TASK MANAGEMENT</span>
            <h1 class="headline-lg">Create New Task</h1>
            <p class="body-sm">Define sprint deliverable, assign an engineer, and set priority.</p>
        </div>
        <a href="<?= BASE_URL ?>/tasks/board.php<?= $preselected_project_id > 0 ? '?project_id=' . $preselected_project_id : '' ?>" class="btn btn-secondary btn-sm flex items-center gap-xs">
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

    <div class="card">
        <form method="POST" action="<?= BASE_URL ?>/tasks/create.php">
            <!-- Project Selection -->
            <div class="form-group">
                <label class="form-label" for="project_id">Assign to Project <span style="color: var(--danger-text);">*</span></label>
                <select id="project_id" name="project_id" class="form-control" required>
                    <option value="">-- Choose Project --</option>
                    <?php foreach ($projects as $proj): ?>
                        <option value="<?= $proj['id'] ?>" <?= $project_id === (int)$proj['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($proj['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Task Title -->
            <div class="form-group">
                <label class="form-label" for="title">Task Title <span style="color: var(--danger-text);">*</span></label>
                <input type="text" id="title" name="title" class="form-control" 
                       value="<?= htmlspecialchars($title) ?>" 
                       placeholder="e.g. Implement OAuth2 refresh token rotation" required autofocus>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label class="form-label" for="description">Task Specification &amp; Acceptance Criteria</label>
                <textarea id="description" name="description" class="form-control" 
                          placeholder="Provide detailed technical instructions, reproduction steps, or QA expectations..."><?= htmlspecialchars($description) ?></textarea>
            </div>

            <div class="grid grid-cols-3" style="gap: 16px;">
                <!-- Assignee -->
                <div class="form-group">
                    <label class="form-label" for="assigned_to">Assignee (Engineer)</label>
                    <select id="assigned_to" name="assigned_to" class="form-control">
                        <option value="">-- Unassigned --</option>
                        <?php foreach ($team_members as $tm): ?>
                            <option value="<?= $tm['id'] ?>" <?= $assigned_to === (int)$tm['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tm['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Priority -->
                <div class="form-group">
                    <label class="form-label" for="priority">Priority</label>
                    <select id="priority" name="priority" class="form-control">
                        <option value="low" <?= $priority === 'low' ? 'selected' : '' ?>>Low</option>
                        <option value="medium" <?= $priority === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>High</option>
                    </select>
                </div>

                <!-- Due Date -->
                <div class="form-group">
                    <label class="form-label" for="due_date">Due Date</label>
                    <input type="date" id="due_date" name="due_date" class="form-control" 
                           value="<?= htmlspecialchars($due_date) ?>">
                </div>
            </div>

            <div class="flex items-center justify-between" style="margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--border);">
                <a href="<?= BASE_URL ?>/tasks/board.php" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary flex items-center gap-xs">
                    <?= tf_icon('save', 16) ?>
                    <span>Create Task</span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
