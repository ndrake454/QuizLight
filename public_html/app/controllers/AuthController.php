<?php
/**
 * Auth Controller
 * 
 * Handles user authentication, registration, and account verification
 */
class AuthController extends BaseController {
    private $userModel;
    
    public function __construct() {
        // Call parent constructor first
        parent::__construct();
        
        // Then initialize your model
        $this->userModel = new UserModel();
    }
    
    /**
     * Display login form
     * 
     * @return void
     */
    public function login() {
        // Redirect if already logged in
        if (isLoggedIn()) {
            $this->redirect('/');
        }
        
        $this->render('auth/login', [
            'pageTitle' => 'Login'
        ]);
    }
    
    /**
     * Process login form
     * 
     * @return void
     */
    public function processLogin() {
        // Redirect if already logged in
        if (isLoggedIn()) {
            $this->redirect('/');
        }
        
        // Validate input
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            setFlashMessage('All fields are required', 'error');
            $this->storeOldInput();
            $this->redirect('/login');
            return;
        }
        
        // Attempt to authenticate
        $user = $this->userModel->authenticate($email, $password);
        
        if (!$user) {
            setFlashMessage('Invalid credentials', 'error');
            $this->storeOldInput(['email' => $email]);
            $this->redirect('/login');
            return;
        }
        
        // Check if account is verified
        if (!$user['is_verified']) {
            setFlashMessage('Your account has not been verified. Please check your email for verification instructions.', 'error');
            $this->redirect('/login');
            return;
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['is_admin'] = $user['is_admin'] ?? 0;
        
        // Redirect to dashboard
        $this->redirect('/');
    }
    
    /**
     * Display registration form
     * 
     * @return void
     */
    public function register() {
        // Redirect if already logged in
        if (isLoggedIn()) {
            $this->redirect('/');
        }
        
        $this->render('auth/register', [
            'pageTitle' => 'Register'
        ]);
    }
    
    /**
     * Process registration form
     * 
     * @return void
     */
    public function processRegister() {
        // Redirect if already logged in
        if (isLoggedIn()) {
            $this->redirect('/');
        }
        
        // Validate input
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Check for missing fields
        $missingFields = $this->validateRequired(['first_name', 'last_name', 'email', 'password', 'confirm_password']);
        
        if (!empty($missingFields)) {
            setFlashMessage('All fields are required', 'error');
            $this->storeOldInput();
            $this->redirect('/register');
            return;
        }
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlashMessage('Please enter a valid email address', 'error');
            $this->storeOldInput();
            $this->redirect('/register');
            return;
        }
        
        // Check password length
        if (strlen($password) < 8) {
            setFlashMessage('Password must be at least 8 characters long', 'error');
            $this->storeOldInput();
            $this->redirect('/register');
            return;
        }
        
        // Check if passwords match
        if ($password !== $confirmPassword) {
            setFlashMessage('Passwords do not match', 'error');
            $this->storeOldInput();
            $this->redirect('/register');
            return;
        }
        
        // Check if email already exists
        $existingUser = $this->userModel->findOneBy('email', $email);
        
        if ($existingUser) {
            setFlashMessage('Email is already registered. Please use a different email or login.', 'error');
            $this->storeOldInput();
            $this->redirect('/register');
            return;
        }
        
        // Generate verification code
        $verificationCode = bin2hex(random_bytes(16));
        
        // Create user
        $userId = $this->userModel->createUser($email, $password, $firstName, $lastName, $verificationCode);
        
        if (!$userId) {
            setFlashMessage('Registration failed. Please try again later.', 'error');
            $this->storeOldInput();
            $this->redirect('/register');
            return;
        }
        
        // Send verification email
        $mailSent = $this->sendVerificationEmail($email, $firstName, $verificationCode);
        
        // Set success message
        $message = 'Registration successful! ';
        $message .= $mailSent ? 'Please check your email to verify your account.' : 'Please contact admin for account verification.';
        
        setFlashMessage($message, 'success');
        $this->redirect('/login');
    }
    
    /**
     * Send verification email
     * 
     * @param string $email
     * @param string $firstName
     * @param string $verificationCode
     * @return bool Success status
     */
    private function sendVerificationEmail($email, $firstName, $verificationCode) {
        // Generate verification link
        $verificationLink = SITE_URL . "/verify?code=" . $verificationCode;
        
        // Prepare email content
        $subject = "Verify your account at " . SITE_NAME;
        $message = "
        <html>
        <head>
            <title>Verify Your Account</title>
        </head>
        <body>
            <p>Hello $firstName,</p>
            <p>Thank you for registering at " . SITE_NAME . ". Please click the link below to verify your account:</p>
            <p><a href='$verificationLink'>Verify My Account</a></p>
            <p>Or copy and paste this URL into your browser:</p>
            <p>$verificationLink</p>
            <p>This link will expire in 24 hours.</p>
            <p>Best regards,<br>" . SITE_NAME . " Team</p>
        </body>
        </html>
        ";
        
        // Set email headers
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . FROM_EMAIL . "\r\n";
        
        // Try to send email
        return mail($email, $subject, $message, $headers);
    }
    
    /**
     * Verify user account
     * 
     * @return void
     */
    public function verify() {
        $code = $_GET['code'] ?? '';
        
        if (empty($code)) {
            setFlashMessage('Verification code is required.', 'error');
            $this->redirect('/login');
            return;
        }
        
        $verified = $this->userModel->verifyUser($code);
        
        if ($verified) {
            setFlashMessage('Your account has been successfully verified! You can now log in.', 'success');
        } else {
            setFlashMessage('Invalid verification code or account is already verified.', 'error');
        }
        
        $this->redirect('/login');
    }
    
    /**
     * Log out user
     * 
     * @return void
     */
    public function logout() {
        // Unset all session variables
        $_SESSION = [];
        
        // If it's desired to kill the session, also delete the session cookie.
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Finally, destroy the session.
        session_destroy();
        
        $this->redirect('/login');
    }
    
    /**
     * Display forgot password form
     * 
     * @return void
     */
    public function forgotPassword() {
        // Redirect if already logged in
        if (isLoggedIn()) {
            $this->redirect('/');
        }
        
        $this->render('auth/forgot-password', [
            'pageTitle' => 'Forgot Password'
        ]);
    }
    
    /**
     * Process forgot password form
     * 
     * @return void
     */
    public function processForgotPassword() {
        // Redirect if already logged in
        if (isLoggedIn()) {
            $this->redirect('/');
        }
        
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email)) {
            setFlashMessage('Email address is required', 'error');
            $this->redirect('/forgot-password');
            return;
        }
        
        // Generate reset token
        $token = $this->userModel->generateResetToken($email);
        
        // Always show success message to prevent email enumeration
        $message = 'If the email address exists in our database, a password reset link has been sent.';
        
        // Send reset email if token was generated
        if ($token) {
            $user = $this->userModel->findOneBy('email', $email);
            $this->sendResetEmail($email, $user['first_name'], $token);
        }
        
        setFlashMessage($message, 'success');
        $this->redirect('/login');
    }
    
    /**
     * Send password reset email
     * 
     * @param string $email
     * @param string $firstName
     * @param string $token
     * @return bool Success status
     */
    private function sendResetEmail($email, $firstName, $token) {
        // Generate reset link
        $resetLink = SITE_URL . "/reset-password?token=" . $token;
        
        // Prepare email content
        $subject = "Reset your password at " . SITE_NAME;
        $message = "
        <html>
        <head>
            <title>Reset Your Password</title>
        </head>
        <body>
            <p>Hello $firstName,</p>
            <p>We received a request to reset your password. Please click the link below to reset your password:</p>
            <p><a href='$resetLink'>Reset Password</a></p>
            <p>Or copy and paste this URL into your browser:</p>
            <p>$resetLink</p>
            <p>This link will expire in 24 hours.</p>
            <p>If you did not request a password reset, please ignore this email.</p>
            <p>Best regards,<br>" . SITE_NAME . " Team</p>
        </body>
        </html>
        ";
        
        // Set email headers
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . FROM_EMAIL . "\r\n";
        
        // Try to send email
        return mail($email, $subject, $message, $headers);
    }
    
    /**
     * Display reset password form
     * 
     * @return void
     */
    public function resetPassword() {
        // Redirect if already logged in
        if (isLoggedIn()) {
            $this->redirect('/');
        }
        
        $token = $_GET['token'] ?? '';
        
        if (empty($token)) {
            setFlashMessage('Reset token is required.', 'error');
            $this->redirect('/login');
            return;
        }
        
        // Validate token
        $user = $this->userModel->validateResetToken($token);
        
        if (!$user) {
            setFlashMessage('The password reset link is invalid or has expired.', 'error');
            $this->redirect('/login');
            return;
        }
        
        $this->render('auth/reset-password', [
            'pageTitle' => 'Reset Password',
            'token' => $token
        ]);
    }
    
    /**
     * Process reset password form
     * 
     * @return void
     */
    public function processResetPassword() {
        // Redirect if already logged in
        if (isLoggedIn()) {
            $this->redirect('/');
        }
        
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($token)) {
            setFlashMessage('Reset token is required.', 'error');
            $this->redirect('/login');
            return;
        }
        
        // Validate token
        $user = $this->userModel->validateResetToken($token);
        
        if (!$user) {
            setFlashMessage('The password reset link is invalid or has expired.', 'error');
            $this->redirect('/login');
            return;
        }
        
        // Validate password
        if (empty($password)) {
            setFlashMessage('Password is required', 'error');
            $this->redirect('/reset-password?token=' . $token);
            return;
        }
        
        if (strlen($password) < 8) {
            setFlashMessage('Password must be at least 8 characters long', 'error');
            $this->redirect('/reset-password?token=' . $token);
            return;
        }
        
        if ($password !== $confirmPassword) {
            setFlashMessage('Passwords do not match', 'error');
            $this->redirect('/reset-password?token=' . $token);
            return;
        }
        
        // Update password
        $updated = $this->userModel->update($user['id'], [
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'reset_token' => null,
            'reset_expires' => null
        ]);
        
        if ($updated) {
            setFlashMessage('Your password has been reset successfully. You can now log in with your new password.', 'success');
        } else {
            setFlashMessage('Failed to update password. Please try again.', 'error');
        }
        
        $this->redirect('/login');
    }
}