<?php
/**
 * Class Autoloader
 */
function autoload($className) {
    // Convert namespace to full file path
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $className);
    
    // Check in different directories
    $directories = [
        APP_PATH . '/controllers/',
        APP_PATH . '/models/',
        APP_PATH . '/services/',
        ROOT_PATH . '/includes/'
    ];
    
    foreach ($directories as $directory) {
        if (file_exists($directory . $className . '.php')) {
            require_once $directory . $className . '.php';
            return;
        }
    }
}

spl_autoload_register('autoload');