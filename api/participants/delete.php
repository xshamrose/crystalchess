<?php

/**
 * API: Delete Participant
 * File: api/participants/delete.php
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
$auth = new Auth();
if (!$auth->check()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$userId = $_SESSION['user_id'];
$db = Database::getInstance();

// Handle JSON or POST data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Validate participant ID
if (empty($data['participant_id'])) {
    echo json_encode(['success' => false, 'message' => 'Participant ID is required']);
    exit;
}

$participantId = intval($data['participant_id']);

// Check ownership
$participant = $db->query("SELECT * FROM participants WHERE participant_id = :id AND user_id = :uid")
    ->bind(':id', $participantId)
    ->bind(':uid', $userId)
    ->fetch();

if (!$participant) {
    echo json_encode(['success' => false, 'message' => 'Participant not found or access denied']);
    exit;
}

// Check if participant has active bookings
$activeBookings = $db->query("
    SELECT COUNT(*) as count 
    FROM booking_participants bp
    JOIN bookings b ON bp.booking_id = b.booking_id
    WHERE bp.participant_id = :pid 
    AND b.booking_status IN ('pending', 'confirmed')
")->bind(':pid', $participantId)->fetch();

if ($activeBookings && $activeBookings['count'] > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Cannot delete participant with active bookings. Please cancel bookings first.'
    ]);
    exit;
}

try {
    // Delete participant
    $deleted = $db->delete('participants', [
        'participant_id' => $participantId,
        'user_id' => $userId
    ]);

    if ($deleted) {
        echo json_encode([
            'success' => true,
            'message' => 'Participant deleted successfully!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete participant'
        ]);
    }
} catch (Exception $e) {
    error_log("Participant Delete Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while deleting participant'
    ]);
}
