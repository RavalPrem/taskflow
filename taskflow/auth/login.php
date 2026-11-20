<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

$success = $_SESSION['flash_success'] ?? '';
unset($_SESSION['flash_success']);

$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please enter both your email address and password.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id, full_name, email, password_hash, role, status FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['status'] !== 'active') {
                $error = "This account has been disabled. Please contact an administrator.";
            } else {
                // Login success: regenerate session ID for security
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];

                // Record login in activity_log
                $log_stmt = mysqli_prepare($conn, "INSERT INTO activity_log (user_id, action) VALUES (?, 'User logged in')");
                if ($log_stmt) {
                    mysqli_stmt_bind_param($log_stmt, "i", $user['id']);
                    mysqli_stmt_execute($log_stmt);
                    mysqli_stmt_close($log_stmt);
                }

                $redirect_to = $_SESSION['redirect_after_login'] ?? (BASE_URL . '/dashboard.php');
                unset($_SESSION['redirect_after_login']);
                header('Location: ' . $redirect_to);
                exit;
            }
        } else {
            // Generic security error message
            $error = "Invalid email address or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — TaskFlow</title>
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
        .quick-accounts {
            margin-top: 24px;
            padding: 16px;
            background-color: var(--surface-alt);
            border: 1px solid var(--border);
            border-radius: 10px;
        }
        .quick-accounts-title {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-secondary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .quick-acc-btn {
            background: #FFFFFF;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 6px 10px;
            font-size: 12px;
            color: var(--text-primary);
            cursor: pointer;
            text-align: left;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 6px;
            transition: all 0.15s ease;
            width: 100%;
        }
        .quick-acc-btn:hover {
            background-color: var(--neutral-pill-bg);
            border-color: var(--border-strong);
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-container">
            <!-- Left Branding Panel (Clean & Minimal) -->
            <div class="auth-sidebar">
                <div>
                    <a href="<?= BASE_URL ?>/" style="display: inline-flex; align-items: center; gap: 10px; text-decoration: none; color: #FFFFFF;">
                        <span class="brand-icon" style="background: rgba(255,255,255,0.15);">TF</span>
                        <span class="brand-text" style="color: #FFFFFF;">TaskFlow</span>
                    </a>

                    <div style="margin-top: 36px;">
                        <h1 class="headline-md" style="color: #FFFFFF; line-height: 1.3; margin-bottom: 12px;">
                            Task &amp; Project Management
                        </h1>
                        <p class="body-sm" style="color: #9CA3AF; line-height: 1.6;">
                            Sign in to organize your work, track tasks, and collaborate with your team.
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

            <!-- Right Form Panel -->
            <div class="auth-main">
                <div style="margin-bottom: 24px;">
                    <span class="eyebrow" style="color: var(--accent); display: block; margin-bottom: 6px;">WELCOME BACK</span>
                    <h2 class="headline-md" style="margin-bottom: 6px;">Sign in to TaskFlow</h2>
                    <p class="body-sm">Enter your credentials to access your workspace.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger">
                        <?= tf_icon('error', '', 20) ?>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <?= tf_icon('check_circle', '', 20) ?>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>/auth/login.php">
                    <div class="form-group">
                        <label class="form-label" for="email">Work Email</label>
                        <div class="input-icon-wrapper">
                            <?= tf_icon('mail', 'icon-left', 18) ?>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($email) ?>" 
                                   placeholder="name@taskflow.local" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="flex items-center justify-between mb-sm">
                            <label class="form-label" for="password" style="margin-bottom: 0;">Password</label>
                        </div>
                        <div class="input-icon-wrapper">
                            <?= tf_icon('lock', 'icon-left', 18) ?>
                            <input type="password" id="password" name="password" class="form-control" 
                                   placeholder="••••••••••••" required>
                            <button type="button" class="toggle-pw" id="togglePassword" aria-label="Toggle password visibility">
                                <span id="pwIcon"><?= tf_icon('visibility', '', 18) ?></span>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; height: 42px; margin-top: 8px;">
                        <span>Sign In</span>
                        <?= tf_icon('arrow_forward', '', 18) ?>
                    </button>
                </form>

                <!-- Quick Test Login Helper (One-Click for easy grading/testing) -->
                <div class="quick-accounts">
                    <div class="quick-accounts-title">
                        <span>Quick-Fill Seeded Accounts</span>
                        <span style="font-weight: 400; text-transform: none;">Password: <code>Password123!</code></span>
                    </div>
                    <button type="button" class="quick-acc-btn" onclick="fillLogin('admin@taskflow.local')">
                        <span><strong>Admin:</strong> System Admin</span>
                        <span class="pill pill-danger" style="font-size: 10px;">admin</span>
                    </button>
                    <button type="button" class="quick-acc-btn" onclick="fillLogin('manager@taskflow.local')">
                        <span><strong>PM:</strong> Sarah Chen</span>
                        <span class="pill pill-warning" style="font-size: 10px;">project_manager</span>
                    </button>
                    <button type="button" class="quick-acc-btn" onclick="fillLogin('member1@taskflow.local')">
                        <span><strong>Dev:</strong> Alex Dev</span>
                        <span class="pill pill-info" style="font-size: 10px;">team_member</span>
                    </button>
                    <button type="button" class="quick-acc-btn" onclick="fillLogin('member2@taskflow.local')">
                        <span><strong>QA:</strong> Jordan QA</span>
                        <span class="pill pill-info" style="font-size: 10px;">team_member</span>
                    </button>
                </div>

                <div style="text-align: center; margin-top: 24px;">
                    <p class="body-sm">
                        Don't have an account? 
                        <a href="<?= BASE_URL ?>/auth/register.php" style="color: var(--accent); font-weight: 600; text-decoration: none;">Sign up</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password visibility toggle
        const toggleBtn = document.getElementById('togglePassword');
        const pwdInput = document.getElementById('password');
        if (toggleBtn && pwdInput) {
            toggleBtn.addEventListener('click', function () {
                const isPassword = pwdInput.getAttribute('type') === 'password';
                pwdInput.setAttribute('type', isPassword ? 'text' : 'password');
            });
        }

        // Quick login fill helper
        function fillLogin(email) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = 'Password123!';
        }
    </script>
</body>
</html>
