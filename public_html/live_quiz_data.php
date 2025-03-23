<?php
/**
 * Live Quiz Data API
 * 
 * This script handles AJAX requests for live quiz data:
 * - Getting participant list
 * - Getting leaderboard data
 * - Checking for question updates
 * - Getting question details
 * - Getting quiz results
 */

require_once 'config.php';
requireLogin(); // Ensure the user is logged in

// Set content type to JSON
header('Content-Type: application/json');

// Get the requested action
$action = $_GET['action'] ?? '';
$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;

// Validate session ID for most actions
if ($sessionId <= 0 && $action !== 'join_session') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid session ID.'
    ]);
    exit;
}

try {
    // Process the requested action
    switch ($action) {
        case 'get_participants':
            getParticipants($pdo, $sessionId);
            break;
            
        case 'get_leaderboard':
            getLeaderboard($pdo, $sessionId);
            break;
            
        case 'check_question':
            checkQuestionStatus($pdo, $sessionId);
            break;
            
        case 'get_question':
            getQuestionDetails($pdo, $_GET['question_id'] ?? 0);
            break;
            
        case 'get_results':
            getQuizResults($pdo, $sessionId);
            break;
            
        case 'join_session':
            joinSession($pdo);
            break;
            
        default:
            throw new Exception("Invalid action.");
    }
} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
    // Log the error
    error_log("Live quiz data error: " . $e->getMessage());
}

/**
 * Get the list of participants for a session
 * 
 * @param PDO $pdo Database connection
 * @param int $sessionId Session ID
 */
function getParticipants($pdo, $sessionId) {
    // Get participants with scores
    $stmt = $pdo->prepare("
        SELECT id, display_name, score, correct_answers, total_answers 
        FROM live_quiz_participants 
        WHERE session_id = ? 
        ORDER BY score DESC
    ");
    $stmt->execute([$sessionId]);
    $participants = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'participants' => $participants
    ]);
}

/**
 * Get the leaderboard for a session
 * 
 * @param PDO $pdo Database connection
 * @param int $sessionId Session ID
 */
function getLeaderboard($pdo, $sessionId) {
    // Get session status
    $stmt = $pdo->prepare("SELECT status FROM live_quiz_sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $status = $stmt->fetchColumn();
    
    // Get top 10 participants by score
    $stmt = $pdo->prepare("
        SELECT display_name, score, correct_answers, total_answers 
        FROM live_quiz_participants 
        WHERE session_id = ? 
        ORDER BY score DESC 
        LIMIT 10
    ");
    $stmt->execute([$sessionId]);
    $leaderboard = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'session_status' => $status,
        'leaderboard' => $leaderboard
    ]);
}

/**
 * Check the status of the current question for a participant
 * 
 * @param PDO $pdo Database connection
 * @param int $sessionId Session ID
 */
function checkQuestionStatus($pdo, $sessionId) {
    // This is used by participants to check if the question has changed
    
    // If user is not in a session, return error
    if (!isset($_SESSION['live_quiz_participant_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Not a participant in this session.',
            'reload' => false
        ]);
        return;
    }
    
    $participantId = $_SESSION['live_quiz_participant_id'];
    
    // Get current active question ID
    $stmt = $pdo->prepare("
        SELECT question_id 
        FROM live_quiz_session_questions 
        WHERE session_id = ? AND status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$sessionId]);
    $activeQuestionId = $stmt->fetchColumn();
    
    // Get session status
    $stmt = $pdo->prepare("SELECT status FROM live_quiz_sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $sessionStatus = $stmt->fetchColumn();
    
    // Check if user has answered the current question
    $hasAnswered = false;
    
    if ($activeQuestionId) {
        $stmt = $pdo->prepare("
            SELECT id 
            FROM live_quiz_answers 
            WHERE participant_id = ? AND question_id = ?
            LIMIT 1
        ");
        $stmt->execute([$participantId, $activeQuestionId]);
        $hasAnswered = $stmt->rowCount() > 0;
    }
    
    // Get user's current view state
    $currentQuestionId = $_GET['current_question_id'] ?? null;
    $currentlyAnswered = isset($_GET['answered']) ? (bool)$_GET['answered'] : false;
    
    // Determine if content needs to be updated
    $needsUpdate = false;
    
    // Session status change
    if ($sessionStatus !== 'in_progress') {
        $needsUpdate = true;
    }
    // Question changed
    elseif ($activeQuestionId != $currentQuestionId) {
        $needsUpdate = true;
    }
    // Answer status changed
    elseif ($hasAnswered != $currentlyAnswered) {
        $needsUpdate = true;
    }
    
    echo json_encode([
        'success' => true,
        'reload' => $needsUpdate,
        'session_status' => $sessionStatus,
        'session_id' => $sessionId,
        'active_question' => [
            'id' => $activeQuestionId
        ],
        'has_answered' => $hasAnswered
    ]);
}

/**
 * Get detailed information about a specific question
 * 
 * @param PDO $pdo Database connection
 * @param int $questionId Question ID
 */
function getQuestionDetails($pdo, $questionId) {
    if ($questionId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid question ID.'
        ]);
        return;
    }
    
    // Get question details
    $stmt = $pdo->prepare("
        SELECT q.*, lqsq.session_id
        FROM questions q
        JOIN live_quiz_session_questions lqsq ON q.id = lqsq.question_id
        WHERE q.id = ?
    ");
    $stmt->execute([$questionId]);
    $question = $stmt->fetch();
    
    if (!$question) {
        echo json_encode([
            'success' => false,
            'message' => 'Question not found.'
        ]);
        return;
    }
    
    // Get time per question from session
    $stmt = $pdo->prepare("SELECT time_per_question FROM live_quiz_sessions WHERE id = ?");
    $stmt->execute([$question['session_id']]);
    $timePerQuestion = $stmt->fetchColumn();
    
    // Get answers if it's a multiple choice question
    if ($question['question_type'] === 'multiple_choice') {
        $stmt = $pdo->prepare("SELECT id, answer_text FROM answers WHERE question_id = ? ORDER BY RAND()");
        $stmt->execute([$questionId]);
        $question['answers'] = $stmt->fetchAll();
    }
    
    echo json_encode([
        'success' => true,
        'question' => $question,
        'time_per_question' => $timePerQuestion
    ]);
}

/**
 * Get quiz results for a participant
 * 
 * @param PDO $pdo Database connection
 * @param int $sessionId Session ID
 */
function getQuizResults($pdo, $sessionId) {
    if (!isset($_SESSION['live_quiz_participant_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Not a participant in this session.'
        ]);
        return;
    }
    
    $participantId = $_SESSION['live_quiz_participant_id'];
    
    // Get participant's final score and stats
    $stmt = $pdo->prepare("
        SELECT score, correct_answers, total_answers
        FROM live_quiz_participants
        WHERE id = ? AND session_id = ?
    ");
    $stmt->execute([$participantId, $sessionId]);
    $participant = $stmt->fetch();
    
    if (!$participant) {
        echo json_encode([
            'success' => false,
            'message' => 'Participant data not found.'
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'user_score' => $participant['score'],
        'correct_answers' => $participant['correct_answers'],
        'total_answers' => $participant['total_answers'],
        'accuracy' => $participant['total_answers'] > 0 ? 
            round(($participant['correct_answers'] / $participant['total_answers']) * 100) : 0
    ]);
}

/**
 * Join a quiz session
 * 
 * @param PDO $pdo Database connection
 */
function joinSession($pdo) {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    $sessionCode = isset($input['session_code']) ? strtoupper(trim($input['session_code'])) : '';
    $displayName = isset($input['display_name']) ? trim($input['display_name']) : '';
    
    if (empty($sessionCode)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please enter a session code.'
        ]);
        return;
    }
    
    if (empty($displayName)) {
        echo json_encode([
            'success' => false,
            'message' => 'Please enter a display name.'
        ]);
        return;
    }
    
    // Check if session exists and is active
    $stmt = $pdo->prepare("SELECT * FROM live_quiz_sessions WHERE session_code = ? AND status != 'closed'");
    $stmt->execute([$sessionCode]);
    $session = $stmt->fetch();
    
    if (!$session) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid session code or session has ended.'
        ]);
        return;
    }
    
    // Check if user is already a participant
    $stmt = $pdo->prepare("SELECT * FROM live_quiz_participants WHERE session_id = ? AND user_id = ?");
    $stmt->execute([$session['id'], $_SESSION['user_id']]);
    $participant = $stmt->fetch();
    
    if (!$participant) {
        // Add user as a participant
        $stmt = $pdo->prepare("INSERT INTO live_quiz_participants (
                               session_id, user_id, display_name, joined_at, last_active
                               ) VALUES (?, ?, ?, NOW(), NOW())");
        $stmt->execute([$session['id'], $_SESSION['user_id'], $displayName]);
        
        $participantId = $pdo->lastInsertId();
    } else {
        $participantId = $participant['id'];
        
        // Update display name if changed
        if ($participant['display_name'] !== $displayName) {
            $stmt = $pdo->prepare("UPDATE live_quiz_participants SET display_name = ?, last_active = NOW() WHERE id = ?");
            $stmt->execute([$displayName, $participantId]);
        } else {
            // Just update last_active
            $stmt = $pdo->prepare("UPDATE live_quiz_participants SET last_active = NOW() WHERE id = ?");
            $stmt->execute([$participantId]);
        }
    }
    
    // Store session ID in user session
    $_SESSION['live_quiz_session_id'] = $session['id'];
    $_SESSION['live_quiz_participant_id'] = $participantId;
    $_SESSION['live_quiz_display_name'] = $displayName;
    
    // Get active question if any
    $activeQuestion = null;
    if ($session['status'] === 'in_progress') {
        $stmt = $pdo->prepare("
            SELECT question_id 
            FROM live_quiz_session_questions 
            WHERE session_id = ? AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$session['id']]);
        $activeQuestionId = $stmt->fetchColumn();
        
        if ($activeQuestionId) {
            $activeQuestion = ['id' => $activeQuestionId];
        }
    }
    
    echo json_encode([
        'success' => true,
        'session_id' => $session['id'],
        'session_status' => $session['status'],
        'display_name' => $displayName,
        'active_question' => $activeQuestion
    ]);
}