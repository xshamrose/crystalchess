<?php
// modules/user/register.php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Mailer.php';

// Ensure session is started (config.php usually does this, but safe check)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (Auth::check()) {
    header('Location: ' . BASE_URL . '/dashboard');
    exit;
}

$errors = [];
$success = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        // Get form data
        $formData = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'user_type' => $_POST['user_type'] ?? 'player'
        ];

        // Validation
        $validator = new Validator($formData);
        $validator->required('full_name', 'Full name')
                  ->minLength('full_name', 3, 'Full name')
                  ->maxLength('full_name', 100, 'Full name');

        $validator->required('email', 'Email')
                  ->email('email');

        $validator->required('password', 'Password')
                  ->minLength('password', 8, 'Password')
                  ->password('password');

        $validator->required('confirm_password', 'Confirm password')
                  ->match('password', 'confirm_password', 'Passwords');

        if (!empty($formData['phone'])) {
            $validator->phone('phone');
        }

        if (!in_array($formData['user_type'], ['player', 'organizer'])) {
            $validator->addError('user_type', 'Invalid user type selected');
        }

        // Collect errors (supports getErrors alias)
        if (method_exists($validator, 'getErrors')) {
            $errors = $validator->getErrors();
        } else {
            // fallback
            $errors = $validator->errors();
        }

        // Check if email already exists (use Database wrapper)
        if (empty($errors)) {
            $db = Database::getInstance();

            $db->query("SELECT user_id FROM users WHERE email = :email");
            $db->bind(':email', $formData['email']);
            $existing = $db->fetch();

            if ($existing) {
                $errors[] = 'Email address already registered. Please login or use a different email.';
            }
        }

        // Register user if no errors
        if (empty($errors)) {
            $auth = new Auth();
            $result = $auth->register([
                'email' => $formData['email'],
                'password' => $formData['password'],
                'full_name' => $formData['full_name'],
                'phone' => $formData['phone'],
                'user_type' => $formData['user_type']
            ]);

          if ($result['success']) {
    // Send verification email
    $mailer = new Mailer();
    $verificationToken = bin2hex(random_bytes(32));

    // Store verification token (24-hour expiry) using wrapper
    $db = Database::getInstance();

    $db->query("
        INSERT INTO password_resets (user_id, token, expires_at)
        VALUES (:user_id, :token, DATE_ADD(NOW(), INTERVAL 24 HOUR))
    ");
    $db->bind(':user_id', $result['user_id']);
    $db->bind(':token', $verificationToken);
    $db->execute();

    $verificationLink = SITE_URL . "/verify-email?token=" . $verificationToken;

    // Send email with the correct method
    $mailer->sendEmailVerification([
        'email' => $formData['email'],
        'name' => $formData['full_name'],
        'verification_link' => $verificationLink
    ]);

    // Store success message in session and redirect
    $_SESSION['registration_success'] = 'Registration successful! Please check your email to verify your account.';
    header('Location: ' . BASE_URL . '/login');
    exit;
} else {
                $errors[] = $result['message'] ?? 'Registration failed. Please try again.';
            }
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <!-- Header -->
        <div class="text-center">
            <h2 class="text-4xl font-extrabold text-gray-900">
                Create Account
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                Join Crystal Chess Tournament Platform
            </p>
        </div>

        <!-- Success Message -->
        <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded-md">
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
        <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded-md">
            <div class="flex">
                <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">There were errors with your submission:</h3>
                    <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Registration Form -->
        <form class="mt-8 space-y-6 bg-white p-8 rounded-xl shadow-lg" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <div class="space-y-4">
                <!-- Full Name -->
                <div>
                    <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">
                        Full Name <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="full_name"
                        name="full_name"
                        type="text"
                        required
                        value="<?php echo htmlspecialchars($formData['full_name'] ?? ''); ?>"
                        class="appearance-none relative block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        placeholder="John Doe"
                    >
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                        Email Address <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        required
                        value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>"
                        class="appearance-none relative block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        placeholder="john@example.com"
                    >
                </div>

                <!-- Phone (Optional) -->
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                        Phone Number <span class="text-gray-400 text-xs">(Optional)</span>
                    </label>
                    <input
                        id="phone"
                        name="phone"
                        type="tel"
                        value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>"
                        class="appearance-none relative block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        placeholder="+1234567890"
                    >
                </div>

                <!-- User Type -->
                <div>
                    <label for="user_type" class="block text-sm font-medium text-gray-700 mb-1">
                        I am a <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="user_type"
                        name="user_type"
                        required
                        class="appearance-none relative block w-full px-3 py-2 border border-gray-300 rounded-md text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    >
                        <option value="player" <?php echo ($formData['user_type'] ?? 'player') === 'player' ? 'selected' : ''; ?>>
                            Player (Book tournaments for myself/others)
                        </option>
                        <option value="organizer" <?php echo ($formData['user_type'] ?? '') === 'organizer' ? 'selected' : ''; ?>>
                            Tournament Organizer (Create & manage events)
                        </option>
                    </select>
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                        Password <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        class="appearance-none relative block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        placeholder="Min. 8 characters"
                    >
                    <p class="mt-1 text-xs text-gray-500">Must be at least 8 characters with uppercase, lowercase, and number</p>
                </div>

                <!-- Confirm Password -->
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">
                        Confirm Password <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="confirm_password"
                        name="confirm_password"
                        type="password"
                        required
                        class="appearance-none relative block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        placeholder="Re-enter password"
                    >
                </div>
            </div>

            <!-- Submit Button -->
            <div>
                <button
                    type="submit"
                    class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                    Create Account
                </button>
            </div>

            <!-- Login Link -->
            <div class="text-center">
                <p class="text-sm text-gray-600">
                    Already have an account?
                    <a href="<?php echo BASE_URL; ?>/login" class="font-medium text-indigo-600 hover:text-indigo-500 transition-colors">
                        Sign in here
                    </a>
                </p>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
