<?php
// modules/admin/audit-logs.php
require_once '../../config/database.php';
require_once '../../core/Auth.php';

$auth = new Auth($pdo);
$auth->requireLogin();
$auth->requireRole(['admin']);

// Filters
$admin_filter = isset($_GET['admin']) ? (int)$_GET['admin'] : 0;
$action_filter = isset($_GET['action']) ? $_GET['action'] : '';
$entity_filter = isset($_GET['entity']) ? $_GET['entity'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = [];
$params = [];

if ($admin_filter) {
    $where_conditions[] = "al.admin_id = ?";
    $params[] = $admin_filter;
}

if ($action_filter) {
    $where_conditions[] = "al.action LIKE ?";
    $params[] = "%$action_filter%";
}

if ($entity_filter) {
    $where_conditions[] = "al.entity_type = ?";
    $params[] = $entity_filter;
}

if ($date_filter) {
    $where_conditions[] = "DATE(al.created_at) = ?";
    $params[] = $date_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM audit_logs al $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_logs = $count_stmt->fetch()['total'];
$total_pages = ceil($total_logs / $per_page);

// Get logs
$sql = "SELECT al.*, u.full_name as admin_name, u.email as admin_email
        FROM audit_logs al
        JOIN users u ON al.admin_id = u.user_id
        $where_clause
        ORDER BY al.created_at DESC
        LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get admins for filter
$admins_sql = "SELECT DISTINCT u.user_id, u.full_name FROM users u 
               JOIN audit_logs al ON u.user_id = al.admin_id 
               WHERE u.user_type = 'admin' ORDER BY u.full_name";
$admins = $pdo->query($admins_sql)->fetchAll();

// Get unique actions
$actions_sql = "SELECT DISTINCT action FROM audit_logs ORDER BY action";
$actions = $pdo->query($actions_sql)->fetchAll();

include '../../includes/header.php';
include '../../includes/nav.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Audit Logs</h1>
            <p class="text-gray-600 mt-1">Track all administrative actions on the platform</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Admin</label>
                    <select name="admin" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">All Admins</option>
                        <?php foreach ($admins as $admin): ?>
                        <option value="<?php echo $admin['user_id']; ?>" <?php echo $admin_filter === $admin['user_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($admin['full_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Action</label>
                    <select name="action" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">All Actions</option>
                        <?php foreach ($actions as $action): ?>
                        <option value="<?php echo $action['action']; ?>" <?php echo $action_filter === $action['action'] ? 'selected' : ''; ?>>
                            <?php echo ucwords(str_replace('_', ' ', $action['action'])); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Entity Type</label>
                    <select name="entity" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">All Types</option>
                        <option value="user" <?php echo $entity_filter === 'user' ? 'selected' : ''; ?>>User</option>
                        <option value="event" <?php echo $entity_filter === 'event' ? 'selected' : ''; ?>>Event</option>
                        <option value="booking" <?php echo $entity_filter === 'booking' ? 'selected' : ''; ?>>Booking</option>
                        <option value="payment" <?php echo $entity_filter === 'payment' ? 'selected' : ''; ?>>Payment</option>
                        <option value="settings" <?php echo $entity_filter === 'settings' ? 'selected' : ''; ?>>Settings</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                    <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition">
                        Filter
                    </button>
                    <a href="audit-logs.php" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-900">Activity Logs (<?php echo number_format($total_logs); ?>)</h2>
                    <a href="../../api/exports/audit-logs.php?<?php echo http_build_query($_GET); ?>" 
                       class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                        Export CSV
                    </a>
                </div>
            </div>

            <?php if (empty($logs)): ?>
            <div class="p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No logs found</h3>
                <p class="mt-1 text-sm text-gray-500">Try adjusting your filters</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Timestamp</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Admin</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($logs as $log): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($log['created_at'])); ?></div>
                                <div class="text-xs text-gray-500"><?php echo date('g:i A', strtotime($log['created_at'])); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($log['admin_name']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($log['admin_email']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $action_colors = [
                                    'create' => 'bg-green-100 text-green-800',
                                    'update' => 'bg-blue-100 text-blue-800',
                                    'delete' => 'bg-red-100 text-red-800',
                                    'suspend' => 'bg-yellow-100 text-yellow-800',
                                    'activate' => 'bg-green-100 text-green-800'
                                ];
                                
                                $color = 'bg-gray-100 text-gray-800';
                                foreach ($action_colors as $key => $val) {
                                    if (strpos($log['action'], $key) !== false) {
                                        $color = $val;
                                        break;
                                    }
                                }
                                ?>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $color; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $log['action'])); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 capitalize"><?php echo htmlspecialchars($log['entity_type']); ?></div>
                                <?php if ($log['entity_id']): ?>
                                <div class="text-xs text-gray-500">ID: <?php echo $log['entity_id']; ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($log['old_values'] || $log['new_values']): ?>
                                <button onclick="showDetails(<?php echo $log['log_id']; ?>)" 
                                        class="text-sm text-blue-600 hover:text-blue-800">
                                    View Details
                                </button>
                                <?php else: ?>
                                <span class="text-sm text-gray-400">No details</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-600"><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></div>
                                <?php if ($log['user_agent']): ?>
                                <div class="text-xs text-gray-400" title="<?php echo htmlspecialchars($log['user_agent']); ?>">
                                    <?php echo substr($log['user_agent'], 0, 30); ?>...
                                </div>
                                <?php endif; ?>
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
                        <a href="?page=<?php echo $page - 1; ?>&admin=<?php echo $admin_filter; ?>&action=<?php echo urlencode($action_filter); ?>&entity=<?php echo $entity_filter; ?>&date=<?php echo $date_filter; ?>" 
                           class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&admin=<?php echo $admin_filter; ?>&action=<?php echo urlencode($action_filter); ?>&entity=<?php echo $entity_filter; ?>&date=<?php echo $date_filter; ?>" 
                           class="px-3 py-1 border rounded <?php echo $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&admin=<?php echo $admin_filter; ?>&action=<?php echo urlencode($action_filter); ?>&entity=<?php echo $entity_filter; ?>&date=<?php echo $date_filter; ?>" 
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

<script>
function showDetails(logId) {
    alert('Details modal would show old/new values for log ID: ' + logId + '\n\nImplement modal to display JSON formatted old_values and new_values fields.');
}
</script>

<?php include '../../includes/footer.php'; ?>