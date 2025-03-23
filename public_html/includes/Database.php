<?php
/**
 * Database Connection Class
 * 
 * Provides a singleton PDO instance for database operations
 */
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        require_once dirname(__DIR__) . '/app/config/config.php';
        
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    // Prevent cloning of the instance
    private function __clone() {}
    
    /**
     * Get database instance
     * 
     * @return PDO
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->pdo;
    }
    
    /**
     * Run a query with parameters
     * 
     * @param string $sql
     * @param array $params
     * @return PDOStatement
     */
    public static function query($sql, $params = []) {
        $pdo = self::getInstance();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}