<!-- app/views/admin/categories.php -->
<div x-data="categoriesApp()">
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-800">Categories</h2>
            
            <!-- Add Category Button -->
            <button @click="showAddModal = true" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2 px-4 rounded-md flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add Category
            </button>
        </div>
        
        <!-- Categories Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Name
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Description
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Questions
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                No categories found. Click the "Add Category" button to create one.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-500 max-w-md truncate">
                                        <?php echo htmlspecialchars($category['description'] ?? ''); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <label class="flex items-center cursor-pointer">
                                            <div class="relative">
                                                <input type="checkbox" class="sr-only" 
                                                       <?php echo $category['is_active'] ? 'checked' : ''; ?> 
                                                       @change="toggleCategory(<?php echo $category['id']; ?>, $event.target.checked)">
                                                <div class="block bg-gray-300 w-10 h-6 rounded-full"></div>
                                                <div class="dot absolute left-1 top-1 bg-white w-4 h-4 rounded-full transition-transform" 
                                                     :class="{'transform translate-x-4': <?php echo $category['is_active'] ? 'true' : 'false'; ?>}"></div>
                                            </div>
                                            <div class="ml-3 text-sm text-gray-700">
                                                <?php echo $category['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </div>
                                        </label>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php 
                                    // Get question count for this category (if available)
                                    $questionCount = isset($category['question_count']) ? $category['question_count'] : '?';
                                    ?>
                                    <a href="/admin/questions?category=<?php echo $category['id']; ?>" class="text-indigo-600 hover:text-indigo-900">
                                        View Questions
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button @click="editCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars(addslashes($category['name'])); ?>', '<?php echo htmlspecialchars(addslashes($category['description'] ?? '')); ?>', <?php echo $category['is_active'] ? 'true' : 'false'; ?>)" 
                                            class="text-indigo-600 hover:text-indigo-900">
                                        Edit
                                    </button>
                                    <button @click="confirmDelete(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars(addslashes($category['name'])); ?>')" 
                                            class="text-red-600 hover:text-red-900 ml-3">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Add/Edit Category Modal -->
    <div x-show="showAddModal || showEditModal" 
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
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                <span x-text="showAddModal ? 'Add Category' : 'Edit Category'"></span>
                            </h3>
                            <div class="mt-4 space-y-4">
                                <!-- Form Fields -->
                                <div>
                                    <label for="category-name" class="block text-sm font-medium text-gray-700">Name</label>
                                    <input type="text" id="category-name" x-model="categoryName" 
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <p x-show="nameError" x-text="nameError" class="mt-1 text-sm text-red-600"></p>
                                </div>
                                <div>
                                    <label for="category-description" class="block text-sm font-medium text-gray-700">Description</label>
                                    <textarea id="category-description" x-model="categoryDescription" rows="3" 
                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" id="category-active" x-model="categoryActive" 
                                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="category-active" class="ml-2 block text-sm text-gray-700">
                                        Active
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" @click="saveCategory()" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                        <span x-text="showAddModal ? 'Add' : 'Save'"></span>
                    </button>
                    <button type="button" @click="cancelEdit()" 
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
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
                                Delete Category
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    Are you sure you want to delete the category <span x-text="deleteCategoryName" class="font-medium"></span>? This action cannot be undone.
                                </p>
                                <p class="text-sm text-gray-500 mt-2">
                                    If this category has questions, you will need to delete or reassign them first.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" @click="deleteCategory()" 
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

<script>
    function categoriesApp() {
        return {
            // Add/Edit Modal
            showAddModal: false,
            showEditModal: false,
            editCategoryId: null,
            categoryName: '',
            categoryDescription: '',
            categoryActive: true,
            nameError: '',
            
            // Delete Modal
            showDeleteModal: false,
            deleteCategoryId: null,
            deleteCategoryName: '',
            
            // Edit Category
            editCategory(id, name, description, isActive) {
                this.editCategoryId = id;
                this.categoryName = name;
                this.categoryDescription = description;
                this.categoryActive = isActive;
                this.nameError = '';
                this.showEditModal = true;
            },
            
            // Cancel Edit/Add
            cancelEdit() {
                this.showAddModal = false;
                this.showEditModal = false;
                this.resetForm();
            },
            
            // Reset Form
            resetForm() {
                this.editCategoryId = null;
                this.categoryName = '';
                this.categoryDescription = '';
                this.categoryActive = true;
                this.nameError = '';
            },
            
            // Toggle Category Status
            toggleCategory(categoryId, isActive) {
                // Send AJAX request to toggle category status
                fetch('/admin/toggle-category', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `category_id=${categoryId}&status=${isActive}`
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
                    alert('An error occurred while updating category status.');
                    location.reload();
                });
            },
            
            // Save Category (Add/Edit)
            saveCategory() {
                // Validate name
                if (!this.categoryName.trim()) {
                    this.nameError = 'Category name is required';
                    return;
                }
                
                const formData = new FormData();
                
                if (this.showEditModal) {
                    // Edit mode
                    formData.append('id', this.editCategoryId);
                }
                
                formData.append('name', this.categoryName);
                formData.append('description', this.categoryDescription);
                
                if (this.categoryActive) {
                    formData.append('is_active', '1');
                }
                
                // Send AJAX request
                fetch(this.showAddModal ? '/admin/create-category' : '/admin/update-category', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload the page to show the updated list
                        location.reload();
                    } else {
                        this.nameError = data.message;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while saving the category.');
                });
            },
            
            // Confirm Delete Category
            confirmDelete(categoryId, categoryName) {
                this.deleteCategoryId = categoryId;
                this.deleteCategoryName = categoryName;
                this.showDeleteModal = true;
            },
            
            // Delete Category
            deleteCategory() {
                // Send AJAX request to delete category
                fetch('/admin/delete-category', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `category_id=${this.deleteCategoryId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload the page to show the updated list
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                    this.showDeleteModal = false;
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the category.');
                    this.showDeleteModal = false;
                });
            }
        };
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