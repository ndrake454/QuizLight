<?php
/**
 * Quiz Review Template
 * 
 * Displays the review section of completed questions and answers.
 */
?>

<h3 class="text-xl font-bold mb-4">Question Review</h3>

<?php foreach ($_SESSION['questions'] as $index => $question): ?>
<div class="mb-6 p-4 rounded-md <?php echo $question['is_correct'] ? 'bg-green-50 border-l-4 border-green-400' : 'bg-red-50 border-l-4 border-red-400'; ?>">
    <div class="flex justify-between items-start">
        <h4 class="font-medium">Question <?php echo $index + 1; ?></h4>
        <span class="px-2 py-1 rounded text-xs font-bold <?php echo $question['is_correct'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
            <?php echo $question['is_correct'] ? 'Correct' : 'Incorrect'; ?>
        </span>
    </div>
    
    <p class="my-2"><?php echo htmlspecialchars($question['question_text']); ?></p>
    
    <?php if (!empty($question['image_path'])): ?>
    <div class="my-2 text-center">
        <img src="uploads/questions/<?php echo htmlspecialchars($question['image_path']); ?>" 
             alt="Question image" class="max-h-60 inline-block rounded">
    </div>
    <?php endif; ?>
    
    <div class="my-2">
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
            <?php 
            switch($question['intended_difficulty']) {
                case 'easy': echo 'bg-green-100 text-green-800'; break;
                case 'challenging': echo 'bg-yellow-100 text-yellow-800'; break;
                case 'hard': echo 'bg-red-100 text-red-800'; break;
                default: echo 'bg-gray-100 text-gray-800';
            }
            ?>">
            <?php echo ucfirst(htmlspecialchars($question['intended_difficulty'])); ?> 
            (<?php echo $question['difficulty_value']; ?>)
        </span>
        
        <!-- Question type badge -->
        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
            <?php echo $question['question_type'] === 'multiple_choice' ? 'Multiple Choice' : 'Written Response'; ?>
        </span>
        
        <?php if (isset($question['user_rating'])): ?>
        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
            You rated this: <?php echo ucfirst(htmlspecialchars($question['user_rating'])); ?>
        </span>
        <?php endif; ?>
    </div>
    
    <?php if ($question['question_type'] === 'multiple_choice'): ?>
    <!-- Multiple choice review -->
    <div class="mt-3">
        <?php foreach ($question['answers'] as $answer): ?>
        <div class="flex items-start space-x-2 p-2 rounded-md <?php 
            if (isset($question['user_answer']) && $answer['id'] == $question['user_answer'] && $answer['is_correct']) {
                echo 'bg-green-100';
            } elseif (isset($question['user_answer']) && $answer['id'] == $question['user_answer'] && !$answer['is_correct']) {
                echo 'bg-red-100';
            } elseif ($answer['is_correct']) {
                echo 'bg-green-50';
            } else {
                echo 'bg-gray-50';
            }
        ?>">
            <div class="mt-1">
                <?php 
                if (isset($question['user_answer']) && $answer['id'] == $question['user_answer'] && $answer['is_correct']) {
                    echo '<svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>';
                } elseif (isset($question['user_answer']) && $answer['id'] == $question['user_answer'] && !$answer['is_correct']) {
                    echo '<svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>';
                } elseif ($answer['is_correct']) {
                    echo '<svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>';
                } else {
                    echo '<svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd"></path></svg>';
                }
                ?>
            </div>
            <div class="flex-1"><?php echo htmlspecialchars($answer['answer_text']); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <!-- Written response review -->
    <div class="mt-3 space-y-3">
        <!-- User's answer -->
        <div class="p-3 rounded-md <?php echo $question['is_correct'] ? 'bg-green-100' : 'bg-red-100'; ?>">
            <div class="flex items-center">
                <?php if ($question['is_correct']): ?>
                    <svg class="w-5 h-5 mr-2 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                <?php else: ?>
                    <svg class="w-5 h-5 mr-2 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                <?php endif; ?>
                <span>Your answer: <strong><?php echo htmlspecialchars($question['user_written_answer'] ?? ''); ?></strong></span>
            </div>
        </div>
        
        <?php if (!$question['is_correct']): ?>
            <!-- Correct answer -->
            <div class="p-3 rounded-md bg-green-50">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Correct answer: <strong><?php echo htmlspecialchars($question['primary_answer'] ?? ''); ?></strong></span>
                </div>
            </div>
            
            <?php if (isset($question['written_answers']) && count($question['written_answers']) > 1): ?>
                <!-- Other acceptable answers -->
                <div class="text-sm text-gray-600 ml-7">
                    <p>Other acceptable answers:</p>
                    <div class="mt-1 pl-2">
                        <?php 
                        $acceptableAnswers = [];
                        foreach ($question['written_answers'] as $answer) {
                            if (!isset($answer['is_primary']) || !$answer['is_primary']) {
                                $acceptableAnswers[] = htmlspecialchars($answer['answer_text']);
                            }
                        }
                        echo implode(', ', $acceptableAnswers);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($question['explanation'])): ?>
    <div class="mt-4 p-3 quiz-card rounded-md text-sm text-gray-700">
        <div class="font-bold text-blue-800 mb-1">Explanation:</div>
        <?php echo nl2br(htmlspecialchars($question['explanation'])); ?>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>