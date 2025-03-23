<!-- app/views/layouts/admin.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>Admin - <?php echo SITE_NAME; ?></title>
    
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
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col" x-data="{ sidebarOpen: true }">
    <div class="flex h-screen bg-gray-50">
        <!-- Sidebar -->
        <div :class="{'hidden': !sidebarOpen, 'block': sidebarOpen}" class="bg-gradient-to-b from-slate-900 to-blue-900 text-white md:block md:w-64 w-full fixed md:relative z-30">
            <div class="flex items-center justify-between p-4 border-b border-indigo-800">
                <div class="flex items-center">
                    <img src="/assets/images/logosmall.png" alt="<?php echo SITE_NAME; ?>" class="h-8 mr-2">
                    <span class="text-xl font-bold"><?php echo SITE_NAME; ?> Admin</span>
                </div>
                <button @click="sidebarOpen = !sidebarOpen" class="md:hidden text-white">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <nav class="p-4">
                <ul class="space-y-2">
                    <li>
                        <a href="/admin" class="block py-2 px-4 rounded-md hover:bg-indigo-800 transition duration-150 <?php echo $_SERVER['REQUEST_URI'] === '/admin' ? 'bg-indigo-800' : ''; ?>">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                                </svg>
                                Dashboard
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="/admin/users" class="block py-2 px-4 rounded-md hover:bg-indigo-800 transition duration-150 <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/users') === 0 ? 'bg-indigo-800' : ''; ?>">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                                Manage Users
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="/admin/categories" class="block py-2 px-4 rounded-md hover:bg-indigo-800 transition duration-150 <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/categories') === 0 ? 'bg-indigo-800' : ''; ?>">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                                Manage Categories
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="/admin/questions" class="block py-2 px-4 rounded-md hover:bg-indigo-800 transition duration-150 <?php echo strpos($_SERVER['REQUEST_URI'], '/admin/questions') === 0 ? 'bg-indigo-800' : ''; ?>">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Manage Questions
                            </div>
                        </a>
                    </li>
                    <li class="mt-8">
                        <a href="/" class="block py-2 px-4 rounded-md hover:bg-indigo-800 transition duration-150">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                Back to Site
                            </div>
                        </a>
                    </li>
                    <li>
                        <a href="/logout" class="block py-2 px-4 rounded-md hover:bg-indigo-800 transition duration-150">
                            <div class="flex items-center">
                                <svg class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                </svg>
                                Logout
                            </div>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        
        <!-- Content -->
        <div class="flex-1 overflow-auto">
            <!-- Top bar -->
            <div class="bg-white p-4 shadow-md flex justify-between items-center">
                <button @click="sidebarOpen = !sidebarOpen" class="md:hidden text-gray-800">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <div class="text-xl font-bold"><?php echo isset($pageTitle) ? $pageTitle : 'Admin'; ?></div>
                <div class="flex items-center">
                    <span class="mr-4"><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></span>
                    <a href="/logout" class="text-red-600 hover:text-red-800 transition-colors">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                    </a>
                </div>
            </div>
            
            <!-- Main content area -->
            <div class="p-6">
                <?php if (isset($flash) && $flash): ?>
                    <div class="mb-6 p-4 rounded-md <?php echo $flash['type'] === 'success' ? 'bg-green-100 text-green-700 border-l-4 border-green-500' : 'bg-red-100 text-red-700 border-l-4 border-red-500'; ?>">
                        <?php echo $flash['message']; ?>
                    </div>
                <?php endif; ?>
                
                <?php echo $content; ?>
            </div>
        </div>
    </div>

    <!-- Custom JS -->
    <?php if (isset($extraScripts)): ?>
        <?php foreach ($extraScripts as $script): ?>
            <script src="<?php echo $