<!-- app/views/layouts/main.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#3a506b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="/assets/manifest.json">
    <link rel="apple-touch-icon" href="/assets/images/icon-192.png">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.12.0/dist/cdn.min.js"></script>
    
    <!-- Custom styles -->
    <link rel="stylesheet" href="/assets/css/styles.css">
    
    <?php if (isset($extraStyles)): ?>
        <?php foreach ($extraStyles as $style): ?>
            <link rel="stylesheet" href="<?php echo $style; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col" x-data="{ mobileMenuOpen: false }">
    <!-- Navigation Bar -->
    <header class="bg-gradient-to-br from-slate-900 via-blue-900 through-indigo-800 to-violet-900 text-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <a href="/" class="flex items-center">
                    <img src="/assets/images/logosmall.png" alt="<?php echo SITE_NAME; ?>" class="h-8 mr-2">
                    <span class="text-xl font-bold text-white"><?php echo SITE_NAME; ?></span>
                </a>
                
                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="text-white focus:outline-none">
                        <svg x-show="!mobileMenuOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                        <svg x-show="mobileMenuOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <!-- Desktop menu -->
                <nav class="hidden md:flex items-center space-x-4">
                    <a href="/" class="py-2 px-3 rounded-md hover:bg-blue-800 transition duration-150">Home</a>
                    <?php if (isLoggedIn()): ?>
                        <a href="/quiz-select" class="py-2 px-3 rounded-md hover:bg-blue-800 transition duration-150">Take Quiz</a>
                        <a href="/profile" class="py-2 px-3 rounded-md hover:bg-blue-800 transition duration-150">Profile</a>
                        
                        <?php if (isAdmin()): ?>
                            <a href="/admin" class="py-2 px-3 rounded-md bg-purple-700 hover:bg-purple-800 transition duration-150">Admin Panel</a>
                        <?php endif; ?>
                        
                        <a href="/logout" class="py-2 px-3 rounded-md hover:bg-blue-800 transition duration-150">Logout</a>
                    <?php else: ?>
                        <a href="/login" class="py-2 px-3 rounded-md hover:bg-blue-800 transition duration-150">Login</a>
                        <a href="/register" class="py-2 px-3 bg-blue-700 text-white rounded-md hover:bg-blue-600 transition duration-150">Register</a>
                    <?php endif; ?>
                </nav>
            </div>
            
            <!-- Mobile menu -->
            <div x-show="mobileMenuOpen" class="md:hidden">
                <nav class="flex flex-col py-2 space-y-1">
                    <a href="/" class="py-2 px-3 rounded-md hover:bg-blue-800 transition duration-150">Home</a>
                    <?php if (isLoggedIn()): ?>
                        <a href="/quiz-select" class="py-2 px-3 rounded-md hover:bg-blue-800 transition duration-150">Take Quiz</a>
                        <a href="/profile" class="py-2 px-3 rounded-md hover:bg-blue-800 transition duration-150">Profile</a>
                        
                        <?php if (isAdmin()): ?>
                            <a href="/admin" class="py-2 px-3 rounded-md bg-purple-700 hover:bg-purple-800 transition duration-150">Admin Panel</a>
                        <?php endif; ?>
                        
                        <a href="/logout" class="py-2 px-3 rounded-md hover:bg-blue-800 transition duration-150">Logout</a>
                    <?php else: ?>
                        <a href="/login" class="py-2 px-3 rounded-md hover:bg-blue-800 transition duration-150">Login</a>
                        <a href="/register" class="py-2 px-3 bg-blue-700 text-white rounded-md hover:bg-blue-600 transition duration-150">Register</a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 py-6">
        <?php if (isset($flash) && $flash): ?>
            <div class="mb-6 p-4 rounded-md <?php echo $flash['type'] === 'success' ? 'bg-green-100 text-green-700 border-l-4 border-green-500' : 'bg-red-100 text-red-700 border-l-4 border-red-500'; ?>">
                <?php echo $flash['message']; ?>
            </div>
        <?php endif; ?>
        
        <?php echo $content; ?>
    </main>
    
    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-6">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
                </div>
                <div class="flex space-x-4">
                    <a href="/about" class="hover:text-blue-300 transition duration-150">About</a>
                    <a href="/privacy" class="hover:text-blue-300 transition duration-150">Privacy</a>
                    <a href="mailto:<?php echo FROM_EMAIL; ?>" class="hover:text-blue-300 transition duration-150">Contact</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Custom JS -->
    <?php if (isset($extraScripts)): ?>
        <?php foreach ($extraScripts as $script): ?>
            <script src="<?php echo $script; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>