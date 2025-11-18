<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Auth.php';

$auth = new Auth($pdo);
$auth->requireLogin();
$auth->requireRole(['organizer', 'admin']); // Now supports array

// Get user data - with fallback
$user = $_SESSION['user'] ?? null;

if (!$user || !isset($user['user_id'])) {
    // Session might be incomplete, redirect to login
    Auth::logout();
    header('Location: ' . BASE_URL . '/login');
    exit;
}

$organizer_id = $user['user_id'];

// Fetch organizer statistics
$stats_query = "
    SELECT 
        COUNT(DISTINCT e.event_id) as total_events,
        COUNT(DISTINCT CASE WHEN e.event_status = 'upcoming' THEN e.event_id END) as upcoming_events,
        COUNT(DISTINCT CASE WHEN e.event_status = 'completed' THEN e.event_id END) as completed_events,
        COUNT(DISTINCT b.booking_id) as total_bookings,
        COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN b.amount_paid ELSE 0 END), 0) as total_revenue,
        COUNT(DISTINCT CASE WHEN b.booking_status = 'confirmed' THEN b.booking_id END) as confirmed_bookings
    FROM events e
    LEFT JOIN bookings b ON e.event_id = b.event_id
    WHERE e.organizer_id = ?
";
$stmt = $pdo->prepare($stats_query);
$stmt->execute([$organizer_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Recent events
$recent_events_query = "
    SELECT 
        e.*,
        COUNT(b.booking_id) as booking_count,
        (e.max_capacity - e.current_bookings) as available_slots
    FROM events e
    LEFT JOIN bookings b ON e.event_id = b.event_id AND b.booking_status IN ('confirmed', 'pending')
    WHERE e.organizer_id = ?
    GROUP BY e.event_id
    ORDER BY e.event_date DESC
    LIMIT 5
";
$stmt = $pdo->prepare($recent_events_query);
$stmt->execute([$organizer_id]);
$recent_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent bookings
$recent_bookings_query = "
    SELECT 
        b.*,
        e.event_name,
        e.event_date
    FROM bookings b
    JOIN events e ON b.event_id = e.event_id
    WHERE e.organizer_id = ?
    ORDER BY b.booking_date DESC
    LIMIT 10
";
$stmt = $pdo->prepare($recent_bookings_query);
$stmt->execute([$organizer_id]);
$recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Revenue trend (last 6 months)
$revenue_trend_query = "
    SELECT 
        DATE_FORMAT(b.booking_date, '%Y-%m') as month,
        SUM(CASE WHEN b.payment_status = 'paid' THEN b.amount_paid ELSE 0 END) as revenue
    FROM bookings b
    JOIN events e ON b.event_id = e.event_id
    WHERE e.organizer_id = ?
    AND b.booking_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month DESC
";
$stmt = $pdo->prepare($revenue_trend_query);
$stmt->execute([$organizer_id]);
$revenue_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set page title
$pageTitle = 'Organizer Dashboard - Crystal Chess';

// Include header
include INCLUDES_PATH . '/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Organizer Dashboard</h1>
            <p class="mt-2 text-gray-600">Welcome back, <?= htmlspecialchars($user['full_name']) ?>!</p>
            <p class="text-sm text-gray-500">Role: <span class="font-semibold text-indigo-600"><?= ucfirst($user['user_type']) ?></span></p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Events -->
            <div class="bg-white rounded-lg shadow p-6 card-hover">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-blue-100 rounded-md p-3">
                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Events</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= $stats['total_events'] ?></p>
                    </div>
                </div>
            </div>

            <!-- Upcoming Events -->
            <div class="bg-white rounded-lg shadow p-6 card-hover">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-100 rounded-md p-3">
                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Upcoming Events</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= $stats['upcoming_events'] ?></p>
                    </div>
                </div>
            </div>

            <!-- Total Bookings -->
            <div class="bg-white rounded-lg shadow p-6 card-hover">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-purple-100 rounded-md p-3">
                        <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Bookings</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= $stats['total_bookings'] ?></p>
                    </div>
                </div>
            </div>

            <!-- Total Revenue -->
            <div class="bg-white rounded-lg shadow p-6 card-hover">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-yellow-100 rounded-md p-3">
                        <span class="text-yellow-600 text-2xl font-bold">₹ </span>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Revenue</p>
                        <p class="text-2xl font-semibold text-gray-900">₹ <?= number_format($stats['total_revenue'], 2) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Recent Events -->
            <div class="lg:col-span-2 bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-900">Your Recent Events</h2>
                    <a href="<?= BASE_URL ?>/manage-events" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium">View All →</a>
                </div>
                <div class="p-6">
                    <?php if (empty($recent_events)): ?>
                        <div class="text-center py-12">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                                <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No events yet</h3>
                            <p class="text-gray-500 mb-6">Get started by creating your first chess tournament</p>
                            <a href="<?= BASE_URL ?>/create-event" class="inline-flex items-center px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium shadow-md hover:shadow-lg">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Create Your First Event
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recent_events as $event): ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:border-indigo-300 hover:shadow-md transition">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <h3 class="text-lg font-semibold text-gray-900">
                                                <?= htmlspecialchars($event['event_name']) ?>
                                            </h3>
                                            <div class="flex items-center text-sm text-gray-600 mt-2">
                                                <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <?= date('F j, Y', strtotime($event['event_date'])) ?> at <?= date('g:i A', strtotime($event['event_time'])) ?>
                                            </div>
                                            <div class="flex items-center text-sm text-gray-500 mt-1">
                                                <svg class="h-4 w-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                                <?= htmlspecialchars($event['location']) ?>
                                            </div>
                                        </div>
                                        <div class="text-right ml-4">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                                <?php
                                                echo match ($event['event_status']) {
                                                    'upcoming' => 'bg-green-100 text-green-800',
                                                    'in_progress' => 'bg-blue-100 text-blue-800',
                                                    'completed' => 'bg-gray-100 text-gray-800',
                                                    'cancelled' => 'bg-red-100 text-red-800',
                                                    default => 'bg-gray-100 text-gray-800'
                                                };
                                                ?>
                                            ">
                                                <?= ucfirst(str_replace('_', ' ', $event['event_status'])) ?>
                                            </span>
                                            <div class="mt-3">
                                                <div class="text-sm font-semibold text-gray-900">
                                                    <?= $event['current_bookings'] ?> / <?= $event['max_capacity'] ?>
                                                </div>
                                                <div class="text-xs text-gray-500">participants</div>
                                            </div>
                                            <a href="<?= BASE_URL ?>/participants?event_id=<?= $event['event_id'] ?>"
                                                class="inline-block mt-2 text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                                                View Participants →
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions & Revenue Trend -->
            <div class="space-y-6">

                <!-- Quick Actions -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Quick Actions
                    </h3>
                    <div class="space-y-3">
                        <a href="<?= BASE_URL ?>/create-event" class="block w-full px-4 py-3 bg-indigo-600 text-white text-center rounded-lg hover:bg-indigo-700 transition font-medium shadow-md hover:shadow-lg">
                            <i class="fas fa-plus-circle mr-2"></i> Create New Event
                        </a>
                        <a href="<?= BASE_URL ?>/manage-events" class="block w-full px-4 py-3 bg-gray-100 text-gray-700 text-center rounded-lg hover:bg-gray-200 transition font-medium">
                            <i class="fas fa-cog mr-2"></i> Manage Events
                        </a>
                        <a href="<?= BASE_URL ?>/participants" class="block w-full px-4 py-3 bg-gray-100 text-gray-700 text-center rounded-lg hover:bg-gray-200 transition font-medium">
                            <i class="fas fa-users mr-2"></i> View All Participants
                        </a>
                        <a href="<?= BASE_URL ?>/notifications" class="block w-full px-4 py-3 bg-gray-100 text-gray-700 text-center rounded-lg hover:bg-gray-200 transition font-medium">
                            <i class="fas fa-bell mr-2"></i> Send Notifications
                        </a>
                    </div>
                </div>

                <!-- Revenue Trend -->
                <?php if (!empty($revenue_trend)): ?>
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                            </svg>
                            Revenue Trend (6 Months)
                        </h3>
                        <div class="space-y-3">
                            <?php foreach ($revenue_trend as $trend): ?>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100 last:border-0">
                                    <span class="text-sm text-gray-600 font-medium"><?= date('M Y', strtotime($trend['month'] . '-01')) ?></span>
                                    <span class="text-sm font-bold text-gray-900">${= number_format($trend['revenue'], 2) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- Recent Bookings -->
        <div class="mt-6 bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-900">Recent Bookings for Your Events</h2>
                <span class="text-sm text-gray-500"><?= count($recent_bookings) ?> total</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Participant</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($recent_bookings)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <div class="inline-flex items-center justify-center w-12 h-12 bg-gray-100 rounded-full mb-3">
                                        <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <p class="text-gray-500">No bookings yet</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_bookings as $booking): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        #<?= htmlspecialchars($booking['booking_reference']) ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?= htmlspecialchars($booking['event_name']) ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?= htmlspecialchars($booking['participant_name']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('M j, Y', strtotime($booking['booking_date'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                        $<?= number_format($booking['amount_paid'], 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full
                                            <?php
                                            echo match ($booking['booking_status']) {
                                                'confirmed' => 'bg-green-100 text-green-800',
                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                'cancelled' => 'bg-red-100 text-red-800',
                                                default => 'bg-gray-100 text-gray-800'
                                            };
                                            ?>
                                        ">
                                            <?= ucfirst($booking['booking_status']) ?>
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
</div>

</main>

<?php include INCLUDES_PATH . '/footer.php'; ?>