<?php
/**
 * Achievement Service
 * 
 * Handles user achievements and badges
 */
class AchievementService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get all achievements
     * 
     * @return array
     */
    public function getAllAchievements() {
        $sql = "SELECT * FROM achievements ORDER BY required_value";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get user's achievements
     * 
     * @param int $userId
     * @return array
     */
    public function getUserAchievements($userId) {
        // Get all achievements
        $achievements = $this->getAllAchievements();
        
        // Get user's completed achievements
        $sql = "SELECT achievement_id, achieved_at FROM user_achievements WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        $userAchievements = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Check which achievements the user is eligible for
        $analyticsService = new AnalyticsService();
        $performanceData = $analyticsService->getPerformanceData($userId);
        
        $result = [
            'completed' => [],
            'inProgress' => []
        ];
        
        foreach ($achievements as $achievement) {
            $achievementData = [
                'id' => $achievement['id'],
                'name' => $achievement['name'],
                'description' => $achievement['description'],
                'icon' => $achievement['icon'],
                'required_value' => $achievement['required_value']
            ];
            
            // Check if user has already achieved this
            if (isset($userAchievements[$achievement['id']])) {
                $achievementData['achieved_at'] = $userAchievements[$achievement['id']];
                $result['completed'][] = $achievementData;
                continue;
            }
            
            // Check if user is eligible for this achievement
            $currentValue = $this->getProgressForAchievement($userId, $achievement, $performanceData);
            $achievementData['current_value'] = $currentValue;
            $achievementData['progress'] = min(100, round(($currentValue / $achievement['required_value']) * 100));
            
            if ($currentValue >= $achievement['required_value']) {
                // User is eligible, award the achievement
                $this->awardAchievement($userId, $achievement['id']);
                $achievementData['achieved_at'] = date('Y-m-d H:i:s');
                $result['completed'][] = $achievementData;
            } else {
                $result['inProgress'][] = $achievementData;
            }
        }
        
        return $result;
    }
    
    /**
     * Award an achievement to a user
     * 
     * @param int $userId
     * @param int $achievementId
     * @return bool
     */
    private function awardAchievement($userId, $achievementId) {
        $sql = "INSERT IGNORE INTO user_achievements (user_id, achievement_id, achieved_at)
                VALUES (?, ?, NOW())";
                
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$userId, $achievementId]);
    }
    
    /**
     * Get progress for a specific achievement
     * 
     * @param int $userId
     * @param array $achievement
     * @param array $performanceData
     * @return int
     */
    private function getProgressForAchievement($userId, $achievement, $performanceData) {
        $type = $achievement['type'];
        
        switch ($type) {
            case 'total_quizzes':
                return $performanceData['totalQuizzes'];
                
            case 'total_questions':
                return $performanceData['totalQuestions'];
                
            case 'accuracy':
                return $performanceData['accuracy'];
                
            case 'streak':
                return $this->getUserStreak($userId);
                
            case 'perfect_quizzes':
                return $this->countPerfectQuizzes($userId);
                
            case 'category_mastery':
                return $this->countCategoryMasteries($userId);
                
            default:
                return 0;
        }
    }
    
    /**
     * Get user's current streak
     * 
     * @param int $userId
     * @return int
     */
    private function getUserStreak($userId) {
        // Get distinct dates the user has taken quizzes
        $sql = "SELECT DISTINCT DATE(created_at) as quiz_date
                FROM user_attempts
                WHERE user_id = ?
                ORDER BY quiz_date DESC";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($dates)) {
            return 0;
        }
        
        // Check for consecutive days
        $streak = 1;
        $today = date('Y-m-d');
        $expectedDate = $today;
        
        foreach ($dates as $date) {
            if ($date == $expectedDate) {
                $expectedDate = date('Y-m-d', strtotime($date . ' -1 day'));
            } elseif ($date == date('Y-m-d', strtotime($today . ' -1 day')) && $today > $dates[0]) {
                // First date in the sequence is yesterday, and no quiz today
                $expectedDate = date('Y-m-d', strtotime($date . ' -1 day'));
            } else {
                // Streak is broken
                break;
            }
            
            $streak++;
        }
        
        return $streak - 1; // Adjust for the initial value
    }
    
    /**
     * Count perfect quizzes (100% correct)
     * 
     * @param int $userId
     * @return int
     */
    private function countPerfectQuizzes($userId) {
        $sql = "SELECT COUNT(*) FROM user_attempts
                WHERE user_id = ?
                AND correct_answers = total_questions
                AND total_questions >= 5";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetchColumn();
    }
    
    /**
     * Count category masteries (categories with >90% accuracy and at least 20 questions)
     * 
     * @param int $userId
     * @return int
     */
    private function countCategoryMasteries($userId) {
        $sql = "SELECT COUNT(*) FROM (
                SELECT c.id, COUNT(qa.id) as total, SUM(qa.is_correct) as correct,
                (SUM(qa.is_correct) / COUNT(qa.id)) * 100 as accuracy
                FROM categories c
                JOIN questions q ON c.id = q.category_id
                JOIN quiz_answers qa ON q.id = qa.question_id
                WHERE qa.user_id = ?
                GROUP BY c.id
                HAVING total >= 20 AND accuracy >= 90
                ) as mastered_categories";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetchColumn();
    }
    
    /**
     * Check for new achievements after a quiz
     * 
     * @param int $userId
     * @return array Newly awarded achievements
     */
    public function checkForNewAchievements($userId) {
        $before = $this->getUserCompletedAchievementIds($userId);
        $after = $this->getUserAchievements($userId)['completed'];
        
        $afterIds = array_column($after, 'id');
        
        // Find new achievements
        $newAchievements = [];
        
        foreach ($after as $achievement) {
            if (!in_array($achievement['id'], $before)) {
                $newAchievements[] = $achievement;
            }
        }
        
        return $newAchievements;
    }
    
    /**
     * Get IDs of user's completed achievements
     * 
     * @param int $userId
     * @return array
     */
    private function getUserCompletedAchievementIds($userId) {
        $sql = "SELECT achievement_id FROM user_achievements WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}