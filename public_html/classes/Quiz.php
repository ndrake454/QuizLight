<?php
/**
 * Quiz Model
 * 
 * Handles all quiz-related operations
 */
class Quiz {
    private $db;
    private $userId;
    private $questions = [];
    private $currentQuestion = 0;
    private $correctAnswers = 0;
    private $quizType;
    private $categories = [];
    private $startTime;
    
    /**
     * Constructor
     * 
     * @param int $userId User ID
     */
    public function __construct($userId = null) {
        $this->db = Database::getInstance();
        $this->userId = $userId;
        $this->startTime = time();
    }
    
    /**
     * Initialize a new quiz
     * 
     * @param string $quizType Type of quiz (quick, test, adaptive, spaced_repetition)
     * @param array $categories Array of category IDs
     * @param int $numQuestions Number of questions (or max questions for adaptive)
     * @param string $difficulty Difficulty level for test mode
     * @return bool Success status
     */
    public function initialize($quizType, $categories, $numQuestions = 10, $difficulty = 'medium') {
        if (empty($this->userId) || empty($categories)) {
            return false;
        }
        
        $this->quizType = $quizType;
        $this->categories = $categories;
        
        // Load questions based on quiz type
        switch ($quizType) {
            case 'spaced_repetition':
                $this->questions = $this->loadSpacedRepetitionQuestions($categories, $numQuestions);
                break;
            case 'adaptive':
                $this->questions = $this->loadAdaptiveQuestions($categories, $numQuestions);
                break;
            case 'test':
                $this->questions = $this->loadTestQuestions($categories, $numQuestions, $difficulty);
                break;
            case 'quick':
            default:
                $this->questions = $this->loadRegularQuestions($categories, $numQuestions);
                break;
        }
        
        return !empty($this->questions);
    }
    
    /**
     * Load regular questions for quick quiz
     * 
     * @param array $categories Array of category IDs
     * @param int $numQuestions Number of questions
     * @return array Array of questions
     */
    private function loadRegularQuestions($categories, $numQuestions) {
        $categoryPlaceholders = implode(',', array_fill(0, count($categories), '?'));
        $params = $categories;
        $params[] = $numQuestions;
        
        $questions = $this->db->fetchAll("
            SELECT q.id, q.question_text, q.explanation, q.category_id, q.image_path, 
                   q.intended_difficulty, q.difficulty_value, q.question_type
            FROM questions q 
            WHERE q.category_id IN ($categoryPlaceholders) 
            ORDER BY RAND() 
            LIMIT ?
        ", $params);
        
        return $this->enrichQuestions($questions);
    }
    
    /**
     * Load test questions with specific difficulty
     * 
     * @param array $categories Array of category IDs
     * @param int $numQuestions Number of questions
     * @param string $difficulty Difficulty level
     * @return array Array of questions
     */
    private function loadTestQuestions($categories, $numQuestions, $difficulty) {
        $categoryPlaceholders = implode(',', array_fill(0, count($categories), '?'));
        $params = $categories;
        
        // Add difficulty filtering
        $difficultyClause = "";
        switch($difficulty) {
            case 'easy':
                $difficultyClause = " AND q.difficulty_value <= 2.0";
                break;
            case 'hard':
                $difficultyClause = " AND q.difficulty_value >= 4.0";
                break;
            default: // 'medium'
                $difficultyClause = " AND q.difficulty_value BETWEEN 2.0 AND 4.0";
                break;
        }
        
        $params[] = $numQuestions;
        
        $questions = $this->db->fetchAll("
            SELECT q.id, q.question_text, q.explanation, q.category_id, q.image_path, 
                   q.intended_difficulty, q.difficulty_value, q.question_type
            FROM questions q 
            WHERE q.category_id IN ($categoryPlaceholders) $difficultyClause
            ORDER BY RAND() 
            LIMIT ?
        ", $params);
        
        return $this->enrichQuestions($questions);
    }
    
    /**
     * Load adaptive questions
     * 
     * @param array $categories Array of category IDs
     * @param int $maxQuestions Maximum number of questions
     * @return array Array of questions
     */
    private function loadAdaptiveQuestions($categories, $maxQuestions) {
        $categoryPlaceholders = implode(',', array_fill(0, count($categories), '?'));
        $params = $categories;
        $params[] = $maxQuestions;
        
        // Start with easier questions
        $questions = $this->db->fetchAll("
            SELECT q.id, q.question_text, q.explanation, q.category_id, q.image_path, 
                   q.intended_difficulty, q.difficulty_value, q.question_type
            FROM questions q 
            WHERE q.category_id IN ($categoryPlaceholders) 
            ORDER BY q.difficulty_value ASC 
            LIMIT ?
        ", $params);
        
        return $this->enrichQuestions($questions);
    }
    
    /**
     * Load spaced repetition questions
     * 
     * @param array $categories Array of category IDs
     * @param int $numQuestions Number of questions
     * @return array Array of questions
     */
    private function loadSpacedRepetitionQuestions($categories, $numQuestions) {
        require_once dirname(__FILE__) . '/SpacedRepetition.php';
        $sr = new SpacedRepetition($this->db->getPdo());
        
        // Get 70% due cards and 30% new cards
        $dueLimit = ceil($numQuestions * 0.7);
        $dueCards = $sr->getDueCards($this->userId, $categories, $dueLimit);
        
        $newLimit = floor($numQuestions * 0.3);
        if (count($dueCards) < $dueLimit) {
            $newLimit += ($dueLimit - count($dueCards));
        }
        
        $newCards = $sr->getNewCards($this->userId, $categories, $newLimit);
        
        // Combine card IDs
        $allCardIds = array_merge(
            array_column($dueCards, 'question_id'),
            array_column($newCards, 'question_id')
        );
        
        // Shuffle cards for variety
        shuffle($allCardIds);
        
        // Make sure we don't exceed the requested number
        $allCardIds = array_slice($allCardIds, 0, $numQuestions);
        
        $questions = [];
        
        if (!empty($allCardIds)) {
            // Create placeholders for the IN clause
            $placeholders = implode(',', array_fill(0, count($allCardIds), '?'));
            
            // Get question data
            $questions = $this->db->fetchAll("
                SELECT q.id, q.question_text, q.explanation, q.category_id, q.image_path, 
                       q.intended_difficulty, q.difficulty_value, q.question_type
                FROM questions q 
                WHERE q.id IN ($placeholders)
            ", $allCardIds);
        }
        
        // If we didn't get enough questions with SR, fall back to regular selection
        if (count($questions) < $numQuestions) {
            $remainingCount = $numQuestions - count($questions);
            $existingIds = array_column($questions, 'id');
            
            // Prepare where clause for categories
            $categoryPlaceholders = implode(',', array_fill(0, count($categories), '?'));
            $params = $categories;
            
            // Exclude questions we already have
            $excludeClause = '';
            if (!empty($existingIds)) {
                $excludePlaceholders = implode(',', array_fill(0, count($existingIds), '?'));
                $excludeClause = " AND q.id NOT IN ($excludePlaceholders)";
                $params = array_merge($params, $existingIds);
            }
            
            $params[] = $remainingCount;
            
            $additionalQuestions = $this->db->fetchAll("
                SELECT q.id, q.question_text, q.explanation, q.category_id, q.image_path, 
                       q.intended_difficulty, q.difficulty_value, q.question_type
                FROM questions q 
                WHERE q.category_id IN ($categoryPlaceholders) $excludeClause
                ORDER BY RAND()
                LIMIT ?
            ", $params);
            
            // Merge with existing questions
            $questions = array_merge($questions, $additionalQuestions);
        }
        
        return $this->enrichQuestions($questions);
    }
    
    /**
     * Add answers and category names to questions
     * 
     * @param array $questions Array of questions
     * @return array Enriched questions
     */
    private function enrichQuestions($questions) {
        foreach ($questions as &$question) {
            if ($question['question_type'] === 'multiple_choice' || !isset($question['question_type'])) {
                // Default to multiple choice for backward compatibility
                $question['question_type'] = 'multiple_choice';
                
                // Get multiple choice answers
                $answers = $this->db->fetchAll("
                    SELECT id, answer_text, is_correct 
                    FROM answers 
                    WHERE question_id = ?
                ", [$question['id']]);
                
                // Randomize answer order except for adaptive mode
                if ($this->quizType !== 'adaptive') {
                    shuffle($answers);
                }
                
                $question['answers'] = $answers;
            } else {
                // Get written response answers
                $writtenAnswers = $this->db->fetchAll("
                    SELECT id, answer_text, is_primary 
                    FROM written_response_answers 
                    WHERE question_id = ?
                ", [$question['id']]);
                
                $question['written_answers'] = $writtenAnswers;
                
                // Find the primary answer
                foreach ($writtenAnswers as $answer) {
                    if ($answer['is_primary']) {
                        $question['primary_answer'] = $answer['answer_text'];
                        break;
                    }
                }
                
                // If no primary answer is set, use the first one
                if (!isset($question['primary_answer']) && !empty($writtenAnswers)) {
                    $question['primary_answer'] = $writtenAnswers[0]['answer_text'];
                }
            }
            
            // Get category name
            $category = $this->db->fetchOne("SELECT name FROM categories WHERE id = ?", [$question['category_id']]);
            if ($category) {
                $question['category_name'] = $category['name'];
            } else {
                $question['category_name'] = 'Unknown';
            }
        }
        
        return $questions;
    }
    
    /**
     * Get the current question
     * 
     * @return array|null Current question or null if no more questions
     */
    public function getCurrentQuestion() {
        if (isset($this->questions[$this->currentQuestion])) {
            return $this->questions[$this->currentQuestion];
        }
        
        return null;
    }
    
    /**
     * Submit an answer for the current question
     * 
     * @param mixed $answer Answer ID or text depending on question type
     * @return array Result information
     */
    public function submitAnswer($answer) {
        $currentQuestion = $this->getCurrentQuestion();
        if (!$currentQuestion) {
            return [
                'success' => false,
                'message' => 'No current question'
            ];
        }
        
        // Process answer based on question type
        $isCorrect = false;
        
        if ($currentQuestion['question_type'] === 'multiple_choice') {
            // For multiple choice, check if the answer is correct
            foreach ($currentQuestion['answers'] as $answerOption) {
                if ($answerOption['id'] == $answer && $answerOption['is_correct']) {
                    $isCorrect = true;
                    break;
                }
            }
            
            // Record the answer in the database
            $this->recordMultipleChoiceAnswer($currentQuestion['id'], $answer, $isCorrect);
        } else {
            // For written response, check against acceptable answers
            $isCorrect = $this->checkWrittenAnswer($answer, $currentQuestion['written_answers']);
            
            // Record the answer in the database
            $this->recordWrittenAnswer($currentQuestion['id'], $answer, $isCorrect);
        }
        
        // Store result for this question
        $this->questions[$this->currentQuestion]['is_answered'] = true;
        $this->questions[$this->currentQuestion]['is_correct'] = $isCorrect;
        $this->questions[$this->currentQuestion]['user_answer'] = $answer;
        
        if ($isCorrect) {
            $this->correctAnswers++;
        }
        
        // Handle adaptive quiz logic if needed
        if ($this->quizType === 'adaptive') {
            $this->adjustAdaptiveDifficulty($isCorrect);
        }
        
        // Handle spaced repetition logic if needed
        if ($this->quizType === 'spaced_repetition') {
            $this->processSpacedRepetition($currentQuestion['id'], $isCorrect);
        }
        
        return [
            'success' => true,
            'is_correct' => $isCorrect,
            'explanation' => $currentQuestion['explanation'] ?? '',
            'correct_answer' => $this->getCorrectAnswer($currentQuestion)
        ];
    }
    
    /**
     * Get the correct answer for a question
     * 
     * @param array $question Question data
     * @return string|array Correct answer(s)
     */
    private function getCorrectAnswer($question) {
        if ($question['question_type'] === 'multiple_choice') {
            foreach ($question['answers'] as $answer) {
                if ($answer['is_correct']) {
                    return $answer['answer_text'];
                }
            }
        } else {
            return $question['primary_answer'] ?? '';
        }
        
        return '';
    }
    
    /**
     * Check if a written answer is correct using fuzzy matching
     * 
     * @param string $userAnswer User's answer
     * @param array $acceptableAnswers Acceptable answers
     * @return bool True if the answer is correct
     */
    private function checkWrittenAnswer($userAnswer, $acceptableAnswers) {
        // Normalize the user's answer (lowercase, trim, remove extra spaces)
        $normalizedUserAnswer = $this->normalizeText($userAnswer);
        
        // First check for exact matches after normalization
        foreach ($acceptableAnswers as $answer) {
            if ($this->normalizeText($answer['answer_text']) === $normalizedUserAnswer) {
                return true;
            }
        }
        
        // Check for fuzzy matches
        $words = explode(' ', $normalizedUserAnswer);
        if (count($words) <= 3) { // Only do fuzzy matching for 1-3 word answers
            foreach ($acceptableAnswers as $answer) {
                $normalizedAcceptableAnswer = $this->normalizeText($answer['answer_text']);
                
                // For short answers, use Levenshtein distance
                $maxLength = max(strlen($normalizedUserAnswer), strlen($normalizedAcceptableAnswer));
                $threshold = min(0.8, 1 - (2 / $maxLength)); // Adaptive threshold
                $maxAllowedDistance = floor($maxLength * (1 - $threshold));
                
                $distance = levenshtein($normalizedUserAnswer, $normalizedAcceptableAnswer);
                if ($distance <= $maxAllowedDistance) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Normalize text for comparison
     * 
     * @param string $text Text to normalize
     * @return string Normalized text
     */
    private function normalizeText($text) {
        return strtolower(
            trim(
                preg_replace('/\s+/', ' ', // Replace multiple spaces with a single space
                    preg_replace('/[.,;:!?()\'"-]/', '', $text) // Remove common punctuation
                )
            )
        );
    }
    
    /**
     * Record a multiple choice answer in the database
     * 
     * @param int $questionId Question ID
     * @param int $answerId Answer ID
     * @param bool $isCorrect Whether the answer is correct
     */
    private function recordMultipleChoiceAnswer($questionId, $answerId, $isCorrect) {
        try {
            $this->db->insert('quiz_answers', [
                'user_id' => $this->userId,
                'question_id' => $questionId,
                'answer_id' => $answerId,
                'is_correct' => $isCorrect ? 1 : 0,
                'quiz_type' => $this->quizType,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Error recording answer: " . $e->getMessage());
        }
    }
    
    /**
     * Record a written answer in the database
     * 
     * @param int $questionId Question ID
     * @param string $userAnswer User's written answer
     * @param bool $isCorrect Whether the answer is correct
     */
    private function recordWrittenAnswer($questionId, $userAnswer, $isCorrect) {
        try {
            $this->db->insert('quiz_answers', [
                'user_id' => $this->userId,
                'question_id' => $questionId,
                'written_answer' => $userAnswer,
                'is_correct' => $isCorrect ? 1 : 0,
                'quiz_type' => $this->quizType,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Error recording written answer: " . $e->getMessage());
        }
    }
    
    /**
     * Adjust difficulty for adaptive quizzes
     * 
     * @param bool $wasCorrect Whether the answer was correct
     */
    private function adjustAdaptiveDifficulty($wasCorrect) {
        // If no more questions to load, return
        if ($this->currentQuestion >= count($this->questions) - 1) {
            return;
        }
        
        try {
            // Get current question's category ID
            $currentCategoryId = $this->questions[$this->currentQuestion]['category_id'];
            
            // Get or initialize difficulty for this category
            if (!isset($this->categoryDifficulty)) {
                $this->categoryDifficulty = [];
            }
            
            if (!isset($this->categoryDifficulty[$currentCategoryId])) {
                $this->categoryDifficulty[$currentCategoryId] = 1.0; // Start at easiest
            }
            
            // Update difficulty based on correctness for this specific category
            if ($wasCorrect) {
                // Increase difficulty if answer was correct
                $this->categoryDifficulty[$currentCategoryId] += 0.5;
            } else {
                // Decrease difficulty if answer was wrong
                $this->categoryDifficulty[$currentCategoryId] -= 0.5;
            }
            
            // Ensure difficulty stays within bounds (1.0 to 5.0)
            $this->categoryDifficulty[$currentCategoryId] = 
                max(1.0, min(5.0, $this->categoryDifficulty[$currentCategoryId]));
                
            // Store the target difficulty for the next question
            $nextDifficulty = $this->categoryDifficulty[$currentCategoryId];
            
            // Find a question with the closest difficulty to our target
            $categoryPlaceholders = implode(',', array_fill(0, count($this->categories), '?'));
            
            // Create parameters array
            $params = array_merge([$nextDifficulty], $this->categories, [$this->userId]);
            
            // Get existing question IDs to exclude them
            $existingIds = [];
            foreach ($this->questions as $question) {
                $existingIds[] = $question['id'];
            }
            
            // Add exclusion for existing questions
            $excludeClause = '';
            if (!empty($existingIds)) {
                $excludePlaceholders = implode(',', array_fill(0, count($existingIds), '?'));
                $excludeClause = " AND q.id NOT IN ($excludePlaceholders)";
                $params = array_merge($params, $existingIds);
            }
            
            $nextQuestion = $this->db->fetchOne("
                SELECT q.id, q.question_text, q.explanation, q.category_id, q.image_path, 
                    q.intended_difficulty, q.difficulty_value, q.question_type,
                    ABS(q.difficulty_value - ?) AS diff_distance
                FROM questions q 
                WHERE q.category_id IN ($categoryPlaceholders)
                AND q.id NOT IN (
                    SELECT question_id FROM quiz_answers 
                    WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                )
                $excludeClause
                ORDER BY diff_distance ASC, RAND() 
                LIMIT 1
            ", $params);
            
            if ($nextQuestion) {
                // Enrich the question with answers and category name
                $enrichedQuestion = $this->enrichQuestions([$nextQuestion])[0];
                
                // Replace the next question in the queue with this adaptive one
                $this->questions[$this->currentQuestion + 1] = $enrichedQuestion;
            }
        } catch (Exception $e) {
            error_log("Error adjusting adaptive difficulty: " . $e->getMessage());
        }
    }
    
    /**
     * Process spaced repetition for a question
     * 
     * @param int $questionId Question ID
     * @param bool $isCorrect Whether the answer was correct
     */
    private function processSpacedRepetition($questionId, $isCorrect) {
        require_once dirname(__FILE__) . '/SpacedRepetition.php';
        
        try {
            $sr = new SpacedRepetition($this->db->getPdo());
            
            // Convert to SM-2 quality score (0-5)
            $quality = $isCorrect ? 5 : 0; // Simplified for this integration
            
            $sr->processReview($this->userId, $questionId, $quality);
        } catch (Exception $e) {
            error_log("Error processing spaced repetition: " . $e->getMessage());
        }
    }
    
    /**
     * Move to the next question
     * 
     * @return bool True if there is a next question, false if quiz is complete
     */
    public function nextQuestion() {
        $this->currentQuestion++;
        
        return $this->currentQuestion < count($this->questions);
    }
    
    /**
     * Save the quiz results to the database
     * 
     * @return int|bool Attempt ID on success, false on failure
     */
    public function saveResults() {
        try {
            $duration = time() - $this->startTime;
            $categoriesStr = implode(',', $this->categories);
            
            $attemptId = $this->db->insert('user_attempts', [
                'user_id' => $this->userId,
                'total_questions' => count($this->questions),
                'correct_answers' => $this->correctAnswers,
                'categories' => $categoriesStr,
                'quiz_type' => $this->quizType,
                'duration_seconds' => $duration,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return $attemptId;
        } catch (Exception $e) {
            error_log("Error saving quiz results: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get the quiz results
     * 
     * @return array Quiz results data
     */
    public function getResults() {
        return [
            'total_questions' => count($this->questions),
            'correct_answers' => $this->correctAnswers,
            'accuracy' => count($this->questions) > 0 ? 
                round(($this->correctAnswers / count($this->questions)) * 100) : 0,
            'duration' => time() - $this->startTime,
            'quiz_type' => $this->quizType,
            'questions' => $this->questions
        ];
    }
    
    /**
     * Get question by index
     * 
     * @param int $index Question index
     * @return array|null Question data or null if index is out of bounds
     */
    public function getQuestionByIndex($index) {
        if (isset($this->questions[$index])) {
            return $this->questions[$index];
        }
        
        return null;
    }
    
    /**
     * Get all questions
     * 
     * @return array All questions
     */
    public function getAllQuestions() {
        return $this->questions;
    }
    
    /**
     * Get current question index
     * 
     * @return int Current question index
     */
    public function getCurrentIndex() {
        return $this->currentQuestion;
    }
    
    /**
     * Get the number of correct answers
     * 
     * @return int Number of correct answers
     */
    public function getCorrectCount() {
        return $this->correctAnswers;
    }
    
    /**
     * Get quiz type
     * 
     * @return string Quiz type
     */
    public function getQuizType() {
        return $this->quizType;
    }
    
    /**
     * Get quiz categories
     * 
     * @return array Quiz categories
     */
    public function getCategories() {
        return $this->categories;
    }
}