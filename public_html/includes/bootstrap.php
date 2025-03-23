<?php
/**
 * Enhanced routing system
 * Supports route parameters, query strings, and better path matching
 */
class Router {
    private static $routes = [];
    private static $matchedRoute = false;
    
    /**
     * Register a route
     *
     * @param string $method HTTP method (GET, POST, etc)
     * @param string $path Route path
     * @param string $controller Controller class name
     * @param string $action Controller method to call
     */
    public static function add($method, $path, $controller, $action) {
        // Normalize path to not have trailing slash except for root
        if ($path !== '/' && substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        }
        
        self::$routes[] = [
            'method' => $method,
            'path' => $path,
            'controller' => $controller,
            'action' => $action
        ];
    }
    
    /**
     * Add a GET route
     */
    public static function get($path, $controller, $action) {
        self::add('GET', $path, $controller, $action);
    }
    
    /**
     * Add a POST route
     */
    public static function post($path, $controller, $action) {
        self::add('POST', $path, $controller, $action);
    }
    
    /**
     * Dispatch the request to the appropriate controller
     */
    public static function dispatch() {
        // Get request method and path
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];
        
        // Extract the path and query string
        $parsedUrl = parse_url($uri);
        $path = $parsedUrl['path'] ?? '/';
        
        // Remove trailing slash for matching (except for root path)
        if ($path !== '/' && substr($path, -1) === '/') {
            $path = rtrim($path, '/');
        }
        
        // Debug output
        if (defined('DISPLAY_ERRORS') && DISPLAY_ERRORS) {
            echo "<!-- DEBUG: Method: $method, Path: $path -->\n";
        }
        
        // Try to match the route
        foreach (self::$routes as $route) {
            if ($route['method'] === $method || $route['method'] === 'ANY') {
                // Exact match
                if ($route['path'] === $path) {
                    return self::executeRoute($route);
                }
            }
        }
        
        // No route found
        return false;
    }
    
    /**
     * Execute a matched route
     */
    private static function executeRoute($route) {
        $controllerName = $route['controller'];
        $actionName = $route['action'];
        $controllerFile = APP_PATH . '/controllers/' . $controllerName . '.php';
        
        if (file_exists($controllerFile)) {
            require_once $controllerFile;
            
            if (class_exists($controllerName)) {
                $controller = new $controllerName();
                
                if (method_exists($controller, $actionName)) {
                    self::$matchedRoute = true;
                    $controller->$actionName();
                    return true;
                } else {
                    // Method doesn't exist
                    if (defined('DISPLAY_ERRORS') && DISPLAY_ERRORS) {
                        echo "<!-- DEBUG: Method $actionName not found in controller $controllerName -->\n";
                    }
                }
            } else {
                // Class doesn't exist
                if (defined('DISPLAY_ERRORS') && DISPLAY_ERRORS) {
                    echo "<!-- DEBUG: Controller class $controllerName not found -->\n";
                }
            }
        } else {
            // File doesn't exist
            if (defined('DISPLAY_ERRORS') && DISPLAY_ERRORS) {
                echo "<!-- DEBUG: Controller file $controllerFile not found -->\n";
            }
        }
        
        return false;
    }
    
    /**
     * Check if a route was matched
     */
    public static function hasMatch() {
        return self::$matchedRoute;
    }
}

// Legacy route function for backward compatibility
function route($path, $controller, $method) {
    Router::add('ANY', $path, $controller, $method);
    return false; // Don't execute immediately, let Router::dispatch() handle it
}