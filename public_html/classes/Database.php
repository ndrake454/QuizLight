<?php
/**
 * Database Abstraction Layer
 * 
 * This class provides a clean interface for database operations
 * and reduces code duplication throughout the application.
 */
class Database {
    private static $instance = null;
    private $pdo;
    
    /**
     * Private constructor to enforce singleton pattern
     */
    private function __construct() {
        $host = Config::get('db_host');
        $dbname = Config::get('db_name');
        $username = Config::get('db_username');
        $password = Config::get('db_password');
        
        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch(PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get database instance (singleton pattern)
     * 
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get the PDO object for direct manipulation
     * 
     * @return PDO
     */
    public function getPdo() {
        return $this->pdo;
    }
    
    /**
     * Execute a query and return affected rows
     * 
     * @param string $sql The SQL query
     * @param array $params The parameters to bind
     * @return int Number of affected rows
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->logError($e, $sql, $params);
            throw $e;
        }
    }
    
    /**
     * Execute a query and fetch all results
     * 
     * @param string $sql The SQL query
     * @param array $params The parameters to bind
     * @return array Results as an associative array
     */
    public function fetchAll($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logError($e, $sql, $params);
            throw $e;
        }
    }
    
    /**
     * Execute a query and fetch a single row
     * 
     * @param string $sql The SQL query
     * @param array $params The parameters to bind
     * @return array|null Single row as an associative array or null if no results
     */
    public function fetchOne($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result !== false ? $result : null;
        } catch (PDOException $e) {
            $this->logError($e, $sql, $params);
            throw $e;
        }
    }
    
    /**
     * Execute a query and fetch a single value
     * 
     * @param string $sql The SQL query
     * @param array $params The parameters to bind
     * @return mixed|null Single value or null if no results
     */
    public function fetchValue($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchColumn();
            return $result !== false ? $result : null;
        } catch (PDOException $e) {
            $this->logError($e, $sql, $params);
            throw $e;
        }
    }
    
    /**
     * Begin a transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commit a transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Roll back a transaction
     */
    public function rollback() {
        return $this->pdo->rollBack();
    }
    
    /**
     * Insert a record into a table
     * 
     * @param string $table Table name
     * @param array $data Associative array of column => value pairs
     * @return int Last insert ID
     */
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_values($data));
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            $this->logError($e, $sql, array_values($data));
            throw $e;
        }
    }
    
    /**
     * Update records in a table
     * 
     * @param string $table Table name
     * @param array $data Associative array of column => value pairs
     * @param string $where WHERE clause
     * @param array $whereParams Parameters for the WHERE clause
     * @return int Number of affected rows
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        foreach(array_keys($data) as $column) {
            $setParts[] = "$column = ?";
        }
        $setClause = implode(', ', $setParts);
        
        $sql = "UPDATE $table SET $setClause WHERE $where";
        $params = array_merge(array_values($data), $whereParams);
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->logError($e, $sql, $params);
            throw $e;
        }
    }
    
    /**
     * Delete records from a table
     * 
     * @param string $table Table name
     * @param string $where WHERE clause
     * @param array $params Parameters for the WHERE clause
     * @return int Number of affected rows
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->logError($e, $sql, $params);
            throw $e;
        }
    }
    
    /**
     * Log database errors to error log
     * 
     * @param PDOException $e The exception
     * @param string $sql The SQL query
     * @param array $params The query parameters
     */
    private function logError($e, $sql, $params) {
        $message = $e->getMessage();
        $trace = $e->getTraceAsString();
        $paramsStr = print_r($params, true);
        
        error_log("Database Error: $message\nSQL: $sql\nParams: $paramsStr\nTrace: $trace");
    }
}