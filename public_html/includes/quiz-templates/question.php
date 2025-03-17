<?php
/**
 * Quiz Question Template
 * 
 * Displays the current quiz question and answer form.
 */

// Extract variables for the template
$currentIndex = $_SESSION['current_question'];
$currentQuestion = $_SESSION['questions'][$currentIndex];
$totalQuestions = count($_SESSION['questions']);
$progress = ($currentIndex / $totalQuestions) * 100;
$showExplanation = isset($_SESSION['show_explanation']) && $_SESSION['show_explanation'];
$isCorrect = isset($_SESSION['current_answer_correct']) ? $_SESSION['current_answer_correct'] : null;
$selectedAnswerId = isset($_SESSION['selected_answer_id']) ? $_SESSION['selected_answer_id'] : null;
$writtenUserAnswer = isset($_SESSION['written_user_answer']) ? $_SESSION['written_user_answer'] : null;
?>

<div class="quiz-card rounded-xl shadow-lg overflow-hidden">
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 py-4 px-6 text-white">
        <div class="flex justify-between items-center">
            <h2 class="text-xl font-bold">Question <?php echo $currentIndex + 1; ?> of <?php echo $totalQuestions; ?></h2>
            
            <span class="px-3 py-1 bg-white/20 rounded-full text-sm">
                <?php echo ucfirst($_SESSION['quiz_type']); ?> Quiz
            </span>
        </div>
        
        <!-- Progress bar -->
        <div class="mt-4 h-2 bg-white/20 rounded-full overflow-hidden">
            <div class="h-full bg-white rounded-full progress-animation" style="width: <?php echo $progress; ?>%"></div>
        </div>
    </div>
    
    <div class="p-6">
        <!-- Question text -->
        <div class="text-lg font-medium mb-6"><?php echo htmlspecialchars($currentQuestion['question_text']); ?></div>
        
        <!-- Question image, if available -->
        <?php if (!empty($currentQuestion['image_path'])): ?>
        <div class="mb-6 text-center">
            <img src="uploads/questions/<?php echo htmlspecialchars($currentQuestion['image_path']); ?>" 
                 alt="Question image" class="max-h-80 inline-block rounded shadow-md">
        </div>
        <?php endif; ?>
        
        <!-- Category and difficulty badge -->
        <div class="mb-4 flex flex-wrap gap-2">
            <?php if (!($_SESSION['quiz_type'] === 'adaptive' || isset($_SESSION['quiz_adaptive']))): ?>
            <!-- Category badge -->
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                <?php echo htmlspecialchars($currentQuestion['category_name']); ?>
            </span>
            
            <!-- Difficulty badge -->
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                <?php 
                switch($currentQuestion['intended_difficulty']) {
                    case 'easy': echo 'bg-green-100 text-green-800'; break;
                    case 'challenging': echo 'bg-yellow-100 text-yellow-800'; break;
                    case 'hard': echo 'bg-red-100 text-red-800'; break;
                    default: echo 'bg-gray-100 text-gray-800';
                }
                ?>">
                <?php echo ucfirst(htmlspecialchars($currentQuestion['intended_difficulty'])); ?>
            </span>
            <?php endif; ?>
            
            <!-- Question type badge - always show this one -->
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                <?php echo $currentQuestion['question_type'] === 'multiple_choice' ? 'Multiple Choice' : 'Written Response'; ?>
            </span>
        </div>
        
        <?php if ($showExplanation): ?>
            <?php include 'explanation.php'; ?>
        <?php else: ?>
            <?php include 'answer_form.php'; ?>
        <?php endif; ?>
    </div>
</div>