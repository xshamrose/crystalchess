<?php

/**
 * Admin Participant Management
 * File: modules/admin/manage-participants.php
 * Admin can view all users and manage their participants
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();
$auth->requireLogin();

// Check admin access
if ($_SESSION['user_type'] !== 'admin') {
    header('Location: ' . BASE_URL . '/dashboard');
    exit;
}

$db = Database::getInstance();

// Fetch all users with participant count
$users = $db->query("
    SELECT 
        u.user_id,
        u.full_name,
        u.email,
        u.user_type,
        u.created_at,
        COUNT(p.participant_id) as participant_count
    FROM users u
    LEFT JOIN participants p ON u.user_id = p.user_id
    WHERE u.user_type IN ('player', 'organizer')
    GROUP BY u.user_id
    ORDER BY u.full_name ASC
")->fetchAll();

$pageTitle = 'Manage User Participants';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .user-row {
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .user-row:hover {
        background: #F3F4F6;
    }

    .participants-container {
        display: none;
        background: #F9FAFB;
    }

    .participants-container.active {
        display: table-row;
    }

    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal-content {
        background: white;
        border-radius: 1rem;
        max-width: 700px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
        from {
            transform: translateY(20px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .expand-icon {
        transition: transform 0.3s ease;
    }

    .expand-icon.rotated {
        transform: rotate(90deg);
    }
</style>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Manage User Participants</h1>
            <p class="mt-2 text-gray-600">View and manage participants for all users</p>
        </div>

        <!-- Alert Container -->
        <div id="alertContainer"></div>

        <!-- Users Table -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12">

                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                User
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                User Type
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Participants
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Member Since
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($users as $user): ?>
                            <!-- User Row -->
                            <tr class="user-row" onclick="toggleParticipants(<?= $user['user_id'] ?>)">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <i class="fas fa-chevron-right expand-icon text-gray-400" id="icon-<?= $user['user_id'] ?>"></i>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 flex-shrink-0">
                                            <div class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-bold">
                                                <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($user['full_name']) ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?= htmlspecialchars($user['email']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <?= ucfirst($user['user_type']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800">
                                        <i class="fas fa-users mr-1"></i>
                                        <?= $user['participant_count'] ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('M d, Y', strtotime($user['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button onclick="event.stopPropagation(); openAddParticipantModal(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['full_name']) ?>')"
                                        class="text-blue-600 hover:text-blue-900 transition">
                                        <i class="fas fa-plus-circle"></i> Add
                                    </button>
                                </td>
                            </tr>

                            <!-- Participants Container -->
                            <tr class="participants-container" id="participants-<?= $user['user_id'] ?>">
                                <td colspan="6" class="px-6 py-4">
                                    <div class="pl-16">
                                        <div class="flex items-center justify-between mb-4">
                                            <h3 class="text-lg font-semibold text-gray-900">
                                                Participants for <?= htmlspecialchars($user['full_name']) ?>
                                            </h3>
                                        </div>
                                        <div id="participant-list-<?= $user['user_id'] ?>" class="text-center text-gray-500 py-4">
                                            <i class="fas fa-spinner fa-spin mr-2"></i>Loading...
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Participant Modal -->
<div id="participantModal" class="modal-overlay">
    <div class="modal-content">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <h2 id="modalTitle" class="text-2xl font-bold text-gray-900">Add Participant</h2>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <p id="modalSubtitle" class="text-sm text-gray-600 mt-1"></p>
        </div>

        <form id="participantForm" class="p-6" enctype="multipart/form-data">
            <input type="hidden" id="participantId" name="participant_id">
            <input type="hidden" id="userId" name="user_id">

            <div class="space-y-4">
                <!-- Full Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Full Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="fullName" name="full_name" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <!-- Date of Birth & Gender -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Date of Birth <span class="text-red-500">*</span>
                        </label>
                        <input type="date" id="dateOfBirth" name="date_of_birth" required
                            max="<?= date('Y-m-d') ?>"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Gender <span class="text-red-500">*</span>
                        </label>
                        <select id="gender" name="gender" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Select</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="others">Other</option>
                        </select>
                    </div>
                </div>

                <!-- Contact & Email -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number</label>
                        <input type="tel" id="contactNumber" name="contact_number"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" id="email" name="email"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    </div>
                </div>

                <!-- Files -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Passport Photo</label>
                    <input type="file" id="passportPhoto" name="passport_photo" accept="image/*"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">FIDE ID</label>
                    <input type="text" id="fideId" name="fide_id"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Birth Certificate</label>
                    <input type="file" id="birthCertificate" name="birth_certificate" accept="image/*,application/pdf"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Aadhar Card</label>
                    <input type="file" id="aadharCard" name="aadhar_card" accept="image/*,application/pdf"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="flex gap-3 mt-6">
                <button type="button" onclick="closeModal()"
                    class="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit" id="submitBtn"
                    class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                    <span id="submitBtnText">Save Participant</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    let loadedUsers = new Set();

    // Toggle participants view
    async function toggleParticipants(userId) {
        const container = document.getElementById(`participants-${userId}`);
        const icon = document.getElementById(`icon-${userId}`);
        const listContainer = document.getElementById(`participant-list-${userId}`);

        if (container.classList.contains('active')) {
            container.classList.remove('active');
            icon.classList.remove('rotated');
        } else {
            container.classList.add('active');
            icon.classList.add('rotated');

            // Load participants if not already loaded
            if (!loadedUsers.has(userId)) {
                await loadParticipants(userId);
                loadedUsers.add(userId);
            }
        }
    }

    // Load participants for a user
    async function loadParticipants(userId) {
        const listContainer = document.getElementById(`participant-list-${userId}`);

        try {
            const response = await fetch(`<?= BASE_URL ?>/api/participants/get-by-user.php?user_id=${userId}`);
            const result = await response.json();

            if (result.success && result.participants.length > 0) {
                let html = '<div class="grid grid-cols-1 gap-3">';

                result.participants.forEach(p => {
                    const age = calculateAge(p.date_of_birth);
                    const genderIcon = p.gender === 'male' ? '♂️' : (p.gender === 'female' ? '♀️' : '⚧');

                    html += `
                    <div class="bg-white border border-gray-200 rounded-lg p-4 flex items-center justify-between hover:shadow-md transition">
                        <div class="flex items-center space-x-4">
                            <div class="h-12 w-12 rounded-full bg-gradient-to-br from-${p.gender === 'male' ? 'blue' : 'pink'}-400 to-${p.gender === 'male' ? 'blue' : 'pink'}-600 flex items-center justify-center text-white font-bold">
                                ${p.full_name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-900">${p.full_name}</h4>
                                <p class="text-sm text-gray-600">${genderIcon} ${p.gender} • ${age} years</p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="editParticipant(${JSON.stringify(p).replace(/"/g, '&quot;')}, ${userId})" 
                                    class="text-blue-600 hover:text-blue-800">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteParticipant(${p.participant_id}, '${p.full_name}', ${userId})" 
                                    class="text-red-600 hover:text-red-800">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
                });

                html += '</div>';
                listContainer.innerHTML = html;
            } else {
                listContainer.innerHTML = '<p class="text-gray-500 py-4">No participants yet</p>';
            }
        } catch (error) {
            console.error('Error:', error);
            listContainer.innerHTML = '<p class="text-red-500">Failed to load participants</p>';
        }
    }

    function calculateAge(dob) {
        const birthDate = new Date(dob);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
        return age;
    }

    function openAddParticipantModal(userId, userName) {
        document.getElementById('modalTitle').textContent = 'Add Participant';
        document.getElementById('modalSubtitle').textContent = `For ${userName}`;
        document.getElementById('participantForm').reset();
        document.getElementById('participantId').value = '';
        document.getElementById('userId').value = userId;
        document.getElementById('participantModal').classList.add('active');
    }

    function editParticipant(participant, userId) {
        document.getElementById('modalTitle').textContent = 'Edit Participant';
        document.getElementById('participantId').value = participant.participant_id;
        document.getElementById('userId').value = userId;
        document.getElementById('fullName').value = participant.full_name;
        document.getElementById('dateOfBirth').value = participant.date_of_birth;
        document.getElementById('gender').value = participant.gender;
        document.getElementById('contactNumber').value = participant.contact_number || '';
        document.getElementById('email').value = participant.email || '';
        document.getElementById('fideId').value = participant.fide_id || '';
        document.getElementById('participantModal').classList.add('active');
    }

    function closeModal() {
        document.getElementById('participantModal').classList.remove('active');
    }

    // Submit form
    document.getElementById('participantForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const participantId = document.getElementById('participantId').value;
        const userId = document.getElementById('userId').value;
        const url = participantId ?
            '<?= BASE_URL ?>/api/participants/admin-update.php' :
            '<?= BASE_URL ?>/api/participants/admin-create.php';

        try {
            const response = await fetch(url, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                showAlert('success', result.message);
                closeModal();
                loadedUsers.delete(parseInt(userId));
                await loadParticipants(userId);
            } else {
                showAlert('error', result.message);
            }
        } catch (error) {
            showAlert('error', 'An error occurred');
        }
    });

    async function deleteParticipant(id, name, userId) {
        if (!confirm(`Delete ${name}?`)) return;

        try {
            const response = await fetch('<?= BASE_URL ?>/api/participants/admin-delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    participant_id: id
                })
            });

            const result = await response.json();
            if (result.success) {
                showAlert('success', 'Participant deleted!');
                loadedUsers.delete(userId);
                await loadParticipants(userId);
            } else {
                showAlert('error', result.message);
            }
        } catch (error) {
            showAlert('error', 'An error occurred');
        }
    }

    function showAlert(type, message) {
        const html = `
        <div class="mb-6 bg-${type === 'success' ? 'green' : 'red'}-50 border-l-4 border-${type === 'success' ? 'green' : 'red'}-400 p-4 rounded-md">
            <p class="text-${type === 'success' ? 'green' : 'red'}-700">${message}</p>
        </div>
    `;
        document.getElementById('alertContainer').innerHTML = html;
        setTimeout(() => document.getElementById('alertContainer').innerHTML = '', 3000);
    }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>