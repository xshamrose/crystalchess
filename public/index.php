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

// Define classes data
$classes = [
    [
        'name' => 'Chess',
        'level' => 'Beginner',
        'description' => 'Learn the fundamentals of chess, from piece movement to basic checkmates. For all levels.',
        'image' => 'chess.jpg',
        'icon' => 'fa-chess'
    ],
    [
        'name' => 'Abacus',
        'level' => 'All Levels',
        'description' => 'Master the art of mental calculation and improve concentration with the abacus.',
        'image' => 'abacus.jpg',
        'icon' => 'fa-calculator'
    ],
    [
        'name' => 'Western Dance',
        'level' => 'All Levels',
        'description' => 'Express yourself through movement with our energetic Western dance classes.',
        'image' => 'dance.jpg',
        'icon' => 'fa-music'
    ],
    [
        'name' => 'Drawing',
        'level' => 'All Levels',
        'description' => 'Unleash your creativity and learn various drawing techniques from our experienced artists.',
        'image' => 'drawing.jpg',
        'icon' => 'fa-palette'
    ],
    [
        'name' => 'Handwriting',
        'level' => 'All Levels',
        'description' => 'Improve your penmanship and develop beautiful, legible handwriting.',
        'image' => 'handwriting.jpg',
        'icon' => 'fa-pen'
    ],
    [
        'name' => 'Hindi',
        'level' => 'All Levels',
        'description' => 'Learn to speak, read, and write Hindi with our comprehensive language course.',
        'image' => 'hindi.jpg',
        'icon' => 'fa-language'
    ],
    [
        'name' => 'Karate',
        'level' => 'All Levels',
        'description' => 'Build discipline, confidence, and self-defense skills in our traditional karate class.',
        'image' => 'karate.jpg',
        'icon' => 'fa-fist-raised'
    ],
    [
        'name' => 'Keyboard',
        'level' => 'All Levels',
        'description' => 'Learn to play the keyboard, from reading music to performing your favorite songs.',
        'image' => 'keyboard.jpg',
        'icon' => 'fa-keyboard'
    ],
    [
        'name' => 'Spoken English',
        'level' => 'All Levels',
        'description' => 'Enhance your fluency, pronunciation, and confidence in speaking English.',
        'image' => 'english.jpg',
        'icon' => 'fa-comments'
    ],
    [
        'name' => 'Silambam',
        'level' => 'All Levels',
        'description' => 'Discover the ancient Tamil martial art of Silambam, focusing on staff-fighting techniques.',
        'image' => 'silambam.jpg',
        'icon' => 'fa-running'
    ],
    [
        'name' => 'Vedic Maths',
        'level' => 'All Levels',
        'description' => 'Unlock high-speed mental math techniques with our Vedic Maths course.',
        'image' => 'vedic.jpg',
        'icon' => 'fa-infinity'
    ],
    [
        'name' => 'Vocal',
        'level' => 'All Levels',
        'description' => 'Develop your singing voice and performance skills with our professional vocal coaching.',
        'image' => 'vocal.jpg',
        'icon' => 'fa-microphone'
    ]
];
?>

<!-- Statistics Section -->
<section class="bg-white">
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

                        <a href="<?php echo BASE_URL; ?>/event-details?id=<?php echo $event['event_id']; ?>"
                            class="block w-full text-center bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-lg font-semibold transition">
                            View Details <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-12">
                <a href="<?php echo BASE_URL; ?>/browse-events"
                    class="btn-outline inline-block">
                    View All Events <i class="fas fa-arrow-right ml-2"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Elevate Your Skills Section -->
<section class="py-16 bg-white">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-4xl font-bold text-gray-800 mb-4">Elevate Your Skills</h2>
            <p class="text-xl text-gray-600">Our expert-led classes cater to all ages and levels, from absolute beginners to aspiring masters.</p>
        </div>

        <!-- Free Demo Banner -->
        <div class="bg-gradient-to-r from-amber-50 to-orange-50 border-l-4 border-amber-400 rounded-lg p-6 mb-12 shadow-sm">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="h-8 w-8 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                </div>
                <div class="ml-4 flex-1">
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Free Demo Classes Available!</h3>
                    <p class="text-gray-700 mb-4">Not sure which class is right for you or your child? We offer free demo sessions for all our subjects. It's a great way to meet our instructors and experience our teaching style firsthand.</p>
                    <a href="<?php echo BASE_URL; ?>/book-demo" 
                        class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-semibold transition shadow-md">
                        Book a Free Demo
                    </a>
                </div>
            </div>
        </div>

        <!-- Classes Grid -->
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach ($classes as $class): ?>
                <div class="card p-5 hover-lift">
                    <div class="flex items-center justify-center w-full h-40 bg-gradient-to-br from-indigo-100 to-purple-100 rounded-lg mb-4">
                        <i class="fas <?php echo $class['icon']; ?> text-6xl text-indigo-600"></i>
                    </div>
                    
                    <div class="mb-3">
                        <span class="inline-block bg-indigo-100 text-indigo-700 text-xs font-semibold px-3 py-1 rounded-full">
                            <?php echo htmlspecialchars($class['level']); ?>
                        </span>
                    </div>
                    
                    <h3 class="text-xl font-bold text-gray-800 mb-2">
                        <?php echo htmlspecialchars($class['name']); ?>
                    </h3>
                    
                    <p class="text-gray-600 text-sm mb-4 line-clamp-3">
                        <?php echo htmlspecialchars($class['description']); ?>
                    </p>
                    
                    <a href="<?php echo BASE_URL; ?>/enroll?class=<?php echo urlencode(strtolower($class['name'])); ?>"
                        class="block w-full text-center bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 rounded-lg font-semibold transition text-sm">
                        Enroll Now
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- How It Works Section -->
<section class="py-16 bg-gray-50">
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
            <a href="<?php echo BASE_URL; ?>/register"
                class="bg-white text-indigo-600 px-8 py-4 rounded-lg font-bold text-lg hover:bg-gray-100 transition shadow-xl">
                Get Started Free
            </a>
            <a href="<?php echo BASE_URL; ?>/browse-events"
                class="bg-transparent border-2 border-white text-white px-8 py-4 rounded-lg font-bold text-lg hover:bg-white hover:text-indigo-600 transition">
                Browse Tournaments
            </a>
        </div>
    </div>
</section>

<?php include INCLUDES_PATH . '/footer.php'; ?>