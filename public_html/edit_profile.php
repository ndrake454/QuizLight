<?php
/**
 * Edit Profile Page
 * 
 * This page allows users to:
 * - Update their personal information
 * - Change their password
 */

require_once 'config.php';
$pageTitle = 'Edit Profile';

// Ensure user is logged in
requireLogin();

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

/**
 * Fetch user data
 */
try {
    $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, created_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // User not found (should not happen)
        session_unset();
        session_destroy();
        header("Location: /login.php");
        exit;
    }
} catch (PDOException $e) {
    $message = "Error fetching user data: " . $e->getMessage();
    $messageType = "error";
    // Log the error
    error_log("Profile data error: " . $e->getMessage());
}

/**
 * Handle profile update
 */
if (isset($_POST['update_profile'])) {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if (empty($firstName) || empty($lastName) || empty($email)) {
        $message = "All fields are required.";
        $messageType = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "error";
    } else {
        try {
            // Check if email is already used by another user
            if ($email !== $user['email']) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $userId]);
                if ($stmt->rowCount() > 0) {
                    $message = "Email address is already in use.";
                    $messageType = "error";
                } else {
                    // Update profile
                    $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
                    $stmt->execute([$firstName, $lastName, $email, $userId]);
                    
                    // Update session data
                    $_SESSION['email'] = $email;
                    $_SESSION['first_name'] = $firstName;
                    
                    // Store success message in session and redirect
                    $_SESSION['profile_message'] = "Profile updated successfully.";
                    $_SESSION['profile_message_type'] = "success";
                    
                    header("Location: profile.php");
                    exit;
                }
            } else {
                // Just update name if email hasn't changed
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE id = ?");
                $stmt->execute([$firstName, $lastName, $userId]);
                
                // Update session data
                $_SESSION['first_name'] = $firstName;
                
                // Store success message in session and redirect
                $_SESSION['profile_message'] = "Profile updated successfully.";
                $_SESSION['profile_message_type'] = "success";
                
                header("Location: profile.php");
                exit;
            }
        } catch (PDOException $e) {
            $message = "Error updating profile: " . $e->getMessage();
            $messageType = "error";
            // Log the error
            error_log("Profile update error: " . $e->getMessage());
        }
    }
}

/**
 * Handle password change
 */
if (isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $message = "All password fields are required.";
        $messageType = "error";
    } elseif ($newPassword !== $confirmPassword) {
        $message = "New passwords do not match.";
        $messageType = "error";
    } elseif (strlen($newPassword) < 8) {
        $message = "New password must be at least 8 characters long.";
        $messageType = "error";
    } else {
        try {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch();
            
            if (!password_verify($currentPassword, $userData['password'])) {
                $message = "Current password is incorrect.";
                $messageType = "error";
            } else {
                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $userId]);
                
                // Store success message in session and redirect
                $_SESSION['profile_message'] = "Password changed successfully.";
                $_SESSION['profile_message_type'] = "success";
                
                header("Location: profile.php");
                exit;
            }
        } catch (PDOException $e) {
            $message = "Error changing password: " . $e->getMessage();
            $messageType = "error";
            // Log the error
            error_log("Password change error: " . $e->getMessage());
        }
    }
}
/**
 * Handle statistics reset
 */
if (isset($_POST['reset_statistics'])) {
    try {
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
        
        // Store success message in session and redirect
        $_SESSION['profile_message'] = "Your statistics have been reset successfully.";
        $_SESSION['profile_message_type'] = "success";
        
        header("Location: profile.php");
        exit;
    } catch (PDOException $e) {
        // Rollback the transaction if anything fails
        $pdo->rollBack();
        
        $message = "Error resetting statistics: " . $e->getMessage();
        $messageType = "error";
        // Log the error
        error_log("Statistics reset error: " . $e->getMessage());
    }
}
include 'includes/header.php';
?>

<div class="max-w-3xl mx-auto">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Edit Profile</h1>
        <a href="profile.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Profile
        </a>
    </div>
    
    <?php if (!empty($message)): ?>
        <div class="mb-8 p-4 rounded-md <?php echo $messageType === 'success' ? 'bg-green-100 border-l-4 border-green-500 text-green-700' : 'bg-red-100 border-l-4 border-red-500 text-red-700'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="space-y-8">
        <!-- Edit Profile Form -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-bold mb-6">Personal Information</h2>
            
            <form action="" method="POST" class="space-y-5">
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required
                           class="mt-1 block w-full py-3 px-4 rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
                
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required
                           class="mt-1 block w-full py-3 px-4 rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required
                           class="mt-1 block w-full py-3 px-4 rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
                
                <div>
                    <button type="submit" name="update_profile" value="1"
                            class="inline-flex justify-center py-3 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Change Password Form -->
        <div class="bg-white p-6 rounded-lg shadow-md" x-data="{ passwordVisible: false, confirmPasswordVisible: false }">
            <h2 class="text-xl font-bold mb-6">Change Password</h2>
            
            <form action="" method="POST" class="space-y-5">
                <div>
                    <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required
                           class="mt-1 block w-full py-3 px-4 rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
                
                <div>
                    <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
                    <div class="relative mt-1">
                        <input :type="passwordVisible ? 'text' : 'password'" id="new_password" name="new_password" required
                               class="block w-full py-3 px-4 rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <button type="button" @click="passwordVisible = !passwordVisible" 
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5">
                            <svg class="h-5 w-5 text-gray-500" fill="none" :class="{'hidden': passwordVisible}" 
                                 viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg class="h-5 w-5 text-gray-500" fill="none" :class="{'hidden': !passwordVisible}" 
                                 viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        </button>
                    </div>
                    <p class="mt-1 text-sm text-gray-500">Password must be at least 8 characters long.</p>
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                    <div class="relative mt-1">
                        <input :type="confirmPasswordVisible ? 'text' : 'password'" id="confirm_password" name="confirm_password" required
                               class="block w-full py-3 px-4 rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <button type="button" @click="confirmPasswordVisible = !confirmPasswordVisible" 
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5">
                            <svg class="h-5 w-5 text-gray-500" fill="none" :class="{'hidden': confirmPasswordVisible}" 
                                 viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg class="h-5 w-5 text-gray-500" fill="none" :class="{'hidden': !confirmPasswordVisible}" 
                                 viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div>
                    <button type="submit" name="change_password" value="1"
                            class="inline-flex justify-center py-3 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150">
                        Change Password
                    </button>
                </div>
            </form>
        </div>
        <!-- Reset Statistics -->
        <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-red-500">
            <h2 class="text-xl font-bold mb-6">Reset Statistics</h2>
            
            <div class="mb-4">
                <p class="text-gray-700 mb-2">Resetting your statistics will:</p>
                <ul class="list-disc list-inside text-gray-600 space-y-1 mb-4">
                    <li>Delete all your quiz attempt history</li>
                    <li>Clear your performance data for all categories</li>
                    <li>Remove all your question answers and ratings</li>
                    <li>Reset your progress tracking to zero</li>
                </ul>
                <p class="text-red-600 font-medium">This action cannot be undone.</p>
            </div>
            
            <form action="" method="POST" onsubmit="return confirmReset()">
                <input type="hidden" name="reset_statistics" value="1">
                <button type="submit" class="inline-flex items-center justify-center py-3 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition duration-150">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    Reset All Statistics
                </button>
            </form>
        </div>

        <script>
        function confirmReset() {
            // Double confirmation for destructive action
            return confirm('Are you sure you want to reset all your statistics? This will delete all your quiz history and progress. This action cannot be undone.');
        }
        </script>


    </div>
</div>

<?php include 'includes/footer.php'; ?>