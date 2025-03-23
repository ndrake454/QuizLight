<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start(['cookie_httponly' => true, 'cookie_secure' => true]); // Secure cookies
}

// Database connection parameters
$host = 'localhost';
$dbname = '-';  // Replace with your database name
$username = '-';     // Replace with your MySQL username
$password = '-';     // Replace with your MySQL password

// Site settings
$site_name = "QuizLight";
$site_url = "https://www.quizlight.org"; // Replace with your actual domain
$from_email = "noreply@quizlight.org"; // Replace with your email

// Establish database connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Helper function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Helper function to check if user is an admin
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

// Helper function to require login
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /login.php");
        exit;
    }
}

// Helper function to require admin rights
function requireAdmin() {
    if (!isLoggedIn() || !isAdmin()) {
        header("Location: /");
        exit;
    }
}