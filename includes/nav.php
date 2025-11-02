<?php
/**
 * Navigation Component
 * File: includes/nav.php
 */
// Always start session safely (only if not already started)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear stale user data if session was destroyed
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_type'] = 'guest';
    $_SESSION['user_name'] = 'User';
}

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$userType = $_SESSION['user_type'] ?? 'guest';
$userName = $_SESSION['user_name'] ?? 'User';
?>

<nav class="bg-white shadow-lg sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Logo -->
            <div class="flex items-center">
                <a href="<?php echo BASE_URL; ?>" class="flex items-center space-x-2">
                    <img src="/assets/img/logo_chess.svg" alt="logo" height="25px" width="25px" />
                    <span class="text-xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">
                        Crystal Chess
                    </span>
                </a>
            </div>

            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center space-x-4">
                <!-- ✅ FIXED: Changed from /events to /browse-events -->
                <a href="<?php echo BASE_URL; ?>/browse-events" 
                   class="text-gray-700 hover:text-purple-600 px-3 py-2 rounded-md font-medium transition">
                    Browse Events
                </a>

                <?php if ($userType === 'guest'): ?>
                    <a href="<?php echo BASE_URL; ?>/login" 
                       class="text-gray-700 hover:text-purple-600 px-3 py-2 rounded-md font-medium transition">
                        Login
                    </a>
                    <a href="<?php echo BASE_URL; ?>/register" 
                       class="bg-gradient-to-r from-purple-600 to-pink-600 text-white px-6 py-2 rounded-lg font-medium hover:shadow-lg transition">
                        Sign Up
                    </a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/dashboard" 
                       class="text-gray-700 hover:text-purple-600 px-3 py-2 rounded-md font-medium transition">
                        Dashboard
                    </a>

                    <?php if ($userType === 'organizer'): ?>
                        <a href="<?php echo BASE_URL; ?>/organizer-dashboard" 
                           class="text-gray-700 hover:text-purple-600 px-3 py-2 rounded-md font-medium transition">
                            Organizer Panel
                        </a>
                    <?php endif; ?>

                    <?php if ($userType === 'admin'): ?>
                        <div class="relative group">
                            <button class="text-gray-700 hover:text-purple-600 px-3 py-2 rounded-md font-medium transition flex items-center">
                                Admin
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 hidden group-focus-within:block">
                                <a href="<?php echo BASE_URL; ?>/admin-dashboard" 
                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-purple-50">
                                    📊 Dashboard
                                </a>
                                <a href="<?php echo BASE_URL; ?>/admin-users" 
                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-purple-50">
                                    👥 Users
                                </a>
                                <a href="<?php echo BASE_URL; ?>/admin-events" 
                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-purple-50">
                                    🏆 Events
                                </a>
                                <a href="<?php echo BASE_URL; ?>/admin-bookings" 
                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-purple-50">
                                    📋 Bookings
                                </a>
                                <a href="<?php echo BASE_URL; ?>/admin-payment-reports" 
                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-purple-50">
                                    💰 Payment Reports
                                </a>
                                <a href="<?php echo BASE_URL; ?>/admin-settings" 
                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-purple-50">
                                    ⚙️ Settings
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="relative group">
                        <button class="flex items-center space-x-2 text-gray-700 hover:text-purple-600 px-3 py-2 rounded-md font-medium transition">
                            <span><?php echo htmlspecialchars($userName); ?></span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 hidden group-focus-within:block">
                            <a href="<?php echo BASE_URL; ?>/profile" 
                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-purple-50">
                                👤 Profile
                            </a>
                            <a href="<?php echo BASE_URL; ?>/booking-history" 
                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-purple-50">
                                📖 Booking History
                            </a>
                            <hr class="my-1">
                            <a href="<?php echo BASE_URL; ?>/logout" 
                               class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                🚪 Logout
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Mobile menu button -->
            <div class="md:hidden flex items-center">
                <button onclick="toggleMobileMenu()" class="text-gray-700 hover:text-purple-600 focus:outline-none">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile menu -->
    <div id="mobileMenu" class="hidden md:hidden bg-white border-t border-gray-200">
        <div class="px-2 pt-2 pb-3 space-y-1">
            <!-- ✅ FIXED: Changed from /events to /browse-events -->
            <a href="<?php echo BASE_URL; ?>/browse-events" 
               class="block px-3 py-2 rounded-md text-gray-700 hover:bg-purple-50 hover:text-purple-600">
                Browse Events
            </a>

            <?php if ($userType === 'guest'): ?>
                <a href="<?php echo BASE_URL; ?>/login" 
                   class="block px-3 py-2 rounded-md text-gray-700 hover:bg-purple-50 hover:text-purple-600">
                    Login
                </a>
                <a href="<?php echo BASE_URL; ?>/register" 
                   class="block px-3 py-2 rounded-md text-gray-700 hover:bg-purple-50 hover:text-purple-600">
                    Sign Up
                </a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>/dashboard" 
                   class="block px-3 py-2 rounded-md text-gray-700 hover:bg-purple-50 hover:text-purple-600">
                    Dashboard
                </a>
                <a href="<?php echo BASE_URL; ?>/profile" 
                   class="block px-3 py-2 rounded-md text-gray-700 hover:bg-purple-50 hover:text-purple-600">
                    Profile
                </a>
                <a href="<?php echo BASE_URL; ?>/booking-history" 
                   class="block px-3 py-2 rounded-md text-gray-700 hover:bg-purple-50 hover:text-purple-600">
                    Booking History
                </a>

                <?php if ($userType === 'organizer'): ?>
                    <a href="<?php echo BASE_URL; ?>/organizer-dashboard" 
                       class="block px-3 py-2 rounded-md text-gray-700 hover:bg-purple-50 hover:text-purple-600">
                        Organizer Panel
                    </a>
                <?php endif; ?>

                <?php if ($userType === 'admin'): ?>
                    <div class="border-t border-gray-200 mt-2 pt-2">
                        <p class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase">Admin</p>
                        <a href="<?php echo BASE_URL; ?>/admin-dashboard" 
                           class="block px-3 py-2 rounded-md text-gray-700 hover:bg-purple-50 hover:text-purple-600">
                            📊 Dashboard
                        </a>
                        <a href="<?php echo BASE_URL; ?>/admin-users" 
                           class="block px-3 py-2 rounded-md text-gray-700 hover:bg-purple-50 hover:text-purple-600">
                            👥 Users
                        </a>
                        <a href="<?php echo BASE_URL; ?>/admin-events" 
                           class="block px-3 py-2 rounded-md text-gray-700 hover:bg-purple-50 hover:text-purple-600">
                            🏆 Events
                        </a>
                        <a href="<?php echo BASE_URL; ?>/admin-bookings" 
                           class="block px-3 py-2 rounded-md text-gray-700 hover:bg-purple-50 hover:text-purple-600">
                            📋 Bookings
                        </a>
                        <a href="<?php echo BASE_URL; ?>/admin-payment-reports" 
                           class="block px-3 py-2 rounded-md text-gray-700 hover:bg-purple-50 hover:text-purple-600">
                            💰 Payment Reports
                        </a>
                        <a href="<?php echo BASE_URL; ?>/admin-settings" 
                           class="block px-3 py-2 rounded-md text-gray-700 hover:bg-purple-50 hover:text-purple-600">
                            ⚙️ Settings
                        </a>
                    </div>
                <?php endif; ?>

                <div class="border-t border-gray-200 mt-2 pt-2">
                    <a href="<?php echo BASE_URL; ?>/logout" 
                       class="block px-3 py-2 rounded-md text-red-600 hover:bg-red-50">
                        🚪 Logout
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<script>
function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    menu.classList.toggle('hidden');
}
</script>