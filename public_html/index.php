<?php
require_once 'config.php';
$pageTitle = 'Home';
include 'includes/header.php';
?>

<div class="relative">
    <!-- Hero Section with Background -->
    <div class="bg-gradient-to-br from-slate-900 via-blue-900 through-indigo-800 to-violet-950 text-white py-16 px-4 rounded-lg shadow-xl">
        <div class="container mx-auto text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">Welcome to <?php echo $site_name; ?></h1>
            <p class="text-xl md:text-2xl mb-8 max-w-3xl mx-auto">An adaptive learning platform - designed to help you learn faster and remember longer.</p>
            
            <?php if (isLoggedIn()): ?>
                <div class="mt-8 flex flex-wrap justify-center gap-4">
                    <a href="/quiz_select.php" class="bg-white text-indigo-800 hover:bg-gray-100 font-bold py-3 px-6 rounded-lg transition-all duration-200 transform hover:scale-105 shadow-lg">
                        Start Quiz
                    </a>
                    <a href="/profile.php" class="bg-transparent border-2 border-white text-white hover:bg-white hover:text-indigo-800 font-bold py-3 px-6 rounded-lg transition-all duration-200">
                        View Profile
                    </a>
                </div>
            <?php else: ?>
                <div class="mt-8 flex flex-wrap justify-center gap-4">
                    <a href="/login.php" class="bg-white text-indigo-800 hover:bg-gray-100 font-bold py-3 px-6 rounded-lg transition-all duration-200 transform hover:scale-105 shadow-lg">
                        Log In
                    </a>
                    <a href="/register.php" class="bg-transparent border-2 border-white text-white hover:bg-white hover:text-indigo-800 font-bold py-3 px-6 rounded-lg transition-all duration-200">
                        Create Account
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Features Section - Updated with quiz-card class -->
    <div class="py-16 px-4">
        <h2 class="text-3xl font-bold text-center mb-12">Features to Accelerate Your Learning</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl mx-auto">
            <!-- Feature 1 - Adaptive Learning -->
            <div class="p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow card-hover quiz-card">
                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold mb-2">Adaptive Learning</h3>
                <p class="text-gray-600">The adaptive test taking system adapts to your skill level, providing questions that are challenging but achievable.</p>
            </div>
            
            <!-- Feature 2 - Multiple Question Types -->
            <div class="p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow card-hover quiz-card">
                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center text-purple-600 mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold mb-2">Multiple Question Types</h3>
                <p class="text-gray-600">From multiple choice to fill-in-the-blank, various question types keep learning engaging.</p>
            </div>
            
            <!-- Feature 3 - Progress Tracking -->
            <div class="p-6 rounded-lg shadow-md hover:shadow-lg transition-shadow card-hover quiz-card">
                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center text-green-600 mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <h3 class="text-xl font-semibold mb-2">Progress Tracking</h3>
                <p class="text-gray-600">Track your learning journey with detailed statistics and performance insights.</p>
            </div>
        </div>
    </div>

<!-- About Section Link -->
<div class="mt-8 mb-16 bg-gradient-to-r from-indigo-50 to-purple-50 border border-indigo-100 rounded-lg p-6 shadow-sm max-w-3xl mx-auto">
    <div class="flex items-center">
        <div class="flex-shrink-0 mr-5">
            <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>
        <div>
            <h3 class="text-xl font-semibold text-gray-800 mb-2">Learn More About the QuizLight Platform</h3>
            <p class="text-gray-600 mb-4">Discover the science behind Quizlight's adaptive learning technology and the principles that make it effective.</p>
            <a href="/about.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors shadow-sm">
                Visit the About page
            </a>
        </div>
    </div>
</div>
    
</div>

<?php include 'includes/footer.php'; ?>