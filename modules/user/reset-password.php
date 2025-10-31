<?php
require_once '../../config/config.php';
require_once '../../core/Database.php';
require_once '../../core/Validator.php';

$errors = [];
$success = '';
$token = $_GET['token'] ?? '';
$tokenValid = false;
$userId = null;

// Verify token
if (!empty($token)) {
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT pr.user_id, u.email, u.full_name
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.user_id
        WHERE pr.token = ? AND pr.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $resetData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resetData) {
        $tokenValid = true;
        $userId = $resetData['user_id'];
    } else {
        $errors[] = 'Invalid or expired reset link. Please request a new password reset.';
    }
} else {
    $errors[] = 'No reset token provided.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Validation
        $validator = new Validator([
            'password' => $password,
            'confirm_password' => $confirmPassword
        ]);
        
        $validator->required('password', 'Password')
                  ->minLength('password', 8, 'Password')
                  ->password('password');
        
        $validator->required('confirm_password', 'Confirm password')
                  ->match('password', 'confirm_password', 'Passwords');

        $errors = $validator->getErrors();

        // Update password if no errors
        if (empty($errors)) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?");
            
            if ($stmt->execute([$passwordHash, $userId])) {
                // Delete used token
                $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $stmt->execute([$userId]);

                // Redirect to login with success message
                header('Location: login.php?reset=1');
                exit;
            } else {
                $errors[] = 'Failed to update password. Please try again.';
            }
        }
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../../includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <!-- Header -->
        <div class="text-center">
            <div class="mx-auto h-16 w-16 bg-green-500 rounded-full flex items-center justify-center">
                <svg class="h-10 w-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
            </div>
            <h2 class="mt-6 text-4xl font-extrabold text-gray-900">
                Reset Password
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                Enter your new password below
            </p>
        </div>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded-md">
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
            <?php if (!$tokenValid): ?>
            <div class="mt-3">
                <a href="forgot-password.php" class="text-sm font-medium text-red-700 hover:text-red-600 underline">
                    Request a new reset link
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($tokenValid): ?>
        <!-- Reset Form -->
        <form class="mt-8 space-y-6 bg-white p-8 rounded-xl shadow-lg" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="space-y-4">
                <!-- New Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                        New Password <span class="text-red-500">*</span>
                    </label>
                    <input 
                        id="password" 
                        name="password" 
                        type="password" 
                        required
                        autofocus
                        class="appearance-none relative block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 sm:text-sm"
                        placeholder="Min. 8 characters"
                    >
                    <p class="mt-1 text-xs text-gray-500">
                        Must contain: 8+ characters, uppercase, lowercase, and number
                    </p>
                </div>

                <!-- Confirm Password -->
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">
                        Confirm New Password <span class="text-red-500">*</span>
                    </label>
                    <input 
                        id="confirm_password" 
                        name="confirm_password" 
                        type="password" 
                        required
                        class="appearance-none relative block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 sm:text-sm"
                        placeholder="Re-enter new password"
                    >
                </div>
            </div>

            <!-- Password Strength Indicator -->
            <div class="bg-gray-50 border border-gray-200 rounded-md p-3">
                <p class="text-xs font-medium text-gray-700 mb-2">Password Requirements:</p>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li id="length-check" class="flex items-center">
                        <span class="mr-2">○</span> At least 8 characters
                    </li>
                    <li id="upper-check" class="flex items-center">
                        <span class="mr-2">○</span> One uppercase letter
                    </li>
                    <li id="lower-check" class="flex items-center">
                        <span class="mr-2">○</span> One lowercase letter
                    </li>
                    <li id="number-check" class="flex items-center">
                        <span class="mr-2">○</span> One number
                    </li>
                </ul>
            </div>

            <!-- Submit Button -->
            <div>
                <button 
                    type="submit" 
                    class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200"
                >
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-green-500 group-hover:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </span>
                    Reset Password
                </button>
            </div>
        </form>

        <!-- Password Strength Validation Script -->
        <script>
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            
            // Length check
            const lengthCheck = password.length >= 8;
            updateCheck('length-check', lengthCheck);
            
            // Uppercase check
            const upperCheck = /[A-Z]/.test(password);
            updateCheck('upper-check', upperCheck);
            
            // Lowercase check
            const lowerCheck = /[a-z]/.test(password);
            updateCheck('lower-check', lowerCheck);
            
            // Number check
            const numberCheck = /[0-9]/.test(password);
            updateCheck('number-check', numberCheck);
        });

        function updateCheck(id, passed) {
            const element = document.getElementById(id);
            const span = element.querySelector('span');
            
            if (passed) {
                span.textContent = '✓';
                span.classList.add('text-green-600');
                element.classList.add('text-green-600');
            } else {
                span.textContent = '○';
                span.classList.remove('text-green-600');
                element.classList.remove('text-green-600');
            }
        }
        </script>
        <?php endif; ?>

        <!-- Back to Login -->
        <div class="text-center">
            <a href="login.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 transition-colors flex items-center justify-center">
                <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Login
            </a>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>