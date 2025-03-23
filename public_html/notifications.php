<?php
/**
 * Notifications Page
 * 
 * This page allows users to:
 * - View their notifications
 * - Mark notifications as read
 * - Delete notifications
 * 
 * For administrators, it also provides:
 * - Ability to send notifications to individual users or groups
 * - View notification analytics
 */

require_once 'config.php';
$pageTitle = 'Notifications';

// Ensure user is logged in
requireLogin();

// Initialize variables
$message = '';
$messageType = '';
$userId = $_SESSION['user_id'];
$isAdmin = isAdmin();

/**
 * Handle notification actions (mark as read, delete)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Mark notifications as read
    if ($action === 'mark_read') {
        $notificationIds = isset($_POST['notification_ids']) ? $_POST['notification_ids'] : [];
        
        if (!empty($notificationIds)) {
            try {
                $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));
                $params = $notificationIds;
                // Add user ID to prevent marking other users' notifications
                $params[] = $userId;
                
                $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id IN ($placeholders) AND user_id = ?");
                $stmt->execute($params);
                
                $message = "Notifications marked as read.";
                $messageType = "success";
            } catch (PDOException $e) {
                $message = "Error updating notifications: " . $e->getMessage();
                $messageType = "error";
                error_log("Notification update error: " . $e->getMessage());
            }
        }
    }
    
    // Delete notifications
    elseif ($action === 'delete') {
        $notificationIds = isset($_POST['notification_ids']) ? $_POST['notification_ids'] : [];
        
        if (!empty($notificationIds)) {
            try {
                $placeholders = implode(',', array_fill(0, count($notificationIds), '?'));
                $params = $notificationIds;
                // Add user ID to prevent deleting other users' notifications
                $params[] = $userId;
                
                $stmt = $pdo->prepare("DELETE FROM notifications WHERE id IN ($placeholders) AND user_id = ?");
                $stmt->execute($params);
                
                $message = "Notifications deleted.";
                $messageType = "success";
            } catch (PDOException $e) {
                $message = "Error deleting notifications: " . $e->getMessage();
                $messageType = "error";
                error_log("Notification delete error: " . $e->getMessage());
            }
        }
    }
    
    // Send notification (admin only)
    elseif ($action === 'send' && $isAdmin) {
        $recipientType = $_POST['recipient_type'] ?? '';
        $recipients = $_POST['recipients'] ?? [];
        $subject = trim($_POST['subject'] ?? '');
        $notificationMessage = trim($_POST['message'] ?? '');
        
        if (empty($subject) || empty($notificationMessage)) {
            $message = "Subject and message are required.";
            $messageType = "error";
        } elseif ($recipientType === 'selected' && empty($recipients)) {
            $message = "Please select at least one recipient.";
            $messageType = "error";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Determine recipients based on selection
                $recipientIds = [];
                
                if ($recipientType === 'all') {
                    // Get all user IDs
                    $stmt = $pdo->query("SELECT id FROM users");
                    while ($row = $stmt->fetch()) {
                        $recipientIds[] = $row['id'];
                    }
                } elseif ($recipientType === 'selected') {
                    $recipientIds = array_map('intval', $recipients);
                } elseif ($recipientType === 'performance') {
                    $performanceLevel = $_POST['performance_level'] ?? '';
                    $performanceValue = 0;
                    
                    switch ($performanceLevel) {
                        case 'high':
                            $performanceValue = 80;
                            break;
                        case 'medium':
                            $performanceValue = 50;
                            break;
                        case 'low':
                            $performanceValue = 0;
                            break;
                    }
                    
                    // Get users based on performance level
                    $stmt = $pdo->prepare("
                        SELECT DISTINCT u.id
                        FROM users u
                        JOIN user_attempts ua ON u.id = ua.user_id
                        GROUP BY u.id
                        HAVING AVG(ua.correct_answers / ua.total_questions * 100) >= ?
                    ");
                    
                    if ($performanceLevel === 'medium') {
                        $stmt = $pdo->prepare("
                            SELECT DISTINCT u.id
                            FROM users u
                            JOIN user_attempts ua ON u.id = ua.user_id
                            GROUP BY u.id
                            HAVING AVG(ua.correct_answers / ua.total_questions * 100) >= ? 
                            AND AVG(ua.correct_answers / ua.total_questions * 100) < 80
                        ");
                    } elseif ($performanceLevel === 'low') {
                        $stmt = $pdo->prepare("
                            SELECT DISTINCT u.id
                            FROM users u
                            JOIN user_attempts ua ON u.id = ua.user_id
                            GROUP BY u.id
                            HAVING AVG(ua.correct_answers / ua.total_questions * 100) < 50
                        ");
                        $stmt->execute();
                    } else {
                        $stmt->execute([$performanceValue]);
                    }
                    
                    while ($row = $stmt->fetch()) {
                        $recipientIds[] = $row['id'];
                    }
                } elseif ($recipientType === 'inactive') {
                    $days = isset($_POST['inactive_days']) ? (int)$_POST['inactive_days'] : 30;
                    
                    // Get users who haven't been active for the specified number of days
                    $stmt = $pdo->prepare("
                        SELECT id FROM users
                        WHERE id NOT IN (
                            SELECT DISTINCT user_id FROM user_attempts
                            WHERE created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                        )
                    ");
                    $stmt->execute([$days]);
                    
                    while ($row = $stmt->fetch()) {
                        $recipientIds[] = $row['id'];
                    }
                }
                
                // Insert notifications for all recipients
                $insertCount = 0;
                foreach ($recipientIds as $recipientId) {
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, subject, message, created_at)
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmt->execute([$recipientId, $subject, $notificationMessage]);
                    $insertCount++;
                }
                
                $pdo->commit();
                
                $message = "Notification sent successfully to $insertCount recipient(s).";
                $messageType = "success";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = "Error sending notifications: " . $e->getMessage();
                $messageType = "error";
                error_log("Notification send error: " . $e->getMessage());
            }
        }
    }
}

/**
 * Mark all notifications as read
 */
if (isset($_GET['mark_all_read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        $message = "All notifications marked as read.";
        $messageType = "success";
    } catch (PDOException $e) {
        $message = "Error updating notifications: " . $e->getMessage();
        $messageType = "error";
        error_log("Notification update error: " . $e->getMessage());
    }
}

/**
 * Fetch user notifications with pagination
 */
try {
    // Set up pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 10;
    $offset = ($page - 1) * $perPage;
    
    // Filter options
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    $whereClause = "WHERE user_id = ?";
    
    if ($filter === 'unread') {
        $whereClause .= " AND is_read = 0";
    } elseif ($filter === 'read') {
        $whereClause .= " AND is_read = 1";
    }
    
    // Get total count for pagination
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications $whereClause");
    $stmt->execute([$userId]);
    $totalNotifications = $stmt->fetchColumn();
    $totalPages = ceil($totalNotifications / $perPage);
    
    // Get notifications for current page
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        $whereClause 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$userId, $perPage, $offset]);
    $notifications = $stmt->fetchAll();
    
    // Get unread count for display
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    $unreadCount = $stmt->fetchColumn();
    
    // For admin: Get users for the send notification form
    $users = [];
    if ($isAdmin) {
        $stmt = $pdo->query("SELECT id, first_name, last_name, email FROM users ORDER BY first_name, last_name");
        $users = $stmt->fetchAll();
    }
    
} catch (PDOException $e) {
    $message = "Error fetching notifications: " . $e->getMessage();
    $messageType = "error";
    error_log("Notification fetch error: " . $e->getMessage());
    $notifications = [];
    $totalPages = 0;
    $unreadCount = 0;
}

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Notifications</h1>
        
        <?php if ($totalNotifications > 0): ?>
            <div class="flex space-x-2">
                <?php if ($unreadCount > 0): ?>
                    <a href="?mark_all_read=1" class="bg-blue-100 text-blue-700 hover:bg-blue-200 px-4 py-2 rounded-md transition-colors">
                        Mark All as Read
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Two-column layout for admin: notifications and send form -->
    <div class="grid grid-cols-1 <?php echo $isAdmin ? 'lg:grid-cols-3' : ''; ?> gap-8">
        <!-- Notifications Column -->
        <div class="<?php echo $isAdmin ? 'lg:col-span-2' : ''; ?>">
            <!-- Filter Tabs -->
            <div class="mb-6 border-b border-gray-200">
                <div class="flex">
                    <a href="?filter=all" class="py-4 px-6 border-b-2 font-medium text-sm <?php echo $filter === 'all' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                        All
                    </a>
                    <a href="?filter=unread" class="py-4 px-6 border-b-2 font-medium text-sm <?php echo $filter === 'unread' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                        Unread 
                        <?php if ($unreadCount > 0): ?>
                            <span class="ml-1 bg-red-100 text-red-700 px-2 py-0.5 rounded-full text-xs"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?filter=read" class="py-4 px-6 border-b-2 font-medium text-sm <?php echo $filter === 'read' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                        Read
                    </a>
                </div>
            </div>
            
            <?php if (empty($notifications)): ?>
                <div class="bg-white rounded-lg shadow-md p-8 text-center">
                    <div class="flex justify-center mb-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 mb-1">No notifications found</h3>
                    <p class="text-gray-500">
                        <?php echo $filter === 'unread' ? 'You have no unread notifications.' : 'You don\'t have any notifications yet.'; ?>
                    </p>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="p-4 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                            <div class="flex items-center">
                                <input type="checkbox" id="select-all" class="form-checkbox h-5 w-5 text-indigo-600 rounded focus:ring-indigo-500">
                                <label for="select-all" class="ml-2 text-sm text-gray-700">Select All</label>
                            </div>
                            
                            <div class="flex space-x-2">
                                <button type="submit" name="action" value="mark_read" class="bg-blue-100 text-blue-700 hover:bg-blue-200 px-3 py-1 rounded text-sm transition-colors">
                                    Mark as Read
                                </button>
                                <button type="submit" name="action" value="delete" class="bg-red-100 text-red-700 hover:bg-red-200 px-3 py-1 rounded text-sm transition-colors"
                                        onclick="return confirm('Are you sure you want to delete the selected notifications?')">
                                    Delete
                                </button>
                            </div>
                        </div>
                        
                        <ul class="divide-y divide-gray-200">
                            <?php foreach ($notifications as $notification): ?>
                                <li class="p-4 hover:bg-gray-50 transition-colors <?php echo $notification['is_read'] ? '' : 'bg-indigo-50'; ?>">
                                    <div class="flex items-start">
                                        <div class="mr-3 mt-1">
                                            <input type="checkbox" name="notification_ids[]" value="<?php echo $notification['id']; ?>" class="notification-checkbox form-checkbox h-5 w-5 text-indigo-600 rounded focus:ring-indigo-500">
                                        </div>
                                        <div class="flex-1">
                                            <div class="flex justify-between">
                                                <h3 class="text-base font-medium text-gray-900 <?php echo $notification['is_read'] ? '' : 'font-bold'; ?>">
                                                    <?php echo htmlspecialchars($notification['subject']); ?>
                                                </h3>
                                                <span class="text-sm text-gray-500">
                                                    <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                                </span>
                                            </div>
                                            <p class="mt-1 text-sm text-gray-600"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                                            
                                            <?php if (!$notification['is_read']): ?>
                                                <div class="mt-2">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        New
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </form>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="mt-6 flex justify-center">
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php 
                            // Prepare pagination URL parameters
                            $filterParam = $filter !== 'all' ? "&filter=$filter" : "";
                            ?>
                            
                            <?php if ($page > 1): ?>
                                <!-- Previous page button -->
                                <a href="?page=<?php echo $page - 1 . $filterParam; ?>" 
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
                                <a href="?page=<?php echo $i . $filterParam; ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium 
                                          <?php echo $i === $page ? 'text-indigo-600 bg-indigo-50' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <!-- Next page button -->
                                <a href="?page=<?php echo $page + 1 . $filterParam; ?>" 
                                   class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <span class="sr-only">Next</span>
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <?php if ($isAdmin): ?>
            <!-- Send Notification Form (Admin only) -->
            <div>
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="p-4 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-lg font-medium text-gray-900">Send Notification</h2>
                    </div>
                    
                    <div class="p-6">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="send">
                            
                            <div class="mb-6" x-data="{ selectedType: 'selected' }">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Recipients</label>
                                
                                <div class="space-y-2">
                                    <div class="flex items-center">
                                        <input type="radio" id="recipient_selected" name="recipient_type" value="selected" 
                                               x-model="selectedType" checked 
                                               class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
                                        <label for="recipient_selected" class="ml-2 block text-sm text-gray-700">
                                            Selected Users
                                        </label>
                                    </div>
                                    
                                    <div class="flex items-center">
                                        <input type="radio" id="recipient_all" name="recipient_type" value="all" 
                                               x-model="selectedType"
                                               class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
                                        <label for="recipient_all" class="ml-2 block text-sm text-gray-700">
                                            All Users
                                        </label>
                                    </div>
                                    
                                    <div class="flex items-center">
                                        <input type="radio" id="recipient_performance" name="recipient_type" value="performance" 
                                               x-model="selectedType"
                                               class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
                                        <label for="recipient_performance" class="ml-2 block text-sm text-gray-700">
                                            By Performance Level
                                        </label>
                                    </div>
                                    
                                    <div class="flex items-center">
                                        <input type="radio" id="recipient_inactive" name="recipient_type" value="inactive" 
                                               x-model="selectedType"
                                               class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300">
                                        <label for="recipient_inactive" class="ml-2 block text-sm text-gray-700">
                                            Inactive Users
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- User selection list (shown when 'Selected Users' is chosen) -->
                                <div class="mt-4" x-show="selectedType === 'selected'">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Users</label>
                                    
                                    <?php if (count($users) > 0): ?>
                                        <div class="max-h-60 overflow-y-auto border border-gray-300 rounded-md p-2">
                                            <?php foreach ($users as $user): ?>
                                                <div class="flex items-center p-2 hover:bg-gray-50">
                                                    <input type="checkbox" id="user_<?php echo $user['id']; ?>" name="recipients[]" value="<?php echo $user['id']; ?>" 
                                                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                                    <label for="user_<?php echo $user['id']; ?>" class="ml-2 block text-sm text-gray-700">
                                                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                                        <span class="text-xs text-gray-500">(<?php echo htmlspecialchars($user['email']); ?>)</span>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-sm text-gray-500">No users available.</p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Performance level selection (shown when 'By Performance Level' is chosen) -->
                                <div class="mt-4" x-show="selectedType === 'performance'">
                                    <label for="performance_level" class="block text-sm font-medium text-gray-700 mb-2">Performance Level</label>
                                    <select id="performance_level" name="performance_level" 
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <option value="high">High Performers (80%+)</option>
                                        <option value="medium">Medium Performers (50-79%)</option>
                                        <option value="low">Low Performers (Below 50%)</option>
                                    </select>
                                </div>
                                
                                <!-- Inactive days selection (shown when 'Inactive Users' is chosen) -->
                                <div class="mt-4" x-show="selectedType === 'inactive'">
                                    <label for="inactive_days" class="block text-sm font-medium text-gray-700 mb-2">Inactive For</label>
                                    <select id="inactive_days" name="inactive_days" 
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <option value="7">7 days</option>
                                        <option value="14">14 days</option>
                                        <option value="30" selected>30 days</option>
                                        <option value="60">60 days</option>
                                        <option value="90">90 days</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">Subject</label>
                                <input type="text" id="subject" name="subject" required 
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                            </div>
                            
                            <div class="mb-6">
                                <label for="message" class="block text-sm font-medium text-gray-700 mb-2">Message</label>
                                <textarea id="message" name="message" rows="5" required 
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"></textarea>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Send Notification
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Quick Templates (admin only) -->
                <div class="mt-6 bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <h3 class="text-md font-medium text-gray-700 mb-2">Quick Templates</h3>
                    
                    <div class="space-y-2">
                        <button onclick="fillTemplate('Welcome Message', 'Welcome to our quiz platform! Here are some tips to get started...')" 
                                class="w-full text-left p-2 text-sm text-indigo-600 hover:bg-indigo-50 rounded transition-colors">
                            Welcome Message
                        </button>
                        
                        <button onclick="fillTemplate('New Features Available', 'We\'ve added new features to enhance your experience...')" 
                                class="w-full text-left p-2 text-sm text-indigo-600 hover:bg-indigo-50 rounded transition-colors">
                            New Features Announcement
                        </button>
                        
                        <button onclick="fillTemplate('Practice Reminder', 'It\'s been a while since your last quiz. Regular practice helps improve retention...')" 
                                class="w-full text-left p-2 text-sm text-indigo-600 hover:bg-indigo-50 rounded transition-colors">
                            Practice Reminder
                        </button>
                        
                        <button onclick="fillTemplate('Achievement Congratulations', 'Congratulations on your recent progress! You\'ve shown great improvement...')" 
                                class="w-full text-left p-2 text-sm text-indigo-600 hover:bg-indigo-50 rounded transition-colors">
                            Achievement Congratulations
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Select all functionality
    document.addEventListener('DOMContentLoaded', function() {
        const selectAllCheckbox = document.getElementById('select-all');
        const notificationCheckboxes = document.querySelectorAll('.notification-checkbox');
        
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                notificationCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });
        }
        
        // Template filling functionality (for admin)
        window.fillTemplate = function(subject, message) {
            document.getElementById('subject').value = subject;
            document.getElementById('message').value = message;
        }
    });
</script>

<?php include 'includes/footer.php'; ?>