<?php
/**
 * Quiz Answer Form Template
 * 
 * Displays the form for submitting an answer to the current question.
 */
?>

<!-- Error message if present -->
<?php if (!empty($error)): ?>
    <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-md">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<form action="quiz.php" method="post" id="quiz-form">
    <input type="hidden" name="question_id" value="<?php echo $currentQuestion['id']; ?>">
    
    <?php if ($currentQuestion['question_type'] === 'multiple_choice'): ?>
        <!-- Multiple choice question -->
        <input type="hidden" name="selected_answer" id="selected-answer" value="">
        
        <div class="space-y-3 mb-8">
            <?php foreach ($currentQuestion['answers'] as $index => $answer): ?>
            <div class="answer-option" 
                 data-answer-id="<?php echo $answer['id']; ?>"
                 onclick="selectAnswer('<?php echo $answer['id']; ?>')">
                <div class="border border-gray-200 rounded-lg p-4 cursor-pointer transition-all duration-200 hover:border-indigo-300 hover:bg-indigo-50 quiz-option bg-white"
                     id="answer-option-<?php echo $answer['id']; ?>">
                    <div class="flex items-center">
                        <div class="w-6 h-6 flex items-center justify-center mr-3 rounded-full border-2 border-gray-300"
                             id="answer-circle-<?php echo $answer['id']; ?>">
                            <div id="answer-dot-<?php echo $answer['id']; ?>" class="w-3 h-3 bg-indigo-500 rounded-full hidden"></div>
                        </div>
                        <span class="flex-1"><?php echo htmlspecialchars($answer['answer_text']); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
    <?php else: ?>
        <!-- Written response question -->
        <div class="mb-8">
            <label for="written-answer" class="block text-gray-700 font-medium mb-2">Your Answer:</label>
            <div class="relative">
                <input type="text" id="written-answer" name="written_answer" maxlength="50" 
                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 py-3 px-4" 
                       placeholder="Type your answer (max 3 words)" required autocomplete="off">
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                    <span class="text-gray-500 text-sm" id="word-counter">0/3</span>
                </div>
            </div>
            <p class="mt-1 text-sm text-gray-500">Short answer only (1-3 words). Spelling variations will be considered.</p>
        </div>
    <?php endif; ?>
    
    <div class="flex justify-between">
        <a href="reset_quiz.php" onclick="return confirm('Are you sure you want to quit this quiz? Your progress will be lost.')"
           class="px-4 py-2 border border-gray-300 rounded text-gray-600 hover:bg-gray-50 transition-colors">
            Quit Quiz
        </a>
        
        <button type="submit" name="submit_answer" value="1" 
                class="px-6 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 transition-colors">
            Submit Answer
        </button>
    </div>
</form>