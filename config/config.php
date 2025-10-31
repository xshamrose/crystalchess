<?php

/**
 * Main Configuration File
 * Crystal Chess Tournament Booking Platform
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Environment
define('ENVIRONMENT', 'development'); // Change to 'production' for live site

// Site Configuration
define('SITE_NAME', 'Crystal Chess');
define('SITE_URL', 'http://localhost:8000'); // ✅ FIXED - matches your setup
define('SITE_EMAIL', 'info@crystalchess.com');
define('SITE_PHONE', '+1234567890');

// Path Configuration
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('CORE_PATH', ROOT_PATH . '/core');
define('MODULES_PATH', ROOT_PATH . '/modules');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('ASSETS_PATH', ROOT_PATH . '/assets');

// URL Configuration
define('BASE_URL', 'http://localhost:8000'); // ✅ FIXED
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');

// Security
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 900); // 15 minutes

// Upload Settings
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg']);
define('PROFILE_UPLOAD_PATH', UPLOADS_PATH . '/profiles');
define('EVENT_UPLOAD_PATH', UPLOADS_PATH . '/events');

// Pagination
define('ITEMS_PER_PAGE', 12);
define('ADMIN_ITEMS_PER_PAGE', 20);

// Email Configuration
define('SMTP_ENABLED', true);
define('SMTP_HOST', 'smtp.gmail.com'); // Update for production
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com'); // Update
define('SMTP_PASSWORD', 'your-app-password'); // Update
define('SMTP_SECURE', 'tls');
define('MAIL_FROM_NAME', SITE_NAME);
define('MAIL_FROM_EMAIL', SITE_EMAIL);

// SMS Configuration (Optional - Twilio)
define('SMS_ENABLED', false);
define('TWILIO_SID', '');
define('TWILIO_TOKEN', '');
define('TWILIO_FROM', '');

// Timezone
date_default_timezone_set('America/New_York'); // Update to your timezone

// Error Reporting
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', ROOT_PATH . '/error_log');
}

// Auto-load core classes
spl_autoload_register(function ($class) {
    $file = CORE_PATH . '/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Load database connection
require_once __DIR__ . '/database.php';

// Load utility functions
require_once INCLUDES_PATH . '/functions.php';

// Generate CSRF token
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
