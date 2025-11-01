<?php
/**
 * Homepage
 * Crystal Chess Tournament Booking Platform
 */

require_once __DIR__ . '/../config/config.php';

$pageTitle = 'Crystal Chess - Book Chess Tournaments Worldwide';
$pageDescription = 'Find and book chess tournaments near you. Connect with players, compete, and improve your game.';

include INCLUDES_PATH . '/header.php';

// Get featured events
$db = Database::getInstance();
$featuredEvents = $db->query("
    SELECT e.*, u.full_name as organizer_name,
           (e.max_capacity - e.current_bookings) as available_slots
    FROM events e
    JOIN users u ON e.organizer_id = u.user_id
    WHERE e.event_status = 'upcoming' 
    AND e.event_date >= CURDATE()
    AND e.featured = 1
    ORDER BY e.event_date ASC
    LIMIT 6
")->fetchAll();

// Get statistics
$totalEvents = $db->count('events', ['event_status' => 'upcoming']);
$totalUsers = $db->count('users', ['user_status' => 'active']);
$totalBookings = $db->count('bookings', ['booking_status' => 'confirmed']);
?>

<!-- Statistics Section -->
<section class=" bg-white">
    <div class="container mx-auto px-4">
         <!-- Logout Success Message -->
        <?php if (isset($_GET['logged_out'])): ?>
            <div class="max-w-2xl mx-auto mb-8">
                <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded-md">
                    <div class="flex items-center">
                        <svg class="h-5 w-5 text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        <p class="text-sm font-medium text-green-700">
                            ✓ You have been successfully logged out. See you again soon!
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <!-- <div class="grid md:grid-cols-3 gap-8">
            <div class="stats-card text-center hover-lift">
                <div class="stats-icon bg-indigo-100 text-indigo-600 mx-auto">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h3 class="text-4xl font-bold text-gray-800 mb-2"><?php echo number_format($totalEvents); ?>+</h3>
                <p class="text-gray-600 font-medium">Active Tournaments</p>
            </div>

            <div class="stats-card text-center hover-lift">
                <div class="stats-icon bg-purple-100 text-purple-600 mx-auto">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="text-4xl font-bold text-gray-800 mb-2"><?php echo number_format($totalUsers); ?>+</h3>
                <p class="text-gray-600 font-medium">Active Players</p>
            </div>

            <div class="stats-card text-center hover-lift">
                <div class="stats-icon bg-pink-100 text-pink-600 mx-auto">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <h3 class="text-4xl font-bold text-gray-800 mb-2"><?php echo number_format($totalBookings); ?>+</h3>
                <p class="text-gray-600 font-medium">Bookings Made</p>
            </div>
        </div> -->
    </div>
</section>

<!-- Featured Events Section -->
<section class="py-16 bg-gray-50">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-4xl font-bold text-gray-800 mb-4">Featured Tournaments</h2>
            <p class="text-xl text-gray-600">Don't miss these exciting upcoming events</p>
        </div>

        <?php if (empty($featuredEvents)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <p class="text-xl">No featured events at the moment. Check back soon!</p>
            </div>
        <?php else: ?>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($featuredEvents as $event): ?>
                    <div class="card p-6 hover-lift event-card">
                        <?php 
                        // Use SVG placeholders
                        $svgImages = ['tournament-1.svg', 'tournament-2.svg', 'tournament-3.svg'];
                        $randomSvg = $svgImages[array_rand($svgImages)];
                        
                        if (!empty($event['event_image']) && file_exists(UPLOADS_PATH . '/events/' . $event['event_image'])): 
                        ?>
                            <img src="<?php echo UPLOADS_URL . '/events/' . $event['event_image']; ?>"
                                alt="<?php echo htmlspecialchars($event['event_name']); ?>"
                                class="w-full h-48 object-cover rounded-lg mb-4">
                        <?php else: ?>
                            <img src="<?php echo ASSETS_URL . '/img/' . $randomSvg; ?>"
                                alt="<?php echo htmlspecialchars($event['event_name']); ?>"
                                class="w-full h-48 object-cover rounded-lg mb-4">
                        <?php endif; ?>

                        <h3 class="text-xl font-bold text-gray-800 mb-2">
                            <?php echo htmlspecialchars($event['event_name']); ?>
                        </h3>

                        <div class="space-y-2 mb-4 text-gray-600">
                            <p class="flex items-center">
                                <i class="fas fa-calendar text-indigo-500 w-5"></i>
                                <span class="ml-2"><?php echo formatDate($event['event_date']); ?></span>
                            </p>
                            <p class="flex items-center">
                                <i class="fas fa-clock text-indigo-500 w-5"></i>
                                <span class="ml-2"><?php echo date('g:i A', strtotime($event['event_time'])); ?></span>
                            </p>
                            <p class="flex items-center">
                                <i class="fas fa-map-marker-alt text-indigo-500 w-5"></i>
                                <span class="ml-2"><?php echo htmlspecialchars($event['location']); ?></span>
                            </p>
                            <p class="flex items-center">
                                <i class="fas fa-user text-indigo-500 w-5"></i>
                                <span class="ml-2"><?php echo htmlspecialchars($event['organizer_name']); ?></span>
                            </p>
                        </div>

                        <div class="flex justify-between items-center mb-4">
                            <span class="text-2xl font-bold text-indigo-600">
                                <?php echo formatCurrency($event['entry_fee']); ?>
                            </span>
                            <span class="badge badge-info">
                                <?php echo $event['available_slots']; ?> slots left
                            </span>
                        </div>

                        <!-- ✅ FIXED: Use clean URL -->
                        <a href="<?php echo BASE_URL; ?>/event-details?id=<?php echo $event['event_id']; ?>"
                            class="block w-full text-center bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-lg font-semibold transition">
                            View Details <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-12">
                <!-- ✅ FIXED: Use clean URL -->
                <a href="<?php echo BASE_URL; ?>/browse-events"
                    class="btn-outline inline-block">
                    View All Events <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- How It Works Section -->
<section class="py-16 bg-white">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-4xl font-bold text-gray-800 mb-4">How It Works</h2>
            <p class="text-xl text-gray-600">Get started in three simple steps</p>
        </div>

        <div class="grid md:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="w-20 h-20 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <span class="text-3xl font-bold text-indigo-600">1</span>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-4">Browse Events</h3>
                <p class="text-gray-600">Explore tournaments happening near you or worldwide. Filter by date, location, and skill level.</p>
            </div>

            <div class="text-center">
                <div class="w-20 h-20 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <span class="text-3xl font-bold text-purple-600">2</span>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-4">Book Your Spot</h3>
                <p class="text-gray-600">Register for tournaments with just a few clicks. Secure payment and instant confirmation.</p>
            </div>

            <div class="text-center">
                <div class="w-20 h-20 bg-pink-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <span class="text-3xl font-bold text-pink-600">3</span>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-4">Compete & Win</h3>
                <p class="text-gray-600">Show up and play! Track your progress, earn ratings, and build your chess legacy.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-16 gradient-bg text-white">
    <div class="container mx-auto px-4 text-center">
        <h2 class="text-4xl font-bold mb-6">Ready to Start Your Chess Journey?</h2>
        <p class="text-xl mb-8 text-gray-100">Join thousands of players competing worldwide</p>
        <div class="flex flex-wrap justify-center gap-4">
            <!-- ✅ FIXED: Use clean URL -->
            <a href="<?php echo BASE_URL; ?>/register"
                class="bg-white text-indigo-600 px-8 py-4 rounded-lg font-bold text-lg hover:bg-gray-100 transition shadow-xl">
                Get Started Free
            </a>
            <!-- ✅ FIXED: Use clean URL -->
            <a href="<?php echo BASE_URL; ?>/browse-events"
                class="bg-transparent border-2 border-white text-white px-8 py-4 rounded-lg font-bold text-lg hover:bg-white hover:text-indigo-600 transition">
                Browse Tournaments
            </a>
        </div>
    </div>
</section>

<?php include INCLUDES_PATH . '/footer.php'; ?>