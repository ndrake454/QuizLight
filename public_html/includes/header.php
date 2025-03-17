<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo $site_name; ?></title>
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#3a506b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/images/icon-192.png">
    
    <!-- Tailwind CSS from CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Alpine.js from CDN -->
    <script defer src="https://unpkg.com/alpinejs@3.12.0/dist/cdn.min.js"></script>
    
    <!-- Custom styles -->
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col" 
      x-data="{ mobileMenuOpen: false }">
    
    <!-- Navigation Bar -->
    
<header class="bg-gradient-to-br from-slate-900 via-blue-900 through-indigo-800 to-violet-900 text-white shadow-lg sticky top-0 z-50" x-data="{ mobileMenuOpen: false }">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <a href="/" class="flex items-center">
                    <img src="/images/logosmall.png" alt="Firelight Academy" class="h-8 mr-2">
                    <span class="text-xl font-bold text-white"><?php echo $site_name; ?></span>
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
                <nav class="hidden md:flex items-center space-x-4"><!-- Notification Bell in Desktop Menu -->
                        <div class="relative">
                            <a href="/notifications.php" class="relative py-2 px-3 rounded-md hover:bg-blue-800 transition duration-150 inline-flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                <span></span>
                                
                                <?php
                                // Count unread notifications
                                $notifCount = 0;
                                if (isLoggedIn()) {
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    $notifCount = (int)$stmt->fetchColumn();
                                }
                                ?>
                                
                                <?php if ($notifCount > 0): ?>
                                    <span class="absolute top-0 right-0 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full">
                                        <?php echo $notifCount; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </div>
                    <a href="/" class="py-2 px-3 rounded-md hover:bg-blue-800 transition duration-150">Home</a>
                    <?php if (isLoggedIn()): ?>
                        <a href="/quiz_select.php" class="py-2 px-3 rounded-md hover:bg-blue-800 transition duration-150">Take Quiz</a>
                        <a href="/profile.php" class="py-2 px-3 rounded-md hover:bg-blue-800 transition duration-150">Profile</a>                        
                        <?php if (isAdmin()): ?>
                            <a href="/admin/" class="py-2 px-3 rounded-md bg-purple-700 hover:bg-purple-800 transition duration-150">Admin Panel</a>
                        <?php endif; ?>
                        
                        <a href="/logout.php" class="py-2 px-3 rounded-md hover:bg-blue-800 transition duration-150">Logout</a>
                    <?php else: ?>
                        <a href="/login.php" class="py-2 px-3 rounded-md hover:bg-blue-800 transition duration-150">Login</a>
                        <a href="/register.php" class="py-2 px-3 bg-blue-700 text-white rounded-md hover:bg-blue-600 transition duration-150">Register</a>
                    <?php endif; ?>
                </nav>
            </div>
            
            <!-- Mobile menu -->
            <div x-show="mobileMenuOpen" class="md:hidden">
                <nav class="flex flex-col py-2 space-y-1">
                    <a href="/" class="py-2 px-3 rounded-md hover:bg-blue-800 transition duration-150">Home</a>
                    <?php if (isLoggedIn()): ?>
                        <a href="/quiz_select.php" class="py-2 px-3 rounded-md hover:bg-blue-800 transition duration-150">Take Quiz</a>
                        <a href="/profile.php" class="py-2 px-3 rounded-md hover:bg-blue-800 transition duration-150">Profile</a>
                        
                        <!-- Notification Bell in Mobile Menu -->
                        <a href="/notifications.php" class="py-2 px-3 rounded-md hover:bg-blue-800 transition duration-150 flex items-center justify-between">
                            <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                <span></span>
                            </div>
                            
                            <?php if ($notifCount > 0): ?>
                                <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full">
                                    <?php echo $notifCount; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        
                        <?php if (isAdmin()): ?>
                            <a href="/admin/" class="py-2 px-3 rounded-md bg-purple-700 hover:bg-purple-800 transition duration-150">Admin Panel</a>
                        <?php endif; ?>
                        
                        <a href="/logout.php" class="py-2 px-3 rounded-md hover:bg-blue-800 transition duration-150">Logout</a>
                    <?php else: ?>
                        <a href="/login.php" class="py-2 px-3 rounded-md hover:bg-blue-800 transition duration-150">Login</a>
                        <a href="/register.php" class="py-2 px-3 bg-blue-700 text-white rounded-md hover:bg-blue-600 transition duration-150">Register</a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 py-6">