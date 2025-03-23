<?php
/**
 * Recommendation Service
 * 
 * Generates personalized quiz recommendations for users
 */
class RecommendationService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Generate quiz recommendations for a user
     * 
     * @param int $userId
     * @return array
     */
    public function generateRecommendations($userId) {
        $recommendations = [];
        
        // Get categories the user has struggled with
        $strugglingCategories = $this->getStrugglingCategories($userId);
        if ($strugglingCategories) {
            $recommendations[] = [
                'type' => 'improvement',
                'title' => 'Need Improvement',
                'description' => 'Focus on these categories to improve your knowledge',
                'categories' => $strugglingCategories
            ];
        }
        
        // Get categories the user hasn't tried yet
        $untouchedCategories = $this->getUntouchedCategories($userId);
        if ($untouchedCategories) {
            $recommendations[] = [
                'type' => 'new',
                'title' => 'Unexplored Categories',
                'description' => 'Try these categories to expand your knowledge',
                'categories' => $untouchedCategories
            ];
        }
        
        // Get categories the user excels at (for reinforcement)
        $excellingCategories = $this->getExcellingCategories($userId);
        if ($excellingCategories) {
            $recommendations[] = [
                'type' => 'mastery',
                'title' => 'Maintain Mastery',
                'description' => 'Keep your knowledge sharp in these categories',
                'categories' => $excellingCategories
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Get categories the user is struggling with
     * 
     * @param int $userId
     * @return array
     */
    private function getStrugglingCategories($userId) {
        $sql = "SELECT 
                c.id,
                c.name,
                COUNT(qa.id) as total_questions,
                SUM(qa.is_correct) as correct_answers,
                (SUM(qa.is_correct) / COUNT(qa.id)) * 100 as accuracy
                FROM categories c
                JOIN questions q ON c.id = q.category_id
                JOIN quiz_answers qa ON q.id = qa.question_id
                WHERE qa.user_id = ?
                GROUP BY c.id
                HAVING total_questions >= 5 AND accuracy < 60
                ORDER BY accuracy ASC
                LIMIT 3";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get categories the user hasn't tried yet
     * 
     * @param int $userId
     * @return array
     */
    private function getUntouchedCategories($userId) {
        $sql = "SELECT c.id, c.name
                FROM categories c
                WHERE c.is_active = 1
                AND c.id NOT IN (
                    SELECT DISTINCT q.category_id
                    FROM quiz_answers qa
                    JOIN questions q ON qa.question_id = q.id
                    WHERE qa.user_id = ?
                )
                ORDER BY RAND()
                LIMIT 3";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get categories the user excels at
     * 
     * @param int $userId
     * @return array
     */
    private function getExcellingCategories($userId) {
        $sql = "SELECT 
                c.id,
                c.name,
                COUNT(qa.id) as total_questions,
                SUM(qa.is_correct) as correct_answers,
                (SUM(qa.is_correct) / COUNT(qa.id)) * 100 as accuracy
                FROM categories c
                JOIN questions q ON c.id = q.category_id
                JOIN quiz_answers qa ON q.id = qa.question_id
                WHERE qa.user_id = ?
                GROUP BY c.id
                HAVING total_questions >= 10 AND accuracy >= 80
                ORDER BY accuracy DESC
                LIMIT 3";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get practice questions based on user's history
     * 
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getPracticeQuestions($userId, $limit = 5) {
        // Get questions the user has answered incorrectly
        $sql = "SELECT 
                q.id,
                q.question_text,
                q.category_id,
                c.name as category_name,
                q.difficulty_value
                FROM questions q
                JOIN quiz_answers qa ON q.id = qa.question_id
                JOIN categories c ON q.category_id = c.id
                WHERE qa.user_id = ? AND qa.is_correct = 0
                GROUP BY q.id
                ORDER BY RAND()
                LIMIT ?";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $limit]);
        
        $questions = $stmt->fetchAll();
        
        // If we don't have enough questions, add some from categories the user is struggling with
        if (count($questions) < $limit) {
            $neededQuestions = $limit - count($questions);
            
            // Get IDs of already selected questions
            $selectedIds = array_column($questions, 'id');
            $idPlaceholders = implode(',', array_fill(0, count($selectedIds), '?'));
            
            $idParams = $selectedIds;
            
            // Get struggling category IDs
            $strugglingCategories = $this->getStrugglingCategories($userId);
            $categoryIds = array_column($strugglingCategories, 'id');
            
            if (empty($categoryIds)) {
                // If no struggling categories, get random active categories
                $sql = "SELECT id FROM categories WHERE is_active = 1 ORDER BY RAND() LIMIT 3";
                $stmt = $this->db->prepare($sql);
                $stmt->execute();
                $categoryIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            
            $categoryPlaceholders = implode(',', array_fill(0, count($categoryIds), '?'));
            
            // Get additional questions
            $sql = "SELECT 
                    q.id,
                    q.question_text,
                    q.category_id,
                    c.name as category_name,
                    q.difficulty_value
                    FROM questions q
                    JOIN categories c ON q.category_id = c.id
                    WHERE q.category_id IN ($categoryPlaceholders)";
                    
            if (!empty($selectedIds)) {
                $sql .= " AND q.id NOT IN ($idPlaceholders)";
            }
            
            $sql .= " ORDER BY RAND() LIMIT ?";
            
            $params = array_merge($categoryIds, $selectedIds, [$neededQuestions]);
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $additionalQuestions = $stmt->fetchAll();
            
            $questions = array_merge($questions, $additionalQuestions);
        }
        
        // Get answers for each question
        $questionModel = new QuestionModel();
        
        foreach ($questions as &$question) {
            $question = $questionModel->getWithAnswers($question['id']);
        }
        
        return $questions;
    }
}