<?php
/**
 * Base Model Class
 * 
 * Provides common functionality for all models
 */
class BaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Find a record by its primary key
     * 
     * @param int $id
     * @return array|false
     */
    public function find($id) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get all records from the table
     * 
     * @param string $orderBy Column to order by
     * @param string $direction Sort direction (ASC or DESC)
     * @return array
     */
    public function all($orderBy = null, $direction = 'ASC') {
        $sql = "SELECT * FROM {$this->table}";
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy} {$direction}";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Create a new record
     * 
     * @param array $data
     * @return int|false The ID of the new record or false on failure
     */
    public function create($data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute(array_values($data));
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Update a record
     * 
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update($id, $data) {
        $setClause = implode(' = ?, ', array_keys($data)) . ' = ?';
        
        $sql = "UPDATE {$this->table} SET {$setClause} WHERE {$this->primaryKey} = ?";
        
        $values = array_values($data);
        $values[] = $id;
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }
    
    /**
     * Delete a record
     * 
     * @param int $id
     * @return bool
     */
    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Find records by a specific column
     * 
     * @param string $column
     * @param mixed $value
     * @return array
     */
    public function findBy($column, $value) {
        $sql = "SELECT * FROM {$this->table} WHERE {$column} = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$value]);
        return $stmt->fetchAll();
    }
    
    /**
     * Find a single record by a specific column
     * 
     * @param string $column
     * @param mixed $value
     * @return array|false
     */
    public function findOneBy($column, $value) {
        $sql = "SELECT * FROM {$this->table} WHERE {$column} = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$value]);
        return $stmt->fetch();
    }
    
    /**
     * Count all records in the table
     * 
     * @return int
     */
    public function count() {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    /**
     * Execute a custom query
     * 
     * @param string $sql
     * @param array $params
     * @return PDOStatement
     */
    public function query($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}