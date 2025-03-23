<?php
/**
 * Live Quiz Join Page
 * 
 * This page allows users to:
 * - Enter a code to join a live quiz session
 * - Enter their display name
 * - Participate in the live quiz
 * - See their results in real-time
 */

require_once 'config.php';
requireLogin(); // Ensure the user is logged in

$pageTitle = 'Join Live Quiz';
$extraScripts = ['/js/js-functions.js']; // Custom JS for live quiz functionality

// Initialize variables
$message = '';
$messageType = '';
$activeSession = null;

// Process join session form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['join_session'])) {
    $sessionCode = strtoupper(trim($_POST['session_code'] ?? ''));
    $displayName = trim($_POST['display_name'] ?? '');
    
    if (empty($sessionCode)) {
        $message = "Please enter a session code.";
        $messageType = "error";
    } elseif (empty($displayName)) {
        $message = "Please enter a display name.";
        $messageType = "error";
    } else {
        try {
            // Check if session exists and is active
            $stmt = $pdo->prepare("SELECT * FROM live_quiz_sessions WHERE session_code = ? AND status != 'closed'");
            $stmt->execute([$sessionCode]);
            $session = $stmt->fetch();
            
            if (!$session) {
                $message = "Invalid session code or session has ended.";
                $messageType = "error";
            } else {
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
                    
                    // Update display name if changed and update last active time
                    $stmt = $pdo->prepare("UPDATE live_quiz_participants SET display_name = ?, last_active = NOW() WHERE id = ?");
                    $stmt->execute([$displayName, $participantId]);
                }
                
                // Store session info in user session
                $_SESSION['live_quiz_session_id'] = $session['id'];
                $_SESSION['live_quiz_participant_id'] = $participantId;
                $_SESSION['live_quiz_display_name'] = $displayName;
                
                // Redirect to prevent form resubmission
                header("Location: live_quiz_join.php");
                exit;
            }
        } catch (PDOException $e) {
            $message = "Error joining session: " . $e->getMessage();
            $messageType = "error";
            error_log("Live quiz join error: " . $e->getMessage());
        }
    }
}

// Check if user is already in a session
if (isset($_SESSION['live_quiz_session_id'])) {
    $sessionId = $_SESSION['live_quiz_session_id'];
    
    try {
        // Get session details
        $stmt = $pdo->prepare("SELECT * FROM live_quiz_sessions WHERE id = ? AND status != 'closed'");
        $stmt->execute([$sessionId]);
        $activeSession = $stmt->fetch();
        
        if ($activeSession) {
            // Update participant's last active time
            $stmt = $pdo->prepare("UPDATE live_quiz_participants SET last_active = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['live_quiz_participant_id']]);
            
            // Get current active question if any
            $stmt = $pdo->prepare("
                SELECT lqsq.*, q.question_text, q.question_type, q.image_path, q.difficulty_value 
                FROM live_quiz_session_questions lqsq
                JOIN questions q ON lqsq.question_id = q.id
                WHERE lqsq.session_id = ? AND lqsq.status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$sessionId]);
            $activeQuestion = $stmt->fetch();
            
            if ($activeQuestion) {
                $activeSession['current_question'] = $activeQuestion;
                
                // Get question answers if it's multiple choice
                if ($activeQuestion['question_type'] === 'multiple_choice') {
                    $stmt = $pdo->prepare("
                        SELECT id, answer_text 
                        FROM answers 
                        WHERE question_id = ? 
                        ORDER BY RAND()
                    ");
                    $stmt->execute([$activeQuestion['question_id']]);
                    $activeSession['current_question']['answers'] = $stmt->fetchAll();
                }
                
                // Check if user has already answered this question
                $stmt = $pdo->prepare("
                    SELECT * FROM live_quiz_answers 
                    WHERE participant_id = ? AND question_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$_SESSION['live_quiz_participant_id'], $activeQuestion['question_id']]);
                $userAnswer = $stmt->fetch();
                
                $activeSession['current_question']['user_answered'] = !empty($userAnswer);
                // Store if user has answered in a hidden input for JS
                $activeSession['has_answered'] = !empty($userAnswer) ? 'true' : 'false';
            }
            
            // Get participant's score
            $stmt = $pdo->prepare("
                SELECT score, correct_answers, total_answers 
                FROM live_quiz_participants 
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['live_quiz_participant_id']]);
            $participantStats = $stmt->fetch();
            $activeSession['user_score'] = $participantStats['score'];
            $activeSession['user_accuracy'] = $participantStats['total_answers'] > 0 
                ? round(($participantStats['correct_answers'] / $participantStats['total_answers']) * 100) 
                : 0;
            
            // Get top 5 leaderboard
            $stmt = $pdo->prepare("
                SELECT display_name, score 
                FROM live_quiz_participants 
                WHERE session_id = ? 
                ORDER BY score DESC 
                LIMIT 5
            ");
            $stmt->execute([$sessionId]);
            $activeSession['leaderboard'] = $stmt->fetchAll();
        } else {
            // Session closed or invalid, clear session data
            unset($_SESSION['live_quiz_session_id']);
            unset($_SESSION['live_quiz_participant_id']);
            unset($_SESSION['live_quiz_display_name']);
        }
    } catch (PDOException $e) {
        $message = "Error loading session: " . $e->getMessage();
        $messageType = "error";
        error_log("Live quiz session load error: " . $e->getMessage());
    }
}

// Leave session action
if (isset($_GET['action']) && $_GET['action'] === 'leave') {
    // Clear session data
    unset($_SESSION['live_quiz_session_id']);
    unset($_SESSION['live_quiz_participant_id']);
    unset($_SESSION['live_quiz_display_name']);
    
    // Redirect to prevent URL parameters
    header("Location: live_quiz_join.php");
    exit;
}

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-8 flex justify-between items-center">
        <h1 class="text-3xl font-bold text-indigo-800">Join Live Quiz</h1>
        
        <a href="/" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition duration-150 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
            </svg>
            Back to Home
        </a>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700 border-l-4 border-green-500' : 'bg-red-100 text-red-700 border-l-4 border-red-500'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($activeSession): ?>
        <!-- Active Quiz View -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <!-- Quiz Header -->
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4 flex justify-between items-center">
                <h2 class="text-white text-xl font-bold">
                    <?php echo htmlspecialchars($activeSession['quiz_name']); ?>
                </h2>
                
                <div class="flex items-center">
                    <span class="bg-white text-indigo-700 px-3 py-1 rounded-full text-sm font-medium flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span id="user-score"><?php echo intval($activeSession['user_score']); ?></span>
                    </span>
                </div>
            </div>
            
            <div class="p-6">
                <!-- Hidden inputs for JS -->
                <input type="hidden" id="session-id" value="<?php echo $activeSession['id']; ?>">
                <input type="hidden" id="session-status" value="<?php echo $activeSession['status']; ?>">
                <input type="hidden" id="user-display-name" value="<?php echo htmlspecialchars($_SESSION['live_quiz_display_name']); ?>">
                
                <?php if (isset($activeSession['current_question'])): ?>
                    <input type="hidden" id="question-id" value="<?php echo $activeSession['current_question']['question_id']; ?>">
                    <input type="hidden" id="has-answered" value="<?php echo $activeSession['has_answered']; ?>">
                <?php endif; ?>
                
                <!-- Session status message -->
                <?php if ($activeSession['status'] === 'waiting'): ?>
                    <div class="mb-6 text-center">
                        <div class="animate-pulse inline-flex items-center justify-center h-24 w-24 rounded-full bg-indigo-100 text-indigo-500 mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Waiting for Quiz to Start</h3>
                        <p class="text-gray-600">The host will start the quiz when everyone is ready.</p>
                        <p class="text-gray-500 text-sm mt-2">Playing as: <strong><?php echo htmlspecialchars($_SESSION['live_quiz_display_name']); ?></strong></p>
                    </div>
                <?php elseif ($activeSession['status'] === 'in_progress' && isset($activeSession['current_question'])): ?>
                    <!-- Active Question -->
                    <div id="question-container" class="mb-6">
                        <!-- Timer -->
                        <div class="mb-4 w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div id="timer-bar" class="h-full bg-indigo-600 transition-all duration-1000" data-time="<?php echo $activeSession['time_per_question']; ?>"></div>
                        </div>
                        
                        <div class="mb-6 text-center">
                            <h3 class="text-lg font-medium text-gray-900 mb-2"><?php echo htmlspecialchars($activeSession['current_question']['question_text']); ?></h3>
                            
                            <?php if (!empty($activeSession['current_question']['image_path'])): ?>
                                <div class="mb-4 flex justify-center">
                                    <img src="uploads/questions/<?php echo htmlspecialchars($activeSession['current_question']['image_path']); ?>" 
                                         alt="Question image" class="max-h-60 rounded">
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($activeSession['current_question']['user_answered']): ?>
                            <div class="flex items-center justify-center">
                                <div class="bg-blue-100 text-blue-700 px-4 py-3 rounded-md">
                                    <p class="font-medium text-center">Your answer has been submitted.</p>
                                    <p class="text-sm text-center">Waiting for next question...</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php if ($activeSession['current_question']['question_type'] === 'multiple_choice'): ?>
                                <!-- Multiple choice answers - Kahoot-style colored buttons -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
                                    <?php
                                    $colors = [
                                        ['bg-red-500 hover:bg-red-600', 'border-red-600'],
                                        ['bg-blue-500 hover:bg-blue-600', 'border-blue-600'],
                                        ['bg-yellow-500 hover:bg-yellow-600', 'border-yellow-600'],
                                        ['bg-green-500 hover:bg-green-600', 'border-green-600']
                                    ];
                                    
                                    foreach ($activeSession['current_question']['answers'] as $index => $answer):
                                        $colorIndex = $index % count($colors);
                                        $colorClasses = $colors[$colorIndex];
                                    ?>
                                        <button type="button" 
                                                class="answer-button p-4 rounded-md text-white text-center font-bold shadow-md border-b-4 transition transform hover:scale-105 <?php echo $colorClasses[0] . ' ' . $colorClasses[1]; ?>"
                                                data-answer-id="<?php echo $answer['id']; ?>">
                                            <?php echo htmlspecialchars($answer['answer_text']); ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <!-- Written response input -->
                                <form id="written-answer-form" class="mt-6">
                                    <div class="mb-4">
                                        <label for="written-answer" class="block text-sm font-medium text-gray-700 mb-2">Your Answer:</label>
                                        <div class="relative">
                                            <input type="text" id="written-answer" name="written_answer" required 
                                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 pl-3 pr-10 py-3"
                                                   placeholder="Type your answer here (1-3 words)">
                                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                <span id="word-counter" class="text-sm text-gray-500">0/3</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded transition-colors">
                                        Submit Answer
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php elseif ($activeSession['status'] === 'completed'): ?>
                    <!-- Quiz completed message -->
                    <div id="quiz-completed" class="mb-6 text-center">
                        <div class="inline-flex items-center justify-center h-24 w-24 rounded-full bg-green-100 text-green-500 mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Quiz Completed!</h3>
                        <p class="text-gray-600 mb-4">Thank you for participating in this live quiz.</p>
                        
                        <div class="inline-block bg-indigo-100 text-indigo-700 px-4 py-2 rounded-md">
                            <p class="font-medium">Your final score: <span class="font-bold text-xl"><?php echo intval($activeSession['user_score']); ?></span></p>
                            <p class="text-sm">Accuracy: <?php echo $activeSession['user_accuracy']; ?>%</p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Leaderboard -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-gray-800 mb-4">Leaderboard</h3>
                    
                    <div id="leaderboard" class="bg-indigo-50 rounded-lg p-4">
                        <?php if (!empty($activeSession['leaderboard'])): ?>
                            <div class="space-y-2">
                                <?php foreach ($activeSession['leaderboard'] as $index => $player): ?>
                                    <div class="bg-white rounded-md p-3 flex items-center justify-between
                                                <?php echo $player['display_name'] === $_SESSION['live_quiz_display_name'] ? 'ring-2 ring-indigo-500' : ''; ?>">
                                        <div class="flex items-center">
                                            <div class="mr-3 w-8 h-8 flex items-center justify-center rounded-full
                                                        <?php
                                                        if ($index === 0) echo 'bg-yellow-100 text-yellow-800';
                                                        elseif ($index === 1) echo 'bg-gray-100 text-gray-800';
                                                        elseif ($index === 2) echo 'bg-yellow-50 text-yellow-700';
                                                        else echo 'bg-blue-50 text-blue-700';
                                                        ?>">
                                                <?php echo $index + 1; ?>
                                            </div>
                                            <span class="font-medium"><?php echo htmlspecialchars($player['display_name']); ?></span>
                                            
                                            <?php if ($player['display_name'] === $_SESSION['live_quiz_display_name']): ?>
                                                <span class="ml-2 text-xs bg-indigo-100 text-indigo-800 px-2 py-0.5 rounded-full">You</span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="font-bold"><?php echo intval($player['score']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-gray-500">No scores yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Leave session button -->
                <div class="text-center">
                    <a href="?action=leave" class="inline-block bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition-colors">
                        Leave Quiz
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Join Session Form -->
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4">
                <h2 class="text-white text-xl font-bold">Join a Live Quiz</h2>
            </div>
            
            <div class="p-6">
                <form method="post" action="">
                    <div class="mb-6">
                        <label for="session_code" class="block text-sm font-medium text-gray-700 mb-1">Session Code</label>
                        <input type="text" id="session_code" name="session_code" required 
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 uppercase"
                               placeholder="Enter 6-digit code" maxlength="6">
                        <p class="mt-1 text-xs text-gray-500">Enter the 6-digit code provided by the quiz host</p>
                    </div>
                    
                    <div class="mb-6">
                        <label for="display_name" class="block text-sm font-medium text-gray-700 mb-1">Display Name</label>
                        <input type="text" id="display_name" name="display_name" required 
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                               value="<?php echo isset($_SESSION['first_name']) ? htmlspecialchars($_SESSION['first_name']) : ''; ?>"
                               placeholder="Enter your name">
                        <p class="mt-1 text-xs text-gray-500">This name will be visible to other participants</p>
                    </div>
                    
                    <div class="mb-6 bg-indigo-50 p-4 rounded-md">
                        <h3 class="font-medium text-indigo-800 mb-2">How to Join</h3>
                        <ol class="list-decimal list-inside text-sm text-indigo-700 space-y-1">
                            <li>Enter the 6-digit code from your quiz host</li>
                            <li>Choose a display name (or use your first name)</li>
                            <li>Click "Join Quiz" and wait for the host to start</li>
                            <li>Answer questions quickly for more points!</li>
                        </ol>
                    </div>
                    
                    <div>
                        <button type="submit" name="join_session" value="1" 
                                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded transition-colors">
                            Join Quiz
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>