<?php
/**
 * Answer Model
 */
class AnswerModel extends BaseModel {
    protected $table = 'answers';
    
    /**
     * Get answers for a question
     * 
     * @param int $questionId
     * @return array
     */
    public function getByQuestion($questionId) {
        return $this->findBy('question_id', $questionId);
    }
    
    /**
     * Check if an answer is correct
     * 
     * @param int $answerId
     * @return bool
     */
    public function isCorrect($answerId) {
        $answer = $this->find($answerId);
        return $answer && $answer['is_correct'] == 1;
    }
    
    /**
     * Get the correct answer for a question
     * 
     * @param int $questionId
     * @return array|false
     */
    public function getCorrectAnswer($questionId) {
        $sql = "SELECT * FROM {$this->table} WHERE question_id = ? AND is_correct = 1 LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$questionId]);
        return $stmt->fetch();
    }
}