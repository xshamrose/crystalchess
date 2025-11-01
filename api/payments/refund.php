<?php
/**
 * Payment Refund API
 * Process refunds for bookings
 * File: api/payments/refund.php
 */

session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Mailer.php';

header('Content-Type: application/json');

// Check authentication
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$db = new Database();
$mailer = new Mailer();
$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'];

try {
    // Get request data
    $data = json_decode(file_get_contents('php://input'), true);
    $bookingId = $data['booking_id'] ?? null;
    $refundReason = $data['reason'] ?? 'Requested by user';
    
    if (!$bookingId) {
        throw new Exception('Booking ID is required');
    }
    
    // Get booking and payment details
    $booking = $db->query(
        "SELECT b.*, p.payment_id, p.transaction_id, p.payment_gateway, p.amount,
                e.event_name, e.event_date, e.organizer_id,
                u.email, u.full_name
         FROM bookings b
         JOIN payments p ON b.booking_id = p.booking_id
         JOIN events e ON b.event_id = e.event_id
         JOIN users u ON b.user_id = u.user_id
         WHERE b.booking_id = ? AND p.payment_status = 'completed'",
        [$bookingId]
    )->fetch();
    
    if (!$booking) {
        throw new Exception('Booking not found or already refunded');
    }
    
    // Authorization check
    $canRefund = false;
    if ($userType === 'admin') {
        $canRefund = true;
    } elseif ($userType === 'organizer' && $booking['organizer_id'] == $userId) {
        $canRefund = true;
    } elseif ($booking['user_id'] == $userId) {
        // Users can only refund if event is more than 48 hours away
        $hoursUntilEvent = (strtotime($booking['event_date']) - time()) / 3600;
        if ($hoursUntilEvent < 48) {
            throw new Exception('Refunds must be requested at least 48 hours before the event');
        }
        $canRefund = true;
    }
    
    if (!$canRefund) {
        throw new Exception('You do not have permission to refund this booking');
    }
    
    // Process refund based on gateway
    $refundSuccess = false;
    $refundResponse = null;
    
    if ($booking['payment_gateway'] === 'stripe') {
        $refundSuccess = processStripeRefund($booking['transaction_id'], $booking['amount']);
    } elseif ($booking['payment_gateway'] === 'paypal') {
        $refundSuccess = processPayPalRefund($booking['transaction_id'], $booking['amount']);
    } else {
        // Demo mode - auto approve
        $refundSuccess = true;
    }
    
    if (!$refundSuccess) {
        throw new Exception('Refund processing failed. Please try again or contact support.');
    }
    
    // Update payment record
    $db->query(
        "UPDATE payments 
         SET payment_status = 'refunded',
             refund_date = NOW(),
             refund_amount = amount,
             gateway_response = ?
         WHERE payment_id = ?",
        [json_encode(['reason' => $refundReason]), $booking['payment_id']]
    );
    
    // Update booking
    $db->query(
        "UPDATE bookings 
         SET booking_status = 'cancelled',
             payment_status = 'refunded'
         WHERE booking_id = ?",
        [$bookingId]
    );
    
    // Update event capacity
    $db->query(
        "UPDATE events 
         SET current_bookings = current_bookings - 1
         WHERE event_id = ?",
        [$booking['event_id']]
    );
    
    // Send refund confirmation email
    $mailer->sendRefundNotification([
        'email' => $booking['email'],
        'name' => $booking['full_name'],
        'booking_reference' => $booking['booking_reference'],
        'event_name' => $booking['event_name'],
        'amount' => $booking['amount']
    ]);
    
    // Create notification
    $db->query(
        "INSERT INTO notifications (user_id, notification_type, subject, message, notification_status)
         VALUES (?, 'email', ?, ?, 'sent')",
        [
            $booking['user_id'],
            'Refund Processed',
            "Your refund of $" . number_format($booking['amount'], 2) . " has been processed for " . $booking['event_name']
        ]
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Refund processed successfully',
        'refund_amount' => $booking['amount'],
        'booking_reference' => $booking['booking_reference']
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Process Stripe Refund
 */
function processStripeRefund($transactionId, $amount) {
    // TODO: Implement actual Stripe refund in production
    /*
    require_once __DIR__ . '/../../vendor/autoload.php';
    \Stripe\Stripe::setApiKey('your_stripe_secret_key');
    
    try {
        $refund = \Stripe\Refund::create([
            'payment_intent' => $transactionId,
            'amount' => $amount * 100
        ]);
        return $refund->status === 'succeeded';
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('Stripe refund error: ' . $e->getMessage());
        return false;
    }
    */
    
    // Demo mode
    error_log("DEMO: Stripe refund processed for $transactionId - Amount: $amount");
    return true;
}

/**
 * Process PayPal Refund
 */
function processPayPalRefund($transactionId, $amount) {
    // TODO: Implement actual PayPal refund in production
    /*
    require_once __DIR__ . '/../../vendor/autoload.php';
    
    $apiContext = new \PayPal\Rest\ApiContext(
        new \PayPal\Auth\OAuthTokenCredential(
            'your_client_id',
            'your_secret'
        )
    );
    
    try {
        $refundRequest = new \PayPal\Api\RefundRequest();
        $refundRequest->setAmount(new \PayPal\Api\Amount([
            'total' => $amount,
            'currency' => 'USD'
        ]));
        
        $sale = \PayPal\Api\Sale::get($transactionId, $apiContext);
        $refund = $sale->refundSale($refundRequest, $apiContext);
        
        return $refund->getState() === 'completed';
    } catch (Exception $e) {
        error_log('PayPal refund error: ' . $e->getMessage());
        return false;
    }
    */
    
    // Demo mode
    error_log("DEMO: PayPal refund processed for $transactionId - Amount: $amount");
    return true;
}