<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Auth.php';

Auth::requireLogin();
$user = Auth::getUser();

// Filters
$status_filter = $_GET['event_status'] ?? 'all';
$payment_filter = $_GET['payment'] ?? 'all';
$search = $_GET['search'] ?? '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query
$where = ["b.user_id = ?"];
$params = [$user['user_id']];

if ($status_filter !== 'all') {
    $where[] = "b.booking_status = ?";
    $params[] = $status_filter;
}

if ($payment_filter !== 'all') {
    $where[] = "b.payment_status = ?";
    $params[] = $payment_filter;
}

if (!empty($search)) {
    $where[] = "(e.event_name LIKE ? OR b.booking_reference LIKE ? OR b.participant_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = implode(' AND ', $where);

try {
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM bookings b JOIN events e ON b.event_id = e.event_id WHERE $where_clause";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_records = $stmt->fetch()['total'];
    $total_pages = ceil($total_records / $per_page);
    
    // Get bookings
    $sql = "
        SELECT 
            b.booking_id,
            b.booking_reference,
            b.participant_name,
            b.booking_status,
            b.payment_status,
            b.amount_paid,
            b.booking_date,
            e.event_id,
            e.event_name,
            e.event_date,
            e.event_time,
            e.location
        FROM bookings b
        JOIN events e ON b.event_id = e.event_id
        WHERE $where_clause
        ORDER BY b.booking_date DESC
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
    
} catch(PDOException $e) {
    $error = "Error loading bookings";
    $bookings = [];
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Booking History</h1>
                    <p class="text-gray-600 mt-2">View and manage all your tournament bookings</p>
                </div>
                <a href="dashboard" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Filters & Search -->
        <div class="bg-white rounded-lg shadow mb-6 p-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                
                <!-- Search -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <div class="relative">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Event name, booking ref, participant..."
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>

                <!-- Booking Status -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Booking Status</label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>

                <!-- Payment Status -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Payment Status</label>
                    <select name="payment" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="all" <?php echo $payment_filter === 'all' ? 'selected' : ''; ?>>All Payments</option>
                        <option value="pending" <?php echo $payment_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="paid" <?php echo $payment_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="failed" <?php echo $payment_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="refunded" <?php echo $payment_filter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                    </select>
                </div>

                <!-- Filter Buttons -->
                <div class="md:col-span-4 flex items-center space-x-3">
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                        Apply Filters
                    </button>
                    <a href="booking-history.php" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-medium">
                        Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Bookings List -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            
            <?php if (empty($bookings)): ?>
                <!-- Empty State -->
                <div class="text-center py-16">
                    <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="mt-4 text-lg font-medium text-gray-900">No bookings found</h3>
                    <p class="mt-2 text-sm text-gray-500">
                        <?php if (!empty($search) || $status_filter !== 'all' || $payment_filter !== 'all'): ?>
                            Try adjusting your filters or search terms
                        <?php else: ?>
                            Start by booking your first tournament!
                        <?php endif; ?>
                    </p>
                    <div class="mt-6">
                        <a href="../events/browse.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                            Browse Events
                        </a>
                    </div>
                </div>
            <?php else: ?>
                
                <!-- Results Count -->
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <p class="text-sm text-gray-700">
                        Showing <span class="font-medium"><?php echo count($bookings); ?></span> of 
                        <span class="font-medium"><?php echo $total_records; ?></span> bookings
                    </p>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Booking Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($bookings as $booking): ?>
                                <tr class="hover:bg-gray-50">
                                    <!-- Booking Details -->
                                    <td class="px-6 py-4">
                                        <div class="text-sm">
                                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($booking['participant_name']); ?></div>
                                            <div class="text-gray-500 mt-1">Ref: <?php echo $booking['booking_reference']; ?></div>
                                            <div class="text-gray-400 text-xs mt-1">
                                                Booked: <?php echo date('M j, Y', strtotime($booking['booking_date'])); ?>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Event -->
                                    <td class="px-6 py-4">
                                        <div class="text-sm">
                                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($booking['event_name']); ?></div>
                                            <div class="text-gray-500 mt-1 flex items-center">
                                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                </svg>
                                                <?php echo htmlspecialchars($booking['location']); ?>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Date -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo date('M j, Y', strtotime($booking['event_date'])); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo date('g:i A', strtotime($booking['event_time'])); ?>
                                        </div>
                                    </td>

                                    <!-- Amount -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            $<?php echo number_format($booking['amount_paid'], 2); ?>
                                        </div>
                                    </td>

                                    <!-- Status -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="space-y-1">
                                            <!-- Booking Status -->
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                <?php 
                                                if ($booking['booking_status'] === 'confirmed') echo 'bg-green-100 text-green-800';
                                                elseif ($booking['booking_status'] === 'pending') echo 'bg-yellow-100 text-yellow-800';
                                                elseif ($booking['booking_status'] === 'cancelled') echo 'bg-red-100 text-red-800';
                                                else echo 'bg-gray-100 text-gray-800';
                                                ?>">
                                                <?php echo ucfirst($booking['booking_status']); ?>
                                            </span>
                                            
                                            <!-- Payment Status -->
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                <?php 
                                                if ($booking['payment_status'] === 'paid') echo 'bg-blue-100 text-blue-800';
                                                elseif ($booking['payment_status'] === 'pending') echo 'bg-yellow-100 text-yellow-800';
                                                elseif ($booking['payment_status'] === 'failed') echo 'bg-red-100 text-red-800';
                                                else echo 'bg-purple-100 text-purple-800';
                                                ?>">
                                                <?php echo ucfirst($booking['payment_status']); ?>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <div class="flex items-center space-x-3">
                                            <a href="../events/details.php?id=<?php echo $booking['event_id']; ?>" 
                                               class="text-blue-600 hover:text-blue-900 font-medium"
                                               title="View Event">
                                                View
                                            </a>
                                            <?php if ($booking['booking_status'] !== 'cancelled' && strtotime($booking['event_date']) > time()): ?>
                                                <button onclick="cancelBooking(<?php echo $booking['booking_id']; ?>)"
                                                        class="text-red-600 hover:text-red-900 font-medium"
                                                        title="Cancel Booking">
                                                    Cancel
                                                </button>
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
                    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&event_status=<?php echo $status_filter; ?>&payment=<?php echo $payment_filter; ?>&search=<?php echo urlencode($search); ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Previous
                                </a>
                            <?php endif; ?>
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&event_status=<?php echo $status_filter; ?>&payment=<?php echo $payment_filter; ?>&search=<?php echo urlencode($search); ?>" 
                                   class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Next
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing page <span class="font-medium"><?php echo $page; ?></span> of 
                                    <span class="font-medium"><?php echo $total_pages; ?></span>
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?php echo $page - 1; ?>&event_status=<?php echo $status_filter; ?>&payment=<?php echo $payment_filter; ?>&search=<?php echo urlencode($search); ?>" 
                                           class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <?php if ($i === $page): ?>
                                            <span class="relative inline-flex items-center px-4 py-2 border border-blue-500 bg-blue-50 text-sm font-medium text-blue-600">
                                                <?php echo $i; ?>
                                            </span>
                                        <?php elseif ($i === 1 || $i === $total_pages || abs($i - $page) <= 2): ?>
                                            <a href="?page=<?php echo $i; ?>&event_status=<?php echo $status_filter; ?>&payment=<?php echo $payment_filter; ?>&search=<?php echo urlencode($search); ?>" 
                                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php elseif (abs($i - $page) === 3): ?>
                                            <span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?php echo $page + 1; ?>&event_status=<?php echo $status_filter; ?>&payment=<?php echo $payment_filter; ?>&search=<?php echo urlencode($search); ?>" 
                                           class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Cancel Booking Modal -->
<div id="cancelModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3 text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            <h3 class="text-lg leading-6 font-medium text-gray-900 mt-4">Cancel Booking</h3>
            <div class="mt-2 px-7 py-3">
                <p class="text-sm text-gray-500">
                    Are you sure you want to cancel this booking? This action cannot be undone.
                </p>
            </div>
            <div class="items-center px-4 py-3 space-x-3">
                <button id="confirmCancel" 
                        class="px-4 py-2 bg-red-600 text-white text-base font-medium rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                    Yes, Cancel Booking
                </button>
                <button onclick="closeCancelModal()" 
                        class="px-4 py-2 bg-gray-300 text-gray-700 text-base font-medium rounded-md shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                    No, Keep It
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let bookingToCancel = null;

function cancelBooking(bookingId) {
    bookingToCancel = bookingId;
    document.getElementById('cancelModal').classList.remove('hidden');
}

function closeCancelModal() {
    bookingToCancel = null;
    document.getElementById('cancelModal').classList.add('hidden');
}

document.getElementById('confirmCancel').addEventListener('click', async function() {
    if (!bookingToCancel) return;
    
    try {
        const response = await fetch('../../api/bookings/cancel.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ booking_id: bookingToCancel })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Booking cancelled successfully');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to cancel booking'));
        }
    } catch (error) {
        alert('Error cancelling booking. Please try again.');
    }
    
    closeCancelModal();
});

// Close modal on outside click
document.getElementById('cancelModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCancelModal();
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>