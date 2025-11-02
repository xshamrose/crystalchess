<?php
/**
 * Booking Confirmation Page
 * File: modules/events/booking-confirmation.php
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

// Get booking reference from URL
$bookingReference = $_GET['ref'] ?? '';

if (empty($bookingReference)) {
    setFlash('error', 'Invalid booking reference.');
    header('Location: ' . BASE_URL . '/browse-events');
    exit;
}

// Get booking details
$db->query("
    SELECT b.*, 
           e.event_name, e.event_date, e.event_time, 
           e.location, e.venue_address
    FROM bookings b
    JOIN events e ON b.event_id = e.event_id
    WHERE b.booking_reference = :ref
");
$db->bind(':ref', $bookingReference);
$booking = $db->fetch();

if (!$booking) {
    setFlash('error', 'Booking not found.');
    header('Location: ' . BASE_URL . '/browse-events');
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
        <div class="text-center mb-8 animate-fade-in">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-green-500 rounded-full mb-4 shadow-lg">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h1 class="text-4xl font-bold text-gray-900 mb-2">🎉 Booking Confirmed!</h1>
            <p class="text-lg text-gray-600">Your tournament booking has been successfully completed</p>
        </div>

        <!-- Booking Details Card -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6">
            <!-- Header with Reference -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-6">
                <div class="text-center">
                    <div class="text-sm font-medium mb-2 opacity-90">Booking Reference</div>
                    <div class="text-3xl font-bold tracking-wider font-mono">
                        <?php echo htmlspecialchars($booking['booking_reference']); ?>
                    </div>
                    <div class="text-sm mt-2 opacity-90">
                        📧 Confirmation email sent to <?php echo htmlspecialchars($booking['participant_email']); ?>
                    </div>
                </div>
            </div>

            <!-- Details -->
            <div class="p-6">
                <h2 class="text-xl font-semibold mb-4 text-gray-900">Event Details</h2>
                
                <div class="space-y-4">
                    <!-- Event Name -->
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                            <i class="fas fa-chess text-blue-600"></i>
                        </div>
                        <div class="flex-1">
                            <div class="text-sm text-gray-500">Event Name</div>
                            <div class="font-semibold text-gray-900 text-lg"><?php echo htmlspecialchars($booking['event_name']); ?></div>
                        </div>
                    </div>

                    <!-- Participant -->
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                            <i class="fas fa-user text-green-600"></i>
                        </div>
                        <div class="flex-1">
                            <div class="text-sm text-gray-500">Participant</div>
                            <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($booking['participant_name']); ?></div>
                            <div class="text-sm text-gray-600">Age: <?php echo htmlspecialchars($booking['participant_age']); ?> | <?php echo ucfirst($booking['player_type']); ?></div>
                        </div>
                    </div>

                    <!-- Date & Time -->
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-3">
                            <i class="fas fa-calendar text-purple-600"></i>
                        </div>
                        <div class="flex-1">
                            <div class="text-sm text-gray-500">Date & Time</div>
                            <div class="font-semibold text-gray-900">
                                <?php echo date('l, F j, Y', strtotime($booking['event_date'])); ?>
                            </div>
                            <div class="text-sm text-gray-600">
                                at <?php echo date('h:i A', strtotime($booking['event_time'])); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Location -->
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-10 h-10 bg-red-100 rounded-full flex items-center justify-center mr-3">
                            <i class="fas fa-map-marker-alt text-red-600"></i>
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
                        <div class="flex-shrink-0 w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center mr-3">
                            <i class="fas fa-dollar-sign text-yellow-600"></i>
                        </div>
                        <div class="flex-1">
                            <div class="text-sm text-gray-500">Amount Paid</div>
                            <div class="text-2xl font-bold text-green-600">$<?php echo number_format($booking['amount_paid'], 2); ?></div>
                            <div class="text-xs text-gray-500 mt-1">Payment Status: <span class="text-green-600 font-semibold">Completed</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Next Steps -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
            <h3 class="text-lg font-semibold text-blue-900 mb-3 flex items-center">
                <i class="fas fa-info-circle mr-2"></i>
                What's Next?
            </h3>
            <ul class="space-y-2 text-sm text-blue-900">
                <li class="flex items-start">
                    <i class="fas fa-check text-green-600 mr-2 mt-1"></i>
                    <span>You will receive a confirmation email shortly with all the details</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-check text-green-600 mr-2 mt-1"></i>
                    <span>A reminder email will be sent 2 days before the event</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-check text-green-600 mr-2 mt-1"></i>
                    <span>Please arrive 15 minutes early on the event day</span>
                </li>
                <li class="flex items-start">
                    <i class="fas fa-check text-green-600 mr-2 mt-1"></i>
                    <span>Bring a valid ID and your booking reference number</span>
                </li>
            </ul>
        </div>

        <!-- Actions -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="<?php echo BASE_URL; ?>/booking-history" 
                   class="flex items-center justify-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition shadow-md">
                    <i class="fas fa-history mr-2"></i>
                    View Bookings
                </a>
                
                <a href="<?php echo BASE_URL; ?>/browse-events" 
                   class="flex items-center justify-center px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    <i class="fas fa-search mr-2"></i>
                    Browse Events
                </a>
                
                <button onclick="window.print()" 
                        class="flex items-center justify-center px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    <i class="fas fa-print mr-2"></i>
                    Print Receipt
                </button>
            </div>
        </div>

        <!-- Help Section -->
        <div class="text-center mt-8 text-gray-600">
            <p class="text-sm">
                Need help? Contact us at 
                <a href="mailto:crystalschess@gmail.com" class="text-blue-600 hover:underline font-medium">
                    crystalschess@gmail.com
                </a>
                or call 
                <a href="tel:+919884423423" class="text-blue-600 hover:underline font-medium">
                    +91 9884423423
                </a>
            </p>
        </div>

    </div>
</div>

<style>
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-fade-in {
        animation: fadeIn 0.6s ease-out;
    }

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
        .bg-gradient-to-br {
            background: white !important;
        }
    }
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>