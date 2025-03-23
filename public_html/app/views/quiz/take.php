<!-- app/views/quiz/take.php -->
<div class="max-w-4xl mx-auto" x-data="quizApp()">
    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <!-- Quiz Header -->
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 py-4 px-6 text-white">
            <div class="flex justify-between items-center">
                <h1 class="text-xl font-bold">
                    <?php echo $quiz['type'] === 'practice' ? 'Practice Mode' : 'Quiz'; ?>
                </h1>
                <div class="text-white">
                    Question <span x-text="currentQuestion"><?php echo $questionNumber; ?></span> of <?php echo $totalQuestions; ?>
                </div>
            </div>
            
            <!-- Progress Bar -->
            <div class="mt-2 bg-white bg-opacity-20 rounded-full h-2.5">
                <div class="bg-white h-2.5 rounded-full transition-all duration-500"
                     :style="{ width: progressPercentage + '%' }"></div>
            </div>
        </div>
        
        <!-- Question Content -->
        <div class="p-6">
            <!-- Category Badge -->
            <div class="mb-4">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-indigo-100 text-indigo-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                    </svg>
                    <?php echo htmlspecialchars($question['category_name']); ?>
                </span>
                
                <!-- Difficulty Badge -->
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                      <?php
                      if ($question['difficulty_value'] <= 2.0) {
                          echo 'bg-green-100 text-green-800';
                      } elseif ($question['difficulty_value'] <= 4.0) {
                          echo 'bg-yellow-100 text-yellow-800';
                      } else {
                          echo 'bg-red-100 text-red-800';
                      }
                      ?>
                      ml-2">
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
            
            <!-- Question Text -->
            <div class="mb-6">
                <h2 class="text-xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($question['question_text']); ?></h2>
                
                <?php if (!empty($question['question_image'])): ?>
                    <div class="my-4 flex justify-center">
                        <img src="<?php echo htmlspecialchars($question['question_image']); ?>" alt="Question Image" class="max-w-full rounded-lg shadow-sm">
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Answers Section -->
            <div class="space-y-4">
                <!-- Multiple Choice Questions -->
                <?php if ($question['question_type'] === 'multiple_choice' || empty($question['question_type'])): ?>
                    <?php foreach ($question['answers'] as $index => $answer): ?>
                        <div class="answer-option">
                            <label class="flex items-center p-4 border rounded-lg cursor-pointer transition-all hover:bg-gray-50"
                                   :class="{ 
                                       'border-green-500 bg-green-50': answered && correctOptionId === <?php echo $answer['id']; ?>,
                                       'border-red-500 bg-red-50': answered && selectedOptionId === <?php echo $answer['id']; ?> && selectedOptionId !== correctOptionId,
                                       'border-gray-300': !(answered && (correctOptionId === <?php echo $answer['id']; ?> || selectedOptionId === <?php echo $answer['id']; ?>))
                                   }">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <span class="h-5 w-5 flex items-center justify-center rounded-full border mr-3"
                                              :class="{
                                                  'border-green-500 bg-green-500 text-white': answered && correctOptionId === <?php echo $answer['id']; ?>,
                                                  'border-red-500 bg-red-500 text-white': answered && selectedOptionId === <?php echo $answer['id']; ?> && selectedOptionId !== correctOptionId,
                                                  'border-gray-300': !(answered && (correctOptionId === <?php echo $answer['id']; ?> || selectedOptionId === <?php echo $answer['id']; ?>))
                                              }">
                                            <template x-if="answered && correctOptionId === <?php echo $answer['id']; ?>">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </template>
                                            <template x-if="answered && selectedOptionId === <?php echo $answer['id']; ?> && selectedOptionId !== correctOptionId">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </template>
                                            <template x-if="!answered">
                                                <?php echo chr(65 + $index); ?>
                                            </template>
                                        </span>
                                        <span class="text-gray-700"><?php echo htmlspecialchars($answer['answer_text']); ?></span>
                                    </div>
                                </div>
                                <div x-show="!answered">
                                    <input type="radio" name="answer" value="<?php echo $answer['id']; ?>" 
                                           @click="selectOption(<?php echo $answer['id']; ?>)" 
                                           class="form-radio h-5 w-5 text-indigo-600">
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Written Answer Questions -->
                    <div class="space-y-4">
                        <div x-show="!answered">
                            <label for="written_answer" class="block text-sm font-medium text-gray-700 mb-1">Your Answer:</label>
                            <textarea id="written_answer" 
                                      x-model="writtenAnswer"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                      rows="3"></textarea>
                        </div>
                        
                        <div x-show="answered">
                            <div class="p-4 border rounded-lg" :class="isCorrect ? 'border-green-500 bg-green-50' : 'border-red-500 bg-red-50'">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0 mt-0.5">
                                        <template x-if="isCorrect">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </template>
                                        <template x-if="!isCorrect">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </template>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium" :class="isCorrect ? 'text-green-800' : 'text-red-800'">
                                            <span x-text="isCorrect ? 'Correct!' : 'Incorrect'"></span>
                                        </h3>
                                        <div class="mt-1 text-sm" :class="isCorrect ? 'text-green-700' : 'text-red-700'">
                                            <div class="mb-1">
                                                <span class="font-medium">Your answer:</span> 
                                                <span x-text="writtenAnswer"></span>
                                            </div>
                                            <template x-if="!isCorrect">
                                                <div>
                                                    <span class="font-medium">Correct answer:</span> 
                                                    <span x-text="correctAnswer"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Feedback Area (shown after answering) -->
            <div x-show="answered && feedbackMessage" class="mt-6">
                <div class="p-4 rounded-lg" :class="isCorrect ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg x-show="isCorrect" class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            <svg x-show="!isCorrect" class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium" x-text="isCorrect ? 'Correct!' : 'Incorrect'"></h3>
                            <div class="mt-2 text-sm" x-html="feedbackMessage"></div>
                            <?php if (!empty($question['explanation'])): ?>
                                <div class="mt-2 pt-2 border-t border-gray-200 text-sm">
                                    <strong>Explanation:</strong> <?php echo htmlspecialchars($question['explanation']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer Actions -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-between">
            <!-- Submit Answer Button (only shown before answering) -->
            <button x-show="!answered && !isLoading" 
                    @click="submitAnswer()" 
                    :disabled="<?php echo ($question['question_type'] === 'multiple_choice' || empty($question['question_type'])) ? 'selectedOptionId === null' : 'writtenAnswer.trim() === ""'; ?>" 
                    class="bg-indigo-600 text-white px-4 py-2 rounded-md font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                Submit Answer
            </button>
            
            <!-- Loading State -->
            <button x-show="isLoading" 
                    disabled
                    class="bg-indigo-600 text-white px-4 py-2 rounded-md font-medium opacity-75 cursor-not-allowed flex items-center">
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Processing...
            </button>
            
            <!-- Next Question Button (only shown after answering) -->
            <button x-show="answered && !quizComplete" 
                    @click="nextQuestion()" 
                    class="bg-indigo-600 text-white px-4 py-2 rounded-md font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Next Question
            </button>
            
            <!-- Complete Quiz Button (only shown after last question) -->
            <button x-show="answered && quizComplete" 
                    @click="finishQuiz()" 
                    class="bg-green-600 text-white px-4 py-2 rounded-md font-medium hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                View Results
            </button>
        </div>
    </div>
    
    <!-- Achievement Popup -->
    <div x-show="showAchievement" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-90"
         x-transition:enter-end="opacity-100 transform scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 transform scale-100"
         x-transition:leave-end="opacity-0 transform scale-90"
         class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
        
        <!-- Modal -->
        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full overflow-hidden transform transition-all">
            <!-- Gold stars at the top -->
            <div class="absolute top-0 left-0 right-0 h-24 bg-gradient-to-r from-yellow-300 via-yellow-400 to-yellow-300 overflow-hidden">
                <div class="flex justify-between">
                    <svg class="h-16 w-16 text-yellow-100 opacity-50 transform -translate-x-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"></path>
                    </svg>
                    <svg class="h-24 w-24 text-yellow-100 opacity-30 transform translate-x-4 -translate-y-6" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"></path>
                    </svg>
                </div>
            </div>
            
            <!-- Achievement content -->
            <div class="pt-20 px-6 pb-6 text-center">
                <div class="mt-2 flex justify-center">
                    <div class="h-24 w-24 rounded-full bg-yellow-100 border-4 border-yellow-400 flex items-center justify-center">
                        <svg x-html="achievementIcon" class="h-12 w-12 text-yellow-600"></svg>
                    </div>
                </div>
                
                <h3 class="mt-4 text-xl font-bold text-gray-900" x-text="achievementName"></h3>
                <p class="mt-2 text-sm text-gray-500" x-text="achievementDescription"></p>
                
                <div class="mt-6">
                    <button @click="showAchievement = false" class="inline-flex justify-center px-4 py-2 text-sm font-medium text-indigo-600 bg-indigo-100 border border-transparent rounded-md hover:bg-indigo-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-indigo-500">
                        Awesome!
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function quizApp() {
        return {
            currentQuestion: <?php echo $questionNumber; ?>,
            totalQuestions: <?php echo $totalQuestions; ?>,
            progressPercentage: <?php echo (($questionNumber - 1) / $totalQuestions) * 100; ?>,
            
            selectedOptionId: null,
            writtenAnswer: '',
            answered: false,
            isCorrect: false,
            correctOptionId: null,
            correctAnswer: '',
            feedbackMessage: '',
            
            isLoading: false,
            quizComplete: false,
            redirect: null,
            
            showAchievement: false,
            achievementName: '',
            achievementDescription: '',
            achievementIcon: '',
            newAchievements: [],
            
            selectOption(optionId) {
                this.selectedOptionId = optionId;
            },
            
            submitAnswer() {
                this.isLoading = true;
                
                // Prepare form data
                const formData = new FormData();
                
                <?php if ($question['question_type'] === 'multiple_choice' || empty($question['question_type'])): ?>
                    formData.append('answer_id', this.selectedOptionId);
                <?php else: ?>
                    formData.append('written_answer', this.writtenAnswer);
                <?php endif; ?>
                
                // Send request to server
                fetch('/quiz/submit-answer', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    this.isLoading = false;
                    this.answered = true;
                    this.isCorrect = data.isCorrect;
                    this.correctAnswer = data.correctAnswer;
                    this.quizComplete = data.quizComplete;
                    this.redirect = data.redirect || null;
                    
                    <?php if ($question['question_type'] === 'multiple_choice' || empty($question['question_type'])): ?>
                        // For multiple choice, find the correct option ID
                        const options = document.querySelectorAll('.answer-option input');
                        options.forEach(option => {
                            if (option.value === this.selectedOptionId) {
                                this.feedbackMessage = this.isCorrect 
                                    ? 'You selected the correct answer.' 
                                    : 'Your answer was incorrect.';
                            }
                            
                            // Check if this is the correct answer
                            const optionLabel = option.closest('label');
                            const answerText = optionLabel.querySelector('.text-gray-700').textContent;
                            if (answerText === data.correctAnswer) {
                                this.correctOptionId = option.value;
                            }
                        });
                    <?php else: ?>
                        this.feedbackMessage = this.isCorrect 
                            ? 'Your answer was correct!' 
                            : `The correct answer is: ${data.correctAnswer}`;
                    <?php endif; ?>
                    
                    // Check for new achievements
                    if (data.newAchievements && data.newAchievements.length > 0) {
                        this.newAchievements = data.newAchievements;
                        this.showNewAchievement();
                    }
                })
                .catch(error => {
                    console.error('Error submitting answer:', error);
                    this.isLoading = false;
                    this.feedbackMessage = 'There was an error submitting your answer. Please try again.';
                });
            },
            
            nextQuestion() {
                this.answered = false;
                this.selectedOptionId = null;
                this.writtenAnswer = '';
                this.feedbackMessage = '';
                this.currentQuestion++;
                this.progressPercentage = ((this.currentQuestion - 1) / this.totalQuestions) * 100;
                
                // Show next achievement if available
                if (this.newAchievements.length > 0) {
                    this.showNewAchievement();
                }
            },
            
            finishQuiz() {
                if (this.redirect) {
                    window.location.href = this.redirect;
                } else {
                    window.location.href = '/quiz/results';
                }
            },
            
            showNewAchievement() {
                if (this.newAchievements.length === 0) return;
                
                const achievement = this.newAchievements.shift();
                this.achievementName = achievement.name;
                this.achievementDescription = achievement.description;
                this.achievementIcon = achievement.icon;
                this.showAchievement = true;
            }
        };
    }
</script>