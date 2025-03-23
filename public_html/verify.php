<?php
require_once 'config.php';
$pageTitle = 'Verify Account';

$message = '';
$messageType = '';

// Check if verification code is provided
if (isset($_GET['code']) && !empty($_GET['code'])) {
    $code = $_GET['code'];
    
    try {
        // Check if verification code exists and is valid
        $stmt = $pdo->prepare("SELECT id FROM users WHERE verification_code = ? AND is_verified = 0");
        $stmt->execute([$code]);
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch();
            
            // Update user to verified status
            $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = ?");
            
            if ($stmt->execute([$user['id']])) {
                $message = 'Your account has been successfully verified! You can now log in.';
                $messageType = 'success';
            } else {
                $message = 'There was a problem verifying your account. Please try again or contact support.';
                $messageType = 'error';
            }
        } else {
            $message = 'Invalid verification code or account is already verified.';
            $messageType = 'error';
        }
    } catch (PDOException $e) {
        $message = 'Server error. Please try again later.';
        $messageType = 'error';
        error_log("Verification error: " . $e->getMessage());
    }
} else {
    $message = 'Verification code is required.';
    $messageType = 'error';
}

include 'includes/header.php';
?>

<div class="max-w-md mx-auto my-10">
    <div class="bg-white p-8 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-8">Account Verification</h2>
        
        <?php if ($messageType === 'success'): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
            
            <div class="text-center mt-6">
                <a href="login.php" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded focus:outline-none focus:shadow-outline transition duration-150">
                    Proceed to Login
                </a>
            </div>
        <?php else: ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
            
            <div class="text-center mt-6">
                <a href="/" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-150">
                    Return to Home
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>