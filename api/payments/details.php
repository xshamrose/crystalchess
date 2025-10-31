<?php
/**
 * Payment Details API
 * Get detailed payment information
 * File: api/payments/details.php
 */

session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';

header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$paymentId = $_GET['id'] ?? null;

if (!$paymentId) {
    echo json_encode(['success' => false, 'message' => 'Payment ID required']);
    exit;
}

$db = new Database();
$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'];

try {
    // Get payment details
    $payment = $db->query(
        "SELECT p.*, b.booking_reference, b.user_id, b.participant_name,
                e.event_name, e.event_date, e.organizer_id,
                u.full_name, u.email
         FROM payments p
         JOIN bookings b ON p.booking_id = b.booking_id
         JOIN events e ON b.event_id = e.event_id
         JOIN users u ON b.user_id = u.user_id
         WHERE p.payment_id = ?",
        [$paymentId]
    )->fetch();
    
    if (!$payment) {
        throw new Exception('Payment not found');
    }
    
    // Authorization check
    $canView = false;
    if ($userType === 'admin') {
        $canView = true;
    } elseif ($userType === 'organizer' && $payment['organizer_id'] == $userId) {
        $canView = true;
    } elseif ($payment['user_id'] == $userId) {
        $canView = true;
    }
    
    if (!$canView) {
        throw new Exception('Unauthorized to view this payment');
    }
    
    echo json_encode([
        'success' => true,
        'payment' => $payment
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}