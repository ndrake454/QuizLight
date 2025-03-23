<?php
/**
 * Authentication Controller
 * 
 * Handles user authentication, registration, and password management
 */
class AuthController {
    private $user;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->user = new User();
    }
    
    /**
     * Handle login request
     * 
     * @param array $data Login form data
     * @return array Result data
     */
    public function login($data) {
        $result = [
            'success' => false,
            'message' => '',
            'redirect' => ''
        ];
        
        // Validate input
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $result['message'] = 'Email and password are required';
            return $result;
        }
        
        // Authenticate user
        if ($this->user->authenticate($email, $password)) {
            // Set session variables
            $_SESSION['user_id'] = $this->user->get('id');
            $_SESSION['email'] = $this->user->get('email');
            $_SESSION['first_name'] = $this->user->get('first_name');
            $_SESSION['is_admin'] = $this->user->isAdmin() ? 1 : 0;
            
            $result['success'] = true;
            $result['redirect'] = '/';
        } else {
            $result['message'] = 'Invalid email or password';
        }
        
        return $result;
    }
    
    /**
     * Handle registration request
     * 
     * @param array $data Registration form data
     * @return array Result data
     */
    public function register($data) {
        $result = [
            'success' => false,
            'message' => ''
        ];
        
        // Validate input
        $firstName = trim($data['first_name'] ?? '');
        $lastName = trim($data['last_name'] ?? '');
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';
        
        if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($confirmPassword)) {
            $result['message'] = 'All fields are required';
            return $result;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result['message'] = 'Please enter a valid email address';
            return $result;
        }
        
        if (strlen($password) < 8) {
            $result['message'] = 'Password must be at least 8 characters long';
            return $result;
        }
        
        if ($password !== $confirmPassword) {
            $result['message'] = 'Passwords do not match';
            return $result;
        }
        
        // Check if user already exists
        if ($this->user->getByEmail($email)) {
            $result['message'] = 'Email is already registered. Please use a different email or login.';
            return $result;
        }
        
        // Create user
        $userData = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => $password
        ];
        
        $userId = $this->user->create($userData);
        
        if ($userId) {
            // Send verification email
            $this->sendVerificationEmail($userId, $email, $firstName);
            
            $result['success'] = true;
            $result['message'] = 'Registration successful! Please check your email to verify your account.';
        } else {
            $result['message'] = 'Server error. Please try again later.';
        }
        
        return $result;
    }
    
    /**
     * Handle logout request
     * 
     * @return string Redirect URL
     */
    public function logout() {
        // End the session
        session_unset();
        session_destroy();
        
        return '/';
    }
    
    /**
     * Handle verify account request
     * 
     * @param string $code Verification code
     * @return array Result data
     */
    public function verify($code) {
        $result = [
            'success' => false,
            'message' => ''
        ];
        
        if (empty($code)) {
            $result['message'] = 'Verification code is required.';
            return $result;
        }
        
        if ($this->user->verify($code)) {
            $result['success'] = true;
            $result['message'] = 'Your account has been successfully verified! You can now log in.';
        } else {
            $result['message'] = 'Invalid verification code or account is already verified.';
        }
        
        return $result;
    }
    
    /**
     * Handle forgot password request
     * 
     * @param array $data Forgot password form data
     * @return array Result data
     */
    public function forgotPassword($data) {
        $result = [
            'success' => false,
            'message' => ''
        ];
        
        $email = trim($data['email'] ?? '');
        
        if (empty($email)) {
            $result['message'] = 'Email address is required';
            return $result;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result['message'] = 'Please enter a valid email address';
            return $result;
        }
        
        // Create password reset token
        $token = $this->user->createPasswordResetToken($email);
        
        if ($token) {
            // Send password reset email
            $this->sendPasswordResetEmail($email, $token);
            
            $result['success'] = true;
            $result['message'] = 'If your email address exists in our database, you will receive a password recovery link shortly.';
        } else {
            // For security reasons, show the same message even if email is not found
            $result['success'] = true;
            $result['message'] = 'If your email address exists in our database, you will receive a password recovery link shortly.';
        }
        
        return $result;
    }
    
    /**
     * Handle reset password request
     * 
     * @param array $data Reset password form data
     * @param string $token Reset token
     * @return array Result data
     */
    public function resetPassword($data, $token) {
        $result = [
            'success' => false,
            'message' => ''
        ];
        
        // Validate token
        $tokenData = $this->user->validatePasswordResetToken($token);
        if (!$tokenData) {
            $result['message'] = 'The password reset link is invalid or has expired.';
            return $result;
        }
        
        // Validate passwords
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';
        
        if (empty($password) || empty($confirmPassword)) {
            $result['message'] = 'Both fields are required';
            return $result;
        }
        
        if (strlen($password) < 8) {
            $result['message'] = 'Password must be at least 8 characters long';
            return $result;
        }
        
        if ($password !== $confirmPassword) {
            $result['message'] = 'Passwords do not match';
            return $result;
        }
        
        // Reset password
        if ($this->user->resetPassword($token, $password)) {
            $result['success'] = true;
            $result['message'] = 'Your password has been reset successfully. You can now log in with your new password.';
        } else {
            $result['message'] = 'Failed to update password. Please try again.';
        }
        
        return $result;
    }
    
    /**
     * Send verification email
     * 
     * @param int $userId User ID
     * @param string $email User email
     * @param string $firstName User first name
     * @return bool Success status
     */
    private function sendVerificationEmail($userId, $email, $firstName) {
        // Get verification code for user
        $db = Database::getInstance();
        $verificationCode = $db->fetchValue(
            "SELECT verification_code FROM users WHERE id = ?", 
            [$userId]
        );
        
        if (!$verificationCode) {
            return false;
        }
        
        // Generate verification link
        $siteUrl = Config::get('site_url');
        $siteName = Config::get('site_name');
        $fromEmail = Config::get('from_email');
        
        $verificationLink = $siteUrl . "/verify.php?code=" . $verificationCode;
        
        // Prepare email content
        $subject = "Verify your account at " . $siteName;
        $message = "
        <html>
        <head>
            <title>Verify Your Account</title>
        </head>
        <body>
            <p>Hello $firstName,</p>
            <p>Thank you for registering at $siteName. Please click the link below to verify your account:</p>
            <p><a href='$verificationLink'>Verify My Account</a></p>
            <p>Or copy and paste this URL into your browser:</p>
            <p>$verificationLink</p>
            <p>This link will expire in 24 hours.</p>
            <p>Best regards,<br>$siteName Team</p>
        </body>
        </html>
        ";
        
        // Set email headers
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: $fromEmail" . "\r\n";
        
        // Send email
        return mail($email, $subject, $message, $headers);
    }
    
    /**
     * Handle profile update request
     * 
     * @param array $data Profile form data
     * @param int $userId User ID
     * @return array Result data
     */
    public function updateProfile($data, $userId) {
        $result = [
            'success' => false,
            'message' => ''
        ];
        
        // Load user data
        if (!$this->user->getById($userId)) {
            $result['message'] = 'User not found';
            return $result;
        }
        
        // Validate input
        $firstName = trim($data['first_name'] ?? '');
        $lastName = trim($data['last_name'] ?? '');
        $email = trim($data['email'] ?? '');
        
        if (empty($firstName) || empty($lastName) || empty($email)) {
            $result['message'] = 'All fields are required';
            return $result;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result['message'] = 'Please enter a valid email address';
            return $result;
        }
        
        // Check if email is already used by another user
        if ($email !== $this->user->get('email')) {
            $tempUser = new User();
            if ($tempUser->getByEmail($email)) {
                $result['message'] = 'Email address is already in use';
                return $result;
            }
        }
        
        // Update profile
        $userData = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email
        ];
        
        if ($this->user->update($userData)) {
            // Update session data
            $_SESSION['email'] = $email;
            $_SESSION['first_name'] = $firstName;
            
            $result['success'] = true;
            $result['message'] = 'Profile updated successfully';
        } else {
            $result['message'] = 'Error updating profile';
        }
        
        return $result;
    }
    
    /**
     * Handle change password request
     * 
     * @param array $data Change password form data
     * @param int $userId User ID
     * @return array Result data
     */
    public function changePassword($data, $userId) {
        $result = [
            'success' => false,
            'message' => ''
        ];
        
        // Load user data
        if (!$this->user->getById($userId)) {
            $result['message'] = 'User not found';
            return $result;
        }
        
        // Validate input
        $currentPassword = $data['current_password'] ?? '';
        $newPassword = $data['new_password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $result['message'] = 'All password fields are required';
            return $result;
        }
        
        if ($newPassword !== $confirmPassword) {
            $result['message'] = 'New passwords do not match';
            return $result;
        }
        
        if (strlen($newPassword) < 8) {
            $result['message'] = 'New password must be at least 8 characters long';
            return $result;
        }
        
        // Change password
        if ($this->user->changePassword($currentPassword, $newPassword)) {
            $result['success'] = true;
            $result['message'] = 'Password changed successfully';
        } else {
            $result['message'] = 'Current password is incorrect';
        }
        
        return $result;
    }
    
    /**
     * Reset user statistics
     * 
     * @param int $userId User ID
     * @return array Result data
     */
    public function resetStatistics($userId) {
        $result = [
            'success' => false,
            'message' => ''
        ];
        
        $db = Database::getInstance();
        
        try {
            // Start a transaction to ensure all or nothing is deleted
            $db->beginTransaction();
            
            // Delete quiz attempts
            $db->execute("DELETE FROM user_attempts WHERE user_id = ?", [$userId]);
            
            // Delete quiz answers
            $db->execute("DELETE FROM quiz_answers WHERE user_id = ?", [$userId]);
            
            // Delete user question status (ratings)
            $db->execute("DELETE FROM user_question_status WHERE user_id = ?", [$userId]);
            
            // Commit the transaction
            $db->commit();
            
            $result['success'] = true;
            $result['message'] = 'Your statistics have been reset successfully';
        } catch (Exception $e) {
            // Rollback the transaction if anything fails
            $db->rollback();
            
            $result['message'] = 'Error resetting statistics: ' . $e->getMessage();
            error_log("Statistics reset error: " . $e->getMessage());
        }
        
        return $result;
    }
}
);
    }
    
    /**
     * Send password reset email
     * 
     * @param string $email User email
     * @param string $token Reset token
     * @return bool Success status
     */
    private function sendPasswordResetEmail($email, $token) {
        // Get user data from token
        $tokenFile = dirname(__FILE__) . '/../tokens/' . $token . '.json';
        if (!file_exists($tokenFile)) {
            return false;
        }
        
        $tokenData = json_decode(file_get_contents($tokenFile), true);
        if (!$tokenData) {
            return false;
        }
        
        // Generate reset link
        $siteUrl = Config::get('site_url');
        $siteName = Config::get('site_name');
        $fromEmail = Config::get('from_email');
        
        $resetLink = $siteUrl . "/reset-password.php?token=" . $token;
        
        // Prepare email content
        $subject = "Password Reset Request - " . $siteName;
        $message = "
        <html>
        <head>
            <title>Password Reset Request</title>
        </head>
        <body>
            <p>Hello {$tokenData['first_name']},</p>
            <p>We received a request to reset your password. Please click the link below to set a new password:</p>
            <p><a href='$resetLink'>Reset My Password</a></p>
            <p>Or copy and paste this URL into your browser:</p>
            <p>$resetLink</p>
            <p>This link will expire in 24 hours.</p>
            <p>If you did not request a password reset, please ignore this email.</p>
            <p>Best regards,<br>$siteName Team</p>
        </body>
        </html>
        ";
        
        // Set email headers
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: $fromEmail" . "\r\n";
        
        // Send email
        return mail($email, $subject, $message, $headers