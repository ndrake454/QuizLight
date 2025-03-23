<?php
/**
 * Live Quiz Host Page
 * 
 * This page allows teachers/admins to:
 * - Create a new live quiz session
 * - Select questions and settings
 * - Start the session and get a join code
 * - Control the flow of questions
 * - View real-time results
 */

require_once 'config.php';
requireLogin(); // Ensure the user is logged in

$pageTitle = 'Host Live Quiz';
$extraScripts = ['/js/js-functions.js']; // Custom JS for live quiz functionality

// Check if user is allowed to host (admins or with hosting permission)
$canHost = isAdmin(); // For now, only admins can host

// Process form submission to create a new session
$message = '';
$messageType = '';
$sessionCode = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_session'])) {
    $quizName = trim($_POST['quiz_name'] ?? '');
    $categoryIds = $_POST['categories'] ?? [];
    $questionCount = (int)($_POST['question_count'] ?? 10);
    $timePerQuestion = (int)($_POST['time_per_question'] ?? 20);
    
    if (empty($quizName)) {
        $message = "Please enter a quiz name.";
        $messageType = "error";
    } elseif (empty($categoryIds)) {
        $message = "Please select at least one category.";
        $messageType = "error";
    } elseif ($questionCount < 1 || $questionCount > 50) {
        $message = "Number of questions must be between 1 and 50.";
        $messageType = "error";
    } elseif ($timePerQuestion < 5 || $timePerQuestion > 120) {
        $message = "Time per question must be between 5 and 120 seconds.";
        $messageType = "error";
    } else {
        try {
            // Generate a unique 6-character session code
            $sessionCode = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
            
            // Create the session in the database
            $stmt = $pdo->prepare("INSERT INTO live_quiz_sessions (
                                        host_id, session_code, quiz_name, time_per_question, 
                                        status, created_at
                                   ) VALUES (?, ?, ?, ?, 'waiting', NOW())");
            $stmt->execute([$_SESSION['user_id'], $sessionCode, $quizName, $timePerQuestion]);
            
            $sessionId = $pdo->lastInsertId();
            
            // Store the selected categories
            foreach ($categoryIds as $categoryId) {
                $stmt = $pdo->prepare("INSERT INTO live_quiz_session_categories (
                                            session_id, category_id
                                       ) VALUES (?, ?)");
                $stmt->execute([$sessionId, $categoryId]);
            }
            
            // Load questions for the session
            $categoriesStr = implode(',', array_map('intval', $categoryIds));
            
            $stmt = $pdo->prepare("SELECT id FROM questions 
                                  WHERE category_id IN ($categoriesStr) 
                                  ORDER BY RAND() 
                                  LIMIT ?");
            $stmt->execute([$questionCount]);
            $questionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Store questions for the session
            foreach ($questionIds as $index => $questionId) {
                $stmt = $pdo->prepare("INSERT INTO live_quiz_session_questions (
                                            session_id, question_id, question_order
                                       ) VALUES (?, ?, ?)");
                $stmt->execute([$sessionId, $questionId, $index + 1]);
            }
            
            // Store session ID in the user's session
            $_SESSION['live_quiz_host_session_id'] = $sessionId;
            
            $message = "Live quiz session created successfully! Share the code: <strong>$sessionCode</strong>";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Error creating quiz session: " . $e->getMessage();
            $messageType = "error";
            error_log("Live quiz create error: " . $e->getMessage());
        }
    }
}

// Get categories from the database for the form
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = "Error loading categories: " . $e->getMessage();
    $messageType = "error";
    $categories = [];
    error_log("Category loading error: " . $e->getMessage());
}

// Check if user already has an active session
$activeSession = null;
if (isset($_SESSION['live_quiz_host_session_id'])) {
    $sessionId = $_SESSION['live_quiz_host_session_id'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM live_quiz_sessions WHERE id = ? AND host_id = ?");
        $stmt->execute([$sessionId, $_SESSION['user_id']]);
        $activeSession = $stmt->fetch();
        
        if ($activeSession) {
            // Get the number of participants
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM live_quiz_participants WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $activeSession['participant_count'] = $stmt->fetchColumn();
            
            // Get the questions for this session
            $stmt = $pdo->prepare("
                SELECT lqsq.*, q.question_text, q.question_type, q.image_path, q.difficulty_value
                FROM live_quiz_session_questions lqsq
                JOIN questions q ON lqsq.question_id = q.id
                WHERE lqsq.session_id = ?
                ORDER BY lqsq.question_order ASC
            ");
            $stmt->execute([$sessionId]);
            $activeSession['questions'] = $stmt->fetchAll();
            
            // Get current question index
            $activeSession['current_question'] = 0;
            foreach ($activeSession['questions'] as $index => $question) {
                if ($question['status'] === 'active') {
                    $activeSession['current_question'] = $index;
                    break;
                }
            }
            
            // If no active question, find the next one to start
            if ($activeSession['current_question'] === 0 && $activeSession['status'] !== 'waiting') {
                for ($i = 0; $i < count($activeSession['questions']); $i++) {
                    if ($activeSession['questions'][$i]['status'] === 'pending') {
                        $activeSession['current_question'] = $i;
                        break;
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $message = "Error loading active session: " . $e->getMessage();
        $messageType = "error";
        error_log("Active session load error: " . $e->getMessage());
    }
}

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-8 flex justify-between items-center">
        <h1 class="text-3xl font-bold text-indigo-800">Host a Live Quiz</h1>
        
        <a href="/" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 transition duration-150 flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
            </svg>
            Back to Home
        </a>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700 border-l-4 border-green-500' : 'bg-red-100 text-red-700 border-l-4 border-red-500'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!$canHost): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
            <p class="font-bold">Permission Required</p>
            <p>You need admin privileges to host a live quiz. Please contact an administrator for assistance.</p>
        </div>
    <?php else: ?>
        <?php if ($activeSession): ?>
            <!-- Active Session Dashboard -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4">
                    <h2 class="text-white text-xl font-bold flex items-center">
                        Active Quiz Session: <?php echo htmlspecialchars($activeSession['quiz_name']); ?>
                        <span class="ml-3 px-3 py-1 bg-white text-indigo-700 rounded-full text-sm">
                            Code: <?php echo htmlspecialchars($activeSession['session_code']); ?>
                        </span>
                    </h2>
                </div>
                
                <div class="p-6">
                    <!-- Hidden session ID for JS -->
                    <input type="hidden" id="session-id" value="<?php echo $activeSession['id']; ?>">
                    <input type="hidden" id="session-status" value="<?php echo $activeSession['status']; ?>">
                    
                    <!-- Session Stats -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="bg-indigo-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-indigo-700">
                                <?php echo htmlspecialchars($activeSession['participant_count']); ?>
                            </div>
                            <div class="text-sm text-indigo-500">Participants</div>
                        </div>
                        
                        <div class="bg-indigo-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-indigo-700">
                                <?php echo count($activeSession['questions']); ?>
                            </div>
                            <div class="text-sm text-indigo-500">Total Questions</div>
                        </div>
                        
                        <div class="bg-indigo-50 rounded-lg p-4 text-center">
                            <div class="text-2xl font-bold text-indigo-700">
                                <?php echo htmlspecialchars($activeSession['time_per_question']); ?>s
                            </div>
                            <div class="text-sm text-indigo-500">Time per Question</div>
                        </div>
                    </div>
                    
                    <!-- Current Session Status Banner -->
                    <div class="mb-6 p-4 rounded-md
                        <?php
                        if ($activeSession['status'] === 'waiting') echo 'bg-blue-100 text-blue-700 border-l-4 border-blue-500';
                        elseif ($activeSession['status'] === 'in_progress') echo 'bg-green-100 text-green-700 border-l-4 border-green-500';
                        else echo 'bg-yellow-100 text-yellow-700 border-l-4 border-yellow-500';
                        ?>">
                        <p class="font-bold">
                            <?php
                            if ($activeSession['status'] === 'waiting') echo 'Waiting for Participants';
                            elseif ($activeSession['status'] === 'in_progress') echo 'Quiz in Progress';
                            else echo 'Quiz Completed';
                            ?>
                        </p>
                        <p>
                            <?php
                            if ($activeSession['status'] === 'waiting') {
                                echo "Share the code <strong>{$activeSession['session_code']}</strong> with participants to join.";
                            } elseif ($activeSession['status'] === 'in_progress') {
                                echo "Question " . ($activeSession['current_question'] + 1) . " of " . count($activeSession['questions']) . " is active.";
                            } else {
                                echo "The quiz has ended. You can view the final results or close the session.";
                            }
                            ?>
                        </p>
                    </div>
                    
                    <!-- Session Controls -->
                    <div class="bg-gray-50 p-4 rounded-lg mb-6">
                        <h3 class="text-lg font-medium text-gray-800 mb-4">Session Controls</h3>
                        
                        <div class="flex flex-wrap gap-3">
                            <?php if ($activeSession['status'] === 'waiting'): ?>
                                <form method="post" action="live_quiz_control.php">
                                    <input type="hidden" name="action" value="start_session">
                                    <input type="hidden" name="session_id" value="<?php echo $activeSession['id']; ?>">
                                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition">
                                        Start Quiz
                                    </button>
                                </form>
                            <?php elseif ($activeSession['status'] === 'in_progress'): ?>
                                <form method="post" action="live_quiz_control.php">
                                    <input type="hidden" name="action" value="next_question">
                                    <input type="hidden" name="session_id" value="<?php echo $activeSession['id']; ?>">
                                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition">
                                        Next Question
                                    </button>
                                </form>
                                
                                <form method="post" action="live_quiz_control.php">
                                    <input type="hidden" name="action" value="end_session">
                                    <input type="hidden" name="session_id" value="<?php echo $activeSession['id']; ?>">
                                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded transition" 
                                           onclick="return confirm('Are you sure you want to end this session?')">
                                        End Quiz
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="p-4 bg-gray-100 text-gray-700 rounded-md">
                                    This quiz session has ended.
                                </div>
                                
                                <form method="post" action="live_quiz_control.php">
                                    <input type="hidden" name="action" value="close_session">
                                    <input type="hidden" name="session_id" value="<?php echo $activeSession['id']; ?>">
                                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded transition">
                                        Close Session & Create New Quiz
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Participant List -->
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-800 mb-4">Participants</h3>
                        
                        <div id="participant-list" class="bg-white border border-gray-200 rounded-lg p-4 min-h-[100px]">
                            <!-- Participants will be loaded here via AJAX -->
                            <p class="text-gray-500 text-center">Loading participants...</p>
                        </div>
                    </div>
                    
                    <!-- Active Question Preview -->
                    <?php if ($activeSession['status'] === 'in_progress' && isset($activeSession['questions'][$activeSession['current_question']])): ?>
                        <?php $currentQuestion = $activeSession['questions'][$activeSession['current_question']]; ?>
                        <div class="mb-6 bg-indigo-50 p-4 rounded-lg border border-indigo-100">
                            <h3 class="text-lg font-medium text-indigo-800 mb-2">Current Question</h3>
                            
                            <div class="bg-white p-4 rounded-md mb-3">
                                <p class="font-medium"><?php echo htmlspecialchars($currentQuestion['question_text']); ?></p>
                                
                                <?php if (!empty($currentQuestion['image_path'])): ?>
                                    <div class="mt-2 text-center">
                                        <img src="uploads/questions/<?php echo htmlspecialchars($currentQuestion['image_path']); ?>" 
                                             alt="Question image" class="max-h-40 inline-block rounded">
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="text-sm text-indigo-700">
                                <p>Type: <?php echo $currentQuestion['question_type'] === 'multiple_choice' ? 'Multiple Choice' : 'Written Response'; ?></p>
                                <p>Difficulty: Level <?php echo intval($currentQuestion['difficulty_value']); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Question List -->
                    <div>
                        <h3 class="text-lg font-medium text-gray-800 mb-4">Questions</h3>
                        
                        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                #
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Question
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Type
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($activeSession['questions'] as $index => $question): ?>
                                            <tr class="<?php echo $index === $activeSession['current_question'] ? 'bg-indigo-50' : ($index % 2 === 0 ? 'bg-white' : 'bg-gray-50'); ?>">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php echo $question['question_order']; ?>
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-900">
                                                    <?php 
                                                    $previewText = htmlspecialchars(substr($question['question_text'], 0, 50));
                                                    echo strlen($question['question_text']) > 50 ? $previewText . '...' : $previewText;
                                                    ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <span class="px-2 py-1 rounded-full text-xs font-medium
                                                        <?php echo $question['question_type'] === 'multiple_choice' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'; ?>">
                                                        <?php echo $question['question_type'] === 'multiple_choice' ? 'Multiple Choice' : 'Written Response'; ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?php
                                                    $statusClass = '';
                                                    switch ($question['status']) {
                                                        case 'active':
                                                            $statusClass = 'bg-green-100 text-green-800';
                                                            $statusText = 'Active';
                                                            break;
                                                        case 'completed':
                                                            $statusClass = 'bg-blue-100 text-blue-800';
                                                            $statusText = 'Completed';
                                                            break;
                                                        case 'skipped':
                                                            $statusClass = 'bg-yellow-100 text-yellow-800';
                                                            $statusText = 'Skipped';
                                                            break;
                                                        default:
                                                            $statusClass = 'bg-gray-100 text-gray-800';
                                                            $statusText = 'Pending';
                                                    }
                                                    ?>
                                                    <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $statusClass; ?>">
                                                        <?php echo $statusText; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Create New Quiz Session Form -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4">
                    <h2 class="text-white text-xl font-bold">Create New Live Quiz</h2>
                </div>
                
                <div class="p-6">
                    <form method="post" action="">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="quiz_name" class="block text-sm font-medium text-gray-700 mb-1">Quiz Name</label>
                                <input type="text" id="quiz_name" name="quiz_name" required 
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                       placeholder="Enter a name for your quiz">
                            </div>
                            
                            <div>
                                <label for="time_per_question" class="block text-sm font-medium text-gray-700 mb-1">Time per Question (seconds)</label>
                                <input type="number" id="time_per_question" name="time_per_question" min="5" max="120" value="20" required 
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <p class="mt-1 text-xs text-gray-500">Recommended: 20 seconds per question</p>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label for="question_count" class="block text-sm font-medium text-gray-700 mb-1">Number of Questions</label>
                            <input type="number" id="question_count" name="question_count" min="1" max="50" value="10" required 
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                            <p class="mt-1 text-xs text-gray-500">Maximum: 50 questions</p>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Select Categories</label>
                            
                            <?php if (!empty($categories)): ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-64 overflow-y-auto p-2 category-list">
                                    <?php foreach ($categories as $category): ?>
                                    <label class="flex items-center p-3 bg-white rounded-md hover:bg-blue-50 transition-colors border border-blue-100">
                                        <input type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>" 
                                               class="h-5 w-5 text-blue-600 focus:ring-blue-500 rounded">
                                        <span class="ml-2 text-gray-700"><?php echo htmlspecialchars($category['name']); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-red-500">No categories available. Please create categories first.</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-6 bg-yellow-50 p-4 rounded-md border-l-4 border-yellow-400">
                            <h3 class="font-medium text-yellow-800 mb-2">How the Live Quiz Works</h3>
                            <ul class="list-disc list-inside text-sm text-yellow-700 space-y-1">
                                <li>Create your quiz and share the join code with participants</li>
                                <li>Participants join using the code on the "Join Live Quiz" page</li>
                                <li>Once everyone has joined, click "Start Quiz" to begin</li>
                                <li>Questions appear to all participants simultaneously with a timer</li>
                                <li>Participants earn points based on correct answers and speed</li>
                                <li>Results and leaderboard are shown after each question</li>
                            </ul>
                        </div>
                        
                        <div>
                            <button type="submit" name="create_session" value="1" 
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded w-full transition">
                                Create Live Quiz
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>