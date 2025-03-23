<?php
/**
 * Quiz Controller
 * 
 * Handles quiz selection, taking, and results
 */
class QuizController extends BaseController {
    private $categoryModel;
    private $questionModel;
    private $quizModel;
    
    public function __construct() {
        parent::__construct();
        $this->categoryModel = new CategoryModel();
        $this->questionModel = new QuestionModel();
        $this->quizModel = new QuizModel();
    }
    
    /**
     * Display quiz selection page
     * 
     * @return void
     */
    public function select() {
        // Ensure user is logged in
        requireLogin();
        
        // Get all active categories
        $categories = $this->categoryModel->getAllActive();
        
        // Get recommendations
        $recommendationService = new RecommendationService();
        $recommendations = $recommendationService->generateRecommendations($_SESSION['user_id']);
        
        $this->render('quiz/select', [
            'pageTitle' => 'Quiz Selection',
            'categories' => $categories,
            'recommendations' => $recommendations,
            'extraScripts' => ['/assets/js/quiz-select.js']
        ]);
    }
    
    /**
     * Process quiz selection and start quiz
     * 
     * @return void
     */
    public function start() {
        // Ensure user is logged in
        requireLogin();
        
        // Validate input
        $categoryIds = $_POST['categories'] ?? [];
        $numQuestions = (int)($_POST['num_questions'] ?? 10);
        $difficulty = $_POST['difficulty'] ?? 'mixed';
        $quizType = $_POST['quiz_type'] ?? 'standard';
        
        if (empty($categoryIds)) {
            setFlashMessage('Please select at least one category', 'error');
            $this->redirect('/quiz-select');
            return;
        }
        
        // Validate number of questions
        if ($numQuestions < 5 || $numQuestions > 30) {
            $numQuestions = 10;
        }
        
        // Create quiz attempt
        $attemptId = $this->quizModel->createAttempt($_SESSION['user_id'], $quizType, $categoryIds);
        
        if (!$attemptId) {
            setFlashMessage('Failed to create quiz attempt', 'error');
            $this->redirect('/quiz-select');
            return;
        }
        
        // Store quiz settings in session
        $_SESSION['quiz'] = [
            'attempt_id' => $attemptId,
            'categories' => $categoryIds,
            'num_questions' => $numQuestions,
            'difficulty' => $difficulty,
            'type' => $quizType,
            'current_question' => 0,
            'total_correct' => 0,
            'start_time' => time()
        ];
        
        // Redirect to quiz page
        $this->redirect('/quiz');
    }
    
    /**
     * Display quiz page
     * 
     * @return void
     */
    public function take() {
        // Ensure user is logged in
        requireLogin();
        
        // Ensure quiz is in progress
        if (!isset($_SESSION['quiz']) || !isset($_SESSION['quiz']['attempt_id'])) {
            setFlashMessage('No quiz in progress', 'error');
            $this->redirect('/quiz-select');
            return;
        }
        
        $quiz = $_SESSION['quiz'];
        
        // Check if we need a new question
        if (!isset($_SESSION['current_question'])) {
            // Get a new question
            if ($quiz['difficulty'] !== 'mixed') {
                $questions = $this->questionModel->getByCategoryAndDifficulty(
                    $quiz['categories'],
                    $quiz['difficulty'],
                    $quiz['num_questions']
                );
            } else {
                $questions = $this->questionModel->getByCategories(
                    $quiz['categories'],
                    $quiz['num_questions']
                );
            }
            
            // Store questions in session
            $_SESSION['quiz']['questions'] = $questions;
            
            // Set current question
            $_SESSION['current_question'] = $questions[0];
            $_SESSION['quiz']['current_index'] = 0;
        }
        
        $currentQuestion = $_SESSION['current_question'];
        
        $this->render('quiz/take', [
            'pageTitle' => 'Quiz',
            'quiz' => $quiz,
            'question' => $currentQuestion,
            'questionNumber' => $quiz['current_question'] + 1,
            'totalQuestions' => $quiz['num_questions'],
            'extraScripts' => ['/assets/js/quiz.js']
        ]);
    }
    
    /**
     * Process quiz answer and show next question or results
     * 
     * @return void
     */
    public function submitAnswer() {
        // Ensure user is logged in
        requireLogin();
        
        // Ensure quiz is in progress
        if (!isset($_SESSION['quiz']) || !isset($_SESSION['quiz']['attempt_id'])) {
            $this->json(['error' => 'No quiz in progress']);
            return;
        }
        
        // Get current question
        $currentQuestion = $_SESSION['current_question'];
        
        // Process the answer
        $answerId = $_POST['answer_id'] ?? null;
        $writtenAnswer = $_POST['written_answer'] ?? null;
        $isCorrect = false;
        
        if ($currentQuestion['question_type'] === 'multiple_choice' || empty($currentQuestion['question_type'])) {
            // Multiple choice question
            if ($answerId) {
                // Check if the answer is correct
                $answerModel = new AnswerModel();
                $isCorrect = $answerModel->isCorrect($answerId);
                
                // Record the answer
                $this->quizModel->recordAnswer(
                    $_SESSION['user_id'],
                    $currentQuestion['id'],
                    $answerId,
                    $isCorrect,
                    $_SESSION['quiz']['type']
                );
            }
        } else {
            // Written response question
            if ($writtenAnswer) {
                // Normalize written answer (lowercase, trim, remove punctuation)
                $normalizedAnswer = $this->normalizeText($writtenAnswer);
                
                // Check against possible answers
                foreach ($currentQuestion['written_answers'] as $answer) {
                    $normalizedCorrect = $this->normalizeText($answer['answer_text']);
                    
                    // Check for fuzzy match (allows for minor typos)
                    if ($this->isFuzzyMatch($normalizedAnswer, $normalizedCorrect)) {
                        $isCorrect = true;
                        break;
                    }
                }
                
                // Record the answer
                $this->quizModel->recordWrittenAnswer(
                    $_SESSION['user_id'],
                    $currentQuestion['id'],
                    $writtenAnswer,
                    $isCorrect,
                    $_SESSION['quiz']['type']
                );
            }
        }
        
        // Update quiz data
        $_SESSION['quiz']['current_question']++;
        if ($isCorrect) {
            $_SESSION['quiz']['total_correct']++;
        }
        
        // Prepare response data
        $correctAnswer = $this->getCorrectAnswer($currentQuestion);
        
        // Determine if the quiz is complete
        $quizComplete = $_SESSION['quiz']['current_question'] >= $_SESSION['quiz']['num_questions'];
        
        // If the quiz is complete, record the results
        if ($quizComplete) {
            $duration = time() - $_SESSION['quiz']['start_time'];
            
            $this->quizModel->completeAttempt(
                $_SESSION['quiz']['attempt_id'],
                $_SESSION['quiz']['num_questions'],
                $_SESSION['quiz']['total_correct'],
                $duration
            );
            
            // Check for new achievements
            $achievementService = new AchievementService();
            $newAchievements = $achievementService->checkForNewAchievements($_SESSION['user_id']);
            
            // Update question difficulty based on user performance
            $this->questionModel->updateDifficulty(
                $currentQuestion['id'],
                $isCorrect ? 'easy' : 'hard'
            );
            
            // Prepare results data
            $result = [
                'isCorrect' => $isCorrect,
                'correctAnswer' => $correctAnswer,
                'quizComplete' => true,
                'redirect' => '/quiz/results',
                'newAchievements' => $newAchievements
            ];
            
            // Store results in session for the results page
            $_SESSION['quiz_results'] = [
                'attempt_id' => $_SESSION['quiz']['attempt_id'],
                'total_questions' => $_SESSION['quiz']['num_questions'],
                'total_correct' => $_SESSION['quiz']['total_correct'],
                'accuracy' => ($_SESSION['quiz']['total_correct'] / $_SESSION['quiz']['num_questions']) * 100,
                'duration' => $duration,
                'new_achievements' => $newAchievements
            ];
            
            // Clear quiz session
            unset($_SESSION['quiz']);
            unset($_SESSION['current_question']);
        } else {
            // Move to the next question
            $_SESSION['quiz']['current_index']++;
            $_SESSION['current_question'] = $_SESSION['quiz']['questions'][$_SESSION['quiz']['current_index']];
            
            // Update question difficulty based on user performance
            $this->questionModel->updateDifficulty(
                $currentQuestion['id'],
                $isCorrect ? 'easy' : 'hard'
            );
            
            $result = [
                'isCorrect' => $isCorrect,
                'correctAnswer' => $correctAnswer,
                'quizComplete' => false,
                'nextQuestion' => $_SESSION['quiz']['current_question'] + 1,
                'totalQuestions' => $_SESSION['quiz']['num_questions']
            ];
        }
        
        $this->json($result);
    }
    
    /**
     * Display quiz results
     * 
     * @return void
     */
    public function results() {
        // Ensure user is logged in
        requireLogin();
        
        // Ensure results are available
        if (!isset($_SESSION['quiz_results'])) {
            $this->redirect('/quiz-select');
            return;
        }
        
        $results = $_SESSION['quiz_results'];
        
        // Get quiz attempt details
        $quizAttempt = $this->quizModel->find($results['attempt_id']);
        
        // Get recommendations
        $recommendationService = new RecommendationService();
        $recommendations = $recommendationService->generateRecommendations($_SESSION['user_id']);
        
        // Get practice questions
        $practiceQuestions = $recommendationService->getPracticeQuestions($_SESSION['user_id'], 3);
        
        $this->render('quiz/results', [
            'pageTitle' => 'Quiz Results',
            'results' => $results,
            'quizAttempt' => $quizAttempt,
            'recommendations' => $recommendations,
            'practiceQuestions' => $practiceQuestions,
            'extraScripts' => ['/assets/js/quiz-results.js']
        ]);
        
        // Clear results from session
        unset($_SESSION['quiz_results']);
    }
    
    /**
     * Practice mode with targeted questions
     * 
     * @return void
     */
    public function practice() {
        // Ensure user is logged in
        requireLogin();
        
        // Get practice questions
        $recommendationService = new RecommendationService();
        $questions = $recommendationService->getPracticeQuestions($_SESSION['user_id'], 5);
        
        // Create practice quiz session
        $_SESSION['quiz'] = [
            'type' => 'practice',
            'current_question' => 0,
            'total_correct' => 0,
            'num_questions' => count($questions),
            'questions' => $questions,
            'start_time' => time()
        ];
        
        // Set current question
        $_SESSION['current_question'] = $questions[0];
        $_SESSION['quiz']['current_index'] = 0;
        
        $this->redirect('/quiz');
    }
    
    /**
     * Get the correct answer for a question
     * 
     * @param array $question
     * @return string
     */
    private function getCorrectAnswer($question) {
        if ($question['question_type'] === 'multiple_choice' || empty($question['question_type'])) {
            // For multiple choice, find the correct answer
            foreach ($question['answers'] as $answer) {
                if ($answer['is_correct']) {
                    return $answer['answer_text'];
                }
            }
            return '';
        } else {
            // For written response, return the primary answer
            foreach ($question['written_answers'] as $answer) {
                if ($answer['is_primary']) {
                    return $answer['answer_text'];
                }
            }
            
            // If no primary answer, return the first one
            return $question['written_answers'][0]['answer_text'] ?? '';
        }
    }
    
    /**
     * Normalize text for comparison
     * 
     * @param string $text
     * @return string
     */
    private function normalizeText($text) {
        // Convert to lowercase
        $text = strtolower($text);
        
        // Remove punctuation
        $text = preg_replace('/[^\w\s]/', '', $text);
        
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * Check if two strings are a fuzzy match
     * 
     * @param string $str1
     * @param string $str2
     * @return bool
     */
    private function isFuzzyMatch($str1, $str2) {
        // Exact match
        if ($str1 === $str2) {
            return true;
        }
        
        // Short strings should match exactly
        if (strlen($str1) <= 3 || strlen($str2) <= 3) {
            return $str1 === $str2;
        }
        
        // Calculate Levenshtein distance (allows for minor typos)
        $distance = levenshtein($str1, $str2);
        
        // Allow more errors for longer strings
        $maxLength = max(strlen($str1), strlen($str2));
        $threshold = floor($maxLength * 0.2); // 20% of the length
        
        return $distance <= $threshold;
    }
}