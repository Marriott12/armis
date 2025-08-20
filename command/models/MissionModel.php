<?php
/**
 * ARMIS Command Module - Mission Model
 * Data access layer for mission operations
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(dirname(__DIR__)) . '/shared/database_connection.php';

class MissionModel {
    private $pdo;
    private $table = 'missions';
    
    public function __construct($pdo = null) {
        $this->pdo = $pdo ?: getDbConnection();
    }
    
    /**
     * Get all missions with optional filters
     */
    public function getAll($filters = []) {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['priority'])) {
            $sql .= " AND priority = ?";
            $params[] = $filters['priority'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (mission_name LIKE ? OR description LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY priority DESC, start_date ASC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . intval($filters['limit']);
        }
        
        return fetchAll($sql, $params) ?: [];
    }
    
    /**
     * Get mission by ID
     */
    public function getById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        return fetchOne($sql, [$id]);
    }
    
    /**
     * Create new mission
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table} (mission_name, description, start_date, end_date, priority, status, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, 'Planning', ?, NOW())";
        
        return executeQuery($sql, [
            $data['mission_name'],
            $data['description'],
            $data['start_date'],
            $data['end_date'] ?? null,
            $data['priority'],
            $_SESSION['user_id']
        ]);
    }
    
    /**
     * Update mission
     */
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            if (in_array($field, ['mission_name', 'description', 'start_date', 'end_date', 'priority', 'status'])) {
                $fields[] = "$field = ?";
                $params[] = $value;
            }
        }
        
        if (empty($fields)) {
            throw new Exception('No valid fields to update');
        }
        
        $fields[] = "updated_at = NOW()";
        $params[] = $id;
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = ?";
        return executeQuery($sql, $params);
    }
    
    /**
     * Delete mission (soft delete - set status to cancelled)
     */
    public function delete($id) {
        return $this->update($id, ['status' => 'Cancelled']);
    }
    
    /**
     * Get missions by status
     */
    public function getByStatus($status) {
        return $this->getAll(['status' => $status]);
    }
    
    /**
     * Get active missions
     */
    public function getActive() {
        return $this->getByStatus('Active');
    }
    
    /**
     * Get mission statistics
     */
    public function getStatistics() {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN priority = 'Critical' THEN 1 ELSE 0 END) as critical
                FROM {$this->table}";
        
        return fetchOne($sql);
    }
}