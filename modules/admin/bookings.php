<?php
// modules/admin/bookings.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Auth.php';

$auth = new Auth($pdo);
$auth->requireLogin();
$auth->requireRole(['admin']);

$admin_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle booking actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $booking_id = (int)$_POST['booking_id'];
        
        try {
            switch ($_POST['action']) {
                case 'confirm':
                    $stmt = $pdo->prepare("UPDATE bookings SET booking_status = 'confirmed' WHERE booking_id = ?");
                    $stmt->execute([$booking_id]);
                    $message = 'Booking confirmed successfully.';
                    
                    $log_stmt = $pdo->prepare("INSERT INTO audit_logs (admin_id, action, entity_type, entity_id) VALUES (?, 'confirm_booking', 'booking', ?)");
                    $log_stmt->execute([$admin_id, $booking_id]);
                    break;
                    
                case 'cancel':
                    $stmt = $pdo->prepare("UPDATE bookings SET booking_status = 'cancelled' WHERE booking_id = ?");
                    $stmt->execute([$booking_id]);
                    $message = 'Booking cancelled successfully.';
                    
                    $log_stmt = $pdo->prepare("INSERT INTO audit_logs (admin_id, action, entity_type, entity_id) VALUES (?, 'cancel_booking', 'booking', ?)");
                    $log_stmt->execute([$admin_id, $booking_id]);
                    break;
                    
                case 'refund':
                    // Update booking payment status
                    $stmt = $pdo->prepare("UPDATE bookings SET payment_status = 'refunded' WHERE booking_id = ?");
                    $stmt->execute([$booking_id]);
                    
                    // Update payment record
                    $payment_stmt = $pdo->prepare("UPDATE payments SET payment_status = 'refunded', refund_date = NOW() WHERE booking_id = ?");
                    $payment_stmt->execute([$booking_id]);
                    
                    $message = 'Booking refunded successfully.';
                    
                    $log_stmt = $pdo->prepare("INSERT INTO audit_logs (admin_id, action, entity_type, entity_id) VALUES (?, 'refund_booking', 'booking', ?)");
                    $log_stmt->execute([$admin_id, $booking_id]);
                    break;
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$booking_status_filter = isset($_GET['booking_status']) ? $_GET['booking_status'] : '';
$payment_status_filter = isset($_GET['payment_status']) ? $_GET['payment_status'] : '';
$event_filter = isset($_GET['event']) ? (int)$_GET['event'] : 0;

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(b.booking_reference LIKE ? OR b.participant_name LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($booking_status_filter) {
    $where_conditions[] = "b.booking_status = ?";
    $params[] = $booking_status_filter;
}

if ($payment_status_filter) {
    $where_conditions[] = "b.payment_status = ?";
    $params[] = $payment_status_filter;
}

if ($event_filter) {
    $where_conditions[] = "b.event_id = ?";
    $params[] = $event_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM bookings b 
              JOIN users u ON b.user_id = u.user_id 
              JOIN events e ON b.event_id = e.event_id 
              $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_bookings = $count_stmt->fetch()['total'];
$total_pages = ceil($total_bookings / $per_page);

// Get bookings
$sql = "SELECT b.*, e.event_name, e.event_date, e.location, 
        u.full_name as booked_by, u.email as booker_email,
        org.full_name as organizer_name
        FROM bookings b
        JOIN events e ON b.event_id = e.event_id
        JOIN users u ON b.user_id = u.user_id
        JOIN users org ON e.organizer_id = org.user_id
        $where_clause
        ORDER BY b.booking_date DESC
        LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Get events for filter
$events_sql = "SELECT event_id, event_name FROM events ORDER BY event_date DESC LIMIT 50";
$events = $pdo->query($events_sql)->fetchAll();

include __DIR__ . '/../../includes/header.php';
// include __DIR__ . '/../../includes/nav.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Booking Management</h1>
            <p class="text-gray-600 mt-1">Manage all bookings across the platform</p>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Reference, name..." 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Event</label>
                    <select name="event" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">All Events</option>
                        <?php foreach ($events as $evt): ?>
                        <option value="<?php echo $evt['event_id']; ?>" <?php echo $event_filter === $evt['event_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($evt['event_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Booking Status</label>
                    <select name="booking_status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">All</option>
                        <option value="pending" <?php echo $booking_status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $booking_status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="cancelled" <?php echo $booking_status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="completed" <?php echo $booking_status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Payment Status</label>
                    <select name="payment_status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">All</option>
                        <option value="pending" <?php echo $payment_status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="paid" <?php echo $payment_status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="refunded" <?php echo $payment_status_filter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                        <option value="failed" <?php echo $payment_status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition">
                        Filter
                    </button>
                    <a href="bookings.php" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Bookings Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-900">Bookings (<?php echo number_format($total_bookings); ?>)</h2>
                    <a href="../../api/exports/all-bookings.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                        Export CSV
                    </a>
                </div>
            </div>

            <?php if (empty($bookings)): ?>
            <div class="p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No bookings found</h3>
                <p class="mt-1 text-sm text-gray-500">Try adjusting your filters</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Participant</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Event</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Booked By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($bookings as $booking): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($booking['booking_reference']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo date('M d, Y', strtotime($booking['booking_date'])); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($booking['participant_name']); ?></div>
                                <?php if ($booking['participant_age']): ?>
                                <div class="text-xs text-gray-500">Age: <?php echo $booking['participant_age']; ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($booking['event_name']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo date('M d', strtotime($booking['event_date'])); ?> • <?php echo htmlspecialchars($booking['location']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($booking['booked_by']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($booking['booker_email']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-gray-900">$<?php echo number_format($booking['amount_paid'], 2); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $booking_status_colors = [
                                    'confirmed' => 'bg-green-100 text-green-800',
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'cancelled' => 'bg-red-100 text-red-800',
                                    'completed' => 'bg-blue-100 text-blue-800'
                                ];
                                $color = $booking_status_colors[$booking['booking_status']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $color; ?>">
                                    <?php echo ucfirst($booking['booking_status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $payment_status_colors = [
                                    'paid' => 'bg-green-100 text-green-800',
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'failed' => 'bg-red-100 text-red-800',
                                    'refunded' => 'bg-gray-100 text-gray-800'
                                ];
                                $color = $payment_status_colors[$booking['payment_status']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $color; ?>">
                                    <?php echo ucfirst($booking['payment_status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex flex-col gap-2">
                                    <?php if ($booking['booking_status'] === 'pending'): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                        <input type="hidden" name="action" value="confirm">
                                        <button type="submit" class="text-green-600 hover:text-green-800 text-xs">Confirm</button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($booking['booking_status'] !== 'cancelled'): ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Cancel this booking?');">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                        <input type="hidden" name="action" value="cancel">
                                        <button type="submit" class="text-red-600 hover:text-red-800 text-xs">Cancel</button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($booking['payment_status'] === 'paid'): ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Process refund?');">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                        <input type="hidden" name="action" value="refund">
                                        <button type="submit" class="text-blue-600 hover:text-blue-800 text-xs">Refund</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </div>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&booking_status=<?php echo $booking_status_filter; ?>&payment_status=<?php echo $payment_status_filter; ?>&event=<?php echo $event_filter; ?>" 
                           class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&booking_status=<?php echo $booking_status_filter; ?>&payment_status=<?php echo $payment_status_filter; ?>&event=<?php echo $event_filter; ?>" 
                           class="px-3 py-1 border rounded <?php echo $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&booking_status=<?php echo $booking_status_filter; ?>&payment_status=<?php echo $payment_status_filter; ?>&event=<?php echo $event_filter; ?>" 
                           class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>