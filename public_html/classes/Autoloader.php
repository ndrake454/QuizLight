<?php
/**
 * Autoloader Class
 * 
 * Automatically loads classes from appropriate directories
 */
class Autoloader {
    /**
     * Register the autoloader
     */
    public static function register() {
        spl_autoload_register(function ($className) {
            // Directories to search for classes
            $directories = [
                'classes/',
                'controllers/',
                'models/'
            ];
            
            // Class file name (assumes class name matches file name)
            $classFile = $className . '.php';
            
            // Loop through directories and check if class file exists
            foreach ($directories as $directory) {
                $file = dirname(__FILE__) . '/../' . $directory . $classFile;
                
                if (file_exists($file)) {
                    require_once $file;
                    return true;
                }
            }
            
            return false;
        });
    }
}