<?php
/**
 * Live Quiz Control Script
 * 
 * This script handles admin actions for controlling the live quiz session:
 * - Starting a session
 * - Moving to the next question
 * - Ending a session
 * - Closing a completed session
 */

require_once 'config.php';
requireLogin(); // Ensure the user is logged in

// Check if user is allowed to control sessions (admins only for now)
$canControl = isAdmin();

if (!$canControl) {
    header("Location: /");
    exit;
}

// Process control actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $sessionId = isset($_POST['session_id']) ? (int)$_POST['session_id'] : 0;
    
    // Validate session ID and ownership
    if ($sessionId <= 0) {
        $_SESSION['message'] = "Invalid session ID.";
        $_SESSION['message_type'] = "error";
        header("Location: live_quiz_host.php");
        exit;
    }
    
    try {
        // Check if session exists and is owned by the current user
        $stmt = $pdo->prepare("SELECT * FROM live_quiz_sessions WHERE id = ? AND host_id = ?");
        $stmt->execute([$sessionId, $_SESSION['user_id']]);
        $session = $stmt->fetch();
        
        if (!$session) {
            $_SESSION['message'] = "Session not found or you don't have permission to manage it.";
            $_SESSION['message_type'] = "error";
            header("Location: live_quiz_host.php");
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
                $_SESSION['message'] = "Invalid action.";
                $_SESSION['message_type'] = "error";
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = "Database error: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
        error_log("Live quiz control error: " . $e->getMessage());
    }
    
    // Redirect back to the host page
    header("Location: live_quiz_host.php");
    exit;
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
        activateNextQuestion($pdo, $sessionId);
        
        $_SESSION['message'] = "Quiz session started successfully!";
        $_SESSION['message_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['message'] = "Error starting session: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
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
        $result = activateNextQuestion($pdo, $sessionId);
        
        if ($result) {
            $_SESSION['message'] = "Moved to the next question.";
            $_SESSION['message_type'] = "success";
        } else {
            // No more questions - end the session
            endSession($pdo, $sessionId);
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = "Error advancing to next question: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
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
        
        $_SESSION['message'] = "Quiz session has been ended.";
        $_SESSION['message_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['message'] = "Error ending session: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
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
        
        $_SESSION['message'] = "Session closed successfully. You can now create a new quiz.";
        $_SESSION['message_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['message'] = "Error closing session: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
        error_log("Live quiz close error: " . $e->getMessage());
    }
}

/**
 * Activate the next available question
 * 
 * @param PDO $pdo Database connection
 * @param int $sessionId Session ID
 * @return bool True if a question was activated, false if no more questions
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
        
        return true;
    }
    
    return false;
}
