<!-- app/views/admin/questions.php -->
<div>
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-800">Questions</h2>
            
            <!-- Add Question Button -->
            <a href="/admin/question-form" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-md flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add Question
            </a>
        </div>
        
        <!-- Filter Form -->
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <form method="GET" action="/admin/questions" class="flex flex-wrap items-center gap-4">
                <!-- Category Filter -->
                <div class="w-full sm:w-auto">
                    <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select id="category" name="category" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $categoryId == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Search Box -->
                <div class="w-full sm:w-auto">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search questions..." 
                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                
                <!-- Submit Button -->
                <div class="w-full sm:w-auto mt-4 sm:mt-0 sm:self-end">
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-md">
                        Apply Filters
                    </button>
                </div>
                
                <!-- Reset Button -->
                <div class="w-full sm:w-auto sm:self-end">
                    <a href="/admin/questions" class="block text-center w-full bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded-md">
                        Reset
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Questions Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Question
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Category
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Type
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Difficulty
                        </th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($questions)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                No questions found. Click the "Add Question" button to create one.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($questions as $question): ?>
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 max-w-md truncate">
                                        <?php echo htmlspecialchars($question['question_text']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($question['category_name']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full
                                          <?php echo $question['question_type'] === 'multiple_choice' || empty($question['question_type']) ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'; ?>">
                                        <?php echo $question['question_type'] === 'multiple_choice' || empty($question['question_type']) ? 'Multiple Choice' : 'Written Response'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full
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
                                        ?> (<?php echo $question['difficulty_value']; ?>)
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div x-data="{ isActive: <?php echo $question['is_active'] ? 'true' : 'false'; ?> }">
                                        <label class="flex items-center justify-center cursor-pointer">
                                            <div class="relative">
                                                <input type="checkbox" class="sr-only" 
                                                       x-model="isActive" 
                                                       @change="toggleQuestion(<?php echo $question['id']; ?>, isActive)">
                                                <div class="block bg-gray-300 w-10 h-6 rounded-full"></div>
                                                <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform" 
                                                     :class="{'transform translate-x-4': isActive}"></div>
                                            </div>
                                        </label>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="/admin/question-form?id=<?php echo $question['id']; ?>" class="text-indigo-600 hover:text-indigo-900">
                                        Edit
                                    </a>
                                    <button @click="confirmDelete(<?php echo $question['id']; ?>)" class="text-red-600 hover:text-red-900 ml-3">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex items-center justify-between">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($currentPage > 1): ?>
                        <a href="/admin/questions?category=<?php echo $categoryId; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $currentPage - 1; ?>" 
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Previous
                        </a>
                    <?php endif; ?>
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="/admin/questions?category=<?php echo $categoryId; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $currentPage + 1; ?>" 
                           class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing page <span class="font-medium"><?php echo $currentPage; ?></span> of <span class="font-medium"><?php echo $totalPages; ?></span>
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($currentPage > 1): ?>
                                <a href="/admin/questions?category=<?php echo $categoryId; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $currentPage - 1; ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Previous</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php endif; ?>
                            
                            <?php 
                            // Show a limited number of page links
                            $startPage = max(1, $currentPage - 2);
                            $endPage = min($totalPages, $currentPage + 2);
                            
                            if ($startPage > 1) {
                                echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                            }
                            
                            for ($i = $startPage; $i <= $endPage; $i++): 
                            ?>
                                <?php if ($i == $currentPage): ?>
                                    <span class="relative inline-flex items-center px-4 py-2 border border-indigo-500 bg-indigo-50 text-sm font-medium text-indigo-600">
                                        <?php echo $i; ?>
                                    </span>
                                <?php else: ?>
                                    <a href="/admin/questions?category=<?php echo $categoryId; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($endPage < $totalPages) {
                                echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                            } ?>
                            
                            <?php if ($currentPage < $totalPages): ?>
                                <a href="/admin/questions?category=<?php echo $categoryId; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $currentPage + 1; ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Next</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10l-3.293-3.293a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div x-data="{ 
            showDeleteModal: false,
            questionId: null,
            
            confirmDelete(id) {
                this.questionId = id;
                this.showDeleteModal = true;
            },
            
            deleteQuestion() {
                fetch('/admin/delete-question', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `question_id=${this.questionId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                    this.showDeleteModal = false;
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the question.');
                    this.showDeleteModal = false;
                });
            }
        }">
        
        <div x-show="showDeleteModal" 
             class="fixed z-10 inset-0 overflow-y-auto" 
             style="display: none;"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform scale-95"
                     x-transition:enter-end="opacity-100 transform scale-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 transform scale-100"
                     x-transition:leave-end="opacity-0 transform scale-95">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    Delete Question
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        Are you sure you want to delete this question? This action cannot be undone.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" @click="deleteQuestion()" 
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Delete
                        </button>
                        <button type="button" @click="showDeleteModal = false" 
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleQuestion(questionId, isActive) {
        // Send AJAX request to toggle question status
        fetch('/admin/toggle-question', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `question_id=${questionId}&status=${isActive}`
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert(data.message);
                // Reset the toggle if the update failed
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating question status.');
            location.reload();
        });
    }
</script>

<style>
    /* Custom styles for the toggle switch */
    .dot {
        transition: all 0.3s ease-in-out;
    }
    input:checked ~ .dot {
        transform: translateX(100%);
    }
    input:checked ~ .block {
        background-color: #4F46E5;
    }
</style>