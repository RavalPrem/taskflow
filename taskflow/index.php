<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['full_name'] ?? '';
$user_role = $_SESSION['role'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskFlow — Role-Based Project & Task Management</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <!-- Design System CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .hero-section {
            padding: 64px 0 48px 0;
            text-align: center;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background-color: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-pill);
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-secondary);
            margin-bottom: 24px;
        }
        .hero-badge .dot {
            width: 6px;
            height: 6px;
            background-color: var(--accent);
            border-radius: 50%;
        }
        .hero-title {
            font-size: 40px;
            line-height: 1.2;
            font-weight: 800;
            letter-spacing: -0.03em;
            max-width: 820px;
            margin: 0 auto 20px auto;
            color: var(--text-primary);
        }
        @media (min-width: 768px) {
            .hero-title {
                font-size: 48px;
            }
        }
        .hero-desc {
            font-size: 16px;
            line-height: 1.6;
            color: var(--text-secondary);
            max-width: 620px;
            margin: 0 auto 32px auto;
        }
        .hero-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 56px;
            flex-wrap: wrap;
        }
        .preview-window {
            background-color: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            text-align: left;
            margin-bottom: 64px;
        }
        .preview-header {
            padding: 12px 20px;
            background-color: var(--surface-alt);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .traffic-lights {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .light {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: var(--border-strong);
        }
        .light-red { background-color: #EF4444; }
        .light-yellow { background-color: #F59E0B; }
        .light-green { background-color: #10B981; }
        .preview-body {
            padding: 24px;
        }
        .kanban-col {
            background-color: var(--surface-alt);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .kanban-col-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
            font-weight: 600;
        }
        .kanban-card {
            background-color: var(--surface);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 12px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        }
        .step-num {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: var(--primary);
            color: #FFFFFF;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 16px;
        }
        .cta-banner {
            background-color: var(--surface-alt);
            border: 1px solid var(--border);
            border-radius: var(--radius-card);
            padding: 48px 32px;
            text-align: center;
            margin: 48px 0;
        }
    </style>
</head>
<body>
    <!-- Top Fixed Public Navbar -->
    <header class="navbar">
        <div class="navbar-inner">
            <div class="flex items-center gap-xl">
                <a href="<?= BASE_URL ?>/" class="navbar-brand">
                    <span class="brand-icon">TF</span>
                    <span class="brand-text">TaskFlow</span>
                </a>

                <nav class="navbar-nav" style="display: none;">
                    <a href="#features" class="nav-link">Features</a>
                    <a href="#how-it-works" class="nav-link">Workflow</a>
                    <a href="#roles" class="nav-link">Roles</a>
                </nav>
            </div>

            <div class="navbar-actions">
                <?php if ($is_logged_in): ?>
                    <span class="pill pill-neutral" style="text-transform: capitalize;"><?= htmlspecialchars(str_replace('_', ' ', $user_role)) ?></span>
                    <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary btn-sm">Go to Dashboard</a>
                    <a href="<?= BASE_URL ?>/auth/logout.php" class="btn btn-ghost btn-sm">Logout</a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-ghost btn-sm">Sign In</a>
                    <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-primary btn-sm">Get Started</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="main-content" style="padding-top: 64px;">
        <!-- Hero Section -->
        <section class="container hero-section">
            <div class="hero-badge">
                <span class="dot"></span>
                <span>Role-Based Project &amp; Task Management</span>
            </div>

            <h1 class="hero-title">
                Organize projects, assign tasks, and keep your team aligned.
            </h1>

            <p class="hero-desc">
                TaskFlow gives software teams the structure they need to plan sprints, enforce quality review gates, and deliver clean work on schedule with procedural PHP &amp; MySQLi.
            </p>

            <div class="hero-cta">
                <?php if ($is_logged_in): ?>
                    <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary btn-lg flex items-center gap-xs">
                        <span>Open Workspace Dashboard</span>
                        <?= tf_icon('arrow_forward', 16) ?>
                    </a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-primary btn-lg flex items-center gap-xs">
                        <span>Get Started Free</span>
                        <?= tf_icon('arrow_forward', 16) ?>
                    </a>
                    <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-secondary btn-lg">
                        <span>Sign In</span>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Realistic Kanban Board Preview Mockup (Static example with clear label) -->
            <div class="preview-window">
                <div class="preview-header">
                    <div class="traffic-lights">
                        <span class="light light-red"></span>
                        <span class="light light-yellow"></span>
                        <span class="light light-green"></span>
                        <span style="margin-left: 8px; font-size: 12px; color: var(--text-secondary); font-family: monospace;">
                            taskflow/tasks/board.php &bull; Interactive Board Preview
                        </span>
                    </div>
                    <span class="pill pill-neutral" style="font-size: 11px;">Example Preview</span>
                </div>

                <div class="preview-body">
                    <div class="grid grid-cols-3" style="gap: 16px;">
                        <!-- Column 1: To Do -->
                        <div class="kanban-col">
                            <div class="kanban-col-header">
                                <span class="flex items-center gap-xs">
                                    <span style="width: 8px; height: 8px; background: var(--text-muted); border-radius: 50%;"></span>
                                    <span>To Do</span>
                                </span>
                                <span class="pill pill-neutral" style="font-size: 11px;">2</span>
                            </div>
                            <div class="kanban-card">
                                <div class="flex items-center justify-between mb-sm">
                                    <span class="pill pill-danger" style="font-size: 10px;">high</span>
                                    <span class="body-sm" style="font-size: 11px;">Sprint 1</span>
                                </div>
                                <strong style="font-size: 13px; display: block; margin-bottom: 6px;">OAuth2 authentication &amp; tokens</strong>
                                <span class="body-sm" style="font-size: 12px;">Mobile API Integration</span>
                            </div>
                            <div class="kanban-card">
                                <div class="flex items-center justify-between mb-sm">
                                    <span class="pill pill-warning" style="font-size: 10px;">medium</span>
                                    <span class="body-sm" style="font-size: 11px;">Sprint 2</span>
                                </div>
                                <strong style="font-size: 13px; display: block; margin-bottom: 6px;">Automated integration tests</strong>
                                <span class="body-sm" style="font-size: 12px;">Mobile API Integration</span>
                            </div>
                        </div>

                        <!-- Column 2: In Progress / Review -->
                        <div class="kanban-col">
                            <div class="kanban-col-header">
                                <span class="flex items-center gap-xs">
                                    <span style="width: 8px; height: 8px; background: var(--accent); border-radius: 50%;"></span>
                                    <span>In Review</span>
                                </span>
                                <span class="pill pill-accent" style="font-size: 11px;">1</span>
                            </div>
                            <div class="kanban-card">
                                <div class="flex items-center justify-between mb-sm">
                                    <span class="pill pill-danger" style="font-size: 10px;">high</span>
                                    <span class="pill pill-accent" style="font-size: 10px;">PM Review</span>
                                </div>
                                <strong style="font-size: 13px; display: block; margin-bottom: 6px;">Authentication flow &amp; session guards</strong>
                                <span class="body-sm" style="font-size: 12px;">Website Redesign &bull; Sarah Chen</span>
                            </div>
                        </div>

                        <!-- Column 3: Done -->
                        <div class="kanban-col">
                            <div class="kanban-col-header">
                                <span class="flex items-center gap-xs">
                                    <span style="width: 8px; height: 8px; background: var(--success-text); border-radius: 50%;"></span>
                                    <span>Done</span>
                                </span>
                                <span class="pill pill-success" style="font-size: 11px;">1</span>
                            </div>
                            <div class="kanban-card">
                                <div class="flex items-center justify-between mb-sm">
                                    <span class="pill pill-success" style="font-size: 10px;">completed</span>
                                    <span class="body-sm" style="font-size: 11px;">Approved</span>
                                </div>
                                <strong style="font-size: 13px; display: block; margin-bottom: 6px;">Design system tokens and UI kit</strong>
                                <span class="body-sm" style="font-size: 12px;">Website Redesign &bull; Alex Dev</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3-Feature Grid -->
        <section id="features" class="container mb-xl">
            <div style="text-align: center; margin-bottom: 40px;">
                <span class="eyebrow" style="color: var(--accent);">CORE CAPABILITIES</span>
                <h2 class="headline-md" style="margin-top: 6px;">Built for structured team execution</h2>
                <p class="body-sm" style="max-width: 520px; margin: 6px auto 0 auto;">Everything your team needs to deliver sprint deliverables on schedule.</p>
            </div>

            <div class="grid grid-cols-3" style="gap: 24px;">
                <div class="card card-hover">
                    <div style="width: 40px; height: 40px; background: var(--surface-alt); border: 1px solid var(--border); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: var(--text-primary);">
                        <?= tf_icon('view_kanban', 22) ?>
                    </div>
                    <h3 class="headline-sm" style="margin-bottom: 8px;">Kanban Task Boards</h3>
                    <p class="body-sm" style="line-height: 1.6; margin-bottom: 16px;">
                        Track tasks visually through <code>To Do</code>, <code>In Progress</code>, <code>In Review</code>, and <code>Done</code> states with priorities and deadlines.
                    </p>
                    <span class="label-sm" style="color: var(--text-muted);">&bull; Priority Labels &bull; Due Dates</span>
                </div>

                <div class="card card-hover">
                    <div style="width: 40px; height: 40px; background: var(--surface-alt); border: 1px solid var(--border); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: var(--text-primary);">
                        <?= tf_icon('verified', 22) ?>
                    </div>
                    <h3 class="headline-sm" style="margin-bottom: 8px;">Approval Review Gates</h3>
                    <p class="body-sm" style="line-height: 1.6; margin-bottom: 16px;">
                        Developers submit work into review, allowing Project Managers and Admins to approve items to Done or request adjustments.
                    </p>
                    <span class="label-sm" style="color: var(--text-muted);">&bull; Quality Gates &bull; Sign-off Flow</span>
                </div>

                <div class="card card-hover">
                    <div style="width: 40px; height: 40px; background: var(--surface-alt); border: 1px solid var(--border); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; color: var(--text-primary);">
                        <?= tf_icon('shield', 22) ?>
                    </div>
                    <h3 class="headline-sm" style="margin-bottom: 8px;">Strict Role Permissions</h3>
                    <p class="body-sm" style="line-height: 1.6; margin-bottom: 16px;">
                        Role-based access control (RBAC) enforced on every page with MySQLi prepared statements and session authentication guards.
                    </p>
                    <span class="label-sm" style="color: var(--text-muted);">&bull; Admin &bull; PM &bull; Team Member</span>
                </div>
            </div>
        </section>

        <!-- How It Works (3 Steps) -->
        <section id="how-it-works" class="container mb-xl">
            <div style="text-align: center; margin-bottom: 40px;">
                <span class="eyebrow" style="color: var(--accent);">WORKFLOW SIMPLICITY</span>
                <h2 class="headline-md" style="margin-top: 6px;">How TaskFlow works</h2>
                <p class="body-sm" style="max-width: 500px; margin: 6px auto 0 auto;">Move from sprint planning to deliverable approval in three clear steps.</p>
            </div>

            <div class="grid grid-cols-3" style="gap: 24px;">
                <div class="card">
                    <span class="step-num">1</span>
                    <h3 class="headline-sm" style="margin-bottom: 8px;">Create a Project</h3>
                    <p class="body-sm" style="line-height: 1.6;">
                        Project Managers and Admins initialize projects, define scopes, and assign participating team members.
                    </p>
                </div>
                <div class="card">
                    <span class="step-num">2</span>
                    <h3 class="headline-sm" style="margin-bottom: 8px;">Assign &amp; Work Tasks</h3>
                    <p class="body-sm" style="line-height: 1.6;">
                        Tasks are created with priority, deadlines, and assigned engineers who advance them from To Do to In Progress.
                    </p>
                </div>
                <div class="card">
                    <span class="step-num">3</span>
                    <h3 class="headline-sm" style="margin-bottom: 8px;">Review &amp; Approve</h3>
                    <p class="body-sm" style="line-height: 1.6;">
                        Tasks are moved to In Review for validation. Managers approve to Done or send feedback via task comments.
                    </p>
                </div>
            </div>
        </section>

        <!-- Role-Based Capabilities (3 Role Cards) -->
        <section id="roles" class="container mb-xl">
            <div style="text-align: center; margin-bottom: 40px;">
                <span class="eyebrow" style="color: var(--accent);">PERMISSION MATRIX</span>
                <h2 class="headline-md" style="margin-top: 6px;">Tailored for each role</h2>
                <p class="body-sm" style="max-width: 500px; margin: 6px auto 0 auto;">Clear separation of duties prevents misconfiguration and unauthorized edits.</p>
            </div>

            <div class="grid grid-cols-3" style="gap: 24px;">
                <!-- Admin Card -->
                <div class="card">
                    <div class="flex items-center justify-between mb-sm">
                        <span class="pill pill-danger">Administrator</span>
                        <span style="color: var(--danger-text); display: flex; align-items: center;"><?= tf_icon('admin_panel_settings', 22) ?></span>
                    </div>
                    <h3 class="headline-sm" style="margin-bottom: 8px;">Full System Control</h3>
                    <p class="body-sm" style="line-height: 1.6; margin-bottom: 16px;">
                        Manage users, reassign roles, toggle account statuses, inspect system-wide audit logs, and oversee all projects and tasks.
                    </p>
                    <ul class="body-sm" style="padding-left: 18px; color: var(--text-secondary);">
                        <li>Manage Users &amp; Roles</li>
                        <li>System Audit &amp; Activity Log</li>
                        <li>Full Project &amp; Task Oversight</li>
                    </ul>
                </div>

                <!-- PM Card -->
                <div class="card">
                    <div class="flex items-center justify-between mb-sm">
                        <span class="pill pill-warning">Project Manager</span>
                        <span style="color: var(--warning-text); display: flex; align-items: center;"><?= tf_icon('manage_accounts', 22) ?></span>
                    </div>
                    <h3 class="headline-sm" style="margin-bottom: 8px;">Project &amp; Task Leadership</h3>
                    <p class="body-sm" style="line-height: 1.6; margin-bottom: 16px;">
                        Create and manage projects, assign team members, plan tasks on the Kanban board, and approve completed work from review.
                    </p>
                    <ul class="body-sm" style="padding-left: 18px; color: var(--text-secondary);">
                        <li>Create &amp; Edit Projects</li>
                        <li>Assign Tasks to Members</li>
                        <li>Approve In-Review Tasks to Done</li>
                    </ul>
                </div>

                <!-- Team Member Card -->
                <div class="card">
                    <div class="flex items-center justify-between mb-sm">
                        <span class="pill pill-info">Team Member</span>
                        <span style="color: var(--info-text); display: flex; align-items: center;"><?= tf_icon('badge', 22) ?></span>
                    </div>
                    <h3 class="headline-sm" style="margin-bottom: 8px;">Focused Delivery</h3>
                    <p class="body-sm" style="line-height: 1.6; margin-bottom: 16px;">
                        View assigned tasks in "My Tasks", update task progress, submit items for manager review, and collaborate via comments.
                    </p>
                    <ul class="body-sm" style="padding-left: 18px; color: var(--text-secondary);">
                        <li>Dedicated My Tasks View</li>
                        <li>Move to In Progress &amp; In Review</li>
                        <li>Task Discussion Comments</li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Call to Action Banner -->
        <section class="container">
            <div class="cta-banner">
                <h2 class="headline-lg" style="margin-bottom: 12px;">Ready to streamline your team's workflow?</h2>
                <p class="body-md" style="color: var(--text-secondary); max-width: 540px; margin: 0 auto 24px auto;">
                    Experience role-based task and project tracking built on pure PHP and MySQLi.
                </p>
                <?php if ($is_logged_in): ?>
                    <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary btn-lg">Open Dashboard</a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-primary btn-lg">Get Started Free</a>
                    <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-secondary btn-lg" style="margin-left: 12px;">Sign In</a>
                <?php endif; ?>
            </div>
        </section>

<?php
require_once __DIR__ . '/includes/footer_public.php';
?>
