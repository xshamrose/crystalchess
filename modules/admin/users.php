<?php
// modules/admin/users.php
require_once './config/database.php';
require_once './core/Auth.php';

$auth = new Auth($pdo);
$auth->requireLogin();
$auth->requireRole(['admin']);

$admin_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle user actions (suspend, activate, delete, change role)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $user_id = (int)$_POST['user_id'];
        
        // Prevent admin from modifying themselves
        if ($user_id === $admin_id) {
            $error = 'You cannot modify your own account.';
        } else {
            try {
                switch ($_POST['action']) {
                    case 'suspend':
                        $stmt = $pdo->prepare("UPDATE users SET user_status = 'suspended' WHERE user_id = ?");
                        $stmt->execute([$user_id]);
                        $message = 'User suspended successfully.';
                        
                        // Log action
                        $log_stmt = $pdo->prepare("INSERT INTO audit_logs (admin_id, action, entity_type, entity_id) VALUES (?, 'suspend_user', 'user', ?)");
                        $log_stmt->execute([$admin_id, $user_id]);
                        break;
                        
                    case 'activate':
                        $stmt = $pdo->prepare("UPDATE users SET user_status = 'active' WHERE user_id = ?");
                        $stmt->execute([$user_id]);
                        $message = 'User activated successfully.';
                        
                        $log_stmt = $pdo->prepare("INSERT INTO audit_logs (admin_id, action, entity_type, entity_id) VALUES (?, 'activate_user', 'user', ?)");
                        $log_stmt->execute([$admin_id, $user_id]);
                        break;
                        
                    case 'delete':
                        // Check for bookings
                        $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bookings WHERE user_id = ?");
                        $check_stmt->execute([$user_id]);
                        $booking_count = $check_stmt->fetch()['count'];
                        
                        if ($booking_count > 0) {
                            $error = 'Cannot delete user with existing bookings. Suspend instead.';
                        } else {
                            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                            $stmt->execute([$user_id]);
                            $message = 'User deleted successfully.';
                            
                            $log_stmt = $pdo->prepare("INSERT INTO audit_logs (admin_id, action, entity_type, entity_id) VALUES (?, 'delete_user', 'user', ?)");
                            $log_stmt->execute([$admin_id, $user_id]);
                        }
                        break;
                        
                    case 'change_role':
                        $new_role = $_POST['new_role'];
                        if (in_array($new_role, ['player', 'organizer', 'admin'])) {
                            $stmt = $pdo->prepare("UPDATE users SET user_type = ? WHERE user_id = ?");
                            $stmt->execute([$new_role, $user_id]);
                            $message = 'User role updated successfully.';
                            
                            $log_stmt = $pdo->prepare("INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, new_values) VALUES (?, 'change_role', 'user', ?, ?)");
                            $log_stmt->execute([$admin_id, $user_id, $new_role]);
                        }
                        break;
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['user_status']) ? $_GET['user_status'] : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(full_name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role_filter) {
    $where_conditions[] = "user_type = ?";
    $params[] = $role_filter;
}

if ($status_filter) {
    $where_conditions[] = "user_status = ?";
    $params[] = $status_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM users $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_users = $count_stmt->fetch()['total'];
$total_pages = ceil($total_users / $per_page);

// Get users
$sql = "SELECT u.user_id, u.full_name, u.email, u.phone, u.user_type, u.user_status, u.created_at, u.last_login,
        COUNT(DISTINCT b.booking_id) as booking_count,
        COUNT(DISTINCT e.event_id) as event_count
        FROM users u
        LEFT JOIN bookings b ON u.user_id = b.user_id
        LEFT JOIN events e ON u.user_id = e.organizer_id
        $where_clause
        GROUP BY u.user_id
        ORDER BY u.created_at DESC
        LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

include './includes/header.php';
#include './includes/nav.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">User Management</h1>
            <p class="text-gray-600 mt-1">Manage all platform users</p>
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
                           placeholder="Name or email..." 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                    <select name="role" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">All Roles</option>
                        <option value="player" <?php echo $role_filter === 'player' ? 'selected' : ''; ?>>Players</option>
                        <option value="organizer" <?php echo $role_filter === 'organizer' ? 'selected' : ''; ?>>Organizers</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admins</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="user_status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">All </option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-medium transition">
                        Filter
                    </button>
                    <a href="?" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-900">Users (<?php echo number_format($total_users); ?>)</h2>
                </div>
            </div>

            <?php if (empty($users)): ?>
            <div class="p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No users found</h3>
                <p class="mt-1 text-sm text-gray-500">Try adjusting your filters</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Activity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Joined</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($users as $user): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="h-10 w-10 flex-shrink-0">
                                        <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                            <span class="text-blue-600 font-semibold text-sm">
                                                <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                        <?php if ($user['user_id'] === $admin_id): ?>
                                        <div class="text-xs text-blue-600 font-medium">You</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></div>
                                <?php if ($user['phone']): ?>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user['phone']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <form method="POST" class="inline" onchange="if(confirm('Change user role?')) this.submit();">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <input type="hidden" name="action" value="change_role">
                                    <select name="new_role" class="text-sm border border-gray-300 rounded px-2 py-1 <?php echo $user['user_id'] === $admin_id ? 'bg-gray-100 cursor-not-allowed' : ''; ?>" 
                                            <?php echo $user['user_id'] === $admin_id ? 'disabled' : ''; ?>>
                                        <option value="player" <?php echo $user['user_type'] === 'player' ? 'selected' : ''; ?>>Player</option>
                                        <option value="organizer" <?php echo $user['user_type'] === 'organizer' ? 'selected' : ''; ?>>Organizer</option>
                                        <option value="admin" <?php echo $user['user_type'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                </form>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?php if ($user['user_type'] === 'organizer'): ?>
                                        <?php echo $user['event_count']; ?> events
                                    <?php else: ?>
                                        <?php echo $user['booking_count']; ?> bookings
                                    <?php endif; ?>
                                </div>
                                <?php if ($user['last_login']): ?>
                                <div class="text-xs text-gray-500">Last: <?php echo date('M d, Y', strtotime($user['last_login'])); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $status_colors = [
                                    'active' => 'bg-green-100 text-green-800',
                                    'inactive' => 'bg-gray-100 text-gray-800',
                                    'suspended' => 'bg-red-100 text-red-800'
                                ];
                                $color = $status_colors[$user['user_status']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $color; ?>">
                                    <?php echo ucfirst($user['user_status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?php if ($user['user_id'] !== $admin_id): ?>
                                <div class="flex items-center gap-2">
                                    <?php if ($user['user_status'] === 'active'): ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Suspend this user?');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <input type="hidden" name="action" value="suspend">
                                        <button type="submit" class="text-yellow-600 hover:text-yellow-800">Suspend</button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <input type="hidden" name="action" value="activate">
                                        <button type="submit" class="text-green-600 hover:text-green-800">Activate</button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" class="inline" onsubmit="return confirm('Permanently delete this user? This cannot be undone.');">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="text-red-600 hover:text-red-800">Delete</button>
                                    </form>
                                </div>
                                <?php else: ?>
                                <span class="text-gray-400 text-xs">Current User</span>
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
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&user_status=<?php echo $status_filter; ?>" 
                           class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&user_status=<?php echo $status_filter; ?>" 
                           class="px-3 py-1 border rounded <?php echo $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&user_status=<?php echo $status_filter; ?>" 
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

<?php include './includes/footer.php'; ?>