<?php
/**
 * Admin User Notifications System
 * 
 * This page allows administrators to:
 * - Create and send notifications to individual or groups of users
 * - View notification history
 * - Monitor delivery and read status
 * - Create templates for common notifications
 */

require_once '../config.php';
$pageTitle = 'User Notifications';

// Ensure user is logged in and is an admin
requireAdmin();

// Initialize variables
$message = '';
$messageType = '';
$notificationTemplates = [
    'welcome' => [
        'title' => 'Welcome to the Platform',
        'content' => 'Welcome to our learning platform! We\'re excited to have you join us. Get started by taking a quiz in your area of interest.'
    ],
    'inactive' => [
        'title' => 'We Miss You!',
        'content' => 'It\'s been a while since you\'ve taken a quiz. Come back and continue your learning journey with us!'
    ],
    'progress' => [
        'title' => 'Your Learning Progress',
        'content' => 'You\'ve been making great progress in your learning journey. Keep up the good work!'
    ],
    'new_content' => [
        'title' => 'New Content Available',
        'content' => 'We\'ve added new questions and categories to the platform. Log in to explore the new content!'
    ],
    'achievement' => [
        'title' => 'Achievement Unlocked!',
        'content' => 'Congratulations! You\'ve reached a new milestone in your learning journey.'
    ]
];

/**
 * Handle form submissions
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create new notification
    if (isset($_POST['send_notification'])) {
        $title = trim($_POST['notification_title']);
        $content = trim($_POST['notification_content']);
        $recipientType = $_POST['recipient_type'];
        $specificUsers = isset($_POST['specific_users']) ? $_POST['specific_users'] : [];
        $activityFilter = $_POST['activity_filter'] ?? 'all';
        $performanceFilter = $_POST['performance_filter'] ?? 'all';
        $importance = $_POST['importance'] ?? 'normal';
        
        // Validate inputs
        if (empty($title) || empty($content)) {
            $message = "Notification title and content are required.";
            $messageType = "error";
        } else {
            try {
                // Get recipient list based on selection
                $recipients = [];
                
                switch ($recipientType) {
                    case 'all':
                        // Get all users except current admin
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE id != ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        break;
                    
                    case 'specific':
                        // Use provided user IDs
                        $recipients = $specificUsers;
                        break;
                    
                    case 'filtered':
                        // Build query based on filters
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
                        
                        // Performance filter
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
                        } else {
                            $performanceJoin = "";
                        }
                        
                        // Don't include current admin
                        $whereClause[] = "u.id != " . $_SESSION['user_id'];
                        
                        // Build query
                        $whereStr = empty($whereClause) ? "" : "WHERE " . implode(" AND ", $whereClause);
                        $sql = "SELECT u.id FROM users u $performanceJoin $whereStr";
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        break;
                }
                
                // Create notification in database
                $notificationCount = 0;
                $current_time = date('Y-m-d H:i:s');
                
                // Create notifications table if it doesn't exist
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS user_notifications (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        title VARCHAR(255) NOT NULL,
                        content TEXT NOT NULL,
                        importance VARCHAR(50) DEFAULT 'normal',
                        created_at DATETIME NOT NULL,
                        read_at DATETIME NULL,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    )
                ");
                
                // Begin transaction
                $pdo->beginTransaction();
                
                foreach ($recipients as $userId) {
                    $stmt = $pdo->prepare("
                        INSERT INTO user_notifications (user_id, title, content, importance, created_at)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$userId, $title, $content, $importance, $current_time]);
                    $notificationCount++;
                }
                
                // Commit transaction
                $pdo->commit();
                
                $message = "Notification sent successfully to $notificationCount user(s).";
                $messageType = "success";
                
            } catch (PDOException $e) {
                // Rollback if error
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                
                $message = "Error sending notifications: " . $e->getMessage();
                $messageType = "error";
                error_log("Notification sending error: " . $e->getMessage());
            }
        }
    }
    
    // Load template (AJAX request)
    elseif (isset($_POST['load_template'])) {
        $templateKey = $_POST['template'];
        if (isset($notificationTemplates[$templateKey])) {
            // Return template as JSON
            header('Content-Type: application/json');
            echo json_encode($notificationTemplates[$templateKey]);
            exit;
        } else {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Template not found']);
            exit;
        }
    }
}

/**
 * Get notification history
 */
try {
    // Check if the notifications table exists
    $tableExists = false;
    $stmt = $pdo->prepare("
        SELECT 1 FROM information_schema.tables 
        WHERE table_schema = DATABASE() 
        AND table_name = 'user_notifications'
    ");
    $stmt->execute();
    $tableExists = ($stmt->fetchColumn() === 1);
    
    $recentNotifications = [];
    
    if ($tableExists) {
        // Get recent notifications (group by title+content to show unique ones)
        $stmt = $pdo->prepare("
            SELECT 
                n.title, 
                n.content, 
                n.importance,
                COUNT(n.id) as recipient_count, 
                n.created_at,
                (SELECT COUNT(*) FROM user_notifications WHERE title = n.title AND content = n.content AND read_at IS NOT NULL) as read_count
            FROM user_notifications n
            GROUP BY n.title, n.content, n.created_at, n.importance
            ORDER BY n.created_at DESC
            LIMIT 10
        ");
        $stmt->execute();
        $recentNotifications = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Error fetching notification history: " . $e->getMessage());
    $recentNotifications = [];
}

/**
 * Get users for the specific recipients selection
 */
try {
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, email 
        FROM users 
        WHERE id != ?
        ORDER BY first_name, last_name
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $availableUsers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching users for notifications: " . $e->getMessage());
    $availableUsers = [];
}

include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Page header with title and back button -->
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">User Notifications</h1>
        <a href="/admin/" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition duration-150">
            Back to Dashboard
        </a>
    </div>
    
    <!-- Display messages -->
    <?php if (!empty($message)): ?>
        <div class="mb-6 p-4 rounded-md <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Main content - two column layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column - Notification Form -->
        <div class="lg:col-span-2">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">Create New Notification</h2>
                
                <form method="POST" action="" id="notificationForm">
                    <!-- Templates dropdown -->
                    <div class="mb-6">
                        <label for="template" class="block text-sm font-medium text-gray-700 mb-1">Use Template</label>
                        <select id="template" name="template" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                            <option value="">Select a template (optional)</option>
                            <?php foreach ($notificationTemplates as $key => $template): ?>
                                <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($template['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Notification Details -->
                    <div class="mb-6">
                        <label for="notification_title" class="block text-sm font-medium text-gray-700 mb-1">Notification Title</label>
                        <input type="text" id="notification_title" name="notification_title" required
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                               placeholder="Enter a clear, concise title">
                    </div>
                    
                    <div class="mb-6">
                        <label for="notification_content" class="block text-sm font-medium text-gray-700 mb-1">Notification Content</label>
                        <textarea id="notification_content" name="notification_content" rows="4" required
                                  class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                  placeholder="Enter the notification message"></textarea>
                    </div>
                    
                    <!-- Recipient Selection -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Recipients</label>
                        <div class="space-y-3">
                            <div class="flex items-center">
                                <input type="radio" id="recipient_all" name="recipient_type" value="all" checked
                                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                                <label for="recipient_all" class="ml-2 block text-sm text-gray-700">All Users</label>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="radio" id="recipient_filtered" name="recipient_type" value="filtered"
                                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                                <label for="recipient_filtered" class="ml-2 block text-sm text-gray-700">Filtered Users</label>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="radio" id="recipient_specific" name="recipient_type" value="specific"
                                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                                <label for="recipient_specific" class="ml-2 block text-sm text-gray-700">Specific Users</label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Conditional options based on recipient selection -->
                    <div id="filtered_options" class="mb-6 hidden">
                        <div class="p-4 bg-gray-50 rounded-md">
                            <h3 class="text-sm font-medium text-gray-700 mb-3">Filter Recipients</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                            </div>
                        </div>
                    </div>
                    
                    <div id="specific_options" class="mb-6 hidden">
                        <div class="p-4 bg-gray-50 rounded-md">
                            <h3 class="text-sm font-medium text-gray-700 mb-3">Select Specific Users</h3>
                            
                            <div>
                                <label for="specific_users" class="block text-sm font-medium text-gray-700">Recipients</label>
                                <select id="specific_users" name="specific_users[]" multiple class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" size="6">
                                    <?php foreach ($availableUsers as $user): ?>
                                        <option value="<?php echo $user['id']; ?>">
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['email'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Hold Ctrl/Cmd to select multiple users</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Importance Selection -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Importance</label>
                        <div class="flex space-x-4">
                            <div class="flex items-center">
                                <input type="radio" id="importance_low" name="importance" value="low"
                                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                                <label for="importance_low" class="ml-2 block text-sm text-gray-700">Low</label>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="radio" id="importance_normal" name="importance" value="normal" checked
                                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                                <label for="importance_normal" class="ml-2 block text-sm text-gray-700">Normal</label>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="radio" id="importance_high" name="importance" value="high"
                                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300">
                                <label for="importance_high" class="ml-2 block text-sm text-gray-700">High</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" name="send_notification" value="1" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Send Notification
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Right Column - Recent Notifications -->
        <div class="lg:col-span-1">
            <div class="bg-white p-6 rounded-lg shadow-md">
                <h2 class="text-xl font-semibold mb-4">Recent Notifications</h2>
                
                <?php if (!empty($recentNotifications)): ?>
                    <div class="space-y-4">
                        <?php foreach ($recentNotifications as $notification): ?>
                            <div class="border-l-4 
                                <?php echo 
                                    $notification['importance'] === 'high' ? 'border-red-500 bg-red-50' : 
                                    ($notification['importance'] === 'normal' ? 'border-blue-500 bg-blue-50' : 'border-gray-500 bg-gray-50'); ?> 
                                p-4 rounded-r-md">
                                <h3 class="font-medium text-gray-800"><?php echo htmlspecialchars($notification['title']); ?></h3>
                                <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($notification['content']); ?></p>
                                
                                <div class="flex justify-between items-center mt-2 text-xs text-gray-500">
                                    <span><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></span>
                                    <span><?php echo $notification['recipient_count']; ?> recipients</span>
                                </div>
                                
                                <div class="mt-2 h-1.5 w-full bg-gray-200 rounded-full overflow-hidden">
                                    <?php 
                                    $readPercentage = $notification['recipient_count'] > 0 ? 
                                        ($notification['read_count'] / $notification['recipient_count']) * 100 : 0;
                                    ?>
                                    <div class="h-1.5 bg-green-500 rounded-full" style="width: <?php echo $readPercentage; ?>%"></div>
                                </div>
                                <div class="text-right text-xs text-gray-500">
                                    <?php echo $notification['read_count']; ?> read (<?php echo round($readPercentage); ?>%)
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-center p-4">No notifications have been sent yet.</p>
                <?php endif; ?>
            </div>
            
            <!-- Tips Section -->
            <div class="bg-blue-50 rounded-lg p-4 border border-blue-200 mt-6">
                <h3 class="text-lg font-medium text-blue-800 mb-2">Tips for Effective Notifications</h3>
                <ul class="list-disc list-inside text-blue-700 space-y-1 text-sm">
                    <li>Keep titles clear and concise</li>
                    <li>Make the message actionable when possible</li>
                    <li>Use high importance sparingly</li>
                    <li>Target specific user groups for better engagement</li>
                    <li>Consider timing - avoid sending too many notifications</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle recipient type selection
    const recipientTypeRadios = document.querySelectorAll('input[name="recipient_type"]');
    const filteredOptions = document.getElementById('filtered_options');
    const specificOptions = document.getElementById('specific_options');
    
    function updateRecipientOptions() {
        const selectedValue = document.querySelector('input[name="recipient_type"]:checked').value;
        
        filteredOptions.classList.toggle('hidden', selectedValue !== 'filtered');
        specificOptions.classList.toggle('hidden', selectedValue !== 'specific');
    }
    
    recipientTypeRadios.forEach(radio => {
        radio.addEventListener('change', updateRecipientOptions);
    });
    
    // Initialize on page load
    updateRecipientOptions();
    
    // Handle template selection
    const templateSelect = document.getElementById('template');
    const titleInput = document.getElementById('notification_title');
    const contentInput = document.getElementById('notification_content');
    
    templateSelect.addEventListener('change', function() {
        const selectedTemplate = this.value;
        
        if (selectedTemplate) {
            // Send AJAX request to get template content
            const formData = new FormData();
            formData.append('load_template', '1');
            formData.append('template', selectedTemplate);
            
            fetch('notifications.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                titleInput.value = data.title;
                contentInput.value = data.content;
            })
            .catch(error => console.error('Error loading template:', error));
        }
    });
    
    // Validation for the notification form
    const notificationForm = document.getElementById('notificationForm');
    
    notificationForm.addEventListener('submit', function(e) {
        const recipientType = document.querySelector('input[name="recipient_type"]:checked').value;
        
        if (recipientType === 'specific') {
            const selectedUsers = document.getElementById('specific_users').selectedOptions;
            if (selectedUsers.length === 0) {
                e.preventDefault();
                alert('Please select at least one user to receive the notification.');
                return false;
            }
        }
        
        return true;
    });
});
</script>

<?php include '../includes/footer.php'; ?>