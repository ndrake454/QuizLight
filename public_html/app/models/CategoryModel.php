<?php
/**
 * Category Model
 */
class CategoryModel extends BaseModel {
    protected $table = 'categories';
    
    /**
     * Get all active categories ordered by name
     * 
     * @return array
     */
    public function getAllActive() {
        $sql = "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Create a new category
     * 
     * @param string $name
     * @param string $description
     * @param int $isActive
     * @return int|false Category ID or false on failure
     */
    public function createCategory($name, $description = '', $isActive = 1) {
        $data = [
            'name' => $name,
            'description' => $description,
            'is_active' => $isActive,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->create($data);
    }
    
    /**
     * Toggle category active status
     * 
     * @param int $id
     * @return bool
     */
    public function toggleActive($id) {
        $category = $this->find($id);
        
        if (!$category) {
            return false;
        }
        
        $newStatus = $category['is_active'] ? 0 : 1;
        
        return $this->update($id, [
            'is_active' => $newStatus
        ]);
    }
}