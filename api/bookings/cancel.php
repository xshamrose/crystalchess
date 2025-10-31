<?php
// api/bookings/cancel.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/BookingManager.php';

$auth = new Auth();
$bookingManager = new BookingManager();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login to continue']);
    exit;
}

$user = $auth->getUser();

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get booking ID
$bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit;
}

// Cancel booking
$result = $bookingManager->cancelBooking($bookingId, $user['user_id']);

echo json_encode($result);