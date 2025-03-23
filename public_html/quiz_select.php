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

// Include enhanced leaderboard functions
require_once 'includes/leaderboard-functions.php';

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
    <p class="mb-4">An intelligent algorithm schedules reviews at optimal intervals to maximize your long-term retention. The more you focus on a subject, the more cards you will master.</p>
    <!-- Spaced repetition stats -->
    <?php if ($srStats['total_cards'] > 0): ?>
    <div class="mt-6 pt-5 border-t border-gray-200">
        <h3 class="text-md font-medium text-gray-700 mb-3">Your Learning Progress</h3>
        <div class="grid grid-cols-3 gap-2 text-center">
            <div class="p-2 bg-gray-50 rounded">
                <div class="text-xl font-bold text-indigo-700"><?php echo $srStats['total_cards']; ?></div>
                <div class="text-xs text-gray-500">Total Cards</div>
            </div>
            <div class="p-2 bg-gray-50 rounded">
                <div class="text-xl font-bold text-indigo-700"><?php echo $srStats['mastered_cards']; ?></div>
                <div class="text-xs text-gray-500">Mastered</div>
            </div>
            <div class="p-2 bg-gray-50 rounded">
                <div class="text-xl font-bold text-indigo-700"><?php echo $srStats['average_ease_factor'] ?? '0.0'; ?></div>
                <div class="text-xs text-gray-500">Avg. Ease</div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
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
    </div><br>
    
    
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
            <?php if ($srStats['cards_due_today'] > 0): ?>
                Review Cards
            <?php else: ?>
                Start Spaced Repetition Session
            <?php endif; ?>
        </button>
    </form>
    
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

<!-- Enhanced Leaderboard Section with Fixed Modal -->
<div x-data="{ showMasteryModal: false }">
    <!-- Include the leaderboard section -->
    <?php include 'includes/leaderboard-section.php'; ?>
    
    <!-- Include the mastery score modal -->
    <?php include 'includes/mastery-score-modal.php'; ?>

<?php include 'includes/footer.php'; ?>