<?php
/**
 * Quiz Utility Functions
 * 
 * Contains all helper functions for the quiz system.
 */

/**
 * Load questions from database based on selections
 * 
 * @param PDO $pdo Database connection
 * @return array Array of questions
 */
function loadQuestions() {
    global $pdo;
    
    // Extract quiz parameters from session
    $categories = $_SESSION['quiz_categories'];
    $numQuestions = $_SESSION['quiz_num_questions'] ?? ($_SESSION['quiz_max_questions'] ?? 10);
    $quizType = $_SESSION['quiz_type'];
    
    // Validate categories
    if (empty($categories)) {
        return [];
    }
    
    try {
        // Check if this is a spaced repetition quiz
        if (isset($_SESSION['quiz_spaced_repetition']) && $_SESSION['quiz_spaced_repetition']) {
            return loadSpacedRepetitionQuestions($pdo, $categories, $numQuestions);
        } else {
            // Regular question selection
            return loadRegularQuestions($pdo, $categories, $numQuestions, $quizType);
        }
    } catch (PDOException $e) {
        error_log("Error loading questions: " . $e->getMessage());
        return [];
    }
}

/**
 * Load spaced repetition questions
 */
function loadSpacedRepetitionQuestions($pdo, $categories, $numQuestions) {
    $sr = new SpacedRepetition($pdo);
    
    // Get 70% due cards and 30% new cards
    $dueLimit = ceil($numQuestions * 0.7);
    $dueCards = $sr->getDueCards($_SESSION['user_id'], $categories, $dueLimit);
    
    $newLimit = floor($numQuestions * 0.3);
    if (count($dueCards) < $dueLimit) {
        $newLimit += ($dueLimit - count($dueCards));
    }
    
    $newCards = $sr->getNewCards($_SESSION['user_id'], $categories, $newLimit);
    
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
        $sql = "SELECT q.id, q.question_text, q.explanation, q.category_id, q.image_path, 
                q.intended_difficulty, q.difficulty_value, q.question_type
                FROM questions q 
                WHERE q.id IN ($placeholders)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($allCardIds);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        
        $sql = "SELECT q.id, q.question_text, q.explanation, q.category_id, q.image_path, 
                q.intended_difficulty, q.difficulty_value, q.question_type
                FROM questions q 
                WHERE q.category_id IN ($categoryPlaceholders) $excludeClause
                ORDER BY RAND()
                LIMIT ?";
        
        $params[] = $remainingCount;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $additionalQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Merge with existing questions
        $questions = array_merge($questions, $additionalQuestions);
    }
    
    // Add answers and category names
    return enrichQuestions($pdo, $questions);
}

/**
 * Load regular (non-spaced repetition) questions
 */
function loadRegularQuestions($pdo, $categories, $numQuestions, $quizType) {
    // Prepare where clause for categories
    $categoryPlaceholders = implode(',', array_fill(0, count($categories), '?'));
    $params = $categories;
    
    // Add difficulty filtering for test mode
    $difficultyClause = "";
    if ($quizType === 'test' && isset($_SESSION['quiz_difficulty'])) {
        switch($_SESSION['quiz_difficulty']) {
            case 'easy':
                $difficultyClause = " AND q.difficulty_value <= 2.0";
                break;
            case 'medium':
                $difficultyClause = " AND q.difficulty_value BETWEEN 2.0 AND 4.0";
                break;
            case 'hard':
                $difficultyClause = " AND q.difficulty_value >= 4.0";
                break;
        }
    }
    
    // For adaptive mode, start with easier questions
    $orderClause = ($quizType === 'adaptive') ? "ORDER BY q.difficulty_value ASC" : "ORDER BY RAND()";
    
    $sql = "SELECT q.id, q.question_text, q.explanation, q.category_id, q.image_path, 
            q.intended_difficulty, q.difficulty_value, q.question_type
            FROM questions q 
            WHERE q.category_id IN ($categoryPlaceholders) $difficultyClause
            $orderClause
            LIMIT ?";
    
    $params[] = $numQuestions;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add answers and category names
    return enrichQuestions($pdo, $questions);
}

/**
 * Add answers and category names to questions
 */
function enrichQuestions($pdo, $questions) {
    $quizType = $_SESSION['quiz_type'] ?? '';
    
    // Get answers for each question based on question type
    foreach ($questions as &$question) {
        if ($question['question_type'] === 'multiple_choice' || !isset($question['question_type'])) {
            // Default to multiple choice for backward compatibility
            $question['question_type'] = 'multiple_choice';
            
            // Get multiple choice answers
            $stmt = $pdo->prepare("SELECT id, answer_text, is_correct FROM answers WHERE question_id = ?");
            $stmt->execute([$question['id']]);
            $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Randomize answer order except for the correct answer in adaptive mode
            if ($quizType !== 'adaptive') {
                shuffle($answers);
            }
            
            $question['answers'] = $answers;
        } else {
            // Get written response answers
            $stmt = $pdo->prepare("SELECT id, answer_text, is_primary FROM written_response_answers WHERE question_id = ?");
            $stmt->execute([$question['id']]);
            $writtenAnswers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
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
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
        $stmt->execute([$question['category_id']]);
        $question['category_name'] = $stmt->fetchColumn();
    }
    
    return $questions;
}

/**
 * Update question difficulty based on user rating
 * 
 * @param PDO $pdo Database connection
 * @param int $questionId Question ID
 * @param string $userRating User rating (easy, challenging, hard)
 */
function updateQuestionDifficulty($questionId, $userRating) {
    global $pdo;
    
    try {
        // Get current question
        $stmt = $pdo->prepare("SELECT difficulty_value FROM questions WHERE id = ?");
        $stmt->execute([$questionId]);
        $currentDifficulty = floatval($stmt->fetchColumn());
        
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
        $stmt = $pdo->prepare("UPDATE questions SET difficulty_value = ? WHERE id = ?");
        $stmt->execute([$newDifficulty, $questionId]);
    } catch (PDOException $e) {
        // Log error but continue with quiz
        error_log("Error updating question difficulty: " . $e->getMessage());
    }
}

/**
 * Adjust difficulty for adaptive quiz mode
 * 
 * @param bool $wasCorrect Whether the answer was correct
 */
function adjustAdaptiveDifficulty($wasCorrect) {
    global $pdo;
    
    // If no more questions to load, return
    if ($_SESSION['current_question'] >= count($_SESSION['questions']) - 1) {
        return;
    }
    
    try {
        // Get current categories
        $categories = $_SESSION['quiz_categories'];
        if (empty($categories)) return;
        
        $categoryPlaceholders = implode(',', array_fill(0, count($categories), '?'));
        
        // Calculate the next difficulty level based on answer correctness
        $currentQuestionDifficulty = $_SESSION['questions'][$_SESSION['current_question']]['difficulty_value'];
        $nextDifficulty = $currentQuestionDifficulty;
        
        if ($wasCorrect) {
            // Increase difficulty if answer was correct
            $nextDifficulty += 0.5;
        } else {
            // Decrease difficulty if answer was wrong
            $nextDifficulty -= 0.5;
        }
        
        // Ensure difficulty stays within bounds
        $nextDifficulty = max(1.0, min(5.0, $nextDifficulty));
        
        // Find a question with the closest difficulty to our target
        $sql = "SELECT q.id, q.question_text, q.explanation, q.category_id, q.image_path, 
                q.intended_difficulty, q.difficulty_value, q.question_type,
                ABS(q.difficulty_value - ?) AS diff_distance
                FROM questions q 
                WHERE q.category_id IN ($categoryPlaceholders)
                AND q.id NOT IN (
                    SELECT question_id FROM quiz_answers 
                    WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                )
                ORDER BY diff_distance ASC
                LIMIT 1";
        
        $params = array_merge([$nextDifficulty], $categories, [$_SESSION['user_id']]);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->rowCount() > 0) {
            $nextQuestion = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get answers for this question based on question type
            if ($nextQuestion['question_type'] === 'multiple_choice' || !isset($nextQuestion['question_type'])) {
                $nextQuestion['question_type'] = 'multiple_choice'; // Default for backward compatibility
                
                // Get multiple choice answers
                $stmt = $pdo->prepare("SELECT id, answer_text, is_correct FROM answers WHERE question_id = ?");
                $stmt->execute([$nextQuestion['id']]);
                $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $nextQuestion['answers'] = $answers;
            } else {
                // Get written response answers
                $stmt = $pdo->prepare("SELECT id, answer_text, is_primary FROM written_response_answers WHERE question_id = ?");
                $stmt->execute([$nextQuestion['id']]);
                $writtenAnswers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
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
            $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
            $stmt->execute([$nextQuestion['category_id']]);
            $nextQuestion['category_name'] = $stmt->fetchColumn();
            
            // Replace the next question in the queue with this adaptive one
            $_SESSION['questions'][$_SESSION['current_question'] + 1] = $nextQuestion;
        }
    } catch (PDOException $e) {
        // Log error but continue with quiz
        error_log("Error adjusting adaptive difficulty: " . $e->getMessage());
    }
}

/**
 * Record user's multiple choice answer in the database
 * 
 * @param int $questionId Question ID
 * @param int $answerId Answer ID
 * @param bool $isCorrect Whether the answer is correct
 */
function recordAnswer($questionId, $answerId, $isCorrect) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO quiz_answers (user_id, question_id, answer_id, is_correct, quiz_type)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $questionId,
            $answerId,
            $isCorrect ? 1 : 0,
            $_SESSION['quiz_type']
        ]);
    } catch (PDOException $e) {
        // Log error but continue with quiz
        error_log("Error recording answer: " . $e->getMessage());
    }
}

/**
 * Record user's written answer in the database
 * 
 * @param int $questionId Question ID
 * @param string $userAnswer User's written answer
 * @param bool $isCorrect Whether the answer is correct
 */
function recordWrittenAnswer($questionId, $userAnswer, $isCorrect) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO quiz_answers (user_id, question_id, written_answer, is_correct, quiz_type)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $questionId,
            $userAnswer,
            $isCorrect ? 1 : 0,
            $_SESSION['quiz_type']
        ]);
    } catch (PDOException $e) {
        // Log error but continue with quiz
        error_log("Error recording written answer: " . $e->getMessage());
    }
}

/**
 * Check written answer with fuzzy matching
 * 
 * @param string $userAnswer User's written answer
 * @param array $acceptableAnswers Array of acceptable answers
 * @return bool Whether the answer is correct
 */
function checkWrittenAnswer($userAnswer, $acceptableAnswers) {
    // Normalize the user's answer (lowercase, trim, remove extra spaces)
    $normalizedUserAnswer = normalizeText($userAnswer);
    
    // First check for exact matches after normalization
    foreach ($acceptableAnswers as $answer) {
        if (normalizeText($answer['answer_text']) === $normalizedUserAnswer) {
            return true;
        }
    }
    
    // Check for fuzzy matches
    $words = explode(' ', $normalizedUserAnswer);
    if (count($words) <= 3) { // Only do fuzzy matching for 1-3 word answers
        foreach ($acceptableAnswers as $answer) {
            $normalizedAcceptableAnswer = normalizeText($answer['answer_text']);
            
            // For short answers, use Levenshtein distance
            $maxLength = max(strlen($normalizedUserAnswer), strlen($normalizedAcceptableAnswer));
            $threshold = min(0.8, 1 - (2 / $maxLength)); // Adaptive threshold
            $maxAllowedDistance = floor($maxLength * (1 - $threshold));
            
            $distance = levenshteinDistance($normalizedUserAnswer, $normalizedAcceptableAnswer);
            if ($distance <= $maxAllowedDistance) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Helper function to normalize text
 * 
 * @param string $text Text to normalize
 * @return string Normalized text
 */
function normalizeText($text) {
    return strtolower(
        trim(
            preg_replace('/\s+/', ' ', // Replace multiple spaces with a single space
                preg_replace('/[.,;:!?()\'"-]/', '', $text) // Remove common punctuation
            )
        )
    );
}

/**
 * Helper function to calculate Levenshtein distance
 * 
 * @param string $str1 First string
 * @param string $str2 Second string
 * @return int Levenshtein distance
 */
function levenshteinDistance($str1, $str2) {
    $len1 = strlen($str1);
    $len2 = strlen($str2);
    
    // Quick check for empty strings
    if ($len1 == 0) return $len2;
    if ($len2 == 0) return $len1;
    
    // Use PHP's built-in levenshtein function
    return levenshtein($str1, $str2);
}

/**
 * Save overall quiz results to database
 */
function saveQuizResults() {
    global $pdo;
    
    try {
        $userId = $_SESSION['user_id'];
        $totalQuestions = count($_SESSION['questions']);
        $correctAnswers = $_SESSION['correct_answers'];
        $categoriesStr = implode(',', $_SESSION['quiz_categories']);
        $quizType = $_SESSION['quiz_type'];
        $quizDuration = time() - ($_SESSION['quiz_started_at'] ?? time());
        
        $stmt = $pdo->prepare("INSERT INTO user_attempts (user_id, total_questions, correct_answers, categories, quiz_type, duration_seconds) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $totalQuestions, $correctAnswers, $categoriesStr, $quizType, $quizDuration]);
    } catch (PDOException $e) {
        // Log error but continue
        error_log("Error saving quiz results: " . $e->getMessage());
    }
}

/**
 * Initialize a new quiz based on POST data
 */
function initializeQuiz() {
    // Clear any existing quiz session data first
    clearQuizSession();
    
    // Now proceed with setting up the new quiz
    $_SESSION['quiz_type'] = $_POST['quiz_type'];
    
    // Store categories based on quiz type
    switch($_POST['quiz_type']) {
        case 'quick':
            $_SESSION['quiz_categories'] = $_POST['quick_categories'] ?? [];
            $_SESSION['quiz_num_questions'] = 10; // Fixed for quick quiz
            break;
        case 'spaced_repetition':
            $_SESSION['quiz_categories'] = $_POST['sr_categories'] ?? [];
            $_SESSION['quiz_num_questions'] = intval($_POST['sr_num_questions'] ?? 20);
            $_SESSION['quiz_adaptive'] = true; // Spaced repetition uses adaptive logic
            $_SESSION['quiz_spaced_repetition'] = true; // Flag for spaced repetition mode
            break;
        case 'test':
            $_SESSION['quiz_categories'] = $_POST['test_categories'] ?? [];
            $_SESSION['quiz_num_questions'] = intval($_POST['test_num_questions'] ?? 20);
            $_SESSION['quiz_difficulty'] = $_POST['test_difficulty'] ?? 'medium';
            $_SESSION['quiz_show_results_at_end'] = true; // For test mode, show results only at the end
            break;
        case 'adaptive':
            $_SESSION['quiz_categories'] = $_POST['adaptive_categories'] ?? [];
            $_SESSION['quiz_max_questions'] = intval($_POST['adaptive_max_questions'] ?? 20);
            $_SESSION['quiz_adaptive'] = true; // Flag for adaptive mode
            $_SESSION['quiz_show_results_at_end'] = true; // Add this to hide results until the end
            break;
    }
    
    // Validate categories
    if (empty($_SESSION['quiz_categories'])) {
        // Set error to be displayed on selection page
        $_SESSION['quiz_error'] = "Please select at least one category.";
        return false;
    }
    
    // Initialize quiz state
    $_SESSION['current_question'] = 0;
    $_SESSION['correct_answers'] = 0;
    $_SESSION['questions'] = [];
    $_SESSION['show_explanation'] = false;
    $_SESSION['current_answer_correct'] = null;
    $_SESSION['selected_answer_id'] = null;
    $_SESSION['written_user_answer'] = null;
    $_SESSION['quiz_started_at'] = time();
    
    // Load questions from database based on selections
    $_SESSION['questions'] = loadQuestions();
    
    return true;
}

/**
 * Clear quiz session data
 */
function clearQuizSession() {
    unset($_SESSION['quiz_type']);
    unset($_SESSION['quiz_categories']);
    unset($_SESSION['quiz_subcategories']);
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
}

/**
 * Process a question's rating and prepare for the next question
 * 
 * @param int $questionId Question ID
 * @param string $difficulty User's difficulty rating
 * @return bool Success status
 */
function processQuestionRating($questionId, $difficulty) {
    global $pdo;
    
    $currentQuestion = $_SESSION['questions'][$_SESSION['current_question']];
    $questionType = $currentQuestion['question_type'];
    $isCorrect = $_SESSION['current_answer_correct'];
    
    // Skip updating difficulty if user didn't rate
    $updateDifficulty = ($difficulty !== 'unrated');
    
    try {
        // Only update user's difficulty rating if they provided one
        if ($updateDifficulty) {
            $stmt = $pdo->prepare("
                INSERT INTO user_question_status (user_id, question_id, status)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE status = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $questionId, $difficulty, $difficulty]);
        }
        
        // Store result for this question
        if ($questionType === 'multiple_choice') {
            $selectedAnswerId = $_SESSION['selected_answer_id'];
            $_SESSION['questions'][$_SESSION['current_question']]['user_answer'] = $selectedAnswerId;
        } else {
            $userAnswer = $_SESSION['written_user_answer'];
            $_SESSION['questions'][$_SESSION['current_question']]['user_written_answer'] = $userAnswer;
        }
        
        $_SESSION['questions'][$_SESSION['current_question']]['is_correct'] = $isCorrect;
        
        // Only store user rating if they provided one
        if ($updateDifficulty) {
            $_SESSION['questions'][$_SESSION['current_question']]['user_rating'] = $difficulty;
        }
        
        if ($isCorrect) {
            $_SESSION['correct_answers']++;
        }
        
        // In adaptive mode, adjust next question difficulty based on answer
        if (isset($_SESSION['quiz_adaptive']) && $_SESSION['quiz_adaptive']) {
            adjustAdaptiveDifficulty($isCorrect);
        }
        
        // Update question difficulty based on user rating only if provided
        if ($updateDifficulty) {
            updateQuestionDifficulty($questionId, $difficulty);
        }
        
        // Record the answer in the database
        if ($questionType === 'multiple_choice') {
            $selectedAnswerId = $_SESSION['selected_answer_id'];
            recordAnswer($questionId, $selectedAnswerId, $isCorrect);
        } else {
            $userAnswer = $_SESSION['written_user_answer'];
            recordWrittenAnswer($questionId, $userAnswer, $isCorrect);
        }
        
        // Process for spaced repetition if enabled
        if (isset($_SESSION['quiz_spaced_repetition']) && $_SESSION['quiz_spaced_repetition']) {
            $sr = new SpacedRepetition($pdo);
            // Convert to SM-2 quality score (0-5)
            $quality = $isCorrect ? 5 : 0; // Simplified for this integration
            $sr->processReview($_SESSION['user_id'], $questionId, $quality);
        }
        
        // Reset explanation flag and move to the next question
        $_SESSION['show_explanation'] = false;
        $_SESSION['current_answer_correct'] = null;
        $_SESSION['selected_answer_id'] = null;
        $_SESSION['written_user_answer'] = null;
        $_SESSION['current_question']++;
        
        // Check if quiz is complete
        if ($_SESSION['current_question'] >= count($_SESSION['questions'])) {
            // Save quiz results to database
            saveQuizResults();
            
            // Set completed flag
            $_SESSION['quiz_completed'] = true;
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Quiz error: " . $e->getMessage());
        return false;
    }
}

/**
 * Process an answer submission
 * 
 * @param int $questionId Question ID
 * @param mixed $answer User's answer (ID or text)
 * @return array Result with status and message
 */
function processAnswerSubmission($questionId, $answer) {
    $currentQuestion = $_SESSION['questions'][$_SESSION['current_question']];
    $questionType = $currentQuestion['question_type'];
    $result = [
        'success' => false,
        'message' => '',
        'redirect' => false
    ];
    
    if ($questionType === 'multiple_choice') {
        $selectedAnswerId = $answer;
        
        if (empty($selectedAnswerId)) {
            $result['message'] = "Please select an answer";
            return $result;
        }
        
        // Check if answer is correct
        $isCorrect = false;
        foreach ($currentQuestion['answers'] as $answerOption) {
            if ($answerOption['id'] == $selectedAnswerId && $answerOption['is_correct']) {
                $isCorrect = true;
                break;
            }
        }
        
        // Store the current answer result
        $_SESSION['current_answer_correct'] = $isCorrect;
        $_SESSION['selected_answer_id'] = $selectedAnswerId;
        
        // Show explanation or move to next question based on quiz type
        if (($_SESSION['quiz_type'] === 'test' || $_SESSION['quiz_type'] === 'adaptive') && isset($_SESSION['quiz_show_results_at_end'])) {
            // Just record the answer and move to next question
            $_SESSION['questions'][$_SESSION['current_question']]['user_answer'] = $selectedAnswerId;
            $_SESSION['questions'][$_SESSION['current_question']]['is_correct'] = $isCorrect;
            
            if ($isCorrect) {
                $_SESSION['correct_answers']++;
            }
            
            // Record answer in database
            recordAnswer($questionId, $selectedAnswerId, $isCorrect);
            
            // For adaptive quiz, still need to adjust difficulty
            if ($_SESSION['quiz_type'] === 'adaptive' && isset($_SESSION['quiz_adaptive'])) {
                adjustAdaptiveDifficulty($isCorrect);
            }
            
            // Move to next question
            $_SESSION['current_question']++;
            
            // Check if quiz is complete
            if ($_SESSION['current_question'] >= count($_SESSION['questions'])) {
                saveQuizResults();
                $_SESSION['quiz_completed'] = true;
            }
            
            $result['success'] = true;
            $result['redirect'] = true;
            return $result;
        } else {
            // For other quiz types, show explanation
            $_SESSION['show_explanation'] = true;
            $result['success'] = true;
            return $result;
        }
    } else {
        // Written response logic
        $userAnswer = trim($answer);
        
        if (empty($userAnswer)) {
            $result['message'] = "Please enter an answer";
            return $result;
        }
        
        // Check against acceptable answers with fuzzy matching
        $isCorrect = checkWrittenAnswer($userAnswer, $currentQuestion['written_answers']);
        
        // Store the current answer result
        $_SESSION['current_answer_correct'] = $isCorrect;
        $_SESSION['written_user_answer'] = $userAnswer;
        
        // Show explanation or move to next question based on quiz type
        if (($_SESSION['quiz_type'] === 'test' || $_SESSION['quiz_type'] === 'adaptive') && isset($_SESSION['quiz_show_results_at_end'])) {
            // Just record the answer and move to next question
            $_SESSION['questions'][$_SESSION['current_question']]['user_written_answer'] = $userAnswer;
            $_SESSION['questions'][$_SESSION['current_question']]['is_correct'] = $isCorrect;
            
            if ($isCorrect) {
                $_SESSION['correct_answers']++;
            }
            
            // Record answer in database
            recordWrittenAnswer($questionId, $userAnswer, $isCorrect);
            
            // For adaptive quiz, still need to adjust difficulty
            if ($_SESSION['quiz_type'] === 'adaptive' && isset($_SESSION['quiz_adaptive'])) {
                adjustAdaptiveDifficulty($isCorrect);
            }
            
            // Move to next question
            $_SESSION['current_question']++;
            
            // Check if quiz is complete
            if ($_SESSION['current_question'] >= count($_SESSION['questions'])) {
                saveQuizResults();
                $_SESSION['quiz_completed'] = true;
            }
            
            $result['success'] = true;
            $result['redirect'] = true;
            return $result;
        } else {
            // For other quiz types, show explanation
            $_SESSION['show_explanation'] = true;
            $result['success'] = true;
            return $result;
        }
    }
}