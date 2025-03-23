<?php
/**
 * Email Service
 * 
 * Handles email sending
 */
class EmailService {
    /**
     * Send an email
     * 
     * @param string $to
     * @param string $subject
     * @param string $message
     * @param array $headers
     * @return bool
     */
    public function send($to, $subject, $message, $headers = []) {
        // Set default headers if not provided
        if (empty($headers)) {
            $headers = [
                'From' => FROM_EMAIL,
                'Reply-To' => FROM_EMAIL,
                'MIME-Version' => '1.0',
                'Content-Type' => 'text/html; charset=UTF-8'
            ];
        }
        
        // Convert headers array to string
        $headerString = '';
        foreach ($headers as $key => $value) {
            $headerString .= "$key: $value\r\n";
        }
        
        // Send email
        return mail($to, $subject, $message, $headerString);
    }
    
    /**
     * Send a verification email
     * 
     * @param string $to
     * @param string $name
     * @param string $verificationCode
     * @return bool
     */
    public function sendVerificationEmail($to, $name, $verificationCode) {
        $subject = "Verify your account at " . SITE_NAME;
        
        $verificationLink = SITE_URL . "/verify?code=" . $verificationCode;
        
        $message = "
        <html>
        <head>
            <title>Verify Your Account</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #3a506b; color: white; padding: 10px 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .button { display: inline-block; background-color: #4a69bd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>" . SITE_NAME . "</h1>
                </div>
                <div class='content'>
                    <p>Hello $name,</p>
                    <p>Thank you for registering at " . SITE_NAME . ". Please click the button below to verify your account:</p>
                    <p style='text-align: center;'>
                        <a href='$verificationLink' class='button'>Verify My Account</a>
                    </p>
                    <p>Or copy and paste this URL into your browser:</p>
                    <p>$verificationLink</p>
                    <p>This link will expire in 24 hours.</p>
                </div>
                <div class='footer'>
                    <p>Best regards,<br>" . SITE_NAME . " Team</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->send($to, $subject, $message);
    }
    
    /**
     * Send a password reset email
     * 
     * @param string $to
     * @param string $name
     * @param string $resetToken
     * @return bool
     */
    public function sendPasswordResetEmail($to, $name, $resetToken) {
        $subject = "Reset your password at " . SITE_NAME;
        
        $resetLink = SITE_URL . "/reset-password?token=" . $resetToken;
        
        $message = "
        <html>
        <head>
            <title>Reset Your Password</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #3a506b; color: white; padding: 10px 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .button { display: inline-block; background-color: #4a69bd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>" . SITE_NAME . "</h1>
                </div>
                <div class='content'>
                    <p>Hello $name,</p>
                    <p>We received a request to reset your password. Please click the button below to reset your password:</p>
                    <p style='text-align: center;'>
                        <a href='$resetLink' class='button'>Reset Password</a>
                    </p>
                    <p>Or copy and paste this URL into your browser:</p>
                    <p>$resetLink</p>
                    <p>This link will expire in 24 hours.</p>
                    <p>If you did not request a password reset, please ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>Best regards,<br>" . SITE_NAME . " Team</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->send($to, $subject, $message);
    }
    
    /**
     * Send a welcome email
     * 
     * @param string $to
     * @param string $name
     * @return bool
     */
    public function sendWelcomeEmail($to, $name) {
        $subject = "Welcome to " . SITE_NAME;
        
        $message = "
        <html>
        <head>
            <title>Welcome to " . SITE_NAME . "</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #3a506b; color: white; padding: 10px 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .button { display: inline-block; background-color: #4a69bd; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; }
                .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>" . SITE_NAME . "</h1>
                </div>
                <div class='content'>
                    <p>Hello $name,</p>
                    <p>Welcome to " . SITE_NAME . "! We're excited to have you on board.</p>
                    <p>Here are a few things you can do to get started:</p>
                    <ul>
                        <li>Take a quiz in your area of interest</li>
                        <li>Explore different quiz categories</li>
                        <li>Track your progress in your profile</li>
                    </ul>
                    <p style='text-align: center;'>
                        <a href='" . SITE_URL . "' class='button'>Get Started</a>
                    </p>
                </div>
                <div class='footer'>
                    <p>Best regards,<br>" . SITE_NAME . " Team</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->send($to, $subject, $message);
    }
}