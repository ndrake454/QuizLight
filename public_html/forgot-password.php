<?php
require_once 'config.php';
$pageTitle = 'Forgot Password';

// If user is already logged in, redirect to home page
if (isLoggedIn()) {
    header("Location: /");
    exit;
}

// Handle form submission
$message = '';
$messageType = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $message = 'Email address is required';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address';
        $messageType = 'error';
    } else {
        try {
            // Check if user exists and is verified
            $stmt = $pdo->prepare("SELECT id, first_name FROM users WHERE email = ? AND is_verified = 1");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch();
                
                // Generate a secure random token
                $token = bin2hex(random_bytes(32));
                
                // Create a token data array with expiry time
                $tokenData = [
                    'user_id' => $user['id'],
                    'email' => $email,
                    'first_name' => $user['first_name'],
                    'expires' => time() + 86400, // 24 hours
                    'created' => time()
                ];
                
                // Create the token directory if it doesn't exist
                $tokenDir = dirname(__FILE__) . '/tokens';
                if (!file_exists($tokenDir)) {
                    if (!mkdir($tokenDir, 0750, true)) {
                        throw new Exception("Failed to create token directory");
                    }
                    
                    // Create an index.php file to prevent directory listing
                    file_put_contents($tokenDir . '/index.php', '<?php // Silence is golden');
                    
                    // Create .htaccess to protect the directory
                    file_put_contents($tokenDir . '/.htaccess', 'Deny from all');
                }
                
                // Store token data in a file
                $tokenFile = $tokenDir . '/' . $token . '.json';
                if (!file_put_contents($tokenFile, json_encode($tokenData))) {
                    throw new Exception("Failed to store token data");
                }
                
                // Generate password reset link
                $resetLink = $site_url . "/reset-password.php?token=" . $token;
                
                // Prepare email content
                $subject = "Password Reset Request - " . $site_name;
                $emailContent = "
                <html>
                <head>
                    <title>Password Reset Request</title>
                </head>
                <body>
                    <p>Hello {$user['first_name']},</p>
                    <p>We received a request to reset your password. Please click the link below to set a new password:</p>
                    <p><a href='$resetLink'>Reset My Password</a></p>
                    <p>Or copy and paste this URL into your browser:</p>
                    <p>$resetLink</p>
                    <p>This link will expire in 24 hours.</p>
                    <p>If you did not request a password reset, please ignore this email.</p>
                    <p>Best regards,<br>$site_name Team</p>
                </body>
                </html>
                ";
                
                // Set email headers
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: $from_email" . "\r\n";
                
                // Try to send email with error handling
                $mailSent = @mail($email, $subject, $emailContent, $headers);
                if (!$mailSent) {
                    // Get mail error
                    $error = error_get_last();
                    error_log("Email sending error: " . ($error ? print_r($error, true) : 'Unknown error'));
                    
                    // Delete the token file if email fails
                    if (file_exists($tokenFile)) {
                        @unlink($tokenFile);
                    }
                    
                    $message = 'There was a problem sending the password reset email. Please try again later.';
                    $messageType = 'error';
                } else {
                    $message = 'If your email address exists in our database, you will receive a password recovery link shortly.';
                    $messageType = 'success';
                }
            } else {
                // For security reasons, show the same message even if the email is not found
                // or the account is not verified
                $message = 'If your email address exists in our database, you will receive a password recovery link shortly.';
                $messageType = 'success';
                
                // Log the failure for debugging purposes
                error_log("Password reset requested for non-existent or unverified email: $email");
            }
        } catch (Exception $e) {
            $message = 'Server error. Please try again later.';
            $messageType = 'error';
            error_log("Password reset error: " . $e->getMessage());
        }
    }
}

include 'includes/header.php';
?>

<div class="max-w-md mx-auto my-10">
    <div class="bg-white p-8 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-8">Forgot Your Password?</h2>
        
        <?php if (!empty($message)): ?>
            <div class="bg-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-100 border-l-4 border-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-500 text-<?php echo $messageType === 'success' ? 'green' : 'red'; ?>-700 p-4 mb-6" role="alert">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endif; ?>
        
        <p class="text-gray-600 mb-6">
            Enter your email address below, and we'll send you a link to reset your password.
        </p>
        
        <form method="POST" action="">
            <div class="mb-6">
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Email Address</label>
                <input type="email" id="email" name="email" required 
                       class="shadow appearance-none border rounded w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                       placeholder="Enter your email">
            </div>
            
            <div class="mb-6">
                <button type="submit" 
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded focus:outline-none focus:shadow-outline transition duration-150">
                    Send Reset Link
                </button>
            </div>
            
            <div class="text-center">
                <p class="text-gray-600">
                    Remembered your password? 
                    <a href="login.php" class="text-indigo-600 hover:text-indigo-800 transition duration-150">
                        Back to Login
                    </a>
                </p>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>