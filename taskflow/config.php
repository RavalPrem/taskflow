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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/icons.php';
