<?php

/**
 * Database Configuration
 * Crystal Chess Tournament Booking Platform
 */

// Development Environment
define('DB_HOST', 'localhost');
define('DB_NAME', 'crystalchess');
define('DB_USER', 'root');
define('DB_PASS', '');

// Production Environment (Update these for GoDaddy)
// define('DB_HOST', 'your_godaddy_host');
// define('DB_NAME', 'your_database_name');
// define('DB_USER', 'your_database_user');
// define('DB_PASS', 'your_database_password');

// Database Connection Options
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', 'utf8mb4_unicode_ci');

// PDO Options
$db_options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Create PDO connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
