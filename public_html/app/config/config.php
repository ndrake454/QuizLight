<?php
/**
 * Application Configuration
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', '-');  // Replace with your database name
define('DB_USER', '-');  // Replace with your database username
define('DB_PASS', '-');  // Replace with your database password

// Site Configuration
define('SITE_NAME', 'QuizLight');
define('SITE_URL', 'https://www.quizlight.org'); // Replace with your site URL (no trailing slash)
define('FROM_EMAIL', 'noreply@quizlight.org'); // Replace with your email

// Session Configuration
define('SESSION_SECURE', true);
define('SESSION_HTTP_ONLY', true);

// Path Configuration
define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
define('APP_PATH', ROOT_PATH . '/app');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

// Error Reporting (set to false in production)
define('DISPLAY_ERRORS', true);