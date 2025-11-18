<?php
// ===== api/participants/admin-delete.php =====
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$auth = new Auth();
if (!$auth->check() || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$participantId = intval($data['participant_id'] ?? 0);

if (!$participantId) {
    echo json_encode(['success' => false, 'message' => 'Participant ID required']);
    exit;
}

$db = Database::getInstance();

try {
    $deleted = $db->delete('participants', ['participant_id' => $participantId]);

    if ($deleted) {
        echo json_encode(['success' => true, 'message' => 'Participant deleted!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error occurred']);
}
