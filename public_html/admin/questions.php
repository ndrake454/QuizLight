<?php
/**
 * Admin Question Management Page
 * 
 * This page allows administrators to:
 * - View all questions in the system
 * - Filter questions by category
 * - Add new questions with multiple answer options
 * - Edit existing questions
 * - Delete questions
 * 
 * Questions can include text, images, and explanations with varying difficulty levels.
 */

require_once '../config.php';
$pageTitle = 'Manage Questions';

// Ensure user is logged in and is an admin
requireAdmin();

// Initialize variables
$message = '';
$messageType = '';

// Create uploads directory if it doesn't exist
$uploadsDir = '../uploads/questions';
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

/**
 * Handle question actions (add, delete)
 * Edit action is handled by a separate page
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ADD QUESTION
    if ($action === 'add_question') {
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $questionText = trim($_POST['question_text'] ?? '');
        $explanation = trim($_POST['explanation'] ?? '');
        $questionType = $_POST['question_type'] ?? 'multiple_choice';
        
        // Get difficulty value directly from slider (Level 1-5)
        $difficultyValue = isset($_POST['difficulty_value']) ? intval($_POST['difficulty_value']) : 3;
        
        // Set intended_difficulty based on level
        $difficulty = "level_" . $difficultyValue;
        
        // Validate input
        if ($categoryId <= 0 || empty($questionText)) {
            $message = "Category and question text are required.";
            $messageType = "error";
        } else {
            try {
                // Start transaction for atomicity
                $pdo->beginTransaction();
                
                // Process image if uploaded
                $imagePath = null;
                if (isset($_FILES['question_image']) && $_FILES['question_image']['error'] == 0) {
                    // Define upload directory
                    $uploadDir = '../uploads/questions/';
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    // Get file info
                    $fileName = basename($_FILES['question_image']['name']);
                    $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    
                    // Generate unique filename to prevent collisions
                    $uniqueName = uniqid() . '.' . $fileType;
                    $targetFilePath = $uploadDir . $uniqueName;
                    
                    // Check if image file is a valid upload
                    $validExtensions = array('jpg', 'jpeg', 'png', 'gif');
                    if (in_array($fileType, $validExtensions)) {
                        // Upload file
                        if (move_uploaded_file($_FILES['question_image']['tmp_name'], $targetFilePath)) {
                            $imagePath = $uniqueName;
                        } else {
                            throw new Exception("Failed to upload image. Please check file permissions.");
                        }
                    } else {
                        throw new Exception("Invalid file format. Please upload a JPG, JPEG, PNG, or GIF image.");
                    }
                }
                
                // Insert question with the level-based difficulty
                $stmt = $pdo->prepare("INSERT INTO questions (category_id, question_text, explanation, intended_difficulty, difficulty_value, image_path, question_type) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$categoryId, $questionText, $explanation, $difficulty, $difficultyValue, $imagePath, $questionType]);
                
                $questionId = $pdo->lastInsertId();
                
                // Process answers based on question type
                if ($questionType === 'multiple_choice') {
                    // Process multiple choice answers
                    $answers = $_POST['answers'] ?? [];
                    $correctAnswer = isset($_POST['correct_answer']) ? (int)$_POST['correct_answer'] : -1;
                    
                    if (count($answers) < 2) {
                        throw new Exception("At least two answers are required for multiple choice questions.");
                    }
                    
                    if ($correctAnswer < 0 || $correctAnswer >= count($answers)) {
                        throw new Exception("Please select a correct answer.");
                    }
                    
                    foreach ($answers as $index => $answerText) {
                        $answerText = trim($answerText);
                        if (empty($answerText)) continue;
                        
                        $isCorrect = ($index === $correctAnswer) ? 1 : 0;
                        
                        $stmt = $pdo->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                        $stmt->execute([$questionId, $answerText, $isCorrect]);
                    }
                } else {
                    // Process written response answers
                    $writtenAnswers = $_POST['written_answers'] ?? [];
                    $primaryAnswer = isset($_POST['primary_answer']) ? (int)$_POST['primary_answer'] : -1;
                    
                    if (count($writtenAnswers) < 1) {
                        throw new Exception("At least one acceptable answer is required for written response questions.");
                    }
                    
                    if ($primaryAnswer < 0 || $primaryAnswer >= count($writtenAnswers)) {
                        throw new Exception("Please select a primary answer.");
                    }
                    
                    foreach ($writtenAnswers as $index => $answerText) {
                        $answerText = trim($answerText);
                        if (empty($answerText)) continue;
                        
                        // Limit answer length for written responses
                        if (strlen($answerText) > 50) {
                            $answerText = substr($answerText, 0, 50);
                        }
                        
                        $isPrimary = ($index === $primaryAnswer) ? 1 : 0;
                        
                        $stmt = $pdo->prepare("INSERT INTO written_response_answers (question_id, answer_text, is_primary) VALUES (?, ?, ?)");
                        $stmt->execute([$questionId, $answerText, $isPrimary]);
                    }
                }
                
                // Commit transaction
                $pdo->commit();
                
                $message = "Question added successfully.";
                $messageType = "success";
            } catch (Exception $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                $message = "Error: " . $e->getMessage();
                $messageType = "error";
                // Log the error for debugging
                error_log("Admin question add error: " . $e->getMessage());
            }
        }
    } 
    // DELETE QUESTION
    elseif ($action === 'delete_question') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($id <= 0) {
            $message = "Invalid question ID.";
            $messageType = "error";
        } else {
            try {
                // Get image path before deleting
                $stmt = $pdo->prepare("SELECT image_path FROM questions WHERE id = ?");
                $stmt->execute([$id]);
                $question = $stmt->fetch();
                
                // Delete question (answers will be deleted by ON DELETE CASCADE in database)
                $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
                $stmt->execute([$id]);
                
                // Delete image file if it exists
                if (!empty($question['image_path'])) {
                    $imagePath = '../uploads/questions/' . $question['image_path'];
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }
                
                $message = "Question deleted successfully.";
                $messageType = "success";
            } catch (PDOException $e) {
                $message = "Database error: " . $e->getMessage();
                $messageType = "error";
                // Log the error for debugging
                error_log("Admin question delete error: " . $e->getMessage());
            }
        }
    }
}

/**
 * Fetch categories for the dropdown
 */
try {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
    $messageType = "error";
    $categories = [];
    // Log the error for debugging
    error_log("Admin category list error: " . $e->getMessage());
}

/**
 * Fetch questions with pagination and category filter
 */
try {
    // Set up pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 10;
    $offset = ($page - 1) * $perPage;
    
    // Filter by category if provided
    $filterCategory = isset($_GET['category']) ? (int)$_GET['category'] : 0;
    $whereClause = $filterCategory > 0 ? "WHERE q.category_id = :category" : "";
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) FROM questions q $whereClause";
    if ($filterCategory > 0) {
        $stmt = $pdo->prepare($countSql);
        $stmt->bindParam(':category', $filterCategory);
        $stmt->execute();
    } else {
        $stmt = $pdo->query($countSql);
    }
    $totalQuestions = $stmt->fetchColumn();
    $totalPages = ceil($totalQuestions / $perPage);
    
    // Get questions for current page
    $sql = "SELECT q.*, c.name as category_name 
            FROM questions q 
            JOIN categories c ON q.category_id = c.id 
            $whereClause 
            ORDER BY q.created_at DESC 
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    if ($filterCategory > 0) {
        $stmt->bindParam(':category', $filterCategory);
    }
    $stmt->execute();
    $questions = $stmt->fetchAll();
    
    // Get answers for each question based on question type
    foreach ($questions as &$question) {
        $questionType = $question['question_type'] ?? 'multiple_choice';
        
        if ($questionType === 'multiple_choice') {
            // Get multiple choice answers
            $stmt = $pdo->prepare("SELECT id, answer_text, is_correct FROM answers WHERE question_id = ? ORDER BY id ASC");
            $stmt->execute([$question['id']]);
            $question['answers'] = $stmt->fetchAll();
        } else {
            // Get written response answers
            $stmt = $pdo->prepare("SELECT id, answer_text, is_primary FROM written_response_answers WHERE question_id = ? ORDER BY id ASC");
            $stmt->execute([$question['id']]);
            $question['written_answers'] = $stmt->fetchAll();
        }
    }
    
} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
    $messageType = "error";
    $questions = [];
    $totalPages = 0;
    // Log the error for debugging
    error_log("Admin question list error: " . $e->getMessage());
}

include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Page header with title and back button -->
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Manage Questions</h1>
        <a href="/admin/" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition duration-150">
            Back to Dashboard
        </a>
    </div>
    
    <!-- Display success/error messages -->
    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Add New Question Form -->
<div class="bg-white rounded-lg shadow-md p-6 mb-8" x-data="{ isOpen: false, answerCount: 2, questionType: 'multiple_choice', difficultyValue: 3 }">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-semibold">Add New Question</h2>
        
        <!-- Improved button styling -->
        <button @click="isOpen = !isOpen" 
                class="px-4 py-2 rounded-md flex items-center transition-colors duration-200"
                :class="isOpen ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-indigo-100 text-indigo-700 hover:bg-indigo-200'">
            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      :d="isOpen ? 'M6 18L18 6M6 6l12 12' : 'M12 6v6m0 0v6m0-6h6m-6 0H6'"></path>
            </svg>
            <span x-show="!isOpen">Show Form</span>
            <span x-show="isOpen">Hide Form</span>
        </button>
    </div>
    
    <!-- Improved form with transitions -->
    <div x-show="isOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 transform scale-98"
         x-transition:enter-end="opacity-100 transform scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 transform scale-100"
         x-transition:leave-end="opacity-0 transform scale-98">
        
        <form action="" method="POST" class="space-y-5 bg-gray-50 p-5 rounded-lg border border-gray-200" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_question">
            
            <!-- Category dropdown with improved styling -->
            <div>
                <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select name="category_id" id="category_id" required 
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-base py-3 px-4">
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-1 text-xs text-gray-500">If the category you need doesn't exist, add it in the Categories section first.</p>
            </div>
            
            <!-- Question text with improved styling -->
            <div>
                <label for="question_text" class="block text-sm font-medium text-gray-700 mb-1">Question Text</label>
                <textarea name="question_text" id="question_text" rows="3" required 
                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-base py-3 px-4"
                          placeholder="Enter the question text"></textarea>
            </div>
            
            <!-- Question image with improved styling -->
            <div>
                <label for="question_image" class="block text-sm font-medium text-gray-700 mb-1">Question Image (Optional)</label>
                <div class="flex items-center justify-center w-full">
                    <label for="question_image" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors">
                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                            <svg class="w-8 h-8 mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            <p class="mb-1 text-sm text-gray-500 text-center"><span class="font-semibold">Click to upload</span> or drag and drop</p>
                            <p class="text-xs text-gray-500 text-center">SVG, PNG, JPG or GIF (MAX. 2MB)</p>
                        </div>
                        <input type="file" id="question_image" name="question_image" accept="image/*" class="hidden" />
                    </label>
                </div>
            </div>
            
            <!-- Explanation with improved styling -->
            <div>
                <label for="explanation" class="block text-sm font-medium text-gray-700 mb-1">Explanation (Optional)</label>
                <textarea name="explanation" id="explanation" rows="3" 
                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-base py-3 px-4"
                          placeholder="Provide an explanation that will be shown after answering"></textarea>
                <p class="mt-1 text-xs text-gray-500">Explanation is shown to users after they answer the question.</p>
            </div>
            
            <!-- Difficulty Slider (Levels 1-5) -->
            <div>
                <label for="difficulty_value" class="block text-sm font-medium text-gray-700 mb-1">Difficulty Level</label>
                <div class="grid grid-cols-1 gap-4">
                    <!-- Slider input (simplified to 1-5 scale) -->
                    <div class="mt-2 relative">
                        <input type="range" id="difficulty_value" name="difficulty_value" min="1" max="5" step="1" 
                               x-model="difficultyValue" class="w-full h-2 rounded-lg appearance-none cursor-pointer bg-gray-200" />
                        
                        <!-- Difficulty label -->
                        <div class="mt-3 flex justify-between">
                            <div>
                                <span class="font-medium text-green-600">Level 1</span>
                            </div>
                            <div>
                                <span class="font-medium text-green-600">Level 2</span>
                            </div>
                            <div>
                                <span class="font-medium text-yellow-600">Level 3</span>
                            </div>
                            <div>
                                <span class="font-medium text-yellow-600">Level 4</span>
                            </div>
                            <div>
                                <span class="font-medium text-red-600">Level 5</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Current value display -->
                    <div class="flex justify-center">
                        <div class="px-4 py-2 rounded-full text-sm font-bold border" 
                             :class="{
                                'bg-green-100 text-green-700 border-green-200': difficultyValue <= 2,
                                'bg-yellow-100 text-yellow-700 border-yellow-200': difficultyValue >= 3 && difficultyValue <= 4,
                                'bg-red-100 text-red-700 border-red-200': difficultyValue == 5
                             }">
                            <span x-text="difficultyValue"></span> 
                            - 
                            <span x-text="'Level ' + difficultyValue"></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Question Type Selection -->
            <div>
                <label for="question_type" class="block text-sm font-medium text-gray-700 mb-1">Question Type</label>
                <div class="mt-2 flex space-x-4">
                    <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus:outline-none">
                        <input type="radio" name="question_type" value="multiple_choice" class="sr-only" 
                               x-model="questionType" checked>
                        <span class="flex flex-1">
                            <span class="flex flex-col">
                                <span class="block text-sm font-medium text-gray-900">Multiple Choice</span>
                                <span class="mt-1 flex items-center text-sm text-gray-500">
                                    Standard question with multiple options
                                </span>
                            </span>
                        </span>
                        <svg class="h-5 w-5 text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                             x-show="questionType === 'multiple_choice'">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </label>
                    
                    <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm focus:outline-none">
                        <input type="radio" name="question_type" value="written_response" class="sr-only" 
                               x-model="questionType" @change="answerCount = Math.max(1, answerCount)">
                        <span class="flex flex-1">
                            <span class="flex flex-col">
                                <span class="block text-sm font-medium text-gray-900">Written Response</span>
                                <span class="mt-1 flex items-center text-sm text-gray-500">
                                    Short answer text response
                                </span>
                            </span>
                        </span>
                        <svg class="h-5 w-5 text-indigo-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"
                             x-show="questionType === 'written_response'">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    </label>
                </div>
            </div>
            
            <!-- Multiple Choice Answer Options -->
<div x-show="questionType === 'multiple_choice'">
    <div class="mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-3">Answers</label>
        <div class="space-y-3 bg-white p-4 rounded-md border border-gray-200">
            <template x-for="i in answerCount" :key="i">
                <div class="flex items-center">
                    <div class="mr-3">
                        <input type="radio" name="correct_answer" :value="i-1" :id="'correct_' + (i-1)" 
                               :required="questionType === 'multiple_choice'"
                               class="h-5 w-5 text-indigo-600 focus:ring-indigo-500">
                    </div>
                    <div class="flex-grow">
                        <input type="text" :name="'answers[' + (i-1) + ']'" 
                               :required="questionType === 'multiple_choice'"
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 py-2 px-3"
                               placeholder="Enter answer option">
                    </div>
                    <button type="button" @click="if(answerCount > 2) answerCount--" 
                            x-show="i > 2" class="ml-2 p-2 rounded-full text-red-600 hover:bg-red-100 transition-colors">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </div>
            </template>
            
            <button type="button" @click="answerCount++" 
                    class="mt-3 w-full flex justify-center items-center py-2 px-4 border border-indigo-300 shadow-sm text-sm font-medium rounded-md text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
                <svg class="h-5 w-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Add Answer Option
            </button>
        </div>
        <p class="mt-1 text-xs text-gray-500">Select the radio button next to the correct answer. At least two answers are required.</p>
    </div>
</div>

<!-- Written Response Answer Options -->
<div x-show="questionType === 'written_response'" class="mb-6">
    <label class="block text-sm font-medium text-gray-700 mb-3">Acceptable Answers</label>
    <div class="space-y-3 bg-white p-4 rounded-md border border-gray-200">
        <div class="w-full mb-2 text-sm bg-blue-50 p-3 rounded border border-blue-200">
            <p class="font-medium text-blue-700">Written Response Guidelines:</p>
            <ul class="list-disc ml-5 text-blue-600 text-xs mt-1">
                <li>Enter all possible acceptable answers (max 50 characters each)</li>
                <li>System will use fuzzy matching for minor spelling variations</li>
                <li>Mark the primary/preferred answer (shown in explanations)</li>
                <li>Keep answers concise (1-3 words work best)</li>
            </ul>
        </div>
        
        <template x-for="i in answerCount" :key="i">
            <div class="flex items-center">
                <div class="mr-3">
                    <input type="radio" name="primary_answer" :value="i-1" :id="'primary_' + (i-1)" 
                           :required="questionType === 'written_response'"
                           class="h-5 w-5 text-indigo-600 focus:ring-indigo-500">
                </div>
                <div class="flex-grow">
                    <input type="text" :name="'written_answers[' + (i-1) + ']'" 
                           :required="questionType === 'written_response'"
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 py-2 px-3"
                           placeholder="Enter acceptable answer" maxlength="50">
                </div>
                <button type="button" @click="if(answerCount > 1) answerCount--" 
                        x-show="i > 1" class="ml-2 p-2 rounded-full text-red-600 hover:bg-red-100 transition-colors">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
            </div>
        </template>
        
        <button type="button" @click="answerCount++" 
                class="mt-3 w-full flex justify-center items-center py-2 px-4 border border-indigo-300 shadow-sm text-sm font-medium rounded-md text-indigo-700 bg-indigo-50 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors">
            <svg class="h-5 w-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Add Alternative Answer
        </button>
    </div>
    <p class="mt-1 text-xs text-gray-500">Select the radio button to mark the primary/preferred answer. At least one answer is required.</p>
</div>
            
            <!-- Submit button with improved styling -->
            <div class="pt-2">
                <button type="submit" class="w-full flex justify-center items-center py-3 px-4 border border-transparent shadow-md text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Add Question
                </button>
            </div>
        </form>
    </div>
</div>
    
    <!-- Filter & Search -->
    <div class="bg-white p-4 rounded-lg shadow-md mb-6">
        <form method="GET" action="" class="flex flex-wrap gap-4 items-end">
            <div class="w-full md:w-auto">
                <label for="category" class="block text-sm font-medium text-gray-700">Filter by Category</label>
                <select name="category" id="category" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo $filterCategory == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <button type="submit" class="py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150">
                    Apply Filter
                </button>
            </div>
        </form>
    </div>
    
    <!-- Questions List -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <!-- Display number of questions found -->
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">
                <?php if ($filterCategory > 0): ?>
                    <?php 
                    $categoryName = '';
                    foreach ($categories as $cat) {
                        if ($cat['id'] == $filterCategory) {
                            $categoryName = $cat['name'];
                            break;
                        }
                    }
                    ?>
                    Questions in category: <?php echo htmlspecialchars($categoryName); ?> (<?php echo $totalQuestions; ?>)
                <?php else: ?>
                    All Questions (<?php echo $totalQuestions; ?>)
                <?php endif; ?>
            </h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Question</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Difficulty</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($questions)): ?>
                        <?php foreach ($questions as $question): ?>
                            <tr x-data="{ isExpanded: false }">
                                <td class="px-6 py-4">
                                    <!-- Question preview (truncated) -->
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars(substr($question['question_text'], 0, 100) . (strlen($question['question_text']) > 100 ? '...' : '')); ?>
                                    </div>
                                    
                                    <!-- Expanded details for each question -->
                                    <div x-show="isExpanded" class="mt-4 bg-gray-50 p-4 rounded text-sm">
                                        <div class="mb-2">
                                            <strong>Full Question:</strong>
                                            <p><?php echo nl2br(htmlspecialchars($question['question_text'])); ?></p>
                                        </div>
                                        
                                        <?php if (!empty($question['image_path'])): ?>
                                            <div class="my-2">
                                                <strong>Question Image:</strong>
                                                <div class="mt-1">
                                                    <img src="../uploads/questions/<?php echo htmlspecialchars($question['image_path']); ?>" 
                                                         alt="Question image" class="max-h-48 rounded border border-gray-300">
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($question['explanation'])): ?>
                                            <div class="mb-2">
                                                <strong>Explanation:</strong>
                                                <p><?php echo nl2br(htmlspecialchars($question['explanation'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div>
                                            <strong>Difficulty:</strong>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php 
                                                if ($question['difficulty_value'] <= 2) {
                                                    echo 'bg-green-100 text-green-800';
                                                } elseif ($question['difficulty_value'] <= 4) {
                                                    echo 'bg-yellow-100 text-yellow-800';
                                                } else {
                                                    echo 'bg-red-100 text-red-800';
                                                }
                                                ?>">
                                                <?php echo 'Level ' . intval($question['difficulty_value']); ?>
                                            </span>
                                        </div>
                                        
                                        <!-- Display answer options based on question type -->
                                        <?php if (($question['question_type'] ?? 'multiple_choice') === 'multiple_choice'): ?>
                                            <!-- Display multiple choice answer options -->
                                            <div class="mt-2">
                                                <strong>Answers:</strong>
                                                <ul class="list-disc list-inside mt-1">
                                                    <?php foreach ($question['answers'] as $answer): ?>
                                                        <li class="<?php echo $answer['is_correct'] ? 'font-bold text-green-600' : ''; ?>">
                                                            <?php echo htmlspecialchars($answer['answer_text']); ?>
                                                            <?php if ($answer['is_correct']): ?> (Correct) <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php else: ?>
                                            <!-- Display written response answers -->
                                            <div class="mt-2">
                                                <strong>Acceptable Answers:</strong>
                                                <ul class="list-disc list-inside mt-1">
                                                    <?php foreach ($question['written_answers'] as $answer): ?>
                                                        <li class="<?php echo $answer['is_primary'] ? 'font-bold text-green-600' : ''; ?>">
                                                            <?php echo htmlspecialchars($answer['answer_text']); ?>
                                                            <?php if ($answer['is_primary']): ?> (Primary) <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($question['category_name']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo ($question['question_type'] ?? 'multiple_choice') === 'multiple_choice' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'; ?>">
                                        <?php echo ($question['question_type'] ?? 'multiple_choice') === 'multiple_choice' ? 'Multiple Choice' : 'Written Response'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php 
                                        if ($question['difficulty_value'] <= 2) {
                                            echo 'bg-green-100 text-green-800';
                                        } elseif ($question['difficulty_value'] <= 4) {
                                            echo 'bg-yellow-100 text-yellow-800';
                                        } else {
                                            echo 'bg-red-100 text-red-800';
                                        }
                                        ?>">
                                        <?php echo 'Level ' . intval($question['difficulty_value']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-3">
                                        <!-- Toggle to expand/collapse question details -->
                                        <button @click="isExpanded = !isExpanded" class="text-indigo-600 hover:text-indigo-900 transition duration-150">
                                            <span x-show="!isExpanded">View Details</span>
                                            <span x-show="isExpanded">Hide Details</span>
                                        </button>
                                        
                                        <!-- Edit question button (links to separate edit page) -->
                                        <a href="edit_question.php?id=<?php echo $question['id']; ?>" class="text-blue-600 hover:text-blue-900 transition duration-150">
                                            Edit
                                        </a>
                                        
                                        <!-- Delete question button -->
                                        <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this question? This action cannot be undone.');">
                                            <input type="hidden" name="action" value="delete_question">
                                            <input type="hidden" name="id" value="<?php echo $question['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900 transition duration-150">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                <?php if ($filterCategory > 0): ?>
                                    No questions found in this category. Try adding some using the form above.
                                <?php else: ?>
                                    No questions found. Try adding some using the form above.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing
                            <span class="font-medium"><?php echo $offset + 1; ?></span>
                            to
                            <span class="font-medium"><?php echo min($offset + $perPage, $totalQuestions); ?></span>
                            of
                            <span class="font-medium"><?php echo $totalQuestions; ?></span>
                            results
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php 
                            // Add category filter to pagination URLs if it exists
                            $categoryParam = $filterCategory > 0 ? "&category=$filterCategory" : "";
                            ?>
                            
                            <?php if ($page > 1): ?>
                                <!-- Previous page button -->
                                <a href="?page=<?php echo $page - 1 . $categoryParam; ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Previous</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php endif; ?>
                            
                            <?php 
                            // Show a reasonable range of page numbers
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            // Ensure at least 5 pages are shown if possible
                            if ($endPage - $startPage < 4 && $totalPages > 4) {
                                if ($startPage === 1) {
                                    $endPage = min($totalPages, 5);
                                } elseif ($endPage === $totalPages) {
                                    $startPage = max(1, $totalPages - 4);
                                }
                            }
                            
                            for ($i = $startPage; $i <= $endPage; $i++): 
                            ?>
                                <!-- Page number buttons -->
                                <a href="?page=<?php echo $i . $categoryParam; ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium 
                                          <?php echo $i === $page ? 'text-indigo-600 bg-indigo-50' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <!-- Next page button -->
                                <a href="?page=<?php echo $page + 1 . $categoryParam; ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Next</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Best Practices Tips -->
    <div class="mt-8 bg-blue-50 rounded-lg p-4 border border-blue-200">
        <h3 class="text-lg font-medium text-blue-800 mb-2">Tips for Question Management</h3>
        <ul class="list-disc list-inside text-blue-700 space-y-1">
            <li>Write clear, concise questions that test specific knowledge</li>
            <li>Include explanations to help users learn from their mistakes</li>
            <li>Use images where they add value to the question</li>
            <li>Make sure your incorrect answers are plausible but clearly wrong</li>
            <li>For written responses, include common variations and misspellings</li>
            <li>Questions are categorized on a difficulty scale from Level 1 to Level 5</li>
            <li>Levels 1-2 are color-coded green (easier questions)</li>
            <li>Levels 3-4 are color-coded yellow (moderate difficulty)</li>
            <li>Level 5 is color-coded red (hardest questions)</li>
        </ul>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // File input handler to show filename
    const fileInput = document.getElementById('question_image');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                const fileLabel = this.closest('label');
                const textElement = fileLabel.querySelector('p.text-sm');
                textElement.innerHTML = `<span class="font-semibold">Selected:</span> ${fileName}`;
            }
        });
    }
});
</script>
<?php include '../includes/footer.php'; ?>