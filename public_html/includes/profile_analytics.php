<?php
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
        
        // Get consecutive days of learning
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT DATE(created_at)) as unique_days
            FROM user_attempts
            WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$userId]);
        $activeDays = $stmt->fetchColumn() ?: 0;
        
        // Get longest streak (consecutive days)
        $stmt = $pdo->prepare("
            SELECT DATE(created_at) as quiz_date
            FROM user_attempts
            WHERE user_id = ?
            GROUP BY DATE(created_at)
            ORDER BY quiz_date
        ");
        $stmt->execute([$userId]);
        $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $longestStreak = 0;
        $currentStreak = 0;
        $previousDate = null;
        
        foreach ($dates as $date) {
            $currentDate = new DateTime($date);
            
            if ($previousDate) {
                $diff = $previousDate->diff($currentDate)->days;
                
                if ($diff == 1) {
                    // Consecutive day
                    $currentStreak++;
                } else if ($diff > 1) {
                    // Streak broken
                    $longestStreak = max($longestStreak, $currentStreak);
                    $currentStreak = 1;
                }
            } else {
                $currentStreak = 1;
            }
            
            $previousDate = $currentDate;
        }
        
        // Update longest streak with current streak if needed
        $longestStreak = max($longestStreak, $currentStreak);
        
        // Get current streak
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM (
                SELECT DISTINCT DATE(created_at) as quiz_date
                FROM user_attempts
                WHERE user_id = ?
                AND created_at >= 
                    (SELECT DATE_SUB(CURDATE(), INTERVAL (
                        SELECT DATEDIFF(CURDATE(), MAX(DATE(created_at)))
                        FROM user_attempts
                        WHERE user_id = ? AND DATEDIFF(CURDATE(), DATE(created_at)) <= 1
                    ) DAY))
                GROUP BY quiz_date
                ORDER BY quiz_date DESC
            ) as streak_days
        ");
        $stmt->execute([$userId, $userId]);
        $currentStreak = $stmt->fetchColumn() ?: 0;
        
        // Get speed metrics
        $stmt = $pdo->prepare("
            SELECT AVG(duration_seconds / total_questions) as avg_time_per_question
            FROM user_attempts
            WHERE user_id = ? AND total_questions > 0 AND duration_seconds > 0
        ");
        $stmt->execute([$userId]);
        $avgTimePerQuestion = $stmt->fetchColumn() ?: 0;
        
        // Get fastest quiz time
        $stmt = $pdo->prepare("
            SELECT MIN(duration_seconds) 
            FROM user_attempts
            WHERE user_id = ? AND total_questions >= 10 AND duration_seconds > 0
        ");
        $stmt->execute([$userId]);
        $fastestQuizTime = $stmt->fetchColumn() ?: 0;
        
        // Get different quiz types attempted
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT quiz_type)
            FROM user_attempts
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $quizTypesCount = $stmt->fetchColumn() ?: 0;
        
        // Get adaptive quiz completions
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM user_attempts
            WHERE user_id = ? AND quiz_type = 'adaptive'
        ");
        $stmt->execute([$userId]);
        $adaptiveQuizzes = $stmt->fetchColumn() ?: 0;
        
        // Get hard questions answered correctly
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM quiz_answers qa
            JOIN questions q ON qa.question_id = q.id
            WHERE qa.user_id = ? AND qa.is_correct = 1 AND q.difficulty_value >= 4.0
        ");
        $stmt->execute([$userId]);
        $hardQuestionsCorrect = $stmt->fetchColumn() ?: 0;
        
        // Calculate mastery score
        $stmt = $pdo->prepare("
            SELECT 
                SUM(ua.total_questions) as total_questions,
                SUM(ua.correct_answers) as correct_answers,
                AVG(COALESCE(q.difficulty_value, 3.0)) as avg_difficulty
            FROM user_attempts ua
            LEFT JOIN quiz_answers qa ON ua.user_id = qa.user_id
            LEFT JOIN questions q ON qa.question_id = q.id
            WHERE ua.user_id = ?
        ");
        $stmt->execute([$userId]);
        $masteryData = $stmt->fetch();
        
        // Calculate mastery score
        $masteryScore = 0;
        if ($masteryData && $masteryData['total_questions'] > 0) {
            $difficultyMultiplier = max(1.0, ($masteryData['avg_difficulty'] ?? 1.0));
            $accuracy = ($masteryData['correct_answers'] / $masteryData['total_questions']);
            $masteryScore = round($masteryData['total_questions'] * $accuracy * $difficultyMultiplier);
        }
        
        // Define all achievements
        $allAchievements = [
            // Beginner achievements
            [
                'id' => 'first_quiz',
                'name' => 'First Steps',
                'description' => 'Complete your first quiz',
                'icon' => 'flag',
                'unlocked' => $quizCount >= 1,
                'progress' => min(1, $quizCount),
                'max' => 1
            ],
            [
                'id' => 'first_correct',
                'name' => 'Brilliant Beginning',
                'description' => 'Answer your first question correctly',
                'icon' => 'check-circle',
                'unlocked' => $correctAnswers >= 1,
                'progress' => min(1, $correctAnswers),
                'max' => 1
            ],
            [
                'id' => 'first_perfect',
                'name' => 'Perfect Start',
                'description' => 'Score 100% on your first quiz',
                'icon' => 'star',
                'unlocked' => $perfectQuizzes >= 1,
                'progress' => min(1, $perfectQuizzes),
                'max' => 1
            ],
            
            // Quiz count achievements
            [
                'id' => 'quiz_10',
                'name' => 'Quiz Enthusiast',
                'description' => 'Complete 10 quizzes',
                'icon' => 'academic-cap',
                'unlocked' => $quizCount >= 10,
                'progress' => min(10, $quizCount),
                'max' => 10
            ],
            [
                'id' => 'quiz_25',
                'name' => 'Quiz Master',
                'description' => 'Complete 25 quizzes',
                'icon' => 'academic-cap',
                'unlocked' => $quizCount >= 25,
                'progress' => min(25, $quizCount),
                'max' => 25
            ],
            [
                'id' => 'quiz_50',
                'name' => 'Quiz Virtuoso',
                'description' => 'Complete 50 quizzes',
                'icon' => 'academic-cap',
                'unlocked' => $quizCount >= 50,
                'progress' => min(50, $quizCount),
                'max' => 50
            ],
            [
                'id' => 'quiz_100',
                'name' => 'Quiz Centurion',
                'description' => 'Complete 100 quizzes',
                'icon' => 'academic-cap',
                'unlocked' => $quizCount >= 100,
                'progress' => min(100, $quizCount),
                'max' => 100
            ],
            
            // Question count achievements
            [
                'id' => 'question_100',
                'name' => 'Century Milestone',
                'description' => 'Answer 100 questions',
                'icon' => 'light-bulb',
                'unlocked' => $totalAnswers >= 100,
                'progress' => min(100, $totalAnswers),
                'max' => 100
            ],
            [
                'id' => 'question_500',
                'name' => 'Knowledge Hunter',
                'description' => 'Answer 500 questions',
                'icon' => 'light-bulb',
                'unlocked' => $totalAnswers >= 500,
                'progress' => min(500, $totalAnswers),
                'max' => 500
            ],
            [
                'id' => 'question_1000',
                'name' => 'Question Conqueror',
                'description' => 'Answer 1,000 questions',
                'icon' => 'light-bulb',
                'unlocked' => $totalAnswers >= 1000,
                'progress' => min(1000, $totalAnswers),
                'max' => 1000
            ],
            [
                'id' => 'question_5000',
                'name' => 'Knowledge Titan',
                'description' => 'Answer 5,000 questions',
                'icon' => 'star',
                'unlocked' => $totalAnswers >= 5000,
                'progress' => min(5000, $totalAnswers),
                'max' => 5000
            ],
            
            // Accuracy achievements
            [
                'id' => 'accuracy_70',
                'name' => 'Accuracy Apprentice',
                'description' => 'Achieve 70% accuracy overall',
                'icon' => 'check-circle',
                'unlocked' => $totalAnswers > 100 && ($correctAnswers / $totalAnswers) >= 0.7,
                'progress' => $totalAnswers > 0 ? min(70, round(($correctAnswers / $totalAnswers) * 100)) : 0,
                'max' => 70
            ],
            [
                'id' => 'accuracy_80',
                'name' => 'Accuracy Expert',
                'description' => 'Achieve 80% accuracy overall',
                'icon' => 'check-circle',
                'unlocked' => $totalAnswers > 100 && ($correctAnswers / $totalAnswers) >= 0.8,
                'progress' => $totalAnswers > 0 ? min(80, round(($correctAnswers / $totalAnswers) * 100)) : 0,
                'max' => 80
            ],
            [
                'id' => 'accuracy_90',
                'name' => 'Accuracy Master',
                'description' => 'Achieve 90% accuracy overall',
                'icon' => 'check-circle',
                'unlocked' => $totalAnswers > 100 && ($correctAnswers / $totalAnswers) >= 0.9,
                'progress' => $totalAnswers > 0 ? min(90, round(($correctAnswers / $totalAnswers) * 100)) : 0,
                'max' => 90
            ],
            [
                'id' => 'accuracy_95',
                'name' => 'Near Perfection',
                'description' => 'Achieve 95% accuracy overall',
                'icon' => 'check-circle',
                'unlocked' => $totalAnswers > 100 && ($correctAnswers / $totalAnswers) >= 0.95,
                'progress' => $totalAnswers > 0 ? min(95, round(($correctAnswers / $totalAnswers) * 100)) : 0,
                'max' => 95
            ],
            
            // Perfect quiz achievements
            [
                'id' => 'perfect_3',
                'name' => 'Perfectionist',
                'description' => 'Score 100% on 3 quizzes',
                'icon' => 'star',
                'unlocked' => $perfectQuizzes >= 3,
                'progress' => min(3, $perfectQuizzes),
                'max' => 3
            ],
            [
                'id' => 'perfect_5',
                'name' => 'Excellence',
                'description' => 'Score 100% on 5 quizzes',
                'icon' => 'star',
                'unlocked' => $perfectQuizzes >= 5,
                'progress' => min(5, $perfectQuizzes),
                'max' => 5
            ],
            [
                'id' => 'perfect_10',
                'name' => 'Flawless Record',
                'description' => 'Score 100% on 10 quizzes',
                'icon' => 'star',
                'unlocked' => $perfectQuizzes >= 10,
                'progress' => min(10, $perfectQuizzes),
                'max' => 10
            ],
            [
                'id' => 'perfect_25',
                'name' => 'Perfection Master',
                'description' => 'Score 100% on 25 quizzes',
                'icon' => 'star',
                'unlocked' => $perfectQuizzes >= 25,
                'progress' => min(25, $perfectQuizzes),
                'max' => 25
            ],
            
            // Category exploration achievements
            [
                'id' => 'explorer_3',
                'name' => 'Subject Explorer',
                'description' => 'Try quizzes in 3 different categories',
                'icon' => 'globe',
                'unlocked' => $categoryCount >= 3,
                'progress' => min(3, $categoryCount),
                'max' => 3
            ],
            [
                'id' => 'explorer_5',
                'name' => 'Knowledge Explorer',
                'description' => 'Try quizzes in 5 different categories',
                'icon' => 'globe',
                'unlocked' => $categoryCount >= 5,
                'progress' => min(5, $categoryCount),
                'max' => 5
            ],
            [
                'id' => 'explorer_8',
                'name' => 'Renaissance Learner',
                'description' => 'Try quizzes in 8 different categories',
                'icon' => 'globe',
                'unlocked' => $categoryCount >= 8,
                'progress' => min(8, $categoryCount),
                'max' => 8
            ],
            [
                'id' => 'explorer_all',
                'name' => 'Complete Explorer',
                'description' => 'Try quizzes in all available categories',
                'icon' => 'globe',
                'unlocked' => $categoryCount >= 10, // Assuming there are 10 total categories
                'progress' => min(10, $categoryCount),
                'max' => 10
            ],
            
            // Streak achievements
            [
                'id' => 'streak_3',
                'name' => 'Learning Habit',
                'description' => 'Learn for 3 days in a row',
                'icon' => 'fire',
                'unlocked' => $longestStreak >= 3,
                'progress' => min(3, $longestStreak),
                'max' => 3
            ],
            [
                'id' => 'streak_7',
                'name' => 'Weekly Warrior',
                'description' => 'Learn for 7 days in a row',
                'icon' => 'fire',
                'unlocked' => $longestStreak >= 7,
                'progress' => min(7, $longestStreak),
                'max' => 7
            ],
            [
                'id' => 'streak_14',
                'name' => 'Fortnight Focus',
                'description' => 'Learn for 14 days in a row',
                'icon' => 'fire',
                'unlocked' => $longestStreak >= 14,
                'progress' => min(14, $longestStreak),
                'max' => 14
            ],
            [
                'id' => 'streak_30',
                'name' => 'Monthly Momentum',
                'description' => 'Learn for 30 days in a row',
                'icon' => 'fire',
                'unlocked' => $longestStreak >= 30,
                'progress' => min(30, $longestStreak),
                'max' => 30
            ],
            
            // Active days achievements
            [
                'id' => 'active_7',
                'name' => 'Active Learner',
                'description' => 'Learn on 7 different days',
                'icon' => 'calendar',
                'unlocked' => $activeDays >= 7,
                'progress' => min(7, $activeDays),
                'max' => 7
            ],
            [
                'id' => 'active_14',
                'name' => 'Committed Student',
                'description' => 'Learn on 14 different days',
                'icon' => 'calendar',
                'unlocked' => $activeDays >= 14,
                'progress' => min(14, $activeDays),
                'max' => 14
            ],
            [
                'id' => 'active_21',
                'name' => 'Dedicated Learner',
                'description' => 'Learn on 21 different days',
                'icon' => 'calendar',
                'unlocked' => $activeDays >= 21,
                'progress' => min(21, $activeDays),
                'max' => 21
            ],
            
            // Speed achievements
            [
                'id' => 'speed_quick',
                'name' => 'Quick Thinker',
                'description' => 'Answer questions in less than 15 seconds on average',
                'icon' => 'clock',
                'unlocked' => $avgTimePerQuestion > 0 && $avgTimePerQuestion <= 15,
                'progress' => $avgTimePerQuestion > 0 ? min(15, 15 - max(0, $avgTimePerQuestion - 15)) : 0,
                'max' => 15
            ],
            [
                'id' => 'speed_lightning',
                'name' => 'Lightning Fast',
                'description' => 'Complete a 10+ question quiz in under 2 minutes',
                'icon' => 'clock',
                'unlocked' => $fastestQuizTime > 0 && $fastestQuizTime <= 120,
                'progress' => $fastestQuizTime > 0 ? min(120, 120 - max(0, $fastestQuizTime - 120)) : 0,
                'max' => 120
            ],
            
            // Quiz type achievements
            [
                'id' => 'quiz_variety',
                'name' => 'Quiz Variety',
                'description' => 'Try all quiz types (Quick, Adaptive, Test, Spaced)',
                'icon' => 'puzzle',
                'unlocked' => $quizTypesCount >= 4,
                'progress' => min(4, $quizTypesCount),
                'max' => 4
            ],
            [
                'id' => 'adaptive_master',
                'name' => 'Adaptive Master',
                'description' => 'Complete 10 adaptive quizzes',
                'icon' => 'chart',
                'unlocked' => $adaptiveQuizzes >= 10,
                'progress' => min(10, $adaptiveQuizzes),
                'max' => 10
            ],
            
            // Difficulty achievements
            [
                'id' => 'hard_5',
                'name' => 'Challenge Accepted',
                'description' => 'Answer 5 hard questions correctly',
                'icon' => 'trophy',
                'unlocked' => $hardQuestionsCorrect >= 5,
                'progress' => min(5, $hardQuestionsCorrect),
                'max' => 5
            ],
            [
                'id' => 'hard_25',
                'name' => 'Hard Mode',
                'description' => 'Answer 25 hard questions correctly',
                'icon' => 'trophy',
                'unlocked' => $hardQuestionsCorrect >= 25,
                'progress' => min(25, $hardQuestionsCorrect),
                'max' => 25
            ],
            [
                'id' => 'hard_100',
                'name' => 'Difficulty Destroyer',
                'description' => 'Answer 100 hard questions correctly',
                'icon' => 'trophy',
                'unlocked' => $hardQuestionsCorrect >= 100,
                'progress' => min(100, $hardQuestionsCorrect),
                'max' => 100
            ],
            
            // Mastery score achievements
            [
                'id' => 'novice_scholar',
                'name' => 'Novice Scholar',
                'description' => 'Reach 50,000 mastery points',
                'icon' => 'badge',
                'unlocked' => $masteryScore >= 50000,
                'progress' => min(50000, $masteryScore),
                'max' => 50000
            ],
            [
                'id' => 'intermediate_scholar',
                'name' => 'Intermediate Scholar',
                'description' => 'Reach 250,000 mastery points',
                'icon' => 'badge',
                'unlocked' => $masteryScore >= 250000,
                'progress' => min(250000, $masteryScore),
                'max' => 250000
            ],
            [
                'id' => 'advanced_scholar',
                'name' => 'Advanced Scholar',
                'description' => 'Reach 500,000 mastery points',
                'icon' => 'fire',
                'unlocked' => $masteryScore >= 500000,
                'progress' => min(500000, $masteryScore),
                'max' => 500000
            ],
            [
                'id' => 'expert_scholar',
                'name' => 'Expert Scholar',
                'description' => 'Reach 1,000,000 mastery points',
                'icon' => 'badge',
                'unlocked' => $masteryScore >= 1000000,
                'progress' => min(1000000, $masteryScore),
                'max' => 1000000
            ],
            [
                'id' => 'master_scholar',
                'name' => 'Master Scholar',
                'description' => 'Reach 2,000,000 mastery points',
                'icon' => 'trophy',
                'unlocked' => $masteryScore >= 2000000,
                'progress' => min(2000000, $masteryScore),
                'max' => 2000000
            ],
            
            // Milestone achievements
            [
                'id' => 'long_journey',
                'name' => 'Long Journey',
                'description' => 'Answer 1000+ questions with 80%+ accuracy',
                'icon' => 'map',
                'unlocked' => $totalAnswers >= 1000 && ($correctAnswers / $totalAnswers) >= 0.8,
                'progress' => $totalAnswers >= 1000 ? 
                              ($correctAnswers / $totalAnswers >= 0.8 ? 100 : round(($correctAnswers / $totalAnswers) * 100)) : 
                              round(($totalAnswers / 1000) * 100),
                'max' => 100
            ],
            [
                'id' => 'medical_expert',
                'name' => 'Medical Expert',
                'description' => 'Master all medical categories with 90%+ accuracy',
                'icon' => 'heart',
                'unlocked' => $categoryCount >= 4 && ($correctAnswers / $totalAnswers) >= 0.9,
                'progress' => $categoryCount >= 4 ? 
                              ($correctAnswers / $totalAnswers >= 0.9 ? 100 : round(($correctAnswers / $totalAnswers) * 100)) : 
                              round(($categoryCount / 4) * 100),
                'max' => 100
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