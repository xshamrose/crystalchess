<?php
/**
 * Event Details Page
 * Displays full information about a selected event
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();
$db = Database::getInstance();

// Get event ID from query parameter
$eventId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($eventId <= 0) {
    header('Location: ' . BASE_URL . '/browse-events');
    exit;
}

// Fetch event details with organizer info
$query = "
    SELECT 
        e.*,
        u.full_name AS organizer_name,
        u.email AS organizer_email,
        u.phone AS organizer_phone,
        (e.max_capacity - e.current_bookings) AS available_slots
    FROM events e
    LEFT JOIN users u ON e.organizer_id = u.user_id
    WHERE e.event_id = :event_id
";

$db->query($query);
$db->bind(':event_id', $eventId);
$event = $db->fetch();

if (!$event) {
    header('Location: ' . BASE_URL . '/browse-events');
    exit;
}

// Check if user has already booked
$userBooked = false;
if (Auth::check()) {
    $userId = Auth::getUserId();
    $bookingQuery = "SELECT booking_id FROM bookings WHERE event_id = :eid AND user_id = :uid AND booking_status IN ('pending','confirmed')";
    $db->query($bookingQuery);
    $db->bind(':eid', $eventId);
    $db->bind(':uid', $userId);
    $userBooked = $db->fetch() !== false;
}

// Calculate event date difference
$eventDate = new DateTime($event['event_date']);
$today = new DateTime();
$daysUntil = $today->diff($eventDate)->days;
$isPast = $today > $eventDate;

// Page title
$pageTitle = htmlspecialchars($event['event_name']);
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Breadcrumb -->
<div class="bg-gray-50 border-b">
    <div class="container mx-auto px-4 py-3 text-sm text-gray-600 flex items-center">
        <a href="<?php echo BASE_URL; ?>" class="hover:text-blue-600">Home</a>
        <span class="mx-2 text-gray-400">›</span>
        <a href="<?php echo BASE_URL; ?>/browse-events" class="hover:text-blue-600">Events</a>
        <span class="mx-2 text-gray-400">›</span>
        <span class="text-gray-900"><?php echo htmlspecialchars($event['event_name']); ?></span>
    </div>
</div>

<!-- Event Details -->
<div class="container mx-auto px-4 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Column -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <div class="relative h-96 bg-gradient-to-br from-blue-400 to-purple-500">
                    <?php if (!empty($event['event_image'])): ?>
                        <img src="<?php echo UPLOADS_URL; ?>/events/<?php echo htmlspecialchars($event['event_image']); ?>" 
                             class="w-full h-full object-cover" 
                             alt="<?php echo htmlspecialchars($event['event_name']); ?>">
                    <?php else: ?>
                        <div class="flex items-center justify-center h-full">
                            <i class="fas fa-chess text-white text-9xl opacity-30"></i>
                        </div>
                    <?php endif; ?>

                    <!-- Status Badge -->
                    <div class="absolute top-4 right-4">
                        <?php if ($event['event_status'] === 'upcoming'): ?>
                            <span class="bg-green-500 text-white px-4 py-2 rounded-full font-semibold shadow-lg">
                                <i class="fas fa-calendar-check mr-1"></i> Upcoming
                            </span>
                        <?php elseif ($event['event_status'] === 'completed'): ?>
                            <span class="bg-gray-500 text-white px-4 py-2 rounded-full font-semibold shadow-lg">
                                <i class="fas fa-flag-checkered mr-1"></i> Completed
                            </span>
                        <?php elseif ($event['event_status'] === 'cancelled'): ?>
                            <span class="bg-red-500 text-white px-4 py-2 rounded-full font-semibold shadow-lg">
                                <i class="fas fa-times-circle mr-1"></i> Cancelled
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Featured Badge -->
                    <?php if ($event['featured']): ?>
                        <div class="absolute top-4 left-4">
                            <span class="bg-yellow-400 text-yellow-900 px-4 py-2 rounded-full font-bold shadow-lg">
                                ⭐ Featured
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="p-6">
                    <h1 class="text-3xl font-bold text-gray-900 mb-4">
                        <?php echo htmlspecialchars($event['event_name']); ?>
                    </h1>

                    <!-- Quick Info Grid -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div class="text-center bg-blue-50 p-4 rounded-lg">
                            <i class="fas fa-calendar text-blue-500 text-2xl mb-2"></i>
                            <div class="text-xs text-gray-600 mb-1">Date</div>
                            <div class="font-bold text-gray-900"><?php echo date('M d, Y', strtotime($event['event_date'])); ?></div>
                        </div>
                        <div class="text-center bg-purple-50 p-4 rounded-lg">
                            <i class="fas fa-clock text-purple-500 text-2xl mb-2"></i>
                            <div class="text-xs text-gray-600 mb-1">Time</div>
                            <div class="font-bold text-gray-900"><?php echo date('h:i A', strtotime($event['event_time'])); ?></div>
                        </div>
                        <div class="text-center bg-yellow-50 p-4 rounded-lg">
                            <i class="fas fa-dollar-sign text-yellow-600 text-2xl mb-2"></i>
                            <div class="text-xs text-gray-600 mb-1">Entry Fee</div>
                            <div class="font-bold text-gray-900">$<?php echo number_format($event['entry_fee'], 2); ?></div>
                        </div>
                        <div class="text-center bg-green-50 p-4 rounded-lg">
                            <i class="fas fa-users text-green-500 text-2xl mb-2"></i>
                            <div class="text-xs text-gray-600 mb-1">Capacity</div>
                            <div class="font-bold text-gray-900"><?php echo $event['max_capacity']; ?> players</div>
                        </div>
                    </div>

                    <!-- Alerts -->
                    <?php if (!$isPast && $event['event_status'] === 'upcoming'): ?>
                        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6 rounded">
                            <div class="flex items-center">
                                <i class="fas fa-info-circle text-blue-500 text-xl mr-3"></i>
                                <p class="text-blue-700 font-semibold">
                                    <?php if ($daysUntil === 0): ?>
                                        🎯 Event is TODAY!
                                    <?php elseif ($daysUntil === 1): ?>
                                        ⏰ Event is TOMORROW!
                                    <?php else: ?>
                                        📅 Event starts in <?php echo $daysUntil; ?> days
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Description -->
                    <div class="mb-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                            About This Event
                        </h2>
                        <div class="prose max-w-none text-gray-700">
                            <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                        </div>
                    </div>

                    <!-- Rules -->
                    <?php if (!empty($event['rules'])): ?>
                        <div class="mb-6">
                            <h2 class="text-xl font-bold text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-gavel text-blue-500 mr-2"></i>
                                Rules & Regulations
                            </h2>
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <div class="prose max-w-none text-gray-700">
                                    <?php echo nl2br(htmlspecialchars($event['rules'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Location -->
                    <div class="mb-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-3 flex items-center">
                            <i class="fas fa-map-marker-alt text-blue-500 mr-2"></i>
                            Location
                        </h2>
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <p class="font-semibold text-gray-900 text-lg">
                                <?php echo htmlspecialchars($event['location']); ?>
                            </p>
                            <?php if (!empty($event['venue_address'])): ?>
                                <p class="text-gray-600 mt-2">
                                    <i class="fas fa-building mr-2"></i>
                                    <?php echo htmlspecialchars($event['venue_address']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <div class="lg:sticky lg:top-20 space-y-6">
                <!-- Booking Card -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="text-center mb-6 pb-6 border-b">
                        <div class="text-4xl font-bold text-blue-600 mb-2">
                            $<?php echo number_format($event['entry_fee'], 2); ?>
                        </div>
                        <div class="text-gray-600 text-sm">Entry Fee</div>
                    </div>

                    <!-- Availability -->
                    <div class="mb-6">
                        <div class="flex justify-between mb-2 text-sm">
                            <span class="text-gray-600">Available Slots</span>
                            <span class="font-bold text-gray-900">
                                <?php echo $event['available_slots']; ?> / <?php echo $event['max_capacity']; ?>
                            </span>
                        </div>
                        <?php 
                            $percentage = ($event['current_bookings'] / $event['max_capacity']) * 100;
                            $barColor = $percentage >= 90 ? 'bg-red-500' : ($percentage >= 70 ? 'bg-yellow-500' : 'bg-green-500');
                        ?>
                        <div class="w-full bg-gray-200 h-3 rounded-full overflow-hidden">
                            <div class="<?php echo $barColor; ?> h-3 transition-all duration-300" 
                                 style="width:<?php echo $percentage; ?>%">
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            <?php echo round($percentage); ?>% booked
                        </p>
                    </div>

                    <!-- Booking Actions -->
                    <?php if ($event['event_status'] === 'upcoming' && $event['available_slots'] > 0): ?>
                        <?php if (Auth::check()): ?>
                            <?php if ($userBooked): ?>
                                <div class="bg-green-50 border-2 border-green-500 p-4 mb-3 text-center rounded-lg">
                                    <i class="fas fa-check-circle text-green-600 text-2xl mb-2"></i>
                                    <p class="text-green-700 font-semibold">You're Registered!</p>
                                </div>
                                <a href="<?php echo BASE_URL; ?>/booking-history" 
                                   class="block text-center px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                                    <i class="fas fa-history mr-2"></i>View My Bookings
                                </a>
                            <?php else: ?>
                                <a href="<?php echo BASE_URL; ?>/book?event_id=<?php echo $eventId; ?>" 
                                   class="block text-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold shadow-lg">
                                    <i class="fas fa-ticket-alt mr-2"></i>Book Now
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>/login?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
                               class="block text-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-semibold shadow-lg">
                                <i class="fas fa-sign-in-alt mr-2"></i>Login to Book
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <button disabled 
                                class="w-full px-6 py-3 bg-gray-400 text-white rounded-lg font-semibold cursor-not-allowed">
                            <i class="fas fa-lock mr-2"></i>Booking Closed
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Organizer Info Card -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                        <i class="fas fa-user-tie text-blue-500 mr-2"></i>
                        Organizer Info
                    </h3>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                <i class="fas fa-user text-blue-600 text-lg"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">
                                    <?php echo htmlspecialchars($event['organizer_name']); ?>
                                </p>
                                <p class="text-xs text-gray-500">Event Organizer</p>
                            </div>
                        </div>
                        <div class="pt-3 border-t space-y-2">
                            <p class="text-gray-600 text-sm flex items-center">
                                <i class="fas fa-envelope text-gray-400 w-5 mr-2"></i>
                                <a href="mailto:<?php echo htmlspecialchars($event['organizer_email']); ?>" 
                                   class="hover:text-blue-600">
                                    <?php echo htmlspecialchars($event['organizer_email']); ?>
                                </a>
                            </p>
                            <?php if (!empty($event['organizer_phone'])): ?>
                                <p class="text-gray-600 text-sm flex items-center">
                                    <i class="fas fa-phone text-gray-400 w-5 mr-2"></i>
                                    <a href="tel:<?php echo htmlspecialchars($event['organizer_phone']); ?>" 
                                       class="hover:text-blue-600">
                                        <?php echo htmlspecialchars($event['organizer_phone']); ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>