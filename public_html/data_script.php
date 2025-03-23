<?php
/**
 * Live Quiz Data Script
 * 
 * This script handles AJAX requests for real-time data:
 * - Getting participant list
 * - Getting leaderboard data
 * - Checking for question updates
 */

require_once 'config.php';
requireLogin(); // Ensure the user is logged in

// Set content type to JSON
header('Content-Type: application/json');

// Get the requested action
$action = $_GET['action'] ?? '';
$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;

// Validate session ID
if ($sessionId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid session ID.'
    ]);
    exit;
}

try {
    // Check if session exists
    $stmt = $pdo->prepare("SELECT * FROM live_quiz_sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();
    
    if (!$session) {
        throw new Exception("Session not found.");
    }
    
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
    
    // Determine if page needs to be reloaded
    $needsReload = false;
    
    // Session status change
    if ($sessionStatus !== 'in_progress') {
        $needsReload = true;
    }
    // Question changed
    elseif ($activeQuestionId != $currentQuestionId) {
        $needsReload = true;
    }
    // Answer status changed
    elseif ($hasAnswered != $currentlyAnswered) {
        $needsReload = true;
    }
    
    echo json_encode([
        'success' => true,
        'reload' => $needsReload,
        'session_status' => $sessionStatus,
        'active_question_id' => $activeQuestionId,
        'has_answered' => $hasAnswered
    ]);
}
