<?php
/**
 * Application Entry Point
 * 
 * All requests are routed through this file
 */

// Load configuration
require_once 'app/config/config.php';

// Load core files
require_once 'includes/autoload.php';
require_once 'includes/functions.php';
require_once 'includes/Database.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => true
    ]);
}

// Simple direct routing
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Remove trailing slash except for root path
if ($path != '/' && substr($path, -1) == '/') {
    $path = rtrim($path, '/');
}

// Debug info (remove in production)
if (defined('DISPLAY_ERRORS') && DISPLAY_ERRORS) {
    echo "<!-- DEBUG: Path: $path -->\n";
}

// Route to the appropriate controller and method
switch ($path) {
    case '/':
        require_once 'app/controllers/HomeController.php';
        $controller = new HomeController();
        $controller->index();
        break;
    
    case '/login':
        require_once 'app/controllers/AuthController.php';
        $controller = new AuthController();
        $controller->login();
        break;
    
    case '/login/process':
        require_once 'app/controllers/AuthController.php';
        $controller = new AuthController();
        $controller->processLogin();
        break;
    
    case '/register':
        require_once 'app/controllers/AuthController.php';
        $controller = new AuthController();
        $controller->register();
        break;
    
    case '/register/process':
        require_once 'app/controllers/AuthController.php';
        $controller = new AuthController();
        $controller->processRegister();
        break;
    
    case '/logout':
        require_once 'app/controllers/AuthController.php';
        $controller = new AuthController();
        $controller->logout();
        break;
    
    case '/profile':
        require_once 'app/controllers/UserController.php';
        $controller = new UserController();
        $controller->profile();
        break;
    
    case '/quiz-select':
        require_once 'app/controllers/QuizController.php';
        $controller = new QuizController();
        $controller->select();
        break;
    
    case '/quiz':
        require_once 'app/controllers/QuizController.php';
        $controller = new QuizController();
        $controller->take();
        break;
    
    case '/quiz/start':
        require_once 'app/controllers/QuizController.php';
        $controller = new QuizController();
        $controller->start();
        break;
    
    case '/quiz/submit-answer':
        require_once 'app/controllers/QuizController.php';
        $controller = new QuizController();
        $controller->submitAnswer();
        break;
    
    case '/quiz/results':
        require_once 'app/controllers/QuizController.php';
        $controller = new QuizController();
        $controller->results();
        break;
    
    case '/quiz/practice':
        require_once 'app/controllers/QuizController.php';
        $controller = new QuizController();
        $controller->practice();
        break;
    
    case '/verify':
        require_once 'app/controllers/AuthController.php';
        $controller = new AuthController();
        $controller->verify();
        break;
    
    case '/about':
        require_once 'app/controllers/HomeController.php';
        $controller = new HomeController();
        $controller->about();
        break;
    
    case '/privacy':
        require_once 'app/controllers/HomeController.php';
        $controller = new HomeController();
        $controller->privacy();
        break;
    
    // Admin routes
    case '/admin':
        require_once 'app/controllers/AdminController.php';
        $controller = new AdminController();
        $controller->dashboard();
        break;
    
    case '/admin/users':
        require_once 'app/controllers/AdminController.php';
        $controller = new AdminController();
        $controller->users();
        break;
    
    case '/admin/questions':
        require_once 'app/controllers/AdminController.php';
        $controller = new AdminController();
        $controller->questions();
        break;
    
    case '/admin/categories':
        require_once 'app/controllers/AdminController.php';
        $controller = new AdminController();
        $controller->categories();
        break;
    
    case '/admin/question-form':
        require_once 'app/controllers/AdminController.php';
        $controller = new AdminController();
        $controller->questionForm();
        break;
    
    case '/admin/toggle-admin':
        require_once 'app/controllers/AdminController.php';
        $controller = new AdminController();
        $controller->toggleAdmin();
        break;
    
    case '/admin/delete-user':
        require_once 'app/controllers/AdminController.php';
        $controller = new AdminController();
        $controller->deleteUser();
        break;
    
    case '/admin/create-category':
        require_once 'app/controllers/AdminController.php';
        $controller = new AdminController();
        $controller->createCategory();
        break;
    
    case '/admin/update-category':
        require_once 'app/controllers/AdminController.php';
        $controller = new AdminController();
        $controller->updateCategory();
        break;
    
    case '/admin/toggle-category':
        require_once 'app/controllers/AdminController.php';
        $controller = new AdminController();
        $controller->toggleCategory();
        break;
    
    case '/admin/delete-category':
        require_once 'app/controllers/AdminController.php';
        $controller = new AdminController();
        $controller->deleteCategory();
        break;
    
    case '/admin/save-question':
        require_once 'app/controllers/AdminController.php';
        $controller = new AdminController();
        $controller->saveQuestion();
        break;
    
    case '/admin/toggle-question':
        require_once 'app/controllers/AdminController.php';
        $controller = new AdminController();
        $controller->toggleQuestion();
        break;
    
    case '/admin/delete-question':
        require_once 'app/controllers/AdminController.php';
        $controller = new AdminController();
        $controller->deleteQuestion();
        break;
    
    default:
        // 404 - Page not found
        require_once 'app/controllers/ErrorController.php';
        $controller = new ErrorController();
        $controller->notFound();
        break;
}