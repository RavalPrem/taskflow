<?php
require_once __DIR__ . '/../config.php';

// First connect to MySQL server without specifying database (in case db does not exist yet)
$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS);

if (!$conn) {
    die("<h3>MySQL Connection Failed</h3><p>" . htmlspecialchars(mysqli_connect_error()) . "</p><p>Please ensure MySQL service is started in XAMPP Control Panel.</p>");
}

mysqli_set_charset($conn, 'utf8mb4');

// Ensure database exists
$create_db_sql = "CREATE DATABASE IF NOT EXISTS `" . mysqli_real_escape_string($conn, DB_NAME) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if (!mysqli_query($conn, $create_db_sql)) {
    die("<h3>Failed to create database:</h3> " . htmlspecialchars(mysqli_error($conn)));
}

// Select database
if (!mysqli_select_db($conn, DB_NAME)) {
    die("<h3>Failed to select database:</h3> " . htmlspecialchars(mysqli_error($conn)));
}

// Check if tables exist by checking `users`
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
$schema_created = false;

if (mysqli_num_rows($table_check) === 0) {
    // Automatically import schema.sql so no terminal/cli is needed!
    $schema_path = __DIR__ . '/schema.sql';
    if (!file_exists($schema_path)) {
        die("<h3>schema.sql not found</h3><p>Expected at: " . htmlspecialchars($schema_path) . "</p>");
    }

    $schema_sql = file_get_contents($schema_path);
    // Execute multi query
    if (mysqli_multi_query($conn, $schema_sql)) {
        do {
            if ($result = mysqli_store_result($conn)) {
                mysqli_free_result($result);
            }
        } while (mysqli_more_results($conn) && mysqli_next_result($conn));
    }
    $schema_created = true;
}

$force = isset($_GET['force']) && $_GET['force'] === '1';

// Check if users already exist
$check_users = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users");
$user_count = 0;
if ($check_users) {
    $row = mysqli_fetch_assoc($check_users);
    $user_count = (int)($row['cnt'] ?? 0);
}

if ($user_count > 0 && !$force) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>TaskFlow — Database Ready</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #0f172a; padding: 40px; margin: 0; }
            .card { max-width: 680px; margin: 0 auto; background: #ffffff; padding: 32px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
            h2 { margin-top: 0; color: #1e293b; }
            .alert { background: #eff6ff; border-left: 4px solid #2563eb; padding: 14px; border-radius: 6px; margin-bottom: 24px; color: #1e40af; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
            th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #e2e8f0; }
            th { background: #f1f5f9; color: #475569; font-weight: 600; }
            code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 13px; }
            .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 500; }
            .badge-admin { background: #fee2e2; color: #991b1b; }
            .badge-pm { background: #fef3c7; color: #92400e; }
            .badge-dev { background: #e0f2fe; color: #075985; }
            .btn { display: inline-block; padding: 10px 20px; background: #2563eb; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 500; margin-right: 12px; }
            .btn-outline { background: #ffffff; border: 1px solid #cbd5e1; color: #475569; }
        </style>
    </head>
    <body>
        <div class="card">
            <h2>TaskFlow Database Ready</h2>
            <div class="alert">
                The database already contains <strong><?= $user_count ?></strong> active user accounts. The tables and seed data are already in place.
            </div>

            <p>You can use these 4 pre-configured test accounts:</p>
            <table>
                <thead>
                    <tr><th>Role</th><th>Full Name</th><th>Email</th><th>Password</th></tr>
                </thead>
                <tbody>
                    <tr><td><span class="badge badge-admin">admin</span></td><td>System Admin</td><td><code>admin@taskflow.local</code></td><td><code>Password123!</code></td></tr>
                    <tr><td><span class="badge badge-pm">project_manager</span></td><td>Sarah Chen</td><td><code>manager@taskflow.local</code></td><td><code>Password123!</code></td></tr>
                    <tr><td><span class="badge badge-dev">team_member</span></td><td>Alex Dev</td><td><code>member1@taskflow.local</code></td><td><code>Password123!</code></td></tr>
                    <tr><td><span class="badge badge-dev">team_member</span></td><td>Jordan QA</td><td><code>member2@taskflow.local</code></td><td><code>Password123!</code></td></tr>
                </tbody>
            </table>

            <div style="margin-top: 24px;">
                <a href="<?= BASE_URL ?>/" class="btn">Go to TaskFlow Home</a>
                <a href="seed.php?force=1" class="btn btn-outline" onclick="return confirm('Reset and re-seed all tables?');">Re-run Seed (?force=1)</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Clear tables if force reset
if ($force) {
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
    mysqli_query($conn, "TRUNCATE TABLE activity_log");
    mysqli_query($conn, "TRUNCATE TABLE task_comments");
    mysqli_query($conn, "TRUNCATE TABLE tasks");
    mysqli_query($conn, "TRUNCATE TABLE project_members");
    mysqli_query($conn, "TRUNCATE TABLE projects");
    mysqli_query($conn, "TRUNCATE TABLE users");
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");
}

// 1. Insert Users using password_hash() at runtime
$password = 'Password123!';
$password_hash = password_hash($password, PASSWORD_DEFAULT);

$users = [
    ['System Admin', 'admin@taskflow.local', $password_hash, 'admin', 'active'],
    ['Sarah Chen', 'manager@taskflow.local', $password_hash, 'project_manager', 'active'],
    ['Alex Dev', 'member1@taskflow.local', $password_hash, 'team_member', 'active'],
    ['Jordan QA', 'member2@taskflow.local', $password_hash, 'team_member', 'active']
];

$user_ids = [];
$stmt = mysqli_prepare($conn, "INSERT INTO users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)");

foreach ($users as $u) {
    mysqli_stmt_bind_param($stmt, "sssss", $u[0], $u[1], $u[2], $u[3], $u[4]);
    mysqli_stmt_execute($stmt);
    $user_ids[$u[1]] = mysqli_insert_id($conn);
}
mysqli_stmt_close($stmt);

$admin_id   = $user_ids['admin@taskflow.local'];
$sarah_id   = $user_ids['manager@taskflow.local'];
$alex_id    = $user_ids['member1@taskflow.local'];
$jordan_id  = $user_ids['member2@taskflow.local'];

// 2. Insert 2–3 sample projects owned by Sarah Chen
$projects = [
    ['Website Redesign', 'Revamp corporate portal with modern UI, mobile responsiveness, and dark mode support.', $sarah_id, 'active'],
    ['Mobile API Integration', 'RESTful API backend for upcoming iOS and Android mobile companion applications.', $sarah_id, 'active'],
    ['Cloud Infrastructure Migration', 'Migrate legacy on-premise application containers to cloud microservices.', $sarah_id, 'on_hold']
];

$project_ids = [];
$stmt_proj = mysqli_prepare($conn, "INSERT INTO projects (name, description, manager_id, status) VALUES (?, ?, ?, ?)");

foreach ($projects as $p) {
    mysqli_stmt_bind_param($stmt_proj, "ssis", $p[0], $p[1], $p[2], $p[3]);
    mysqli_stmt_execute($stmt_proj);
    $project_ids[] = mysqli_insert_id($conn);
}
mysqli_stmt_close($stmt_proj);

$proj_web = $project_ids[0];
$proj_api = $project_ids[1];
$proj_cloud = $project_ids[2];

// 3. Insert project_members rows adding Alex Dev and Jordan QA
$members = [
    [$proj_web, $alex_id],
    [$proj_web, $jordan_id],
    [$proj_api, $alex_id],
    [$proj_api, $jordan_id],
    [$proj_cloud, $alex_id]
];

$stmt_mem = mysqli_prepare($conn, "INSERT INTO project_members (project_id, user_id) VALUES (?, ?)");
foreach ($members as $m) {
    mysqli_stmt_bind_param($stmt_mem, "ii", $m[0], $m[1]);
    mysqli_stmt_execute($stmt_mem);
}
mysqli_stmt_close($stmt_mem);

// 4. Insert 5–8 sample tasks spread across todo, in_progress, in_review, done
$tasks = [
    [$proj_web, 'Design system tokens and UI kit', 'Define CSS variables for colors, typography, buttons, and responsive breakpoints.', $alex_id, $sarah_id, 'high', 'done', date('Y-m-d', strtotime('+3 days'))],
    [$proj_web, 'Landing page hero & responsive layout', 'Implement accessible hero section with clean CTAs and modern grid.', $alex_id, $sarah_id, 'high', 'in_progress', date('Y-m-d', strtotime('+7 days'))],
    [$proj_web, 'Authentication flow & session guards', 'Implement secure role-based session login, logout, and password hashing.', $jordan_id, $sarah_id, 'high', 'in_review', date('Y-m-d', strtotime('+5 days'))],
    [$proj_web, 'Cross-browser QA and accessibility review', 'Test keyboard navigation, contrast ratios, and screen reader labels.', $jordan_id, $sarah_id, 'medium', 'todo', date('Y-m-d', strtotime('+12 days'))],
    [$proj_api, 'OAuth2 & JWT endpoint architecture', 'Build token issuance and refresh token rotation handlers.', $alex_id, $sarah_id, 'high', 'todo', date('Y-m-d', strtotime('+10 days'))],
    [$proj_api, 'REST API rate limiting and audit logging', 'Add middleware to throttle requests and record sensitive access logs.', $alex_id, $sarah_id, 'medium', 'in_progress', date('Y-m-d', strtotime('+6 days'))],
    [$proj_api, 'Automated integration test suite', 'Write comprehensive endpoint validation tests with mock datasets.', $jordan_id, $sarah_id, 'medium', 'todo', date('Y-m-d', strtotime('+14 days'))]
];

$task_ids = [];
$stmt_task = mysqli_prepare($conn, "INSERT INTO tasks (project_id, title, description, assigned_to, created_by, priority, status, due_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

foreach ($tasks as $t) {
    mysqli_stmt_bind_param($stmt_task, "ississss", $t[0], $t[1], $t[2], $t[3], $t[4], $t[5], $t[6], $t[7]);
    mysqli_stmt_execute($stmt_task);
    $task_ids[] = mysqli_insert_id($conn);
}
mysqli_stmt_close($stmt_task);

// 5. Insert task comments
$comments = [
    [$task_ids[0], $sarah_id, 'Looks fantastic! The color palette and token contrast ratios pass WCAG AAA.'],
    [$task_ids[1], $alex_id, 'Hero section flex layout is complete, currently wrapping up the feature grid.'],
    [$task_ids[2], $jordan_id, 'Session checks tested against unauthenticated direct URL access. Verification passes.']
];

$stmt_com = mysqli_prepare($conn, "INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)");
foreach ($comments as $c) {
    mysqli_stmt_bind_param($stmt_com, "iis", $c[0], $c[1], $c[2]);
    mysqli_stmt_execute($stmt_com);
}
mysqli_stmt_close($stmt_com);

// 6. Insert sample activity log entries
$logs = [
    [$sarah_id, 'Created project "Website Redesign"'],
    [$sarah_id, 'Assigned task "Design system tokens and UI kit" to Alex Dev'],
    [$alex_id, 'Moved task "Design system tokens and UI kit" to Done'],
    [$jordan_id, 'Submitted task "Authentication flow & session guards" for review'],
    [$admin_id, 'System initial setup completed']
];

$stmt_log = mysqli_prepare($conn, "INSERT INTO activity_log (user_id, action) VALUES (?, ?)");
foreach ($logs as $l) {
    mysqli_stmt_bind_param($stmt_log, "is", $l[0], $l[1]);
    mysqli_stmt_execute($stmt_log);
}
mysqli_stmt_close($stmt_log);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskFlow — Setup & Seed Completed</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #0f172a; padding: 40px; margin: 0; }
        .card { max-width: 680px; margin: 0 auto; background: #ffffff; padding: 32px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; }
        h2 { margin-top: 0; color: #1e293b; }
        .success { background: #ecfdf5; border-left: 4px solid #10b981; padding: 14px; border-radius: 6px; margin-bottom: 24px; color: #065f46; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #e2e8f0; }
        th { background: #f1f5f9; color: #475569; font-weight: 600; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 13px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 12px; font-weight: 500; }
        .badge-admin { background: #fee2e2; color: #991b1b; }
        .badge-pm { background: #fef3c7; color: #92400e; }
        .badge-dev { background: #e0f2fe; color: #075985; }
        .btn { display: inline-block; padding: 10px 20px; background: #2563eb; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 500; }
        .summary-list { margin: 16px 0; padding-left: 20px; color: #475569; font-size: 14px; }
    </style>
</head>
<body>
    <div class="card">
        <h2>TaskFlow Database Successfully Setup & Seeded!</h2>
        <div class="success">
            <?php if ($schema_created): ?>
                <strong>Schema initialized!</strong> All database tables were created automatically from schema.sql, and seed data was inserted.
            <?php else: ?>
                <strong>Seed successful!</strong> All tables were populated with real sample data and runtime password hashes.
            <?php endif; ?>
        </div>

        <ul class="summary-list">
            <li><strong>4 Users</strong> inserted with password <code>Password123!</code></li>
            <li><strong>3 Projects</strong> created with Sarah Chen as Project Manager</li>
            <li><strong>5 Project Member</strong> associations added</li>
            <li><strong>7 Tasks</strong> distributed across <code>todo</code>, <code>in_progress</code>, <code>in_review</code>, <code>done</code></li>
            <li><strong>3 Comments</strong> and <strong>5 Audit Log</strong> entries created</li>
        </ul>

        <h3>Ready-to-Use Test Accounts</h3>
        <table>
            <thead>
                <tr><th>Role</th><th>Full Name</th><th>Email</th><th>Password</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="badge badge-admin">admin</span></td>
                    <td>System Admin</td>
                    <td><code>admin@taskflow.local</code></td>
                    <td><code>Password123!</code></td>
                </tr>
                <tr>
                    <td><span class="badge badge-pm">project_manager</span></td>
                    <td>Sarah Chen</td>
                    <td><code>manager@taskflow.local</code></td>
                    <td><code>Password123!</code></td>
                </tr>
                <tr>
                    <td><span class="badge badge-dev">team_member</span></td>
                    <td>Alex Dev</td>
                    <td><code>member1@taskflow.local</code></td>
                    <td><code>Password123!</code></td>
                </tr>
                <tr>
                    <td><span class="badge badge-dev">team_member</span></td>
                    <td>Jordan QA</td>
                    <td><code>member2@taskflow.local</code></td>
                    <td><code>Password123!</code></td>
                </tr>
            </tbody>
        </table>

        <div style="margin-top: 24px;">
            <a href="<?= BASE_URL ?>/" class="btn">Return to TaskFlow</a>
        </div>
    </div>
</body>
</html>
