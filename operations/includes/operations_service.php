<?php
/**
 * ARMIS Operations Service
 * Business logic layer for operations management
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once dirname(dirname(__DIR__)) . '/shared/database_connection.php';

class OperationsService {
    private $pdo;
    
    public function __construct($pdo = null) {
        $this->pdo = $pdo ?: getDbConnection();
    }
    
    public function getDashboardData() {
        return [
            'stats' => OperationsUtils::getOperationsStats(),
            'active_operations' => OperationsUtils::getActiveOperations(5),
            'recent_deployments' => $this->getRecentDeployments(5),
            'resource_status' => $this->getResourceStatus()
        ];
    }
    
    public function createOperation($data) {
        $required = ['operation_name', 'description', 'start_date', 'priority', 'classification'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field {$field} is required");
            }
        }
        
        $operationId = OperationsUtils::createOperation($data);
        
        if ($operationId) {
            logOperationsActivity('operation_created', "Operation '{$data['operation_name']}' created", [
                'operation_id' => $operationId,
                'classification' => $data['classification']
            ]);
        }
        
        return $operationId;
    }
    
    public function searchOperations($params = []) {
        $sql = "SELECT * FROM operations WHERE 1=1";
        $bindings = [];
        
        if (!empty($params['status'])) {
            $sql .= " AND status = ?";
            $bindings[] = $params['status'];
        }
        
        if (!empty($params['classification'])) {
            $sql .= " AND classification = ?";
            $bindings[] = $params['classification'];
        }
        
        if (!empty($params['search'])) {
            $sql .= " AND (operation_name LIKE ? OR description LIKE ?)";
            $searchTerm = '%' . $params['search'] . '%';
            $bindings[] = $searchTerm;
            $bindings[] = $searchTerm;
        }
        
        $sql .= " ORDER BY priority DESC, start_date ASC";
        
        if (!empty($params['limit'])) {
            $sql .= " LIMIT " . intval($params['limit']);
        }
        
        return fetchAll($sql, $bindings) ?: [];
    }
    
    private function getRecentDeployments($limit) {
        $sql = "SELECT * FROM deployments ORDER BY deployment_date DESC LIMIT ?";
        return fetchAll($sql, [$limit]) ?: [];
    }
    
    private function getResourceStatus() {
        $sql = "SELECT 
                    resource_type,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as available
                FROM resources
                GROUP BY resource_type";
        
        return fetchAll($sql) ?: [];
    }
}