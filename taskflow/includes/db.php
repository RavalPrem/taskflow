<?php
require_once __DIR__ . '/../config.php';

$isWasmer = getenv('DB_HOST') !== false
    && getenv('DB_HOST') !== '';

$conn = mysqli_init();

$flags = $isWasmer
    ? (MYSQLI_CLIENT_SSL | MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT)
    : 0;

$connected = mysqli_real_connect(
    $conn,
    DB_HOST,
    DB_USER,
    DB_PASS,
    DB_NAME,
    DB_PORT,
    null,
    $flags
);

if (!$connected) {
    error_log("Database connection failure: " . mysqli_connect_error());
    die("Unable to connect to the database. Please check the database configuration.");
}

mysqli_set_charset($conn, 'utf8mb4');