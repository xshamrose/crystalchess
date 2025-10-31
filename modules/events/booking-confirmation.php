<?php
// modules/events/booking-confirmation.php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/BookingManager.php';

$auth = new Auth();
$bookingManager = new BookingManager();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: ' . BASE_URL . '/modules/user/login.php');
    exit;
}

// Get booking reference from URL
$bookingReference = $_GET['ref'] ?? '';

if (empty($bookingReference)) {
    header('Location: ' . BASE_URL . '/modules/events/browse.php');
    exit;
}

// Get booking details
$booking = $bookingManager->getBookingByReference($bookingReference);

if (!$booking) {
    header('Location: ' . BASE_URL . '/modules/events/browse.php');
    exit;
}

// Clear pending booking from session
unset($_SESSION['pending_booking_id']);

$pageTitle = 'Booking Confirmed - Crystal Chess';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-green-50 to-blue-50 py-12">
    <div class="max-w-3xl mx-auto px-4">
        
        <!-- Success Message -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-green-500 rounded-full mb-4">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h1 class="text-4xl font-bold text-gray-900 mb-2">Booking Confirmed!</h1>
            <p class="text-lg text-gray-600">Your tournament booking has been successfully completed</p>
        </div>

        <!-- Booking Details Card -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6">
            <!-- Header with Reference -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-6">
                <div class="text-center">
                    <div class="text-sm font-medium mb-2">Booking Reference</div>
                    <div class="text-3xl font-bold tracking-wider font-mono">
                        <?php echo htmlspecialchars($booking['booking_reference']); ?>
                    </div>
                    <div class="text-sm mt-2 opacity-90">
                        Please save this reference number for your records
                    </div>
                </div>
            </div>

            <!-- Details -->
            <div class="p-6">
                <h2 class="text-xl font-semibold mb-4 text-gray-900">Event Details</h2>
                
                <div class="space-y-4">
                    <!-- Event Name -->
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <div class="text-sm text-gray-500">Event Name</div>
                            <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($booking['event_name']); ?></div>
                        </div>
                    </div>

                    <!-- Participant -->
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <div class="text-sm text-gray-500">Participant</div>
                            <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($booking['participant_name']); ?></div>
                        </div>
                    </div>

                    <!-- Date & Time -->
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <div class="text-sm text-gray-500">Date & Time</div>
                            <div class="font-semibold text-gray-900">
                                <?php echo date('l, F j, Y', strtotime($booking['event_date'])); ?><br>
                                <span class="text-sm">at <?php echo date('h:i A', strtotime($booking['event_time'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Location -->
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-8 h-8 bg-red-100 rounded-full flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <div class="text-sm text-gray-500">Location</div>
                            <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($booking['location']); ?></div>
                            <?php if (!empty($booking['venue_address'])): ?>
                            <div class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($booking['venue_address']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Amount Paid -->
                    <div class="flex items-start pt-4 border-t">
                        <div class="flex-shrink-0 w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <div class="text-sm text-gray-500">Amount Paid</div>
                            <div class="text-2xl font-bold text-green-600">$<?php echo number_format($booking['amount_paid'], 2); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Next Steps -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-blue-900 mb-3 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                What's Next?
            </h3>
            <ul class="space-y-2 text-sm text-blue-900">
                <li class="flex items-start">
                    <span class="mr-2">✓</span>
                    <span>You will receive a confirmation email shortly with all the details</span>
                </li>
                <li class="flex items-start">
                    <span class="mr-2">✓</span>
                    <span>A reminder email will be sent 2 days before the event</span>
                </li>
                <li class="flex items-start">
                    <span class="mr-2">✓</span>
                    <span>Please arrive 15 minutes early on the event day</span>
                </li>
                <li class="flex items-start">
                    <span class="mr-2">✓</span>
                    <span>Bring a valid ID and your booking reference number</span>
                </li>
            </ul>
        </div>

        <!-- Actions -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="<?php echo BASE_URL; ?>/modules/user/booking-history.php" 
                   class="flex items-center justify-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    View Bookings
                </a>
                
                <a href="<?php echo BASE_URL; ?>/modules/events/browse.php" 
                   class="flex items-center justify-center px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    Browse Events
                </a>
                
                <button onclick="window.print()" 
                        class="flex items-center justify-center px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                    Print Receipt
                </button>
            </div>
        </div>

        <!-- Help Section -->
        <div class="text-center mt-8 text-gray-600">
            <p>Need help? Contact us at <a href="mailto:support@crystalchess.com" class="text-blue-600 hover:underline">support@crystalchess.com</a></p>
        </div>

    </div>
</div>

<style>
    @media print {
        body * {
            visibility: hidden;
        }
        .bg-white, .bg-white * {
            visibility: visible;
        }
        .bg-white {
            position: absolute;
            left: 0;
            top: 0;
        }
        button, a {
            display: none !important;
        }
    }
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>