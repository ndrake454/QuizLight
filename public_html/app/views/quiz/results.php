<!-- app/views/quiz/results.php -->
<div class="max-w-4xl mx-auto">
    <!-- Results Header -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 py-6 px-6 text-white text-center">
            <h1 class="text-2xl font-bold mb-2">Quiz Results</h1>
            <p class="text-lg opacity-90">
                <?php echo $quizAttempt['quiz_type'] === 'practice' ? 'Practice Session' : 'Quiz'; ?> Completed!
            </p>
        </div>
        
        <!-- Results Summary -->
        <div class="p-6">
            <div class="mb-8 text-center">
                <div class="inline-flex items-center justify-center h-24 w-24 rounded-full bg-indigo-100 mb-4">
                    <?php if ($results['accuracy'] >= 80): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    <?php elseif ($results['accuracy'] >= 50): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905V15l-3.226-3.226a.905.905 0 00-1.275 0l-.774.774a.905.905 0 000 1.275l4.244 4.243a1.5 1.5 0 001.06.44h4.471" />
                        </svg>
                    <?php else: ?>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    <?php endif; ?>
                </div>
                
                <h2 class="text-3xl font-bold text-gray-800 mb-1">
                    <?php echo $results['total_correct']; ?> / <?php echo $results['total_questions']; ?> Correct
                </h2>
                
                <p class="text-lg text-gray-600">
                    Accuracy: <?php echo round($results['accuracy']); ?>%
                </p>
                
                <p class="text-sm text-gray-500 mt-1">
                    Completed in 
                    <?php 
                    $minutes = floor($results['duration'] / 60);
                    $seconds = $results['duration'] % 60;
                    
                    if ($minutes > 0) {
                        echo $minutes . ' min ' . $seconds . ' sec';
                    } else {
                        echo $seconds . ' seconds';
                    }
                    ?>
                </p>
            </div>
            
            <!-- Performance Visualization -->
            <div class="mb-8">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Performance</h3>
                
                <div class="h-7 bg-gray-200 rounded-full overflow-hidden">
                    <?php 
                    $accuracyWidth = round($results['accuracy']);
                    $accuracyColor = $accuracyWidth >= 80 ? 'bg-green-500' : ($accuracyWidth >= 50 ? 'bg-yellow-500' : 'bg-red-500');
                    ?>
                    <div class="h-full <?php echo $accuracyColor; ?> rounded-full" style="width: <?php echo $accuracyWidth; ?>%"></div>
                </div>
                
                <div class="flex justify-between text-sm text-gray-600 mt-2">
                    <span>0%</span>
                    <span>50%</span>
                    <span>100%</span>
                </div>
            </div>
            
            <!-- New Achievements -->
            <?php if (!empty($results['new_achievements'])): ?>
                <div class="mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Achievements Unlocked</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php foreach ($results['new_achievements'] as $achievement): ?>
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 flex items-center">
                                <div class="flex-shrink-0 bg-yellow-100 rounded-full p-3 mr-4">
                                    <div class="h-8 w-8 text-yellow-600" dangerously-set-inner-html="<?php echo $achievement['icon']; ?>"></div>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($achievement['name']); ?></h4>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($achievement['description']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Action Buttons -->
            <div class="flex flex-wrap gap-4 justify-center md:justify-between mt-8">
                <a href="/quiz-select" class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-6 rounded-lg font-medium transition duration-150">
                    Take Another Quiz
                </a>
                
                <a href="/profile" class="border border-indigo-600 text-indigo-600 hover:bg-indigo-50 py-2 px-6 rounded-lg font-medium transition duration-150">
                    View Profile
                </a>
            </div>
        </div>
    </div>
    
    <!-- Recommendations Section -->
    <?php if (!empty($recommendations)): ?>
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="text-xl font-semibold text-gray-800">Recommended for You</h2>
            </div>
            
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($recommendations as $recommendation): ?>
                        <div class="border rounded-lg p-4 <?php echo $recommendation['type'] === 'improvement' ? 'border-amber-200 bg-amber-50' : ($recommendation['type'] === 'new' ? 'border-blue-200 bg-blue-50' : 'border-green-200 bg-green-50'); ?>">
                            <h3 class="font-medium mb-2 <?php echo $recommendation['type'] === 'improvement' ? 'text-amber-800' : ($recommendation['type'] === 'new' ? 'text-blue-800' : 'text-green-800'); ?>">
                                <?php echo htmlspecialchars($recommendation['title']); ?>
                            </h3>
                            <p class="text-sm text-gray-600 mb-4"><?php echo htmlspecialchars($recommendation['description']); ?></p>
                            
                            <div class="space-y-2">
                                <?php foreach ($recommendation['categories'] as $category): ?>
                                    <div class="flex justify-between items-center p-2 bg-white bg-opacity-50 rounded-md">
                                        <span class="text-gray-800"><?php echo htmlspecialchars($category['name']); ?></span>
                                        <?php if (isset($category['accuracy'])): ?>
                                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $category['accuracy'] < 60 ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
                                                <?php echo round($category['accuracy']); ?>%
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mt-4 text-center">
                                <form method="POST" action="/quiz/start" class="inline-block">
                                    <input type="hidden" name="quiz_type" value="standard">
                                    <input type="hidden" name="num_questions" value="10">
                                    <input type="hidden" name="difficulty" value="mixed">
                                    <?php foreach ($recommendation['categories'] as $category): ?>
                                        <input type="hidden" name="categories[]" value="<?php echo $category['id']; ?>">
                                    <?php endforeach; ?>
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors shadow-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                        Quick Start
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Practice Questions Section -->
    <?php if (!empty($practiceQuestions)): ?>
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="text-xl font-semibold text-gray-800">Practice These Questions</h2>
            </div>
            
            <div class="p-6">
                <div class="space-y-6">
                    <?php foreach ($practiceQuestions as $index => $question): ?>
                        <div class="border border-gray-200 rounded-lg p-4" x-data="{ showAnswer: false }">
                            <div class="flex justify-between mb-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                    <?php echo htmlspecialchars($question['category_name']); ?>
                                </span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                      <?php
                                      if ($question['difficulty_value'] <= 2.0) {
                                          echo 'bg-green-100 text-green-800';
                                      } elseif ($question['difficulty_value'] <= 4.0) {
                                          echo 'bg-yellow-100 text-yellow-800';
                                      } else {
                                          echo 'bg-red-100 text-red-800';
                                      }
                                      ?>">
                                    <?php
                                    if ($question['difficulty_value'] <= 2.0) {
                                        echo 'Easy';
                                    } elseif ($question['difficulty_value'] <= 4.0) {
                                        echo 'Medium';
                                    } else {
                                        echo 'Hard';
                                    }
                                    ?>
                                </span>
                            </div>
                            
                            <h3 class="text-lg font-medium text-gray-800 mb-3"><?php echo htmlspecialchars($question['question_text']); ?></h3>
                            
                            <div class="mt-4">
                                <button @click="showAnswer = !showAnswer" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium flex items-center">
                                    <span x-text="showAnswer ? 'Hide Answer' : 'Show Answer'"></span>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="ml-1 h-4 w-4 transition-transform duration-200" :class="{ 'transform rotate-180': showAnswer }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                
                                <div x-show="showAnswer" class="mt-2 p-3 bg-gray-50 rounded-md">
                                    <?php if ($question['question_type'] === 'multiple_choice' || empty($question['question_type'])): ?>
                                        <p class="font-medium text-gray-700">Correct Answer:</p>
                                        <ul class="mt-1 space-y-1">
                                            <?php foreach ($question['answers'] as $answer): ?>
                                                <li class="flex items-start">
                                                    <span class="flex-shrink-0 h-5 w-5 inline-flex items-center justify-center rounded-full mr-2 <?php echo $answer['is_correct'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400'; ?>">
                                                        <?php if ($answer['is_correct']): ?>
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                            </svg>
                                                        <?php endif; ?>
                                                    </span>
                                                    <span class="<?php echo $answer['is_correct'] ? 'font-medium text-gray-900' : 'text-gray-700'; ?>">
                                                        <?php echo htmlspecialchars($answer['answer_text']); ?>
                                                    </span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="font-medium text-gray-700">Correct Answer:</p>
                                        <p class="mt-1 text-gray-900">
                                            <?php 
                                            // Find the primary answer
                                            $primaryAnswer = '';
                                            foreach ($question['written_answers'] as $answer) {
                                                if ($answer['is_primary']) {
                                                    $primaryAnswer = $answer['answer_text'];
                                                    break;
                                                }
                                            }
                                            
                                            echo htmlspecialchars($primaryAnswer ?: $question['written_answers'][0]['answer_text']);
                                            ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($question['explanation'])): ?>
                                        <div class="mt-3 pt-3 border-t border-gray-200">
                                            <p class="font-medium text-gray-700">Explanation:</p>
                                            <p class="mt-1 text-gray-700"><?php echo htmlspecialchars($question['explanation']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-6 text-center">
                    <a href="/quiz/practice" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Practice Mode
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>