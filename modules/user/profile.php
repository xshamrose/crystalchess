<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Validator.php';

$auth = new Auth();
$auth->requireLogin();

$errors = [];
$success = '';
$db = Database::getInstance();
$conn = $db->getConnection(); // in case we need native PDO access later
$userId = $auth->getUserId();

// ✅ Fetch user data using our Database class
$user = $db->query("SELECT * FROM users WHERE user_id = :id")
           ->bind(":id", $userId)
           ->fetch();

if (!$user) {
    header('Location: login.php');
    exit;
}

// ✅ Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        // Validation
        $validator = new Validator([
            'full_name' => $fullName,
            'phone' => $phone
        ]);

        $validator->required('full_name', 'Full name')
                  ->minLength('full_name', 3, 'Full name')
                  ->maxLength('full_name', 100, 'Full name');

        if (!empty($phone)) {
            $validator->phone('phone');
        }

        $errors = $validator->getErrors();

        // ✅ Handle profile picture upload
        $profilePicture = $user['profile_picture'];
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            $fileType = $_FILES['profile_picture']['type'];
            $fileSize = $_FILES['profile_picture']['size'];

            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = 'Invalid file type. Only JPG and PNG allowed.';
            } elseif ($fileSize > $maxSize) {
                $errors[] = 'File too large. Maximum size is 5MB.';
            } else {
                $uploadDir = __DIR__ . '/../../uploads/profiles/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $filename = 'profile_' . $userId . '_' . time() . '.' . $extension;
                $destination = $uploadDir . $filename;

                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $destination)) {
                    // Delete old picture
                    $oldFile = __DIR__ . '/../../uploads/profiles/' . $user['profile_picture'];
                    if ($user['profile_picture'] && file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                    $profilePicture = $filename;
                } else {
                    $errors[] = 'Failed to upload profile picture.';
                }
            }
        }

        // ✅ Update profile
        if (empty($errors)) {
            $result = $db->query("
                UPDATE users 
                SET full_name = :full_name, phone = :phone, profile_picture = :pic, updated_at = NOW()
                WHERE user_id = :id
            ")
            ->bind(':full_name', $fullName)
            ->bind(':phone', $phone)
            ->bind(':pic', $profilePicture)
            ->bind(':id', $userId)
            ->execute();

            if ($result) {
                $success = 'Profile updated successfully!';
                $user['full_name'] = $fullName;
                $user['phone'] = $phone;
                $user['profile_picture'] = $profilePicture;
                $_SESSION['user_name'] = $fullName;
            } else {
                $errors[] = 'Failed to update profile.';
            }
        }
    }
}

// ✅ Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $validator = new Validator([
            'current_password' => $currentPassword,
            'new_password' => $newPassword,
            'confirm_password' => $confirmPassword
        ]);

        $validator->required('current_password', 'Current password')
                  ->required('new_password', 'New password')
                  ->minLength('new_password', 8, 'New password')
                  ->password('new_password')
                  ->required('confirm_password', 'Confirm password')
                  ->match('new_password', 'confirm_password', 'Passwords');

        $errors = $validator->getErrors();

        if (empty($errors)) {
            if (!password_verify($currentPassword, $user['password_hash'])) {
                $errors[] = 'Current password is incorrect.';
            }
        }

        if (empty($errors)) {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updated = $db->query("
                UPDATE users 
                SET password_hash = :pass, updated_at = NOW() 
                WHERE user_id = :id
            ")
            ->bind(':pass', $passwordHash)
            ->bind(':id', $userId)
            ->execute();

            if ($updated) {
                $success = 'Password changed successfully!';
            } else {
                $errors[] = 'Failed to change password.';
            }
        }
    }
}

// ✅ CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Profile Settings</h1>
            <p class="mt-1 text-sm text-gray-600">Manage your account information and preferences</p>
        </div>

        <!-- Success Message -->
        <?php if ($success): ?>
        <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4 rounded-md">
            <div class="flex">
                <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <p class="ml-3 text-sm text-green-700"><?php echo htmlspecialchars($success); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
        <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-md">
            <div class="flex">
                <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <div class="ml-3">
                    <ul class="text-sm text-red-700 list-disc list-inside">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow p-6">
                    <!-- Profile Picture -->
                    <div class="text-center">
                        <div class="mx-auto h-32 w-32 rounded-full overflow-hidden bg-gray-200 mb-4">
                            <?php if ($user['profile_picture']): ?>
                            <img src="../../uploads/profiles/<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                                 alt="Profile" 
                                 class="h-full w-full object-cover">
                            <?php else: ?>
                            <div class="h-full w-full flex items-center justify-center bg-indigo-500 text-white text-4xl font-bold">
                                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></h3>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></p>
                        <span class="mt-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                            <?php echo ucfirst($user['user_type']); ?>
                        </span>
                    </div>

                    <!-- Account Status -->
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500">Email Status</span>
                            <?php if ($user['email_verified']): ?>
                            <span class="text-green-600 flex items-center">
                                <svg class="h-4 w-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Verified
                            </span>
                            <?php else: ?>
                            <span class="text-yellow-600">Not Verified</span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-sm">
                            <span class="text-gray-500">Account Status</span>
                            <span class="text-<?php echo $user['status'] === 'active' ? 'green' : 'gray'; ?>-600 capitalize">
                                <?php echo htmlspecialchars($user['status']); ?>
                            </span>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-sm">
                            <span class="text-gray-500">Member Since</span>
                            <span class="text-gray-900">
                                <?php echo date('M Y', strtotime($user['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Profile Information Form -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Profile Information</h2>
                    </div>
                    <form method="POST" enctype="multipart/form-data" class="px-6 py-4 space-y-4">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="update_profile" value="1">

                        <!-- Full Name -->
                        <div>
                            <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="full_name" 
                                name="full_name" 
                                required
                                value="<?php echo htmlspecialchars($user['full_name']); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                        </div>

                        <!-- Email (Read-only) -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                Email Address
                            </label>
                            <input 
                                type="email" 
                                id="email" 
                                value="<?php echo htmlspecialchars($user['email']); ?>"
                                disabled
                                class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-500 cursor-not-allowed"
                            >
                            <p class="mt-1 text-xs text-gray-500">Email cannot be changed</p>
                        </div>

                        <!-- Phone -->
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                                Phone Number
                            </label>
                            <input 
                                type="tel" 
                                id="phone" 
                                name="phone" 
                                value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                placeholder="+1234567890"
                            >
                        </div>

                        <!-- Profile Picture -->
                        <div>
                            <label for="profile_picture" class="block text-sm font-medium text-gray-700 mb-1">
                                Profile Picture
                            </label>
                            <input 
                                type="file" 
                                id="profile_picture" 
                                name="profile_picture" 
                                accept="image/jpeg,image/png,image/jpg"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                            <p class="mt-1 text-xs text-gray-500">JPG or PNG. Max 5MB.</p>
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-4">
                            <button 
                                type="submit" 
                                class="w-full sm:w-auto px-6 py-2 bg-indigo-600 text-white font-medium rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                            >
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Change Password Form -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Change Password</h2>
                    </div>
                    <form method="POST" class="px-6 py-4 space-y-4">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="change_password" value="1">

                        <!-- Current Password -->
                        <div>
                            <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">
                                Current Password <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="password" 
                                id="current_password" 
                                name="current_password" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                        </div>

                        <!-- New Password -->
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">
                                New Password <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="password" 
                                id="new_password" 
                                name="new_password" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                            <p class="mt-1 text-xs text-gray-500">Min. 8 characters with uppercase, lowercase, and number</p>
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">
                                Confirm New Password <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                        </div>

                        <!-- Submit Button -->
                        <div class="pt-4">
                            <button 
                                type="submit" 
                                class="w-full sm:w-auto px-6 py-2 bg-green-600 text-white font-medium rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors"
                            >
                                Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>