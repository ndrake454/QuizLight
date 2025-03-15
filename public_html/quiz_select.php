<?php
/**
 * Quiz Selection Page
 * 
 * This page allows users to:
 * - Select different quiz modes (Quick, Spaced Repetition, Test, Adaptive)
 * - Choose categories for their quiz
 * - Configure quiz parameters like number of questions and difficulty
 * 
 * The page uses Alpine.js for tab navigation and dynamic category selection.
 */

require_once 'config.php';
requireLogin(); // Ensure the user is logged in

// Include the SpacedRepetition class to get stats
require_once 'includes/SpacedRepetition.php';

$pageTitle = 'Select Quiz';
$extraScripts = ['/js/quiz.js']; // Custom JS for quiz functionality

// Get categories from the database
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error loading categories: " . $e->getMessage();
    $categories = [];
    // Log the error for debugging
    error_log("Quiz category loading error: " . $e->getMessage());
}

// Get spaced repetition statistics for the user
$sr = new SpacedRepetition($pdo);
$srStats = $sr->getUserStats($_SESSION['user_id']);

// Get top performers data for this week based on mastery score (total questions × accuracy)
try {
    $stmt = $pdo->query("
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            COUNT(ua.id) as quiz_count,
            SUM(ua.total_questions) as total_questions,
            SUM(ua.correct_answers) as correct_answers,
            ROUND((SUM(ua.correct_answers) / SUM(ua.total_questions)) * 100, 1) as accuracy
        FROM users u
        JOIN user_attempts ua ON u.id = ua.user_id
        WHERE ua.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY u.id
        HAVING total_questions > 0
        ORDER BY (SUM(ua.correct_answers) / SUM(ua.total_questions)) * SUM(ua.total_questions) DESC
        LIMIT 5
    ");
    $topPerformers = $stmt->fetchAll();
    
    // Calculate mastery score for display
    foreach ($topPerformers as &$performer) {
        $performer['mastery_score'] = round($performer['total_questions'] * ($performer['accuracy'] / 100));
    }
} catch (PDOException $e) {
    error_log("Leaderboard error: " . $e->getMessage());
    $topPerformers = [];
}

// Get week of
$startOfWeek = date('M j', strtotime('monday this week'));
$endOfWeek = date('M j, Y', strtotime('sunday this week'));
$weekDateRange = "$startOfWeek - $endOfWeek";

include 'includes/header.php';
?>

<div x-data="quizSelect">
    <!-- Header Section -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold mb-2">Select Your Quiz</h1>
        <p class="text-gray-600">Choose your quiz type and categories to get started</p>
    </div>
    
    <!-- Error message if present -->
    <?php if (isset($_SESSION['quiz_error'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo htmlspecialchars($_SESSION['quiz_error']); ?></p>
        </div>
        <?php unset($_SESSION['quiz_error']); ?>
    <?php endif; ?>
    
    <!-- Tab Navigation - Responsive Grid for Mobile -->
    <div class="mb-6">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <button @click="activeTab = 'spaced'" 
                    class="py-3 px-2 font-medium text-sm rounded-md focus:outline-none transition ease-in-out duration-150 flex flex-col items-center justify-center"
                    :class="activeTab === 'spaced' ? 'bg-indigo-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Spaced Repetition</span>
            </button>
            <button @click="activeTab = 'quick'" 
                    class="py-3 px-2 font-medium text-sm rounded-md focus:outline-none transition ease-in-out duration-150 flex flex-col items-center justify-center"
                    :class="activeTab === 'quick' ? 'bg-amber-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <span>Quick Quiz</span>
            </button>
            <button @click="activeTab = 'test'" 
                    class="py-3 px-2 font-medium text-sm rounded-md focus:outline-none transition ease-in-out duration-150 flex flex-col items-center justify-center"
                    :class="activeTab === 'test' ? 'bg-indigo-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <span>Test Mode</span>
            </button>
            <button @click="activeTab = 'adaptive'" 
                    class="py-3 px-2 font-medium text-sm rounded-md focus:outline-none transition ease-in-out duration-150 flex flex-col items-center justify-center"
                    :class="activeTab === 'adaptive' ? 'bg-amber-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
                <span>Adaptive</span>
            </button>
        </div>
    </div>
    
    <!-- Spaced Repetition Tab -->
    <div x-show="activeTab === 'spaced'" class="rounded-lg shadow-md p-6 mb-8 quiz-card">
        <h2 class="text-xl font-semibold mb-4">Spaced Repetition</h2>
        <p class="mb-4">An intelligent algorithm schedules reviews at optimal intervals to maximize your long-term retention.</p>
        
        <form action="quiz.php" method="post">
            <input type="hidden" name="quiz_type" value="spaced_repetition">
            
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">Select Categories:</label>
                
                <?php if (!empty($categories)): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-64 overflow-y-auto p-2 category-list">
                        <?php foreach ($categories as $category): ?>
                        <label class="flex items-center p-3 bg-white rounded-md hover:bg-blue-50 transition-colors border border-blue-100">
                            <input type="checkbox" name="sr_categories[]" value="<?php echo $category['id']; ?>" 
                                   class="h-5 w-5 text-blue-600 focus:ring-blue-500 rounded">
                            <span class="ml-2 text-gray-700"><?php echo htmlspecialchars($category['name']); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-red-500">No categories available. Please contact an administrator.</p>
                <?php endif; ?>
            </div>
            
            <div class="mb-6">
                <label for="sr_num_questions" class="block text-gray-700 text-sm font-bold mb-2">Number of cards per session:</label>
                <input type="number" id="sr_num_questions" name="sr_num_questions" min="5" max="50" value="20"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                <p class="mt-1 text-sm text-gray-500">Recommended: 20 cards</p>
            </div>
            
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-md transition duration-150 shadow-md quiz-option">
                Start Spaced Repetition Session
            </button>
        </form>
        
        <!-- Info box about spaced repetition -->
        <div class="mt-6 bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-md">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">About Spaced Repetition</h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <p>Spaced repetition is a learning technique that incorporates increasing intervals of time between review of previously learned material to exploit the psychological spacing effect.</p>
                        <p class="mt-1">Cards you answer correctly will appear less frequently, while cards you struggle with will appear more often.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Quiz Tab -->
    <div x-show="activeTab === 'quick'" class="rounded-lg shadow-md p-6 mb-8 quiz-card">
        <h2 class="text-xl font-semibold mb-4">Quick Quiz</h2>
        <p class="mb-4">Take a quick 10-question quiz from your selected categories. The questions will be randomized from your selected categories.</p>
        
        <form action="quiz.php" method="post">
            <input type="hidden" name="quiz_type" value="quick">
            <input type="hidden" name="num_questions" value="10">
            
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">Select Categories:</label>
                <?php if (!empty($categories)): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-64 overflow-y-auto p-2 category-list">
                        <?php foreach ($categories as $category): ?>
                        <label class="flex items-center p-3 bg-gray-50 rounded-md hover:bg-gray-100 transition-colors">
                            <input type="checkbox" name="quick_categories[]" value="<?php echo $category['id']; ?>" 
                                   class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 rounded">
                            <span class="ml-2 text-gray-700"><?php echo htmlspecialchars($category['name']); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-red-500">No categories available. Please contact an administrator.</p>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-md transition duration-150 quiz-option">
                Start Quick Quiz
            </button>
        </form>
    </div>
    
    <!-- Test Tab -->
    <div x-show="activeTab === 'test'" class="rounded-lg shadow-md p-6 mb-8 quiz-card">
        <h2 class="text-xl font-semibold mb-4">Test Mode</h2>
        <p class="mb-4">Challenge yourself with a test where you'll receive feedback only at the end. The questions will be randomized from the categories you select.</p>
        
        <form action="quiz.php" method="post">
            <input type="hidden" name="quiz_type" value="test">
            
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">Select Categories:</label>
                <?php if (!empty($categories)): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-64 overflow-y-auto p-2 category-list">
                        <?php foreach ($categories as $category): ?>
                        <label class="flex items-center p-3 bg-gray-50 rounded-md hover:bg-gray-100 transition-colors">
                            <input type="checkbox" name="test_categories[]" value="<?php echo $category['id']; ?>" 
                                   class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 rounded">
                            <span class="ml-2 text-gray-700"><?php echo htmlspecialchars($category['name']); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-red-500">No categories available. Please contact an administrator.</p>
                <?php endif; ?>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                    <label for="test_num_questions" class="block text-gray-700 text-sm font-bold mb-2">Number of Questions:</label>
                    <input type="number" id="test_num_questions" name="test_num_questions" min="5" max="50" value="20"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
                
                <div>
                    <label for="test_difficulty" class="block text-gray-700 text-sm font-bold mb-2">Difficulty:</label>
                    <select id="test_difficulty" name="test_difficulty"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="easy">Easy</option>
                        <option value="medium" selected>Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="w-full bg-amber-600 hover:bg-amber-700 text-white font-bold py-3 px-4 rounded-md transition duration-150 quiz-option">
                Start Test
            </button>
        </form>
    </div>
    
    <!-- Adaptive Test Tab -->
    <div x-show="activeTab === 'adaptive'" class="rounded-lg shadow-md p-6 mb-8 quiz-card">
        <h2 class="text-xl font-semibold text-blue-800 mb-4">Adaptive Test</h2>
        <p class="mb-4 text-gray-700">Take a dynamic test that adapts to your skill level in real-time. You will receive feedback after you complete the test.</p>
        
        <div class="bg-white p-4 rounded-md shadow-sm mb-6 border-l-4 border-blue-500">
            <h3 class="font-medium text-blue-800 mb-2">How It Works</h3>
            <ol class="list-decimal pl-5 space-y-1 text-gray-700">
                <li>Questions start at an easy level</li>
                <li>Each correct answer increases the difficulty</li>
                <li>Each incorrect answer decreases the difficulty</li>
                <li>The test measures your mastery level in each category</li>
                <li>Your final score reflects both accuracy and the difficulty level you reached</li>
            </ol>
        </div>
        
        <form action="quiz.php" method="post">
            <input type="hidden" name="quiz_type" value="adaptive">
            
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">Select Categories:</label>
                <?php if (!empty($categories)): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-64 overflow-y-auto p-2 category-list">
                        <?php foreach ($categories as $category): ?>
                        <label class="flex items-center p-3 bg-white rounded-md hover:bg-blue-50 transition-colors border border-blue-100">
                            <input type="checkbox" name="adaptive_categories[]" value="<?php echo $category['id']; ?>" 
                                   class="h-5 w-5 text-blue-600 focus:ring-blue-500 rounded">
                            <span class="ml-2 text-gray-700"><?php echo htmlspecialchars($category['name']); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-red-500">No categories available. Please contact an administrator.</p>
                <?php endif; ?>
            </div>
            
            <div class="mb-6">
                <label for="adaptive_max_questions" class="block text-gray-700 text-sm font-bold mb-2">Maximum number of questions:</label>
                <input type="number" id="adaptive_max_questions" name="adaptive_max_questions" min="5" max="50" value="20"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                <p class="mt-1 text-sm text-gray-500">Minimum: 5, Maximum: 50</p>
            </div>
            
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-md transition duration-150 shadow-md quiz-option">
                Start Adaptive Test
            </button>
        </form>
    </div>

    <!-- No Categories Warning -->
    <?php if (empty($categories)): ?>
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mt-4" role="alert">
        <p class="font-bold">No Categories Available</p>
        <p>The quiz system requires categories to function. Please contact an administrator to set up categories.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Top Performers Section - This Week -->
<div class="mt-8 bg-white rounded-lg shadow-md overflow-hidden quiz-card">
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 py-4 px-6 text-white">
        <h2 class="text-xl font-bold">This Week's Top Performers</h2>
        <p class="text-sm opacity-75">Leaderboard for <?php echo $weekDateRange; ?></p>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RANK</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NAME</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MASTERY SCORE</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (count($topPerformers) > 0): ?>
                    <?php 
                    // Show only top 3 performers - they're already sorted by mastery score from the query
                    $limitedPerformers = array_slice($topPerformers, 0, 3);
                    foreach ($limitedPerformers as $index => $performer): 
                    ?>
                        <tr class="<?php echo $index % 2 === 0 ? 'bg-gray-50' : 'bg-white'; ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($index === 0): ?>
                                    <span class="px-3 py-1 rounded-full bg-yellow-100 text-yellow-800 font-bold">1st</span>
                                <?php elseif ($index === 1): ?>
                                    <span class="px-3 py-1 rounded-full bg-gray-100 text-gray-800 font-bold">2nd</span>
                                <?php elseif ($index === 2): ?>
                                    <span class="px-3 py-1 rounded-full bg-yellow-50 text-yellow-700 font-bold">3rd</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900">
                                    <?php echo htmlspecialchars($performer['first_name'] . ' ' . substr($performer['last_name'], 0, 1) . '.'); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="font-medium text-indigo-600"><?php echo number_format($performer['mastery_score']); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="px-6 py-4 text-center text-gray-500">
                            No data available for this week yet
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if (isLoggedIn()): ?>
        <div class="p-4 text-center bg-indigo-50 border-t border-indigo-100">
            <?php
            // Check if the current user is on the leaderboard
            $currentUserOnLeaderboard = false;
            $currentUserRank = 0;
            $currentUserScore = 0;
            
            if (!empty($topPerformers)) {
                foreach ($topPerformers as $index => $performer) {
                    if (isset($_SESSION['user_id']) && $performer['id'] == $_SESSION['user_id']) {
                        $currentUserOnLeaderboard = true;
                        $currentUserRank = $index + 1;
                        $currentUserScore = $performer['mastery_score'];
                        break;
                    }
                }
            }
            
            if ($currentUserOnLeaderboard && $currentUserRank <= 3):
            ?>
                <p class="text-indigo-700 font-medium">Congratulations! You're currently ranked #<?php echo $currentUserRank; ?> with a score of <?php echo number_format($currentUserScore); ?>!</p>
            <?php elseif ($currentUserOnLeaderboard): ?>
                <p class="text-indigo-700">Your current rank: #<?php echo $currentUserRank; ?> with a score of <?php echo number_format($currentUserScore); ?>.</p>
                <p class="text-gray-700 mt-1">Keep practicing to climb the leaderboard!</p>
            <?php else: ?>
                <p class="text-gray-700">Take more quizzes and answer carefully to appear on the leaderboard!</p>
            <?php endif; ?>
        </div>
        
        <!-- Weekly Progress Tracker -->
        <?php
        // Get current user's previous week score for comparison
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    SUM(ua.total_questions) as total_questions,
                    SUM(ua.correct_answers) as correct_answers
                FROM user_attempts ua 
                WHERE ua.user_id = ? 
                AND ua.created_at BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $lastWeekData = $stmt->fetch();
            
            // Get current week score
            $stmt = $pdo->prepare("
                SELECT 
                    SUM(ua.total_questions) as total_questions,
                    SUM(ua.correct_answers) as correct_answers
                FROM user_attempts ua 
                WHERE ua.user_id = ? 
                AND ua.created_at BETWEEN DATE_SUB(NOW(), INTERVAL 7 DAY) AND NOW()
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $thisWeekData = $stmt->fetch();
            
            // Calculate scores for both weeks
            $lastWeekScore = 0;
            if ($lastWeekData && $lastWeekData['total_questions'] > 0) {
                $lastWeekScore = round($lastWeekData['total_questions'] * ($lastWeekData['correct_answers'] / $lastWeekData['total_questions']));
            }
            
            $thisWeekScore = 0;
            if ($thisWeekData && $thisWeekData['total_questions'] > 0) {
                $thisWeekScore = round($thisWeekData['total_questions'] * ($thisWeekData['correct_answers'] / $thisWeekData['total_questions']));
            }
            
            // If we have data for either week, show comparison
            if ($lastWeekScore > 0 || $thisWeekScore > 0):
        ?>
        <div class="px-4 pb-4 text-center">
            <div class="mt-2 text-sm">
                <?php if ($thisWeekScore > $lastWeekScore): ?>
                    <span class="text-green-600 font-medium">
                        <svg xmlns="http://www.w3.org/2000/svg" class="inline-block h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                        You're up <?php echo number_format($thisWeekScore - $lastWeekScore); ?> points from last week!
                    </span>
                <?php elseif ($thisWeekScore < $lastWeekScore && $thisWeekScore > 0): ?>
                    <span class="text-yellow-600 font-medium">
                        <svg xmlns="http://www.w3.org/2000/svg" class="inline-block h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6" />
                        </svg>
                        You're down <?php echo number_format($lastWeekScore - $thisWeekScore); ?> points from last week.
                    </span>
                <?php elseif ($thisWeekScore == $lastWeekScore && $thisWeekScore > 0): ?>
                    <span class="text-blue-600 font-medium">
                        <svg xmlns="http://www.w3.org/2000/svg" class="inline-block h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14" />
                        </svg>
                        You're maintaining the same score as last week.
                    </span>
                <?php elseif ($lastWeekScore > 0 && $thisWeekScore == 0): ?>
                    <span class="text-gray-600">
                        You haven't started learning this week yet. Last week's score: <?php echo number_format($lastWeekScore); ?>
                    </span>
                <?php elseif ($lastWeekScore == 0 && $thisWeekScore > 0): ?>
                    <span class="text-green-600 font-medium">
                        Great start! This is your first week on the leaderboard.
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php 
            endif;
        } catch (PDOException $e) {
            // Silently fail and don't show the comparison section
            error_log("Weekly comparison error: " . $e->getMessage());
        }
        ?>
    <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>