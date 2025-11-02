<?php
/**
 * Admin Dashboard
 * Crystal Chess Tournament Booking Platform
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Auth.php';

// Ensure only admins can access this page
Auth::require();
Auth::requireRole('admin');

// Admin info
$admin_id = Auth::getUserId();
$admin_name = Auth::getUserName();

// Get DB instance
$db = Database::getInstance();

// --- Fetch Dashboard Stats ---

// Total users
$total_users = $db->query("SELECT COUNT(*) AS total FROM users")->fetch()['total'];

// Active users (last 30 days)
$active_users = $db->query("
    SELECT COUNT(*) AS active 
    FROM users 
    WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetch()['active'];

// Total events
$total_events = $db->query("SELECT COUNT(*) AS total FROM events")->fetch()['total'];

// Upcoming events
$upcoming_events = $db->query("
    SELECT COUNT(*) AS upcoming 
    FROM events 
    WHERE event_status = 'upcoming' AND event_date >= CURDATE()
")->fetch()['upcoming'];

// Total bookings
$total_bookings = $db->query("SELECT COUNT(*) AS total FROM bookings")->fetch()['total'];

// Confirmed bookings
$confirmed_bookings = $db->query("
    SELECT COUNT(*) AS confirmed 
    FROM bookings 
    WHERE booking_status = 'confirmed'
")->fetch()['confirmed'];

// Total revenue
$total_revenue = $db->query("
    SELECT COALESCE(SUM(amount_paid), 0) AS revenue 
    FROM bookings 
    WHERE payment_status = 'paid'
")->fetch()['revenue'];

// Pending payments
$pending_payments = $db->query("
    SELECT COUNT(*) AS pending 
    FROM bookings 
    WHERE payment_status = 'pending'
")->fetch()['pending'];

// User distribution by role
$user_roles = $db->query("
    SELECT user_type, COUNT(*) AS count 
    FROM users 
    WHERE user_status = 'active' 
    GROUP BY user_type
")->fetchAll();

// Recent activity (last 10 bookings)
$recent_bookings = $db->query("
    SELECT 
        b.booking_id, b.booking_reference, b.participant_name, b.booking_date,
        e.event_name, u.full_name AS booked_by, 
        b.booking_status, b.payment_status
    FROM bookings b
    JOIN events e ON b.event_id = e.event_id
    JOIN users u ON b.user_id = u.user_id
    ORDER BY b.booking_date DESC
    LIMIT 10
")->fetchAll();

// Top organizers by revenue
$top_organizers = $db->query("
    SELECT 
        u.full_name, u.email, 
        COUNT(DISTINCT e.event_id) AS event_count,
        COUNT(b.booking_id) AS booking_count,
        COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN b.amount_paid ELSE 0 END), 0) AS revenue
    FROM users u
    LEFT JOIN events e ON u.user_id = e.organizer_id
    LEFT JOIN bookings b ON e.event_id = b.event_id
    WHERE u.user_type = 'organizer' AND u.user_status = 'active'
    GROUP BY u.user_id
    ORDER BY revenue DESC
    LIMIT 5
")->fetchAll();

// Monthly revenue (last 6 months)
$monthly_revenue = $db->query("
    SELECT 
        DATE_FORMAT(booking_date, '%Y-%m') AS month,
        COALESCE(SUM(amount_paid), 0) AS revenue
    FROM bookings
    WHERE payment_status = 'paid'
      AND booking_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(booking_date, '%Y-%m')
    ORDER BY month DESC
")->fetchAll();

// Include UI components
include __DIR__ . '/../../includes/header.php';
// include __DIR__ . '/../../includes/nav.php';
?>

<!-- ✅ Admin Dashboard Page HTML -->
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Admin Dashboard</h1>
            <p class="text-gray-600 mt-1">System overview and analytics</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <?php
            $cards = [
                ['title' => 'Total Users', 'count' => $total_users, 'sub' => "$active_users active", 'color' => 'blue', 'icon' => 'users'],
                ['title' => 'Total Events', 'count' => $total_events, 'sub' => "$upcoming_events upcoming", 'color' => 'green', 'icon' => 'calendar'],
                ['title' => 'Total Bookings', 'count' => $total_bookings, 'sub' => "$confirmed_bookings confirmed", 'color' => 'purple', 'icon' => 'clipboard-list'],
                ['title' => 'Total Revenue', 'count' => '$' . number_format($total_revenue, 2), 'sub' => "$pending_payments pending", 'color' => 'yellow', 'icon' => 'dollar-sign'],
            ];

            foreach ($cards as $card): ?>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600"><?= htmlspecialchars($card['title']) ?></p>
                            <p class="text-3xl font-bold text-gray-900 mt-2"><?= htmlspecialchars($card['count']) ?></p>
                            <p class="text-sm text-<?= $card['color'] ?>-600 mt-1"><?= htmlspecialchars($card['sub']) ?></p>
                        </div>
                        <div class="bg-<?= $card['color'] ?>-100 rounded-full p-3">
                            <i class="fas fa-<?= $card['icon'] ?> text-<?= $card['color'] ?>-600 text-2xl"></i>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Role Distribution & Revenue Trend -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- User Distribution -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">User Distribution by Role</h2>
                <div class="space-y-4">
                    <?php foreach ($user_roles as $role): 
                        $percentage = $total_users > 0 ? ($role['count'] / $total_users) * 100 : 0;
                        $color = match($role['user_type']) {
                            'player' => 'bg-blue-600',
                            'organizer' => 'bg-green-600',
                            default => 'bg-purple-600'
                        };
                    ?>
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm font-medium text-gray-700 capitalize"><?= htmlspecialchars($role['user_type']) ?>s</span>
                            <span class="text-sm font-medium text-gray-900"><?= number_format($role['count']) ?></span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="<?= $color ?> h-2 rounded-full" style="width: <?= $percentage ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Monthly Revenue -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Revenue Trend (Last 6 Months)</h2>
                <div class="space-y-3">
                    <?php foreach (array_reverse($monthly_revenue) as $month): ?>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-600"><?= date('M Y', strtotime($month['month'] . '-01')) ?></span>
                            <span class="text-sm font-semibold text-gray-900">$<?= number_format($month['revenue'], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Top Organizers -->
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Top Organizers by Revenue</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Organizer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Events</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bookings</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($top_organizers as $o): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4"><?= htmlspecialchars($o['full_name']) ?></td>
                            <td class="px-6 py-4"><?= htmlspecialchars($o['email']) ?></td>
                            <td class="px-6 py-4"><?= number_format($o['event_count']) ?></td>
                            <td class="px-6 py-4"><?= number_format($o['booking_count']) ?></td>
                            <td class="px-6 py-4 text-green-600 font-semibold">$<?= number_format($o['revenue'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Bookings -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Recent Booking Activity</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Participant</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Event</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Booked By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recent_bookings as $b): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4"><?= htmlspecialchars($b['booking_reference']) ?></td>
                            <td class="px-6 py-4"><?= htmlspecialchars($b['participant_name']) ?></td>
                            <td class="px-6 py-4"><?= htmlspecialchars($b['event_name']) ?></td>
                            <td class="px-6 py-4"><?= htmlspecialchars($b['booked_by']) ?></td>
                            <td class="px-6 py-4"><?= date('M d, Y', strtotime($b['booking_date'])) ?></td>
                            <td class="px-6 py-4"><?= ucfirst($b['booking_status']) ?></td>
                            <td class="px-6 py-4"><?= ucfirst($b['payment_status']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="modules/admin/users.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg text-center font-medium transition">
                Manage Users
            </a>
            <a href="modules/admin/events.php" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg text-center font-medium transition">
                Manage Events
            </a>
            <a href="modules/admin/reports.php" class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-lg text-center font-medium transition">
                View Reports
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
