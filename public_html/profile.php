<?php
/**
 * User Profile Page
 * 
 * Enhanced version with advanced analytics, learning insights,
 * and personalized recommendations
 */

require_once 'config.php';
$pageTitle = 'My Profile';

// Ensure user is logged in
requireLogin();

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Check for success message from edit page
if (isset($_SESSION['profile_message']) && isset($_SESSION['profile_message_type'])) {
    $message = $_SESSION['profile_message'];
    $messageType = $_SESSION['profile_message_type'];
    
    // Clear the message after displaying it
    unset($_SESSION['profile_message']);
    unset($_SESSION['profile_message_type']);
}

/**
 * Fetch user data
 */
try {
    $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // User not found (should not happen)
        session_unset();
        session_destroy();
        header("Location: /login.php");
        exit;
    }
} catch (PDOException $e) {
    $message = "Error fetching user data: " . $e->getMessage();
    $messageType = "error";
    // Log the error
    error_log("Profile data error: " . $e->getMessage());
}

/**
 * Fetch user quiz statistics
 */
try {
    // Get total attempts
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_attempts WHERE user_id = ?");
    $stmt->execute([$userId]);
    $totalAttempts = $stmt->fetchColumn();
    
    // Get total questions answered
    $stmt = $pdo->prepare("SELECT SUM(total_questions) FROM user_attempts WHERE user_id = ?");
    $stmt->execute([$userId]);
    $totalQuestions = $stmt->fetchColumn() ?: 0;
    
    // Get total correct answers
    $stmt = $pdo->prepare("SELECT SUM(correct_answers) FROM user_attempts WHERE user_id = ?");
    $stmt->execute([$userId]);
    $totalCorrect = $stmt->fetchColumn() ?: 0;
    
    // Calculate average score
    $averageScore = $totalQuestions > 0 ? round(($totalCorrect / $totalQuestions) * 100) : 0;
    
    // Get average time per quiz
    $stmt = $pdo->prepare("SELECT AVG(duration_seconds) FROM user_attempts WHERE user_id = ? AND duration_seconds > 0");
    $stmt->execute([$userId]);
    $avgTime = $stmt->fetchColumn() ?: 0;
    
    // Get best quiz score
    $stmt = $pdo->prepare("
        SELECT 
            (correct_answers / total_questions) * 100 as score,
            quiz_type, 
            created_at
        FROM user_attempts 
        WHERE user_id = ? AND total_questions > 0
        ORDER BY score DESC, created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $bestQuiz = $stmt->fetch();
    
    // Get category performance
    $stmt = $pdo->prepare("
        SELECT 
            c.name as category_name,
            COUNT(qa.id) as total_answers,
            SUM(qa.is_correct) as correct_answers,
            (SUM(qa.is_correct) / COUNT(qa.id)) * 100 as percentage
        FROM quiz_answers qa
        JOIN questions q ON qa.question_id = q.id
        JOIN categories c ON q.category_id = c.id
        WHERE qa.user_id = ?
        GROUP BY c.id
        ORDER BY percentage DESC
    ");
    $stmt->execute([$userId]);
    $categoryPerformance = $stmt->fetchAll();
    
    // Get recent attempts
    $stmt = $pdo->prepare("
        SELECT ua.*, 
               (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') 
                FROM categories c 
                WHERE FIND_IN_SET(c.id, ua.categories)) as category_names
        FROM user_attempts ua
        WHERE ua.user_id = ?
        ORDER BY ua.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $recentAttempts = $stmt->fetchAll();
    
} catch (PDOException $e) {
    // Just log the error for stats, don't show to user
    error_log("Profile stats error: " . $e->getMessage());
    $totalAttempts = 0;
    $totalQuestions = 0;
    $totalCorrect = 0;
    $averageScore = 0;
    $recentAttempts = [];
}

// Load enhanced profile utilities
include 'includes/profile_analytics.php';

// Generate recommendations
$recommendations = generateRecommendations($userId, $pdo);

// Get performance data for charts
$performanceData = getPerformanceData($userId, $pdo);

// Add analytics JS to the page
$extraScripts[] = '/js/profile-analytics.js';

include 'includes/header.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">My Profile</h1>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="mb-8 p-4 rounded-md <?php echo $messageType === 'success' ? 'bg-green-100 border-l-4 border-green-500 text-green-700' : 'bg-red-100 border-l-4 border-red-500 text-red-700'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        <!-- Left Column: Profile and Stats -->
        <div class="space-y-6">
            <!-- Profile Summary Card - Updated with quiz-card -->
            <div class="p-6 rounded-lg shadow-md border-l-4 border-indigo-500 quiz-card">
                <div class="text-center mb-4">
                    <div class="w-24 h-24 bg-indigo-100 rounded-full flex items-center justify-center text-indigo-500 mx-auto mb-3">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                    <p class="text-gray-500 mb-2"><?php echo htmlspecialchars($user['email']); ?></p>
                    
                    <p class="text-sm text-gray-600">
                        <span class="font-medium">Member since:</span> <?php echo date('F j, Y', strtotime($user['created_at'])); ?>
                    </p>
                </div>
                
                <div class="border-t border-gray-200 pt-4 flex justify-center">
                    <a href="edit_profile.php" class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                        Edit Profile
                    </a>
                </div>
            </div>
            
            
            
        </div>
        
        
    </div>
<!-- Achievements Section -->
<div class="mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Your Achievements</h2>
    
    <?php if (!empty($recommendations['achievements'])): ?>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <?php foreach ($recommendations['achievements'] as $achievement): ?>
                <div class="p-4 rounded-lg shadow-md text-center quiz-card <?php echo $achievement['unlocked'] ? '' : 'opacity-60'; ?>">
                    <div class="w-16 h-16 mx-auto mb-3 rounded-full flex items-center justify-center
                         <?php echo $achievement['unlocked'] ? 'bg-indigo-100 text-indigo-600' : 'bg-gray-100 text-gray-400'; ?>">
                        <?php 
                        $icons = [
                            'flag' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" /></svg>',
                            'academic-cap' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 14l9-5-9-5-9 5 9 5z" /><path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222" /></svg>',
                            'globe' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
                            'light-bulb' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" /></svg>',
                            'check-circle' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
                            'star' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" /></svg>',
                        ];
                        echo $icons[$achievement['icon']] ?? '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg>';
                        ?>
                    </div>
                    
                    <h4 class="font-bold text-sm mb-1"><?php echo htmlspecialchars($achievement['name']); ?></h4>
                    <p class="text-xs text-gray-600 mb-2"><?php echo htmlspecialchars($achievement['description']); ?></p>
                    
                    <!-- Progress bar -->
                    <div class="w-full bg-gray-200 rounded-full h-2 mb-1">
                        <div class="<?php echo $achievement['unlocked'] ? 'bg-indigo-600' : 'bg-gray-400'; ?> h-2 rounded-full" 
                             style="width: <?php echo ($achievement['progress'] / $achievement['max']) * 100; ?>%"></div>
                    </div>
                    <p class="text-xs text-gray-500"><?php echo $achievement['progress']; ?>/<?php echo $achievement['max']; ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="bg-white p-6 rounded-lg shadow-md text-center">
            <p class="text-gray-500">Complete quizzes to earn achievements!</p>
        </div>
    <?php endif; ?>
</div>
    <!-- Analytics Dashboard Section -->
<div class="mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Learning Analytics</h2>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Performance Trend Chart -->
        <div class="p-6 rounded-lg shadow-md border-l-4 border-indigo-500 quiz-card">
            <div id="performance-trend-chart">
                <div class="text-center py-10">
                    <div class="animate-pulse flex space-x-4 justify-center">
                        <div class="rounded-full bg-indigo-100 h-12 w-12"></div>
                        <div class="flex-1 space-y-4 py-1 max-w-sm">
                            <div class="h-4 bg-indigo-100 rounded w-3/4"></div>
                            <div class="space-y-2">
                                <div class="h-4 bg-indigo-100 rounded"></div>
                                <div class="h-4 bg-indigo-100 rounded w-5/6"></div>
                            </div>
                        </div>
                    </div>
                    <p class="mt-4 text-gray-500">Loading performance data...</p>
                </div>
            </div>
        </div>
        
        <!-- Strength & Weakness Chart -->
        <div class="p-6 rounded-lg shadow-md border-l-4 border-indigo-500 quiz-card">
            <div id="strength-weakness-chart">
                <div class="text-center py-10">
                    <div class="animate-pulse flex space-x-4 justify-center">
                        <div class="rounded-full bg-indigo-100 h-12 w-12"></div>
                        <div class="flex-1 space-y-4 py-1 max-w-sm">
                            <div class="h-4 bg-indigo-100 rounded w-3/4"></div>
                            <div class="space-y-2">
                                <div class="h-4 bg-indigo-100 rounded"></div>
                                <div class="h-4 bg-indigo-100 rounded w-5/6"></div>
                            </div>
                        </div>
                    </div>
                    <p class="mt-4 text-gray-500">Loading category data...</p>
                </div>
            </div>
        </div>
        
        <!-- Difficulty Distribution -->
        <div class="p-6 rounded-lg shadow-md border-l-4 border-indigo-500 quiz-card">
            <div id="difficulty-distribution">
                <div class="text-center py-10">
                    <div class="animate-pulse flex space-x-4 justify-center">
                        <div class="rounded-full bg-indigo-100 h-12 w-12"></div>
                        <div class="flex-1 space-y-4 py-1 max-w-sm">
                            <div class="h-4 bg-indigo-100 rounded w-3/4"></div>
                            <div class="space-y-2">
                                <div class="h-4 bg-indigo-100 rounded"></div>
                                <div class="h-4 bg-indigo-100 rounded w-5/6"></div>
                            </div>
                        </div>
                    </div>
                    <p class="mt-4 text-gray-500">Loading difficulty data...</p>
                </div>
            </div>
        </div>
        
        <!-- Learning Growth Chart -->
        <div class="p-6 rounded-lg shadow-md border-l-4 border-indigo-500 quiz-card">
            <div id="learning-growth">
                <div class="text-center py-10">
                    <div class="animate-pulse flex space-x-4 justify-center">
                        <div class="rounded-full bg-indigo-100 h-12 w-12"></div>
                        <div class="flex-1 space-y-4 py-1 max-w-sm">
                            <div class="h-4 bg-indigo-100 rounded w-3/4"></div>
                            <div class="space-y-2">
                                <div class="h-4 bg-indigo-100 rounded"></div>
                                <div class="h-4 bg-indigo-100 rounded w-5/6"></div>
                            </div>
                        </div>
                    </div>
                    <p class="mt-4 text-gray-500">Loading growth data...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recommendations Section -->
<div class="mb-8">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Personalized Recommendations</h2>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Recommended Quiz -->
        <div class="p-6 rounded-lg shadow-md quiz-card">
            <h3 class="text-lg font-semibold mb-3">Recommended Quiz</h3>
            
            <?php if (!empty($recommendations['quiz_type'])): ?>
                <div class="bg-indigo-50 p-4 rounded-lg mb-4">
                    <div class="flex items-center justify-center">
                        <span class="bg-indigo-100 text-indigo-800 text-lg font-bold rounded-full h-10 w-10 flex items-center justify-center mr-3">
                            <?php echo substr(ucfirst($recommendations['quiz_type']['type']), 0, 1); ?>
                        </span>
                        <span class="text-indigo-800 font-medium"><?php echo ucfirst($recommendations['quiz_type']['type']); ?> Quiz</span>
                    </div>
                </div>
                
                <p class="text-gray-600 mb-4"><?php echo $recommendations['quiz_type']['message']; ?></p>
                
                <a href="quiz_select.php?rec=<?php echo $recommendations['quiz_type']['type']; ?>" 
                   class="block w-full bg-indigo-600 hover:bg-indigo-700 text-white text-center font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-150">
                    Start Recommended Quiz
                </a>
            <?php else: ?>
                <p class="text-gray-500 text-center py-4">Complete more quizzes to get personalized recommendations.</p>
            <?php endif; ?>
        </div>
        
        <!-- Focus Categories -->
        <div class="p-6 rounded-lg shadow-md quiz-card">
            <h3 class="text-lg font-semibold mb-3">Focus Areas</h3>
            
            <?php if (!empty($recommendations['categories']['items'])): ?>
                <p class="text-gray-600 mb-4"><?php echo $recommendations['categories']['message']; ?></p>
                
                <ul class="space-y-2">
                    <?php foreach ($recommendations['categories']['items'] as $category): ?>
                        <li class="flex items-center justify-between p-3 bg-red-50 rounded-md">
                            <span class="font-medium"><?php echo htmlspecialchars($category['name']); ?></span>
                            <span class="text-red-600 font-bold"><?php echo round($category['percentage']); ?>%</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <div class="mt-4 text-center">
                    <a href="quiz_select.php" class="text-indigo-600 hover:text-indigo-800 font-medium">
                        Create a focused quiz →
                    </a>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-4">Take quizzes in more categories to see your focus areas.</p>
            <?php endif; ?>
        </div>
        
        <!-- Weekly Study Plan -->
        <div class="p-6 rounded-lg shadow-md quiz-card">
            <h3 class="text-lg font-semibold mb-3">Weekly Study Plan</h3>
            
            <?php if (!empty($recommendations['study_schedule']['items'])): ?>
                <p class="text-gray-600 mb-4"><?php echo $recommendations['study_schedule']['message']; ?></p>
                
                <ul class="space-y-2">
                    <?php foreach ($recommendations['study_schedule']['items'] as $day): ?>
                        <li class="flex items-center justify-between p-3 
                            <?php 
                            echo ($day['focus_level'] === 'high') ? 'bg-red-50' : 
                                 (($day['focus_level'] === 'medium') ? 'bg-yellow-50' : 'bg-green-50'); 
                            ?> rounded-md">
                            <span class="font-medium"><?php echo $day['day']; ?></span>
                            <span class="
                                <?php 
                                echo ($day['focus_level'] === 'high') ? 'text-red-600' : 
                                     (($day['focus_level'] === 'medium') ? 'text-yellow-600' : 'text-green-600'); 
                                ?> font-bold"><?php echo htmlspecialchars($day['category']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-gray-500 text-center py-4">Complete more quizzes to get a personalized study plan.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Pass data to the charts -->
<script>
    window.performanceData = <?php echo json_encode($performanceData); ?>;
</script>
</div>

<?php include 'includes/footer.php'; ?>