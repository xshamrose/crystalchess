<?php
require_once '../../config/config.php';
require_once '../../core/Database.php';

$success = false;
$error = '';
$token = $_GET['token'] ?? '';

if (!empty($token)) {
    $db = Database::getInstance();
    
    // Find the verification token
    $stmt = $db->prepare("
        SELECT pr.user_id, u.email, u.full_name, u.email_verified
        FROM password_resets pr
        JOIN users u ON pr.user_id = u.user_id
        WHERE pr.token = ? AND pr.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $verificationData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($verificationData) {
        // Check if already verified
        if ($verificationData['email_verified']) {
            $error = 'Email already verified. You can login now.';
        } else {
            // Verify the email
            $stmt = $db->prepare("UPDATE users SET email_verified = 1, updated_at = NOW() WHERE user_id = ?");
            
            if ($stmt->execute([$verificationData['user_id']])) {
                // Delete the verification token
                $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $stmt->execute([$verificationData['user_id']]);
                
                $success = true;
            } else {
                $error = 'Failed to verify email. Please try again.';
            }
        }
    } else {
        $error = 'Invalid or expired verification link. Please request a new verification email.';
    }
} else {
    $error = 'No verification token provided.';
}

include '../../includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <?php if ($success): ?>
        <!-- Success Message -->
        <div class="text-center">
            <div class="mx-auto h-20 w-20 bg-green-500 rounded-full flex items-center justify-center animate-bounce">
                <svg class="h-12 w-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h2 class="mt-6 text-4xl font-extrabold text-gray-900">
                Email Verified! 🎉
            </h2>
            <p class="mt-2 text-lg text-gray-600">
                Your account has been successfully verified
            </p>
        </div>

        <div class="bg-white p-8 rounded-xl shadow-lg space-y-6">
            <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded-md">
                <div class="flex">
                    <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800">
                            What's Next?
                        </h3>
                        <div class="mt-2 text-sm text-green-700">
                            <ul class="list-disc list-inside space-y-1">
                                <li>Login to your account</li>
                                <li>Complete your profile</li>
                                <li>Start browsing tournaments</li>
                                <li>Book your first event!</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Continue Button -->
            <div>
                <a 
                    href="login.php?verified=1" 
                    class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200"
                >
                    <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                        <svg class="h-5 w-5 text-green-500 group-hover:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                        </svg>
                    </span>
                    Continue to Login
                </a>
            </div>
        </div>

        <?php else: ?>
        <!-- Error Message -->
        <div class="text-center">
            <div class="mx-auto h-20 w-20 bg-red-500 rounded-full flex items-center justify-center">
                <svg class="h-12 w-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            <h2 class="mt-6 text-4xl font-extrabold text-gray-900">
                Verification Failed
            </h2>
            <p class="mt-2 text-lg text-gray-600">
                We couldn't verify your email
            </p>
        </div>

        <div class="bg-white p-8 rounded-xl shadow-lg space-y-6">
            <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded-md">
                <div class="flex">
                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <p class="ml-3 text-sm text-red-700">
                        <?php echo htmlspecialchars($error); ?>
                    </p>
                </div>
            </div>

            <div class="space-y-3">
                <!-- Resend Verification -->
                <a 
                    href="resend-verification.php" 
                    class="w-full flex justify-center py-3 px-4 border border-indigo-600 text-sm font-medium rounded-md text-indigo-600 bg-white hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200"
                >
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    Resend Verification Email
                </a>

                <!-- Back to Login -->
                <a 
                    href="login.php" 
                    class="w-full flex justify-center py-3 px-4 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-200"
                >
                    Back to Login
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Help Section -->
        <div class="bg-blue-50 border border-blue-200 rounded-md p-4 text-sm">
            <p class="font-semibold text-blue-800 mb-2">Need Help?</p>
            <p class="text-blue-700 text-xs">
                If you continue to experience issues, please contact support at 
                <a href="mailto:<?php echo SUPPORT_EMAIL; ?>" class="font-medium underline">
                    <?php echo SUPPORT_EMAIL; ?>
                </a>
            </p>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>