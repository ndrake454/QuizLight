<?php
/**
 * Simplified Front Controller
 * 
 * This version routes all requests to the original files
 * to ensure the application continues working while we 
 * implement the new architecture.
 */

// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start(['cookie_httponly' => true]);
}

// Define application root path
define('APP_ROOT', dirname(__FILE__));

// Include the original config.php
if (file_exists(APP_ROOT . '/config.php')) {
    require_once APP_ROOT . '/config.php';
} else {
    // If original config doesn't exist, create a minimal one
    $host = 'localhost';
    $dbname = '-';
    $username = '-';
    $password = '-';
    $site_name = 'QuizLight';
    $site_url = '';
    $from_email = '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    } catch(PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
    
    // Helper functions from original config
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    function isAdmin() {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
    }
    
    function requireLogin() {
        if (!isLoggedIn()) {
            header("Location: /login.php");
            exit;
        }
    }
    
    function requireAdmin() {
        if (!isLoggedIn() || !isAdmin()) {
            header("Location: /");
            exit;
        }
    }
}

// Extract the request URI
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

// Remove query string if present
if (($pos = strpos($requestUri, '?')) !== false) {
    $requestUri = substr($requestUri, 0, $pos);
}

// Remove trailing slash if not root
if ($requestUri !== '/' && substr($requestUri, -1) === '/') {
    $requestUri = rtrim($requestUri, '/');
}

// Get the path without any base URI
$path = $requestUri;

// Determine which file to include
if ($path === '/' || $path === '') {
    // Home page
    include APP_ROOT . '/index.html.php';
} else {
    // Remove leading slash
    $path = ltrim($path, '/');
    
    // Check if the file exists
    if (file_exists(APP_ROOT . '/' . $path . '.php')) {
        include APP_ROOT . '/' . $path . '.php';
    } 
    // Try without extension
    elseif (file_exists(APP_ROOT . '/' . $path)) {
        include APP_ROOT . '/' . $path;
    }
    // Check common endpoints
    elseif ($path === 'login') {
        include APP_ROOT . '/login.php';
    }
    elseif ($path === 'register') {
        include APP_ROOT . '/register.php';
    }
    elseif ($path === 'logout') {
        include APP_ROOT . '/logout.php';
    }
    elseif ($path === 'profile') {
        include APP_ROOT . '/profile.php';
    }
    elseif ($path === 'quiz') {
        include APP_ROOT . '/quiz.php';
    }
    elseif ($path === 'quiz_select') {
        include APP_ROOT . '/quiz_select.php';
    }
    elseif ($path === 'about') {
        include APP_ROOT . '/about.php';
    }
    elseif ($path === 'privacy') {
        include APP_ROOT . '/privacy.php';
    }
    elseif ($path === 'edit_profile') {
        include APP_ROOT . '/edit_profile.php';
    }
    elseif ($path === 'reset_quiz') {
        include APP_ROOT . '/reset_quiz.php';
    }
    elseif ($path === 'notifications') {
        include APP_ROOT . '/notifications.php';
    }
    elseif ($path === 'verify') {
        include APP_ROOT . '/verify.php';
    }
    elseif ($path === 'reset-password' || $path === 'reset_password') {
        include APP_ROOT . '/reset-password.php';
    }
    elseif ($path === 'forgot-password' || $path === 'forgot_password') {
        include APP_ROOT . '/forgot-password.php';
    }
    // File not found, show 404
    else {
        header("HTTP/1.1 404 Not Found");
        echo '<h1>404 Not Found</h1>';
        echo '<p>The page you requested could not be found.</p>';
    }
}