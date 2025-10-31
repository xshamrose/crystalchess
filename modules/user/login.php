<?php

/**
 * Login Page
 * Crystal Chess Tournament Booking Platform
 * File: modules/user/login.php
 */

// Config is already loaded by router - DON'T load again
// But we need to load core classes
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Validator.php';

// Redirect if already logged in
if (Auth::check()) {
    $userType = Auth::getUserType();
    $redirect = BASE_URL . '/dashboard';

    if ($userType === 'admin') {
        $redirect = BASE_URL . '/admin-dashboard';
    } elseif ($userType === 'organizer') {
        $redirect = BASE_URL . '/organizer-dashboard';
    }

    header('Location: ' . $redirect);
    exit;
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        // Basic validation
        $validator = new Validator(['email' => $email, 'password' => $password]);
        $validator->required('email', 'Email')->email('email');
        $validator->required('password', 'Password');

        // $errors = $validator->getErrors();

        // Attempt login
        if (empty($errors)) {
            $auth = new Auth();
            $result = $auth->login($email, $password, $remember);

            if ($result['success']) {
                // Redirect based on user type
                $userType = Auth::getUserType();

                switch ($userType) {
                    case 'admin':
                        header('Location: ' . BASE_URL . '/admin-dashboard');
                        break;
                    case 'organizer':
                        header('Location: ' . BASE_URL . '/organizer-dashboard');
                        break;
                    default:
                        header('Location: ' . BASE_URL . '/dashboard');
                }
                exit;
            } else {
                $errors[] = $result['message'];
            }
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$success = '';
if (isset($_SESSION['registration_success'])) {
    $success = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']); // Clear it after displaying
}
// Page variables for header
$pageTitle = 'Login - Crystal Chess';
$pageDescription = 'Sign in to your Crystal Chess account';

include __DIR__ . '/../../includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <!-- Header -->
        <div class="text-center">
            <div class="mx-auto h-16 w-16 bg-indigo-600 rounded-full flex items-center justify-center">
                <svg class="h-10 w-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
            </div>
            <h2 class="mt-6 text-4xl font-extrabold text-gray-900">
                Welcome Back
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                Sign in to your Crystal Chess account
            </p>
        </div>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded-md">
                <div class="flex">
                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
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

        <!-- Success message from query string (e.g., after registration) -->
        <?php if (isset($_GET['verified'])): ?>
            <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded-md">
                <p class="text-sm text-green-700">✓ Email verified successfully! You can now login.</p>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['reset'])): ?>
            <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded-md">
                <p class="text-sm text-green-700">✓ Password reset successfully! Please login with your new password.</p>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form class="mt-8 space-y-6 bg-white p-8 rounded-xl shadow-lg" method="POST" action="<?php echo BASE_URL; ?>/login">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <div class="space-y-4">
                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                        Email Address
                    </label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        required
                        autofocus
                        value="<?php echo htmlspecialchars($email); ?>"
                        class="appearance-none relative block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        placeholder="you@example.com">
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                        Password
                    </label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        class="appearance-none relative block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        placeholder="••••••••">
                </div>
            </div>

            <!-- Remember Me & Forgot Password -->
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input
                        id="remember"
                        name="remember"
                        type="checkbox"
                        class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded cursor-pointer">
                    <label for="remember" class="ml-2 block text-sm text-gray-900 cursor-pointer">
                        Remember me
                    </label>
                </div>

                <div class="text-sm">
                    <a href="<?php echo BASE_URL; ?>/forgot-password" class="font-medium text-indigo-600 hover:text-indigo-500 transition-colors">
                        Forgot password?
                    </a>
                </div>
            </div>

            <!-- Submit Button -->
            <div>
                <button
                    type="submit"
                    class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-indigo-500 group-hover:text-indigo-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                        </svg>
                    </span>
                    Sign In
                </button>
            </div>

            <!-- Register Link -->
            <div class="text-center">
                <p class="text-sm text-gray-600">
                    Don't have an account?
                    <a href="<?php echo BASE_URL; ?>/register" class="font-medium text-indigo-600 hover:text-indigo-500 transition-colors">
                        Create one now
                    </a>
                </p>
            </div>
        </form>

        <!-- Quick Login Info (for demo/testing) -->
        <?php if (ENVIRONMENT === 'development'): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4 text-xs">
                <p class="font-semibold text-yellow-800 mb-2">Demo Credentials:</p>
                <p class="text-yellow-700">Admin: admin@crystalchess.com / admin123</p>
                <p class="text-yellow-700 text-xs mt-1">⚠️ This appears only in development mode</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>