<?php
/**
 * Admin Bulk User Management Page
 * 
 * This page allows administrators to:
 * - Select multiple users based on criteria
 * - Perform bulk actions on selected users
 * - Send messages or notifications to groups of users
 * - Export user data in bulk
 */

require_once '../config.php';
$pageTitle = 'Bulk User Management';

// Ensure user is logged in and is an admin
requireAdmin();

// Initialize variables
$message = '';
$messageType = '';
$selectedUsers = [];

/**
 * Process bulk actions
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get action and user IDs
    $action = $_POST['action'] ?? '';
    $userIds = isset($_POST['user_ids']) ? $_POST['user_ids'] : [];
    
    // For Select All option
    if (isset($_POST['select_all']) && $_POST['select_all'] == 1) {
        // Apply filters to get all matching user IDs
        $activityFilter = isset($_POST['activity_filter']) ? $_POST['activity_filter'] : 'all';
        $performanceFilter = isset($_POST['performance_filter']) ? $_POST['performance_filter'] : 'all';
        
        // Build query to get all user IDs matching criteria
        $whereClause = [];
        $params = [];
        
        // Activity filter
        if ($activityFilter !== 'all') {
            switch ($activityFilter) {
                case 'active':
                    // Active in the last 30 days
                    $whereClause[] = "EXISTS (SELECT 1 FROM user_attempts ua WHERE ua.user_id = u.id AND ua.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY))";
                    break;
                case 'inactive':
                    // No activity in the last 30 days
                    $whereClause[] = "NOT EXISTS (SELECT 1 FROM user_attempts ua WHERE ua.user_id = u.id AND ua.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY))";
                    break;
                case 'new':
                    // Joined in the last 7 days
                    $whereClause[] = "u.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
            }
        }
        
        // Performance filter
        if ($performanceFilter !== 'all') {
            // Join with attempts to get performance data
            $performanceJoin = "LEFT JOIN (
                SELECT user_id, AVG(correct_answers/total_questions) * 100 as avg_score 
                FROM user_attempts 
                WHERE total_questions > 0 
                GROUP BY user_id
            ) as perf ON u.id = perf.user_id";
            
            switch ($performanceFilter) {
                case 'high':
                    $whereClause[] = "(perf.avg_score >= 80 OR perf.avg_score IS NULL)";
                    break;
                case 'medium':
                    $whereClause[] = "(perf.avg_score >= 50 AND perf.avg_score < 80)";
                    break;
                case 'low':
                    $whereClause[] = "(perf.avg_score < 50)";
                    break;
            }
        } else {
            $performanceJoin = "";
        }
        
        // Build WHERE clause
        $whereStr = empty($whereClause) ? "" : "WHERE " . implode(" AND ", $whereClause);
        
        // Get all matching user IDs
        $sql = "SELECT u.id FROM users u $performanceJoin $whereStr";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }
    
    if (!empty($userIds) && !empty($action)) {
        try {
            $pdo->beginTransaction();
            $actionCount = 0;
            
            switch ($action) {
                case 'verify_accounts':
                    $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = ? AND is_verified = 0");
                    foreach ($userIds as $userId) {
                        $stmt->execute([$userId]);
                        $actionCount += $stmt->rowCount();
                    }
                    $message = "$actionCount user accounts have been verified.";
                    $messageType = "success";
                    break;
                
                case 'reset_stats':
                    $actionCount = count($userIds);
                    
                    // Delete quiz attempts
                    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                    
                    $stmt = $pdo->prepare("DELETE FROM user_attempts WHERE user_id IN ($placeholders)");
                    $stmt->execute($userIds);
                    
                    // Delete quiz answers
                    $stmt = $pdo->prepare("DELETE FROM quiz_answers WHERE user_id IN ($placeholders)");
                    $stmt->execute($userIds);
                    
                    // Delete user question status (ratings)
                    $stmt = $pdo->prepare("DELETE FROM user_question_status WHERE user_id IN ($placeholders)");
                    $stmt->execute($userIds);
                    
                    $message = "Statistics for $actionCount users have been reset successfully.";
                    $messageType = "success";
                    break;
                
                case 'export_data':
                    // This will be handled differently - see below
                    break;
                
                case 'send_message':
                    // This would integrate with your messaging/notification system
                    // For this example, we'll just simulate a message being sent
                    $messageSubject = trim($_POST['message_subject'] ?? '');
                    $messageContent = trim($_POST['message_content'] ?? '');
                    
                    if (empty($messageSubject) || empty($messageContent)) {
                        throw new Exception("Message subject and content are required.");
                    }
                    
                    // Here you would actually send the message via email, in-app notification, etc.
                    // For now, we'll just log it
                    $actionCount = count($userIds);
                    $userList = implode(',', $userIds);
                    error_log("Admin message sent to users: $userList. Subject: $messageSubject");
                    
                    $message = "Message sent to $actionCount users successfully.";
                    $messageType = "success";
                    break;
                
                default:
                    throw new Exception("Invalid action selected.");
            }
            
            $pdo->commit();
            
            // Special handling for export
            if ($action === 'export_data') {
                // For security in a real app, you would:
                // 1. Generate the export
                // 2. Store it securely (time-limited)
                // 3. Email the admin a secure download link
                // For this example, we'll just provide a success message
                $actionCount = count($userIds);
                $message = "Data export for $actionCount users has been scheduled. You will receive an email with the download link shortly.";
                $messageType = "success";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
            // Log the error for debugging
            error_log("Bulk user management error: " . $e->getMessage());
        }
    } else {
        $message = "Please select users and an action to proceed.";
        $messageType = "error";
    }
}

/**
 * Get users to display in the list
 */
try {
    // Query to get a list of all users for selection
    $sql = "SELECT u.id, u.email, u.first_name, u.last_name, u.created_at, u.is_verified, u.is_admin,
           (SELECT COUNT(*) FROM user_attempts ua WHERE ua.user_id = u.id) as attempt_count,
           (SELECT MAX(ua2.created_at) FROM user_attempts ua2 WHERE ua2.user_id = u.id) as last_activity
           FROM users u
           ORDER BY u.created_at DESC
           LIMIT 1000"; // Limit for performance
    
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
    $messageType = "error";
    $users = [];
    // Log the error for debugging
    error_log("Error fetching users for bulk management: " . $e->getMessage());
}

include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Page header with title and back button -->
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Bulk User Management</h1>
        <a href="/admin/users.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition duration-150">
            Back to User Management
        </a>
    </div>
    
    <!-- Display success/error messages -->
    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Bulk Action Form -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6" x-data="{ 
        selectedAction: '',
        messageFormVisible: false,
        selectAll: false,
        selectedUsers: [],
        activityFilter: 'all',
        performanceFilter: 'all'
    }">
        <h2 class="text-xl font-bold mb-6">Bulk Actions</h2>
        
        <form method="POST" action="">
            <!-- Filtering Options -->
            <div class="bg-gray-50 p-4 rounded-md mb-6">
                <h3 class="text-md font-semibold mb-3">Filter Users</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Activity</label>
                        <select x-model="activityFilter" name="activity_filter" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                            <option value="all">All Users</option>
                            <option value="active">Active (Last 30 Days)</option>
                            <option value="inactive">Inactive</option>
                            <option value="new">New Users (Last 7 Days)</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Performance</label>
                        <select x-model="performanceFilter" name="performance_filter" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                            <option value="all">All Performance</option>
                            <option value="high">High (80%+)</option>
                            <option value="medium">Medium (50-79%)</option>
                            <option value="low">Low (Below 50%)</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Select Action -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Select Action</label>
                <select x-model="selectedAction" name="action" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    <option value="">-- Select an action --</option>
                    <option value="verify_accounts">Verify Selected Accounts</option>
                    <option value="reset_stats">Reset Statistics</option>
                    <option value="export_data">Export User Data</option>
                    <option value="send_message">Send Message/Notification</option>
                </select>
            </div>
            
            <!-- Messaging Form (conditional) -->
            <div x-show="selectedAction === 'send_message'" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 class="mb-6 bg-blue-50 p-4 rounded-md">
                <h3 class="text-md font-semibold mb-3">Message Details</h3>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <input type="text" name="message_subject" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" 
                           placeholder="Enter message subject">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Message Content</label>
                    <textarea name="message_content" rows="4" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                              placeholder="Enter message content"></textarea>
                </div>
            </div>
            
            <!-- Warning for Reset Stats -->
            <div x-show="selectedAction === 'reset_stats'" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 class="mb-6 bg-red-50 p-4 rounded-md border-l-4 border-red-500">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Warning</h3>
                        <div class="mt-1 text-sm text-red-700">
                            <p>This action will permanently delete all quiz attempts, answers, and performance history for the selected users. This action cannot be undone.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- User Selection Table -->
            <div class="mb-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-md font-semibold">Select Users</h3>
                    
                    <div class="flex items-center">
                        <input type="checkbox" id="select-all" x-model="selectAll" name="select_all" value="1" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                        <label for="select-all" class="ml-2 text-sm text-gray-700">Select All Matching Users</label>
                    </div>
                </div>
                
                <div class="bg-white rounded-md shadow overflow-x-auto max-h-96">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Select</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>" 
                                               :checked="selectAll"
                                               x-model="selectedUsers"
                                               class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user['email']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php echo $user['is_verified'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                            <?php echo $user['is_verified'] ? 'Verified' : 'Unverified'; ?>
                                        </span>
                                        <?php if ($user['is_admin']): ?>
                                            <span class="ml-1 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">
                                                Admin
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500">
                                            <?php echo $user['attempt_count']; ?> quizzes
                                            <?php if (!empty($user['last_activity'])): ?>
                                                <br>
                                                <span class="text-xs">Last: <?php echo date('M j, Y', strtotime($user['last_activity'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500">
                                            <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                        No users found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Selected Count -->
                <div class="mt-2 text-sm text-gray-500">
                    <span x-text="selectAll ? 'All matching users selected' : selectedUsers.length + ' users selected'"></span>
                </div>
            </div>
            
            <!-- Submit Button -->
            <div class="flex justify-end">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        x-bind:disabled="(selectedAction === '' || (selectedUsers.length === 0 && !selectAll))">
                    Apply Action
                </button>
            </div>
        </form>
    </div>
    
    <!-- Help Section -->
    <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
        <h3 class="text-lg font-medium text-blue-800 mb-2">Bulk User Management</h3>
        <ul class="list-disc list-inside text-blue-700 space-y-1">
            <li>Use the filters to narrow down which users you want to act on</li>
            <li>Check "Select All Matching Users" to include all users that match your filters</li>
            <li>Alternatively, manually select individual users from the list</li>
            <li>Choose an action to perform on the selected users</li>
            <li>The "Send Message" option can be used for announcements or reminders</li>
            <li>Use "Reset Statistics" with caution as this permanently removes user progress</li>
        </ul>
    </div>
</div>

<?php include '../includes/footer.php'; ?>