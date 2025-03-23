<?php
/**
 * Live Quiz Events API
 * 
 * This script simulates real-time events using polling.
 * In a production environment, this could be replaced with WebSockets.
 */

require_once 'config.php';
requireLogin(); // Ensure the user is logged in

// Set content type to JSON
header('Content-Type: application/json');

// Get session ID from request
$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;

if ($sessionId <= 0) {
    echo json_encode([]);
    exit;
}

// Initialize events array
$events = [];

// Check session status (if changed since last poll)
$stmt = $pdo->prepare("SELECT status, updated_at FROM live_quiz_sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

// Get last poll timestamp from session or use current time - 5 seconds
$lastPoll = $_SESSION['last_event_poll_' . $sessionId] ?? (time() - 5);
$currentTime = time();

// Update last poll timestamp
$_SESSION['last_event_poll_' . $sessionId] = $currentTime;

// Check if session status changed
if ($session && strtotime($session['updated_at']) > $lastPoll) {
    if ($session['status'] === 'in_progress') {
        $events[] = [
            'type' => 'session_started',
            'timestamp' => $currentTime
        ];
    } elseif ($session['status'] === 'completed') {
        $events[] = [
            'type' => 'session_ended',
            'timestamp' => $currentTime,
            'data' => [
                'session_id' => $sessionId
            ]
        ];
    }
}

// Check if question changed
$stmt = $pdo->prepare("
    SELECT q.id, q.question_text, q.question_type, lqsq.started_at, lqsq.completed_at
    FROM live_quiz_session_questions lqsq
    JOIN questions q ON lqsq.question_id = q.id
    WHERE lqsq.session_id = ? AND (
        (lqsq.status = 'active' AND lqsq.started_at > FROM_UNIXTIME(?)) OR
        (lqsq.status = 'completed' AND lqsq.completed_at > FROM_UNIXTIME(?))
    )
");
$stmt->execute([$sessionId, $lastPoll, $lastPoll]);
$questions = $stmt->fetchAll();

foreach ($questions as $question) {
    if (!empty($question['started_at']) && strtotime($question['started_at']) > $lastPoll) {
        $events[] = [
            'type' => 'question_started',
            'timestamp' => strtotime($question['started_at']),
            'data' => [
                'id' => $question['id']
            ]
        ];
    }
    
    if (!empty($question['completed_at']) && strtotime($question['completed_at']) > $lastPoll) {
        $events[] = [
            'type' => 'question_ended',
            'timestamp' => strtotime($question['completed_at']),
            'data' => [
                'id' => $question['id']
            ]
        ];
    }
}

// Check for score updates if participant
if (isset($_SESSION['live_quiz_participant_id'])) {
    $participantId = $_SESSION['live_quiz_participant_id'];
    $stmt = $pdo->prepare("
        SELECT score, updated_at 
        FROM live_quiz_participants 
        WHERE id = ? AND updated_at > FROM_UNIXTIME(?)
    ");
    $stmt->execute([$participantId, $lastPoll]);
    $participant = $stmt->fetch();
    
    if ($participant) {
        $events[] = [
            'type' => 'score_updated',
            'timestamp' => strtotime($participant['updated_at']),
            'data' => [
                'score' => $participant['score']
            ]
        ];
    }
}

// Sort events by timestamp
usort($events, function($a, $b) {
    return $a['timestamp'] - $b['timestamp'];
});

echo json_encode($events);