<?php
/**
 * Admin Edit Question Page
 * 
 * This page allows administrators to:
 * - Edit an existing question's text, category, explanation, and difficulty
 * - Modify, add, or remove answer options
 * - Update the correct answer
 * - Upload or remove a question image
 * 
 * This page requires a question ID parameter to be passed via GET.
 */

require_once '../config.php';
$pageTitle = 'Edit Question';

// Ensure user is logged in and is an admin
requireAdmin();

// Initialize variables
$message = '';
$messageType = '';

// Get question ID from URL parameter
$questionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Validate question ID
if ($questionId <= 0) {
    header("Location: questions.php");
    exit;
}

/**
 * Handle form submission to update the question
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $questionText = trim($_POST['question_text'] ?? '');
    $explanation = trim($_POST['explanation'] ?? '');
    
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
            // Start transaction
            $pdo->beginTransaction();
            
            // Get current image path
            $stmt = $pdo->prepare("SELECT image_path FROM questions WHERE id = ?");
            $stmt->execute([$questionId]);
            $currentQuestion = $stmt->fetch();
            $imagePath = $currentQuestion['image_path'];
            
            // Process new image if uploaded
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
                
                // Generate unique filename
                $uniqueName = uniqid() . '.' . $fileType;
                $targetFilePath = $uploadDir . $uniqueName;
                
                // Check if image file is a valid upload
                $validExtensions = array('jpg', 'jpeg', 'png', 'gif');
                if (in_array($fileType, $validExtensions)) {
                    // Upload file
                    if (move_uploaded_file($_FILES['question_image']['tmp_name'], $targetFilePath)) {
                        // Delete old image if exists
                        if (!empty($imagePath)) {
                            $oldImagePath = $uploadDir . $imagePath;
                            if (file_exists($oldImagePath)) {
                                unlink($oldImagePath);
                            }
                        }
                        
                        $imagePath = $uniqueName;
                    } else {
                        throw new Exception("Failed to upload image.");
                    }
                } else {
                    throw new Exception("Invalid file format. Please upload a JPG, JPEG, PNG, or GIF image.");
                }
            }
            
            // Handle image deletion if requested
            if (isset($_POST['delete_image']) && $_POST['delete_image'] == 1 && !empty($currentQuestion['image_path'])) {
                $oldImagePath = '../uploads/questions/' . $currentQuestion['image_path'];
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
                $imagePath = null;
            }
            
            // Update question
            $stmt = $pdo->prepare("UPDATE questions SET 
                                   category_id = ?, 
                                   question_text = ?, 
                                   explanation = ?, 
                                   intended_difficulty = ?, 
                                   difficulty_value = ?,
                                   image_path = ?
                                   WHERE id = ?");
            $stmt->execute([$categoryId, $questionText, $explanation, $difficulty, $difficultyValue, $imagePath, $questionId]);
            
            // Process answers
            $answerIds = $_POST['answer_ids'] ?? [];
            $answers = $_POST['answers'] ?? [];
            $correctAnswer = isset($_POST['correct_answer']) ? (int)$_POST['correct_answer'] : -1;
            
            if (count($answers) < 2) {
                throw new Exception("At least two answers are required.");
            }
            
            if ($correctAnswer < 0 || $correctAnswer >= count($answers)) {
                throw new Exception("Please select a correct answer.");
            }
            
            // First, mark all existing answers as incorrect
            $stmt = $pdo->prepare("UPDATE answers SET is_correct = 0 WHERE question_id = ?");
            $stmt->execute([$questionId]);
            
            foreach ($answers as $index => $answerText) {
                $answerText = trim($answerText);
                if (empty($answerText)) continue;
                
                $isCorrect = ($index === $correctAnswer) ? 1 : 0;
                $answerId = isset($answerIds[$index]) ? (int)$answerIds[$index] : 0;
                
                if ($answerId > 0) {
                    // Update existing answer
                    $stmt = $pdo->prepare("UPDATE answers SET answer_text = ?, is_correct = ? WHERE id = ? AND question_id = ?");
                    $stmt->execute([$answerText, $isCorrect, $answerId, $questionId]);
                } else {
                    // Insert new answer
                    $stmt = $pdo->prepare("INSERT INTO answers (question_id, answer_text, is_correct) VALUES (?, ?, ?)");
                    $stmt->execute([$questionId, $answerText, $isCorrect]);
                }
            }
            
            // Delete answers that aren't in the form anymore
            $keepAnswerIds = array_filter($answerIds, function($id) { return $id > 0; });
            if (!empty($keepAnswerIds)) {
                $placeholders = implode(',', array_fill(0, count($keepAnswerIds), '?'));
                $stmt = $pdo->prepare("DELETE FROM answers WHERE question_id = ? AND id NOT IN ($placeholders)");
                $params = array_merge([$questionId], $keepAnswerIds);
                $stmt->execute($params);
            }
            
            // Commit transaction
            $pdo->commit();
            
            $message = "Question updated successfully.";
            $messageType = "success";
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
            // Log the error for debugging
            error_log("Admin question edit error: " . $e->getMessage());
        }
    }
}

/**
 * Fetch question and answers data
 */
try {
    $stmt = $pdo->prepare("SELECT q.*, c.name as category_name 
                          FROM questions q 
                          JOIN categories c ON q.category_id = c.id 
                          WHERE q.id = ?");
    $stmt->execute([$questionId]);
    
    if ($stmt->rowCount() === 0) {
        header("Location: questions.php");
        exit;
    }
    
    $question = $stmt->fetch();
    
    // Get answers
    $stmt = $pdo->prepare("SELECT id, answer_text, is_correct FROM answers WHERE question_id = ? ORDER BY id ASC");
    $stmt->execute([$questionId]);
    $answers = $stmt->fetchAll();
    
    // Fetch categories for the dropdown
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
    $messageType = "error";
    // Log the error for debugging
    error_log("Admin question fetch error: " . $e->getMessage());
    exit;
}

include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Edit Question</h1>
        <div>
            <a href="questions.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition duration-150">
                Back to Questions
            </a>
        </div>
    </div>
    
    <!-- Display success/error messages -->
    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <form action="" method="POST" class="space-y-6" enctype="multipart/form-data" x-data="{ 
            answerCount: <?php echo count($answers); ?>,
            difficultyValue: <?php echo intval($question['difficulty_value']); ?>
        }">
            <!-- Category selection -->
            <div>
                <label for="category_id" class="block text-sm font-medium text-gray-700">Category</label>
                <select name="category_id" id="category_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo $category['id'] == $question['category_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Question text -->
            <div>
                <label for="question_text" class="block text-sm font-medium text-gray-700">Question Text</label>
                <textarea name="question_text" id="question_text" rows="3" required 
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"><?php echo htmlspecialchars($question['question_text']); ?></textarea>
            </div>
            
            <!-- Question image -->
            <div>
                <label for="question_image" class="block text-sm font-medium text-gray-700">Question Image</label>
                
                <?php if (!empty($question['image_path'])): ?>
                    <div class="mt-2 mb-4">
                        <img src="../uploads/questions/<?php echo htmlspecialchars($question['image_path']); ?>" 
                             alt="Question image" class="max-h-60 rounded border border-gray-300">
                        
                        <div class="mt-2 flex items-center">
                            <input type="checkbox" id="delete_image" name="delete_image" value="1" class="h-4 w-4 text-red-600">
                            <label for="delete_image" class="ml-2 text-sm text-red-600">Delete current image</label>
                        </div>
                    </div>
                <?php endif; ?>
                
                <input type="file" name="question_image" id="question_image" accept="image/*"
                       class="mt-1 block w-full text-sm text-gray-500
                       file:mr-4 file:py-2 file:px-4
                       file:rounded-md file:border-0
                       file:text-sm file:font-semibold
                       file:bg-indigo-50 file:text-indigo-700
                       hover:file:bg-indigo-100">
                <p class="mt-1 text-sm text-gray-500">Upload a new image (JPG, JPEG, PNG, or GIF).</p>
            </div>
            
            <!-- Explanation -->
            <div>
                <label for="explanation" class="block text-sm font-medium text-gray-700">Explanation</label>
                <textarea name="explanation" id="explanation" rows="3" 
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"><?php echo htmlspecialchars($question['explanation'] ?? ''); ?></textarea>
                <p class="mt-1 text-sm text-gray-500">Explanation is shown to users after they answer the question.</p>
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
            
            <!-- Answer options -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Answers</label>
                <div class="space-y-2">
                    <?php foreach ($answers as $index => $answer): ?>
                        <div class="flex items-center">
                            <input type="hidden" name="answer_ids[<?php echo $index; ?>]" value="<?php echo $answer['id']; ?>">
                            <input type="radio" name="correct_answer" value="<?php echo $index; ?>" id="correct_<?php echo $index; ?>" 
                                   <?php echo $answer['is_correct'] ? 'checked' : ''; ?> required 
                                   class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 mr-2">
                            <label for="correct_<?php echo $index; ?>" class="sr-only">Correct answer</label>
                            <input type="text" name="answers[<?php echo $index; ?>]" value="<?php echo htmlspecialchars($answer['answer_text']); ?>" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                            <button type="button" @click="answerCount--" 
                                    x-show="answerCount > 2" class="ml-2 text-red-600 hover:text-red-800 transition duration-150">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Template for adding new answer options -->
                    <template x-for="i in Math.max(0, answerCount - <?php echo count($answers); ?>)" :key="i + <?php echo count($answers); ?>">
                        <div class="flex items-center">
                            <input type="hidden" :name="'answer_ids[' + (i + <?php echo count($answers); ?> - 1) + ']'" value="0">
                            <input type="radio" name="correct_answer" :value="i + <?php echo count($answers); ?> - 1" :id="'correct_' + (i + <?php echo count($answers); ?> - 1)" required 
                                   class="h-5 w-5 text-indigo-600 focus:ring-indigo-500 mr-2">
                            <label :for="'correct_' + (i + <?php echo count($answers); ?> - 1)" class="sr-only">Correct answer</label>
                            <input type="text" :name="'answers[' + (i + <?php echo count($answers); ?> - 1) + ']'" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                   placeholder="Enter answer option">
                            <button type="button" @click="answerCount--" 
                                    class="ml-2 text-red-600 hover:text-red-800 transition duration-150">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>
                
                <!-- Add answer button -->
                <button type="button" @click="answerCount++" 
                        class="mt-2 inline-flex items-center text-indigo-600 hover:text-indigo-800 transition duration-150">
                    <svg class="h-5 w-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Add Answer Option
                </button>
                <p class="mt-1 text-sm text-gray-500">Select the radio button next to the correct answer.</p>
            </div>
            
            <!-- Action buttons -->
            <div class="flex justify-end">
                <a href="questions.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded mr-2 transition duration-150">
                    Cancel
                </a>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded transition duration-150">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>