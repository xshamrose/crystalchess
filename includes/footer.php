</main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white ">
        <div class="container mx-auto px-4 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                
                <!-- Brand -->
                <div class="col-span-1">
                    <div class="flex items-center space-x-2 mb-4">
                        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 p-2 rounded-lg">
                            <i class="fas fa-chess-king text-white text-xl"></i>
                        </div>
                        <span class="text-xl font-bold">Crystal Chess</span>
                    </div>
                    <p class="text-gray-400 text-sm mb-4">
                        Your premier platform for chess tournament bookings. Connect with players and compete worldwide.
                    </p>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-indigo-400 transition">
                            <i class="fab fa-facebook-f text-xl"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-indigo-400 transition">
                            <i class="fab fa-twitter text-xl"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-indigo-400 transition">
                            <i class="fab fa-instagram text-xl"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-indigo-400 transition">
                            <i class="fab fa-linkedin-in text-xl"></i>
                        </a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div>
                    <h3 class="text-lg font-semibold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li>
                            <a href="<?php echo BASE_URL; ?>/" 
                               class="text-gray-400 hover:text-white transition">
                                <i class="fas fa-chevron-right text-xs mr-2"></i> Home
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/events" 
                               class="text-gray-400 hover:text-white transition">
                                <i class="fas fa-chevron-right text-xs mr-2"></i> Browse Events
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/pages/about.php" 
                               class="text-gray-400 hover:text-white transition">
                                <i class="fas fa-chevron-right text-xs mr-2"></i> About Us
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/pages/how-it-works.php" 
                               class="text-gray-400 hover:text-white transition">
                                <i class="fas fa-chevron-right text-xs mr-2"></i> How It Works
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- For Organizers -->
                <div>
                    <h3 class="text-lg font-semibold mb-4">For Organizers</h3>
                    <ul class="space-y-2">
                        <li>
                            <a href="<?php echo BASE_URL; ?>/create-event" 
                               class="text-gray-400 hover:text-white transition">
                                <i class="fas fa-chevron-right text-xs mr-2"></i> Create Event
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/pages/organizer-guide.php" 
                               class="text-gray-400 hover:text-white transition">
                                <i class="fas fa-chevron-right text-xs mr-2"></i> Organizer Guide
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/pages/pricing.php" 
                               class="text-gray-400 hover:text-white transition">
                                <i class="fas fa-chevron-right text-xs mr-2"></i> Pricing
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/pages/support.php" 
                               class="text-gray-400 hover:text-white transition">
                                <i class="fas fa-chevron-right text-xs mr-2"></i> Support
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Contact -->
                <div>
                    <h3 class="text-lg font-semibold mb-4">Contact Us</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <i class="fas fa-map-marker-alt text-indigo-400 mt-1 mr-3"></i>
                            <span class="text-gray-400 text-sm">123 Chess Street, New York, NY 10001</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-phone text-indigo-400 mr-3"></i>
                            <a href="tel:<?php echo SITE_PHONE; ?>" 
                               class="text-gray-400 hover:text-white transition text-sm">
                                <?php echo SITE_PHONE; ?>
                            </a>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-envelope text-indigo-400 mr-3"></i>
                            <a href="mailto:<?php echo SITE_EMAIL; ?>" 
                               class="text-gray-400 hover:text-white transition text-sm">
                                <?php echo SITE_EMAIL; ?>
                            </a>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-clock text-indigo-400 mr-3"></i>
                            <span class="text-gray-400 text-sm">Mon - Fri: 9:00 AM - 6:00 PM</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Bottom Bar -->
            <div class="border-t border-gray-800 mt-8 pt-8">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <p class="text-gray-400 text-sm mb-4 md:mb-0">
                        &copy; <?php echo date('Y'); ?> Crystal Chess. All rights reserved.
                    </p>
                    <div class="flex space-x-6">
                        <a href="<?php echo BASE_URL; ?>/pages/privacy.php" 
                           class="text-gray-400 hover:text-white text-sm transition">
                            Privacy Policy
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pages/terms.php" 
                           class="text-gray-400 hover:text-white text-sm transition">
                            Terms of Service
                        </a>
                        <a href="<?php echo BASE_URL; ?>/pages/refund.php" 
                           class="text-gray-400 hover:text-white text-sm transition">
                            Refund Policy
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Custom JavaScript -->
    <script src="<?php echo ASSETS_URL; ?>/js/app.js"></script>
    
    <!-- Page-specific scripts -->
    <?php if (isset($pageScripts)) echo $pageScripts; ?>
</body>
</html>