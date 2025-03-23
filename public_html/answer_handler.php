<?php
/**
 * Live Quiz Answer Processing Script
 * 
 * This script:
 * - Processes answer submissions from participants
 * - Validates answers and calculates scores
 * - Returns scoring results
 */

require_once 'config.php';
requireLogin(); // Ensure the user is logged in

// Set content type to JSON
header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Check if user is in a session
if (!isset($_SESSION['live_quiz_session_id']) || !isset($_SESSION['live_quiz_participant_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'You are not in an active quiz session.'
    ]);
    exit;
}

$sessionId = $_SESSION['live_quiz_session_id'];
$participantId = $_SESSION['live_quiz_participant_id'];

// Validate input
if (!isset($input['question_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing question ID.'
    ]);
    exit;
}

$questionId = $input['question_id'];
$answerId = $input['answer_id'] ?? null;
$writtenAnswer = $input['written_answer'] ?? null;
$timeTaken = isset($input['time_taken']) ? max(1, min((int)$input['time_taken'], 120)) : 0;
$timedOut = isset($input['timed_out']) ? (bool)$input['timed_out'] : false;

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Check if session is active
    $stmt = $pdo->prepare("SELECT * FROM live_quiz_sessions WHERE id = ? AND status = 'in_progress'");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    
    if (!$session) {
        throw new Exception("Session is not active.");
    }
    
    // Check if question is active
    $stmt = $pdo->prepare("
        SELECT lqsq.*, q.question_type 
        FROM live_quiz_session_questions lqsq
        JOIN questions q ON lqsq.question_id = q.id
        WHERE lqsq.session_id = ? AND lqsq.question_id = ? AND lqsq.status = 'active'
    ");
    $stmt->execute([$sessionId, $questionId]);
    $question = $stmt->fetch();
    
    if (!$question) {
        throw new Exception("Question is not active.");
    }
    
    // Check if user has already answered this question
    $stmt = $pdo->prepare("
        SELECT id FROM live_quiz_answers 
        WHERE participant_id = ? AND question_id = ?
    ");
    $stmt->execute([$participantId, $questionId]);
    
    if ($stmt->rowCount() > 0) {
        throw new Exception("You have already answered this question.");
    }
    
    // Process answer based on question type
    $isCorrect = false;
    $points = 0;
    
    if ($timedOut) {
        // No answer submitted in time
        $isCorrect = false;
        $points = 0;
    } else if ($question['question_type'] === 'multiple_choice') {
        // Multiple choice question
        if ($answerId === null) {
            throw new Exception("No answer selected.");
        }
        
        // Check if answer is correct
        $stmt = $pdo->prepare("SELECT is_correct FROM answers WHERE id = ? AND question_id = ?");
        $stmt->execute([$answerId, $questionId]);
        $answer = $stmt->fetch();
        
        if (!$answer) {
            throw new Exception("Invalid answer ID.");
        }
        
        $isCorrect = (bool)$answer['is_correct'];
        
        // Calculate points based on correctness and time taken
        if ($isCorrect) {
            // Base points for correct answer
            $basePoints = 1000;
            
            // Time bonus: faster answers get more points (up to 1000 bonus points)
            $timePerQuestion = $session['time_per_question'];
            $timeBonus = (int)(($timePerQuestion - $timeTaken) / $timePerQuestion * 1000);
            
            // Total points (minimum 100 for correct answer)
            $points = max(100, $basePoints + $timeBonus);
        }
    } else {
        // Written response question
        if (empty($writtenAnswer)) {
            throw new Exception("No answer provided.");
        }
        
        // Get acceptable answers
        $stmt = $pdo->prepare("
            SELECT answer_text, is_primary
            FROM written_response_answers 
            WHERE question_id = ?
        ");
        $stmt->execute([$questionId]);
        $acceptableAnswers = $stmt->fetchAll();
        
        // Normalize user's answer
        $normalizedUserAnswer = normalizeText($writtenAnswer);
        
        // First check for exact matches
        foreach ($acceptableAnswers as $answer) {
            if (normalizeText($answer['answer_text']) === $normalizedUserAnswer) {
                $isCorrect = true;
                break;
            }
        }
        
        // If no exact match, use fuzzy matching for short answers
        if (!$isCorrect && strlen($normalizedUserAnswer) <= 30) {
            foreach ($acceptableAnswers as $answer) {
                $normalizedAcceptableAnswer = normalizeText($answer['answer_text']);
                
                // For short answers, use Levenshtein distance
                $maxLength = max(strlen($normalizedUserAnswer), strlen($normalizedAcceptableAnswer));
                $threshold = min(0.8, 1 - (2 / $maxLength)); // Adaptive threshold
                $maxAllowedDistance = floor($maxLength * (1 - $threshold));
                
                $distance = levenshtein($normalizedUserAnswer, $normalizedAcceptableAnswer);
                if ($distance <= $maxAllowedDistance) {
                    $isCorrect = true;
                    break;
                }
            }
        }
        
        // Calculate points based on correctness and time taken
        if ($isCorrect) {
            // Base points for correct answer
            $basePoints = 1000;
            
            // Time bonus: faster answers get more points (up to 1000 bonus points)
            $timePerQuestion = $session['time_per_question'];
            $timeBonus = (int)(($timePerQuestion - $timeTaken) / $timePerQuestion * 1000);
            
            // Total points (minimum 100 for correct answer)
            $points = max(100, $basePoints + $timeBonus);
        }
    }
    
    // Record the answer
    $stmt = $pdo->prepare("
        INSERT INTO live_quiz_answers (
            participant_id, session_id, question_id, answer_id, written_answer,
            is_correct, points, time_taken, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $participantId,
        $sessionId,
        $questionId,
        $answerId,
        $writtenAnswer,
        $isCorrect ? 1 : 0,
        $points,
        $timeTaken
    ]);
    
    // Update participant's score
    $stmt = $pdo->prepare("
        UPDATE live_quiz_participants 
        SET score = score + ?, 
            correct_answers = correct_answers + ?, 
            total_answers = total_answers + 1 
        WHERE id = ?
    ");
    $stmt->execute([$points, $isCorrect ? 1 : 0, $participantId]);
    
    // Get updated score
    $stmt = $pdo->prepare("SELECT score FROM live_quiz_participants WHERE id = ?");
    $stmt->execute([$participantId]);
    $updatedScore = $stmt->fetchColumn();
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'is_correct' => $isCorrect,
        'points' => $points,
        'score' => $updatedScore
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
    // Log the error
    error_log("Live quiz answer error: " . $e->getMessage());
}

/**
 * Helper function to normalize text for comparison
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
