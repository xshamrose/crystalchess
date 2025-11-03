<?php

/**
 * Header Component
 * Crystal Chess Tournament Booking Platform
 */
if (!isset($_SESSION)) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo $pageTitle ?? 'Crystal Chess - Tournament Booking Platform'; ?></title>
    <meta name="description" content="<?php echo $pageDescription ?? 'Book chess tournaments and compete with players worldwide'; ?>">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Alpine.js for interactivity -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/custom.css">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo ASSETS_URL; ?>/img/logo.png">

    <style>
        /* Custom Tailwind Configuration */
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-primary {
            @apply bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-6 rounded-lg transition duration-200 shadow-md hover:shadow-lg;
        }

        .btn-secondary {
            @apply bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-6 rounded-lg transition duration-200;
        }

        .btn-outline {
            @apply border-2 border-indigo-600 text-indigo-600 hover:bg-indigo-600 hover:text-white font-semibold py-2 px-6 rounded-lg transition duration-200;
        }

        .card {
            @apply bg-white rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300;
        }

        .input-field {
            @apply w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <?php if (isset($_GET['logged_out'])): ?>
        <div id="logout-toast"
            class="fixed top-5 right-5 z-[9999] bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-3 animate-fade-in">
            <i class="fas fa-check-circle text-white text-lg"></i>
            <span>You have been successfully logged out. See you again soon!</span>
        </div>

        <script>
            // Auto-hide after 3 seconds
            setTimeout(() => {
                const toast = document.getElementById('logout-toast');
                if (toast) toast.remove();
            }, 3000);

            // Remove the ?logged_out=1 from URL after showing once
            if (window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('logged_out');
                window.history.replaceState({}, document.title, url.pathname + url.search);
            }
        </script>

        <style>
            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .animate-fade-in {
                animation: fadeIn 0.3s ease-out;
            }
        </style>
    <?php endif; ?>

    <!-- Flash Messages -->
    <?php
    $flash = getFlash();
    if ($flash):
        $bgColor = [
            'success' => 'bg-green-100 border-green-500 text-green-800',
            'error' => 'bg-red-100 border-red-500 text-red-800',
            'warning' => 'bg-yellow-100 border-yellow-500 text-yellow-800',
            'info' => 'bg-blue-100 border-blue-500 text-blue-800'
        ][$flash['type']] ?? 'bg-gray-100 border-gray-500 text-gray-800';

        $icon = [
            'success' => 'fa-check-circle',
            'error' => 'fa-exclamation-circle',
            'warning' => 'fa-exclamation-triangle',
            'info' => 'fa-info-circle'
        ][$flash['type']] ?? 'fa-info-circle';
    ?>
        <div x-data="{ show: true }"
            x-show="show"
            x-transition
            class="fixed top-4 right-4 z-50 max-w-md">
            <div class="<?php echo $bgColor; ?> border-l-4 p-4 rounded-lg shadow-lg">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas <?php echo $icon; ?> mr-3"></i>
                        <p><?php echo htmlspecialchars($flash['message']); ?></p>
                    </div>
                    <button @click="show = false" class="ml-4 text-xl">&times;</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Navigation -->
    <?php include INCLUDES_PATH . '/nav.php'; ?>

    <!-- Main Content -->
    <main class="min-h-screen">