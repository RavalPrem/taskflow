<?php
require_once __DIR__ . '/../config.php';

// Establish MySQL connection
$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    error_log("Database connection failure: " . mysqli_connect_error());
    die("<div style='font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,sans-serif;background:#fef2f2;border:1px solid #fecaca;padding:24px;border-radius:10px;max-width:540px;margin:60px auto;color:#991b1b;box-shadow:0 4px 12px rgba(0,0,0,0.05);'>
        <h3 style='margin-top:0;font-size:18px;'>Database Connection Error</h3>
        <p style='font-size:14px;line-height:1.5;margin-bottom:0;'>Unable to connect to MySQL database <code>" . htmlspecialchars(DB_NAME) . "</code>. Please verify that MySQL is running in XAMPP and that the database has been created.</p>
    </div>");
}

mysqli_set_charset($conn, 'utf8mb4');
