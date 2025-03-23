<?php
/**
 * Leaderboard Service
 * 
 * Handles leaderboard and ranking functionality
 */
class LeaderboardService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get top performers for a specific week
     * 
     * @param int $weeksAgo Number of weeks ago (0 = current week)
     * @param int $limit Number of users to return
     * @return array
     */
    public function getTopPerformers($weeksAgo = 0, $limit = 10) {
        // Calculate the date range for the specified week
        $mondayThisWeek = date('Y-m-d', strtotime("monday this week - {$weeksAgo} week"));
        $sundayThisWeek = date('Y-m-d', strtotime("sunday this week - {$weeksAgo} week"));
        
        // Format date range for display
        $dateRange = date('M j', strtotime($mondayThisWeek)) . ' - ' . date('M j, Y', strtotime($sundayThisWeek));
        
        $sql = "SELECT 
                u.id,
                u.first_name,
                u.last_name,
                COUNT(qa.id) as total_questions,
                SUM(qa.is_correct) as correct_answers,
                (SUM(qa.is_correct) / COUNT(qa.id)) * 100 as accuracy,
                (
                    SUM(qa.is_correct) * 
                    SUM(CASE WHEN q.difficulty_value <= 2.0 THEN 500
                        WHEN q.difficulty_value <= 4.0 THEN 1000
                        ELSE 2000 END)
                ) as mastery_score
                FROM users u
                JOIN quiz_answers qa ON u.id = qa.user_id
                JOIN questions q ON qa.question_id = q.id
                WHERE qa.created_at BETWEEN ? AND ?
                GROUP BY u.id
                HAVING total_questions >= 10
                ORDER BY mastery_score DESC
                LIMIT ?";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $mondayThisWeek . ' 00:00:00',
            $sundayThisWeek . ' 23:59:59',
            $limit
        ]);
        
        $performers = $stmt->fetchAll();
        
        // Add date range to each performer
        foreach ($performers as &$performer) {
            $performer['date_range'] = $dateRange;
            
            // Round the mastery score to the nearest hundred
            $performer['mastery_score'] = round($performer['mastery_score'], -2);
        }
        
        return $performers;
    }
    
    /**
     * Compare user's weekly performance with previous week
     * 
     * @param int $userId
     * @return array
     */
    public function compareUserWeeklyPerformance($userId) {
        $currentWeekData = $this->getUserWeekPerformance($userId, 0);
        $previousWeekData = $this->getUserWeekPerformance($userId, 1);
        
        return [
            'current' => $currentWeekData,
            'previous' => $previousWeekData
        ];
    }
    
    /**
     * Get user's performance for a specific week
     * 
     * @param int $userId
     * @param int $weeksAgo
     * @return array
     */
    private function getUserWeekPerformance($userId, $weeksAgo = 0) {
        // Calculate the date range for the specified week
        $mondayThisWeek = date('Y-m-d', strtotime("monday this week - {$weeksAgo} week"));
        $sundayThisWeek = date('Y-m-d', strtotime("sunday this week - {$weeksAgo} week"));
        
        // Get the user's mastery score for the week
        $sql = "SELECT 
                COUNT(qa.id) as total_questions,
                SUM(qa.is_correct) as correct_answers,
                (SUM(qa.is_correct) / COUNT(qa.id)) * 100 as accuracy,
                (
                    SUM(qa.is_correct) * 
                    SUM(CASE WHEN q.difficulty_value <= 2.0 THEN 500
                        WHEN q.difficulty_value <= 4.0 THEN 1000
                        ELSE 2000 END)
                ) as mastery_score
                FROM quiz_answers qa
                JOIN questions q ON qa.question_id = q.id
                WHERE qa.user_id = ?
                AND qa.created_at BETWEEN ? AND ?";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $userId,
            $mondayThisWeek . ' 00:00:00',
            $sundayThisWeek . ' 23:59:59'
        ]);
        
        $data = $stmt->fetch();
        
        if (!$data || !$data['total_questions']) {
            return [
                'total_questions' => 0,
                'correct_answers' => 0,
                'accuracy' => 0,
                'mastery_score' => 0,
                'rank' => null
            ];
        }
        
        // Round the mastery score to the nearest hundred
        $data['mastery_score'] = round($data['mastery_score'], -2);
        
        // Calculate rank
        $sql = "SELECT COUNT(*) + 1 as rank FROM (
                    SELECT 
                    u.id,
                    (
                        SUM(qa.is_correct) * 
                        SUM(CASE WHEN q.difficulty_value <= 2.0 THEN 500
                            WHEN q.difficulty_value <= 4.0 THEN 1000
                            ELSE 2000 END)
                    ) as mastery_score
                    FROM users u
                    JOIN quiz_answers qa ON u.id = qa.user_id
                    JOIN questions q ON qa.question_id = q.id
                    WHERE qa.created_at BETWEEN ? AND ?
                    GROUP BY u.id
                    HAVING COUNT(qa.id) >= 10 AND mastery_score > ?
                ) as ranks";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $mondayThisWeek . ' 00:00:00',
            $sundayThisWeek . ' 23:59:59',
            $data['mastery_score']
        ]);
        
        $data['rank'] = $stmt->fetchColumn();
        
        return $data;
    }
    
    /**
     * Get user's mastery scores
     * 
     * @param int $userId
     * @return array
     */
    public function getUserMasteryScores($userId) {
        // Weekly score
        $weeklyData = $this->getUserWeekPerformance($userId);
        
        // All-time score
        $sql = "SELECT 
                COUNT(qa.id) as total_questions,
                SUM(qa.is_correct) as correct_answers,
                (SUM(qa.is_correct) / COUNT(qa.id)) * 100 as accuracy,
                (
                    SUM(qa.is_correct) * 
                    SUM(CASE WHEN q.difficulty_value <= 2.0 THEN 500
                        WHEN q.difficulty_value <= 4.0 THEN 1000
                        ELSE 2000 END)
                ) as mastery_score
                FROM quiz_answers qa
                JOIN questions q ON qa.question_id = q.id
                WHERE qa.user_id = ?";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        $allTimeData = $stmt->fetch();
        
        if (!$allTimeData || !$allTimeData['total_questions']) {
            $allTimeData = [
                'mastery_score' => 0
            ];
        } else {
            // Round the mastery score to the nearest hundred
            $allTimeData['mastery_score'] = round($allTimeData['mastery_score'], -2);
        }
        
        return [
            'weekly' => $weeklyData['mastery_score'],
            'allTime' => $allTimeData['mastery_score']
        ];
    }
}