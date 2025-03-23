<?php
/**
 * Get top performers for a specific week
 * 
 * @param PDO $pdo Database connection
 * @param int $weeksAgo Number of weeks ago (0 = current week)
 * @param int $limit Maximum number of performers to return
 * @return array Array of top performers with enhanced mastery scores
 */
function getTopPerformers($pdo, $weeksAgo = 0, $limit = 5) {
    try {
        // Calculate start and end dates for the week in a more reliable way
        // Using first day of week (Monday) and last day of week (Sunday)
        $today = new DateTime();
        $dayOfWeek = $today->format('N'); // 1 (Monday) through 7 (Sunday)
        
        // Calculate days to subtract to get to Monday of this week
        $daysToMonday = $dayOfWeek - 1;
        
        // Calculate the start date (Monday of the target week)
        $startDay = clone $today;
        $startDay->sub(new DateInterval('P' . ($daysToMonday + (7 * $weeksAgo)) . 'D'));
        $startDate = $startDay->format('Y-m-d');
        
        // Calculate the end date (Sunday of the target week)
        $endDay = clone $startDay;
        $endDay->add(new DateInterval('P6D'));
        $endDay->setTime(23, 59, 59);
        $endDate = $endDay->format('Y-m-d H:i:s');
        
        // Log the date range for debugging
        error_log("Calculating leaderboard for date range: $startDate to $endDate");
        
        // Query to get user performance with difficulty factored in
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.first_name,
                u.last_name,
                COUNT(ua.id) as quiz_count,
                SUM(ua.total_questions) as total_questions,
                SUM(ua.correct_answers) as correct_answers,
                ROUND((SUM(ua.correct_answers) / SUM(ua.total_questions)) * 100, 1) as accuracy,
                AVG(COALESCE(q.difficulty_value, 3.0)) as avg_difficulty
            FROM users u
            JOIN user_attempts ua ON u.id = ua.user_id
            LEFT JOIN quiz_answers qa ON ua.user_id = qa.user_id AND qa.created_at BETWEEN ? AND ?
            LEFT JOIN questions q ON qa.question_id = q.id
            WHERE ua.created_at BETWEEN ? AND ?
            GROUP BY u.id
            HAVING total_questions > 0
            ORDER BY (SUM(ua.correct_answers) / SUM(ua.total_questions) * SUM(ua.total_questions) * AVG(COALESCE(q.difficulty_value, 3.0))) DESC
            LIMIT ?
        ");
        
        $stmt->execute([$startDate, $endDate, $startDate, $endDate, $limit]);
        $performers = $stmt->fetchAll();
        
        // Calculate enhanced mastery score for each performer
        foreach ($performers as &$performer) {
            // Use difficulty as a multiplier, with a minimum value of 1.0 to avoid reducing scores
            $difficultyMultiplier = max(1.0, ($performer['avg_difficulty'] ?? 1.0));
            
            // New mastery formula: questions × accuracy × difficulty multiplier
            $performer['mastery_score'] = round(
                $performer['total_questions'] * 
                ($performer['accuracy'] / 100) * 
                $difficultyMultiplier
            );
            
            // Add week identifier
            if ($weeksAgo == 0) {
                $performer['week'] = 'This Week';
                $performer['date_range'] = $startDay->format('M j') . ' - ' . $endDay->format('M j, Y');
            } else if ($weeksAgo == 1) {
                $performer['week'] = 'Last Week';
                $performer['date_range'] = $startDay->format('M j') . ' - ' . $endDay->format('M j, Y');
            } else {
                $performer['week'] = "$weeksAgo Weeks Ago";
                $performer['date_range'] = $startDay->format('M j') . ' - ' . $endDay->format('M j, Y');
            }
        }
        
        return $performers;
    } catch (PDOException $e) {
        error_log("Leaderboard error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user's rank for a specific week
 * 
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @param int $weeksAgo Number of weeks ago (0 = current week)
 * @return array|null User's rank data or null if not found
 */
function getUserRankForWeek($pdo, $userId, $weeksAgo = 0) {
    try {
        // Use the same date calculation method as getTopPerformers
        $today = new DateTime();
        $dayOfWeek = $today->format('N'); // 1 (Monday) through 7 (Sunday)
        
        // Calculate days to subtract to get to Monday of this week
        $daysToMonday = $dayOfWeek - 1;
        
        // Calculate the start date (Monday of the target week)
        $startDay = clone $today;
        $startDay->sub(new DateInterval('P' . ($daysToMonday + (7 * $weeksAgo)) . 'D'));
        $startDate = $startDay->format('Y-m-d');
        
        // Calculate the end date (Sunday of the target week)
        $endDay = clone $startDay;
        $endDay->add(new DateInterval('P6D'));
        $endDay->setTime(23, 59, 59);
        $endDate = $endDay->format('Y-m-d H:i:s');
        
        // Get all users' performance for ranking
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                SUM(ua.total_questions) as total_questions,
                SUM(ua.correct_answers) as correct_answers,
                AVG(COALESCE(q.difficulty_value, 3.0)) as avg_difficulty,
                (SUM(ua.correct_answers) / SUM(ua.total_questions) * SUM(ua.total_questions) * AVG(COALESCE(q.difficulty_value, 3.0))) as mastery
            FROM users u
            JOIN user_attempts ua ON u.id = ua.user_id
            LEFT JOIN quiz_answers qa ON ua.user_id = qa.user_id AND qa.created_at BETWEEN ? AND ?
            LEFT JOIN questions q ON qa.question_id = q.id
            WHERE ua.created_at BETWEEN ? AND ?
            GROUP BY u.id
            HAVING total_questions > 0
            ORDER BY mastery DESC
        ");
        
        $stmt->execute([$startDate, $endDate, $startDate, $endDate]);
        $allUsers = $stmt->fetchAll();
        
        // Find user's rank and data
        $userRank = null;
        $userMasteryScore = 0;
        
        foreach ($allUsers as $index => $user) {
            if ($user['id'] == $userId) {
                $userRank = $index + 1;
                
                // Calculate mastery score with difficulty factored in
                $difficultyMultiplier = max(1.0, ($user['avg_difficulty'] ?? 1.0));
                $userMasteryScore = round(
                    $user['total_questions'] * 
                    ($user['correct_answers'] / $user['total_questions']) * 
                    $difficultyMultiplier
                );
                
                return [
                    'rank' => $userRank,
                    'mastery_score' => $userMasteryScore,
                    'total_users' => count($allUsers)
                ];
            }
        }
        
        return null;
    } catch (PDOException $e) {
        error_log("User rank error: " . $e->getMessage());
        return null;
    }
}

/**
 * Compare user's performance between two weeks
 * 
 * @param PDO $pdo Database connection
 * @param int $userId User ID
 * @param int $weeksAgo1 First week (default = 0, current week)
 * @param int $weeksAgo2 Second week to compare against (default = 1, last week)
 * @return array Comparison data including change in mastery score and rank
 */
function compareUserWeeklyPerformance($pdo, $userId, $weeksAgo1 = 0, $weeksAgo2 = 1) {
    $week1Data = getUserRankForWeek($pdo, $userId, $weeksAgo1);
    $week2Data = getUserRankForWeek($pdo, $userId, $weeksAgo2);
    
    return [
        'current' => $week1Data,
        'previous' => $week2Data,
        'score_change' => $week1Data ? ($week2Data ? $week1Data['mastery_score'] - $week2Data['mastery_score'] : $week1Data['mastery_score']) : 0,
        'rank_change' => $week1Data && $week2Data ? $week2Data['rank'] - $week1Data['rank'] : 0
    ];
}