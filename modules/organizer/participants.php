<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Auth.php';

$auth = new Auth($pdo);
$auth->requireLogin();
$auth->requireRole(['organizer', 'admin']);

$user = $_SESSION['user'];
$organizer_id = $user['user_id'];

// Get event_id from URL
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : null;

// If no event_id, fetch all events for dropdown
if (!$event_id) {
    $events_sql = "SELECT event_id, event_name, event_date FROM events WHERE organizer_id = ? ORDER BY event_date DESC";
    $stmt = $pdo->prepare($events_sql);
    $stmt->execute([$organizer_id]);
    $all_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Verify event ownership
    $check_sql = "SELECT * FROM events WHERE event_id = ? AND organizer_id = ?";
    $stmt = $pdo->prepare($check_sql);
    $stmt->execute([$event_id, $organizer_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        $_SESSION['error_message'] = "Event not found or you don't have permission to view it.";
        header('Location: manage-events.php');
        exit;
    }
    
    // Fetch participants
    $participants_sql = "
        SELECT 
            b.*,
            u.email as booker_email,
            u.phone as booker_phone,
            u.full_name as booker_name
        FROM bookings b
        JOIN users u ON b.user_id = u.user_id
        WHERE b.event_id = ?
        ORDER BY b.booking_date DESC
    ";
    $stmt = $pdo->prepare($participants_sql);
    $stmt->execute([$event_id]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stats_sql = "
        SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN booking_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count,
            SUM(CASE WHEN booking_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN booking_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
            SUM(CASE WHEN payment_status = 'paid' THEN amount_paid ELSE 0 END) as total_revenue
        FROM bookings
        WHERE event_id = ?
    ";
    $stmt = $pdo->prepare($stats_sql);
    $stmt->execute([$event_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
}

include __DIR__ . '/../../includes/header.php';
// include __DIR__ . '/../../includes/nav.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <?php if (!$event_id): ?>
            <!-- Event Selection -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-4">Participants</h1>
                <p class="text-gray-600 mb-6">Select an event to view its participants</p>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Event</label>
                    <select onchange="if(this.value) window.location.href='participants.php?event_id='+this.value"
                            class="w-full max-w-md px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Choose an event...</option>
                        <?php foreach ($all_events as $ev): ?>
                            <option value="<?= $ev['event_id'] ?>">
                                <?= htmlspecialchars($ev['event_name']) ?> - <?= date('M j, Y', strtotime($ev['event_date'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        <?php else: ?>
            <!-- Event Header -->
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900"><?= htmlspecialchars($event['event_name']) ?></h1>
                        <p class="mt-2 text-gray-600">
                            <?= date('F j, Y', strtotime($event['event_date'])) ?> at <?= date('g:i A', strtotime($event['event_time'])) ?>
                            • <?= htmlspecialchars($event['location']) ?>
                        </p>
                    </div>
                    <div class="flex space-x-3">
                        <a href="/chess/crystalchess/modules/organizer/participants.php?event_id=<?= $event['event_id'] ?>" 
                           class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                           View Participants
                        </a>
                        <a href="manage-events.php" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            Back to Events
                        </a>
                        <button onclick="exportParticipants()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                            Export CSV
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <!-- Total Bookings -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-blue-100 rounded-md p-3">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Total Bookings</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $stats['total_bookings'] ?></p>
                        </div>
                    </div>
                </div>

                <!-- Confirmed -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-green-100 rounded-md p-3">
                            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Confirmed</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $stats['confirmed_count'] ?></p>
                        </div>
                    </div>
                </div>

                <!-- Pending -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-yellow-100 rounded-md p-3">
                            <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Pending</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $stats['pending_count'] ?></p>
                        </div>
                    </div>
                </div>

                <!-- Revenue -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-purple-100 rounded-md p-3">
                            <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-500">Revenue</p>
                            <p class="text-2xl font-semibold text-gray-900">$<?= number_format($stats['total_revenue'], 2) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Participants Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Participant List</h2>
                </div>
                
                <?php if (empty($participants)): ?>
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <p class="mt-2 text-gray-500">No participants yet</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Participant</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Booked By</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Booking Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($participants as $participant): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($participant['booking_reference']) ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($participant['participant_name']) ?>
                                            </div>
                                            <?php if ($participant['participant_age']): ?>
                                                <div class="text-sm text-gray-500">Age: <?= $participant['participant_age'] ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($participant['participant_email']): ?>
                                                <div class="text-sm text-gray-900"><?= htmlspecialchars($participant['participant_email']) ?></div>
                                            <?php endif; ?>
                                            <?php if ($participant['participant_phone']): ?>
                                                <div class="text-sm text-gray-500"><?= htmlspecialchars($participant['participant_phone']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm text-gray-900"><?= htmlspecialchars($participant['booker_name']) ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($participant['booker_email']) ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= date('M j, Y', strtotime($participant['booking_date'])) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            $<?= number_format($participant['amount_paid'], 2) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                                <?php
                                                    echo match($participant['booking_status']) {
                                                        'confirmed' => 'bg-green-100 text-green-800',
                                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                                        'cancelled' => 'bg-red-100 text-red-800',
                                                        default => 'bg-gray-100 text-gray-800'
                                                    };
                                                ?>
                                            ">
                                                <?= ucfirst($participant['booking_status']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                                <?php
                                                    echo match($participant['payment_status']) {
                                                        'paid' => 'bg-green-100 text-green-800',
                                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                                        'failed' => 'bg-red-100 text-red-800',
                                                        'refunded' => 'bg-gray-100 text-gray-800',
                                                        default => 'bg-gray-100 text-gray-800'
                                                    };
                                                ?>
                                            ">
                                                <?= ucfirst($participant['payment_status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
function exportParticipants() {
    const eventId = <?= $event_id ?? 0 ?>;
    if (eventId) {
        window.location.href = `../../api/exports/participants.php?event_id=${eventId}`;
    }
}
</script>

<?php include '../../includes/footer.php'; ?>