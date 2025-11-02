<?php
/**
 * Logout Handler
 * Crystal Chess Tournament Booking Platform
 * File: api/auth/logout.php
 */

// Load configuration
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Auth.php';

// Perform logout
Auth::logout();

// Redirect to home page
header('Location: ' . BASE_URL . '/home?logged_out=1');
exit;