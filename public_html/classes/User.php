<?php
/**
 * User Model
 * 
 * Handles all user-related operations
 */
class User {
    private $db;
    private $data = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get a user by ID
     * 
     * @param int $userId User ID
     * @return bool True if user was found
     */
    public function getById($userId) {
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        
        if ($user) {
            $this->data = $user;
            return true;
        }
        
        return false;
    }
    
    /**
     * Get a user by email
     * 
     * @param string $email User email
     * @return bool True if user was found
     */
    public function getByEmail($email) {
        $user = $this->db->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);
        
        if ($user) {
            $this->data = $user;
            return true;
        }
        
        return false;
    }
    
    /**
     * Create a new user
     * 
     * @param array $userData User data
     * @return int|bool User ID on success, false on failure
     */
    public function create($userData) {
        // Validate required fields
        $requiredFields = ['email', 'password', 'first_name', 'last_name'];
        foreach ($requiredFields as $field) {
            if (empty($userData[$field])) {
                return false;
            }
        }
        
        // Hash password
        $userData['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
        
        // Generate verification code
        $userData['verification_code'] = bin2hex(random_bytes(16));
        $userData['created_at'] = date('Y-m-d H:i:s');
        $userData['is_verified'] = 0;
        $userData['is_admin'] = 0;
        
        try {
            $userId = $this->db->insert('users', $userData);
            
            if ($userId) {
                // Load the new user data
                $this->getById($userId);
                return $userId;
            }
        } catch (Exception $e) {
            error_log("User creation error: " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Update user data
     * 
     * @param array $userData User data to update
     * @return bool Success status
     */
    public function update($userData) {
        if (empty($this->data['id'])) {
            return false;
        }
        
        // Don't update these fields directly
        $protectedFields = ['id', 'password', 'is_admin', 'created_at'];
        foreach ($protectedFields as $field) {
            if (isset($userData[$field])) {
                unset($userData[$field]);
            }
        }
        
        try {
            $result = $this->db->update('users', $userData, 'id = ?', [$this->data['id']]);
            
            if ($result) {
                // Reload user data
                $this->getById($this->data['id']);
                return true;
            }
        } catch (Exception $e) {
            error_log("User update error: " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Change user password
     * 
     * @param string $currentPassword Current password
     * @param string $newPassword New password
     * @return bool Success status
     */
    public function changePassword($currentPassword, $newPassword) {
        if (empty($this->data['id'])) {
            return false;
        }
        
        // Verify current password
        if (!password_verify($currentPassword, $this->data['password'])) {
            return false;
        }
        
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        try {
            $result = $this->db->update(
                'users', 
                ['password' => $hashedPassword], 
                'id = ?', 
                [$this->data['id']]
            );
            
            if ($result) {
                // Reload user data
                $this->getById($this->data['id']);
                return true;
            }
        } catch (Exception $e) {
            error_log("Password change error: " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Verify a user account
     * 
     * @param string $verificationCode Verification code
     * @return bool Success status
     */
    public function verify($verificationCode) {
        try {
            $user = $this->db->fetchOne(
                "SELECT id FROM users WHERE verification_code = ? AND is_verified = 0", 
                [$verificationCode]
            );
            
            if ($user) {
                $result = $this->db->update(
                    'users', 
                    ['is_verified' => 1, 'verification_code' => null], 
                    'id = ?', 
                    [$user['id']]
                );
                
                return $result > 0;
            }
        } catch (Exception $e) {
            error_log("User verification error: " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Authenticate a user
     * 
     * @param string $email User email
     * @param string $password User password
     * @return bool Success status
     */
    public function authenticate($email, $password) {
        try {
            $user = $this->db->fetchOne(
                "SELECT * FROM users WHERE email = ?", 
                [$email]
            );
            
            if ($user && password_verify($password, $user['password'])) {
                // Check if the user is verified
                if ($user['is_verified'] != 1) {
                    return false;
                }
                
                $this->data = $user;
                return true;
            }
        } catch (Exception $e) {
            error_log("Authentication error: " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Create a password reset token
     * 
     * @param string $email User email
     * @return string|bool Token on success, false on failure
     */
    public function createPasswordResetToken($email) {
        try {
            $user = $this->db->fetchOne(
                "SELECT id, first_name FROM users WHERE email = ? AND is_verified = 1", 
                [$email]
            );
            
            if ($user) {
                // Generate a secure random token
                $token = bin2hex(random_bytes(32));
                
                // Create token directory if it doesn't exist
                $tokenDir = dirname(__FILE__) . '/../tokens';
                if (!file_exists($tokenDir)) {
                    if (!mkdir($tokenDir, 0750, true)) {
                        throw new Exception("Failed to create token directory");
                    }
                    
                    // Create an index.php file to prevent directory listing
                    file_put_contents($tokenDir . '/index.php', '<?php // Silence is golden');
                    
                    // Create .htaccess to protect the directory
                    file_put_contents($tokenDir . '/.htaccess', 'Deny from all');
                }
                
                // Create token data
                $tokenData = [
                    'user_id' => $user['id'],
                    'email' => $email,
                    'first_name' => $user['first_name'],
                    'expires' => time() + 86400, // 24 hours
                    'created' => time()
                ];
                
                // Store token data in a file
                $tokenFile = $tokenDir . '/' . $token . '.json';
                if (file_put_contents($tokenFile, json_encode($tokenData))) {
                    return $token;
                }
            }
        } catch (Exception $e) {
            error_log("Password reset token error: " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Validate a password reset token
     * 
     * @param string $token Password reset token
     * @return array|bool Token data on success, false on failure
     */
    public function validatePasswordResetToken($token) {
        $tokenFile = dirname(__FILE__) . '/../tokens/' . $token . '.json';
        
        if (!file_exists($tokenFile)) {
            return false;
        }
        
        $tokenData = json_decode(file_get_contents($tokenFile), true);
        if (!$tokenData) {
            return false;
        }
        
        // Check if token is expired
        if (time() > $tokenData['expires']) {
            // Delete expired token file
            @unlink($tokenFile);
            return false;
        }
        
        return $tokenData;
    }
    
    /**
     * Reset password using a token
     * 
     * @param string $token Password reset token
     * @param string $newPassword New password
     * @return bool Success status
     */
    public function resetPassword($token, $newPassword) {
        $tokenData = $this->validatePasswordResetToken($token);
        
        if (!$tokenData) {
            return false;
        }
        
        // Hash the new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        try {
            $result = $this->db->update(
                'users', 
                ['password' => $hashedPassword], 
                'id = ?', 
                [$tokenData['user_id']]
            );
            
            if ($result) {
                // Delete the token file
                $tokenFile = dirname(__FILE__) . '/../tokens/' . $token . '.json';
                if (file_exists($tokenFile)) {
                    @unlink($tokenFile);
                }
                
                return true;
            }
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Check if user is an admin
     * 
     * @return bool True if user is an admin
     */
    public function isAdmin() {
        return !empty($this->data['is_admin']) && $this->data['is_admin'] == 1;
    }
    
    /**
     * Get user data
     * 
     * @param string $key Data key
     * @param mixed $default Default value if key not found
     * @return mixed User data
     */
    public function get($key = null, $default = null) {
        if ($key === null) {
            return $this->data;
        }
        
        return $this->data[$key] ?? $default;
    }
}