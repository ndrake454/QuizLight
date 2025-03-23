<?php
/**
 * Quiz Answer Explanation Template
 * 
 * Displays the explanation after a user submits an answer.
 */
?>

<!-- Answer Result and Explanation -->
<div class="p-4 mb-6 rounded-md <?php echo $isCorrect ? 'bg-green-50 border-l-4 border-green-500' : 'bg-red-50 border-l-4 border-red-500'; ?>">
    <div class="font-bold text-xl mb-2 <?php echo $isCorrect ? 'text-green-700' : 'text-red-700'; ?>">
        <?php echo $isCorrect ? 'Correct!' : 'Incorrect'; ?>
    </div>
    
    <?php if ($currentQuestion['question_type'] === 'multiple_choice'): ?>
        <!-- Show multiple choice answers explanation -->
        <div class="space-y-3 mb-4">
            <?php foreach ($currentQuestion['answers'] as $answer): ?>
                <div class="p-3 rounded-md <?php 
                    if ($answer['id'] == $selectedAnswerId && $answer['is_correct']) {
                        echo 'bg-green-100';
                    } elseif ($answer['id'] == $selectedAnswerId && !$answer['is_correct']) {
                        echo 'bg-red-100';
                    } elseif ($answer['is_correct']) {
                        echo 'bg-green-50';
                    } else {
                        echo 'bg-gray-50';
                    }
                ?>">
                    <div class="flex items-center">
                        <?php if ($answer['id'] == $selectedAnswerId && $answer['is_correct']): ?>
                            <svg class="w-5 h-5 mr-2 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                        <?php elseif ($answer['id'] == $selectedAnswerId && !$answer['is_correct']): ?>
                            <svg class="w-5 h-5 mr-2 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                        <?php elseif ($answer['is_correct']): ?>
                            <svg class="w-5 h-5 mr-2 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                        <?php else: ?>
                            <span class="w-5 h-5 mr-2"></span>
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars($answer['answer_text']); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- Show written response explanation -->
        <div class="space-y-3 mb-4">
            <div class="p-3 rounded-md <?php echo $isCorrect ? 'bg-green-100' : 'bg-red-100'; ?>">
                <div class="flex items-center">
                    <?php if ($isCorrect): ?>
                        <svg class="w-5 h-5 mr-2 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                    <?php else: ?>
                        <svg class="w-5 h-5 mr-2 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                    <?php endif; ?>
                    <span>Your answer: <strong><?php echo htmlspecialchars($writtenUserAnswer); ?></strong></span>
                </div>
            </div>
            
            <?php if (!$isCorrect): ?>
                <div class="p-3 rounded-md bg-green-50">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        <span>Correct answer: <strong><?php echo htmlspecialchars($currentQuestion['primary_answer']); ?></strong></span>
                    </div>
                </div>
                
                <?php if (count($currentQuestion['written_answers']) > 1): ?>
                    <div class="text-sm text-gray-600 mt-2">
                        <p>Other acceptable answers:</p>
                        <ul class="list-disc list-inside mt-1 pl-2">
                            <?php 
                            $acceptableAnswers = [];
                            foreach ($currentQuestion['written_answers'] as $answer) {
                                if (!$answer['is_primary']) {
                                    $acceptableAnswers[] = htmlspecialchars($answer['answer_text']);
                                }
                            }
                            echo implode(', ', $acceptableAnswers);
                            ?>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($currentQuestion['explanation'])): ?>
        <div class="mt-4 p-3 bg-white rounded-md">
            <div class="font-bold text-blue-800 mb-1">Explanation:</div>
            <div><?php echo nl2br(htmlspecialchars($currentQuestion['explanation'])); ?></div>
        </div>
    <?php endif; ?>
</div>

<!-- Rate Question Form -->
<form action="quiz.php" method="post" class="mt-6">
    <input type="hidden" name="question_id" value="<?php echo $currentQuestion['id']; ?>">
    <input type="hidden" name="difficulty_rating" value="unrated">
    
    <div class="mb-4">
        <label class="block text-gray-700 text-sm font-bold mb-2">Rate this question (optional):</label>
        <div class="flex space-x-4">
            <label class="flex items-center">
                <input type="radio" name="difficulty_rating" value="easy" class="mr-2">
                <span class="text-green-600 font-medium">Easy</span>
            </label>
            <label class="flex items-center">
                <input type="radio" name="difficulty_rating" value="challenging" class="mr-2">
                <span class="text-yellow-600 font-medium">Challenging</span>
            </label>
            <label class="flex items-center">
                <input type="radio" name="difficulty_rating" value="hard" class="mr-2">
                <span class="text-red-600 font-medium">Hard</span>
            </label>
        </div>
        <p class="text-sm text-gray-500 mt-2">Your optional rating helps us improve our question difficulty levels.</p>
    </div>
    
    <div class="text-right">
        <button type="submit" name="rate_question" value="1" 
                class="px-6 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 transition-colors">
            Continue to Next Question
        </button>
    </div>
</form>