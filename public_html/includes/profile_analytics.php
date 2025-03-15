<?php
/**
 * Generates personalized recommendations based on user's quiz history
 *
 * @param int $userId The user ID
 * @param PDO $pdo Database connection
 * @return array Recommendations and insights
 */
function generateRecommendations($userId, $pdo) {
    $recommendations = [];
    
    try {
        // Get categories the user has attempted, ordered by performance
        $stmt = $pdo->prepare("
            SELECT 
                c.id,
                c.name,
                COUNT(qa.id) as total_answers,
                SUM(qa.is_correct) as correct_answers,
                (SUM(qa.is_correct) / COUNT(qa.id)) * 100 as percentage
            FROM quiz_answers qa
            JOIN questions q ON qa.question_id = q.id
            JOIN categories c ON q.category_id = c.id
            WHERE qa.user_id = ?
            GROUP BY c.id
            ORDER BY percentage ASC
        ");
        $stmt->execute([$userId]);
        $categoryPerformance = $stmt->fetchAll();
        
        // Find weakest categories (bottom 2)
        $weakCategories = array_slice($categoryPerformance, 0, min(2, count($categoryPerformance)));
        
        // Get most recent quiz attempt
        $stmt = $pdo->prepare("
            SELECT quiz_type, total_questions, correct_answers, created_at 
            FROM user_attempts 
            WHERE user_id = ? 
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$userId]);
        $recentAttempt = $stmt->fetch();
        
        // Get questions the user consistently gets wrong
        $stmt = $pdo->prepare("
            SELECT 
                q.id,
                q.question_text,
                q.category_id,
                c.name as category_name,
                COUNT(qa.id) as attempt_count,
                SUM(qa.is_correct) as correct_count
            FROM quiz_answers qa
            JOIN questions q ON qa.question_id = q.id
            JOIN categories c ON q.category_id = c.id
            WHERE qa.user_id = ?
            GROUP BY q.id
            HAVING attempt_count >= 2 AND (correct_count / attempt_count) < 0.5
            ORDER BY attempt_count DESC
            LIMIT 5
        ");
        $stmt->execute([$userId]);
        $troubleQuestions = $stmt->fetchAll();
        
        // Build recommendations
        
        // 1. Recommended quiz type based on history
        if (!empty($recentAttempt)) {
            $recentScore = ($recentAttempt['correct_answers'] / $recentAttempt['total_questions']) * 100;
            
            if ($recentScore < 60) {
                $recommendations['quiz_type'] = [
                    'type' => 'standard',
                    'message' => 'Focus on building core knowledge with a standard quiz.'
                ];
            } elseif ($recentScore < 80) {
                $recommendations['quiz_type'] = [
                    'type' => 'adaptive',
                    'message' => 'Challenge yourself with an adaptive quiz to improve your skills.'
                ];
            } else {
                $recommendations['quiz_type'] = [
                    'type' => 'test',
                    'message' => 'You\'re doing well! Try a test mode quiz to simulate exam conditions.'
                ];
            }
        } else {
            $recommendations['quiz_type'] = [
                'type' => 'quick',
                'message' => 'Start with a quick quiz to gauge your knowledge.'
            ];
        }
        
        // 2. Recommended categories to focus on
        if (!empty($weakCategories)) {
            $recommendations['categories'] = [
                'items' => $weakCategories,
                'message' => 'Consider focusing on these categories to improve your overall performance.'
            ];
        }
        
        // 3. Specific trouble questions
        if (!empty($troubleQuestions)) {
            $recommendations['trouble_questions'] = [
                'items' => $troubleQuestions,
                'message' => 'You\'ve struggled with these questions. Review the concepts they cover.'
            ];
        }
        
        // 4. Study schedule suggestion
        $recommendations['study_schedule'] = generateStudySchedule($categoryPerformance);
        
        // 5. Get achievements data
        $achievements = getAchievements($userId, $pdo);
        if (!empty($achievements)) {
            $recommendations['achievements'] = $achievements;
        }
        
    } catch (PDOException $e) {
        error_log("Error generating recommendations: " . $e->getMessage());
    }
    
    return $recommendations;
}

/**
 * Generate a personalized study schedule based on performance
 */
function generateStudySchedule($categoryPerformance) {
    if (empty($categoryPerformance)) {
        return [
            'message' => 'Take some quizzes to get a personalized study schedule.'
        ];
    }
    
    // Sort categories by performance (lowest first)
    usort($categoryPerformance, function($a, $b) {
        return $a['percentage'] <=> $b['percentage'];
    });
    
    // Top 3-5 categories to focus on
    $focusCategories = array_slice($categoryPerformance, 0, min(5, count($categoryPerformance)));
    
    // Create a 5-day schedule
    $schedule = [];
    $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    
    foreach ($focusCategories as $index => $category) {
        if ($index < count($daysOfWeek)) {
            $schedule[] = [
                'day' => $daysOfWeek[$index],
                'category' => $category['name'],
                'focus_level' => ($category['percentage'] < 60) ? 'high' : 
                                 (($category['percentage'] < 80) ? 'medium' : 'low')
            ];
        }
    }
    
    return [
        'items' => $schedule,
        'message' => 'Here\'s a suggested weekly study plan based on your performance.'
    ];
}

/**
 * Get user achievements
 */
function getAchievements($userId, $pdo) {
    $achievements = [];
    
    try {
        // Total questions answered
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_answers WHERE user_id = ?");
        $stmt->execute([$userId]);
        $totalAnswers = $stmt->fetchColumn();
        
        // Total correct answers
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_answers WHERE user_id = ? AND is_correct = 1");
        $stmt->execute([$userId]);
        $correctAnswers = $stmt->fetchColumn();
        
        // Quiz attempts
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_attempts WHERE user_id = ?");
        $stmt->execute([$userId]);
        $quizCount = $stmt->fetchColumn();
        
        // Categories attempted
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT q.category_id) 
            FROM quiz_answers qa 
            JOIN questions q ON qa.question_id = q.id 
            WHERE qa.user_id = ?
        ");
        $stmt->execute([$userId]);
        $categoryCount = $stmt->fetchColumn();
        
        // Perfect quizzes
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM user_attempts 
            WHERE user_id = ? AND correct_answers = total_questions AND total_questions >= 5
        ");
        $stmt->execute([$userId]);
        $perfectQuizzes = $stmt->fetchColumn();
        
        // Define achievements
        $allAchievements = [
            [
                'id' => 'first_quiz',
                'name' => 'First Steps',
                'description' => 'Completed your first quiz',
                'icon' => 'flag',
                'unlocked' => $quizCount >= 1,
                'progress' => min(1, $quizCount),
                'max' => 1
            ],
            [
                'id' => 'quiz_master',
                'name' => 'Quiz Master',
                'description' => 'Complete 10 quizzes',
                'icon' => 'academic-cap',
                'unlocked' => $quizCount >= 10,
                'progress' => min(10, $quizCount),
                'max' => 10
            ],
            [
                'id' => 'explorer',
                'name' => 'Knowledge Explorer',
                'description' => 'Try quizzes in 5 different categories',
                'icon' => 'globe',
                'unlocked' => $categoryCount >= 5,
                'progress' => min(5, $categoryCount),
                'max' => 5
            ],
            [
                'id' => 'century',
                'name' => 'Century Milestone',
                'description' => 'Answer 100 questions',
                'icon' => 'light-bulb',
                'unlocked' => $totalAnswers >= 100,
                'progress' => min(100, $totalAnswers),
                'max' => 100
            ],
            [
                'id' => 'accuracy',
                'name' => 'Sharp Mind',
                'description' => 'Achieve 80% accuracy overall',
                'icon' => 'check-circle',
                'unlocked' => $totalAnswers > 0 && ($correctAnswers / $totalAnswers) >= 0.8,
                'progress' => $totalAnswers > 0 ? min(80, round(($correctAnswers / $totalAnswers) * 100)) : 0,
                'max' => 80
            ],
            [
                'id' => 'perfect',
                'name' => 'Perfectionist',
                'description' => 'Score 100% on 3 quizzes',
                'icon' => 'star',
                'unlocked' => $perfectQuizzes >= 3,
                'progress' => min(3, $perfectQuizzes),
                'max' => 3
            ]
        ];
        
        // Return all achievements
        return $allAchievements;
        
    } catch (PDOException $e) {
        error_log("Error getting achievements: " . $e->getMessage());
        return [];
    }
}

/**
 * Get performance data for analytics charts
 */
function getPerformanceData($userId, $pdo) {
    $data = [
        'history' => [],
        'categories' => [],
        'difficultyLevels' => [],
        'growth' => []
    ];
    
    try {
        // Performance history
        $stmt = $pdo->prepare("
            SELECT 
                created_at as date, 
                (correct_answers / total_questions) * 100 as score
            FROM user_attempts 
            WHERE user_id = ? AND total_questions > 0
            ORDER BY created_at ASC
            LIMIT 20
        ");
        $stmt->execute([$userId]);
        $data['history'] = $stmt->fetchAll();
        
        // Category performance
        $stmt = $pdo->prepare("
            SELECT 
                c.name,
                COUNT(qa.id) as total_answers,
                SUM(qa.is_correct) as correct_answers,
                (SUM(qa.is_correct) / COUNT(qa.id)) * 100 as percentage
            FROM quiz_answers qa
            JOIN questions q ON qa.question_id = q.id
            JOIN categories c ON q.category_id = c.id
            WHERE qa.user_id = ?
            GROUP BY c.id
            HAVING COUNT(qa.id) >= 5
        ");
        $stmt->execute([$userId]);
        $data['categories'] = $stmt->fetchAll();
        
        // Performance by difficulty
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
        $data['difficultyLevels'] = $stmt->fetchAll();
        
        // Learning growth data (how scores improve over multiple attempts)
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(ua.id) as attempt,
                AVG((ua.correct_answers / ua.total_questions) * 100) as score
            FROM user_attempts ua
            WHERE ua.user_id = ?
            GROUP BY DATE(ua.created_at)
            ORDER BY attempt
            LIMIT 10
        ");
        $stmt->execute([$userId]);
        $data['growth'] = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Error getting performance data: " . $e->getMessage());
    }
    
    return $data;
}