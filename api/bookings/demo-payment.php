<?php
/**
 * Demo Payment API
 * Simulate payment completion for testing (will be replaced in Phase 2.2)
 */

require_once '../../config/database.php';
require_once '../../core/Database.php';
require_once '../../core/Auth.php';

session_start();
$auth = new Auth();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    $_SESSION['error'] = 'Please login to continue';
    header('Location: ../../modules/user/login.php');
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method';
    header('Location: ../../modules/events/browse.php');
    exit;
}

$db = Database::getInstance();
$userId = $_SESSION['user_id'];
$bookingId = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;

if ($bookingId === 0) {
    $_SESSION['error'] = 'Invalid booking ID';
    header('Location: ../../modules/events/browse.php');
    exit;
}

try {
    // Get booking details
    $bookingQuery = "SELECT * FROM bookings WHERE booking_id = :booking_id AND user_id = :user_id";
    $bookingStmt = $db->prepare($bookingQuery);
    $bookingStmt->execute([':booking_id' => $bookingId, ':user_id' => $userId]);
    $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        $_SESSION['error'] = 'Booking not found';
        header('Location: ../../modules/events/browse.php');
        exit;
    }
    
    // Check if already paid
    if ($booking['payment_status'] === 'paid') {
        $_SESSION['info'] = 'This booking has already been paid';
        header('Location: ../../modules/user/booking-history.php');
        exit;
    }
    
    // Start transaction
    $db->beginTransaction();
    
    // Generate demo transaction ID
    $transactionId = 'DEMO-' . strtoupper(uniqid());
    
    // Update booking status
    $updateBookingQuery = "
        UPDATE bookings 
        SET booking_status = 'confirmed', 
            payment_status = 'paid',
            updated_at = CURRENT_TIMESTAMP
        WHERE booking_id = :booking_id
    ";
    $updateBookingStmt = $db->prepare($updateBookingQuery);
    $updateBookingStmt->execute([':booking_id' => $bookingId]);
    
    // Insert payment record
    $insertPaymentQuery = "
        INSERT INTO payments (
            booking_id, transaction_id, payment_gateway, amount, 
            currency, payment_status, gateway_response
        ) VALUES (
            :booking_id, :transaction_id, 'stripe', :amount,
            'USD', 'completed', :response
        )
    ";
    $insertPaymentStmt = $db->prepare($insertPaymentQuery);
    $insertPaymentStmt->execute([
        ':booking_id' => $bookingId,
        ':transaction_id' => $transactionId,
        ':amount' => $booking['amount_paid'],
        ':response' => json_encode([
            'status' => 'success',
            'mode' => 'demo',
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => 'Demo payment processed successfully'
        ])
    ]);
    
    // Commit transaction
    $db->commit();
    
    $_SESSION['success'] = 'Payment completed successfully! Your booking is confirmed.';
    $_SESSION['booking_reference'] = $booking['booking_reference'];
    header('Location: ../../modules/user/booking-history.php');
    exit;
    
} catch (Exception $e) {
    // Rollback on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    $_SESSION['error'] = 'Payment processing failed. Please try again.';
    header('Location: ../../modules/events/checkout.php?booking_id=' . $bookingId);
    exit;
}
?>