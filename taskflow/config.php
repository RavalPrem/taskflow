<?php
// define('DB_HOST', 'localhost');
// define('DB_USER', 'root');
// define('DB_PASS', '');
// define('DB_NAME', 'taskflow_db');

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USERNAME') ?: 'root');
define('DB_PASS', getenv('DB_PASSWORD') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'taskflow_db');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));

// Dynamic base URL detection fallback to /taskflow
if (!defined('BASE_URL')) {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($scriptName, '/phpproject3/taskflow') === 0) {
        define('BASE_URL', '/phpproject3/taskflow');
    } else {
        define('BASE_URL', '/taskflow');
    }
}

// NOTE: session_start() is intentionally NOT called here anymore.
// On Wasmer (and other serverless/ephemeral PHP hosts) requests can be
// handled by different instances with no shared, persistent local
// filesystem, so PHP's default file-based session storage silently loses
// sessions between requests (login appears to work, then the very next
// page redirects back to login). We now start the session from
// includes/db.php, AFTER the MySQL connection exists, using a database
// session handler (includes/db_session_handler.php) so sessions survive
// across instances. See includes/db.php.

require_once __DIR__ . '/includes/icons.php';
