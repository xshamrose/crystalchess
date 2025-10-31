<?php
// modules/admin/events.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Auth.php';


$auth = new Auth($pdo);
$auth->requireLogin();
$auth->requireRole(['admin']);

$admin_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle event actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $event_id = (int)$_POST['event_id'];
        
        try {
            switch ($_POST['action']) {
                case 'feature':
                    $featured = (int)$_POST['featured'];
                    $stmt = $pdo->prepare("UPDATE events SET featured = ? WHERE event_id = ?");
                    $stmt->execute([$featured, $event_id]);
                    $message = $featured ? 'Event featured successfully.' : 'Event unfeatured successfully.';
                    
                    $log_stmt = $pdo->prepare("INSERT INTO audit_logs (admin_id, action, entity_type, entity_id) VALUES (?, ?, 'event', ?)");
                    $log_stmt->execute([$admin_id, $featured ? 'feature_event' : 'unfeature_event', $event_id]);
                    break;
                    
                case 'cancel':
                    $stmt = $pdo->prepare("UPDATE events SET status = 'cancelled' WHERE event_id = ?");
                    $stmt->execute([$event_id]);
                    $message = 'Event cancelled successfully.';
                    
                    $log_stmt = $pdo->prepare("INSERT INTO audit_logs (admin_id, action, entity_type, entity_id) VALUES (?, 'cancel_event', 'event', ?)");
                    $log_stmt->execute([$admin_id, $event_id]);
                    break;
                    
                case 'delete':
                    // Check for bookings
                    $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bookings WHERE event_id = ? AND booking_status IN ('confirmed', 'pending')");
                    $check_stmt->execute([$event_id]);
                    $booking_count = $check_stmt->fetch()['count'];
                    
                    if ($booking_count > 0) {
                        $error = 'Cannot delete event with active bookings. Cancel the event instead.';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM events WHERE event_id = ?");
                        $stmt->execute([$event_id]);
                        $message = 'Event deleted successfully.';
                        
                        $log_stmt = $pdo->prepare("INSERT INTO audit_logs (admin_id, action, entity_type, entity_id) VALUES (?, 'delete_event', 'event', ?)");
                        $log_stmt->execute([$admin_id, $event_id]);
                    }
                    break;
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$organizer_filter = isset($_GET['organizer']) ? (int)$_GET['organizer'] : 0;

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(e.event_name LIKE ? OR e.location LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $where_conditions[] = "e.status = ?";
    $params[] = $status_filter;
}

if ($organizer_filter) {
    $where_conditions[] = "e.organizer_id = ?";
    $params[] = $organizer_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM events e $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_events = $count_stmt->fetch()['total'];
$total_pages = ceil($total_events / $per_page);

// Get events
$sql = "SELECT e.*, u.full_name as organizer_name, u.email as organizer_email,
        COUNT(DISTINCT b.booking_id) as booking_count,
        COALESCE(SUM(CASE WHEN b.payment_status = 'paid' THEN b.amount_paid ELSE 0 END), 0) as revenue
        FROM events e
        JOIN users u ON e.organizer_id = u.user_id
        LEFT JOIN bookings b ON e.event_id = b.event_id
        $where_clause
        GROUP BY e.event_id
        ORDER BY e.event_date DESC
        LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Get organizers for filter
$org_sql = "SELECT DISTINCT u.user_id, u.full_name FROM users u 
            JOIN events e ON u.user_id = e.organizer_id 
            WHERE u.user_type = 'organizer' ORDER BY u.full_name";
$organizers = $pdo->query($org_sql)->fetchAll();

include __DIR__ . '/../../includes/header.php';
// include __DIR__ . '/../../includes/nav.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Event Management</h1>
            <p class="text-gray-600 mt-1">Manage all events across the platform</p>
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
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Event name or location..." 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">All Statuses</option>
                        <option value="upcoming" <?php echo $status_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Organizer</label>
                    <select name="organizer" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">All Organizers</option>
                        <?php foreach ($organizers as $org): ?>
                        <option value="<?php echo $org['user_id']; ?>" <?php echo $organizer_filter === $org['user_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($org['full_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition">
                        Filter
                    </button>
                    <a href="events.php" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Events Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Events (<?php echo number_format($total_events); ?>)</h2>
            </div>

            <?php if (empty($events)): ?>
            <div class="p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No events found</h3>
                <p class="mt-1 text-sm text-gray-500">Try adjusting your filters</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Event</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Organizer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date & Location</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Capacity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Revenue</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($events as $event): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <?php if ($event['event_image']): ?>
                                    <img src="../../uploads/events/<?php echo htmlspecialchars($event['event_image']); ?>" 
                                         alt="Event" class="h-10 w-10 rounded object-cover">
                                    <?php else: ?>
                                    <div class="h-10 w-10 rounded bg-gray-200 flex items-center justify-center">
                                        <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                    <?php endif; ?>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($event['event_name']); ?></div>
                                        <?php if ($event['featured']): ?>
                                        <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded">Featured</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($event['organizer_name']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($event['organizer_email']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($event['event_date'])); ?></div>
                                <div class="text-sm text-gray-500"><?php echo date('g:i A', strtotime($event['event_time'])); ?> • <?php echo htmlspecialchars($event['location']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo $event['current_bookings']; ?> / <?php echo $event['max_capacity']; ?></div>
                                <?php 
                                $percentage = $event['max_capacity'] > 0 ? ($event['current_bookings'] / $event['max_capacity']) * 100 : 0;
                                $bar_color = $percentage >= 90 ? 'bg-red-600' : ($percentage >= 70 ? 'bg-yellow-600' : 'bg-green-600');
                                ?>
                                <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                                    <div class="<?php echo $bar_color; ?> h-1.5 rounded-full" style="width: <?php echo min($percentage, 100); ?>%"></div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-green-600">$<?php echo number_format($event['revenue'], 2); ?></div>
                                <div class="text-xs text-gray-500"><?php echo $event['booking_count']; ?> bookings</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $status_colors = [
                                    'upcoming' => 'bg-blue-100 text-blue-800',
                                    'in_progress' => 'bg-green-100 text-green-800',
                                    'completed' => 'bg-gray-100 text-gray-800',
                                    'cancelled' => 'bg-red-100 text-red-800'
                                ];
                                $color = $status_colors[$event['status']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $color; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $event['status'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex flex-col gap-2">
                                    <!-- Feature/Unfeature -->
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                        <input type="hidden" name="action" value="feature">
                                        <input type="hidden" name="featured" value="<?php echo $event['featured'] ? 0 : 1; ?>">
                                        <button type="submit" class="text-yellow-600 hover:text-yellow-800 text-xs">
                                            <?php echo $event['featured'] ? 'Unfeature' : 'Feature'; ?>
                                        </button>
                                    </form>
                                    
                                    <!-- Cancel -->
                                    <?php if ($event['status'] !== 'cancelled' && $event['status'] !== 'completed'): ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Cancel this event?');">
                                        <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                        <input type="hidden" name="action" value="cancel">
                                        <button type="submit" class="text-orange-600 hover:text-orange-800 text-xs">Cancel</button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <!-- Delete -->
                                    <form method="POST" class="inline" onsubmit="return confirm('Permanently delete this event?');">
                                        <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="text-red-600 hover:text-red-800 text-xs">Delete</button>
                                    </form>
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
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&organizer=<?php echo $organizer_filter; ?>" 
                           class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&organizer=<?php echo $organizer_filter; ?>" 
                           class="px-3 py-1 border rounded <?php echo $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&organizer=<?php echo $organizer_filter; ?>" 
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>