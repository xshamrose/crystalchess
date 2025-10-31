<?php
// api/bookings/create.php
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

// Get POST data
$eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
$participantName = trim($_POST['participant_name'] ?? '');
$participantEmail = trim($_POST['participant_email'] ?? '');
$participantPhone = trim($_POST['participant_phone'] ?? '');
$participantAge = filter_input(INPUT_POST, 'participant_age', FILTER_VALIDATE_INT);
$playerType = $_POST['player_type'] ?? 'self';

// Validation
$errors = [];

if (!$eventId) {
    $errors[] = 'Invalid event selected';
}

if (empty($participantName)) {
    $errors[] = 'Participant name is required';
}

if (!empty($participantEmail) && !filter_var($participantEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email address';
}

if (!empty($participantAge) && ($participantAge < 5 || $participantAge > 100)) {
    $errors[] = 'Age must be between 5 and 100';
}

if (!in_array($playerType, ['self', 'student', 'team_member'])) {
    $errors[] = 'Invalid player type';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

// Create booking
$bookingData = [
    'event_id' => $eventId,
    'user_id' => $user['user_id'],
    'participant_name' => $participantName,
    'participant_email' => $participantEmail,
    'participant_phone' => $participantPhone,
    'participant_age' => $participantAge,
    'player_type' => $playerType
];

$result = $bookingManager->createBooking($bookingData);

if ($result['success']) {
    // Store booking ID in session for checkout page
    $_SESSION['pending_booking_id'] = $result['booking_id'];
    
    // Return success with redirect URL
    echo json_encode([
        'success' => true,
        'message' => 'Booking created successfully',
        'booking_id' => $result['booking_id'],
        'booking_reference' => $result['booking_reference'],
        'redirect_url' => BASE_URL . '/modules/events/checkout.php'
    ]);
} else {
    echo json_encode($result);
}