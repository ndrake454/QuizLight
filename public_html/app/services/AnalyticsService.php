<?php
/**
 * Analytics Service
 * 
 * Provides performance analytics and statistics
 */
class AnalyticsService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get performance data for a user
     * 
     * @param int $userId
     * @return array
     */
    public function getPerformanceData($userId) {
        $data = [
            'totalQuizzes' => $this->getTotalQuizzes($userId),
            'totalQuestions' => $this->getTotalQuestions($userId),
            'accuracy' => $this->getOverallAccuracy($userId),
            'weeklyProgress' => $this->getWeeklyProgress($userId),
            'categoryPerformance' => $this->getCategoryPerformance($userId),
            'strengths' => $this->getUserStrengths($userId),
            'weaknesses' => $this->getUserWeaknesses($userId)
        ];
        
        return $data;
    }
    
    /**
     * Get total number of quizzes completed by a user
     * 
     * @param int $userId
     * @return int
     */
    private function getTotalQuizzes($userId) {
        $sql = "SELECT COUNT(*) FROM user_attempts WHERE user_id = ? AND completed_at IS NOT NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetchColumn();
    }
    
    /**
     * Get total number of questions answered by a user
     * 
     * @param int $userId
     * @return int
     */
    private function getTotalQuestions($userId) {
        $sql = "SELECT COUNT(*) FROM quiz_answers WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetchColumn();
    }
    
    /**
     * Get overall accuracy for a user
     * 
     * @param int $userId
     * @return float
     */
    private function getOverallAccuracy($userId) {
        $sql = "SELECT 
                COUNT(*) as total,
                SUM(is_correct) as correct,
                IFNULL((SUM(is_correct) / COUNT(*)) * 100, 0) as accuracy
                FROM quiz_answers
                WHERE user_id = ?";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        $result = $stmt->fetch();
        
        return $result['accuracy'] ?? 0;
    }
    
    /**
     * Get weekly progress for a user
     * 
     * @param int $userId
     * @return array
     */
    private function getWeeklyProgress($userId) {
        // Get data for the last 8 weeks
        $weeks = [];
        
        for ($i = 7; $i >= 0; $i--) {
            $startDate = date('Y-m-d', strtotime("-$i weeks Monday"));
            $endDate = date('Y-m-d', strtotime("-$i weeks Sunday"));
            
            $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(is_correct) as correct,
                    IFNULL((SUM(is_correct) / COUNT(*)) * 100, 0) as accuracy
                    FROM quiz_answers
                    WHERE user_id = ?
                    AND created_at BETWEEN ? AND ?";
                    
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $userId,
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ]);
            
            $result = $stmt->fetch();
            
            $weeks[] = [
                'week' => date('M j', strtotime($startDate)),
                'questions' => $result['total'],
                'accuracy' => round($result['accuracy'], 1)
            ];
        }
        
        return $weeks;
    }
    
    /**
     * Get category performance for a user
     * 
     * @param int $userId
     * @return array
     */
    private function getCategoryPerformance($userId) {
        $sql = "SELECT 
                c.id,
                c.name,
                COUNT(qa.id) as total,
                SUM(qa.is_correct) as correct,
                IFNULL((SUM(qa.is_correct) / COUNT(qa.id)) * 100, 0) as accuracy
                FROM categories c
                JOIN questions q ON c.id = q.category_id
                JOIN quiz_answers qa ON q.id = qa.question_id
                WHERE qa.user_id = ?
                GROUP BY c.id
                HAVING total >= 5
                ORDER BY accuracy DESC";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get user's strengths (categories with highest accuracy)
     * 
     * @param int $userId
     * @return array
     */
    private function getUserStrengths($userId) {
        $sql = "SELECT 
                c.id,
                c.name,
                COUNT(qa.id) as total,
                SUM(qa.is_correct) as correct,
                IFNULL((SUM(qa.is_correct) / COUNT(qa.id)) * 100, 0) as accuracy
                FROM categories c
                JOIN questions q ON c.id = q.category_id
                JOIN quiz_answers qa ON q.id = qa.question_id
                WHERE qa.user_id = ?
                GROUP BY c.id
                HAVING total >= 10
                ORDER BY accuracy DESC
                LIMIT 3";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get user's weaknesses (categories with lowest accuracy)
     * 
     * @param int $userId
     * @return array
     */
    private function getUserWeaknesses($userId) {
        $sql = "SELECT 
                c.id,
                c.name,
                COUNT(qa.id) as total,
                SUM(qa.is_correct) as correct,
                IFNULL((SUM(qa.is_correct) / COUNT(qa.id)) * 100, 0) as accuracy
                FROM categories c
                JOIN questions q ON c.id = q.category_id
                JOIN quiz_answers qa ON q.id = qa.question_id
                WHERE qa.user_id = ?
                GROUP BY c.id
                HAVING total >= 5
                ORDER BY accuracy ASC
                LIMIT 3";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get performance comparison between two time periods
     * 
     * @param int $userId
     * @param string $period1Start
     * @param string $period1End
     * @param string $period2Start
     * @param string $period2End
     * @return array
     */
    public function comparePerformance($userId, $period1Start, $period1End, $period2Start, $period2End) {
        $period1Data = $this->getPeriodPerformance($userId, $period1Start, $period1End);
        $period2Data = $this->getPeriodPerformance($userId, $period2Start, $period2End);
        
        return [
            'period1' => $period1Data,
            'period2' => $period2Data,
            'questionDifference' => $period2Data['total'] - $period1Data['total'],
            'accuracyDifference' => $period2Data['accuracy'] - $period1Data['accuracy']
        ];
    }
    
    /**
     * Get performance data for a specific time period
     * 
     * @param int $userId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    private function getPeriodPerformance($userId, $startDate, $endDate) {
        $sql = "SELECT 
                COUNT(*) as total,
                SUM(is_correct) as correct,
                IFNULL((SUM(is_correct) / COUNT(*)) * 100, 0) as accuracy
                FROM quiz_answers
                WHERE user_id = ?
                AND created_at BETWEEN ? AND ?";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $userId,
            $startDate . ' 00:00:00',
            $endDate . ' 23:59:59'
        ]);
        
        return $stmt->fetch();
    }
}