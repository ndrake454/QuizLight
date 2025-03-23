<?php
/**
 * Base Controller Class
 * 
 * Provides common functionality for all controllers
 */
class BaseController {
    protected $viewPath = APP_PATH . '/views/';
    
    // Don't define a constructor in the base class
    // Or if you need one, make it a simple initialization:
    public function __construct() {
        // Simple initialization without any complex dependencies
    }
    
    /**
     * Render a view
     * 
     * @param string $view Path to the view file
     * @param array $data Data to pass to the view
     * @param string $layout Layout to use (defaults to 'main')
     * @return void
     */
    protected function render($view, $data = [], $layout = 'main') {
        // Make data variables accessible in the view
        extract($data);
        
        // Get flash message if any
        $flash = getFlashMessage();
        
        // Start output buffering
        ob_start();
        
        // Include the view file
        include $this->viewPath . $view . '.php';
        
        // Get the view content
        $content = ob_get_clean();
        
        // If layout is null, return the view content without a layout
        if ($layout === null) {
            echo $content;
            return;
        }
        
        // Otherwise, include the layout
        include $this->viewPath . 'layouts/' . $layout . '.php';
    }
    
    /**
     * Render JSON response
     * 
     * @param array $data
     * @param int $statusCode
     * @return void
     */
    protected function json($data, $statusCode = 200) {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }
    
    /**
     * Return input value from previous request (for form repopulation)
     * 
     * @param string $field Field name
     * @param string $default Default value
     * @return string
     */
    protected function old($field, $default = '') {
        return isset($_SESSION['old'][$field]) ? $_SESSION['old'][$field] : $default;
    }
    
    /**
     * Store old input in session
     * 
     * @param array $data Input data
     * @return void
     */
    protected function storeOldInput($data = null) {
        if ($data === null) {
            $data = $_POST;
        }
        
        $_SESSION['old'] = $data;
    }
    
    /**
     * Clear old input
     * 
     * @return void
     */
    protected function clearOldInput() {
        unset($_SESSION['old']);
    }
    
    /**
     * Validate required fields
     * 
     * @param array $fields Field names
     * @param array $data Input data
     * @return array Array of missing fields
     */
    protected function validateRequired($fields, $data = null) {
        if ($data === null) {
            $data = $_POST;
        }
        
        $missing = [];
        
        foreach ($fields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                $missing[] = $field;
            }
        }
        
        return $missing;
    }
    
    /**
     * Redirect to a URL
     * 
     * @param string $url
     * @return void
     */
    protected function redirect($url) {
        header("Location: $url");
        exit;
    }
}