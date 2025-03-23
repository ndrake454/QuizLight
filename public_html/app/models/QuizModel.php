<?php
/**
 * Quiz Model
 */
class QuizModel extends BaseModel {
    protected $table = 'user_attempts';
    
    /**
     * Create a new quiz attempt
     * 
     * @param int $userId
     * @param string $quizType
     * @param array $categoryIds
     * @return int|false Quiz attempt ID or false on failure
     */
    public function createAttempt($userId, $quizType, $categoryIds) {
        $data = [
            'user_id' => $userId,
            'quiz_type' => $quizType,
            'categories' => implode(',', $categoryIds),
            'total_questions' => 0,
            'correct_answers' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->create($data);
    }
    
    /**
     * Complete a quiz attempt
     * 
     * @param int $attemptId
     * @param int $totalQuestions
     * @param int $correctAnswers
     * @param int $durationSeconds
     * @return bool
     */
    public function completeAttempt($attemptId, $totalQuestions, $correctAnswers, $durationSeconds) {
        $data = [
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctAnswers,
            'duration_seconds' => $durationSeconds,
            'completed_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->update($attemptId, $data);
    }
    
    /**
     * Record a question answer
     * 
     * @param int $userId
     * @param int $questionId
     * @param int $answerId
     * @param bool $isCorrect
     * @param string $quizType
     * @return bool
     */
    public function recordAnswer($userId, $questionId, $answerId, $isCorrect, $quizType) {
        $sql = "INSERT INTO quiz_answers 
                (user_id, question_id, answer_id, is_correct, quiz_type, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $userId,
            $questionId,
            $answerId,
            $isCorrect ? 1 : 0,
            $quizType
        ]);
    }
    
    /**
     * Record a written answer
     * 
     * @param int $userId
     * @param int $questionId
     * @param string $writtenAnswer
     * @param bool $isCorrect
     * @param string $quizType
     * @return bool
     */
    public function recordWrittenAnswer($userId, $questionId, $writtenAnswer, $isCorrect, $quizType) {
        $sql = "INSERT INTO quiz_answers 
                (user_id, question_id, written_answer, is_correct, quiz_type, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $userId,
            $questionId,
            $writtenAnswer,
            $isCorrect ? 1 : 0,
            $quizType
        ]);
    }
    
    /**
     * Get user's quiz history
     * 
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getUserHistory($userId, $limit = 5) {
        $sql = "SELECT ua.*, 
                (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') 
                FROM categories c 
                WHERE FIND_IN_SET(c.id, ua.categories)) as category_names
                FROM {$this->table} ua
                WHERE ua.user_id = ?
                ORDER BY ua.created_at DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get user's category performance
     * 
     * @param int $userId
     * @return array
     */
    public function getUserCategoryPerformance($userId) {
        $sql = "SELECT 
                c.name as category_name,
                COUNT(qa.id) as total_answers,
                SUM(qa.is_correct) as correct_answers,
                (SUM(qa.is_correct) / COUNT(qa.id)) * 100 as percentage
                FROM quiz_answers qa
                JOIN questions q ON qa.question_id = q.id
                JOIN categories c ON q.category_id = c.id
                WHERE qa.user_id = ?
                GROUP BY c.id
                ORDER BY percentage DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}

/**
 * Get recent quiz attempts
 * 
 * @param int $limit
 * @return array
 */
public function getRecentAttempts($limit = 5) {
    $sql = "SELECT ua.*, u.first_name, u.last_name, u.email,
            (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') 
            FROM categories c 
            WHERE FIND_IN_SET(c.id, ua.categories)) as category_names
            FROM {$this->table} ua
            JOIN users u ON ua.user_id = u.id
            WHERE ua.completed_at IS NOT NULL
            ORDER BY ua.completed_at DESC
            LIMIT ?";
    
    $stmt = $this->db->prepare($sql);
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}