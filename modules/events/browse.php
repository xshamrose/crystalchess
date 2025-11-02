<?php
/**
 * Event Browsing Page
 * Display all events with search and filter functionality
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';

// Initialize Auth and Database
$auth = new Auth();
$db = Database::getInstance()->getConnection();

// Pagination
$perPage = 12; // Increased from 9
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

// Filters
$search = $_GET['search'] ?? '';
$location = $_GET['location'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$feeMin = isset($_GET['fee_min']) ? floatval($_GET['fee_min']) : 0;
$feeMax = isset($_GET['fee_max']) && $_GET['fee_max'] !== '' ? floatval($_GET['fee_max']) : 999999;
$status = $_GET['event_status'] ?? 'upcoming';

// Build WHERE clause
$whereConditions = ["e.event_status = :event_status"];
$params = [':event_status' => $status];

if (!empty($search)) {
    $whereConditions[] = "(e.event_name LIKE :search OR e.description LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($location)) {
    $whereConditions[] = "e.location LIKE :location";
    $params[':location'] = "%$location%";
}

if (!empty($dateFrom)) {
    $whereConditions[] = "e.event_date >= :date_from";
    $params[':date_from'] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereConditions[] = "e.event_date <= :date_to";
    $params[':date_to'] = $dateTo;
}

$whereConditions[] = "e.entry_fee BETWEEN :fee_min AND :fee_max";
$params[':fee_min'] = $feeMin;
$params[':fee_max'] = $feeMax;

$whereClause = implode(' AND ', $whereConditions);

// Count total for pagination
$countQuery = "SELECT COUNT(*) as total FROM events e WHERE $whereClause";
$countStmt = $db->prepare($countQuery);
$countStmt->execute($params);
$totalEvents = $countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$totalPages = ceil($totalEvents / $perPage);

// Fetch event data
$query = "
    SELECT 
        e.*,
        u.full_name AS organizer_name,
        (e.max_capacity - e.current_bookings) AS available_slots
    FROM events e
    LEFT JOIN users u ON e.organizer_id = u.user_id
    WHERE $whereClause
    ORDER BY e.event_date ASC, e.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $db->prepare($query);

// Bind params safely
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique locations
$locationsQuery = "SELECT DISTINCT location FROM events WHERE event_status = 'upcoming' ORDER BY location";
$locationsStmt = $db->query($locationsQuery);
$locations = $locationsStmt->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Browse Events';

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.compact-event-card {
    transition: all 0.2s ease;
    border-left: 4px solid transparent;
}
.compact-event-card:hover {
    border-left-color: #4f46e5;
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.15);
}
.location-link {
    transition: all 0.2s ease;
}
.location-link:hover {
    color: #4f46e5;
    text-decoration: underline;
}
.badge-compact {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-weight: 600;
}
.event-image-compact {
    width: 80px;
    height: 80px;
    border-radius: 0.5rem;
    object-fit: cover;
}
</style>

<!-- Hero Section -->
<div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white py-8">
    <div class="container mx-auto px-4">
        <h1 class="text-3xl font-bold mb-2">Chess Tournaments</h1>
        <p class="text-lg opacity-90">Find and book your next chess tournament</p>
    </div>
</div>

<!-- Search & Filters -->
<div class="bg-white shadow-sm border-b sticky top-0 z-10">
    <div class="container mx-auto px-4 py-4">
        <form method="GET" action="<?php echo BASE_URL; ?>/browse-events" class="space-y-4">
            <!-- Search -->
            <div class="flex gap-2">
                <input 
                    type="text" 
                    name="search" 
                    placeholder="Search events..." 
                    value="<?= htmlspecialchars($search) ?>"
                    class="flex-1 px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                />
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium transition">
                    <i class="fas fa-search mr-2"></i>Search
                </button>
            </div>

            <!-- Advanced Filters -->
            <div x-data="{ showFilters: false }">
                <button type="button" @click="showFilters = !showFilters"
                    class="text-indigo-600 hover:text-indigo-700 font-medium flex items-center gap-2">
                    <i class="fas fa-filter"></i> Advanced Filters
                </button>

                <div x-show="showFilters" x-collapse class="mt-4 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
                    <!-- Location -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                        <select name="location" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                            <option value="">All Locations</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= htmlspecialchars($loc) ?>" <?= $location === $loc ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($loc) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Dates -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" 
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" 
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500" />
                    </div>

                    <!-- Fees -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Min Fee ($)</label>
                        <input type="number" step="0.01" name="fee_min" value="<?= $feeMin ?>" 
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Max Fee ($)</label>
                        <input type="number" step="0.01" name="fee_max" 
                               value="<?= $feeMax == 999999 ? '' : $feeMax ?>" 
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500" />
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Events List -->
<div class="container mx-auto px-4 py-6">
    <div class="mb-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <p class="text-gray-600 text-sm">
            <i class="fas fa-info-circle text-indigo-500 mr-1"></i>
            Showing <span class="font-semibold text-gray-800"><?= count($events) ?></span> of 
            <span class="font-semibold text-gray-800"><?= $totalEvents ?></span> tournaments
        </p>
        <div class="flex gap-2">
            <a href="<?php echo BASE_URL; ?>/browse-events?status=upcoming" 
               class="px-4 py-2 rounded-lg <?= $status === 'upcoming' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' ?>">
                Upcoming
            </a>
            <a href="<?php echo BASE_URL; ?>/browse-events?status=completed" 
               class="px-4 py-2 rounded-lg <?= $status === 'completed' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' ?>">
                Past
            </a>
        </div>
    </div>

    <?php if (empty($events)): ?>
        <div class="text-center py-16 bg-white rounded-lg shadow-sm">
            <i class="fas fa-calendar-times text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">No Events Found</h3>
            <p class="text-gray-500 mb-4">Try adjusting your search filters</p>
            <a href="<?php echo BASE_URL; ?>/browse-events" 
               class="inline-flex items-center text-indigo-600 hover:text-indigo-700 font-medium">
                <i class="fas fa-redo mr-2"></i>Clear All Filters
            </a>
        </div>
    <?php else: ?>
        <!-- Compact Card Layout -->
        <div class="space-y-3">
            <?php foreach ($events as $event): ?>
                <div class="compact-event-card bg-white rounded-lg shadow-sm hover:shadow-md p-4 border border-gray-100">
                    <div class="flex flex-col md:flex-row gap-4">
                        <!-- Image Section -->
                        <div class="flex-shrink-0">
                            <?php if (!empty($event['event_image'])): ?>
                                <img src="<?php echo UPLOADS_URL; ?>/events/<?= htmlspecialchars($event['event_image']) ?>" 
                                     alt="<?= htmlspecialchars($event['event_name']) ?>" 
                                     class="event-image-compact" />
                            <?php else: ?>
                                <div class="event-image-compact bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center">
                                    <i class="fas fa-chess text-white text-3xl"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Main Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-lg font-bold text-gray-800 mb-1 truncate">
                                        <?= htmlspecialchars($event['event_name']) ?>
                                        <?php if ($event['featured']): ?>
                                            <span class="inline-block ml-2 text-yellow-500 text-sm">
                                                <i class="fas fa-star"></i>
                                            </span>
                                        <?php endif; ?>
                                    </h3>
                                    <p class="text-gray-600 text-sm line-clamp-1">
                                        <?= htmlspecialchars(substr($event['description'], 0, 120)) ?>
                                    </p>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <div class="text-2xl font-bold text-indigo-600">
                                        $<?= number_format($event['entry_fee'], 2) ?>
                                    </div>
                                    <div class="text-xs text-gray-500">Entry Fee</div>
                                </div>
                            </div>

                            <!-- Event Details Grid -->
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-3 text-sm">
                                <div class="flex items-center text-gray-700">
                                    <i class="fas fa-map-marker-alt text-indigo-500 w-4 mr-2"></i>
                                    <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($event['location']) ?>" 
                                       target="_blank" 
                                       rel="noopener noreferrer"
                                       class="location-link truncate flex-1 hover:text-indigo-600"
                                       title="Open in Google Maps">
                                        <?= htmlspecialchars($event['location']) ?>
                                    </a>
                                </div>
                                <div class="flex items-center text-gray-700">
                                    <i class="fas fa-calendar text-indigo-500 w-4 mr-2"></i>
                                    <span class="truncate"><?= date('M d, Y', strtotime($event['event_date'])) ?></span>
                                </div>
                                <div class="flex items-center text-gray-700">
                                    <i class="fas fa-clock text-indigo-500 w-4 mr-2"></i>
                                    <span class="truncate"><?= date('h:i A', strtotime($event['event_time'])) ?></span>
                                </div>
                                <div class="flex items-center text-gray-700">
                                    <i class="fas fa-user text-indigo-500 w-4 mr-2"></i>
                                    <span class="truncate" title="<?= htmlspecialchars($event['organizer_name']) ?>">
                                        <?= htmlspecialchars($event['organizer_name']) ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="badge-compact <?= $event['available_slots'] > 10 ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700' ?>">
                                        <i class="fas fa-users text-xs mr-1"></i>
                                        <?= $event['available_slots'] ?> / <?= $event['max_capacity'] ?> slots
                                    </span>
                                    <?php if ($event['featured']): ?>
                                        <span class="badge-compact bg-yellow-100 text-yellow-700">
                                            <i class="fas fa-star text-xs mr-1"></i>Featured
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <a href="<?php echo BASE_URL; ?>/event-details?id=<?= $event['event_id'] ?>" 
                                   class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm font-medium">
                                    View Details
                                    <i class="fas fa-arrow-right ml-2 text-xs"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="mt-8 flex justify-center gap-2">
                <?php if ($page > 1): ?>
                    <a href="<?php echo BASE_URL; ?>/browse-events?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                       class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="<?php echo BASE_URL; ?>/browse-events?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                       class="px-4 py-2 rounded-lg transition <?= $i === $page ? 'bg-indigo-600 text-white font-semibold' : 'bg-white border border-gray-300 hover:bg-gray-50' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="<?php echo BASE_URL; ?>/browse-events?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                       class="px-4 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>