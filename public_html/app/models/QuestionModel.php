<?php
/**
 * Question Model
 */
class QuestionModel extends BaseModel {
    protected $table = 'questions';
    
    /**
     * Get questions by category IDs
     * 
     * @param array $categoryIds
     * @param int $limit
     * @param string $orderBy
     * @return array
     */
    public function getByCategories($categoryIds, $limit = 10, $orderBy = 'RAND()') {
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        
        $sql = "SELECT q.*, c.name as category_name 
                FROM {$this->table} q 
                JOIN categories c ON q.category_id = c.id 
                WHERE q.category_id IN ({$placeholders})
                ORDER BY {$orderBy}
                LIMIT ?";
        
        $params = array_merge($categoryIds, [$limit]);
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get questions by category and difficulty
     * 
     * @param array $categoryIds
     * @param string $difficulty
     * @param int $limit
     * @return array
     */
    public function getByCategoryAndDifficulty($categoryIds, $difficulty, $limit = 10) {
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        
        $difficultyClause = '';
        if ($difficulty === 'easy') {
            $difficultyClause = "AND q.difficulty_value <= 2.0";
        } elseif ($difficulty === 'medium') {
            $difficultyClause = "AND q.difficulty_value BETWEEN 2.0 AND 4.0";
        } elseif ($difficulty === 'hard') {
            $difficultyClause = "AND q.difficulty_value >= 4.0";
        }
        
        $sql = "SELECT q.*, c.name as category_name 
                FROM {$this->table} q 
                JOIN categories c ON q.category_id = c.id 
                WHERE q.category_id IN ({$placeholders})
                {$difficultyClause}
                ORDER BY RAND()
                LIMIT ?";
        
        $params = array_merge($categoryIds, [$limit]);
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get a question with its answers
     * 
     * @param int $id
     * @return array|false
     */
    public function getWithAnswers($id) {
        // Get question
        $question = $this->find($id);
        
        if (!$question) {
            return false;
        }
        
        // Get answers based on question type
        $answerModel = new AnswerModel();
        
        if ($question['question_type'] === 'multiple_choice' || empty($question['question_type'])) {
            $question['answers'] = $answerModel->getByQuestion($id);
        } else {
            $sql = "SELECT id, answer_text, is_primary 
                    FROM written_response_answers 
                    WHERE question_id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $question['written_answers'] = $stmt->fetchAll();
        }
        
        // Get category name
        $sql = "SELECT name FROM categories WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$question['category_id']]);
        $question['category_name'] = $stmt->fetchColumn();
        
        return $question;
    }
    
    /**
     * Create a new question
     * 
     * @param array $data Question data
     * @param array $answers Array of answer data
     * @return int|false Question ID or false on failure
     */
    public function createQuestion($data, $answers) {
        try {
            $this->db->beginTransaction();
            
            // Insert question
            $questionId = $this->create($data);
            
            if (!$questionId) {
                throw new Exception("Failed to create question");
            }
            
            // Insert answers
            $answerModel = new AnswerModel();
            
            if ($data['question_type'] === 'multiple_choice') {
                foreach ($answers as $index => $answer) {
                    $answerData = [
                        'question_id' => $questionId,
                        'answer_text' => $answer['text'],
                        'is_correct' => $answer['is_correct'] ? 1 : 0
                    ];
                    
                    $answerModel->create($answerData);
                }
            } else {
                // Written response answers
                foreach ($answers as $index => $answer) {
                    $sql = "INSERT INTO written_response_answers 
                            (question_id, answer_text, is_primary) 
                            VALUES (?, ?, ?)";
                    
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        $questionId,
                        $answer['text'],
                        $answer['is_primary'] ? 1 : 0
                    ]);
                }
            }
            
            $this->db->commit();
            return $questionId;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    /**
     * Update difficulty based on user rating
     * 
     * @param int $questionId
     * @param string $rating
     * @return bool
     */
    public function updateDifficulty($questionId, $rating) {
        $question = $this->find($questionId);
        
        if (!$question) {
            return false;
        }
        
        $currentDifficulty = floatval($question['difficulty_value']);
        
        // Map rating to target difficulty
        $targetDifficulty = ($rating === 'easy') ? 1.0 : (($rating === 'hard') ? 5.0 : 3.0);
        
        // Move difficulty 0.1 towards the target
        $newDifficulty = $currentDifficulty;
        
        if ($currentDifficulty < $targetDifficulty) {
            $newDifficulty = min($targetDifficulty, $currentDifficulty + 0.1);
        } elseif ($currentDifficulty > $targetDifficulty) {
            $newDifficulty = max($targetDifficulty, $currentDifficulty - 0.1);
        }
        
        return $this->update($questionId, [
            'difficulty_value' => $newDifficulty
        ]);
    }
}

/**
 * Get filtered questions with pagination
 * 
 * @param int $categoryId
 * @param string $search
 * @param int $offset
 * @param int $limit
 * @return array
 */
public function getFilteredQuestions($categoryId = 0, $search = '', $offset = 0, $limit = 10) {
    $params = [];
    $sql = "SELECT q.*, c.name as category_name,
            (SELECT COUNT(*) FROM answers WHERE question_id = q.id) as answer_count
            FROM {$this->table} q
            JOIN categories c ON q.category_id = c.id
            WHERE 1=1";
    
    if ($categoryId > 0) {
        $sql .= " AND q.category_id = ?";
        $params[] = $categoryId;
    }
    
    if (!empty($search)) {
        $sql .= " AND (q.question_text LIKE ? OR c.name LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    $sql .= " ORDER BY q.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Count filtered questions
 * 
 * @param int $categoryId
 * @param string $search
 * @return int
 */
public function countFilteredQuestions($categoryId = 0, $search = '') {
    $params = [];
    $sql = "SELECT COUNT(*) FROM {$this->table} q
            JOIN categories c ON q.category_id = c.id
            WHERE 1=1";
    
    if ($categoryId > 0) {
        $sql .= " AND q.category_id = ?";
        $params[] = $categoryId;
    }
    
    if (!empty($search)) {
        $sql .= " AND (q.question_text LIKE ? OR c.name LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}