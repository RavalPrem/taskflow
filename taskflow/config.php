<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'taskflow_db');

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
