<?php
/**
 * Helper Functions
 */

/**
 * Check if the user is logged in
 * 
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if the user is an admin
 * 
 * @return bool
 */
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

/**
 * Require login to access a page
 * 
 * @return void
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('/login');
    }
}

/**
 * Require admin rights to access a page
 * 
 * @return void
 */
function requireAdmin() {
    if (!isLoggedIn() || !isAdmin()) {
        redirect('/');
    }
}

/**
 * Redirect to a URL
 * 
 * @param string $url
 * @return void
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Flash message for one-time display
 * 
 * @param string $message
 * @param string $type (success, error, info, warning)
 * @return void
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Get and clear flash message
 * 
 * @return array|null
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message']) && isset($_SESSION['flash_type'])) {
        $message = [
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type']
        ];
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        return $message;
    }
    
    return null;
}