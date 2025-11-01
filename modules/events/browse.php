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
$perPage = 9;
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

<!-- Hero Section -->
<div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white py-12">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl font-bold mb-2">Chess Tournaments</h1>
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
                    class="flex-1 px-4 py-3 border rounded-lg focus:ring-2 focus:ring-blue-500"
                />
                <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                    Search
                </button>
            </div>

            <!-- Advanced Filters -->
            <div x-data="{ showFilters: false }">
                <button type="button" @click="showFilters = !showFilters"
                    class="text-blue-600 hover:text-blue-700 font-medium flex items-center gap-2">
                    <i class="fas fa-filter"></i> Filters
                </button>

                <div x-show="showFilters" x-collapse class="mt-4 grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
                    <!-- Location -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                        <select name="location" class="w-full px-3 py-2 border rounded-lg">
                            <option value="">All</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= htmlspecialchars($loc) ?>" <?= $location === $loc ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($loc) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Dates -->
                    <div>
                        <label class="block text-sm font-medium mb-1">From</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="w-full px-3 py-2 border rounded-lg" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">To</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="w-full px-3 py-2 border rounded-lg" />
                    </div>

                    <!-- Fees -->
                    <div>
                        <label class="block text-sm font-medium mb-1">Min Fee</label>
                        <input type="number" step="0.01" name="fee_min" value="<?= $feeMin ?>" class="w-full px-3 py-2 border rounded-lg" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Max Fee</label>
                        <input type="number" step="0.01" name="fee_max" value="<?= $feeMax == 999999 ? '' : $feeMax ?>" class="w-full px-3 py-2 border rounded-lg" />
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Events Grid -->
<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex justify-between items-center">
        <p class="text-gray-600">Showing <?= count($events) ?> of <?= $totalEvents ?> events</p>
        <div class="flex gap-2">
            <a href="<?php echo BASE_URL; ?>/browse-events?event_status=upcoming" 
               class="px-4 py-2 rounded-lg <?= $status === 'upcoming' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' ?>">
                Upcoming
            </a>
            <a href="<?php echo BASE_URL; ?>/browse-events?event_status=completed" 
               class="px-4 py-2 rounded-lg <?= $status === 'completed' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' ?>">
                Past
            </a>
        </div>
    </div>

    <?php if (empty($events)): ?>
        <div class="text-center py-16">
            <i class="fas fa-calendar-times text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">No Events Found</h3>
            <p class="text-gray-500 mb-4">Try adjusting your filters</p>
            <a href="<?php echo BASE_URL; ?>/browse-events" class="text-blue-600 hover:text-blue-700 font-medium">
                <i class="fas fa-redo mr-2"></i>Clear Filters
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($events as $event): ?>
                <div class="bg-white rounded-lg shadow-md hover:shadow-xl transition-shadow overflow-hidden">
                    <div class="relative h-48 bg-gradient-to-br from-blue-400 to-purple-500">
                        <?php if (!empty($event['event_image'])): ?>
                            <img src="<?php echo UPLOADS_URL; ?>/events/<?= htmlspecialchars($event['event_image']) ?>" 
                                 alt="<?= htmlspecialchars($event['event_name']) ?>" 
                                 class="w-full h-full object-cover" />
                        <?php else: ?>
                            <div class="flex items-center justify-center h-full">
                                <i class="fas fa-chess text-white text-6xl opacity-50"></i>
                            </div>
                        <?php endif; ?>
                        <?php if ($event['featured']): ?>
                            <span class="absolute top-2 right-2 bg-yellow-400 text-yellow-900 px-3 py-1 rounded-full text-xs font-bold">
                                ⭐ Featured
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="p-5">
                        <h3 class="text-xl font-bold mb-2 text-gray-800">
                            <?= htmlspecialchars($event['event_name']) ?>
                        </h3>
                        <p class="text-gray-600 text-sm mb-3 line-clamp-2">
                            <?= htmlspecialchars(substr($event['description'], 0, 100)) ?>...
                        </p>
                        
                        <div class="space-y-2 mb-4 text-sm">
                            <p class="flex items-center text-gray-700">
                                <i class="fas fa-map-marker-alt text-blue-500 w-5"></i>
                                <span class="ml-2"><?= htmlspecialchars($event['location']) ?></span>
                            </p>
                            <p class="flex items-center text-gray-700">
                                <i class="fas fa-calendar text-blue-500 w-5"></i>
                                <span class="ml-2"><?= date('M d, Y', strtotime($event['event_date'])) ?></span>
                            </p>
                            <p class="flex items-center text-gray-700">
                                <i class="fas fa-clock text-blue-500 w-5"></i>
                                <span class="ml-2"><?= date('h:i A', strtotime($event['event_time'])) ?></span>
                            </p>
                            <p class="flex items-center text-gray-700">
                                <i class="fas fa-users text-blue-500 w-5"></i>
                                <span class="ml-2"><?= $event['available_slots'] ?> / <?= $event['max_capacity'] ?> slots left</span>
                            </p>
                        </div>

                        <div class="flex justify-between items-center border-t pt-3">
                            <span class="text-2xl font-bold text-blue-600">
                                $<?= number_format($event['entry_fee'], 2) ?>
                            </span>
                            <a href="<?php echo BASE_URL; ?>/event-details?id=<?= $event['event_id'] ?>" 
                               class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                View Details
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="mt-8 flex justify-center gap-2">
                <?php if ($page > 1): ?>
                    <a href="<?php echo BASE_URL; ?>/browse-events?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                       class="px-4 py-2 bg-white border rounded-lg hover:bg-gray-50">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="<?php echo BASE_URL; ?>/browse-events?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                       class="px-4 py-2 rounded-lg <?= $i === $page ? 'bg-blue-600 text-white' : 'bg-white border hover:bg-gray-50' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="<?php echo BASE_URL; ?>/browse-events?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                       class="px-4 py-2 bg-white border rounded-lg hover:bg-gray-50">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>