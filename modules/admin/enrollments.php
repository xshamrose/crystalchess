<?php

/**
 * Admin Enrollments Management
 * File: modules/admin/enrollments.php
 */

require_once __DIR__ . '/../../config/config.php';

// Check authentication and admin access
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ' . BASE_URL . '/login');
    exit;
}

$pageTitle = 'Manage Enrollments - Admin';
include INCLUDES_PATH . '/header.php';

$db = Database::getInstance();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status' && isset($_POST['enrollment_id'])) {
        $enrollmentId = (int)$_POST['enrollment_id'];
        $newStatus = $_POST['status'];
        $notes = $_POST['notes'] ?? null;

        $updateData = [
            'enrollment_status' => $newStatus,
            'notes' => $notes
        ];

        if ($newStatus === 'approved') {
            $updateData['approved_date'] = date('Y-m-d H:i:s');
            $updateData['approved_by'] = $_SESSION['user_id'];
        } elseif ($newStatus === 'rejected') {
            $updateData['rejection_reason'] = $notes;
        }

        $db->update('enrollments', $updateData, ['enrollment_id' => $enrollmentId]);

        $_SESSION['success_message'] = 'Enrollment status updated successfully!';
        header('Location: ' . BASE_URL . '/admin-enrollments');
        exit;
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$classFilter = $_GET['class'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Build query
$sql = "SELECT e.*, u.full_name as user_name, a.full_name as approved_by_name
        FROM enrollments e
        LEFT JOIN users u ON e.user_id = u.user_id
        LEFT JOIN users a ON e.approved_by = a.user_id
        WHERE 1=1";

$params = [];

if ($statusFilter !== 'all') {
    $sql .= " AND e.enrollment_status = ?";
    $params[] = $statusFilter;
}

if ($classFilter !== 'all') {
    $sql .= " AND e.class_name = ?";
    $params[] = $classFilter;
}

if (!empty($searchQuery)) {
    $sql .= " AND (e.student_name LIKE ? OR e.student_email LIKE ? OR e.student_phone LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$sql .= " ORDER BY e.enrollment_date DESC";

$enrollments = $db->query($sql, $params)->fetchAll();

// Get all unique class names for filter
$classes = $db->query("SELECT DISTINCT class_name FROM enrollments ORDER BY class_name")->fetchAll();

// Get statistics
$stats = [
    'total' => $db->count('enrollments'),
    'pending' => $db->count('enrollments', ['enrollment_status' => 'pending']),
    'approved' => $db->count('enrollments', ['enrollment_status' => 'approved']),
    'rejected' => $db->count('enrollments', ['enrollment_status' => 'rejected'])
];
?>

<style>
    .enrollment-card {
        background: white;
        border-radius: 0.5rem;
        padding: 1.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        margin-bottom: 1rem;
        transition: all 0.2s;
    }

    .enrollment-card:hover {
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .status-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .status-pending {
        background: #fef3c7;
        color: #92400e;
    }

    .status-approved {
        background: #d1fae5;
        color: #065f46;
    }

    .status-rejected {
        background: #fee2e2;
        color: #991b1b;
    }

    .status-cancelled {
        background: #e5e7eb;
        color: #374151;
    }

    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1.5rem;
        border-radius: 0.5rem;
        text-align: center;
    }

    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal-content {
        background: white;
        padding: 2rem;
        border-radius: 0.5rem;
        max-width: 600px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }
</style>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="container mx-auto px-4">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">
                <i class="fas fa-graduation-cap text-indigo-600"></i> Manage Enrollments
            </h1>
            <p class="text-gray-600">Review and manage class enrollment requests</p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="stat-card">
                <div class="text-3xl font-bold mb-2"><?php echo $stats['total']; ?></div>
                <div class="text-indigo-100">Total Enrollments</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <div class="text-3xl font-bold mb-2"><?php echo $stats['pending']; ?></div>
                <div class="text-orange-100">Pending</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <div class="text-3xl font-bold mb-2"><?php echo $stats['approved']; ?></div>
                <div class="text-green-100">Approved</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                <div class="text-3xl font-bold mb-2"><?php echo $stats['rejected']; ?></div>
                <div class="text-red-100">Rejected</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Class</label>
                    <select name="class" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="all">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo htmlspecialchars($class['class_name']); ?>"
                                <?php echo $classFilter === $class['class_name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>"
                        placeholder="Name, email, phone..."
                        class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>

                <div class="flex items-end">
                    <button type="submit" class="w-full bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
                        <i class="fas fa-search mr-2"></i> Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Success Message -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo $_SESSION['success_message'];
                unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Enrollments List -->
        <?php if (empty($enrollments)): ?>
            <div class="text-center py-12 bg-white rounded-lg shadow-sm">
                <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg">No enrollments found</p>
            </div>
        <?php else: ?>
            <?php foreach ($enrollments as $enrollment): ?>
                <div class="enrollment-card">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h3 class="text-xl font-bold text-gray-800 mb-1">
                                <?php echo htmlspecialchars($enrollment['student_name']); ?>
                                <span class="text-sm font-normal text-gray-500">(<?php echo $enrollment['student_age']; ?> years)</span>
                            </h3>
                            <p class="text-indigo-600 font-semibold">
                                <i class="fas fa-book-open mr-1"></i> <?php echo htmlspecialchars($enrollment['class_name']); ?>
                            </p>
                        </div>
                        <span class="status-badge status-<?php echo $enrollment['enrollment_status']; ?>">
                            <?php echo ucfirst($enrollment['enrollment_status']); ?>
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4 text-sm">
                        <div>
                            <p class="text-gray-600"><i class="fas fa-envelope mr-2"></i><?php echo htmlspecialchars($enrollment['student_email']); ?></p>
                            <p class="text-gray-600"><i class="fas fa-phone mr-2"></i><?php echo htmlspecialchars($enrollment['student_phone']); ?></p>
                        </div>
                        <div>
                            <?php if ($enrollment['parent_name']): ?>
                                <p class="text-gray-600"><i class="fas fa-user mr-2"></i>Parent: <?php echo htmlspecialchars($enrollment['parent_name']); ?></p>
                                <p class="text-gray-600"><i class="fas fa-phone mr-2"></i><?php echo htmlspecialchars($enrollment['parent_phone']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <p class="text-gray-600"><i class="fas fa-clock mr-2"></i><?php echo ucfirst($enrollment['preferred_schedule']); ?></p>
                            <p class="text-gray-600"><i class="fas fa-calendar mr-2"></i>Enrolled: <?php echo date('M j, Y', strtotime($enrollment['enrollment_date'])); ?></p>
                        </div>
                    </div>

                    <?php if ($enrollment['city']): ?>
                        <p class="text-sm text-gray-600 mb-4">
                            <i class="fas fa-map-marker-alt mr-2"></i>
                            <?php echo htmlspecialchars($enrollment['city']) . ', ' . htmlspecialchars($enrollment['state']); ?>
                        </p>
                    <?php endif; ?>

                    <div class="flex gap-2 mt-4">
                        <button onclick="viewEnrollmentDetails(<?php echo $enrollment['enrollment_id']; ?>)"
                            class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm">
                            <i class="fas fa-eye mr-1"></i> View Details
                        </button>

                        <?php if ($enrollment['enrollment_status'] === 'pending'): ?>
                            <button onclick="updateEnrollmentStatus(<?php echo $enrollment['enrollment_id']; ?>, 'approved')"
                                class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition text-sm">
                                <i class="fas fa-check mr-1"></i> Approve
                            </button>
                            <button onclick="updateEnrollmentStatus(<?php echo $enrollment['enrollment_id']; ?>, 'rejected')"
                                class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition text-sm">
                                <i class="fas fa-times mr-1"></i> Reject
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Update Status Modal -->
<div id="statusModal" class="modal-overlay">
    <div class="modal-content">
        <h3 class="text-2xl font-bold text-gray-800 mb-4">Update Enrollment Status</h3>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="enrollment_id" id="modalEnrollmentId">
            <input type="hidden" name="status" id="modalStatus">

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Notes / Reason</label>
                <textarea name="notes" rows="4" class="w-full border border-gray-300 rounded-lg px-3 py-2"
                    placeholder="Add notes or rejection reason..."></textarea>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
                    Confirm
                </button>
                <button type="button" onclick="closeModal()" class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Details Modal -->
<div id="detailsModal" class="modal-overlay">
    <div class="modal-content">
        <div class="flex justify-between items-start mb-4">
            <h3 class="text-2xl font-bold text-gray-800">Enrollment Details</h3>
            <button onclick="closeDetailsModal()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>
        <div id="enrollmentDetailsContent"></div>
    </div>
</div>

<script>
    function updateEnrollmentStatus(enrollmentId, status) {
        document.getElementById('modalEnrollmentId').value = enrollmentId;
        document.getElementById('modalStatus').value = status;
        document.getElementById('statusModal').classList.add('active');
    }

    function closeModal() {
        document.getElementById('statusModal').classList.remove('active');
    }

    function viewEnrollmentDetails(enrollmentId) {
        // Find enrollment data
        const enrollments = <?php echo json_encode($enrollments); ?>;
        const enrollment = enrollments.find(e => e.enrollment_id == enrollmentId);

        if (!enrollment) return;

        let html = `
            <div class="space-y-4">
                <div class="border-b pb-3">
                    <h4 class="font-semibold text-gray-700 mb-2">Student Information</h4>
                    <p><strong>Name:</strong> ${enrollment.student_name}</p>
                    <p><strong>Age:</strong> ${enrollment.student_age} years</p>
                    <p><strong>Email:</strong> ${enrollment.student_email}</p>
                    <p><strong>Phone:</strong> ${enrollment.student_phone}</p>
                </div>
                
                <div class="border-b pb-3">
                    <h4 class="font-semibold text-gray-700 mb-2">Parent/Guardian Information</h4>
                    <p><strong>Name:</strong> ${enrollment.parent_name || 'N/A'}</p>
                    <p><strong>Phone:</strong> ${enrollment.parent_phone || 'N/A'}</p>
                    <p><strong>Email:</strong> ${enrollment.parent_email || 'N/A'}</p>
                </div>
                
                <div class="border-b pb-3">
                    <h4 class="font-semibold text-gray-700 mb-2">Address</h4>
                    <p>${enrollment.address || 'N/A'}</p>
                    <p>${enrollment.city || ''} ${enrollment.state || ''} ${enrollment.pincode || ''}</p>
                </div>
                
                <div class="border-b pb-3">
                    <h4 class="font-semibold text-gray-700 mb-2">Class Details</h4>
                    <p><strong>Class:</strong> ${enrollment.class_name}</p>
                    <p><strong>Preferred Schedule:</strong> ${enrollment.preferred_schedule}</p>
                    <p><strong>Preferred Days:</strong> ${enrollment.preferred_days || 'N/A'}</p>
                    <p><strong>Previous Experience:</strong> ${enrollment.previous_experience}</p>
                </div>
                
                ${enrollment.message ? `
                <div class="border-b pb-3">
                    <h4 class="font-semibold text-gray-700 mb-2">Message</h4>
                    <p>${enrollment.message}</p>
                </div>
                ` : ''}
                
                <div>
                    <h4 class="font-semibold text-gray-700 mb-2">Enrollment Status</h4>
                    <p><strong>Status:</strong> <span class="status-badge status-${enrollment.enrollment_status}">${enrollment.enrollment_status}</span></p>
                    <p><strong>Enrollment Date:</strong> ${new Date(enrollment.enrollment_date).toLocaleString()}</p>
                    ${enrollment.approved_date ? `<p><strong>Approved Date:</strong> ${new Date(enrollment.approved_date).toLocaleString()}</p>` : ''}
                    ${enrollment.approved_by_name ? `<p><strong>Approved By:</strong> ${enrollment.approved_by_name}</p>` : ''}
                    ${enrollment.notes ? `<p><strong>Notes:</strong> ${enrollment.notes}</p>` : ''}
                </div>
            </div>
        `;

        document.getElementById('enrollmentDetailsContent').innerHTML = html;
        document.getElementById('detailsModal').classList.add('active');
    }

    function closeDetailsModal() {
        document.getElementById('detailsModal').classList.remove('active');
    }

    // Close modals when clicking outside
    document.getElementById('statusModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    document.getElementById('detailsModal').addEventListener('click', function(e) {
        if (e.target === this) closeDetailsModal();
    });
</script>

<?php include INCLUDES_PATH . '/footer.php'; ?>