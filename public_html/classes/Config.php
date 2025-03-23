<?php
/**
 * Configuration Class
 * 
 * Centralized configuration management for the application
 */
class Config {
    private static $config = [];
    private static $loaded = false;
    
    /**
     * Load configuration from files
     * 
     * @return bool Success status
     */
    public static function load() {
        if (self::$loaded) {
            return true;
        }
        
        // Load base configuration
        $configPath = __DIR__ . '/../config/config.php';
        if (file_exists($configPath)) {
            self::$config = require $configPath;
        }
        
        // Load environment-specific configuration if exists
        $envConfigPath = __DIR__ . '/../config/config.local.php';
        if (file_exists($envConfigPath)) {
            $envConfig = require $envConfigPath;
            self::$config = array_merge(self::$config, $envConfig);
        }
        
        self::$loaded = true;
        return true;
    }
    
    /**
     * Get a configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }
        
        if (isset(self::$config[$key])) {
            return self::$config[$key];
        }
        
        return $default;
    }
    
    /**
     * Set a configuration value at runtime
     * 
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     */
    public static function set($key, $value) {
        if (!self::$loaded) {
            self::load();
        }
        
        self::$config[$key] = $value;
    }
    
    /**
     * Check if a configuration key exists
     * 
     * @param string $key Configuration key
     * @return bool True if the key exists
     */
    public static function has($key) {
        if (!self::$loaded) {
            self::load();
        }
        
        return isset(self::$config[$key]);
    }
    
    /**
     * Get all configuration values
     * 
     * @return array All configuration values
     */
    public static function all() {
        if (!self::$loaded) {
            self::load();
        }
        
        return self::$config;
    }
}