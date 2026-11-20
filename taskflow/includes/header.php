<?php
require_once __DIR__ . '/../config.php';

// Active page detection helper
$current_script = $_SERVER['SCRIPT_NAME'] ?? '';
function is_nav_active(string $path, string $current_script): bool {
    return strpos($current_script, $path) !== false;
}

$user_role = $_SESSION['role'] ?? null;
$user_name = $_SESSION['full_name'] ?? 'User';
$is_logged_in = isset($_SESSION['user_id']);

$role_labels = [
    'admin' => 'Admin',
    'project_manager' => 'Project Manager',
    'team_member' => 'Team Member'
];
$display_role = $role_labels[$user_role] ?? 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' — TaskFlow' : 'TaskFlow — Enterprise Project Management' ?></title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <!-- Design System CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
    <!-- Fixed Top Navbar -->
    <header class="navbar">
        <div class="navbar-inner">
            <div class="flex items-center gap-lg">
                <!-- Brand Logo -->
                <a href="<?= $is_logged_in ? BASE_URL . '/dashboard.php' : BASE_URL . '/' ?>" class="navbar-brand">
                    <span class="brand-icon">TF</span>
                    <span class="brand-text">TaskFlow</span>
                </a>

                <!-- Role-Aware Navigation Links -->
                <?php if ($is_logged_in): ?>
                    <nav class="navbar-nav">
                        <!-- Dashboard (All Roles) -->
                        <a href="<?= BASE_URL ?>/dashboard.php" 
                           class="nav-link <?= is_nav_active('dashboard.php', $current_script) ? 'active' : '' ?>">
                            Dashboard
                        </a>

                        <?php if ($user_role === 'admin'): ?>
                            <!-- Admin Navigation -->
                            <a href="<?= BASE_URL ?>/projects/index.php" 
                               class="nav-link <?= is_nav_active('projects/', $current_script) ? 'active' : '' ?>">
                                Projects
                            </a>
                            <a href="<?= BASE_URL ?>/tasks/board.php" 
                               class="nav-link <?= (is_nav_active('tasks/board.php', $current_script) || is_nav_active('tasks/create.php', $current_script) || is_nav_active('tasks/view.php', $current_script)) ? 'active' : '' ?>">
                                Tasks
                            </a>
                            <a href="<?= BASE_URL ?>/admin/manage_users.php" 
                               class="nav-link <?= is_nav_active('admin/manage_users.php', $current_script) ? 'active' : '' ?>">
                                Manage Users
                            </a>
                            <a href="<?= BASE_URL ?>/admin/system_admin.php" 
                               class="nav-link <?= is_nav_active('admin/system_admin.php', $current_script) ? 'active' : '' ?>">
                                System Admin
                            </a>

                        <?php elseif ($user_role === 'project_manager'): ?>
                            <!-- Project Manager Navigation -->
                            <a href="<?= BASE_URL ?>/projects/index.php" 
                               class="nav-link <?= is_nav_active('projects/', $current_script) ? 'active' : '' ?>">
                                Projects
                            </a>
                            <a href="<?= BASE_URL ?>/tasks/board.php" 
                               class="nav-link <?= is_nav_active('tasks/', $current_script) ? 'active' : '' ?>">
                                Tasks
                            </a>

                        <?php elseif ($user_role === 'team_member'): ?>
                            <!-- Team Member Navigation -->
                            <a href="<?= BASE_URL ?>/tasks/my_tasks.php" 
                               class="nav-link <?= is_nav_active('tasks/my_tasks.php', $current_script) ? 'active' : '' ?>">
                                My Tasks
                            </a>
                            <a href="<?= BASE_URL ?>/projects/index.php" 
                               class="nav-link <?= is_nav_active('projects/', $current_script) ? 'active' : '' ?>">
                                My Projects
                            </a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </div>

            <!-- Right-Hand Side Navbar Actions -->
            <div class="navbar-actions">
                <?php if ($is_logged_in): ?>
                    <!-- User Profile Dropdown (Clean, replaces direct logout button) -->
                    <div class="user-dropdown">
                        <button type="button" class="user-dropdown-btn" aria-haspopup="true" aria-expanded="false">
                            <span class="user-avatar">
                                <?= strtoupper(substr($user_name, 0, 1)) ?>
                            </span>
                            <div style="text-align: left; display: flex; flex-direction: column;">
                                <span class="user-name" style="line-height: 1.2;"><?= htmlspecialchars($user_name) ?></span>
                                <span style="font-size: 11px; color: var(--text-secondary); text-transform: capitalize;"><?= str_replace('_', ' ', $user_role) ?></span>
                            </div>
                            <?= tf_icon('arrow_drop_down', '', 18) ?>
                        </button>

                        <div class="dropdown-menu">
                            <div class="dropdown-header">
                                <div class="dropdown-header-name"><?= htmlspecialchars($user_name) ?></div>
                                <div class="dropdown-header-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
                                <div style="margin-top: 6px;">
                                    <span class="pill pill-neutral" style="font-size: 11px;"><?= htmlspecialchars($display_role) ?></span>
                                </div>
                            </div>

                            <a href="<?= BASE_URL ?>/profile.php" class="dropdown-item">
                                <?= tf_icon('person', '', 18) ?>
                                <span>Profile &amp; Settings</span>
                            </a>

                            <?php if ($user_role === 'admin'): ?>
                                <a href="<?= BASE_URL ?>/admin/manage_users.php" class="dropdown-item">
                                    <?= tf_icon('manage_accounts', '', 18) ?>
                                    <span>Manage Users</span>
                                </a>
                                <a href="<?= BASE_URL ?>/admin/system_admin.php" class="dropdown-item">
                                    <?= tf_icon('security', '', 18) ?>
                                    <span>System Admin</span>
                                </a>
                            <?php endif; ?>

                            <div class="dropdown-divider"></div>

                            <a href="<?= BASE_URL ?>/auth/logout.php" class="dropdown-item dropdown-item-danger">
                                <?= tf_icon('logout', '', 18) ?>
                                <span>Sign Out</span>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-ghost">Log In</a>
                    <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-primary">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main Content Shell -->
    <main class="main-content">
        <?php if (!empty($_SESSION['flash_success'])): ?>
            <div class="container" style="padding-top: 16px;">
                <div class="alert alert-success">
                    <?= tf_icon('check_circle', '', 20) ?>
                    <span><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
                </div>
            </div>
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['flash_error'])): ?>
            <div class="container" style="padding-top: 16px;">
                <div class="alert alert-danger">
                    <?= tf_icon('error', '', 20) ?>
                    <span><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
                </div>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>
