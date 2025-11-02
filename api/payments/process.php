<?php
/**
 * Payment Processing API - FINAL VERSION
 * File: api/payments/process.php
 * Matches your exact database structure
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!Auth::check()) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Please login.'
    ]);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

$userId = Auth::getUserId();
$bookingId = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
$paymentMethod = $_POST['payment_method'] ?? '';

// Validate input
if ($bookingId === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid booking ID.'
    ]);
    exit;
}

if (empty($paymentMethod) || !in_array($paymentMethod, ['stripe', 'paypal', 'cash'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Please select a valid payment method.'
    ]);
    exit;
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Get booking details with FOR UPDATE lock
    $db->query("
        SELECT b.*, e.event_id, e.event_name, e.max_capacity, e.current_bookings
        FROM bookings b
        JOIN events e ON b.event_id = e.event_id
        WHERE b.booking_id = :booking_id 
          AND b.user_id = :user_id
        FOR UPDATE
    ");
    $db->bind(':booking_id', $bookingId);
    $db->bind(':user_id', $userId);
    $booking = $db->fetch();
    
    if (!$booking) {
        throw new Exception('Booking not found or unauthorized access.');
    }
    
    // Check if already paid
    if ($booking['payment_status'] === 'completed') {
        $pdo->rollBack();
        echo json_encode([
            'success' => true,
            'message' => 'Payment already completed.',
            'booking_reference' => $booking['booking_reference']
        ]);
        exit;
    }
    
    // Check if booking is cancelled
    if ($booking['booking_status'] === 'cancelled') {
        throw new Exception('This booking has been cancelled.');
    }
    
    // Simulate payment processing (replace with real gateway in production)
    $paymentSuccess = true;
    $transactionId = 'TXN-' . strtoupper(uniqid());
    
    // ==========================================
    // UPDATE BOOKING STATUS
    // ==========================================
    // Your bookings table columns: booking_id, event_id, user_id, booking_reference, 
    // participant_name, participant_email, participant_phone, participant_age, 
    // player_type, booking_status, payment_status, amount_paid, booking_date, updated_at
    
    $db->query("
        UPDATE bookings 
        SET booking_status = 'confirmed',
            payment_status = 'completed',
            updated_at = NOW()
        WHERE booking_id = :booking_id
    ");
    $db->bind(':booking_id', $bookingId);
    
    if (!$db->execute()) {
        throw new Exception('Failed to update booking status.');
    }
    
    // ==========================================
    // CREATE PAYMENT RECORD
    // ==========================================
    // Your payments table columns: payment_id, booking_id, transaction_id, 
    // payment_gateway, amount, currency, payment_status, gateway_response,
    // refund_date, refund_amount, payment_date
    
    $gatewayResponse = json_encode([
        'status' => 'success',
        'transaction_id' => $transactionId,
        'payment_method' => $paymentMethod,
        'amount' => $booking['amount_paid'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $db->query("
        INSERT INTO payments (
            booking_id, 
            transaction_id, 
            payment_gateway, 
            amount, 
            currency, 
            payment_status, 
            gateway_response, 
            payment_date
        ) VALUES (
            :booking_id, 
            :transaction_id, 
            :payment_gateway,
            :amount, 
            'USD', 
            'completed', 
            :gateway_response, 
            NOW()
        )
    ");
    $db->bind(':booking_id', $bookingId);
    $db->bind(':transaction_id', $transactionId);
    $db->bind(':payment_gateway', $paymentMethod);
    $db->bind(':amount', $booking['amount_paid']);
    $db->bind(':gateway_response', $gatewayResponse);
    
    if (!$db->execute()) {
        throw new Exception('Failed to log payment transaction.');
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Clear session
    unset($_SESSION['pending_booking_id']);
    
    // Log success
    error_log("Payment successful: Booking #{$bookingId}, Transaction #{$transactionId}");
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Payment successful! Your booking is confirmed.',
        'booking_reference' => $booking['booking_reference'],
        'transaction_id' => $transactionId,
        'amount' => number_format($booking['amount_paid'], 2)
    ]);
    
} catch (Exception $e) {
    // Rollback on any error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log error
    error_log("Payment Error [Booking #{$bookingId}]: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
exit;