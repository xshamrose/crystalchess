<?php
/**
 * Router for PHP Built-in Server
 * This file routes all requests through index.php
 */

// Allow direct access to static files
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Check if it's a static file that exists
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false; // Serve the file directly
}

// Route everything else through index.php
require_once __DIR__ . '/index.php';
