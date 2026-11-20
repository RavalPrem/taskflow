<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';
$full_name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if email already exists
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($check_stmt, "s", $email);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);

        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $error = "An account with this email address already exists. Please sign in instead.";
            mysqli_stmt_close($check_stmt);
        } else {
            mysqli_stmt_close($check_stmt);

            // Hash password and insert as team_member
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'team_member';
            $status = 'active';

            $insert_stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($insert_stmt, "sssss", $full_name, $email, $password_hash, $role, $status);

            if (mysqli_stmt_execute($insert_stmt)) {
                $new_user_id = mysqli_insert_id($conn);
                mysqli_stmt_close($insert_stmt);

                // Record audit log
                $log_stmt = mysqli_prepare($conn, "INSERT INTO activity_log (user_id, action) VALUES (?, 'Registered as new Team Member')");
                if ($log_stmt) {
                    mysqli_stmt_bind_param($log_stmt, "i", $new_user_id);
                    mysqli_stmt_execute($log_stmt);
                    mysqli_stmt_close($log_stmt);
                }

                // Immediate login & session initialization
                session_regenerate_id(true);
                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['role'] = $role;
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                $_SESSION['flash_success'] = "Welcome to TaskFlow, " . htmlspecialchars($full_name) . "! Your workspace is ready.";

                header('Location: ' . BASE_URL . '/dashboard.php');
                exit;
            } else {
                $error = "Registration failed due to a system error. Please try again.";
                mysqli_stmt_close($insert_stmt);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — TaskFlow</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <!-- Design System CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background-color: var(--bg);
        }
        .auth-container {
            width: 100%;
            max-width: 1060px;
            background: #FFFFFF;
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.03);
            display: flex;
            flex-direction: column;
        }
        @media (min-width: 768px) {
            .auth-container {
                flex-direction: row;
            }
        }
        .auth-sidebar {
            background-color: var(--primary);
            color: #FFFFFF;
            padding: 40px;
            flex: 0 0 42%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }
        .auth-main {
            padding: 40px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        @media (min-width: 1024px) {
            .auth-main {
                padding: 48px 56px;
            }
        }
        .input-icon-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-icon-wrapper .icon-left {
            position: absolute;
            left: 12px;
            color: var(--text-muted);
            pointer-events: none;
            font-size: 20px;
        }
        .input-icon-wrapper input {
            padding-left: 40px;
            padding-right: 40px;
        }
        .input-icon-wrapper .toggle-pw {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
        }
        .input-icon-wrapper .toggle-pw:hover {
            color: var(--text-primary);
        }
        .role-notice {
            background-color: var(--surface-alt);
            border: 1px solid var(--border);
            border-left: 4px solid var(--accent);
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-container">
            <!-- Left Branding Presentation (Clean & Minimal) -->
            <div class="auth-sidebar">
                <div>
                    <a href="<?= BASE_URL ?>/" style="display: inline-flex; align-items: center; gap: 10px; text-decoration: none; color: #FFFFFF;">
                        <span class="brand-icon" style="background: rgba(255,255,255,0.15);">TF</span>
                        <span class="brand-text" style="color: #FFFFFF;">TaskFlow</span>
                    </a>

                    <div style="margin-top: 36px;">
                        <h1 class="headline-md" style="color: #FFFFFF; line-height: 1.3; margin-bottom: 12px;">
                            Join Your Workspace
                        </h1>
                        <p class="body-sm" style="color: #9CA3AF; line-height: 1.6;">
                            Create your account to start managing tasks, collaborating on projects, and tracking milestones.
                        </p>
                    </div>
                </div>

                <div style="margin-top: 36px; padding-top: 18px; border-top: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: space-between;">
                    <div class="flex items-center gap-xs">
                        <span style="width: 8px; height: 8px; background: #10B981; border-radius: 50%;"></span>
                        <span class="body-sm" style="color: #D1D5DB; font-size: 12px;">TaskFlow System</span>
                    </div>
                    <span class="body-sm" style="color: #9CA3AF; font-size: 12px;">XAMPP Local</span>
                </div>
            </div>

            <!-- Right Registration Form -->
            <div class="auth-main">
                <div style="margin-bottom: 20px;">
                    <span class="eyebrow" style="color: var(--accent); display: block; margin-bottom: 6px;">GET STARTED</span>
                    <h2 class="headline-md" style="margin-bottom: 6px;">Create your account</h2>
                    <p class="body-sm">Start collaborating seamlessly across your project teams.</p>
                </div>

                <!-- Info Notice per Design Mockup -->
                <div class="role-notice">
                    <?= tf_icon('info', '', 18) ?>
                    <p class="body-sm" style="margin: 0; color: var(--text-primary);">
                        New public accounts default to <span class="pill pill-neutral" style="font-size: 11px;">team_member</span> role. Organization administrators can grant elevated privileges anytime.
                    </p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <?= tf_icon('error', '', 20) ?>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/auth/register.php">
                    <div class="form-group">
                        <label class="form-label" for="full_name">Full Name</label>
                        <div class="input-icon-wrapper">
                            <?= tf_icon('person', 'icon-left', 18) ?>
                            <input type="text" id="full_name" name="full_name" class="form-control" 
                                   value="<?= htmlspecialchars($full_name) ?>" 
                                   placeholder="e.g. Elena Rostova" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">Work Email</label>
                        <div class="input-icon-wrapper">
                            <?= tf_icon('mail', 'icon-left', 18) ?>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($email) ?>" 
                                   placeholder="elena@company.com" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-2" style="gap: 16px;">
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label" for="password">Password</label>
                            <div class="input-icon-wrapper">
                                <?= tf_icon('lock', 'icon-left', 18) ?>
                                <input type="password" id="password" name="password" class="form-control" 
                                       placeholder="Min. 8 chars" required>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 12px;">
                            <label class="form-label" for="confirm_password">Confirm Password</label>
                            <div class="input-icon-wrapper">
                                <?= tf_icon('lock_reset', 'icon-left', 18) ?>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                       placeholder="Re-enter password" required>
                            </div>
                        </div>
                    </div>
                    <p class="form-hint" style="margin-top: -6px; margin-bottom: 16px;">Minimum 8 characters required.</p>

                    <button type="submit" class="btn btn-primary" style="width: 100%; height: 42px;">
                        <span>Create Account</span>
                        <?= tf_icon('arrow_forward', '', 18) ?>
                    </button>
                </form>

                <div style="text-align: center; margin-top: 24px;">
                    <p class="body-sm">
                        Already have an account? 
                        <a href="<?= BASE_URL ?>/auth/login.php" style="color: var(--accent); font-weight: 600; text-decoration: none;">Sign in</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
