<?php
/**
 * Main Entry Point & Router
 * Crystal Chess Tournament Booking Platform
 */

require_once __DIR__ . '/config/config.php';

// Get the requested URI
$request_uri = $_SERVER['REQUEST_URI'];

// Parse URI - remove leading slash and query string
$uri = parse_url($request_uri, PHP_URL_PATH);
$uri = trim($uri, '/');

// Remove query string
$uri = strtok($uri, '?');

// Remove any base directory from URI (if hosted in subdirectory)
$script_name = dirname($_SERVER['SCRIPT_NAME']);
if ($script_name !== '/') {
    $uri = str_replace(trim($script_name, '/') . '/', '', $uri);
}

// Define all routes
$routes = [
    // Home
    '' => 'public/index.php',
    'home' => 'public/index.php',

    // User Authentication
    'login' => '/modules/user/login.php',
    'register' => '/modules/user/register.php',
    'logout' => 'api/auth/logout.php',
    'forgot-password' => 'modules/user/forgot-password.php',
    'reset-password' => 'modules/user/reset-password.php',
    'verify-email' => 'modules/user/verify-email.php',
    'resend-verification' => 'modules/user/resend-verification.php',

    // User Dashboard
    'dashboard' => 'modules/user/dashboard.php',
    'profile' => 'modules/user/profile.php',
    'booking-history' => 'modules/user/booking-history.php',

    // Events
    'events' => 'modules/events/browse.php',
    'browse' => 'modules/events/browse.php',
    'browse-events' => 'modules/events/browse.php',
    'event-details' => 'modules/events/details.php',
    'details' => 'modules/events/details.php',
    'book' => 'modules/events/book.php',
    'checkout' => 'modules/events/checkout.php',
    'booking-confirmation' => 'modules/events/booking-confirmation.php',

    // Organizer
    'organizer' => 'modules/organizer/dashboard.php',
    'organizer-dashboard' => 'modules/organizer/dashboard.php',
    'create-event' => 'modules/organizer/create-event.php',
    'manage-events' => 'modules/organizer/manage-events.php',
    'participants' => 'modules/organizer/participants.php',
    'notifications' => 'modules/organizer/notifications.php',

    // Admin
    'admin' => 'modules/admin/dashboard.php',
    'admin-dashboard' => 'modules/admin/dashboard.php',
    'admin-users' => 'modules/admin/users.php',
    'admin-events' => 'modules/admin/events.php',
    'admin-bookings' => 'modules/admin/bookings.php',
    'admin-payments' => 'modules/admin/payments.php',
    'admin-payment-reports' => 'modules/admin/payment-reports.php',
    'admin-reports' => 'modules/admin/reports.php',
    'admin-settings' => 'modules/admin/settings.php',
    'audit-logs' => 'modules/admin/audit-logs.php',
];

// Check if route exists
if (isset($routes[$uri])) {
    $file = __DIR__ . '/' . $routes[$uri];
    
    if (file_exists($file)) {
        require_once $file;
        exit;
    } else {
        http_response_code(500);
        die("Error: Route file not found: " . htmlspecialchars($routes[$uri]));
    }
} else {
    // Check if it's a static file request (assets, uploads)
    if (preg_match('/\.(css|js|jpg|jpeg|png|gif|svg|ico|woff|woff2|ttf|eot)$/i', $uri)) {
        return false; // Let server handle static files
    }

    // 404 - Route not found
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>404 - Page Not Found | Crystal Chess</title>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    </head>
    <body class="bg-gray-50">
        <div class="min-h-screen flex items-center justify-center px-4">
            <div class="text-center">
                <h1 class="text-9xl font-bold text-indigo-600">404</h1>
                <h2 class="text-4xl font-bold text-gray-800 mb-4">Page Not Found</h2>
                <p class="text-xl text-gray-600 mb-8">
                    The page <code class="bg-gray-200 px-2 py-1 rounded"><?php echo htmlspecialchars($uri); ?></code> does not exist.
                </p>
                <a href="<?php echo BASE_URL; ?>" 
                   class="inline-block bg-indigo-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-indigo-700 transition">
                    ← Back to Home
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>