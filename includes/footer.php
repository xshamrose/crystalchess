</main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white mt-auto">
        <div class="container mx-auto px-4 py-12">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                
                <!-- Brand & About -->
                <div class="space-y-4">
                    <div class="flex items-center space-x-2 mb-4">
                        <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-100 rounded-lg flex items-center justify-center p-2">
                            <img src="/assets/img/logo_chess.svg" alt="logo" height="25px" width="25px" />
                        </div>
                        <span class="text-xl font-bold text-white">Crystal Chess Arena</span>
                    </div>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        Empowering minds through strategic thinking. Join us for tournaments, classes in Chess, Abacus, Dance, and Drawing.
                    </p>
                </div>

                <!-- Quick Links -->
                <div>
                    <h3 class="text-lg font-semibold mb-4 text-white">Quick Links</h3>
                    <ul class="space-y-2">
                        <li>
                            <a href="<?php echo BASE_URL; ?>/" 
                               class="text-sm text-gray-400 hover:text-indigo-400 transition-colors duration-200 flex items-center">
                                <i class="fas fa-chevron-right text-xs mr-2"></i> Home
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/events" 
                               class="text-sm text-gray-400 hover:text-indigo-400 transition-colors duration-200 flex items-center">
                                <i class="fas fa-chevron-right text-xs mr-2"></i> Browse Events
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/register" 
                               class="text-sm text-gray-400 hover:text-indigo-400 transition-colors duration-200 flex items-center">
                                <i class="fas fa-chevron-right text-xs mr-2"></i> Signup
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/login" 
                               class="text-sm text-gray-400 hover:text-indigo-400 transition-colors duration-200 flex items-center">
                                <i class="fas fa-chevron-right text-xs mr-2"></i> Login
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Contact Info -->
                <div>
                    <h3 class="text-lg font-semibold mb-4 text-white">Contact Us</h3>
                    <ul class="space-y-3">

                        <li class="flex items-start">
                            <i class="fas fa-map-marker-alt text-indigo-400 mt-1 mr-3"></i>
                            <span class="text-gray-400 text-sm">No.5, Sathyamurthy road, Perungalathur, chennai - 600063</span>

                        <li class="flex items-start space-x-3">
                            <i class="fas fa-phone text-indigo-400 flex-shrink-0 mt-1"></i>
                            <div class="text-sm">
                                <a href="tel:+919884423423" class="text-gray-400 hover:text-indigo-400 transition-colors block">
                                    +91 9884423423
                                </a>
                                <a href="tel:+919787286554" class="text-gray-400 hover:text-indigo-400 transition-colors block">
                                    +91 9787286554
                                </a>
                            </div>

                        </li>
                        <li class="flex items-start space-x-3">
                            <i class="fas fa-envelope text-indigo-400 flex-shrink-0 mt-1"></i>
                            <a href="mailto:crystalschess@gmail.com" 
                               class="text-sm text-gray-400 hover:text-indigo-400 transition-colors">
                                crystalschess@gmail.com
                            </a>
                        </li>
                        <li class="flex items-start space-x-3">
                            <i class="fas fa-map-marker-alt text-indigo-400 flex-shrink-0 mt-1"></i>
                            <p class="text-sm text-gray-400 leading-relaxed">
                                No.5, Sathyamurthy Road,<br>
                                Perungalathur, Chennai<br>
                                Tamil Nadu 600063
                            </p>
                        </li>
                    </ul>
                </div>

                <!-- Connect With Us -->
                <div>
                    <h3 class="text-lg font-semibold mb-4 text-white">Connect With Us</h3>
                    
                    <!-- Social Media Icons -->
                    <div class="flex space-x-3 mb-6">
                        <a href="https://facebook.com/crystalchess" 
                           target="_blank" 
                           rel="noopener noreferrer"
                           class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-blue-600 hover:scale-110 transition-all duration-200"
                           aria-label="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://twitter.com/crystalchess" 
                           target="_blank" 
                           rel="noopener noreferrer"
                           class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-blue-400 hover:scale-110 transition-all duration-200"
                           aria-label="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="https://www.instagram.com/crystal_chess_academy" 
                           target="_blank" 
                           rel="noopener noreferrer"
                           class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-pink-600 hover:scale-110 transition-all duration-200"
                           aria-label="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                    </div>

                    <!-- WhatsApp QR Code -->
                    <div class="bg-gray-800 rounded-xl p-4 inline-block border border-gray-700 hover:border-green-500 transition-all duration-300 group">
                        <a href="https://chat.whatsapp.com/LfBgCZOIMl49DJcAyLb7PB" 
                           target="_blank" 
                           rel="noopener noreferrer"
                           class="block text-center">
                            <div class="bg-white rounded-lg p-2 mb-2 group-hover:shadow-[0_0_20px_rgba(37,211,102,0.5)] transition-all duration-300">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?data=https://chat.whatsapp.com/LfBgCZOIMl49DJcAyLb7PB&size=120x120&margin=0" 
                                     alt="Join WhatsApp Group" 
                                     class="w-24 h-24 mx-auto"
                                     loading="lazy">
                            </div>
                            <div class="flex items-center justify-center space-x-2 text-gray-400 group-hover:text-green-400 transition-colors">
                                <i class="fab fa-whatsapp text-lg"></i>
                                <span class="text-xs font-medium">Scan to Join Group</span>
                            </div>
                        </a>

