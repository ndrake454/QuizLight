<!-- app/views/admin/question-form.php -->
<div x-data="questionFormApp()" class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">
                <?php echo $question ? 'Edit Question' : 'Add Question'; ?>
            </h2>
        </div>
        
        <form method="POST" action="/admin/save-question" @submit="return validateForm()">
            <?php if ($question): ?>
                <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
            <?php endif; ?>
            
            <div class="px-6 py-4 space-y-6">
                <!-- Category -->
                <div>
                    <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select id="category_id" name="category_id" x-model="categoryId" required
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $question && $question['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div x-show="errors.categoryId" class="mt-1 text-sm text-red-600" x-text="errors.categoryId"></div>
                </div>
                
                <!-- Question Text -->
                <div>
                    <label for="question_text" class="block text-sm font-medium text-gray-700 mb-1">Question Text</label>
                    <textarea id="question_text" name="question_text" x-model="questionText" required rows="3" 
                              class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"><?php echo $question ? htmlspecialchars($question['question_text']) : ''; ?></textarea>
                    <div x-show="errors.questionText" class="mt-1 text-sm text-red-600" x-text="errors.questionText"></div>
                </div>
                
                <!-- Question Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Question Type</label>
                    <div class="flex gap-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="question_type" value="multiple_choice" 
                                   x-model="questionType" 
                                   class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                            <span class="ml-2 text-gray-700">Multiple Choice</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="question_type" value="written_response" 
                                   x-model="questionType" 
                                   class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                            <span class="ml-2 text-gray-700">Written Response</span>
                        </label>
                    </div>
                </div>
                
                <!-- Multiple Choice Answers -->
                <div x-show="questionType === 'multiple_choice'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Answer Options</label>
                    <div class="space-y-3">
                        <template x-for="(answer, index) in answers" :key="index">
                            <div class="flex items-center gap-2">
                                <input type="radio" :name="'correct_answer'" :value="index" 
                                       x-model="correctAnswerIndex" 
                                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                                <input type="text" :name="'answer_text['+index+']'" x-model="answer.text" 
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" 
                                       placeholder="Answer option...">
                                <button type="button" @click="removeAnswer(index)" 
                                        class="text-red-500 hover:text-red-700 focus:outline-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </template>
                        
                        <button type="button" @click="addAnswer" 
                                class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Add Answer Option
                        </button>
                        
                        <div x-show="errors.answers" class="mt-1 text-sm text-red-600" x-text="errors.answers"></div>
                    </div>
                </div>
                
                <!-- Written Response Answers -->
                <div x-show="questionType === 'written_response'">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Acceptable Answers</label>
                    <p class="text-sm text-gray-500 mb-2">
                        Add all possible correct answers. The system will match the student's response against these.
                    </p>
                    <div class="space-y-3">
                        <template x-for="(answer, index) in writtenAnswers" :key="index">
                            <div class="flex items-center gap-2">
                                <input type="radio" :name="'primary_answer'" :value="index" 
                                       x-model="primaryAnswerIndex" 
                                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                                <input type="text" :name="'written_answer['+index+']'" x-model="answer.text" 
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" 
                                       placeholder="Acceptable answer...">
                                <button type="button" @click="removeWrittenAnswer(index)" 
                                        class="text-red-500 hover:text-red-700 focus:outline-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </template>
                        
                        <button type="button" @click="addWrittenAnswer" 
                                class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Add Answer
                        </button>
                        
                        <div class="text-sm text-gray-600 mt-2">
                            <span class="font-medium">Note:</span> Mark the primary (canonical) answer with the radio button.
                        </div>
                        
                        <div x-show="errors.writtenAnswers" class="mt-1 text-sm text-red-600" x-text="errors.writtenAnswers"></div>
                    </div>
                </div>
                
                <!-- Explanation -->
                <div>
                    <label for="explanation" class="block text-sm font-medium text-gray-700 mb-1">Explanation (Optional)</label>
                    <textarea id="explanation" name="explanation" rows="3" 
                              class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"><?php echo $question ? htmlspecialchars($question['explanation'] ?? '') : ''; ?></textarea>
                    <p class="mt-1 text-sm text-gray-500">
                        Provide an explanation for the correct answer. This will be shown to students after they answer.
                    </p>
                </div>
                
                <!-- Active Status -->
                <div class="flex items-center">
                    <input id="is_active" name="is_active" type="checkbox" 
                           <?php echo (!$question || $question['is_active']) ? 'checked' : ''; ?> 
                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    <label for="is_active" class="ml-2 block text-sm text-gray-900">
                        Active (available for quizzes)
                    </label>
                </div>
            </div>
            
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-between">
                <a href="/admin/questions" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Cancel
                </a>
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <?php echo $question ? 'Update Question' : 'Create Question'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function questionFormApp() {
        return {
            categoryId: '<?php echo $question ? $question['category_id'] : ''; ?>',
            questionText: '<?php echo $question ? addslashes($question['question_text']) : ''; ?>',
            questionType: '<?php echo $question ? ($question['question_type'] ?: 'multiple_choice') : 'multiple_choice'; ?>',
            answers: [
                <?php if ($question && ($question['question_type'] === 'multiple_choice' || empty($question['question_type']))): ?>
                    <?php foreach ($question['answers'] as $index => $answer): ?>
                        {
                            text: '<?php echo addslashes($answer['answer_text']); ?>',
                            isCorrect: <?php echo $answer['is_correct'] ? 'true' : 'false'; ?>
                        },
                    <?php endforeach; ?>
                <?php else: ?>
                    { text: '', isCorrect: false },
                    { text: '', isCorrect: false },
                    { text: '', isCorrect: false },
                    { text: '', isCorrect: false }
                <?php endif; ?>
            ],
            correctAnswerIndex: <?php 
                $correctIndex = 0;
                if ($question && ($question['question_type'] === 'multiple_choice' || empty($question['question_type']))) {
                    foreach ($question['answers'] as $index => $answer) {
                        if ($answer['is_correct']) {
                            $correctIndex = $index;
                            break;
                        }
                    }
                }
                echo $correctIndex;
            ?>,
            writtenAnswers: [
                <?php if ($question && $question['question_type'] === 'written_response' && isset($question['written_answers'])): ?>
                    <?php foreach ($question['written_answers'] as $index => $answer): ?>
                        {
                            text: '<?php echo addslashes($answer['answer_text']); ?>',
                            isPrimary: <?php echo $answer['is_primary'] ? 'true' : 'false'; ?>
                        },
                    <?php endforeach; ?>
                <?php else: ?>
                    { text: '', isPrimary: true }
                <?php endif; ?>
            ],
            primaryAnswerIndex: <?php 
                $primaryIndex = 0;
                if ($question && $question['question_type'] === 'written_response' && isset($question['written_answers'])) {
                    foreach ($question['written_answers'] as $index => $answer) {
                        if ($answer['is_primary']) {
                            $primaryIndex = $index;
                            break;
                        }
                    }
                }
                echo $primaryIndex;
            ?>,
            errors: {
                categoryId: '',
                questionText: '',
                answers: '',
                writtenAnswers: ''
            },
            
            addAnswer() {
                this.answers.push({ text: '', isCorrect: false });
            },
            
            removeAnswer(index) {
                if (this.answers.length > 2) {
                    // Adjust the correct answer index if necessary
                    if (this.correctAnswerIndex === index) {
                        this.correctAnswerIndex = 0;
                    } else if (this.correctAnswerIndex > index) {
                        this.correctAnswerIndex--;
                    }
                    
                    this.answers.splice(index, 1);
                } else {
                    alert('A multiple choice question must have at least 2 answers.');
                }
            },
            
            addWrittenAnswer() {
                this.writtenAnswers.push({ text: '', isPrimary: false });
            },
            
            removeWrittenAnswer(index) {
                if (this.writtenAnswers.length > 1) {
                    // Adjust the primary answer index if necessary
                    if (this.primaryAnswerIndex === index) {
                        this.primaryAnswerIndex = 0;
                    } else if (this.primaryAnswerIndex > index) {
                        this.primaryAnswerIndex--;
                    }
                    
                    this.writtenAnswers.splice(index, 1);
                } else {
                    alert('A written response question must have at least 1 answer.');
                }
            },
            
            validateForm() {
                let isValid = true;
                this.errors = {
                    categoryId: '',
                    questionText: '',
                    answers: '',
                    writtenAnswers: ''
                };
                
                // Validate category
                if (!this.categoryId) {
                    this.errors.categoryId = 'Please select a category';
                    isValid = false;
                }
                
                // Validate question text
                if (!this.questionText.trim()) {
                    this.errors.questionText = 'Question text is required';
                    isValid = false;
                }
                
                // Validate answers based on question type
                if (this.questionType === 'multiple_choice') {
                    // Check if we have at least 2 answers
                    if (this.answers.length < 2) {
                        this.errors.answers = 'At least 2 answer options are required';
                        isValid = false;
                    }
                    
                    // Check if all answers have text
                    const emptyAnswers = this.answers.some(answer => !answer.text.trim());
                    if (emptyAnswers) {
                        this.errors.answers = 'All answer options must have text';
                        isValid = false;
                    }
                    
                    // Update isCorrect values based on correctAnswerIndex
                    this.answers.forEach((answer, index) => {
                        answer.isCorrect = (index === parseInt(this.correctAnswerIndex));
                    });
                } else {
                    // Written response validation
                    if (this.writtenAnswers.length < 1) {
                        this.errors.writtenAnswers = 'At least 1 acceptable answer is required';
                        isValid = false;
                    }
                    
                    // Check if all answers have text
                    const emptyAnswers = this.writtenAnswers.some(answer => !answer.text.trim());
                    if (emptyAnswers) {
                        this.errors.writtenAnswers = 'All answers must have text';
                        isValid = false;
                    }
                    
                    // Update isPrimary values based on primaryAnswerIndex
                    this.writtenAnswers.forEach((answer, index) => {
                        answer.isPrimary = (index === parseInt(this.primaryAnswerIndex));
                    });
                }
                
                return isValid;
            }
        };
    }
</script>