<?php
require_once 'config.php';
$pageTitle = 'Reset Password';

// If user is already logged in, redirect to home page
if (isLoggedIn()) {
    header("Location: /");
    exit;
}

// Initialize variables
$message = '';
$messageType = '';
$validToken = false;
$token = '';
$userData = null;

// Function to validate a token
function validateToken($token) {
    $tokenDir = dirname(__FILE__) . '/tokens';
    $tokenFile = $tokenDir . '/' . $token . '.json';
    
    if (!file_exists($tokenFile)) {
        return null;
    }
    
    $tokenData = json_decode(file_get_contents($tokenFile), true);
    if (!$tokenData) {
        return null;
    }
    
    // Check if token is expired
    if (time() > $tokenData['expires']) {
        // Delete expired token file
        @unlink($tokenFile);
        return null;
    }
    
    return $tokenData;
}

// Check if token is provided
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    
    // Validate token format (to prevent directory traversal)
    if (!preg_match('/^[a-f0-9]+$/', $token)) {
        $message = 'Invalid token format';
        $messageType = 'error';
    } else {
        $userData = validateToken($token);
        if ($userData) {
            $validToken = true;
        } else {
            $message = 'The password reset link is invalid or has expired.';
            $messageType = 'error';
        }
    }
} else {
    $message = 'No reset token provided.';
    $messageType = 'error';
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirmPassword)) {
        $message = 'Both fields are required';
        $messageType = 'error';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters long';
        $messageType = 'error';
    } elseif ($password !== $confirmPassword) {
        $message = 'Passwords do not match';
        $messageType = 'error';
    } else {
        try {
            // Hash the new password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Update user's password
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashedPassword, $userData['user_id']])) {
                // Password updated successfully, delete token file
                $tokenFile = dirname(__FILE__) . '/tokens/' . $token . '.json';
                if (file_exists($tokenFile)) {
                    @unlink($tokenFile);
                }
                
                $message = 'Your password has been reset successfully. You can now log in with your new password.';
                $messageType = 'success';
                $validToken = false; // Hide the form after successful reset
            } else {
                $message = 'Failed to update password. Please try again.';
                $messageType = 'error';
            }
        } catch (PDOException $e) {
            $message = 'Server error. Please try again later.';
            $messageType = 'error';
            error_log("Reset password update error: " . $e->getMessage());
        }
    }
}

include 'includes/header.php';
?>

<div class="max-w-md mx-auto my-10">
    <div class="bg-white p-8 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-8">Reset Your Password</h2>
        
        <?php if (!empty($message)): ?>
            <div class="bg-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-100 border-l-4 border-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-500 text-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-700 p-4 mb-6" role="alert">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($validToken): ?>
            <form method="POST" action="reset-password.php?token=<?php echo htmlspecialchars($token); ?>" x-data="{
                password: '',
                confirmPassword: '',
                showPassword: false,
                showConfirmPassword: false,
                passwordStrength: 0,
                
                checkPasswordStrength() {
                    let strength = 0;
                    const password = this.password;
                    
                    if (password.length >= 8) strength += 1;
                    if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength += 1;
                    if (password.match(/\d/)) strength += 1;
                    if (password.match(/[^a-zA-Z\d]/)) strength += 1;
                    
                    this.passwordStrength = strength;
                }
            }">
                <div class="mb-6">
                    <label for="password" class="block text-gray-700 text-sm font-bold mb-2">New Password</label>
                    <div class="relative">
                        <input :type="showPassword ? 'text' : 'password'" id="password" name="password" 
                               x-model="password" @input="checkPasswordStrength()" required
                               class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                               placeholder="Enter your new password">
                        <button type="button" @click="showPassword = !showPassword" 
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5">
                            <svg class="h-6 w-6 text-gray-500" fill="none" x-show="!showPassword" 
                                 viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg class="h-6 w-6 text-gray-500" fill="none" x-show="showPassword" 
                                 viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Password strength indicator -->
                    <div class="mt-2">
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="h-2.5 rounded-full transition-all duration-300"
                                 :class="{
                                     'w-1/4 bg-red-500': passwordStrength === 1,
                                     'w-2/4 bg-yellow-500': passwordStrength === 2,
                                     'w-3/4 bg-blue-500': passwordStrength === 3,
                                     'w-full bg-green-500': passwordStrength === 4,
                                     'w-0': passwordStrength === 0
                                 }">
                            </div>
                        </div>
                        <p class="text-xs text-gray-600 mt-1">Password should be at least 8 characters with uppercase, lowercase, numbers, and special characters.</p>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label for="confirm_password" class="block text-gray-700 text-sm font-bold mb-2">Confirm New Password</label>
                    <div class="relative">
                        <input :type="showConfirmPassword ? 'text' : 'password'" id="confirm_password" name="confirm_password" 
                               x-model="confirmPassword" required
                               class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                               :class="{'border-red-500': confirmPassword && password !== confirmPassword}"
                               placeholder="Confirm your new password">
                        <button type="button" @click="showConfirmPassword = !showConfirmPassword" 
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-sm leading-5">
                            <svg class="h-6 w-6 text-gray-500" fill="none" x-show="!showConfirmPassword" 
                                 viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            <svg class="h-6 w-6 text-gray-500" fill="none" x-show="showConfirmPassword" 
                                 viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        </button>
                    </div>
                    <div x-show="confirmPassword && password !== confirmPassword" class="text-red-500 text-xs mt-1">
                        Passwords do not match
                    </div>
                </div>
                
                <div class="mb-6">
                    <button type="submit" 
                            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded focus:outline-none focus:shadow-outline transition duration-150"
                            :disabled="!password || !confirmPassword || password !== confirmPassword"
                            :class="{'opacity-50 cursor-not-allowed': !password || !confirmPassword || password !== confirmPassword}">
                        Reset Password
                    </button>
                </div>
            </form>
        <?php elseif ($messageType === 'success'): ?>
            <div class="text-center">
                <a href="login.php" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded focus:outline-none focus:shadow-outline transition duration-150">
                    Go to Login
                </a>
            </div>
        <?php else: ?>
            <div class="text-center">
                <p class="text-gray-600 mb-4">
                    The password reset link is invalid or has expired. Please try again.
                </p>
                <a href="forgot-password.php" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded focus:outline-none focus:shadow-outline transition duration-150">
                    Request New Reset Link
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>