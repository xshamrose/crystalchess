<?php
// modules/admin/reports.php
require_once '../../config/database.php';
require_once '../../core/Auth.php';

$auth = new Auth($pdo);
$auth->requireLogin();
$auth->requireRole(['admin']);

// Date range filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Revenue Analytics
$revenue_sql = "SELECT 
                DATE(booking_date) as date,
                COUNT(*) as bookings,
                SUM(amount_paid) as revenue
                FROM bookings
                WHERE payment_status = 'paid' 
                AND booking_date BETWEEN ? AND ?
                GROUP BY DATE(booking_date)
                ORDER BY date";
$revenue_stmt = $pdo->prepare($revenue_sql);
$revenue_stmt->execute([$start_date, $end_date]);
$daily_revenue = $revenue_stmt->fetchAll();

// Summary Statistics
$summary_sql = "SELECT 
                COUNT(DISTINCT CASE WHEN payment_status = 'paid' THEN booking_id END) as total_paid_bookings,
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN amount_paid END), 0) as total_revenue,
                COALESCE(AVG(CASE WHEN payment_status = 'paid' THEN amount_paid END), 0) as avg_booking_value,
                COUNT(DISTINCT CASE WHEN payment_status = 'refunded' THEN booking_id END) as total_refunds,
                COALESCE(SUM(CASE WHEN payment_status = 'refunded' THEN amount_paid END), 0) as refund_amount
                FROM bookings
                WHERE booking_date BETWEEN ? AND ?";
$summary_stmt = $pdo->prepare($summary_sql);
$summary_stmt->execute([$start_date, $end_date]);
$summary = $summary_stmt->fetch();

// Revenue by Payment Gateway
$gateway_sql = "SELECT 
                p.payment_gateway,
                COUNT(*) as transaction_count,
                SUM(p.amount) as total_amount
                FROM payments p
                WHERE p.payment_status = 'completed'
                AND p.payment_date BETWEEN ? AND ?
                GROUP BY p.payment_gateway";
$gateway_stmt = $pdo->prepare($gateway_sql);
$gateway_stmt->execute([$start_date, $end_date]);
$gateway_stats = $gateway_stmt->fetchAll();

// Top Events by Revenue
$top_events_sql = "SELECT 
                   e.event_name,
                   e.event_date,
                   u.full_name as organizer,
                   COUNT(b.booking_id) as bookings,
                   COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN b.amount_paid END), 0) as revenue
                   FROM events e
                   LEFT JOIN bookings b ON e.event_id = b.event_id 
                   AND b.booking_date BETWEEN ? AND ?
                   JOIN users u ON e.organizer_id = u.user_id
                   GROUP BY e.event_id
                   HAVING revenue > 0
                   ORDER BY revenue DESC
                   LIMIT 10";
$top_events_stmt = $pdo->prepare($top_events_sql);
$top_events_stmt->execute([$start_date, $end_date]);
$top_events = $top_events_stmt->fetchAll();

// User Registration Trend
$user_trend_sql = "SELECT 
                   DATE(created_at) as date,
                   COUNT(*) as new_users
                   FROM users
                   WHERE created_at BETWEEN ? AND ?
                   GROUP BY DATE(created_at)
                   ORDER BY date";
$user_trend_stmt = $pdo->prepare($user_trend_sql);
$user_trend_stmt->execute([$start_date, $end_date]);
$user_trend = $user_trend_stmt->fetchAll();

// Booking Status Distribution
$status_sql = "SELECT 
               booking_status,
               COUNT(*) as count,
               SUM(amount_paid) as total_amount
               FROM bookings
               WHERE booking_date BETWEEN ? AND ?
               GROUP BY booking_status";
$status_stmt = $pdo->prepare($status_sql);
$status_stmt->execute([$start_date, $end_date]);
$status_distribution = $status_stmt->fetchAll();

include '../../includes/header.php';
include '../../includes/nav.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Reports & Analytics</h1>
            <p class="text-gray-600 mt-1">Comprehensive platform insights and metrics</p>
        </div>

        <!-- Date Range Filter -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition">
                        Generate Report
                    </button>
                </div>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Revenue</p>
                        <p class="text-3xl font-bold text-green-600 mt-2">$<?php echo number_format($summary['total_revenue'], 2); ?></p>
                        <p class="text-sm text-gray-500 mt-1"><?php echo number_format($summary['total_paid_bookings']); ?> paid bookings</p>
                    </div>
                    <div class="bg-green-100 rounded-full p-3">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Avg Booking Value</p>
                        <p class="text-3xl font-bold text-blue-600 mt-2">$<?php echo number_format($summary['avg_booking_value'], 2); ?></p>
                        <p class="text-sm text-gray-500 mt-1">Per transaction</p>
                    </div>
                    <div class="bg-blue-100 rounded-full p-3">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Refunds</p>
                        <p class="text-3xl font-bold text-red-600 mt-2">$<?php echo number_format($summary['refund_amount'], 2); ?></p>
                        <p class="text-sm text-gray-500 mt-1"><?php echo number_format($summary['total_refunds']); ?> refunds</p>
                    </div>
                    <div class="bg-red-100 rounded-full p-3">
                        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Net Revenue</p>
                        <p class="text-3xl font-bold text-purple-600 mt-2">$<?php echo number_format($summary['total_revenue'] - $summary['refund_amount'], 2); ?></p>
                        <p class="text-sm text-gray-500 mt-1">After refunds</p>
                    </div>
                    <div class="bg-purple-100 rounded-full p-3">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Payment Gateway Distribution -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Payment Gateway Distribution</h2>
                <div class="space-y-4">
                    <?php if (empty($gateway_stats)): ?>
                    <p class="text-sm text-gray-500">No payment data for selected period</p>
                    <?php else: ?>
                        <?php foreach ($gateway_stats as $gateway): ?>
                        <div>
                            <div class="flex justify-between mb-2">
                                <span class="text-sm font-medium text-gray-700 capitalize"><?php echo htmlspecialchars($gateway['payment_gateway']); ?></span>
                                <span class="text-sm font-semibold text-gray-900">$<?php echo number_format($gateway['total_amount'], 2); ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="flex-1 bg-gray-200 rounded-full h-2">
                                    <?php 
                                    $percentage = ($gateway['total_amount'] / $summary['total_revenue']) * 100;
                                    ?>
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo min($percentage, 100); ?>%"></div>
                                </div>
                                <span class="text-xs text-gray-600"><?php echo number_format($gateway['transaction_count']); ?> txn</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Booking Status Distribution -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Booking Status Distribution</h2>
                <div class="space-y-4">
                    <?php foreach ($status_distribution as $status): ?>
                    <div>
                        <div class="flex justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700 capitalize"><?php echo htmlspecialchars($status['booking_status']); ?></span>
                            <div class="text-right">
                                <span class="text-sm font-semibold text-gray-900"><?php echo number_format($status['count']); ?> bookings</span>
                                <span class="text-xs text-gray-500 ml-2">$<?php echo number_format($status['total_amount'], 2); ?></span>
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <?php 
                            $total_bookings = array_sum(array_column($status_distribution, 'count'));
                            $percentage = ($status['count'] / $total_bookings) * 100;
                            $colors = [
                                'confirmed' => 'bg-green-600',
                                'pending' => 'bg-yellow-600',
                                'cancelled' => 'bg-red-600',
                                'completed' => 'bg-blue-600'
                            ];
                            $color = $colors[$status['booking_status']] ?? 'bg-gray-600';
                            ?>
                            <div class="<?php echo $color; ?> h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Top Events -->
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Top Events by Revenue</h2>
            </div>
            <?php if (empty($top_events)): ?>
            <div class="p-12 text-center">
                <p class="text-sm text-gray-500">No events data for selected period</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rank</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Event</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Organizer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bookings</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($top_events as $index => $event): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-bold text-gray-900">#<?php echo $index + 1; ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($event['event_name']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($event['organizer']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-600"><?php echo date('M d, Y', strtotime($event['event_date'])); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo number_format($event['bookings']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-green-600">$<?php echo number_format($event['revenue'], 2); ?></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Daily Revenue Chart -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Daily Revenue Trend</h2>
            <?php if (empty($daily_revenue)): ?>
            <p class="text-sm text-gray-500 text-center py-8">No revenue data for selected period</p>
            <?php else: ?>
            <div class="space-y-2">
                <?php 
                $max_revenue = max(array_column($daily_revenue, 'revenue'));
                foreach ($daily_revenue as $day): 
                    $bar_width = $max_revenue > 0 ? ($day['revenue'] / $max_revenue) * 100 : 0;
                ?>
                <div class="flex items-center gap-4">
                    <div class="w-24 text-xs text-gray-600"><?php echo date('M d', strtotime($day['date'])); ?></div>
                    <div class="flex-1">
                        <div class="bg-gray-200 rounded-full h-6 relative">
                            <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-6 rounded-full flex items-center justify-end pr-2" 
                                 style="width: <?php echo $bar_width; ?>%">
                                <?php if ($bar_width > 15): ?>
                                <span class="text-xs font-medium text-white">$<?php echo number_format($day['revenue'], 2); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="w-20 text-xs text-gray-600 text-right"><?php echo $day['bookings']; ?> bookings</div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- User Registration Trend -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">New User Registrations</h2>
            <?php if (empty($user_trend)): ?>
            <p class="text-sm text-gray-500 text-center py-8">No registration data for selected period</p>
            <?php else: ?>
            <div class="space-y-2">
                <?php 
                $max_users = max(array_column($user_trend, 'new_users'));
                foreach ($user_trend as $day): 
                    $bar_width = $max_users > 0 ? ($day['new_users'] / $max_users) * 100 : 0;
                ?>
                <div class="flex items-center gap-4">
                    <div class="w-24 text-xs text-gray-600"><?php echo date('M d', strtotime($day['date'])); ?></div>
                    <div class="flex-1">
                        <div class="bg-gray-200 rounded-full h-6">
                            <div class="bg-gradient-to-r from-purple-500 to-purple-600 h-6 rounded-full flex items-center justify-end pr-2" 
                                 style="width: <?php echo $bar_width; ?>%">
                                <?php if ($bar_width > 20): ?>
                                <span class="text-xs font-medium text-white"><?php echo $day['new_users']; ?> users</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="w-20 text-xs text-gray-600 text-right"><?php echo $day['new_users']; ?> new</div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Export Options -->
        <div class="mt-8 flex gap-4">
            <a href="../../api/exports/revenue-report.php?start=<?php echo $start_date; ?>&end=<?php echo $end_date; ?>" 
               class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-medium transition">
                Export Revenue Report (CSV)
            </a>
            <a href="../../api/exports/all-bookings.php?start=<?php echo $start_date; ?>&end=<?php echo $end_date; ?>" 
               class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition">
                Export All Bookings (CSV)
            </a>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>