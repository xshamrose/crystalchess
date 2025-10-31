<?php
/**
 * Payment Webhook Handler
 * Handles payment gateway callbacks (Stripe, PayPal)
 * File: api/payments/webhook.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Mailer.php';

// Disable session for webhooks (they're called by payment gateways)
header('Content-Type: application/json');

$db = new Database();
$mailer = new Mailer();

// Determine which gateway is calling
$gateway = $_GET['gateway'] ?? 'stripe';

try {
    if ($gateway === 'stripe') {
        handleStripeWebhook($db, $mailer);
    } elseif ($gateway === 'paypal') {
        handlePayPalWebhook($db, $mailer);
    } else {
        throw new Exception('Invalid payment gateway');
    }
} catch (Exception $e) {
    error_log('Webhook Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Handle Stripe Webhook
 */
function handleStripeWebhook($db, $mailer) {
    // Get the raw POST body
    $payload = @file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    
    // TODO: Add your Stripe webhook secret
    // $endpoint_secret = 'whsec_...';
    
    // For demo/testing, we'll parse the payload directly
    // In production, verify signature:
    // $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    
    $event = json_decode($payload, true);
    
    if (!$event) {
        throw new Exception('Invalid payload');
    }
    
    // Handle different event types
    switch ($event['type']) {
        case 'payment_intent.succeeded':
            handlePaymentSuccess($db, $mailer, $event['data']['object'], 'stripe');
            break;
            
        case 'payment_intent.payment_failed':
            handlePaymentFailed($db, $event['data']['object'], 'stripe');
            break;
            
        case 'charge.refunded':
            handleRefund($db, $mailer, $event['data']['object'], 'stripe');
            break;
            
        default:
            error_log('Unhandled Stripe event: ' . $event['type']);
    }
    
    http_response_code(200);
    echo json_encode(['status' => 'success']);
}

/**
 * Handle PayPal Webhook
 */
function handlePayPalWebhook($db, $mailer) {
    $payload = @file_get_contents('php://input');
    $event = json_decode($payload, true);
    
    if (!$event) {
        throw new Exception('Invalid payload');
    }
    
    // TODO: Verify PayPal webhook signature in production
    
    switch ($event['event_type']) {
        case 'PAYMENT.CAPTURE.COMPLETED':
            handlePaymentSuccess($db, $mailer, $event['resource'], 'paypal');
            break;
            
        case 'PAYMENT.CAPTURE.DENIED':
            handlePaymentFailed($db, $event['resource'], 'paypal');
            break;
            
        case 'PAYMENT.CAPTURE.REFUNDED':
            handleRefund($db, $mailer, $event['resource'], 'paypal');
            break;
            
        default:
            error_log('Unhandled PayPal event: ' . $event['event_type']);
    }
    
    http_response_code(200);
    echo json_encode(['status' => 'success']);
}

/**
 * Handle Successful Payment
 */
function handlePaymentSuccess($db, $mailer, $paymentData, $gateway) {
    // Extract transaction ID
    $transactionId = $gateway === 'stripe' 
        ? $paymentData['id'] 
        : $paymentData['id'];
    
    // Find payment record
    $payment = $db->query(
        "SELECT p.*, b.booking_id, b.booking_reference, b.user_id, 
                e.event_name, u.email, u.full_name
         FROM payments p
         JOIN bookings b ON p.booking_id = b.booking_id
         JOIN events e ON b.event_id = e.event_id
         JOIN users u ON b.user_id = u.user_id
         WHERE p.transaction_id = ?",
        [$transactionId]
    )->fetch();
    
    if (!$payment) {
        error_log("Payment not found for transaction: $transactionId");
        return;
    }
    
    // Update payment status
    $db->query(
        "UPDATE payments 
         SET payment_status = 'completed',
             gateway_response = ?
         WHERE payment_id = ?",
        [json_encode($paymentData), $payment['payment_id']]
    );
    
    // Update booking status
    $db->query(
        "UPDATE bookings 
         SET payment_status = 'paid',
             booking_status = 'confirmed'
         WHERE booking_id = ?",
        [$payment['booking_id']]
    );
    
    // Send confirmation email
    $mailer->sendBookingConfirmation([
        'email' => $payment['email'],
        'name' => $payment['full_name'],
        'booking_reference' => $payment['booking_reference'],
        'event_name' => $payment['event_name']
    ]);
    
    error_log("Payment confirmed: $transactionId");
}

/**
 * Handle Failed Payment
 */
function handlePaymentFailed($db, $paymentData, $gateway) {
    $transactionId = $gateway === 'stripe' 
        ? $paymentData['id'] 
        : $paymentData['id'];
    
    // Update payment status
    $db->query(
        "UPDATE payments 
         SET payment_status = 'failed',
             gateway_response = ?
         WHERE transaction_id = ?",
        [json_encode($paymentData), $transactionId]
    );
    
    // Update booking status
    $db->query(
        "UPDATE bookings b
         JOIN payments p ON b.booking_id = p.booking_id
         SET b.payment_status = 'failed'
         WHERE p.transaction_id = ?",
        [$transactionId]
    );
    
    error_log("Payment failed: $transactionId");
}

/**
 * Handle Refund
 */
function handleRefund($db, $mailer, $refundData, $gateway) {
    $transactionId = $gateway === 'stripe'
        ? $refundData['payment_intent']
        : $refundData['id'];
    
    $refundAmount = $gateway === 'stripe'
        ? $refundData['amount'] / 100
        : $refundData['amount']['value'];
    
    // Update payment record
    $db->query(
        "UPDATE payments 
         SET payment_status = 'refunded',
             refund_date = NOW(),
             refund_amount = ?,
             gateway_response = ?
         WHERE transaction_id = ?",
        [$refundAmount, json_encode($refundData), $transactionId]
    );
    
    // Update booking status
    $payment = $db->query(
        "SELECT p.*, b.booking_id, b.user_id, u.email, u.full_name
         FROM payments p
         JOIN bookings b ON p.booking_id = b.booking_id
         JOIN users u ON b.user_id = u.user_id
         WHERE p.transaction_id = ?",
        [$transactionId]
    )->fetch();
    
    if ($payment) {
        $db->query(
            "UPDATE bookings 
             SET payment_status = 'refunded',
                 booking_status = 'cancelled'
             WHERE booking_id = ?",
            [$payment['booking_id']]
        );
        
        // Send refund notification
        $mailer->sendRefundNotification([
            'email' => $payment['email'],
            'name' => $payment['full_name'],
            'amount' => $refundAmount
        ]);
    }
    
    error_log("Refund processed: $transactionId - Amount: $refundAmount");
}