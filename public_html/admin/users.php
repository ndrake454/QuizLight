<?php
/**
 * Enhanced Admin User Management Page
 * 
 * Features:
 * - View all users in the system
 * - Search and filter users by various criteria
 * - User progress statistics overview
 * - Verify unverified accounts
 * - Grant/revoke admin privileges
 * - Delete user accounts
 * - View detailed user analytics
 */

require_once '../config.php';
$pageTitle = 'Manage Users';

// Ensure user is logged in and is an admin
requireAdmin();

// Initialize variables
$message = '';
$messageType = '';

/**
 * Handle user management actions (verify, grant/revoke admin, delete, reset stats)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    
    if ($action && $userId) {
        try {
            switch ($action) {
                // Verify user account
                case 'verify':
                    $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = ?");
                    $stmt->execute([$userId]);
                    $message = "User has been verified.";
                    $messageType = "success";
                    break;
                
                // Grant admin privileges
                case 'make_admin':
                    $stmt = $pdo->prepare("UPDATE users SET is_admin = 1 WHERE id = ?");
                    $stmt->execute([$userId]);
                    $message = "User has been granted admin privileges.";
                    $messageType = "success";
                    break;
                
                // Revoke admin privileges
                case 'remove_admin':
                    // Don't allow removing admin status from own account
                    if ($userId == $_SESSION['user_id']) {
                        $message = "You cannot remove your own admin privileges.";
                        $messageType = "error";
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET is_admin = 0 WHERE id = ?");
                        $stmt->execute([$userId]);
                        $message = "Admin privileges have been removed.";
                        $messageType = "success";
                    }
                    break;
                
                // Reset user statistics
                case 'reset_stats':
                    // Start a transaction to ensure all or nothing is deleted
                    $pdo->beginTransaction();
                    
                    // Delete quiz attempts
                    $stmt = $pdo->prepare("DELETE FROM user_attempts WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    // Delete quiz answers
                    $stmt = $pdo->prepare("DELETE FROM quiz_answers WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    // Delete user question status (ratings)
                    $stmt = $pdo->prepare("DELETE FROM user_question_status WHERE user_id = ?");
                    $stmt->execute([$userId]);
                    
                    // Commit the transaction
                    $pdo->commit();
                    
                    $message = "User statistics have been reset successfully.";
                    $messageType = "success";
                    break;
                
                // Delete user account
                case 'delete':
                    // Don't allow deleting own account
                    if ($userId == $_SESSION['user_id']) {
                        $message = "You cannot delete your own account.";
                        $messageType = "error";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$userId]);
                        $message = "User has been deleted.";
                        $messageType = "success";
                    }
                    break;
                
                default:
                    $message = "Invalid action.";
                    $messageType = "error";
            }
        } catch (PDOException $e) {
            // Rollback if we were in a transaction
            if ($action === 'reset_stats' && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            $message = "Database error: " . $e->getMessage();
            $messageType = "error";
            // Log the error for debugging
            error_log("Admin user management error: " . $e->getMessage());
        }
    }
}

/**
 * Fetch users with search, filters, and pagination
 */
try {
    // Set up pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 15; // Number of users per page
    $offset = ($page - 1) * $perPage;
    
    // Get filter parameters
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $activityFilter = isset($_GET['activity']) ? $_GET['activity'] : 'all';
    $performanceFilter = isset($_GET['performance']) ? $_GET['performance'] : 'all';
    
    // Build the WHERE clause for filters
    $whereClause = [];
    $params = [];
    
    // Search filter
    if (!empty($search)) {
        $whereClause[] = "(u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
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
    
    // Build the final where clause string
    $whereStr = empty($whereClause) ? "" : "WHERE " . implode(" AND ", $whereClause);
    
    // Get total count for pagination
    $countSql = "SELECT COUNT(*) FROM users u $performanceJoin $whereStr";
    $stmt = $pdo->prepare($countSql);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $totalUsers = $stmt->fetchColumn();
    $totalPages = ceil($totalUsers / $perPage);
    
    // Get users for current page
    $sql = "SELECT u.* FROM users u $performanceJoin $whereStr ORDER BY u.created_at DESC LIMIT ? OFFSET ?";
    
    // Bind parameters (search + pagination)
    $finalParams = $params;
    $finalParams[] = $perPage;
    $finalParams[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($finalParams);
    $users = $stmt->fetchAll();
    
    // For each user, get their statistics
    foreach ($users as &$user) {
        // Get total quiz attempts
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
        
        // Get total questions answered
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_answers WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $user['total_questions'] = $stmt->fetchColumn();
        
        // Determine activity level
        if (empty($user['last_activity'])) {
            $user['activity_level'] = 'inactive';
        } else {
            $lastActivity = new DateTime($user['last_activity']);
            $now = new DateTime();
            $daysSinceActivity = $now->diff($lastActivity)->days;
            
            if ($daysSinceActivity <= 7) {
                $user['activity_level'] = 'high';
            } elseif ($daysSinceActivity <= 30) {
                $user['activity_level'] = 'medium';
            } else {
                $user['activity_level'] = 'low';
            }
        }
        
        // Determine performance level based on average score
        if ($user['avg_score'] >= 80) {
            $user['performance_level'] = 'high';
        } elseif ($user['avg_score'] >= 50) {
            $user['performance_level'] = 'medium';
        } else {
            $user['performance_level'] = 'low';
        }
    }
    
} catch (PDOException $e) {
    $message = "Database error: " . $e->getMessage();
    $messageType = "error";
    $users = [];
    $totalPages = 0;
    
    // Log the error for debugging
    error_log("Admin user listing error: " . $e->getMessage());
}

include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Page header with title and back button -->
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Manage Users</h1>
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
    
    <!-- Enhanced Search & Filter Form -->
    <div class="bg-white p-4 rounded-lg shadow-md mb-6">
        <form method="GET" action="" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="col-span-2">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search Users</label>
                    <div class="relative rounded-md shadow-sm">
                        <input type="text" name="search" id="search" 
                               class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-4 pr-12 sm:text-sm border-gray-300 rounded-md"
                               placeholder="Search by name or email..."
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <div class="absolute inset-y-0 right-0 flex items-center">
                            <button type="submit" class="p-2 text-indigo-600 hover:text-indigo-900">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label for="activity" class="block text-sm font-medium text-gray-700 mb-1">Activity</label>
                    <select id="activity" name="activity" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                        <option value="all" <?php echo (!isset($_GET['activity']) || $_GET['activity'] === 'all') ? 'selected' : ''; ?>>All Users</option>
                        <option value="active" <?php echo (isset($_GET['activity']) && $_GET['activity'] === 'active') ? 'selected' : ''; ?>>Active (Last 30 Days)</option>
                        <option value="inactive" <?php echo (isset($_GET['activity']) && $_GET['activity'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        <option value="new" <?php echo (isset($_GET['activity']) && $_GET['activity'] === 'new') ? 'selected' : ''; ?>>New Users (Last 7 Days)</option>
                    </select>
                </div>
                
                <div>
                    <label for="performance" class="block text-sm font-medium text-gray-700 mb-1">Performance</label>
                    <select id="performance" name="performance" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                        <option value="all" <?php echo (!isset($_GET['performance']) || $_GET['performance'] === 'all') ? 'selected' : ''; ?>>All Performance</option>
                        <option value="high" <?php echo (isset($_GET['performance']) && $_GET['performance'] === 'high') ? 'selected' : ''; ?>>High (80%+)</option>
                        <option value="medium" <?php echo (isset($_GET['performance']) && $_GET['performance'] === 'medium') ? 'selected' : ''; ?>>Medium (50-79%)</option>
                        <option value="low" <?php echo (isset($_GET['performance']) && $_GET['performance'] === 'low') ? 'selected' : ''; ?>>Low (Below 50%)</option>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-end">
                <a href="users.php" class="mr-2 px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Reset Filters
                </a>
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Apply Filters
                </button>
            </div>
        </form>
    </div>
    
    <!-- Overview Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-indigo-400">
            <h3 class="text-lg font-semibold text-gray-700">Total Users</h3>
            <p class="text-3xl font-bold text-indigo-600"><?php echo $totalUsers; ?></p>
        </div>
        
        <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-green-400">
            <h3 class="text-lg font-semibold text-gray-700">Active Users</h3>
            <?php
            $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM user_attempts WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $activeUsers = $stmt->fetchColumn();
            ?>
            <p class="text-3xl font-bold text-green-600"><?php echo $activeUsers; ?></p>
            <p class="text-sm text-gray-500">Last 30 days</p>
        </div>
        
        <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-yellow-400">
            <h3 class="text-lg font-semibold text-gray-700">Avg. Performance</h3>
            <?php
            $stmt = $pdo->query("
                SELECT AVG(correct_answers/total_questions) * 100 as overall_avg 
                FROM user_attempts 
                WHERE total_questions > 0
            ");
            $avgPerformance = round($stmt->fetchColumn() ?: 0);
            ?>
            <p class="text-3xl font-bold text-yellow-600"><?php echo $avgPerformance; ?>%</p>
        </div>
        
        <div class="bg-white p-4 rounded-lg shadow-md border-l-4 border-purple-400">
            <h3 class="text-lg font-semibold text-gray-700">New Users</h3>
            <?php
            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $newUsers = $stmt->fetchColumn();
            ?>
            <p class="text-3xl font-bold text-purple-600"><?php echo $newUsers; ?></p>
            <p class="text-sm text-gray-500">Last 7 days</p>
        </div>
    </div>
    
    <!-- Enhanced Users Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activity</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Performance</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $user): ?>
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
                                    <!-- Status badges for verification and admin status -->
                                    <div class="flex flex-col space-y-1">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php echo $user['is_verified'] ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                            <?php echo $user['is_verified'] ? 'Verified' : 'Unverified'; ?>
                                        </span>
                                        <?php if ($user['is_admin']): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">
                                                Admin
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <!-- Activity level indicator -->
                                        <div class="mr-2 flex-shrink-0 h-3 w-3 rounded-full 
                                            <?php echo
                                                $user['activity_level'] === 'high' ? 'bg-green-500' :
                                                ($user['activity_level'] === 'medium' ? 'bg-yellow-500' : 'bg-red-500');
                                            ?>">
                                        </div>
                                        <div>
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
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($user['total_attempts'] > 0): ?>
                                        <div class="flex items-center">
                                            <!-- Progress bar -->
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
                                        <div class="text-xs text-gray-500 mt-1">
                                            <?php echo $user['total_questions']; ?> questions
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-500">No data</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex flex-col space-y-2">
                                        <!-- View detailed stats -->
                                        <a href="user_progress.php?id=<?php echo $user['id']; ?>" class="text-indigo-600 hover:text-indigo-900 transition duration-150">
                                            View Stats
                                        </a>
                                        
                                        <!-- Action menu for each user -->
                                        <div x-data="{ open: false }" class="relative inline-block text-left">
                                            <div>
                                                <button @click="open = !open" type="button" class="inline-flex items-center text-sm text-gray-700 hover:text-gray-900">
                                                    Actions
                                                    <svg class="-mr-1 ml-1 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                    </svg>
                                                </button>
                                            </div>
                                            
                                            <div x-show="open" @click.away="open = false" class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 divide-y divide-gray-100 z-10">
                                                <div class="py-1">
                                                    <?php if (!$user['is_verified']): ?>
                                                        <!-- Verify user button -->
                                                        <form method="POST" action="">
                                                            <input type="hidden" name="action" value="verify">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 w-full text-left">
                                                                <svg class="mr-3 h-5 w-5 text-gray-400 group-hover:text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                                </svg>
                                                                Verify User
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!$user['is_admin']): ?>
                                                        <!-- Make admin button -->
                                                        <form method="POST" action="" onsubmit="return confirm('Make this user an admin? This will grant them full access to the admin panel.');">
                                                            <input type="hidden" name="action" value="make_admin">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 w-full text-left">
                                                                <svg class="mr-3 h-5 w-5 text-gray-400 group-hover:text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                                                </svg>
                                                                Make Admin
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <!-- Remove admin button -->
                                                        <form method="POST" action="" onsubmit="return confirm('Remove admin privileges from this user?');">
                                                            <input type="hidden" name="action" value="remove_admin">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 w-full text-left" 
                                                                    <?php echo $user['id'] == $_SESSION['user_id'] ? 'disabled title="You cannot remove your own admin privileges"' : ''; ?>>
                                                                <svg class="mr-3 h-5 w-5 text-gray-400 group-hover:text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                    <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd" />
                                                                </svg>
                                                                Remove Admin
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="py-1">
                                                    <!-- Reset stats button -->
                                                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to reset all statistics for this user? This will delete all quiz attempts and progress data. This action cannot be undone.');">
                                                        <input type="hidden" name="action" value="reset_stats">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="group flex items-center px-4 py-2 text-sm text-yellow-700 hover:bg-yellow-100 hover:text-yellow-900 w-full text-left">
                                                            <svg class="mr-3 h-5 w-5 text-yellow-400 group-hover:text-yellow-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd" />
                                                            </svg>
                                                            Reset Statistics
                                                        </button>
                                                    </form>
                                                    
                                                    <!-- Delete user button -->
                                                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone and will delete all user data including quiz history.');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="group flex items-center px-4 py-2 text-sm text-red-700 hover:bg-red-100 hover:text-red-900 w-full text-left"
                                                                <?php echo $user['id'] == $_SESSION['user_id'] ? 'disabled title="You cannot delete your own account"' : ''; ?>>
                                                            <svg class="mr-3 h-5 w-5 text-red-400 group-hover:text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                                                            </svg>
                                                            Delete User
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                <?php echo !empty($search) ? 'No users found matching your search.' : 'No users found.'; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing
                            <span class="font-medium"><?php echo $offset + 1; ?></span>
                            to
                            <span class="font-medium"><?php echo min($offset + $perPage, $totalUsers); ?></span>
                            of
                            <span class="font-medium"><?php echo $totalUsers; ?></span>
                            results
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php 
                            // Prepare pagination URL parameters
                            $queryParams = [];
                            if (!empty($search)) $queryParams['search'] = $search;
                            if ($activityFilter !== 'all') $queryParams['activity'] = $activityFilter;
                            if ($performanceFilter !== 'all') $queryParams['performance'] = $performanceFilter;
                            
                            $queryString = http_build_query($queryParams);
                            $pageQueryPrefix = !empty($queryString) ? "?$queryString&page=" : "?page=";
                            ?>
                            
                            <?php if ($page > 1): ?>
                                <!-- Previous page button -->
                                <a href="<?php echo $pageQueryPrefix . ($page - 1); ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Previous</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php endif; ?>
                            
                            <?php 
                            // Show a reasonable range of page numbers
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            // Ensure at least 5 pages are shown if possible
                            if ($endPage - $startPage < 4 && $totalPages > 4) {
                                if ($startPage === 1) {
                                    $endPage = min($totalPages, 5);
                                } elseif ($endPage === $totalPages) {
                                    $startPage = max(1, $totalPages - 4);
                                }
                            }
                            
                            for ($i = $startPage; $i <= $endPage; $i++): 
                            ?>
                                <!-- Page number buttons -->
                                <a href="<?php echo $pageQueryPrefix . $i; ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium 
                                          <?php echo $i === $page ? 'text-indigo-600 bg-indigo-50' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <!-- Next page button -->
                                <a href="<?php echo $pageQueryPrefix . ($page + 1); ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Next</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Help Section -->
    <div class="mt-8 bg-blue-50 rounded-lg p-4 border border-blue-200">
        <h3 class="text-lg font-medium text-blue-800 mb-2">Managing Users</h3>
        <ul class="list-disc list-inside text-blue-700 space-y-1">
            <li>Use filters to find specific groups of users</li>
            <li>View detailed statistics for any user by clicking "View Stats"</li>
            <li>The colored dot in Activity column indicates user engagement (green = high, yellow = medium, red = low)</li>
            <li>Performance bar shows average user score across all quizzes</li>
            <li>Use the Actions menu for account management options</li>
        </ul>
    </div>
</div>

<?php include '../includes/footer.php'; ?>