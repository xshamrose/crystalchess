<?php

/**
 * User Participant Management Page
 * File: modules/user/manage-participants.php
 * Users can add, edit, delete their participants
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// Fetch user's participants
$participants = $db->query("
    SELECT * FROM participants 
    WHERE user_id = :user_id 
    ORDER BY created_at DESC
")->bind(':user_id', $userId)->fetchAll();

$pageTitle = 'Manage Participants';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
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
        max-width: 600px;
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

    .participant-card {
        transition: all 0.2s ease;
    }

    .participant-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    }

    .participant-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        font-weight: bold;
        color: white;
    }

    .gender-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .male-badge {
        background: #DBEAFE;
        color: #1E40AF;
    }

    .female-badge {
        background: #FCE7F3;
        color: #BE185D;
    }

    .others-badge {
        background: #E0E7FF;
        color: #4338CA;
    }
</style>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <!-- Header -->
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">My Participants</h1>
                <p class="mt-2 text-gray-600">Manage participants for tournament bookings</p>
            </div>
            <button onclick="openAddModal()"
                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold shadow-lg transition">
                <i class="fas fa-plus mr-2"></i>Add Participant
            </button>
        </div>

        <!-- Alert Messages -->
        <div id="alertContainer"></div>

        <!-- Empty State -->
        <?php if (empty($participants)): ?>
            <div class="bg-white rounded-lg shadow-sm p-12 text-center">
                <div class="text-6xl mb-4">👤</div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No Participants Yet</h3>
                <p class="text-gray-600 mb-6">Add participants to quickly book tournaments</p>
                <button onclick="openAddModal()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition">
                    <i class="fas fa-plus mr-2"></i>Add Your First Participant
                </button>
            </div>
        <?php else: ?>

            <!-- Participants Table -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Participant
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Gender
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Age / DOB
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Contact
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    FIDE ID
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($participants as $participant): ?>
                                <?php
                                $dob = new DateTime($participant['date_of_birth']);
                                $now = new DateTime();
                                $age = $now->diff($dob)->y;
                                ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="h-10 w-10 rounded-full flex items-center justify-center text-white font-bold"
                                                    style="background: linear-gradient(135deg, <?php echo $participant['gender'] === 'male' ? '#3B82F6, #1D4ED8' : ($participant['gender'] === 'female' ? '#EC4899, #BE185D' : '#8B5CF6, #6D28D9'); ?>)">
                                                    <?php echo strtoupper(substr($participant['full_name'], 0, 1)); ?>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($participant['full_name']); ?>
                                                </div>
                                                <?php if (!empty($participant['email'])): ?>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo htmlspecialchars($participant['email']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php echo $participant['gender'] === 'male' ? 'bg-blue-100 text-blue-800' : ($participant['gender'] === 'female' ? 'bg-pink-100 text-pink-800' : 'bg-purple-100 text-purple-800'); ?>">
                                            <?php
                                            if ($participant['gender'] === 'male') echo '♂️ Male';
                                            elseif ($participant['gender'] === 'female') echo '♀️ Female';
                                            else echo '⚧ Other';
                                            ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 font-medium"><?php echo $age; ?> years</div>
                                        <div class="text-sm text-gray-500"><?php echo date('M d, Y', strtotime($participant['date_of_birth'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if (!empty($participant['contact_number'])): ?>
                                            <div class="text-sm text-gray-900">
                                                <i class="fas fa-phone text-gray-400 mr-1"></i>
                                                <?php echo htmlspecialchars($participant['contact_number']); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-sm text-gray-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if (!empty($participant['fide_id'])): ?>
                                            <div class="text-sm text-gray-900 font-mono">
                                                <?php echo htmlspecialchars($participant['fide_id']); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-sm text-gray-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button onclick='editParticipant(<?php echo json_encode($participant); ?>)'
                                            class="text-blue-600 hover:text-blue-900 mr-3 transition"
                                            title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="deleteParticipant(<?php echo $participant['participant_id']; ?>, '<?php echo htmlspecialchars($participant['full_name']); ?>')"
                                            class="text-red-600 hover:text-red-900 transition"
                                            title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="participantModal" class="modal-overlay">
    <div class="modal-content">
        <div class="p-6 border-b">
            <div class="flex items-center justify-between">
                <h2 id="modalTitle" class="text-2xl font-bold text-gray-900">Add Participant</h2>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
        </div>

        <form id="participantForm" class="p-6">
            <input type="hidden" id="participantId" name="participant_id">

            <div class="space-y-4">
                <!-- Full Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Full Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="fullName" name="full_name" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Enter full name">
                </div>

                <!-- Date of Birth -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Date of Birth <span class="text-red-500">*</span>
                    </label>
                    <input type="date" id="dateOfBirth" name="date_of_birth" required
                        max="<?php echo date('Y-m-d'); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <!-- Gender -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Gender <span class="text-red-500">*</span>
                    </label>
                    <select id="gender" name="gender" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">Select Gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="others">Other</option>
                    </select>
                </div>

                <!-- Contact Number -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Contact Number
                    </label>
                    <input type="tel" id="contactNumber" name="contact_number"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="+1234567890">
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Email Address
                    </label>
                    <input type="email" id="email" name="email"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="email@example.com">
                </div>

                <!-- Passport Photo -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Passport Size Photo
                    </label>
                    <input type="file" id="passportPhoto" name="passport_photo" accept="image/jpeg,image/jpg,image/png"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p class="mt-1 text-xs text-gray-500">JPG or PNG, max 5MB</p>
                </div>

                <!-- FIDE ID -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        FIDE ID (Optional)
                    </label>
                    <input type="text" id="fideId" name="fide_id"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Enter FIDE ID">
                </div>

                <!-- Birth Certificate -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Birth Certificate (Optional)
                    </label>
                    <input type="file" id="birthCertificate" name="birth_certificate" accept="image/*,application/pdf"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p class="mt-1 text-xs text-gray-500">JPG, PNG or PDF, max 10MB</p>
                </div>

                <!-- Aadhar Card -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Aadhar Card (Optional)
                    </label>
                    <input type="file" id="aadharCard" name="aadhar_card" accept="image/*,application/pdf"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p class="mt-1 text-xs text-gray-500">JPG, PNG or PDF, max 10MB</p>
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
                    <span id="submitBtnLoading" class="hidden">
                        <i class="fas fa-spinner fa-spin mr-1"></i>Saving...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Open Add Modal
    function openAddModal() {
        document.getElementById('modalTitle').textContent = 'Add Participant';
        document.getElementById('participantForm').reset();
        document.getElementById('participantId').value = '';
        document.getElementById('submitBtnText').textContent = 'Save Participant';
        document.getElementById('participantModal').classList.add('active');
    }

    // Edit Participant
    function editParticipant(participant) {
        document.getElementById('modalTitle').textContent = 'Edit Participant';
        document.getElementById('participantId').value = participant.participant_id;
        document.getElementById('fullName').value = participant.full_name;
        document.getElementById('dateOfBirth').value = participant.date_of_birth;
        document.getElementById('gender').value = participant.gender;
        document.getElementById('contactNumber').value = participant.contact_number || '';
        document.getElementById('email').value = participant.email || '';
        document.getElementById('fideId').value = participant.fide_id || '';
        document.getElementById('submitBtnText').textContent = 'Update Participant';
        document.getElementById('participantModal').classList.add('active');
    }

    // Close Modal
    function closeModal() {
        document.getElementById('participantModal').classList.remove('active');
    }

    // Submit Form
    document.getElementById('participantForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const submitBtn = document.getElementById('submitBtn');
        const submitBtnText = document.getElementById('submitBtnText');
        const submitBtnLoading = document.getElementById('submitBtnLoading');

        submitBtn.disabled = true;
        submitBtnText.classList.add('hidden');
        submitBtnLoading.classList.remove('hidden');

        const formData = new FormData(this);
        const participantId = document.getElementById('participantId').value;
        const url = participantId ?
            '<?php echo BASE_URL; ?>/api/participants/update.php' :
            '<?php echo BASE_URL; ?>/api/participants/create.php';

        try {
            const response = await fetch(url, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                showAlert('success', result.message || 'Participant saved successfully!');
                closeModal();
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert('error', result.message || 'Failed to save participant');
                submitBtn.disabled = false;
                submitBtnText.classList.remove('hidden');
                submitBtnLoading.classList.add('hidden');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('error', 'An error occurred. Please try again.');
            submitBtn.disabled = false;
            submitBtnText.classList.remove('hidden');
            submitBtnLoading.classList.add('hidden');
        }
    });

    // Delete Participant
    async function deleteParticipant(id, name) {
        if (!confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
            return;
        }

        try {
            const response = await fetch('<?php echo BASE_URL; ?>/api/participants/delete.php', {
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
                showAlert('success', 'Participant deleted successfully!');
                setTimeout(() => location.reload(), 1000);
            } else {
                showAlert('error', result.message || 'Failed to delete participant');
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('error', 'An error occurred. Please try again.');
        }
    }

    // Show Alert
    function showAlert(type, message) {
        const alertHTML = `
        <div class="mb-6 bg-${type === 'success' ? 'green' : 'red'}-50 border-l-4 border-${type === 'success' ? 'green' : 'red'}-400 p-4 rounded-md">
            <div class="flex">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} text-${type === 'success' ? 'green' : 'red'}-400 mr-3 mt-1"></i>
                <p class="text-${type === 'success' ? 'green' : 'red'}-700">${message}</p>
            </div>
        </div>
    `;
        document.getElementById('alertContainer').innerHTML = alertHTML;
        setTimeout(() => {
            document.getElementById('alertContainer').innerHTML = '';
        }, 5000);
    }

    // Close modal on outside click
    document.getElementById('participantModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>