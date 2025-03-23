<?php
/**
 * Admin Bulk User Actions Page
 * 
 * This page allows administrators to:
 * - Select multiple users based on criteria
 * - Perform bulk actions like reset statistics, export data, etc.
 * - Preview selected users before executing actions
 */

require_once '../config.php';
$pageTitle = 'Bulk User Actions';

// Ensure user is logged in and is an admin
requireAdmin();

// Initialize variables
$message = '';
$messageType = '';
$selectedUsers = [];
$totalSelected = 0;

/**
 * Handle form submissions
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Action selection form
    if (isset($_POST['select_users'])) {
        $activityFilter = $_POST['activity_filter'] ?? 'all';
        $performanceFilter = $_POST['performance_filter'] ?? 'all';
        $verificationFilter = $_POST['verification_filter'] ?? 'all';
        $dateRange = $_POST['date_range'] ?? 'all';
        
        // Build SQL query based on filters
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
            }
        }
        
        // Performance filter (requires join with attempts)
        $performanceJoin = "";
        if ($performanceFilter !== 'all') {
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
        }
        
        // Verification filter
        if ($verificationFilter !== 'all') {
            $whereClause[] = "u.is_verified = " . ($verificationFilter === 'verified' ? '1' : '0');
        }
        
        // Date range filter
        if ($dateRange !== 'all') {
            switch ($dateRange) {
                case 'last_7_days':
                    $whereClause[] = "u.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case 'last_30_days':
                    $whereClause[] = "u.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
                case 'last_90_days':
                    $whereClause[] = "u.created_at > DATE_SUB(NOW(), INTERVAL 90 DAY)";
                    break;
            }
        }
        
        // Don't include current admin to prevent self-actions
        $whereClause[] = "u.id != " . $_SESSION['user_id'];
        
        // Build query
        $whereStr = empty($whereClause) ? "" : "WHERE " . implode(" AND ", $whereClause);
        $sql = "SELECT u.* FROM users u $performanceJoin $whereStr ORDER BY u.created_at DESC";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $selectedUsers = $stmt->fetchAll();
            $totalSelected = count($selectedUsers);
            
            // Add statistics for each user
            foreach ($selectedUsers as &$user) {
                // Get quiz attempts
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_attempts WHERE user_id = ?");
                $stmt->execute([$user['id']]);
                $user['total_attempts'] = $stmt->fetchColumn();
                
                // Get average score
                $stmt = $pdo->prepare("
                    SELECT AVG(correct_answers/total_questions) * 100 as avg_score 
                    FROM user_attempts 
                    WHERE user_id = ? AND total_questions > 0
                ");
                $stmt->execute([$user['id']]);
                $user['avg_score'] = round($stmt->fetchColumn() ?: 0);
                
                // Get last activity
                $stmt = $pdo->prepare("
                    SELECT created_at 
                    FROM user_attempts 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $stmt->execute([$user['id']]);
                $user['last_activity'] = $stmt->fetchColumn();
            }
            
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $messageType = "error";
            error_log("Bulk actions user selection error: " . $e->getMessage());
        }
    }
    
    // Action execution form
    elseif (isset($_POST['execute_action'])) {
        $action = $_POST['action'] ?? '';
        $userIds = isset($_POST['user_ids']) ? explode(',', $_POST['user_ids']) : [];
        
        if (empty($userIds)) {
            $message = "No users selected for the action.";
            $messageType = "error";
        } 
        else {
            try {
                switch ($action) {
                    case 'reset_stats':
                        // Start a transaction
                        $pdo->beginTransaction();
                        
                        $resetCount = 0;
                        foreach ($userIds as $userId) {
                            // Delete quiz attempts
                            $stmt = $pdo->prepare("DELETE FROM user_attempts WHERE user_id = ?");
                            $stmt->execute([$userId]);
                            
                            // Delete quiz answers
                            $stmt = $pdo->prepare("DELETE FROM quiz_answers WHERE user_id = ?");
                            $stmt->execute([$userId]);
                            
                            // Delete user question status (ratings)
                            $stmt = $pdo->prepare("DELETE FROM user_question_status WHERE user_id = ?");
                            $stmt->execute([$userId]);
                            
                            $resetCount++;
                        }
                        
                        // Commit transaction
                        $pdo->commit();
                        
                        $message = "Statistics reset successful for $resetCount users.";
                        $messageType = "success";
                        break;
                    
                    case 'verify_users':
                        $verifiedCount = 0;
                        foreach ($userIds as $userId) {
                            $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = ? AND is_verified = 0");
                            $stmt->execute([$userId]);
                            if ($stmt->rowCount() > 0) {
                                $verifiedCount++;
                            }
                        }
                        
                        if ($verifiedCount > 0) {
                            $message = "$verifiedCount user(s) were successfully verified.";
                            $messageType = "success";
                        } else {
                            $message = "No users were verified. They may already be verified.";
                            $messageType = "info";
                        }
                        break;
                    
                    case 'export_data':
                        // This would normally generate a file download, but for simplicity, 
                        // we'll just simulate success
                        $message = "Data export feature is coming soon. Selected " . count($userIds) . " users.";
                        $messageType = "info";
                        break;
                    
                    default:
                        $message = "Unknown action specified.";
                        $messageType = "error";
                }
            } catch (PDOException $e) {
                // Rollback transaction if needed
                if ($action === 'reset_stats' && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                
                $message = "Error executing action: " . $e->getMessage();
                $messageType = "error";
                error_log("Bulk actions execution error: " . $e->getMessage());
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Page header with title and back button -->
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Bulk User Actions</h1>
        <a href="users.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition duration-150">
            Back to Users
        </a>
    </div>
    
    <!-- Display messages -->
    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : ($messageType === 'info' ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700'); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <!-- User Selection Form -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8">
        <h2 class="text-xl font-semibold mb-4">Select Users</h2>
        
        <form method="POST" action="">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Activity Filter -->
                <div>
                    <label for="activity_filter" class="block text-sm font-medium text-gray-700">Activity Level</label>
                    <select id="activity_filter" name="activity_filter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="all">All Users</option>
                        <option value="active">Active Users (last 30 days)</option>
                        <option value="inactive">Inactive Users (no activity in 30 days)</option>
                    </select>
                </div>
                
                <!-- Performance Filter -->
                <div>
                    <label for="performance_filter" class="block text-sm font-medium text-gray-700">Performance Level</label>
                    <select id="performance_filter" name="performance_filter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="all">All Performance Levels</option>
                        <option value="high">High Performers (80%+)</option>
                        <option value="medium">Medium Performers (50-79%)</option>
                        <option value="low">Low Performers (Below 50%)</option>
                    </select>
                </div>
                
                <!-- Verification Filter -->
                <div>
                    <label for="verification_filter" class="block text-sm font-medium text-gray-700">Verification Status</label>
                    <select id="verification_filter" name="verification_filter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="all">All Users</option>
                        <option value="verified">Verified Users</option>
                        <option value="unverified">Unverified Users</option>
                    </select>
                </div>
                
                <!-- Date Range Filter -->
                <div>
                    <label for="date_range" class="block text-sm font-medium text-gray-700">Registration Date</label>
                    <select id="date_range" name="date_range" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="all">All Time</option>
                        <option value="last_7_days">Last 7 Days</option>
                        <option value="last_30_days">Last 30 Days</option>
                        <option value="last_90_days">Last 90 Days</option>
                    </select>
                </div>
            </div>
            
            <div class="mt-6">
                <button type="submit" name="select_users" value="1" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Find Matching Users
                </button>
            </div>
        </form>
    </div>
    
    <!-- Preview Selected Users -->
    <?php if (!empty($selectedUsers)): ?>
        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Selected Users (<?php echo $totalSelected; ?>)</h2>
                
                <!-- Action Selection Form -->
                <form method="POST" action="" id="actionForm">
                    <input type="hidden" name="user_ids" value="<?php echo implode(',', array_column($selectedUsers, 'id')); ?>">
                    
                    <div class="flex space-x-3">
                        <select name="action" id="action" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                            <option value="">Select Action...</option>
                            <option value="reset_stats">Reset Statistics</option>
                            <option value="verify_users">Verify Users</option>
                            <option value="export_data">Export User Data</option>
                        </select>
                        
                        <button type="submit" name="execute_action" value="1" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Apply to Selected
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Users Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($selectedUsers as $user): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Joined: <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
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
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">
                                            Admin
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?php echo $user['total_attempts']; ?> quizzes
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php
                                        if (!empty($user['last_activity'])) {
                                            echo 'Last: ' . date('M j, Y', strtotime($user['last_activity']));
                                        } else {
                                            echo 'No activity';
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($user['total_attempts'] > 0): ?>
                                        <div class="flex items-center">
                                            <div class="w-16 bg-gray-200 rounded-full h-2.5 mr-2">
                                                <div class="h-2.5 rounded-full 
                                                    <?php echo
                                                        $user['avg_score'] >= 80 ? 'bg-green-600' :
                                                        ($user['avg_score'] >= 50 ? 'bg-yellow-500' : 'bg-red-500');
                                                    ?>" 
                                                    style="width: <?php echo min(100, $user['avg_score']); ?>%">
                                                </div>
                                            </div>
                                            <div class="text-sm font-medium 
                                                <?php echo
                                                    $user['avg_score'] >= 80 ? 'text-green-600' :
                                                    ($user['avg_score'] >= 50 ? 'text-yellow-600' : 'text-red-600');
                                                ?>">
                                                <?php echo $user['avg_score']; ?>%
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-500">No data</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif (isset($_POST['select_users'])): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6" role="alert">
            <p>No users match the selected criteria. Please try different filters.</p>
        </div>
    <?php endif; ?>
    
    <!-- Help Section -->
    <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
        <h3 class="text-lg font-medium text-blue-800 mb-2">Bulk Action Help</h3>
        <ul class="list-disc list-inside text-blue-700 space-y-1">
            <li>Filter users by activity, performance, verification status, and registration date</li>
            <li>Preview matching users before executing actions</li>
            <li><strong>Reset Statistics</strong>: Removes all quiz attempts, answers, and ratings for selected users</li>
            <li><strong>Verify Users</strong>: Marks unverified accounts as verified</li>
            <li><strong>Export Data</strong>: Downloads user data in CSV format (coming soon)</li>
        </ul>
        <div class="mt-4 p-3 bg-white rounded-md text-sm">
            <p class="font-medium text-red-600 mb-1">Warning:</p>
            <p class="text-gray-700">Bulk actions cannot be undone. Double-check the user list before applying actions.</p>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Confirmation dialog for bulk actions
    const actionForm = document.getElementById('actionForm');
    if (actionForm) {
        actionForm.addEventListener('submit', function(e) {
            const action = document.getElementById('action').value;
            let confirmMessage = '';
            
            if (action === 'reset_stats') {
                confirmMessage = 'This will permanently delete all quiz history and progress for the selected users. This action cannot be undone. Continue?';
            } else if (action === 'verify_users') {
                confirmMessage = 'This will verify all selected users. Continue?';
            } else if (action === 'export_data') {
                // No confirmation needed for exports
                return true;
            } else {
                e.preventDefault();
                alert('Please select an action.');
                return false;
            }
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>