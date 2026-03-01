<?php
// ============================================================
//  db_connect.php
//  Database connection using PDO
//  Event Registration Management System
// ============================================================

// ── Timezone ────────────────────────────────────────────────
// Set once here so every date() call across all pages is correct.
// Philippines Standard Time = UTC+8, no daylight saving.
date_default_timezone_set('Asia/Manila');

define('DB_HOST', 'localhost');
define('DB_NAME', 'event_registration_db');
define('DB_USER', 'root');       // Default XAMPP username
define('DB_PASS', '');           // Default XAMPP password (empty)
define('DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // Throw exceptions on error
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // Fetch as associative array
    PDO::ATTR_EMULATE_PREPARES   => false,                    // Use real prepared statements
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    // Sync MySQL session timezone to match PHP — ensures NOW(), CURDATE(),
    // and all timestamp comparisons use Philippine Standard Time (UTC+8).
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    // Do NOT expose raw error in production — log it instead
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed. Please try again later.'
    ]));
}