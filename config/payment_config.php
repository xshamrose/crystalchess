<?php

/**
 * Payment Gateway Configuration
 * Crystal Chess Tournament Booking Platform
 */

// Default Payment Gateway
define('PAYMENT_GATEWAY', 'stripe'); // stripe, paypal, razorpay

// Currency Settings
define('CURRENCY', 'USD');
define('CURRENCY_SYMBOL', '$');

// Stripe Configuration
define('STRIPE_ENABLED', true);
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_your_key_here'); // Update with your key
define('STRIPE_SECRET_KEY', 'sk_test_your_key_here'); // Update with your key
define('STRIPE_WEBHOOK_SECRET', 'whsec_your_webhook_secret'); // Update with your secret

// PayPal Configuration
define('PAYPAL_ENABLED', false);
define('PAYPAL_MODE', 'sandbox'); // sandbox or live
define('PAYPAL_CLIENT_ID', 'your_paypal_client_id'); // Update
define('PAYPAL_SECRET', 'your_paypal_secret'); // Update

// Razorpay Configuration (For India)
define('RAZORPAY_ENABLED', false);
define('RAZORPAY_KEY_ID', 'your_razorpay_key_id'); // Update
define('RAZORPAY_KEY_SECRET', 'your_razorpay_key_secret'); // Update

// Payment Settings
define('PAYMENT_SUCCESS_URL', SITE_URL . '/modules/events/payment-success.php');
define('PAYMENT_CANCEL_URL', SITE_URL . '/modules/events/payment-cancel.php');
define('PAYMENT_WEBHOOK_URL', SITE_URL . '/api/payments/webhook.php');

// Refund Policy
define('REFUND_ENABLED', true);
define('REFUND_DAYS_BEFORE_EVENT', 7); // Days before event to allow refunds

// Payment Processing Fees
define('PLATFORM_FEE_PERCENTAGE', 5); // 5% platform fee
define('INCLUDE_FEE_IN_PRICE', false); // If true, fee is added to event price

/**
 * Get Stripe Configuration
 */
function getStripeConfig()
{
    return [
        'publishable_key' => STRIPE_PUBLISHABLE_KEY,
        'secret_key' => STRIPE_SECRET_KEY,
        'webhook_secret' => STRIPE_WEBHOOK_SECRET,
        'currency' => strtolower(CURRENCY),
    ];
}

/**
 * Get PayPal Configuration
 */
function getPayPalConfig()
{
    return [
        'mode' => PAYPAL_MODE,
        'client_id' => PAYPAL_CLIENT_ID,
        'secret' => PAYPAL_SECRET,
        'currency' => CURRENCY,
    ];
}

/**
 * Get Razorpay Configuration
 */
function getRazorpayConfig()
{
    return [
        'key_id' => RAZORPAY_KEY_ID,
        'key_secret' => RAZORPAY_KEY_SECRET,
        'currency' => CURRENCY,
    ];
}

/**
 * Calculate Total Amount with Platform Fee
 */
function calculateTotalAmount($baseAmount)
{
    if (INCLUDE_FEE_IN_PRICE) {
        $fee = ($baseAmount * PLATFORM_FEE_PERCENTAGE) / 100;
        return round($baseAmount + $fee, 2);
    }
    return round($baseAmount, 2);
}

/**
 * Format Currency
 */
function formatCurrency($amount)
{
    return CURRENCY_SYMBOL . number_format($amount, 2);
}
