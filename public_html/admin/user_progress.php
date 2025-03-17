<?php
/**
 * Admin User Progress Detail Page
 * 
 * This page provides detailed analytics about a specific user's performance:
 * - Overall statistics and engagement metrics
 * - Performance charts across categories
 * - Performance trends over time
 * - Quiz history and detailed attempt breakdown
 * - Skill development tracking
 */

require_once '../config.php';
$pageTitle = 'User Progress';

// Ensure user is logged in and is an admin
requireAdmin();

// Get user ID from URL parameter
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($userId <= 0) {
    // Redirect back to users page if no valid ID provided
    header("Location: users.php");
    exit;
}

/**
 * Fetch user data and statistics
 */
try {
    // Get basic user information
    $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, created_at, is_verified, is_admin FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // User not found, redirect back
        header("Location: users.php");
        exit;
    }
    
    // Get overall statistics
    
    // Total quiz attempts
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_attempts WHERE user_id = ?");
    $stmt->execute([$userId]);
    $totalAttempts = $stmt->fetchColumn();
    
    // Total questions answered
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_answers WHERE user_id = ?");
    $stmt->execute([$userId]);
    $totalAnswers = $stmt->fetchColumn();
    
    // Correct answers
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_answers WHERE user_id = ? AND is_correct = 1");
    $stmt->execute([$userId]);
    $correctAnswers = $stmt->fetchColumn();
    
    // Calculate success rate
    $successRate = $totalAnswers > 0 ? round(($correctAnswers / $totalAnswers) * 100) : 0;
    
    // Average time per quiz
    $stmt = $pdo->prepare("SELECT AVG(duration_seconds) FROM user_attempts WHERE user_id = ? AND duration_seconds > 0");
    $stmt->execute([$userId]);
    $avgTime = $stmt->fetchColumn() ?: 0;
    
    // First and last activity dates
    $stmt = $pdo->prepare("SELECT MIN(created_at) FROM user_attempts WHERE user_id = ?");
    $stmt->execute([$userId]);
    $firstActivity = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT MAX(created_at) FROM user_attempts WHERE user_id = ?");
    $stmt->execute([$userId]);
    $lastActivity = $stmt->fetchColumn();
    
    // Get category performance
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
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
    
    // Performance by difficulty level
    $stmt = $pdo->prepare("
        SELECT 
            q.intended_difficulty as difficulty,
            COUNT(qa.id) as total_answers,
            SUM(qa.is_correct) as correct_answers,
            (SUM(qa.is_correct) / COUNT(qa.id)) * 100 as percentage
        FROM quiz_answers qa
        JOIN questions q ON qa.question_id = q.id
        WHERE qa.user_id = ?
        GROUP BY q.intended_difficulty
        ORDER BY 
            CASE 
                WHEN q.intended_difficulty = 'easy' THEN 1 
                WHEN q.intended_difficulty = 'challenging' THEN 2 
                WHEN q.intended_difficulty = 'hard' THEN 3 
                ELSE 4 
            END
    ");
    $stmt->execute([$userId]);
    $difficultyPerformance = $stmt->fetchAll();
    
    // Performance over time (last 10 quizzes)
    $stmt = $pdo->prepare("
        SELECT 
            created_at as date,
            correct_answers,
            total_questions,
            (correct_answers / total_questions) * 100 as score,
            quiz_type
        FROM user_attempts
        WHERE user_id = ? AND total_questions > 0
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $performanceTrend = $stmt->fetchAll();
    // Reverse to show chronological order
    $performanceTrend = array_reverse($performanceTrend);
    
    // Get recent quiz attempts with details
    $stmt = $pdo->prepare("
        SELECT 
            ua.*,
            GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as categories
        FROM user_attempts ua
        LEFT JOIN categories c ON FIND_IN_SET(c.id, ua.categories)
        WHERE ua.user_id = ?
        GROUP BY ua.id
        ORDER BY ua.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $recentAttempts = $stmt->fetchAll();
    
    // Get engagement pattern (active days per week)
    $stmt = $pdo->prepare("
        SELECT 
            DAYOFWEEK(created_at) as day_of_week,
            COUNT(DISTINCT DATE(created_at)) as activity_count
        FROM user_attempts
        WHERE user_id = ?
        GROUP BY DAYOFWEEK(created_at)
        ORDER BY DAYOFWEEK(created_at)
    ");
    $stmt->execute([$userId]);
    $engagementPattern = $stmt->fetchAll();
    
    // Convert to a more usable format
    $daysOfWeek = [
        1 => 'Sunday',
        2 => 'Monday',
        3 => 'Tuesday',
        4 => 'Wednesday',
        5 => 'Thursday',
        6 => 'Friday',
        7 => 'Saturday'
    ];
    
    $formattedEngagement = [];
    foreach ($daysOfWeek as $dayNum => $dayName) {
        $found = false;
        foreach ($engagementPattern as $pattern) {
            if ($pattern['day_of_week'] == $dayNum) {
                $formattedEngagement[] = [
                    'day' => $dayName,
                    'count' => (int)$pattern['activity_count']
                ];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $formattedEngagement[] = [
                'day' => $dayName,
                'count' => 0
            ];
        }
    }
    
    // Find most active and least active days
    usort($formattedEngagement, function($a, $b) {
        return $b['count'] - $a['count'];
    });
    $mostActiveDay = $formattedEngagement[0];
    $leastActiveDay = end($formattedEngagement);
    
    // Skills improvement - check for improvement in repeated categories
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.name,
            COUNT(DISTINCT DATE(qa.created_at)) as days_practiced,
            MIN(DATE(qa.created_at)) as first_day,
            MAX(DATE(qa.created_at)) as last_day,
            (
                SELECT AVG(is_correct) * 100
                FROM quiz_answers qa2
                JOIN questions q2 ON qa2.question_id = q2.id
                WHERE qa2.user_id = ? AND q2.category_id = c.id
                AND DATE(qa2.created_at) = (
                    SELECT MIN(DATE(qa3.created_at))
                    FROM quiz_answers qa3
                    JOIN questions q3 ON qa3.question_id = q3.id
                    WHERE qa3.user_id = ? AND q3.category_id = c.id
                )
            ) as initial_score,
            (
                SELECT AVG(is_correct) * 100
                FROM quiz_answers qa2
                JOIN questions q2 ON qa2.question_id = q2.id
                WHERE qa2.user_id = ? AND q2.category_id = c.id
                AND DATE(qa2.created_at) = (
                    SELECT MAX(DATE(qa3.created_at))
                    FROM quiz_answers qa3
                    JOIN questions q3 ON qa3.question_id = q3.id
                    WHERE qa3.user_id = ? AND q3.category_id = c.id
                )
            ) as recent_score
        FROM quiz_answers qa
        JOIN questions q ON qa.question_id = q.id
        JOIN categories c ON q.category_id = c.id
        WHERE qa.user_id = ?
        GROUP BY c.id
        HAVING days_practiced > 1
        ORDER BY days_practiced DESC
    ");
    $stmt->execute([$userId, $userId, $userId, $userId, $userId]);
    $skillDevelopment = $stmt->fetchAll();
    
    // Add improvement percentage
    foreach ($skillDevelopment as &$skill) {
        $skill['initial_score'] = round($skill['initial_score']);
        $skill['recent_score'] = round($skill['recent_score']);
        
        if ($skill['initial_score'] > 0) {
            $skill['improvement'] = round(($skill['recent_score'] - $skill['initial_score']) / $skill['initial_score'] * 100);
        } else {
            $skill['improvement'] = $skill['recent_score'] > 0 ? 100 : 0;
        }
    }
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    error_log("Error fetching user progress data: " . $e->getMessage());
}

// Add scripts for charts
$extraScripts = ['https://cdn.jsdelivr.net/npm/chart.js@3.5.1/dist/chart.min.js'];

include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header with user info and back button -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">
                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>'s Progress
            </h1>
            <p class="text-gray-600"><?php echo htmlspecialchars($user['email']); ?></p>
        </div>
        <div class="flex space-x-3">
            <!-- Back button -->
            <a href="users.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition duration-150">
                Back to Users
            </a>
            
            <!-- Export button (future feature) -->
            <button class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 transition duration-150" 
                    onclick="alert('Export feature coming soon!')">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Export Data
            </button>
        </div>
    </div>
    
    <!-- Key Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <!-- Total Quiz Attempts -->
        <div class="bg-white p-5 rounded-lg shadow-md border-l-4 border-indigo-500">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm text-gray-500">Quiz Attempts</p>
                    <p class="text-3xl font-bold text-indigo-600"><?php echo $totalAttempts; ?></p>
                </div>
                <div class="p-3 rounded-full bg-indigo-100 text-indigo-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                    </svg>
                </div>
            </div>
            <?php if ($totalAttempts > 0): ?>
            <div class="text-sm text-gray-500 mt-2">
                First: <?php echo date('M j, Y', strtotime($firstActivity)); ?>
                <br>
                Last: <?php echo date('M j, Y', strtotime($lastActivity)); ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Questions Answered -->
        <div class="bg-white p-5 rounded-lg shadow-md border-l-4 border-blue-500">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm text-gray-500">Questions Answered</p>
                    <p class="text-3xl font-bold text-blue-600"><?php echo $totalAnswers; ?></p>
                </div>
                <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <?php if ($totalAnswers > 0): ?>
            <div class="text-sm text-gray-500 mt-2">
                <?php echo $correctAnswers; ?> correct (<?php echo $successRate; ?>%)
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Average Score -->
        <div class="bg-white p-5 rounded-lg shadow-md border-l-4 border-green-500">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm text-gray-500">Average Score</p>
                    <p class="text-3xl font-bold text-green-600"><?php echo $successRate; ?>%</p>
                </div>
                <div class="p-3 rounded-full bg-green-100 text-green-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
            </div>
            <div class="text-sm text-gray-500 mt-2">
                <?php
                $scoreClass = $successRate >= 80 ? 'text-green-600' : ($successRate >= 50 ? 'text-yellow-600' : 'text-red-600');
                $scoreLabel = $successRate >= 80 ? 'Excellent' : ($successRate >= 50 ? 'Good' : 'Needs improvement');
                ?>
                <span class="<?php echo $scoreClass; ?> font-medium"><?php echo $scoreLabel; ?></span>
            </div>
        </div>
        
        <!-- Average Time Per Quiz -->
        <div class="bg-white p-5 rounded-lg shadow-md border-l-4 border-yellow-500">
            <div class="flex justify-between items-center">
                <div>
                    <p class="text-sm text-gray-500">Average Time</p>
                    <p class="text-3xl font-bold text-yellow-600">
                        <?php 
                        // Format time in minutes and seconds
                        $minutes = floor($avgTime / 60);
                        $seconds = round($avgTime % 60);
                        echo $minutes . ":" . str_pad($seconds, 2, '0', STR_PAD_LEFT);
                        ?> 
                    </p>
                </div>
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <div class="text-sm text-gray-500 mt-2">
                Per quiz
            </div>
        </div>
    </div>
    
    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column - Category Performance -->
        <div class="lg:col-span-1">
            <div class="bg-white p-5 rounded-lg shadow-md mb-8">
                <h2 class="text-xl font-bold mb-4">Category Performance</h2>
                
                <?php if (!empty($categoryPerformance)): ?>
                    <div class="space-y-4">
                        <?php foreach ($categoryPerformance as $category): ?>
                            <div>
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-sm font-medium text-gray-700">
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </span>
                                    <span class="text-sm font-medium 
                                        <?php echo 
                                            $category['percentage'] >= 80 ? 'text-green-600' : 
                                            ($category['percentage'] >= 50 ? 'text-yellow-600' : 'text-red-600'); 
                                        ?>">
                                        <?php echo round($category['percentage']); ?>%
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="h-2.5 rounded-full 
                                        <?php echo 
                                            $category['percentage'] >= 80 ? 'bg-green-600' : 
                                            ($category['percentage'] >= 50 ? 'bg-yellow-500' : 'bg-red-500'); 
                                        ?>" 
                                        style="width: <?php echo $category['percentage']; ?>%">
                                    </div>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    <?php echo $category['correct_answers']; ?> / <?php echo $category['total_answers']; ?> questions
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-center p-4">No category data available.</p>
                <?php endif; ?>
            </div>
            
            <!-- Difficulty Level Performance -->
            <div class="bg-white p-5 rounded-lg shadow-md mb-8">
                <h2 class="text-xl font-bold mb-4">Performance by Difficulty</h2>
                
                <?php if (!empty($difficultyPerformance)): ?>
                    <div class="space-y-4">
                        <?php foreach ($difficultyPerformance as $difficulty): ?>
                            <div>
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-sm font-medium text-gray-700">
                                        <?php echo ucfirst(htmlspecialchars($difficulty['difficulty'])); ?>
                                    </span>
                                    <span class="text-sm font-medium 
                                        <?php echo 
                                            $difficulty['percentage'] >= 80 ? 'text-green-600' : 
                                            ($difficulty['percentage'] >= 50 ? 'text-yellow-600' : 'text-red-600'); 
                                        ?>">
                                        <?php echo round($difficulty['percentage']); ?>%
                                    </span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="h-2.5 rounded-full 
                                        <?php echo 
                                            $difficulty['percentage'] >= 80 ? 'bg-green-600' : 
                                            ($difficulty['percentage'] >= 50 ? 'bg-yellow-500' : 'bg-red-500'); 
                                        ?>" 
                                        style="width: <?php echo $difficulty['percentage']; ?>%">
                                    </div>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    <?php echo $difficulty['correct_answers']; ?> / <?php echo $difficulty['total_answers']; ?> questions
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-center p-4">No difficulty data available.</p>
                <?php endif; ?>
            </div>
            
            <!-- Engagement Pattern -->
            <div class="bg-white p-5 rounded-lg shadow-md">
                <h2 class="text-xl font-bold mb-4">Engagement Pattern</h2>
                
                <?php if (!empty($formattedEngagement) && array_sum(array_column($formattedEngagement, 'count')) > 0): ?>
                    <canvas id="engagementChart" width="400" height="300"></canvas>
                    
                    <div class="mt-4 p-3 bg-gray-50 rounded-md text-sm">
                        <p class="font-medium">Insights:</p>
                        <ul class="mt-2 space-y-1 text-gray-600">
                            <li>Most active day: <span class="font-medium"><?php echo $mostActiveDay['day']; ?></span> (<?php echo $mostActiveDay['count']; ?> sessions)</li>
                            <?php if ($mostActiveDay['count'] > 0 && $leastActiveDay['count'] === 0): ?>
                                <li>Least active day: <span class="font-medium"><?php echo $leastActiveDay['day']; ?></span> (no activity)</li>
                            <?php endif; ?>
                            <?php 
                            // Calculate consistency (how many days they use the system per week)
                            $activeDays = count(array_filter($formattedEngagement, function($day) { return $day['count'] > 0; }));
                            ?>
                            <li>Studies on <span class="font-medium"><?php echo $activeDays; ?></span> out of 7 days of the week</li>
                        </ul>
                    </div>

                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const ctx = document.getElementById('engagementChart').getContext('2d');
                            
                            const data = {
                                labels: <?php echo json_encode(array_column($formattedEngagement, 'day')); ?>,
                                datasets: [{
                                    label: 'Activity Sessions',
                                    data: <?php echo json_encode(array_column($formattedEngagement, 'count')); ?>,
                                    backgroundColor: [
                                        'rgba(54, 162, 235, 0.5)',
                                        'rgba(255, 99, 132, 0.5)',
                                        'rgba(255, 206, 86, 0.5)',
                                        'rgba(75, 192, 192, 0.5)',
                                        'rgba(153, 102, 255, 0.5)',
                                        'rgba(255, 159, 64, 0.5)',
                                        'rgba(199, 199, 199, 0.5)'
                                    ],
                                    borderColor: [
                                        'rgba(54, 162, 235, 1)',
                                        'rgba(255, 99, 132, 1)',
                                        'rgba(255, 206, 86, 1)',
                                        'rgba(75, 192, 192, 1)',
                                        'rgba(153, 102, 255, 1)',
                                        'rgba(255, 159, 64, 1)',
                                        'rgba(199, 199, 199, 1)'
                                    ],
                                    borderWidth: 1
                                }]
                            };
                            
                            const config = {
                                type: 'bar',
                                data: data,
                                options: {
                                    responsive: true,
                                    plugins: {
                                        legend: {
                                            display: false
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    return context.raw + ' study sessions';
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            title: {
                                                display: true,
                                                text: 'Number of Sessions'
                                            },
                                            ticks: {
                                                stepSize: 1
                                            }
                                        }
                                    }
                                }
                            };
                            
                            new Chart(ctx, config);
                        });
                    </script>
                <?php else: ?>
                    <p class="text-gray-500 text-center p-4">No engagement data available.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Middle and Right Columns -->
        <div class="lg:col-span-2">
            <!-- Performance Trend Chart -->
            <div class="bg-white p-5 rounded-lg shadow-md mb-8">
                <h2 class="text-xl font-bold mb-4">Performance Over Time</h2>
                
                <?php if (!empty($performanceTrend)): ?>
                    <canvas id="performanceChart" width="800" height="400"></canvas>
                    
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const ctx = document.getElementById('performanceChart').getContext('2d');
                            
                            const data = {
                                labels: <?php echo json_encode(array_map(function($item) { 
                                    return date('M j', strtotime($item['date']));
                                }, $performanceTrend)); ?>,
                                datasets: [{
                                    label: 'Score (%)',
                                    data: <?php echo json_encode(array_map(function($item) { 
                                        return round($item['score']);
                                    }, $performanceTrend)); ?>,
                                    backgroundColor: 'rgba(79, 70, 229, 0.2)',
                                    borderColor: 'rgba(79, 70, 229, 1)',
                                    borderWidth: 2,
                                    tension: 0.3,
                                    fill: true
                                }]
                            };
                            
                            const config = {
                                type: 'line',
                                data: data,
                                options: {
                                    responsive: true,
                                    plugins: {
                                        tooltip: {
                                            callbacks: {
                                                afterLabel: function(context) {
                                                    const index = context.dataIndex;
                                                    const item = <?php echo json_encode($performanceTrend); ?>[index];
                                                    return [
                                                        `Correct: ${item.correct_answers}/${item.total_questions}`,
                                                        `Quiz Type: ${item.quiz_type}`
                                                    ];
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            max: 100,
                                            title: {
                                                display: true,
                                                text: 'Score (%)'
                                            }
                                        }
                                    }
                                }
                            };
                            
                            new Chart(ctx, config);
                        });
                    </script>
                    
                    <?php 
                    // Calculate trend for insights
                    if (count($performanceTrend) >= 2) {
                        $firstScore = $performanceTrend[0]['score'];
                        $lastScore = end($performanceTrend)['score'];
                        $scoreDiff = $lastScore - $firstScore;
                        $trendDirection = $scoreDiff > 0 ? 'improving' : ($scoreDiff < 0 ? 'declining' : 'stable');
                    }
                    ?>
                    
                    <?php if (isset($trendDirection) && count($performanceTrend) >= 2): ?>
                    <div class="mt-4 p-3 bg-gray-50 rounded-md text-sm">
                        <p class="font-medium">Trend Analysis:</p>
                        <p class="mt-2 
                            <?php echo $trendDirection === 'improving' ? 'text-green-600' : 
                                        ($trendDirection === 'declining' ? 'text-red-600' : 'text-gray-600'); ?>">
                            <?php
                            if ($trendDirection === 'improving') {
                                echo 'Performance is improving. Score increased by ' . abs(round($scoreDiff)) . ' percentage points.';
                            } elseif ($trendDirection === 'declining') {
                                echo 'Performance is declining. Score decreased by ' . abs(round($scoreDiff)) . ' percentage points.';
                            } else {
                                echo 'Performance is stable with no significant change.';
                            }
                            ?>
                        </p>
                        
                        <?php
                        // Get highest and lowest scores
                        $scores = array_column($performanceTrend, 'score');
                        $highestScore = max($scores);
                        $lowestScore = min($scores);
                        $highestIndex = array_search($highestScore, $scores);
                        $lowestIndex = array_search($lowestScore, $scores);
                        $highestDate = date('M j, Y', strtotime($performanceTrend[$highestIndex]['date']));
                        $lowestDate = date('M j, Y', strtotime($performanceTrend[$lowestIndex]['date']));
                        ?>
                        
                        <ul class="mt-2 space-y-1 text-gray-600">
                            <li>Best performance: <span class="font-medium"><?php echo round($highestScore); ?>%</span> on <?php echo $highestDate; ?></li>
                            <li>Lowest performance: <span class="font-medium"><?php echo round($lowestScore); ?>%</span> on <?php echo $lowestDate; ?></li>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <p class="text-gray-500 text-center p-4">No performance trend data available.</p>
                <?php endif; ?>
            </div>
            
            <!-- Skill Development Chart -->
            <div class="bg-white p-5 rounded-lg shadow-md mb-8">
                <h2 class="text-xl font-bold mb-4">Skill Development</h2>
                
                <?php if (!empty($skillDevelopment)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Practice Days</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">First Score</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recent Score</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Improvement</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($skillDevelopment as $skill): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($skill['name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500"><?php echo $skill['days_practiced']; ?> days</div>
                                        <div class="text-xs text-gray-400"><?php echo date('M j', strtotime($skill['first_day'])); ?> - <?php echo date('M j', strtotime($skill['last_day'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo $skill['initial_score']; ?>%</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo $skill['recent_score']; ?>%</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium <?php echo $skill['improvement'] >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $skill['improvement'] >= 0 ? '+' : ''; ?><?php echo $skill['improvement']; ?>%
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-center p-4">No skill development data available. User needs to practice categories on multiple days to track improvement.</p>
                <?php endif; ?>
            </div>
            
            <!-- Recent Quiz Attempts -->
            <div class="bg-white p-5 rounded-lg shadow-md">
                <h2 class="text-xl font-bold mb-4">Recent Quiz Attempts</h2>
                
                <?php if (!empty($recentAttempts)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quiz Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categories</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($recentAttempts as $attempt): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($attempt['created_at'])); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo date('g:i A', strtotime($attempt['created_at'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php 
                                            switch($attempt['quiz_type']) {
                                                case 'quick': echo 'bg-blue-100 text-blue-800'; break;
                                                case 'custom': echo 'bg-indigo-100 text-indigo-800'; break;
                                                case 'test': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'adaptive': echo 'bg-purple-100 text-purple-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo ucfirst($attempt['quiz_type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($attempt['categories'] ?? 'N/A'); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php 
                                        $score = $attempt['total_questions'] > 0 ? round(($attempt['correct_answers'] / $attempt['total_questions']) * 100) : 0;
                                        $scoreClass = $score >= 80 ? 'text-green-600' : ($score >= 50 ? 'text-yellow-600' : 'text-red-600');
                                        ?>
                                        <div class="text-sm font-medium <?php echo $scoreClass; ?>">
                                            <?php echo $attempt['correct_answers']; ?>/<?php echo $attempt['total_questions']; ?>
                                            (<?php echo $score; ?>%)
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500">
                                            <?php 
                                            if ($attempt['duration_seconds'] > 0) {
                                                $minutes = floor($attempt['duration_seconds'] / 60);
                                                $seconds = $attempt['duration_seconds'] % 60;
                                                echo $minutes . "m " . $seconds . "s";
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-center p-4">No recent quiz attempts available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recommendations and Actions Section -->
    <div class="mt-8 bg-white p-5 rounded-lg shadow-md">
        <h2 class="text-xl font-bold mb-4">Recommendations</h2>
        
        <?php if (!empty($categoryPerformance)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php
                // Find weakest categories (bottom 2)
                usort($categoryPerformance, function($a, $b) {
                    return $a['percentage'] <=> $b['percentage'];
                });
                $weakCategories = array_slice($categoryPerformance, 0, min(2, count($categoryPerformance)));
                
                // Calculate overall engagement level
                if ($totalAttempts > 0) {
                    // Days since first activity
                    $firstDate = new DateTime($firstActivity);
                    $today = new DateTime();
                    $daysSince = $today->diff($firstDate)->days;
                    
                    if ($daysSince > 0) {
                        $activityFrequency = $totalAttempts / $daysSince;
                        
                        if ($activityFrequency >= 0.5) {
                            $engagementLevel = 'high';
                            $engagementMessage = 'User is highly engaged with the platform.';
                        } elseif ($activityFrequency >= 0.2) {
                            $engagementLevel = 'medium';
                            $engagementMessage = 'User is moderately engaged with the platform.';
                        } else {
                            $engagementLevel = 'low';
                            $engagementMessage = 'User shows low engagement with the platform.';
                        }
                    } else {
                        $engagementLevel = 'new';
                        $engagementMessage = 'User is new to the platform.';
                    }
                } else {
                    $engagementLevel = 'inactive';
                    $engagementMessage = 'User has not taken any quizzes.';
                }
                ?>
                
                <!-- Engagement Recommendation -->
                <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                    <h3 class="font-bold text-purple-800 mb-2">Engagement Strategy</h3>
                    
                    <p class="text-purple-700 mb-3"><?php echo $engagementMessage; ?></p>
                    
                    <ul class="list-disc list-inside text-purple-800 space-y-1">
                        <?php if ($engagementLevel === 'high'): ?>
                            <li>Maintain engagement with challenging content</li>
                            <li>Introduce advanced quiz types (adaptive)</li>
                            <li>Suggest expanding into new categories</li>
                        <?php elseif ($engagementLevel === 'medium'): ?>
                            <li>Encourage more regular practice</li>
                            <li>Suggest setting a weekly quiz goal</li>
                            <li>Highlight progress made so far</li>
                        <?php elseif ($engagementLevel === 'low'): ?>
                            <li>Send re-engagement messages</li>
                            <li>Suggest shorter, more focused quiz sessions</li>
                            <li>Highlight benefits of regular practice</li>
                        <?php elseif ($engagementLevel === 'new'): ?>
                            <li>Welcome the user personally</li>
                            <li>Suggest starting with quick quizzes</li>
                            <li>Provide platform orientation</li>
                        <?php else: ?>
                            <li>Send activation email</li>
                            <li>Highlight interesting features</li>
                            <li>Offer assistance with getting started</li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <!-- Learning Recommendation -->
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                    <h3 class="font-bold text-blue-800 mb-2">Learning Focus</h3>
                    
                    <?php if (!empty($weakCategories)): ?>
                        <p class="text-blue-700 mb-3">
                            <?php echo $successRate >= 70 ? 'User is performing well overall, but can improve in specific areas.' :
                                          'User should focus on improving these areas:'; ?>
                        </p>
                        
                        <ul class="list-disc list-inside text-blue-800 space-y-1">
                            <?php foreach ($weakCategories as $category): ?>
                                <li>
                                    <?php echo htmlspecialchars($category['category_name']); ?> 
                                    (<?php echo round($category['percentage']); ?>% success rate)
                                </li>
                            <?php endforeach; ?>
                            
                            <?php if (!empty($difficultyPerformance)): ?>
                                <?php
                                // Find lowest performing difficulty
                                usort($difficultyPerformance, function($a, $b) {
                                    return $a['percentage'] <=> $b['percentage'];
                                });
                                $weakestDifficulty = $difficultyPerformance[0];
                                ?>
                                <li>
                                    Practice with <?php echo strtolower($weakestDifficulty['difficulty']); ?> difficulty questions
                                    (<?php echo round($weakestDifficulty['percentage']); ?>% success rate)
                                </li>
                            <?php endif; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-blue-700">Insufficient data to provide focused recommendations.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <p class="text-gray-500 text-center p-4">
                Not enough data available to generate personalized recommendations.
                Encourage the user to take more quizzes across different categories.
            </p>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>