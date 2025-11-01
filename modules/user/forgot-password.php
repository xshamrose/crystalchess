<?php
require_once '../../config/config.php';
require_once '../../core/Database.php';
require_once '../../core/Validator.php';
require_once '../../core/Mailer.php';

$errors = [];
$success = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');

        // Validation
        $validator = new Validator(['email' => $email]);
        $validator->required('email', 'Email')->email('email');
        // $errors = $validator->getErrors();

        if (empty($errors)) {
            // $db = Database::getInstance();

            // Check if user exists
            $stmt = $db->prepare("SELECT user_id, full_name, email FROM users WHERE email = ? AND user_status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Generate reset token
                $token = bin2hex(random_bytes(32));

                // Delete old tokens for this user
                $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $stmt->execute([$user['user_id']]);

                // Insert new token (expires in 1 hour)
                $stmt = $db->prepare("
                    INSERT INTO password_resets (user_id, token, expires_at) 
                    VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
                ");
                $stmt->execute([$user['user_id'], $token]);

                // Send reset email
                $resetLink = SITE_URL . "/modules/user/reset-password.php?token=" . $token;

                $mailer = new Mailer();
                $emailSent = $mailer->sendTemplate(
                    $user['email'],
                    'password_reset',
                    [
                        'user_name' => $user['full_name'],
                        'reset_link' => $resetLink
                    ]
                );

                if ($emailSent) {
                    $success = 'Password reset instructions have been sent to your email address.';
                    $email = ''; // Clear email field
                } else {
                    $errors[] = 'Failed to send reset email. Please try again.';
                }
            } else {
                // For security, show success message even if email doesn't exist
                // This prevents email enumeration attacks
                $success = 'If that email exists in our system, password reset instructions have been sent.';
                $email = '';
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
            <div class="mx-auto h-16 w-16 bg-yellow-500 rounded-full flex items-center justify-center">
                <svg class="h-10 w-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
            </div>
            <h2 class="mt-6 text-4xl font-extrabold text-gray-900">
                Forgot Password?
            </h2>
            <p class="mt-2 text-sm text-gray-600">
                No worries! Enter your email and we'll send you reset instructions.
            </p>
        </div>

        <!-- Success Message -->
        <?php if ($success): ?>
            <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded-md">
                <div class="flex">
                    <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
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

        <!-- Reset Form -->
        <form class="mt-8 space-y-6 bg-white p-8 rounded-xl shadow-lg" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

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
                    class="appearance-none relative block w-full px-3 py-2 border border-gray-300 rounded-md placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 sm:text-sm"
                    placeholder="you@example.com">
                <p class="mt-2 text-xs text-gray-500">
                    Enter the email address associated with your account
                </p>
            </div>

            <!-- Submit Button -->
            <div>
                <button
                    type="submit"
                    class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-yellow-500 hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition-colors duration-200">
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-yellow-400 group-hover:text-yellow-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </span>
                    Send Reset Instructions
                </button>
            </div>

            <!-- Back to Login -->
            <div class="text-center">
                <a href="login.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-500 transition-colors flex items-center justify-center">
                    <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Login
                </a>
            </div>
        </form>

        <!-- Help Text -->
        <div class="bg-blue-50 border border-blue-200 rounded-md p-4 text-sm text-blue-700">
            <p class="font-semibold mb-1">📧 Check your email</p>
            <p class="text-xs">If your email exists in our system, you'll receive reset instructions within a few minutes. The link expires in 1 hour.</p>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>