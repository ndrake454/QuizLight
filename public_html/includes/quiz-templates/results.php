<?php
/**
 * Quiz Results Template
 * 
 * Displays the results page after completing a quiz.
 */
?>

<div class="quiz-card rounded-xl shadow-lg overflow-hidden">
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 py-6 px-6 text-white">
        <h2 class="text-2xl font-bold">Quiz Completed!</h2>
        <p class="opacity-80">Here's how you did</p>
    </div>
    
    <div class="p-6">
        <!-- Score summary - redesigned -->
        <div class="mb-6 text-center">
            <?php 
            $score = round(($_SESSION['correct_answers'] / count($_SESSION['questions'])) * 100);
            $scoreColor = $score >= 80 ? 'bg-green-100 text-green-700 border-green-200' : 
                        ($score >= 60 ? 'bg-yellow-100 text-yellow-700 border-yellow-200' : 
                        'bg-red-100 text-red-700 border-red-200');
            ?>
            
            <div class="flex flex-col items-center">
                <!-- Score fraction -->
                <div class="text-lg text-gray-700 mb-2">
                    You scored
                </div>
                
                <div class="flex items-center space-x-2 mb-1">
                    <!-- Actual score display -->
                    <div class="font-bold text-3xl">
                        <?php echo $_SESSION['correct_answers']; ?> / <?php echo count($_SESSION['questions']); ?>
                    </div>
                    
                    <!-- Percentage badge -->
                    <div class="<?php echo $scoreColor; ?> px-3 py-1 rounded-full text-sm font-bold border">
                        <?php echo $score; ?>%
                    </div>
                </div>
                
                <!-- Trophy icon for perfect score, checkmark for passing -->
                <?php if ($score == 100): ?>
                    <div class="text-yellow-500 mb-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                        </svg>
                    </div>
                <?php elseif ($score >= 70): ?>
                    <div class="text-green-500 mb-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Performance message -->
            <?php if ($score >= 80): ?>
                <p class="text-green-600 font-medium mt-2">Excellent work! You've mastered this material.</p>
            <?php elseif ($score >= 60): ?>
                <p class="text-yellow-600 font-medium mt-2">Good job! Keep practicing to improve further.</p>
            <?php else: ?>
                <p class="text-red-600 font-medium mt-2">Keep studying! You'll get better with practice.</p>
            <?php endif; ?>
        </div>
        
        <div class="text-center mb-8">
            <p class="text-lg">
                You answered <span class="font-bold"><?php echo $_SESSION['correct_answers']; ?></span> out of 
                <span class="font-bold"><?php echo count($_SESSION['questions']); ?></span> questions correctly.
            </p>
            
            <p class="text-sm text-gray-500 mt-2">
                Quiz type: <?php echo ucfirst($_SESSION['quiz_type']); ?>
            </p>
        </div>
        
        <?php if ($_SESSION['quiz_type'] === 'adaptive' || $_SESSION['quiz_type'] === 'spaced_repetition'): ?>
        <!-- Adaptive Quiz Progress Chart -->
        <div class="mb-8">
            <div id="adaptive-progress-chart"></div>
            <script>
                // Pass quiz data to the chart component
                window.adaptiveQuizData = <?php echo json_encode($_SESSION['questions']); ?>;
            </script>
        </div>
        <?php endif; ?>
        
        <!-- Review answers button -->
        <div class="flex flex-wrap gap-4 justify-center">
            <button id="toggleReviewBtn" class="bg-indigo-100 text-indigo-700 hover:bg-indigo-200 font-medium py-2 px-4 rounded-md transition duration-150">
                Review Answers
            </button>
            
            <a href="reset_quiz.php" 
               class="bg-indigo-600 text-white hover:bg-indigo-700 font-medium py-2 px-4 rounded-md transition duration-150">
                Take Another Quiz
            </a>
        </div>
        
        <!-- Review section -->
        <div id="reviewSection" class="mt-8 border-t border-gray-200 pt-6 quiz-card p-4 rounded-lg" style="display: none;">
            <?php include 'includes/quiz-templates/review.php'; ?>
        </div>
    </div>
</div>