<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Validator.php';

$auth = new Auth($pdo);
$auth->requireLogin();
$auth->requireRole(['organizer', 'admin']);

// Get user data
$user = $_SESSION['user'] ?? null;

if (!$user || !isset($user['user_id'])) {
    Auth::logout();
    header('Location: ' . BASE_URL . '/login');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validator = new Validator($_POST);
    
    // Validation rules
    $validator->required('event_name')->minLength('event_name', 3)->maxLength('event_name', 255);
    $validator->required('event_date')->futureDate('event_date');
    $validator->required('event_time');
    $validator->required('location')->minLength('location', 3);
    $validator->required('max_capacity')->numeric('max_capacity')->min('max_capacity', 1);
    $validator->required('entry_fee')->numeric('entry_fee')->min('entry_fee', 0);
    
    $errors = $validator->getErrors();
    
    if (empty($errors)) {
        try {
            // Handle image upload
            $event_image = null;
            if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                if (in_array($_FILES['event_image']['type'], $allowed_types) && $_FILES['event_image']['size'] <= $max_size) {
                    $upload_dir = __DIR__ . '/../../uploads/events/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_extension = pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION);
                    $new_filename = uniqid('event_') . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['event_image']['tmp_name'], $upload_path)) {
                        $event_image = 'uploads/events/' . $new_filename;
                    }
                } else {
                    $errors[] = "Invalid image file. Please upload JPG or PNG under 5MB.";
                }
            }
            
            if (empty($errors)) {
                $sql = "INSERT INTO events (
                    organizer_id, event_name, description, event_date, event_time, 
                    location, venue_address, entry_fee, max_capacity, rules, 
                    event_image, event_status, featured, current_bookings
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'upcoming', 0, 0)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $user['user_id'],
                    $_POST['event_name'],
                    $_POST['description'] ?? null,
                    $_POST['event_date'],
                    $_POST['event_time'],
                    $_POST['location'],
                    $_POST['venue_address'] ?? null,
                    $_POST['entry_fee'],
                    $_POST['max_capacity'],
                    $_POST['rules'] ?? null,
                    $event_image
                ]);
                
                $success = true;
                $_SESSION['success_message'] = "Event created successfully!";
                header('Location: ' . BASE_URL . '/manage-events');
                exit;
            }
        } catch (PDOException $e) {
            error_log("Event creation error: " . $e->getMessage());
            $errors[] = "Database error: Unable to create event. Please try again.";
        }
    }
}

// Set page title
$pageTitle = 'Create New Event - Crystal Chess';

// Include header
include INCLUDES_PATH . '/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Create New Event</h1>
                    <p class="mt-2 text-gray-600">Fill in the details to create your chess tournament</p>
                </div>
                <a href="<?= BASE_URL ?>/manage-events" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Events
                </a>
            </div>
        </div>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 rounded-lg p-4 shadow-sm">
                <div class="flex">
                    <svg class="h-5 w-5 text-red-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
                    </svg>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Please fix the following errors:</h3>
                        <ul class="mt-2 text-sm text-red-700 list-disc list-inside space-y-1">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Event Creation Form -->
        <form method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow-lg">
            
            <!-- Basic Information -->
            <div class="px-6 py-5 border-b border-gray-200 bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Basic Information
                </h2>
            </div>
            
            <div class="px-6 py-5 space-y-6">
                
                <!-- Event Name -->
                <div>
                    <label for="event_name" class="block text-sm font-medium text-gray-700 mb-2">
                        Event Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="event_name" id="event_name" required
                           value="<?= htmlspecialchars($_POST['event_name'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                           placeholder="e.g., Crystal Chess Championship 2025">
                    <p class="mt-1 text-xs text-gray-500">Choose a descriptive and memorable name</p>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                        Description
                    </label>
                    <textarea name="description" id="description" rows="4"
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                              placeholder="Describe your event, format, and what participants can expect..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    <p class="mt-1 text-xs text-gray-500">Provide details about the tournament format, prizes, and expectations</p>
                </div>

                <!-- Date and Time -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="event_date" class="block text-sm font-medium text-gray-700 mb-2">
                            Event Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="event_date" id="event_date" required
                               value="<?= htmlspecialchars($_POST['event_date'] ?? '') ?>"
                               min="<?= date('Y-m-d') ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                    </div>
                    
                    <div>
                        <label for="event_time" class="block text-sm font-medium text-gray-700 mb-2">
                            Event Time <span class="text-red-500">*</span>
                        </label>
                        <input type="time" name="event_time" id="event_time" required
                               value="<?= htmlspecialchars($_POST['event_time'] ?? '') ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                    </div>
                </div>

            </div>

            <!-- Location Details -->
            <div class="px-6 py-5 border-t border-gray-200 bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Location Details
                </h2>
            </div>
            
            <div class="px-6 py-5 space-y-6">
                <!-- Location -->
                <div>
                    <label for="location" class="block text-sm font-medium text-gray-700 mb-2">
                        City/Location <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="location" id="location" required
                           value="<?= htmlspecialchars($_POST['location'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                           placeholder="e.g., New York, NY">
                </div>

                <!-- Venue Address -->
                <div>
                    <label for="venue_address" class="block text-sm font-medium text-gray-700 mb-2">
                        Venue Address
                    </label>
                    <textarea name="venue_address" id="venue_address" rows="2"
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                              placeholder="Full venue address with street, city, state, and zip code"><?= htmlspecialchars($_POST['venue_address'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Event Capacity & Pricing -->
            <div class="px-6 py-5 border-t border-gray-200 bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Capacity & Pricing
                </h2>
            </div>
            
            <div class="px-6 py-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Max Capacity -->
                    <div>
                        <label for="max_capacity" class="block text-sm font-medium text-gray-700 mb-2">
                            Maximum Participants <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="max_capacity" id="max_capacity" required min="1"
                               value="<?= htmlspecialchars($_POST['max_capacity'] ?? '') ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                               placeholder="e.g., 50">
                        <p class="mt-1 text-xs text-gray-500">Total number of participants allowed</p>
                    </div>

                    <!-- Entry Fee -->
                    <div>
                        <label for="entry_fee" class="block text-sm font-medium text-gray-700 mb-2">
                            Entry Fee ($) <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-3.5 text-gray-500">$</span>
                            <input type="number" name="entry_fee" id="entry_fee" required min="0" step="0.01"
                                   value="<?= htmlspecialchars($_POST['entry_fee'] ?? '') ?>"
                                   class="w-full pl-8 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                                   placeholder="0.00">
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Set to 0 for free events</p>
                    </div>
                </div>
            </div>

            <!-- Rules & Guidelines -->
            <div class="px-6 py-5 border-t border-gray-200 bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Rules & Guidelines
                </h2>
            </div>
            
            <div class="px-6 py-5">
                <div>
                    <label for="rules" class="block text-sm font-medium text-gray-700 mb-2">
                        Tournament Rules
                    </label>
                    <textarea name="rules" id="rules" rows="5"
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition font-mono text-sm"
                              placeholder="Enter tournament rules, time controls, scoring system, etc."><?= htmlspecialchars($_POST['rules'] ?? '') ?></textarea>
                    <p class="mt-1 text-xs text-gray-500">Include time controls, format, and any special rules</p>
                </div>
            </div>

            <!-- Event Image -->
            <div class="px-6 py-5 border-t border-gray-200 bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                    <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Event Image
                </h2>
            </div>
            
            <div class="px-6 py-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Upload Event Banner (Optional)
                    </label>
                    <div id="dropZone" class="mt-2 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-indigo-400 transition cursor-pointer">
                        <div class="space-y-2 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="flex text-sm text-gray-600">
                                <label for="event_image" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500">
                                    <span>Upload a file</span>
                                    <input id="event_image" name="event_image" type="file" accept="image/jpeg,image/png,image/jpg" class="sr-only">
                                </label>
                                <p class="pl-1">or drag and drop</p>
                            </div>
                            <p class="text-xs text-gray-500">PNG, JPG up to 5MB</p>
                        </div>
                    </div>
                    <div id="imagePreview" class="mt-4 hidden">
                        <img id="previewImg" src="" alt="Preview" class="max-w-full h-48 rounded-lg mx-auto shadow-md">
                        <p id="fileName" class="text-sm text-center text-gray-600 mt-2"></p>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="px-6 py-4 bg-gray-100 border-t border-gray-200 flex justify-end space-x-3">
                <a href="<?= BASE_URL ?>/manage-events" 
                   class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-white transition font-medium">
                    <i class="fas fa-times mr-2"></i>Cancel
                </a>
                <button type="submit" 
                        class="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium shadow-md hover:shadow-lg">
                    <i class="fas fa-check mr-2"></i>Create Event
                </button>
            </div>

        </form>

    </div>
</div>

<script>
// Image preview
const imageInput = document.getElementById('event_image');
const dropZone = document.getElementById('dropZone');
const imagePreview = document.getElementById('imagePreview');
const previewImg = document.getElementById('previewImg');
const fileName = document.getElementById('fileName');

imageInput.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        displayImage(file);
    }
});

// Drag and drop functionality
dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.classList.add('border-indigo-500', 'bg-indigo-50');
});

dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('border-indigo-500', 'bg-indigo-50');
});

dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('border-indigo-500', 'bg-indigo-50');
    
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
        imageInput.files = e.dataTransfer.files;
        displayImage(file);
    }
});

function displayImage(file) {
    const reader = new FileReader();
    reader.onload = function(e) {
        previewImg.src = e.target.result;
        fileName.textContent = file.name;
        imagePreview.classList.remove('hidden');
    }
    reader.readAsDataURL(file);
}

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const eventDate = new Date(document.getElementById('event_date').value);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (eventDate < today) {
        e.preventDefault();
        alert('Event date must be in the future');
        return false;
    }
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Creating Event...';
});
</script>

</main>

<?php include INCLUDES_PATH . '/footer.php'; ?>