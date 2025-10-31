<?php
// api/payments/process.php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/BookingManager.php';
require_once __DIR__ . '/../../core/Database.php';

$auth = new Auth();
$bookingManager = new BookingManager();
$db = new Database();

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
$bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
$paymentMethod = $_POST['payment_method'] ?? 'stripe';

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit;
}

// Get booking details
$booking = $bookingManager->getBookingDetails($bookingId);

if (!$booking || $booking['user_id'] != $user['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Booking not found']);
    exit;
}

if ($booking['payment_status'] === 'paid') {
    echo json_encode(['success' => false, 'message' => 'Payment already completed']);
    exit;
}

try {
    // ============================================
    // PAYMENT GATEWAY INTEGRATION
    // ============================================
    // NOTE: This is a DEMO implementation
    // In production, integrate with actual payment gateways:
    // - Stripe: https://stripe.com/docs/api
    // - PayPal: https://developer.paypal.com/
    // - Razorpay: https://razorpay.com/docs/api/
    
    $transactionId = 'TXN_' . uniqid() . '_' . time();
    $paymentSuccess = false;
    $gatewayResponse = '';
    
    if ($paymentMethod === 'stripe') {
        // DEMO: Stripe Payment Processing
        // In production, use: \Stripe\Charge::create() or \Stripe\PaymentIntent::create()
        
        /*
        // Real Stripe Integration Example:
        require_once __DIR__ . '/../../vendor/autoload.php';
        \Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);
        
        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => $booking['amount_paid'] * 100, // Amount in cents
            'currency' => 'usd',
            'description' => 'Chess Tournament - ' . $booking['event_name'],
            'metadata' => [
                'booking_id' => $bookingId,
                'booking_reference' => $booking['booking_reference']
            ]
        ]);
        
        $transactionId = $paymentIntent->id;
        $paymentSuccess = $paymentIntent->status === 'succeeded';
        $gatewayResponse = json_encode($paymentIntent);
        */
        
        // DEMO: Simulate successful payment
        $paymentSuccess = true;
        $gatewayResponse = json_encode([
            'status' => 'succeeded',
            'method' => 'stripe',
            'amount' => $booking['amount_paid'],
            'demo' => true
        ]);
        
    } elseif ($paymentMethod === 'paypal') {
        // DEMO: PayPal Payment Processing
        // In production, use PayPal SDK
        
        /*
        // Real PayPal Integration Example:
        require_once __DIR__ . '/../../vendor/autoload.php';
        
        $paypal = new \PayPal\Rest\ApiContext(
            new \PayPal\Auth\OAuthTokenCredential(
                PAYPAL_CLIENT_ID,
                PAYPAL_SECRET
            )
        );
        
        // Create payment, execute, etc.
        */
        
        // DEMO: Simulate successful payment
        $paymentSuccess = true;
        $gatewayResponse = json_encode([
            'status' => 'COMPLETED',
            'method' => 'paypal',
            'amount' => $booking['amount_paid'],
            'demo' => true
        ]);
    }
    
    // ============================================
    // SAVE PAYMENT RECORD
    // ============================================
    if ($paymentSuccess) {
        // Insert payment record
        $sql = "INSERT INTO payments (
                    booking_id, transaction_id, payment_gateway, 
                    amount, currency, payment_status, gateway_response
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $db->query($sql, [
            $bookingId,
            $transactionId,
            $paymentMethod,
            $booking['amount_paid'],
            'USD',
            'completed',
            $gatewayResponse
        ]);
        
        // Confirm booking
        $confirmResult = $bookingManager->confirmBooking($bookingId, $transactionId);
        
        if ($confirmResult['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'Payment successful',
                'transaction_id' => $transactionId,
                'booking_reference' => $booking['booking_reference']
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Payment processed but booking confirmation failed'
            ]);
        }
    } else {
        // Payment failed
        $sql = "INSERT INTO payments (
                    booking_id, transaction_id, payment_gateway, 
                    amount, currency, payment_status, gateway_response
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $db->query($sql, [
            $bookingId,
            $transactionId,
            $paymentMethod,
            $booking['amount_paid'],
            'USD',
            'failed',
            $gatewayResponse
        ]);
        
        echo json_encode([
            'success' => false,
            'message' => 'Payment processing failed. Please try again.'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Payment Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Payment processing error. Please contact support.'
    ]);
}