<?php
/**
 * Live Quiz Control API
 * 
 * This script handles AJAX requests for controlling the live quiz session:
 * - Starting a session
 * - Moving to the next question
 * - Ending a session
 * - Closing a completed session
 */

require_once 'config.php';
requireLogin(); // Ensure the user is logged in

// Set content type to JSON
header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$sessionId = isset($input['session_id']) ? (int)$input['session_id'] : 0;

// Check if user is allowed to control sessions (admins only for now)
$canControl = isAdmin();

if (!$canControl) {
    echo json_encode([
        'success' => false,
        'message' => 'You do not have permission to control quiz sessions.'
    ]);
    exit;
}

// Validate session ID and ownership
if ($sessionId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid session ID.'
    ]);
    exit;
}

try {
    // Check if session exists and is owned by the current user
    $stmt = $pdo->prepare("SELECT * FROM live_quiz_sessions WHERE id = ? AND host_id = ?");
    $stmt->execute([$sessionId, $_SESSION['user_id']]);
    $session = $stmt->fetch();
    
    if (!$session) {
        echo json_encode([
            'success' => false,
            'message' => 'Session not found or you don\'t have permission to manage it.'
        ]);
        exit;
    }
    
    // Execute the requested action
    switch ($action) {
        case 'start_session':
            startSession($pdo, $sessionId);
            break;
            
        case 'next_question':
            nextQuestion($pdo, $sessionId);
            break;
            
        case 'end_session':
            endSession($pdo, $sessionId);
            break;
            
        case 'close_session':
            closeSession($pdo, $sessionId);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action.'
            ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    error_log("Live quiz control error: " . $e->getMessage());
}

/**
 * Start a quiz session
 * 
 * @param PDO $pdo Database connection
 * @param int $sessionId Session ID
 */
function startSession($pdo, $sessionId) {
    try {
        // Update session status
        $stmt = $pdo->prepare("UPDATE live_quiz_sessions SET status = 'in_progress', started_at = NOW() WHERE id = ?");
        $stmt->execute([$sessionId]);
        
        // Activate the first question
        $questionId = activateNextQuestion($pdo, $sessionId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Quiz session started successfully!',
            'status' => 'in_progress',
            'current_question' => $questionId
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error starting session: ' . $e->getMessage()
        ]);
        error_log("Live quiz start error: " . $e->getMessage());
    }
}

/**
 * Move to the next question
 * 
 * @param PDO $pdo Database connection
 * @param int $sessionId Session ID
 */
function nextQuestion($pdo, $sessionId) {
    try {
        // Mark the current active question as completed
        $stmt = $pdo->prepare("
            UPDATE live_quiz_session_questions 
            SET status = 'completed', completed_at = NOW() 
            WHERE session_id = ? AND status = 'active'
        ");
        $stmt->execute([$sessionId]);
        
        // Activate the next question
        $questionId = activateNextQuestion($pdo, $sessionId);
        
        if ($questionId) {
            echo json_encode([
                'success' => true,
                'message' => 'Moved to the next question.',
                'current_question' => $questionId
            ]);
        } else {
            // No more questions - end the session
            endSession($pdo, $sessionId);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error advancing to next question: ' . $e->getMessage()
        ]);
        error_log("Live quiz next question error: " . $e->getMessage());
    }
}

/**
 * End a quiz session
 * 
 * @param PDO $pdo Database connection
 * @param int $sessionId Session ID
 */
function endSession($pdo, $sessionId) {
    try {
        // Mark any active questions as completed
        $stmt = $pdo->prepare("
            UPDATE live_quiz_session_questions 
            SET status = 'completed', completed_at = NOW() 
            WHERE session_id = ? AND status = 'active'
        ");
        $stmt->execute([$sessionId]);
        
        // Mark all pending questions as skipped
        $stmt = $pdo->prepare("
            UPDATE live_quiz_session_questions 
            SET status = 'skipped' 
            WHERE session_id = ? AND status = 'pending'
        ");
        $stmt->execute([$sessionId]);
        
        // Update session status
        $stmt = $pdo->prepare("UPDATE live_quiz_sessions SET status = 'completed', completed_at = NOW() WHERE id = ?");
        $stmt->execute([$sessionId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Quiz session has been ended.',
            'status' => 'completed'
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error ending session: ' . $e->getMessage()
        ]);
        error_log("Live quiz end error: " . $e->getMessage());
    }
}

/**
 * Close a completed session (cleanup)
 * 
 * @param PDO $pdo Database connection
 * @param int $sessionId Session ID
 */
function closeSession($pdo, $sessionId) {
    try {
        // Update session status
        $stmt = $pdo->prepare("UPDATE live_quiz_sessions SET status = 'closed' WHERE id = ?");
        $stmt->execute([$sessionId]);
        
        // Clear the session ID from the host's session
        unset($_SESSION['live_quiz_host_session_id']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Session closed successfully. You can now create a new quiz.',
            'status' => 'closed'
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error closing session: ' . $e->getMessage()
        ]);
        error_log("Live quiz close error: " . $e->getMessage());
    }
}

/**
 * Activate the next available question
 * 
 * @param PDO $pdo Database connection
 * @param int $sessionId Session ID
 * @return int|null The question ID that was activated, or null if no more questions
 */
function activateNextQuestion($pdo, $sessionId) {
    // Find the next pending question
    $stmt = $pdo->prepare("
        SELECT id, question_id 
        FROM live_quiz_session_questions 
        WHERE session_id = ? AND status = 'pending' 
        ORDER BY question_order ASC 
        LIMIT 1
    ");
    $stmt->execute([$sessionId]);
    $nextQuestion = $stmt->fetch();
    
    if ($nextQuestion) {
        // Activate the question
        $stmt = $pdo->prepare("
            UPDATE live_quiz_session_questions 
            SET status = 'active', started_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$nextQuestion['id']]);
        
        return $nextQuestion['question_id'];
    }
    
    return null;
}