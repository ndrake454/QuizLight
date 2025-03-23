<!-- app/views/quiz/select.php -->
<div x-data="quizSelectApp()" class="max-w-5xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Select Quiz Options</h1>

    <form method="POST" action="/quiz/start" @submit="return validateForm()">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Quiz Config Panel -->
            <div class="md:col-span-2">
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-semibold mb-4">Quiz Configuration</h2>

                    <!-- Quiz Type Selection -->
                    <div class="mb-6">
                        <label class="block text-gray-700 font-medium mb-2">Quiz Type</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="flex items-center p-4 border rounded-lg cursor-pointer transition-all"
                                       :class="quizType === 'standard' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-indigo-200'">
                                    <input type="radio" name="quiz_type" value="standard" x-model="quizType" class="sr-only">
                                    <div class="flex items-center justify-center w-10 h-10 bg-indigo-100 rounded-full mr-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="font-medium">Standard Quiz</div>
                                        <div class="text-sm text-gray-500">Multiple choice questions</div>
                                    </div>
                                </label>
                            </div>
                            <div>
                                <label class="flex items-center p-4 border rounded-lg cursor-pointer transition-all"
                                       :class="quizType === 'adaptive' ? 'border-purple-500 bg-purple-50' : 'border-gray-200 hover:border-purple-200'">
                                    <input type="radio" name="quiz_type" value="adaptive" x-model="quizType" class="sr-only">
                                    <div class="flex items-center justify-center w-10 h-10 bg-purple-100 rounded-full mr-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="font-medium">Adaptive Quiz</div>
                                        <div class="text-sm text-gray-500">Questions adjust to your level</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Number of Questions Slider -->
                    <div class="mb-6">
                        <label for="num_questions" class="block text-gray-700 font-medium mb-2">Number of Questions: <span x-text="numQuestions"></span></label>
                        <input type="range" id="num_questions" name="num_questions" min="5" max="30" step="5" 
                               x-model="numQuestions" 
                               class="w-full h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer">
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span>5</span>
                            <span>10</span>
                            <span>15</span>
                            <span>20</span>
                            <span>25</span>
                            <span>30</span>
                        </div>
                    </div>

                    <!-- Difficulty Selection -->
                    <div class="mb-6">
                        <label class="block text-gray-700 font-medium mb-2">Difficulty Level</label>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                            <label class="inline-flex items-center p-3 border rounded-lg cursor-pointer transition-all"
                                   :class="difficulty === 'easy' ? 'border-green-500 bg-green-50' : 'border-gray-200 hover:border-green-200'">
                                <input type="radio" name="difficulty" value="easy" x-model="difficulty" class="sr-only">
                                <span class="h-4 w-4 mr-2 rounded-full" :class="difficulty === 'easy' ? 'bg-green-500' : 'bg-gray-200'"></span>
                                <span>Easy</span>
                            </label>
                            <label class="inline-flex items-center p-3 border rounded-lg cursor-pointer transition-all"
                                   :class="difficulty === 'medium' ? 'border-yellow-500 bg-yellow-50' : 'border-gray-200 hover:border-yellow-200'">
                                <input type="radio" name="difficulty" value="medium" x-model="difficulty" class="sr-only">
                                <span class="h-4 w-4 mr-2 rounded-full" :class="difficulty === 'medium' ? 'bg-yellow-500' : 'bg-gray-200'"></span>
                                <span>Medium</span>
                            </label>
                            <label class="inline-flex items-center p-3 border rounded-lg cursor-pointer transition-all"
                                   :class="difficulty === 'hard' ? 'border-red-500 bg-red-50' : 'border-gray-200 hover:border-red-200'">
                                <input type="radio" name="difficulty" value="hard" x-model="difficulty" class="sr-only">
                                <span class="h-4 w-4 mr-2 rounded-full" :class="difficulty === 'hard' ? 'bg-red-500' : 'bg-gray-200'"></span>
                                <span>Hard</span>
                            </label>
                        </div>
                        <div class="mt-2">
                            <label class="inline-flex items-center p-3 border rounded-lg cursor-pointer transition-all w-full"
                                   :class="difficulty === 'mixed' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-blue-200'">
                                <input type="radio" name="difficulty" value="mixed" x-model="difficulty" class="sr-only">
                                <span class="h-4 w-4 mr-2 rounded-full" :class="difficulty === 'mixed' ? 'bg-blue-500' : 'bg-gray-200'"></span>
                                <span>Mixed (Recommended)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Category Selection -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">
                            Select Categories 
                            <span class="text-sm text-gray-500">(at least one required)</span>
                        </label>
                        
                        <div x-show="categoryError" class="text-red-500 text-sm mb-2">
                            Please select at least one category
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                            <?php foreach ($categories as $category): ?>
                                <label class="inline-flex items-center p-3 border rounded-lg cursor-pointer transition-all"
                                       :class="selectedCategories.includes('<?php echo $category['id']; ?>') ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-indigo-200'">
                                    <input type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>" 
                                           @change="updateCategories" 
                                           :checked="selectedCategories.includes('<?php echo $category['id']; ?>')"
                                           class="form-checkbox h-5 w-5 text-indigo-600 rounded">
                                    <span class="ml-2"><?php echo htmlspecialchars($category['name']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Start Quiz Button -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-lg transition duration-150 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0 0 10 9.87v4.263a1 1 0 0 0 1.555.832l3.197-2.132a1 1 0 0 0 0-1.664z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                        </svg>
                        Start Quiz
                    </button>
                </div>
            </div>

            <!-- Recommendations Panel -->
            <div class="md:col-span-1">
                <?php if (!empty($recommendations)): ?>
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <h2 class="text-xl font-semibold mb-4">Recommended for You</h2>
                        
                        <div class="space-y-4">
                            <?php foreach ($recommendations as $recommendation): ?>
                                <div class="border rounded-lg p-3 <?php echo $recommendation['type'] === 'improvement' ? 'border-amber-200 bg-amber-50' : ($recommendation['type'] === 'new' ? 'border-blue-200 bg-blue-50' : 'border-green-200 bg-green-50'); ?>">
                                    <h3 class="font-medium mb-1 <?php echo $recommendation['type'] === 'improvement' ? 'text-amber-800' : ($recommendation['type'] === 'new' ? 'text-blue-800' : 'text-green-800'); ?>">
                                        <?php echo htmlspecialchars($recommendation['title']); ?>
                                    </h3>
                                    <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($recommendation['description']); ?></p>
                                    
                                    <div class="space-y-1">
                                        <?php foreach ($recommendation['categories'] as $category): ?>
                                            <button type="button" 
                                                    @click="selectCategory('<?php echo $category['id']; ?>')"
                                                    class="block w-full text-left px-2 py-1 rounded text-sm hover:bg-white transition-all">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                                <?php if (isset($category['accuracy'])): ?>
                                                    <span class="float-right font-medium <?php echo $category['accuracy'] < 60 ? 'text-red-600' : 'text-green-600'; ?>">
                                                        <?php echo round($category['accuracy']); ?>%
                                                    </span>
                                                <?php endif; ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold mb-4">Quiz Tips</h2>
                    
                    <div class="space-y-4">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <p class="ml-2 text-sm text-gray-600">Mixed difficulty quizzes help you learn more effectively by challenging you at different levels.</p>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <p class="ml-2 text-sm text-gray-600">For best results, take quizzes regularly across different categories.</p>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <p class="ml-2 text-sm text-gray-600">Your performance is tracked to provide personalized recommendations.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    function quizSelectApp() {
        return {
            quizType: 'standard',
            numQuestions: 10,
            difficulty: 'mixed',
            selectedCategories: [],
            categoryError: false,
            
            updateCategories(event) {
                if (event.target.checked) {
                    this.selectedCategories.push(event.target.value);
                } else {
                    this.selectedCategories = this.selectedCategories.filter(id => id !== event.target.value);
                }
                this.categoryError = this.selectedCategories.length === 0;
            },
            
            selectCategory(categoryId) {
                if (!this.selectedCategories.includes(categoryId)) {
                    this.selectedCategories.push(categoryId);
                    const checkbox = document.querySelector(`input[name="categories[]"][value="${categoryId}"]`);
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                    this.categoryError = false;
                }
            },
            
            validateForm() {
                this.categoryError = this.selectedCategories.length === 0;
                return !this.categoryError;
            }
        }
    }
</script>