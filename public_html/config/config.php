<?php
/**
 * Application Configuration
 * 
 * This file contains the main configuration for the application.
 * For local development, create a config.local.php file with overrides.
 */

return [
    // Database settings
    'db_host' => 'localhost',
    'db_name' => 'dbdr24slrhxi1h',
    'db_username' => 'unqiadb7jug2u',
    'db_password' => '.2(hA|#1d&23',
    
    // Site settings
    'site_name' => 'QuizLight',
    'site_url' => 'https://quizlight.org',
    'from_email' => 'admin@quizlight.org',
    
    // Security settings
    'session_lifetime' => 86400,  // 24 hours
    'password_min_length' => 8,
    
    // Application settings
    'items_per_page' => 10,
    'log_level' => 'error',        // none, error, warning, info, debug
    'debug_mode' => false,
    
    // Quiz settings
    'default_quiz_questions' => 10,
    'max_quiz_questions' => 50,
    'default_quiz_difficulty' => 'medium',
    
    // Cache settings
    'cache_enabled' => true,
    'cache_lifetime' => 3600,      // 1 hour
];