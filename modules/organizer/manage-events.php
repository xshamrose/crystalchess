<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Auth.php';


$auth = new Auth($pdo);
$auth->requireLogin();
$auth->requireRole(['organizer', 'admin']);

$user = $_SESSION['user'];
$organizer_id = $user['user_id'];

// Handle event deletion
if (isset($_GET['delete']) && isset($_GET['event_id'])) {
    $event_id = (int)$_GET['event_id'];
    
    // Verify ownership
    $check_sql = "SELECT event_id FROM events WHERE event_id = ? AND organizer_id = ?";
    $stmt = $pdo->prepare($check_sql);
    $stmt->execute([$event_id, $organizer_id]);
    
    if ($stmt->fetch()) {
        // Check if there are confirmed bookings
        $booking_check = "SELECT COUNT(*) as count FROM bookings WHERE event_id = ? AND booking_status = 'confirmed'";
        $stmt = $pdo->prepare($booking_check);
        $stmt->execute([$event_id]);
        $booking_count = $stmt->fetch()['count'];
        
        if ($booking_count > 0) {
            $_SESSION['error_message'] = "Cannot delete event with confirmed bookings. Please cancel the event instead.";
        } else {
            $delete_sql = "DELETE FROM events WHERE event_id = ?";
            $stmt = $pdo->prepare($delete_sql);
            $stmt->execute([$event_id]);
            $_SESSION['success_message'] = "Event deleted successfully!";
        }
    }
    
    header('Location: manage-events.php');
    exit;
}

// Handle event status update
if (isset($_GET['update_status']) && isset($_GET['event_id']) && isset($_GET['status'])) {
    $event_id = (int)$_GET['event_id'];
    $new_status = $_GET['status'];
    $allowed_statuses = ['upcoming', 'in_progress', 'completed', 'cancelled'];
    
    if (in_array($new_status, $allowed_statuses)) {
        $check_sql = "SELECT event_id FROM events WHERE event_id = ? AND organizer_id = ?";
        $stmt = $pdo->prepare($check_sql);
        $stmt->execute([$event_id, $organizer_id]);
        
        if ($stmt->fetch()) {
            $update_sql = "UPDATE events SET status = ? WHERE event_id = ?";
            $stmt = $pdo->prepare($update_sql);
            $stmt->execute([$new_status, $event_id]);
            $_SESSION['success_message'] = "Event status updated to " . ucfirst($new_status);
        }
    }
    
    header('Location: manage-events.php');
    exit;
}

// Fetch events with filters
$filter_status = $_GET['status'] ?? 'all';
$search_query = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$where_conditions = ["e.organizer_id = ?"];
$params = [$organizer_id];

if ($filter_status !== 'all') {
    $where_conditions[] = "e.status = ?";
    $params[] = $filter_status;
}

if (!empty($search_query)) {
    $where_conditions[] = "(e.event_name LIKE ? OR e.location LIKE ?)";
    $search_param = "%{$search_query}%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Count total events
$count_sql = "SELECT COUNT(*) as total FROM events e WHERE {$where_clause}";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_events = $stmt->fetch()['total'];
$total_pages = ceil($total_events / $per_page);

// Fetch events
$sql = "
    SELECT 
        e.*,
        COUNT(b.booking_id) as booking_count,
        SUM(CASE WHEN b.payment_status = 'paid' THEN b.amount_paid ELSE 0 END) as revenue
    FROM events e
    LEFT JOIN bookings b ON e.event_id = b.event_id AND b.booking_status IN ('confirmed', 'pending')
    WHERE {$where_clause}
    GROUP BY e.event_id
    ORDER BY e.event_date DESC
    LIMIT ? OFFSET ?
";

$params[] = $per_page;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../../includes/header.php';
// include __DIR__ . '/../../includes/nav.php';

?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Manage Events</h1>
                    <p class="mt-2 text-gray-600">View and manage all your chess tournaments</p>
                </div>
                <a href="create-event" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <svg class="inline-block w-5 h-5 mr-2 -mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Create Event
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex">
                    <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                    </svg>
                    <p class="ml-3 text-sm text-green-700"><?= htmlspecialchars($_SESSION['success_message']) ?></p>
                </div>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex">
                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
                    </svg>
                    <p class="ml-3 text-sm text-red-700"><?= htmlspecialchars($_SESSION['error_message']) ?></p>
                </div>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Filters & Search -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4">
                <form method="GET" class="flex flex-col md:flex-row gap-4">
                    <!-- Search -->
                    <div class="flex-1">
                        <input type="text" name="search" placeholder="Search events by name or location..."
                               value="<?= htmlspecialchars($search_query) ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    
                    <!-- Status Filter -->
                    <div>
                        <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="upcoming" <?= $filter_status === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                            <option value="in_progress" <?= $filter_status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <!-- Search Button -->
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        Search
                    </button>
                    
                    <?php if (!empty($search_query) || $filter_status !== 'all'): ?>
                        <a href="manage-events.php" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                            Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Events Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <?php if (empty($events)): ?>
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No events found</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by creating your first event.</p>
                    <div class="mt-6">
                        <a href="create-event" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Create Event
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Event</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date & Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Capacity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Revenue</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($events as $event): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <?php if ($event['event_image']): ?>
                                                <img src="../../<?= htmlspecialchars($event['event_image']) ?>" 
                                                     alt="Event" class="h-10 w-10 rounded object-cover">
                                            <?php else: ?>
                                                <div class="h-10 w-10 rounded bg-gray-200 flex items-center justify-center">
                                                    <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?= htmlspecialchars($event['event_name']) ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    $<?= number_format($event['entry_fee'], 2) ?> entry fee
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?= date('M j, Y', strtotime($event['event_date'])) ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?= date('g:i A', strtotime($event['event_time'])) ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?= htmlspecialchars($event['location']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?= $event['current_bookings'] ?> / <?= $event['max_capacity'] ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php 
                                                $percentage = $event['max_capacity'] > 0 
                                                    ? round(($event['current_bookings'] / $event['max_capacity']) * 100) 
                                                    : 0;
                                                echo $percentage . '% full';
                                            ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        $<?= number_format($event['revenue'] ?? 0, 2) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                            <?php
                                                echo match($event['status']) {
                                                    'upcoming' => 'bg-green-100 text-green-800',
                                                    'in_progress' => 'bg-blue-100 text-blue-800',
                                                    'completed' => 'bg-gray-100 text-gray-800',
                                                    'cancelled' => 'bg-red-100 text-red-800',
                                                    default => 'bg-gray-100 text-gray-800'
                                                };
                                            ?>
                                        ">
                                            <?= ucfirst(str_replace('_', ' ', $event['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end space-x-2">
                                            <a href="participants.php?event_id=<?= $event['event_id'] ?>" 
                                               class="text-blue-600 hover:text-blue-900" title="View Participants">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                                </svg>
                                            </a>
                                            <a href="edit-event.php?id=<?= $event['event_id'] ?>" 
                                               class="text-gray-600 hover:text-gray-900" title="Edit">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </a>
                                            <button onclick="confirmDelete(<?= $event['event_id'] ?>, '<?= htmlspecialchars(addslashes($event['event_name'])) ?>')"
                                                    class="text-red-600 hover:text-red-900" title="Delete">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="bg-white px-6 py-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing <span class="font-medium"><?= $offset + 1 ?></span> to 
                                <span class="font-medium"><?= min($offset + $per_page, $total_events) ?></span> of 
                                <span class="font-medium"><?= $total_events ?></span> events
                            </div>
                            <div class="flex space-x-2">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?>&status=<?= $filter_status ?>&search=<?= urlencode($search_query) ?>"
                                       class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                        Previous
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="px-4 py-2 bg-blue-600 text-white rounded-lg"><?= $i ?></span>
                                    <?php elseif (abs($i - $page) <= 2 || $i == 1 || $i == $total_pages): ?>
                                        <a href="?page=<?= $i ?>&status=<?= $filter_status ?>&search=<?= urlencode($search_query) ?>"
                                           class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                            <?= $i ?>
                                        </a>
                                    <?php elseif (abs($i - $page) == 3): ?>
                                        <span class="px-4 py-2">...</span>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?= $page + 1 ?>&status=<?= $filter_status ?>&search=<?= urlencode($search_query) ?>"
                                       class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                        Next
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
function confirmDelete(eventId, eventName) {
    if (confirm(`Are you sure you want to delete "${eventName}"?\n\nThis action cannot be undone and will fail if there are confirmed bookings.`)) {
        window.location.href = `manage-events.php?delete=1&event_id=${eventId}`;
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>