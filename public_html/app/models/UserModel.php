<?php
/**
 * User Model
 */
class UserModel extends BaseModel {
    protected $table = 'users';
    
    /**
     * Create a new user
     * 
     * @param string $email
     * @param string $password
     * @param string $firstName
     * @param string $lastName
     * @param string $verificationCode
     * @return int|false User ID or false on failure
     */
    public function createUser($email, $password, $firstName, $lastName, $verificationCode = null) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $data = [
            'email' => $email,
            'password' => $hashedPassword,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'verification_code' => $verificationCode,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->create($data);
    }
    
    /**
     * Verify user account
     * 
     * @param string $code Verification code
     * @return bool
     */
    public function verifyUser($code) {
        $user = $this->findOneBy('verification_code', $code);
        
        if (!$user || $user['is_verified']) {
            return false;
        }
        
        return $this->update($user['id'], [
            'is_verified' => 1,
            'verification_code' => null
        ]);
    }
    
    /**
     * Authenticate user
     * 
     * @param string $email
     * @param string $password
     * @return array|false User data or false on failure
     */
    public function authenticate($email, $password) {
        $user = $this->findOneBy('email', $email);
        
        if (!$user) {
            return false;
        }
        
        if (!password_verify($password, $user['password'])) {
            return false;
        }
        
        return $user;
    }
    
    /**
     * Update password
     * 
     * @param int $userId
     * @param string $newPassword
     * @return bool
     */
    public function updatePassword($userId, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        return $this->update($userId, [
            'password' => $hashedPassword
        ]);
    }
    
    /**
     * Generate password reset token
     * 
     * @param string $email
     * @return string|false Token or false on failure
     */
    public function generateResetToken($email) {
        $user = $this->findOneBy('email', $email);
        
        if (!$user) {
            return false;
        }
        
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 day'));
        
        $updated = $this->update($user['id'], [
            'reset_token' => $token,
            'reset_expires' => $expires
        ]);
        
        return $updated ? $token : false;
    }
    
    /**
     * Validate reset token
     * 
     * @param string $token
     * @return array|false User data or false if invalid
     */
    public function validateResetToken($token) {
        $user = $this->findOneBy('reset_token', $token);
        
        if (!$user) {
            return false;
        }
        
        if (strtotime($user['reset_expires']) < time()) {
            return false;
        }
        
        return $user;
    }
}

/**
 * Get all users with pagination
 * 
 * @param int $offset
 * @param int $limit
 * @return array
 */
public function getAllPaginated($offset = 0, $limit = 10) {
    $sql = "SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([$limit, $offset]);
    return $stmt->fetchAll();
}

/**
 * Get recent users
 * 
 * @param int $limit
 * @return array
 */
public function getRecent($limit = 5) {
    $sql = "SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT ?";
    $stmt = $this->db->prepare($sql);
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}