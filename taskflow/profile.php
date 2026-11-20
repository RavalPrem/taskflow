<?php
$page_title = 'My Profile';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth_check.php';

$user_id = (int)$_SESSION['user_id'];
$flash_success = '';
$flash_error = '';

if (!empty($_SESSION['flash_success'])) {
    $flash_success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}
if (!empty($_SESSION['flash_error'])) {
    $flash_error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action 1: Update Full Name
    if ($action === 'update_name') {
        $full_name = trim($_POST['full_name'] ?? '');
        if (strlen($full_name) < 2) {
            $_SESSION['flash_error'] = 'Full name must be at least 2 characters.';
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $full_name, $user_id);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);

                $_SESSION['full_name'] = $full_name;

                // Log activity
                $log_stmt = mysqli_prepare($conn, "INSERT INTO activity_log (user_id, action) VALUES (?, 'Updated profile name')");
                mysqli_stmt_bind_param($log_stmt, "i", $user_id);
                mysqli_stmt_execute($log_stmt);
                mysqli_stmt_close($log_stmt);

                $_SESSION['flash_success'] = 'Profile details updated successfully.';
            } else {
                mysqli_stmt_close($stmt);
                $_SESSION['flash_error'] = 'Database error: unable to update profile.';
            }
        }
        header('Location: ' . BASE_URL . '/profile.php');
        exit;
    }

    // Action 2: Change Password
    if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password     = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $_SESSION['flash_error'] = 'All password fields are required.';
        } elseif (strlen($new_password) < 8) {
            $_SESSION['flash_error'] = 'New password must be at least 8 characters long.';
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['flash_error'] = 'New password and confirmation do not match.';
        } else {
            // Verify current password
            $stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $user_data = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if (!$user_data || !password_verify($current_password, $user_data['password'])) {
                $_SESSION['flash_error'] = 'Current password is incorrect.';
            } else {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $up_stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
                mysqli_stmt_bind_param($up_stmt, "si", $new_hash, $user_id);
                if (mysqli_stmt_execute($up_stmt)) {
                    mysqli_stmt_close($up_stmt);

                    // Log activity
                    $log_stmt = mysqli_prepare($conn, "INSERT INTO activity_log (user_id, action) VALUES (?, 'Changed account password')");
                    mysqli_stmt_bind_param($log_stmt, "i", $user_id);
                    mysqli_stmt_execute($log_stmt);
                    mysqli_stmt_close($log_stmt);

                    $_SESSION['flash_success'] = 'Password changed successfully.';
                } else {
                    mysqli_stmt_close($up_stmt);
                    $_SESSION['flash_error'] = 'Database error: unable to update password.';
                }
            }
        }
        header('Location: ' . BASE_URL . '/profile.php');
        exit;
    }
}

// Fetch current user details
$stmt = mysqli_prepare($conn, "SELECT id, full_name, email, role, status, created_at FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$me = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

// Fetch personal recent activity (last 10 events)
$act_stmt = mysqli_prepare($conn, "SELECT id, action, created_at FROM activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
mysqli_stmt_bind_param($act_stmt, "i", $user_id);
mysqli_stmt_execute($act_stmt);
$act_res = mysqli_stmt_get_result($act_stmt);
$my_activities = [];
while ($row = mysqli_fetch_assoc($act_res)) {
    $my_activities[] = $row;
}
mysqli_stmt_close($act_stmt);

$role_pill = match($me['role']) {
    'admin' => 'pill-danger',
    'project_manager' => 'pill-warning',
    'team_member' => 'pill-info',
    default => ''
};

require_once __DIR__ . '/includes/header.php';
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
    <div class="page-header" style="margin-bottom: 28px;">
        <div>
            <span class="eyebrow">ACCOUNT SETTINGS</span>
            <h1 class="headline-lg">My Profile</h1>
            <p class="body-sm">Manage personal account credentials and view personal activity history.</p>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px; align-items: start;">
        
        <!-- Left: Profile Summary Card -->
        <div class="card" style="padding: 24px; text-align: center; background: #FFFFFF; border: 1px solid var(--border); border-radius: 12px;">
            <div style="width: 72px; height: 72px; border-radius: 50%; background: var(--primary); color: #FFFFFF; display: flex; align-items: center; justify-content: center; font-size: 26px; font-weight: 800; margin: 0 auto 16px auto;">
                <?= strtoupper(substr($me['full_name'], 0, 2)) ?>
            </div>
            
            <h2 class="headline-sm" style="font-size: 20px; margin-bottom: 4px;">
                <?= htmlspecialchars($me['full_name']) ?>
            </h2>
            <div class="body-sm" style="color: var(--text-secondary); margin-bottom: 12px;">
                <?= htmlspecialchars($me['email']) ?>
            </div>
            
            <div style="margin-bottom: 20px;">
                <span class="pill <?= $role_pill ?>" style="font-size: 12px; padding: 3px 12px;">
                    <?= ucwords(str_replace('_', ' ', $me['role'])) ?>
                </span>
            </div>

            <div style="border-top: 1px solid var(--border); padding-top: 16px; text-align: left;" class="body-xs">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="color: var(--text-secondary);">Account Status</span>
                    <span style="font-weight: 600; color: #16A34A; text-transform: uppercase;">Active</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: var(--text-secondary);">Member Since</span>
                    <span style="font-weight: 600; color: var(--text-primary);"><?= date('M j, Y', strtotime($me['created_at'])) ?></span>
                </div>
            </div>
        </div>

        <!-- Right: Update Profile & Change Password Cards -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            
            <!-- Update Full Name Card -->
            <div class="card" style="padding: 24px; background: #FFFFFF; border: 1px solid var(--border); border-radius: 12px;">
                <h3 class="headline-sm" style="font-size: 16px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                    <?= tf_icon('person', 18) ?>
                    <span>Personal Details</span>
                </h3>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_name">

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="form-label" style="font-size: 13px; font-weight: 600;">Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($me['full_name']) ?>" required style="height: 38px;">
                    </div>

                    <div class="form-group" style="margin-bottom: 16px;">
                        <label class="form-label" style="font-size: 13px; font-weight: 600;">Email Address</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($me['email']) ?>" disabled style="height: 38px; background: var(--surface-muted); color: var(--text-muted);">
                        <span class="body-xs" style="color: var(--text-muted); display: block; margin-top: 4px;">Email is immutable for security purposes.</span>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm flex items-center gap-xs">
                        <?= tf_icon('save', 15) ?>
                        <span>Save Changes</span>
                    </button>
                </form>
            </div>

            <!-- Change Password Card -->
            <div class="card" style="padding: 24px; background: #FFFFFF; border: 1px solid var(--border); border-radius: 12px;">
                <h3 class="headline-sm" style="font-size: 16px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
                    <?= tf_icon('key', 18) ?>
                    <span>Change Password</span>
                </h3>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="form-label" style="font-size: 13px; font-weight: 600;">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required style="height: 38px;">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" style="font-size: 13px; font-weight: 600;">New Password</label>
                            <input type="password" name="new_password" class="form-control" required minlength="8" placeholder="At least 8 chars" style="height: 38px;">
                        </div>
                        <div class="form-group" style="margin: 0;">
                            <label class="form-label" style="font-size: 13px; font-weight: 600;">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="8" placeholder="Confirm password" style="height: 38px;">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm flex items-center gap-xs">
                        <?= tf_icon('lock', 15) ?>
                        <span>Update Password</span>
                    </button>
                </form>
            </div>

        </div>

    </div>

    <!-- Personal Activity History -->
    <div class="card" style="margin-top: 32px; padding: 0; overflow: hidden; background: #FFFFFF; border: 1px solid var(--border); border-radius: 12px;">
        <div style="padding: 16px 20px; background: var(--surface-alt); border-bottom: 1px solid var(--border);">
            <h3 class="headline-sm" style="font-size: 15px; margin: 0; display: flex; align-items: center; gap: 8px;">
                <?= tf_icon('history', 16) ?>
                <span>My Recent Activity</span>
            </h3>
        </div>

        <div class="table-container" style="border: none; margin: 0;">
            <table class="table" style="width: 100%; margin: 0;">
                <thead>
                    <tr style="background: #FAFAFA;">
                        <th style="width: 70%; padding: 10px 16px; font-size: 12px; color: var(--text-secondary); text-transform: uppercase;">Action</th>
                        <th style="width: 30%; padding: 10px 16px; font-size: 12px; color: var(--text-secondary); text-transform: uppercase; text-align: right;">Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($my_activities)): ?>
                        <tr>
                            <td colspan="2" style="text-align: center; padding: 24px; color: var(--text-muted); font-size: 13px;">
                                No recent activity recorded.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($my_activities as $act): ?>
                            <tr style="border-bottom: 1px solid var(--border);">
                                <td style="padding: 12px 16px; font-size: 13px; color: var(--text-primary);">
                                    <?= htmlspecialchars($act['action']) ?>
                                </td>
                                <td style="padding: 12px 16px; font-size: 12px; color: var(--text-secondary); text-align: right;">
                                    <?= date('M j, Y — g:i A', strtotime($act['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
