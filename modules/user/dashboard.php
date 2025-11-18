<?php

/**
 * User Dashboard
 * Crystal Chess Tournament Booking Platform
 * File: modules/user/dashboard.php
 */

// Config is already loaded by router - DON'T load again
// But we need to load core classes
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';

// Check authentication
Auth::require();
// $user = Auth::getUser();

// Get database connection
$db = Database::getInstance();
$pdo = $db->getConnection();

// Fetch user statistics
$stats = [
    'total_bookings' => 0,
    'upcoming_events' => 0,
    'completed_events' => 0,
    'total_spent' => 0
];

try {
    // Total bookings
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bookings WHERE user_id = ?");
    $stmt->execute([Auth::getUserId()]);
    $stats['total_bookings'] = $stmt->fetch()['count'];

    // Upcoming events
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM bookings b 
        JOIN events e ON b.event_id = e.event_id 
        WHERE b.user_id = ? 
        AND e.event_date >= CURDATE() 
        AND b.booking_status IN ('confirmed', 'pending')
    ");
    $stmt->execute([Auth::getUserId()]);
    $stats['upcoming_events'] = $stmt->fetch()['count'];

    // Completed events
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM bookings b 
        JOIN events e ON b.event_id = e.event_id 
        WHERE b.user_id = ? 
        AND e.event_date < CURDATE()
        AND b.booking_status = 'confirmed'
    ");
    $stmt->execute([Auth::getUserId()]);
    $stats['completed_events'] = $stmt->fetch()['count'];

    // Total spent
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount_paid), 0) as total 
        FROM bookings 
        WHERE user_id = ? AND payment_status = 'paid'
    ");
    $stmt->execute([Auth::getUserId()]);
    $stats['total_spent'] = $stmt->fetch()['total'];

    // Fetch upcoming bookings (limit 5)
    $stmt = $pdo->prepare("
        SELECT 
            b.booking_id,
            b.booking_reference,
            b.participant_name,
            b.booking_status,
            b.payment_status,
            b.amount_paid,
            e.event_name,
            e.event_date,
            e.event_time,
            e.location,
            e.event_id
        FROM bookings b
        JOIN events e ON b.event_id = e.event_id
        WHERE b.user_id = ?
        AND e.event_date >= CURDATE()
        ORDER BY e.event_date ASC
        LIMIT 5
    ");
    $stmt->execute([Auth::getUserId()]);
    $upcoming_bookings = $stmt->fetchAll();

    // Fetch recent bookings (limit 5)
    $stmt = $pdo->prepare("
        SELECT 
            b.booking_id,
            b.booking_reference,
            b.participant_name,
            b.booking_status,
            b.payment_status,
            b.amount_paid,
            b.booking_date,
            e.event_name,
            e.event_date,
            e.location
        FROM bookings b
        JOIN events e ON b.event_id = e.event_id
        WHERE b.user_id = ?
        ORDER BY b.booking_date DESC
        LIMIT 5
    ");
    $stmt->execute([Auth::getUserId()]);
    $recent_bookings = $stmt->fetchAll();

    // Fetch user details for profile section
    $stmt = $pdo->prepare("
        SELECT full_name, email, phone, profile_picture, created_at 
        FROM users 
        WHERE user_id = ?
    ");
    $stmt->execute([Auth::getUserId()]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    $error = "Error loading dashboard data";
    error_log("Dashboard error: " . $e->getMessage());
}

// Set default values if user not found
if (!$user) {
    $user = [
        'full_name' => Auth::getUserName(),
        'email' => Auth::getUserEmail(),
        'phone' => '',
        'profile_picture' => null,
        'created_at' => date('Y-m-d H:i:s')
    ];
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>! 👋</h1>
            <p class="text-gray-600 mt-2">Here's what's happening with your bookings</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Bookings -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Total Bookings</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2"><?php echo $stats['total_bookings']; ?></p>
                    </div>
                    <div class="bg-blue-100 rounded-full p-3">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Upcoming Events -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Upcoming Events</p>
                        <p class="text-3xl font-bold text-green-600 mt-2"><?php echo $stats['upcoming_events']; ?></p>
                    </div>
                    <div class="bg-green-100 rounded-full p-3">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Completed Events -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Completed</p>
                        <p class="text-3xl font-bold text-purple-600 mt-2"><?php echo $stats['completed_events']; ?></p>
                    </div>
                    <div class="bg-purple-100 rounded-full p-3">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Total Spent -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 font-medium">Total Spent</p>
                        <p class="text-3xl font-bold text-indigo-600 mt-2">₹ <?php echo number_format($stats['total_spent'], 2); ?></p>
                    </div>
                    <div class="bg-indigo-100 rounded-full p-3">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content (Left 2/3) -->
            <div class="lg:col-span-2 space-y-8">

                <!-- Upcoming Bookings -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-900">Upcoming Events</h2>
                        <a href="<?php echo BASE_URL; ?>/booking-history" class="text-sm text-blue-600 hover:text-blue-700 font-medium">View All →</a>
                    </div>
                    <div class="p-6">
                        <?php if (empty($upcoming_bookings)): ?>
                            <div class="text-center py-12">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No upcoming events</h3>
                                <p class="mt-1 text-sm text-gray-500">Get started by booking your first tournament!</p>
                                <div class="mt-6">
                                    <a href="<?php echo BASE_URL; ?>/events" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                        Browse Events
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($upcoming_bookings as $booking): ?>
                                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($booking['event_name']); ?></h3>
                                                <div class="mt-2 space-y-1 text-sm text-gray-600">
                                                    <p class="flex items-center">
                                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                        <?php echo date('D, M j, Y', strtotime($booking['event_date'])); ?> at <?php echo date('g:i A', strtotime($booking['event_time'])); ?>
                                                    </p>
                                                    <p class="flex items-center">
                                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        </svg>
                                                        <?php echo htmlspecialchars($booking['location']); ?>
                                                    </p>
                                                    <p class="flex items-center">
                                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                        </svg>
                                                        Participant: <?php echo htmlspecialchars($booking['participant_name']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="ml-4 flex flex-col items-end space-y-2">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    <?php echo $booking['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                    <?php echo ucfirst($booking['payment_status']); ?>
                                                </span>
                                                <a href="<?php echo BASE_URL; ?>/event-details?id=<?php echo $booking['event_id']; ?>"
                                                    class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                                                    View Details →
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Bookings -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-xl font-semibold text-gray-900">Recent Bookings</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($recent_bookings)): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-12 text-center text-gray-500">No bookings yet</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_bookings as $booking): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($booking['event_name']); ?></div>
                                                <div class="text-sm text-gray-500">Ref: <?php echo $booking['booking_reference']; ?></div>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                <?php echo date('M j, Y', strtotime($booking['event_date'])); ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900 font-medium">
                                                $<?php echo number_format($booking['amount_paid'], 2); ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    <?php
                                                    if ($booking['payment_status'] === 'paid') echo 'bg-green-100 text-green-800';
                                                    elseif ($booking['payment_status'] === 'pending') echo 'bg-yellow-100 text-yellow-800';
                                                    else echo 'bg-red-100 text-red-800';
                                                    ?>">
                                                    <?php echo ucfirst($booking['payment_status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- Sidebar (Right 1/3) -->
            <div class="space-y-6">

                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <a href="<?php echo BASE_URL; ?>/events" class="flex items-center justify-between p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                            <span class="text-sm font-medium text-blue-900">Browse Events</span>
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/booking-history" class="flex items-center justify-between p-3 bg-green-50 rounded-lg hover:bg-green-100 transition">
                            <span class="text-sm font-medium text-green-900">My Bookings</span>
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/profile" class="flex items-center justify-between p-3 bg-purple-50 rounded-lg hover:bg-purple-100 transition">
                            <span class="text-sm font-medium text-purple-900">Edit Profile</span>
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Profile Card -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Your Profile</h3>
                    <div class="flex items-center space-x-4">
                        <div class="h-16 w-16 rounded-full bg-blue-100 flex items-center justify-center">
                            <?php if (!empty($user['profile_picture'])): ?>
                                <img src="<?php echo UPLOADS_URL; ?>/profiles/<?php echo htmlspecialchars($user['profile_picture']); ?>"
                                    alt="Profile" class="h-16 w-16 rounded-full object-cover">
                            <?php else: ?>
                                <span class="text-2xl font-bold text-blue-600">
                                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($user['email']); ?></p>
                            <p class="text-xs text-gray-500 mt-1">Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Support Card -->
                <!-- <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow p-6 text-white">
                    <h3 class="text-lg font-semibold mb-2">Need Help?</h3>
                    <p class="text-sm text-blue-100 mb-4">Our support team is here to assist you with any questions.</p>
                    <a href="#" class="inline-flex items-center text-sm font-medium text-white hover:text-blue-100">
                        Contact Support
                        <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div> -->

            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>