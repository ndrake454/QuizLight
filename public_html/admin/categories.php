<?php
/**
 * Admin Category Management Page
 * 
 * This page allows administrators to:
 * - View all categories
 * - Add new categories
 * - Edit existing categories
 * - Delete categories (if they don't contain questions)
 */

require_once '../config.php';
$pageTitle = 'Manage Categories';

// Ensure user is logged in and is an admin
requireAdmin();

// Initialize variables
$message = '';
$messageType = '';

/**
 * Handle category actions (add, edit, delete)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ADD CATEGORY
    if ($action === 'add_category') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name)) {
            $message = "Category name is required.";
            $messageType = "error";
        } else {
            try {
                // Check if category exists
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
                $stmt->execute([$name]);
                
                if ($stmt->rowCount() > 0) {
                    $message = "A category with this name already exists.";
                    $messageType = "error";
                } else {
                    // Add new category
                    $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                    $stmt->execute([$name, $description]);
                    
                    $message = "Category added successfully.";
                    $messageType = "success";
                }
            } catch (PDOException $e) {
                $message = "Database error: " . $e->getMessage();
                $messageType = "error";
                // Log the error for debugging
                error_log("Admin category add error: " . $e->getMessage());
            }
        }
    } 
    // EDIT CATEGORY
    elseif ($action === 'edit_category') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (empty($name) || $id <= 0) {
            $message = "Category name and ID are required.";
            $messageType = "error";
        } else {
            try {
                // Check if another category with this name exists
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
                $stmt->execute([$name, $id]);
                
                if ($stmt->rowCount() > 0) {
                    $message = "A category with this name already exists.";
                    $messageType = "error";
                } else {
                    // Update category
                    $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $id]);
                    
                    $message = "Category updated successfully.";
                    $messageType = "success";
                }
            } catch (PDOException $e) {
                $message = "Database error: " . $e->getMessage();
                $messageType = "error";
                // Log the error for debugging
                error_log("Admin category edit error: " . $e->getMessage());
            }
        }
    } 
    // DELETE CATEGORY
    elseif ($action === 'delete_category') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($id <= 0) {
            $message = "Invalid category ID.";
            $messageType = "error";
        } else {
            try {
                // Check if the category has questions
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE category_id = ?");
                $stmt->execute([$id]);
                $questionCount = $stmt->fetchColumn();
                
                if ($questionCount > 0) {
                    $message = "Cannot delete this category because it contains questions. Please delete or reassign the questions first.";
                    $messageType = "error";
                } else {
                    // Delete category
                    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    $message = "Category deleted successfully.";
                    $messageType = "success";
                }
            } catch (PDOException $e) {
                $message = "Database error: " . $e->getMessage();
                $messageType = "error";
                // Log the error for debugging
                error_log("Admin category delete error: " . $e->getMessage());
            }
        }
    }
}

/**
 * Fetch all categories with question counts
 */
try {
    $stmt = $pdo->query("SELECT c.*, 
                         (SELECT COUNT(*) FROM questions q WHERE q.category_id = c.id) AS question_count 
                         FROM categories c 
                         ORDER BY c.name ASC");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
    $messageType = "error";
    $categories = [];
    // Log the error for debugging
    error_log("Admin category listing error: " . $e->getMessage());
}

include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Page header with title and back button -->
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Manage Categories</h1>
        <a href="/admin/" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition duration-150">
            Back to Dashboard
        </a>
    </div>
    
    <!-- Display success/error messages -->
    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Add New Category Form -->
<div class="bg-white rounded-lg shadow-md p-6 mb-8" x-data="{ isOpen: false }">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-semibold">Add New Category</h2>
        
        <!-- Improved button styling instead of text link -->
        <button @click="isOpen = !isOpen" 
                class="px-4 py-2 rounded-md flex items-center transition-colors duration-200"
                :class="isOpen ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-indigo-100 text-indigo-700 hover:bg-indigo-200'">
            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      :d="isOpen ? 'M6 18L18 6M6 6l12 12' : 'M12 6v6m0 0v6m0-6h6m-6 0H6'"></path>
            </svg>
            <span x-show="!isOpen">Show Form</span>
            <span x-show="isOpen">Hide Form</span>
        </button>
    </div>
    
    <!-- Improved form with transitions -->
    <div x-show="isOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 transform scale-100"
         x-transition:leave-end="opacity-0 transform scale-95">
        
        <form action="" method="POST" class="space-y-5 bg-gray-50 p-5 rounded-lg border border-gray-200">
            <input type="hidden" name="action" value="add_category">
            
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Category Name</label>
                <input type="text" name="name" id="name" required 
                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-base py-3 px-4"
                       placeholder="Enter category name">
            </div>
            
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" id="description" rows="3" 
                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-base py-3 px-4"
                          placeholder="Enter category description (optional)"></textarea>
            </div>
            
            <div class="pt-2">
                <button type="submit" class="w-full flex justify-center items-center py-3 px-4 border border-transparent shadow-md text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Add Category
                </button>
            </div>
        </form>
    </div>
</div>
    
    <!-- Categories Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Questions</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $category): ?>
                        <tr x-data="{ isEditing: false }">
                            <td class="px-6 py-4">
                                <!-- Display mode -->
                                <div x-show="!isEditing" class="text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </div>
                                <!-- Edit mode -->
                                <div x-show="isEditing" class="text-sm">
                                    <input type="text" x-ref="editName" value="<?php echo htmlspecialchars($category['name']); ?>" 
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <!-- Display mode -->
                                <div x-show="!isEditing" class="text-sm text-gray-500">
                                    <?php echo !empty($category['description']) ? htmlspecialchars($category['description']) : '<em class="text-gray-400">No description</em>'; ?>
                                </div>
                                <!-- Edit mode -->
                                <div x-show="isEditing" class="text-sm">
                                    <textarea x-ref="editDescription" rows="2" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"><?php echo htmlspecialchars($category['description'] ?? ''); ?></textarea>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-500">
                                    <?php echo $category['question_count']; ?> questions
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm font-medium">
                                <!-- Display mode actions -->
                                <div x-show="!isEditing" class="flex space-x-3">
                                    <button @click="isEditing = true" class="text-indigo-600 hover:text-indigo-900 transition duration-150">Edit</button>
                                    
                                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this category? This action cannot be undone.');">
                                        <input type="hidden" name="action" value="delete_category">
                                        <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900 transition duration-150" 
                                                <?php echo $category['question_count'] > 0 ? 'disabled title="Cannot delete categories with questions"' : ''; ?>>
                                            Delete
                                        </button>
                                    </form>
                                </div>
                                
                                <!-- Edit mode actions (improved) -->
                                <div x-show="isEditing" class="flex space-x-3">
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="edit_category">
                                        <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                        <input type="hidden" x-ref="hiddenName" name="name">
                                        <input type="hidden" x-ref="hiddenDescription" name="description">
                                        
                                        <button type="submit" @click="$refs.hiddenName.value = $refs.editName.value; $refs.hiddenDescription.value = $refs.editDescription.value"
                                                class="px-4 py-2 bg-green-100 text-green-700 hover:bg-green-200 rounded-md transition-colors flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            Save
                                        </button>
                                    </form>
                                    
                                    <button @click="isEditing = false" class="px-4 py-2 bg-gray-100 text-gray-700 hover:bg-gray-200 rounded-md transition-colors flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                        Cancel
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">No categories found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Tips Section -->
    <div class="mt-8 bg-blue-50 rounded-lg p-4 border border-blue-200">
        <h3 class="text-lg font-medium text-blue-800 mb-2">Tips for Category Management</h3>
        <ul class="list-disc list-inside text-blue-700 space-y-1">
            <li>Categories are used to organize questions for quizzes</li>
            <li>Create clear, descriptive names for your categories</li>
            <li>You can't delete categories that contain questions</li>
            <li>Use descriptions to provide context about what the category covers</li>
            <li>Well-organized categories make it easier for users to find relevant quizzes</li>
        </ul>
    </div>
</div>

<?php include '../includes/footer.php'; ?>