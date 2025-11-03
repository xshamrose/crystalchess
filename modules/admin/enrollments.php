<?php

/**
 * Admin Enrollments Management Page
 */

require_once __DIR__ . '/../../config/config.php';
require_once ROOT_PATH . '/core/Database.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin access
requireRole('admin');

$pageTitle = 'Enrollments Management';
$db = Database::getInstance();

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "enrollment_status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(student_name LIKE :search OR student_email LIKE :search OR class_name LIKE :search)";
    $params[':search'] = "%{$search}%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM enrollments {$where_clause}";
$stmt = $db->query($count_sql);
foreach ($params as $key => $value) {
    $stmt->bind($key, $value);
}
$total_enrollments = $stmt->fetch()['total'];
$total_pages = ceil($total_enrollments / $per_page);

// Get enrollments
$sql = "SELECT * FROM enrollments {$where_clause} ORDER BY enrollment_date DESC LIMIT {$per_page} OFFSET {$offset}";
$stmt = $db->query($sql);
foreach ($params as $key => $value) {
    $stmt->bind($key, $value);
}
$enrollments = $stmt->fetchAll();

// Get statistics
$stats = [
    'total' => $db->count('enrollments'),
    'pending' => $db->count('enrollments', ['enrollment_status' => 'pending']),
    'approved' => $db->count('enrollments', ['enrollment_status' => 'approved']),
    'rejected' => $db->count('enrollments', ['enrollment_status' => 'rejected'])
];

include INCLUDES_PATH . '/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="container mx-auto px-4">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Enrollments Management</h1>
            <p class="text-gray-600">Manage all class enrollment applications</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Enrollments</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $stats['total']; ?></p>
                    </div>
                    <div class="bg-indigo-100 rounded-full p-3">
                        <i class="fas fa-users text-indigo-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Pending</p>
                        <p class="text-3xl font-bold text-yellow-600"><?php echo $stats['pending']; ?></p>
                    </div>
                    <div class="bg-yellow-100 rounded-full p-3">
                        <i class="fas fa-clock text-yellow-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Approved</p>
                        <p class="text-3xl font-bold text-green-600"><?php echo $stats['approved']; ?></p>
                    </div>
                    <div class="bg-green-100 rounded-full p-3">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Rejected</p>
                        <p class="text-3xl font-bold text-red-600"><?php echo $stats['rejected']; ?></p>
                    </div>
                    <div class="bg-red-100 rounded-full p-3">
                        <i class="fas fa-times-circle text-red-600 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <form method="GET" class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-[200px]">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Search by name, email, class..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                </div>

                <div class="min-w-[150px]">
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>

                <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                    <i class="fas fa-filter mr-2"></i>Filter
                </button>

                <a href="<?php echo BASE_URL; ?>/admin-enrollments" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                    <i class="fas fa-redo mr-2"></i>Reset
                </a>
            </form>
        </div>

        <!-- Enrollments Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Class</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($enrollments)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-inbox text-4xl mb-2 text-gray-300"></i>
                                    <p>No enrollments found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($enrollments as $enrollment): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                        #<?php echo $enrollment['enrollment_id']; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($enrollment['student_name']); ?></div>
                                        <div class="text-sm text-gray-500">Age: <?php echo $enrollment['student_age'] ?? 'N/A'; ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($enrollment['class_name']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo ucfirst($enrollment['preferred_schedule'] ?? 'N/A'); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($enrollment['student_email']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($enrollment['student_phone']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <?php echo formatDate($enrollment['enrollment_date']); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $statusClasses = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'approved' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800',
                                            'cancelled' => 'bg-gray-100 text-gray-800'
                                        ];
                                        $statusClass = $statusClasses[$enrollment['enrollment_status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($enrollment['enrollment_status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <button onclick="viewEnrollment(<?php echo $enrollment['enrollment_id']; ?>)"
                                            class="text-indigo-600 hover:text-indigo-900 mr-3">
                                            <i class="fas fa-eye"></i> View
                                        </button>

                                        <?php if ($enrollment['enrollment_status'] === 'pending'): ?>
                                            <button onclick="updateStatus(<?php echo $enrollment['enrollment_id']; ?>, 'approved')"
                                                class="text-green-600 hover:text-green-900 mr-3">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button onclick="updateStatus(<?php echo $enrollment['enrollment_id']; ?>, 'rejected')"
                                                class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-t">
                    <div class="text-sm text-gray-700">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_enrollments); ?> of <?php echo $total_enrollments; ?> results
                    </div>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>"
                                class="px-4 py-2 bg-white border rounded-lg hover:bg-gray-50">Previous</a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>"
                                class="px-4 py-2 border rounded-lg <?php echo $i === $page ? 'bg-indigo-600 text-white' : 'bg-white hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>"
                                class="px-4 py-2 bg-white border rounded-lg hover:bg-gray-50">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- View Enrollment Modal -->
<div id="viewModal" class="modal-overlay" style="display:none;">
    <div class="bg-white rounded-lg max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b flex justify-between items-center">
            <h3 class="text-xl font-bold">Enrollment Details</h3>
            <button onclick="closeViewModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="modalContent" class="p-6">
            <!-- Content loaded via JavaScript -->
        </div>
    </div>
</div>

<script>
    function viewEnrollment(enrollmentId) {
        // Fetch enrollment details via AJAX
        fetch(`<?php echo BASE_URL; ?>/api/enrollments/view.php?id=${enrollmentId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const e = data.enrollment;
                    document.getElementById('modalContent').innerHTML = `
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500">Enrollment ID</p>
                                <p class="font-semibold">#${e.enrollment_id}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Status</p>
                                <p class="font-semibold">${e.enrollment_status.toUpperCase()}</p>
                            </div>
                        </div>
                        
                        <div class="border-t pt-4">
                            <h4 class="font-bold mb-2">Student Information</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div><p class="text-sm text-gray-500">Name</p><p>${e.student_name}</p></div>
                                <div><p class="text-sm text-gray-500">Age</p><p>${e.student_age || 'N/A'}</p></div>
                                <div><p class="text-sm text-gray-500">Email</p><p>${e.student_email}</p></div>
                                <div><p class="text-sm text-gray-500">Phone</p><p>${e.student_phone}</p></div>
                            </div>
                        </div>
                        
                        ${e.parent_name ? `
                        <div class="border-t pt-4">
                            <h4 class="font-bold mb-2">Parent Information</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div><p class="text-sm text-gray-500">Name</p><p>${e.parent_name}</p></div>
                                <div><p class="text-sm text-gray-500">Phone</p><p>${e.parent_phone || 'N/A'}</p></div>
                                <div><p class="text-sm text-gray-500">Email</p><p>${e.parent_email || 'N/A'}</p></div>
                            </div>
                        </div>
                        ` : ''}
                        
                        <div class="border-t pt-4">
                            <h4 class="font-bold mb-2">Class Details</h4>
                            <div class="grid grid-cols-2 gap-4">
                                <div><p class="text-sm text-gray-500">Class</p><p>${e.class_name}</p></div>
                                <div><p class="text-sm text-gray-500">Preferred Schedule</p><p>${e.preferred_schedule || 'N/A'}</p></div>
                                <div><p class="text-sm text-gray-500">Preferred Days</p><p>${e.preferred_days || 'N/A'}</p></div>
                                <div><p class="text-sm text-gray-500">Experience Level</p><p>${e.previous_experience}</p></div>
                            </div>
                        </div>
                        
                        ${e.message ? `
                        <div class="border-t pt-4">
                            <h4 class="font-bold mb-2">Additional Message</h4>
                            <p class="text-gray-700">${e.message}</p>
                        </div>
                        ` : ''}
                        
                        ${e.address ? `
                        <div class="border-t pt-4">
                            <h4 class="font-bold mb-2">Address</h4>
                            <p class="text-gray-700">${e.address}, ${e.city}, ${e.state} - ${e.pincode}</p>
                        </div>
                        ` : ''}
                    </div>
                `;
                    document.getElementById('viewModal').style.display = 'flex';
                }
            });
    }

    function closeViewModal() {
        document.getElementById('viewModal').style.display = 'none';
    }

    function updateStatus(enrollmentId, status) {
        let notes = '';
        let rejection_reason = '';

        if (status === 'rejected') {
            rejection_reason = prompt('Enter rejection reason (optional):');
        }

        if (!confirm(`Are you sure you want to ${status} this enrollment?`)) {
            return;
        }

        const formData = new FormData();
        formData.append('enrollment_id', enrollmentId);
        formData.append('status', status);
        formData.append('notes', notes);
        formData.append('rejection_reason', rejection_reason);

        fetch('<?php echo BASE_URL; ?>/api/enrollments/update-status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('An error occurred: ' + error.message);
            });
    }

    // Close modal when clicking outside
    document.getElementById('viewModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeViewModal();
        }
    });
</script>

<style>
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 50;
    }
</style>

<?php
include INCLUDES_PATH . '/footer.php';
?>