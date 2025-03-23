<?php
/**
 * Quiz Controller
 * 
 * Handles all quiz-related operations including quiz creation,
 * question management, and result processing.
 */
class QuizController {
    private $db;
    private $userId;
    
    /**
     * Constructor
     * 
     * @param int $userId User ID (optional)
     */
    public function __construct($userId = null) {
        $this->db = Database::getInstance();
        $this->userId = $userId;
    }
    
    /**
     * Set user ID
     * 
     * @param int $userId User ID
     */
    public function setUserId($userId) {
        $this->userId = $userId;
    }
    
    /**
     * Get all categories
     * 
     * @return array Categories
     */
    public function getCategories() {
        try {
            return $this->db->fetchAll("SELECT * FROM categories ORDER BY name ASC");
        } catch (Exception $e) {
            error_log("Error getting categories: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Initialize a new quiz
     * 
     * @param array $data Quiz initialization data
     * @return array Result with quiz session data or error
     */
    public function initializeQuiz($data) {
        if (empty($this->userId)) {
            return [
                'success' => false,
                'message' => 'User ID is required'
            ];
        }
        
        $quizType = $data['quiz_type'] ?? '';
        
        if (empty($quizType)) {
            return [
                'success' => false,
                'message' => 'Quiz type is required'
            ];
        }
        
        // Determine categories based on quiz type
        $categories = [];
        switch ($quizType) {
            case 'quick':
                $categories = $data['quick_categories'] ?? [];
                $numQuestions = 10; // Fixed for quick quiz
                $difficulty = 'medium';
                break;
            case 'spaced_repetition':
                $categories = $data['sr_categories'] ?? [];
                $numQuestions = isset($data['sr_num_questions']) ? (int)$data['sr_num_questions'] : 20;
                $difficulty = 'medium';
                break;
            case 'test':
                $categories = $data['test_categories'] ?? [];
                $numQuestions = isset($data['test_num_questions']) ? (int)$data['test_num_questions'] : 20;
                $difficulty = $data['test_difficulty'] ?? 'medium';
                break;
            case 'adaptive':
                $categories = $data['adaptive_categories'] ?? [];
                $numQuestions = isset($data['adaptive_max_questions']) ? (int)$data['adaptive_max_questions'] : 20;
                $difficulty = 'adaptive';
                break;
            default:
                return [
                    'success' => false,
                    'message' => 'Invalid quiz type'
                ];
        }
        
        // Validate categories
        if (empty($categories)) {
            return [
                'success' => false,
                'message' => 'Please select at least one category'
            ];
        }
        
        // Initialize quiz
        $quiz = new Quiz($this->userId);
        $initialized = $quiz->initialize($quizType, $categories, $numQuestions, $difficulty);
        
        if (!$initialized) {
            return [
                'success' => false,
                'message' => 'Failed to initialize quiz. Please try again.'
            ];
        }
        
        // Store quiz in session
        $_SESSION['quiz_type'] = $quizType;
        $_SESSION['quiz_categories'] = $categories;
        $_SESSION['current_question'] = 0;
        $_SESSION['correct_answers'] = 0;
        $_SESSION['questions'] = $quiz->getAllQuestions();
        $_SESSION['show_explanation'] = false;
        $_SESSION['quiz_started_at'] = time();
        
        // Add additional flags based on quiz type
        if ($quizType === 'adaptive' || $quizType === 'test') {
            $_SESSION['quiz_show_results_at_end'] = true;
        }
        
        if ($quizType === 'adaptive') {
            $_SESSION['quiz_adaptive'] = true;
            $_SESSION['category_difficulty'] = [];
        }
        
        if ($quizType === 'spaced_repetition') {
            $_SESSION['quiz_spaced_repetition'] = true;
            $_SESSION['quiz_adaptive'] = true;
        }
        
        return [
            'success' => true,
            'redirect' => 'quiz.php'
        ];
    }
    
    /**
     * Submit answer to current question
     * 
     * @param array $data Answer submission data
     * @return array Result with success flag and feedback
     */
    public function submitAnswer($data) {
        if (!isset($_SESSION['questions']) || !isset($_SESSION['current_question'])) {
            return [
                'success' => false,
                'message' => 'No active quiz found'
            ];
        }
        
        $currentIndex = $_SESSION['current_question'];
        $questions = $_SESSION['questions'];
        
        if (!isset($questions[$currentIndex])) {
            return [
                'success' => false,
                'message' => 'Invalid question index'
            ];
        }
        
        $currentQuestion = $questions[$currentIndex];
        $questionId = $data['question_id'] ?? 0;
        
        // Verify question ID
        if ($questionId != $currentQuestion['id']) {
            return [
                'success' => false,
                'message' => 'Question ID mismatch'
            ];
        }
        
        // Process answer based on question type
        $answer = null;
        
        if ($currentQuestion['question_type'] === 'multiple_choice') {
            $answer = $data['selected_answer'] ?? '';
            
            if (empty($answer)) {
                return [
                    'success' => false,
                    'message' => 'Please select an answer'
                ];
            }
        } else {
            $answer = trim($data['written_answer'] ?? '');
            
            if (empty($answer)) {
                return [
                    'success' => false,
                    'message' => 'Please enter an answer'
                ];
            }
        }
        
        // Initialize Quiz object and submit answer
        $quiz = new Quiz($this->userId);
        
        // Load quiz state from session
        $quiz->initialize(
            $_SESSION['quiz_type'], 
            $_SESSION['quiz_categories'], 
            count($questions), 
            'medium' // Default difficulty, will be overridden for adaptive quizzes
        );
        
        // Process the answer
        $result = $quiz->submitAnswer($answer);
        
        if (!$result['success']) {
            return $result;
        }
        
        // Update the session with the result
        $_SESSION['current_answer_correct'] = $result['is_correct'];
        
        if ($currentQuestion['question_type'] === 'multiple_choice') {
            $_SESSION['selected_answer_id'] = $answer;
        } else {
            $_SESSION['written_user_answer'] = $answer;
        }
        
        // For test and adaptive quizzes, move to next question immediately
        if (isset($_SESSION['quiz_show_results_at_end']) && $_SESSION['quiz_show_results_at_end']) {
            // Update question data in session
            $_SESSION['questions'][$currentIndex]['is_correct'] = $result['is_correct'];
            
            if ($currentQuestion['question_type'] === 'multiple_choice') {
                $_SESSION['questions'][$currentIndex]['user_answer'] = $answer;
            } else {
                $_SESSION['questions'][$currentIndex]['user_written_answer'] = $answer;
            }
            
            if ($result['is_correct']) {
                $_SESSION['correct_answers']++;
            }
            
            // Move to next question
            $_SESSION['current_question']++;
            
            // Check if quiz is complete
            if ($_SESSION['current_question'] >= count($_SESSION['questions'])) {
                $this->saveQuizResults();
                $_SESSION['quiz_completed'] = true;
            }
            
            return [
                'success' => true,
                'redirect' => true
            ];
        } else {
            // For regular quizzes, show explanation
            $_SESSION['show_explanation'] = true;
            
            return [
                'success' => true,
                'is_correct' => $result['is_correct'],
                'explanation' => $result['explanation'],
                'correct_answer' => $result['correct_answer']
            ];
        }
    }
    
    /**
     * Process question rating and move to next question
     * 
     * @param array $data Rating submission data
     * @return array Result with success flag
     */
    public function processQuestionRating($data) {
        if (!isset($_SESSION['questions']) || !isset($_SESSION['current_question'])) {
            return [
                'success' => false,
                'message' => 'No active quiz found'
            ];
        }
        
        $currentIndex = $_SESSION['current_question'];
        $questions = $_SESSION['questions'];
        
        if (!isset($questions[$currentIndex])) {
            return [
                'success' => false,
                'message' => 'Invalid question index'
            ];
        }
        
        $currentQuestion = $questions[$currentIndex];
        $questionId = $data['question_id'] ?? 0;
        $difficulty = $data['difficulty_rating'] ?? 'unrated';
        
        // Verify question ID
        if ($questionId != $currentQuestion['id']) {
            return [
                'success' => false,
                'message' => 'Question ID mismatch'
            ];
        }
        
        $isCorrect = $_SESSION['current_answer_correct'] ?? false;
        
        // Only update user's difficulty rating if they provided one
        if ($difficulty !== 'unrated') {
            try {
                $this->db->execute(
                    "INSERT INTO user_question_status (user_id, question_id, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = ?",
                    [$this->userId, $questionId, $difficulty, $difficulty]
                );
                
                // Update question difficulty in database
                $this->updateQuestionDifficulty($questionId, $difficulty);
                
                // Store user rating in session
                $_SESSION['questions'][$currentIndex]['user_rating'] = $difficulty;
            } catch (Exception $e) {
                error_log("Error processing question rating: " . $e->getMessage());
            }
        }
        
        // Store result for this question
        if ($currentQuestion['question_type'] === 'multiple_choice') {
            $selectedAnswerId = $_SESSION['selected_answer_id'];
            $_SESSION['questions'][$currentIndex]['user_answer'] = $selectedAnswerId;
        } else {
            $userAnswer = $_SESSION['written_user_answer'];
            $_SESSION['questions'][$currentIndex]['user_written_answer'] = $userAnswer;
        }
        
        $_SESSION['questions'][$currentIndex]['is_correct'] = $isCorrect;
        
        if ($isCorrect) {
            $_SESSION['correct_answers']++;
        }
        
        // In adaptive mode, adjust next question difficulty based on answer
        if (isset($_SESSION['quiz_adaptive']) && $_SESSION['quiz_adaptive']) {
            $this->adjustAdaptiveDifficulty($isCorrect);
        }
        
        // Process for spaced repetition if enabled
        if (isset($_SESSION['quiz_spaced_repetition']) && $_SESSION['quiz_spaced_repetition']) {
            require_once dirname(__FILE__) . '/profile_analytics.php';
            return generateRecommendations($this->userId, $this->db->getPdo());
        } catch (Exception $e) {
            error_log("Error getting user recommendations: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get performance data for analytics charts
     * 
     * @return array Performance data
     */
    public function getPerformanceData() {
        try {
            require_once dirname(__FILE__) . '/profile_analytics.php';
            return getPerformanceData($this->userId, $this->db->getPdo());
        } catch (Exception $e) {
            error_log("Error getting performance data: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get spaced repetition statistics
     * 
     * @return array Spaced repetition statistics
     */
    public function getSpacedRepetitionStats() {
        try {
            require_once dirname(__FILE__) . '/SpacedRepetition.php';
            $sr = new SpacedRepetition($this->db->getPdo());
            return $sr->getUserStats($this->userId);
        } catch (Exception $e) {
            error_log("Error getting spaced repetition stats: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get leaderboard data
     * 
     * @param int $weeks Number of weeks ago (0 = current week)
     * @param int $limit Maximum number of users to include
     * @return array Leaderboard data
     */
    public function getLeaderboard($weeksAgo = 0, $limit = 5) {
        try {
            require_once dirname(__FILE__) . '/leaderboard-functions.php';
            return getTopPerformers($this->db->getPdo(), $weeksAgo, $limit);
        } catch (Exception $e) {
            error_log("Error getting leaderboard: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user ranking on leaderboard
     * 
     * @param int $weeksAgo Number of weeks ago (0 = current week)
     * @return array|null User ranking data or null if not found
     */
    public function getUserRanking($weeksAgo = 0) {
        try {
            require_once dirname(__FILE__) . '/leaderboard-functions.php';
            return getUserRankForWeek($this->db->getPdo(), $this->userId, $weeksAgo);
        } catch (Exception $e) {
            error_log("Error getting user ranking: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Compare user performance between weeks
     * 
     * @param int $weeksAgo1 First week (default = 0, current week)
     * @param int $weeksAgo2 Second week to compare against (default = 1, last week)
     * @return array Comparison data
     */
    public function compareUserWeeklyPerformance($weeksAgo1 = 0, $weeksAgo2 = 1) {
        try {
            require_once dirname(__FILE__) . '/leaderboard-functions.php';
            return compareUserWeeklyPerformance($this->db->getPdo(), $this->userId, $weeksAgo1, $weeksAgo2);
        } catch (Exception $e) {
            error_log("Error comparing user performance: " . $e->getMessage());
            return [];
        }
    }
}
FILE__) . '/SpacedRepetition.php';
            $sr = new SpacedRepetition($this->db->getPdo());
            
            // Convert to SM-2 quality score (0-5)
            $quality = $isCorrect ? 5 : 0; // Simplified for this integration
            $sr->processReview($this->userId, $questionId, $quality);
        }
        
        // Reset explanation flag and move to the next question
        $_SESSION['show_explanation'] = false;
        $_SESSION['current_answer_correct'] = null;
        $_SESSION['selected_answer_id'] = null;
        $_SESSION['written_user_answer'] = null;
        $_SESSION['current_question']++;
        
        // Check if quiz is complete
        if ($_SESSION['current_question'] >= count($_SESSION['questions'])) {
            $this->saveQuizResults();
            $_SESSION['quiz_completed'] = true;
        }
        
        return [
            'success' => true,
            'redirect' => true
        ];
    }
    
    /**
     * Save quiz results to database
     * 
     * @return bool Success status
     */
    public function saveQuizResults() {
        try {
            $totalQuestions = count($_SESSION['questions']);
            $correctAnswers = $_SESSION['correct_answers'];
            $categoriesStr = implode(',', $_SESSION['quiz_categories']);
            $quizType = $_SESSION['quiz_type'];
            $duration = time() - ($_SESSION['quiz_started_at'] ?? time());
            
            $this->db->insert('user_attempts', [
                'user_id' => $this->userId,
                'total_questions' => $totalQuestions,
                'correct_answers' => $correctAnswers,
                'categories' => $categoriesStr,
                'quiz_type' => $quizType,
                'duration_seconds' => $duration,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("Error saving quiz results: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reset current quiz
     * 
     * @return bool Success status
     */
    public function resetQuiz() {
        // Clear all quiz-related session variables
        unset($_SESSION['quiz_type']);
        unset($_SESSION['quiz_categories']);
        unset($_SESSION['quiz_num_questions']);
        unset($_SESSION['quiz_max_questions']);
        unset($_SESSION['quiz_difficulty']);
        unset($_SESSION['quiz_show_results_at_end']);
        unset($_SESSION['quiz_adaptive']);
        unset($_SESSION['quiz_spaced_repetition']);
        unset($_SESSION['current_question']);
        unset($_SESSION['correct_answers']);
        unset($_SESSION['questions']);
        unset($_SESSION['show_explanation']);
        unset($_SESSION['current_answer_correct']);
        unset($_SESSION['selected_answer_id']);
        unset($_SESSION['written_user_answer']);
        unset($_SESSION['quiz_completed']);
        unset($_SESSION['quiz_started_at']);
        unset($_SESSION['category_difficulty']);
        
        return true;
    }
    
    /**
     * Update question difficulty based on user rating
     * 
     * @param int $questionId Question ID
     * @param string $userRating User rating (easy, challenging, hard)
     * @return bool Success status
     */
    private function updateQuestionDifficulty($questionId, $userRating) {
        try {
            // Get current question difficulty
            $currentDifficulty = $this->db->fetchValue(
                "SELECT difficulty_value FROM questions WHERE id = ?",
                [$questionId]
            );
            
            $currentDifficulty = floatval($currentDifficulty);
            
            // Map user rating to difficulty value
            $targetDifficulty = ($userRating === 'easy') ? 1.0 : (($userRating === 'hard') ? 5.0 : 3.0);
            
            // Move difficulty 0.1 towards the target (weighted average)
            $newDifficulty = $currentDifficulty;
            if ($currentDifficulty < $targetDifficulty) {
                $newDifficulty = min($targetDifficulty, $currentDifficulty + 0.1);
            } elseif ($currentDifficulty > $targetDifficulty) {
                $newDifficulty = max($targetDifficulty, $currentDifficulty - 0.1);
            }
            
            // Update question difficulty in database
            $this->db->execute(
                "UPDATE questions SET difficulty_value = ? WHERE id = ?",
                [$newDifficulty, $questionId]
            );
            
            return true;
        } catch (Exception $e) {
            error_log("Error updating question difficulty: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Adjust difficulty for adaptive quizzes
     * 
     * @param bool $wasCorrect Whether the answer was correct
     * @return bool Success status
     */
    private function adjustAdaptiveDifficulty($wasCorrect) {
        // If no more questions to load, return
        if ($_SESSION['current_question'] >= count($_SESSION['questions']) - 1) {
            return false;
        }
        
        try {
            // Get current question's category ID
            $currentCategoryId = $_SESSION['questions'][$_SESSION['current_question']]['category_id'];
            
            // Initialize category difficulty tracking if it doesn't exist
            if (!isset($_SESSION['category_difficulty'])) {
                $_SESSION['category_difficulty'] = [];
            }
            
            // Set default difficulty for this category if not set
            if (!isset($_SESSION['category_difficulty'][$currentCategoryId])) {
                $_SESSION['category_difficulty'][$currentCategoryId] = 1.0; // Start at easiest
            }
            
            // Update difficulty based on correctness for this specific category
            if ($wasCorrect) {
                // Increase difficulty if answer was correct
                $_SESSION['category_difficulty'][$currentCategoryId] += 0.5;
            } else {
                // Decrease difficulty if answer was wrong
                $_SESSION['category_difficulty'][$currentCategoryId] -= 0.5;
            }
            
            // Ensure difficulty stays within bounds (1.0 to 5.0)
            $_SESSION['category_difficulty'][$currentCategoryId] = 
                max(1.0, min(5.0, $_SESSION['category_difficulty'][$currentCategoryId]));
                
            // Store the target difficulty for the next question
            $nextDifficulty = $_SESSION['category_difficulty'][$currentCategoryId];
            
            // Find a question with the closest difficulty to our target
            $categoryPlaceholders = implode(',', array_fill(0, count($_SESSION['quiz_categories']), '?'));
            
            // Get existing question IDs to exclude them
            $existingIds = [];
            foreach ($_SESSION['questions'] as $question) {
                $existingIds[] = $question['id'];
            }
            
            // Add exclusion for existing questions
            $excludeClause = '';
            if (!empty($existingIds)) {
                $excludePlaceholders = implode(',', array_fill(0, count($existingIds), '?'));
                $excludeClause = " AND q.id NOT IN ($excludePlaceholders)";
            }
            
            // Parameters for the query
            $params = array_merge(
                [$nextDifficulty], 
                $_SESSION['quiz_categories'], 
                [$this->userId],
                $existingIds
            );
            
            // Find the question closest to the target difficulty
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
            
            if (!$nextQuestion) {
                return false;
            }
            
            // Get answers for this question
            if ($nextQuestion['question_type'] === 'multiple_choice' || !isset($nextQuestion['question_type'])) {
                $nextQuestion['question_type'] = 'multiple_choice';
                
                // Get multiple choice answers
                $answers = $this->db->fetchAll(
                    "SELECT id, answer_text, is_correct FROM answers WHERE question_id = ?",
                    [$nextQuestion['id']]
                );
                
                $nextQuestion['answers'] = $answers;
            } else {
                // Get written response answers
                $writtenAnswers = $this->db->fetchAll(
                    "SELECT id, answer_text, is_primary FROM written_response_answers WHERE question_id = ?",
                    [$nextQuestion['id']]
                );
                
                $nextQuestion['written_answers'] = $writtenAnswers;
                
                // Find the primary answer
                foreach ($writtenAnswers as $answer) {
                    if ($answer['is_primary']) {
                        $nextQuestion['primary_answer'] = $answer['answer_text'];
                        break;
                    }
                }
                
                // If no primary answer is set, use the first one
                if (!isset($nextQuestion['primary_answer']) && !empty($writtenAnswers)) {
                    $nextQuestion['primary_answer'] = $writtenAnswers[0]['answer_text'];
                }
            }
            
            // Get category name
            $category = $this->db->fetchOne(
                "SELECT name FROM categories WHERE id = ?",
                [$nextQuestion['category_id']]
            );
            
            if ($category) {
                $nextQuestion['category_name'] = $category['name'];
            } else {
                $nextQuestion['category_name'] = 'Unknown';
            }
            
            // Replace the next question in the queue with this adaptive one
            $_SESSION['questions'][$_SESSION['current_question'] + 1] = $nextQuestion;
            
            return true;
        } catch (Exception $e) {
            error_log("Error adjusting adaptive difficulty: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user quiz statistics
     * 
     * @return array Quiz statistics
     */
    public function getUserStatistics() {
        try {
            $stats = [
                'total_attempts' => 0,
                'total_questions' => 0,
                'total_correct' => 0,
                'average_score' => 0,
                'average_time' => 0,
                'category_performance' => [],
                'recent_attempts' => []
            ];
            
            // Get total attempts
            $stats['total_attempts'] = $this->db->fetchValue(
                "SELECT COUNT(*) FROM user_attempts WHERE user_id = ?",
                [$this->userId]
            ) ?? 0;
            
            // Get total questions answered
            $stats['total_questions'] = $this->db->fetchValue(
                "SELECT SUM(total_questions) FROM user_attempts WHERE user_id = ?",
                [$this->userId]
            ) ?? 0;
            
            // Get total correct answers
            $stats['total_correct'] = $this->db->fetchValue(
                "SELECT SUM(correct_answers) FROM user_attempts WHERE user_id = ?",
                [$this->userId]
            ) ?? 0;
            
            // Calculate average score
            if ($stats['total_questions'] > 0) {
                $stats['average_score'] = round(($stats['total_correct'] / $stats['total_questions']) * 100);
            }
            
            // Get average time per quiz
            $stats['average_time'] = $this->db->fetchValue(
                "SELECT AVG(duration_seconds) FROM user_attempts WHERE user_id = ? AND duration_seconds > 0",
                [$this->userId]
            ) ?? 0;
            
            // Get category performance
            $stats['category_performance'] = $this->db->fetchAll("
                SELECT 
                    c.name as category_name,
                    COUNT(qa.id) as total_answers,
                    SUM(qa.is_correct) as correct_answers,
                    (SUM(qa.is_correct) / COUNT(qa.id)) * 100 as percentage
                FROM quiz_answers qa
                JOIN questions q ON qa.question_id = q.id
                JOIN categories c ON q.category_id = c.id
                WHERE qa.user_id = ?
                GROUP BY c.id
                ORDER BY percentage DESC
            ", [$this->userId]) ?? [];
            
            // Get recent attempts
            $stats['recent_attempts'] = $this->db->fetchAll("
                SELECT ua.*, 
                       (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') 
                        FROM categories c 
                        WHERE FIND_IN_SET(c.id, ua.categories)) as category_names
                FROM user_attempts ua
                WHERE ua.user_id = ?
                ORDER BY ua.created_at DESC
                LIMIT 5
            ", [$this->userId]) ?? [];
            
            return $stats;
        } catch (Exception $e) {
            error_log("Error getting user statistics: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user achievements
     * 
     * @return array User achievements
     */
    public function getUserAchievements() {
        try {
            require_once dirname(__FILE__) . '/profile_analytics.php';
            return getAchievements($this->userId, $this->db->getPdo());
        } catch (Exception $e) {
            error_log("Error getting user achievements: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get personalized recommendations for the user
     * 
     * @return array Recommendations
     */
    public function getRecommendations() {
        try {
            require_once dirname(__