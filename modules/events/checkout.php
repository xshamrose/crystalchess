<?php
/**
 * Checkout Page
 * File: modules/events/checkout.php
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Database.php';

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'] ?? 0;

// Get booking ID from URL or session
$bookingId = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : ($_SESSION['pending_booking_id'] ?? 0);

if ($bookingId === 0) {
    setFlash('error', 'No pending booking found.');
    header('Location: ' . BASE_URL . '/browse-events');
    exit;
}

// Get booking details with event info
$db->query("
    SELECT b.*, 
           e.event_name, e.event_date, e.event_time, e.location, 
           e.venue_address, e.entry_fee
    FROM bookings b
    JOIN events e ON b.event_id = e.event_id
    WHERE b.booking_id = :booking_id AND b.user_id = :user_id
");
$db->bind(':booking_id', $bookingId);
$db->bind(':user_id', $userId);
$booking = $db->fetch();

if (!$booking) {
    setFlash('error', 'Booking not found or unauthorized access.');
    header('Location: ' . BASE_URL . '/browse-events');
    exit;
}

// If already paid, redirect to confirmation
if ($booking['payment_status'] === 'completed') {
    header('Location: ' . BASE_URL . '/booking-confirmation?ref=' . $booking['booking_reference']);
    exit;
}

$pageTitle = 'Checkout - Crystal Chess';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-4xl mx-auto px-4">
        
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Complete Your Booking</h1>
            <p class="mt-2 text-gray-600">Review your booking details and proceed to payment</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Booking Summary -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <h2 class="text-xl font-semibold mb-4 flex items-center">
                        <i class="fas fa-file-invoice text-blue-600 mr-2"></i>
                        Booking Summary
                    </h2>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between py-3 border-b">
                            <span class="text-gray-600">Event</span>
                            <span class="font-semibold text-right"><?php echo htmlspecialchars($booking['event_name']); ?></span>
                        </div>
                        
                        <div class="flex justify-between py-3 border-b">
                            <span class="text-gray-600">Participant</span>
                            <span class="font-semibold"><?php echo htmlspecialchars($booking['participant_name']); ?></span>
                        </div>
                        
                        <div class="flex justify-between py-3 border-b">
                            <span class="text-gray-600">Date & Time</span>
                            <span class="font-semibold text-right">
                                <?php echo date('M d, Y', strtotime($booking['event_date'])); ?><br>
                                <span class="text-sm text-gray-600"><?php echo date('h:i A', strtotime($booking['event_time'])); ?></span>
                            </span>
                        </div>
                        
                        <div class="flex justify-between py-3 border-b">
                            <span class="text-gray-600">Location</span>
                            <span class="font-semibold text-right"><?php echo htmlspecialchars($booking['location']); ?></span>
                        </div>
                        
                        <div class="flex justify-between py-3 border-b">
                            <span class="text-gray-600">Booking Reference</span>
                            <span class="font-mono font-semibold text-blue-600">
                                <?php echo htmlspecialchars($booking['booking_reference']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-semibold mb-4 flex items-center">
                        <i class="fas fa-credit-card text-blue-600 mr-2"></i>
                        Payment Method
                    </h2>
                    
                    <form id="paymentForm" method="POST" action="<?php echo BASE_URL; ?>/api/payments/process.php">
                        <input type="hidden" name="booking_id" value="<?php echo $bookingId; ?>">
                        
                        <!-- Payment Options -->
                        <div class="space-y-3 mb-6">
                            <label class="flex items-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-blue-500 transition">
                                <input type="radio" name="payment_method" value="stripe" class="mr-3" checked>
                                <div class="flex-1">
                                    <div class="font-semibold">Credit/Debit Card</div>
                                    <div class="text-sm text-gray-500">Visa, Mastercard, Amex</div>
                                </div>
                                <div class="text-2xl">💳</div>
                            </label>
                            
                            <label class="flex items-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-blue-500 transition">
                                <input type="radio" name="payment_method" value="paypal" class="mr-3">
                                <div class="flex-1">
                                    <div class="font-semibold">PayPal</div>
                                    <div class="text-sm text-gray-500">Pay with your PayPal account</div>
                                </div>
                                <div class="text-2xl">🅿️</div>
                            </label>
                        </div>

                        <!-- Card Details (Stripe) -->
                        <div id="stripeCardForm" class="space-y-4 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Card Number</label>
                                <input type="text" id="cardNumber" placeholder="1234 5678 9012 3456" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Expiry Date</label>
                                    <input type="text" id="cardExpiry" placeholder="MM/YY" 
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">CVV</label>
                                    <input type="text" id="cardCvv" placeholder="123" maxlength="4"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Cardholder Name</label>
                                <input type="text" id="cardName" placeholder="John Doe" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            </div>
                        </div>

                        <!-- Terms -->
                        <div class="mb-6">
                            <label class="flex items-start">
                                <input type="checkbox" id="termsAccept" required class="mt-1 mr-3">
                                <span class="text-sm text-gray-600">
                                    I agree to the <a href="<?= BASE_URL ?>/pages/terms.php" target="_blank" class="text-blue-600 hover:underline">Terms & Conditions</a> 
                                    and <a href="<?= BASE_URL ?>/pages/refund.php" target="_blank" class="text-blue-600 hover:underline">Cancellation Policy</a>
                                </span>
                            </label>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" id="payBtn" 
                                class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition disabled:bg-gray-400 disabled:cursor-not-allowed">
                            <span id="payBtnText">
                                <i class="fas fa-lock mr-2"></i>Pay $<?php echo number_format($booking['amount_paid'], 2); ?>
                            </span>
                            <span id="payBtnLoading" class="hidden">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Processing...
                            </span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Order Summary Sidebar -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm p-6 sticky top-4">
                    <h3 class="text-lg font-semibold mb-4">Order Summary</h3>
                    
                    <div class="space-y-3 mb-4">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Entry Fee</span>
                            <span class="font-semibold">$<?php echo number_format($booking['entry_fee'], 2); ?></span>
                        </div>
                        
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Service Fee</span>
                            <span class="font-semibold">$0.00</span>
                        </div>
                        
                        <div class="border-t pt-3 mt-3">
                            <div class="flex justify-between">
                                <span class="font-semibold">Total</span>
                                <span class="text-xl font-bold text-blue-600">
                                    $<?php echo number_format($booking['amount_paid'], 2); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Security Badge -->
                    <div class="bg-gray-50 p-4 rounded-lg text-center">
                        <div class="text-3xl mb-2">🔒</div>
                        <div class="text-sm font-medium text-gray-700">Secure Payment</div>
                        <div class="text-xs text-gray-500 mt-1">256-bit SSL encrypted</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Payment form handling
document.getElementById('paymentForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const payBtn = document.getElementById('payBtn');
    const payBtnText = document.getElementById('payBtnText');
    const payBtnLoading = document.getElementById('payBtnLoading');
    
    // Disable button and show loading
    payBtn.disabled = true;
    payBtnText.classList.add('hidden');
    payBtnLoading.classList.remove('hidden');
    
    // Simple validation
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
    
    if (paymentMethod === 'stripe') {
        const cardNumber = document.getElementById('cardNumber').value;
        const cardExpiry = document.getElementById('cardExpiry').value;
        const cardCvv = document.getElementById('cardCvv').value;
        const cardName = document.getElementById('cardName').value;
        
        if (!cardNumber || !cardExpiry || !cardCvv || !cardName) {
            alert('Please fill in all card details');
            payBtn.disabled = false;
            payBtnText.classList.remove('hidden');
            payBtnLoading.classList.add('hidden');
            return;
        }
    }
    
    // Submit form
    try {
        const formData = new FormData(this);
        const response = await fetch(this.action, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            window.location.href = '<?php echo BASE_URL; ?>/booking-confirmation?ref=' + result.booking_reference;
        } else {
            alert(result.message || 'Payment failed. Please try again.');
            payBtn.disabled = false;
            payBtnText.classList.remove('hidden');
            payBtnLoading.classList.add('hidden');
        }
    } catch (error) {
        console.error('Payment error:', error);
        alert('An error occurred. Please try again.');
        payBtn.disabled = false;
        payBtnText.classList.remove('hidden');
        payBtnLoading.classList.add('hidden');
    }
});

// Show/hide card form based on payment method
document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const cardForm = document.getElementById('stripeCardForm');
        if (this.value === 'stripe') {
            cardForm.style.display = 'block';
        } else {
            cardForm.style.display = 'none';
        }
    });
});

// Card number formatting
document.getElementById('cardNumber').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\s/g, '');
    let formatted = value.match(/.{1,4}/g)?.join(' ') || value;
    e.target.value = formatted.substr(0, 19);
});

// Expiry date formatting
document.getElementById('cardExpiry').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length >= 2) {
        value = value.substr(0, 2) + '/' + value.substr(2, 2);
    }
    e.target.value = value;
});

// CVV numeric only
document.getElementById('cardCvv').addEventListener('input', function(e) {
    e.target.value = e.target.value.replace(/\D/g, '');
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>